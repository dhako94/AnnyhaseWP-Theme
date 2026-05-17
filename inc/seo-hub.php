<?php
/**
 * SEO Hub
 *
 * Admin page (position 8, dashicons-chart-line) with three tabs:
 *   1. Yoast Einrichtung  – one-click Yoast template configuration
 *   2. Kategorien SEO     – per-category focus keyword and meta-desc suffix
 *   3. SEO Diagnose       – per-product SEO status table with coverage bars
 */
defined('ABSPATH') || exit;

/* =================================================================
   MENU REGISTRATION
   ================================================================= */

add_action('admin_menu', function (): void {
    add_menu_page(
        'SEO Hub',
        'SEO Hub',
        'manage_options',
        'annyhase-seo-hub',
        'annyhase_seo_hub_page',
        'dashicons-chart-line',
        8
    );
}, 20);

/* =================================================================
   AJAX – apply Yoast templates
   ================================================================= */

add_action('wp_ajax_annyhase_apply_yoast_config', function (): void {
    check_ajax_referer('annyhase_seo_hub', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['msg' => 'Unauthorized']);

    if (!defined('WPSEO_VERSION')) {
        wp_send_json_error(['msg' => 'Yoast SEO ist nicht aktiv.']);
    }

    $opt = get_option('wpseo_titles', []);

    // Produkt: title uses our custom variables; meta desc is set per-product via sync.
    $opt['title-produkt']                 = '%%short_title%% – %%produkt_kat%% | %%sitename%%';
    $opt['metadesc-produkt']              = '';

    // Produktkategorie archive templates.
    $opt['title-tax-produktkategorie']    = '%%term_title%% – Handgetöpferte Keramik | %%sitename%%';
    $opt['metadesc-tax-produktkategorie'] = '%%term_description%%';

    update_option('wpseo_titles', $opt);
    wp_send_json_success(['msg' => 'Yoast-Templates wurden aktualisiert.']);
});

/* =================================================================
   admin_post – save sync/filter settings
   ================================================================= */

add_action('admin_post_annyhase_save_sync_settings', function (): void {
    check_admin_referer('annyhase_save_sync_settings');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');

    update_option('annyhase_desc_filter_enabled', !empty($_POST['desc_filter_enabled']) ? '1' : '0');
    $custom = sanitize_textarea_field(wp_unslash($_POST['desc_filter_custom'] ?? ''));
    update_option('annyhase_desc_filter_custom', $custom);

    wp_safe_redirect(add_query_arg(
        ['page' => 'annyhase-seo-hub', 'tab' => 'yoast', 'saved' => '1'],
        admin_url('admin.php')
    ));
    exit;
});

/* =================================================================
   admin_post – save all category SEO fields at once
   ================================================================= */

add_action('admin_post_annyhase_save_seo_categories', function (): void {
    check_admin_referer('annyhase_save_seo_categories');
    if (!current_user_can('manage_categories')) wp_die('Unauthorized');

    // Map of POST key suffix → [meta key, sanitizer]
    $field_map = [
        'seo_kw_'        => ['_annyhase_seo_kw',             'sanitize_text_field'],
        'seo_desc_'      => ['_annyhase_seo_desc',           'sanitize_textarea_field'],
        'title_prefix_'  => ['_annyhase_title_prefix',       'sanitize_text_field'],
        'title_suffix_'  => ['_annyhase_title_suffix',       'sanitize_text_field'],
        'fkw_addon_'     => ['_annyhase_focuskw_addon',      'sanitize_text_field'],
        'pers_sfx_'      => ['_annyhase_personalizable_sfx', 'sanitize_text_field'],
        'intro_tpl_'     => ['_annyhase_intro_template',     'sanitize_textarea_field'],
    ];

    $terms = get_terms(['taxonomy' => 'produktkategorie', 'hide_empty' => false]);
    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            $tid = (int) $term->term_id;
            foreach ($field_map as $post_pfx => [$meta_key, $sanitizer]) {
                $raw = wp_unslash($_POST[$post_pfx . $tid] ?? '');
                update_term_meta($tid, $meta_key, $sanitizer($raw));
            }
        }
    }

    wp_safe_redirect(add_query_arg(
        ['page' => 'annyhase-seo-hub', 'tab' => 'categories', 'saved' => '1'],
        admin_url('admin.php')
    ));
    exit;
});

/* =================================================================
   MAIN PAGE CALLBACK
   ================================================================= */

function annyhase_seo_hub_page(): void {
    $active = sanitize_key($_GET['tab'] ?? 'yoast');
    $saved  = !empty($_GET['saved']);
    $tabs   = [
        'yoast'       => 'Yoast Einrichtung',
        'categories'  => 'Kategorien SEO',
        'diagnostics' => 'SEO Diagnose',
    ];
    ?>
    <div class="wrap">
    <h1 style="display:flex;align-items:center;gap:.5rem">
        <span class="dashicons dashicons-chart-line" style="font-size:1.5rem;width:auto;height:auto;color:#c4704a;margin-top:2px"></span>
        SEO Hub
    </h1>

    <?php if ($saved): ?>
    <div class="notice notice-success is-dismissible"><p>Änderungen gespeichert.</p></div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper" style="margin-bottom:1.5rem">
        <?php foreach ($tabs as $slug => $label): ?>
        <a href="<?php echo esc_url(add_query_arg(['page' => 'annyhase-seo-hub', 'tab' => $slug], admin_url('admin.php'))); ?>"
           class="nav-tab<?php echo $active === $slug ? ' nav-tab-active' : ''; ?>">
            <?php echo esc_html($label); ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <?php
    if ($active === 'categories')  annyhase_seo_hub_tab_categories();
    elseif ($active === 'diagnostics') annyhase_seo_hub_tab_diagnostics();
    else                            annyhase_seo_hub_tab_yoast();
    ?>
    </div>
    <?php
}

/* =================================================================
   TAB 1 – Yoast Einrichtung
   ================================================================= */

function annyhase_seo_hub_tab_yoast(): void {
    $yoast_active = defined('WPSEO_VERSION');
    $opt          = get_option('wpseo_titles', []);

    $rows = [
        ['Produkt – Titel',       'title-produkt',                 '%%short_title%% – %%produkt_kat%% | %%sitename%%'],
        ['Produkt – Meta Desc',   'metadesc-produkt',              '(leer — wird per Sync pro Produkt gesetzt)'],
        ['Kategorie – Titel',     'title-tax-produktkategorie',    '%%term_title%% – Handgetöpferte Keramik | %%sitename%%'],
        ['Kategorie – Meta Desc', 'metadesc-tax-produktkategorie', '%%term_description%%'],
    ];
    ?>
    <div style="max-width:900px">

    <?php if (!$yoast_active): ?>
    <div class="notice notice-warning inline"><p>
        <strong>Yoast SEO ist nicht aktiv.</strong>
        Installiere und aktiviere Yoast SEO, bevor du diese Konfiguration anwendest.
    </p></div>
    <?php else: ?>

    <!-- Current vs. recommended table -->
    <div style="background:#fff;border:1px solid #e2e4e7;border-radius:8px;padding:1.25rem;margin-bottom:1.25rem">
        <h3 style="margin-top:0;font-size:.95rem">Aktuelle vs. empfohlene Templates</h3>
        <table class="widefat striped" style="font-size:.85rem">
            <thead>
                <tr>
                    <th style="width:200px">Bereich</th>
                    <th>Aktuell in Yoast</th>
                    <th>Empfohlen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as [$label, $key, $recommended]):
                    $current = $opt[$key] ?? '(nicht gesetzt)';
                    $match   = $current === $recommended || ($recommended === '(leer — wird per Sync pro Produkt gesetzt)' && !$current);
                ?>
                <tr>
                    <td><?php echo esc_html($label); ?></td>
                    <td>
                        <code><?php echo esc_html($current ?: '(leer)'); ?></code>
                        <?php if ($match): ?>
                        <span style="color:#22c55e;margin-left:.3rem">✓</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?php echo esc_html($recommended); ?></code></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Explanation box -->
    <div style="background:#f0f6fc;border:1px solid #b8d4ea;border-radius:8px;padding:1.25rem;margin-bottom:1.25rem">
        <h3 style="margin-top:0;font-size:.95rem">Was wird konfiguriert?</h3>
        <ul style="margin:0;padding-left:1.4rem;line-height:1.85;color:#444;font-size:.88rem">
            <li><strong>%%short_title%%</strong> – Produkttitel auf ~40 Zeichen gekürzt (custom variable, bereits registriert).</li>
            <li><strong>%%produkt_kat%%</strong> – Name der ersten Produktkategorie (custom variable, bereits registriert).</li>
            <li>Produkt-Meta-Description wird leer gelassen — der Etsy-Sync setzt pro Produkt automatisch einen individuellen Wert.</li>
            <li>Kategorie-Archive nutzen das native WordPress "Beschreibung"-Feld via <code>%%term_description%%</code>.</li>
        </ul>
    </div>

    <div id="yoast-result" style="display:none;margin-bottom:.85rem"></div>
    <button id="btn-apply-yoast" class="button button-primary button-large">
        Yoast-Templates jetzt konfigurieren
    </button>

    <script>
    (function(){
        var btn = document.getElementById('btn-apply-yoast');
        var res = document.getElementById('yoast-result');
        if (!btn) return;
        btn.addEventListener('click', function(){
            btn.disabled    = true;
            btn.textContent = 'Wird gespeichert…';
            var fd = new FormData();
            fd.append('action', 'annyhase_apply_yoast_config');
            fd.append('nonce',  '<?php echo esc_js(wp_create_nonce('annyhase_seo_hub')); ?>');
            fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {method:'POST',body:fd})
                .then(function(r){ return r.json(); })
                .then(function(d){
                    res.style.display = '';
                    if (d.success) {
                        res.innerHTML = '<div class="notice notice-success inline" style="margin:0"><p>✓ ' + d.data.msg + '</p></div>';
                        btn.textContent = '✓ Erfolgreich konfiguriert';
                        setTimeout(function(){ location.reload(); }, 1500);
                    } else {
                        res.innerHTML = '<div class="notice notice-error inline" style="margin:0"><p>Fehler: ' + (d.data && d.data.msg ? d.data.msg : 'Unbekannter Fehler') + '</p></div>';
                        btn.disabled    = false;
                        btn.textContent = 'Yoast-Templates jetzt konfigurieren';
                    }
                })
                .catch(function(){
                    res.style.display = '';
                    res.innerHTML = '<div class="notice notice-error inline" style="margin:0"><p>Netzwerkfehler.</p></div>';
                    btn.disabled    = false;
                    btn.textContent = 'Yoast-Templates jetzt konfigurieren';
                });
        });
    })();
    </script>

    <?php endif; ?>

    <!-- Description Filter Settings -->
    <?php
    $filter_on     = get_option('annyhase_desc_filter_enabled', '1') !== '0';
    $filter_custom = (string) get_option('annyhase_desc_filter_custom', '');
    $builtin = [
        'gem. §19 UStG …',
        'Gemäß §19 …',
        'Kleinunternehmer …',
        'Es wird keine Mehrwertsteuer …',
        'kein Ausweis von Mehrwertsteuer …',
        'keine Umsatzsteuer …',
    ];
    ?>
    <div style="background:#fff;border:1px solid #e2e4e7;border-radius:8px;padding:1.25rem;margin-top:1.25rem">
        <h3 style="margin-top:0;font-size:.95rem;font-weight:700">Beschreibungs-Filter (§19 UStG &amp; Boilerplate)</h3>
        <p style="font-size:.85rem;color:#555;margin-bottom:1rem">
            Entfernt automatisch rechtliche Pflichthinweise und Boilerplate aus importierten Etsy-Beschreibungen,
            bevor der Text auf der Website gespeichert wird.
            Eigene Texte werden als einfache Substring-Suche (kein Regex) angewendet.
        </p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('annyhase_save_sync_settings'); ?>
            <input type="hidden" name="action" value="annyhase_save_sync_settings">

            <label style="display:flex;align-items:center;gap:.5rem;font-size:.9rem;margin-bottom:1rem;cursor:pointer">
                <input type="checkbox" name="desc_filter_enabled" value="1" <?php checked($filter_on); ?>>
                <strong>Filter aktiv</strong>
                <span style="color:#888;font-size:.82rem">(deaktivieren um Rohtexte von Etsy unverändert zu übernehmen)</span>
            </label>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
                <div>
                    <p style="font-size:.83rem;font-weight:600;margin:0 0 .4rem">Eingebaute Muster (immer aktiv wenn Filter an):</p>
                    <ul style="margin:0;padding-left:1.2rem;font-size:.82rem;color:#555;line-height:1.9">
                        <?php foreach ($builtin as $b): ?>
                        <li><code><?php echo esc_html($b); ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <label for="desc_filter_custom" style="display:block;font-size:.83rem;font-weight:600;margin-bottom:.25rem">
                        Eigene Filter-Texte (einer pro Zeile):
                    </label>
                    <textarea id="desc_filter_custom" name="desc_filter_custom"
                              rows="7" style="width:100%;resize:vertical;font-family:monospace;font-size:.82rem"><?php echo esc_textarea($filter_custom); ?></textarea>
                    <p style="font-size:.72rem;color:#888;margin:.25rem 0 0">
                        Jede Zeile wird als exakter Textblock gesucht und entfernt. Groß-/Kleinschreibung wird ignoriert.
                    </p>
                </div>
            </div>

            <?php submit_button('Filter-Einstellungen speichern', 'secondary', 'submit', false,
                ['style' => 'font-size:.9rem']); ?>
        </form>
    </div>

    </div>
    <?php
}

/* =================================================================
   TAB 2 – Kategorien SEO
   ================================================================= */

function annyhase_seo_hub_tab_categories(): void {
    $terms = get_terms(['taxonomy' => 'produktkategorie', 'hide_empty' => false]);
    if (is_wp_error($terms) || empty($terms)) {
        echo '<p>Keine Produktkategorien vorhanden. <a href="'
            . esc_url(admin_url('edit-tags.php?taxonomy=produktkategorie&post_type=produkt'))
            . '">Jetzt anlegen →</a></p>';
        return;
    }
    $site_name = get_bloginfo('name');
    $site_host = (string) parse_url(home_url(), PHP_URL_HOST);
    ?>
    <div style="display:grid;grid-template-columns:minmax(0,1fr) 305px;gap:1.5rem;align-items:start;max-width:1300px">
    <div><!-- left: form column -->

    <p style="color:#555;margin-bottom:1.5rem;font-size:.9rem">
        Alle Felder werden beim nächsten Etsy-Sync auf die Produkte dieser Kategorie angewendet.
        <strong>Neue Kategorien</strong> erscheinen hier automatisch sobald der Sync sie anlegt — kein Code-Update nötig.
    </p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('annyhase_save_seo_categories'); ?>
        <input type="hidden" name="action" value="annyhase_save_seo_categories">

        <?php foreach ($terms as $term):
            $tid      = (int) $term->term_id;
            $kw       = (string) get_term_meta($tid, '_annyhase_seo_kw',             true);
            $desc_sfx = (string) get_term_meta($tid, '_annyhase_seo_desc',           true);
            $prefix   = (string) get_term_meta($tid, '_annyhase_title_prefix',       true);
            $suffix   = (string) get_term_meta($tid, '_annyhase_title_suffix',       true);
            $fkw_add  = (string) get_term_meta($tid, '_annyhase_focuskw_addon',      true);
            $pers_sfx = (string) get_term_meta($tid, '_annyhase_personalizable_sfx', true);
            $intro    = (string) get_term_meta($tid, '_annyhase_intro_template',     true);
        ?>
        <div class="seo-cat-card"
             data-site="<?php echo esc_attr($site_name); ?>"
             data-cat="<?php echo esc_attr($term->name); ?>"
             style="background:#fff;border:1px solid #e2e4e7;border-radius:8px;padding:1.25rem;margin-bottom:1.5rem">

            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
                <h3 style="margin:0;font-size:1rem;font-weight:700">
                    <?php echo esc_html($term->name); ?>
                    <span style="font-weight:400;color:#777;font-size:.82rem">(<?php echo (int) $term->count; ?> Produkte)</span>
                </h3>
                <a href="<?php echo esc_url(get_term_link($term)); ?>" target="_blank"
                   style="font-size:.8rem;color:#666;text-decoration:none">Archiv ↗</a>
            </div>

            <!-- Row 1: Titel-Prefix + Titel-Suffix + Personalizable-Suffix -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.85rem;margin-bottom:.85rem">
                <div>
                    <label style="display:block;font-size:.83rem;font-weight:600;margin-bottom:.2rem"
                           for="pfx_<?php echo esc_attr($tid); ?>">Titel-Prefix</label>
                    <input type="text" id="pfx_<?php echo esc_attr($tid); ?>"
                           name="title_prefix_<?php echo esc_attr($tid); ?>"
                           value="<?php echo esc_attr($prefix); ?>"
                           placeholder="z.B. Keramik"
                           class="regular-text an-prefix" style="width:100%">
                    <p style="font-size:.72rem;color:#888;margin:.2rem 0 0">Vor dem Produktnamen (leer = kein Prefix)</p>
                </div>
                <div>
                    <label style="display:block;font-size:.83rem;font-weight:600;margin-bottom:.2rem"
                           for="sfx_<?php echo esc_attr($tid); ?>">Titel-Suffix</label>
                    <input type="text" id="sfx_<?php echo esc_attr($tid); ?>"
                           name="title_suffix_<?php echo esc_attr($tid); ?>"
                           value="<?php echo esc_attr($suffix); ?>"
                           placeholder="z.B. nach Maß"
                           class="regular-text an-suffix" style="width:100%">
                    <p style="font-size:.72rem;color:#888;margin:.2rem 0 0">Nach dem Produktnamen</p>
                </div>
                <div>
                    <label style="display:block;font-size:.83rem;font-weight:600;margin-bottom:.2rem"
                           for="pers_<?php echo esc_attr($tid); ?>">Suffix wenn personalisierbar</label>
                    <input type="text" id="pers_<?php echo esc_attr($tid); ?>"
                           name="pers_sfx_<?php echo esc_attr($tid); ?>"
                           value="<?php echo esc_attr($pers_sfx); ?>"
                           placeholder="z.B. personalisiert mit Namen"
                           class="regular-text" style="width:100%">
                    <p style="font-size:.72rem;color:#888;margin:.2rem 0 0">Überschreibt Titel-Suffix wenn is_personalizable</p>
                </div>
            </div>

            <!-- Row 2: Focus KW + Focus-KW Addon + Meta-Desc Suffix -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.85rem;margin-bottom:.85rem">
                <div>
                    <label style="display:block;font-size:.83rem;font-weight:600;margin-bottom:.2rem"
                           for="kw_<?php echo esc_attr($tid); ?>">Fallback Focus Keyword</label>
                    <input type="text" id="kw_<?php echo esc_attr($tid); ?>"
                           name="seo_kw_<?php echo esc_attr($tid); ?>"
                           value="<?php echo esc_attr($kw); ?>"
                           placeholder="z.B. handgemachte Keramik Tassen"
                           class="regular-text an-kw" style="width:100%">
                    <p style="font-size:.72rem;color:#888;margin:.2rem 0 0">Wenn kein Clean Title ableitbar</p>
                </div>
                <div>
                    <label style="display:block;font-size:.83rem;font-weight:600;margin-bottom:.2rem"
                           for="fkw_<?php echo esc_attr($tid); ?>">Focus-KW Zusatz</label>
                    <input type="text" id="fkw_<?php echo esc_attr($tid); ?>"
                           name="fkw_addon_<?php echo esc_attr($tid); ?>"
                           value="<?php echo esc_attr($fkw_add); ?>"
                           placeholder="z.B. handgetöpfert"
                           class="regular-text an-fkw-add" style="width:100%">
                    <p style="font-size:.72rem;color:#888;margin:.2rem 0 0">Hinter Clean Title in der Fokusphrase</p>
                </div>
                <div>
                    <label style="display:block;font-size:.83rem;font-weight:600;margin-bottom:.2rem"
                           for="dsf_<?php echo esc_attr($tid); ?>">Meta-Desc. Suffix</label>
                    <textarea id="dsf_<?php echo esc_attr($tid); ?>"
                              name="seo_desc_<?php echo esc_attr($tid); ?>"
                              rows="2" class="regular-text an-desc-sfx"
                              style="width:100%;resize:vertical"><?php echo esc_textarea($desc_sfx); ?></textarea>
                </div>
            </div>

            <!-- Row 3: SEO Intro-Template (full width) -->
            <div style="margin-bottom:1rem">
                <label style="display:block;font-size:.83rem;font-weight:600;margin-bottom:.2rem"
                       for="intro_<?php echo esc_attr($tid); ?>">SEO Intro-Template</label>
                <textarea id="intro_<?php echo esc_attr($tid); ?>"
                          name="intro_tpl_<?php echo esc_attr($tid); ?>"
                          rows="2" class="large-text"
                          style="width:100%;resize:vertical"><?php echo esc_textarea($intro); ?></textarea>
                <p style="font-size:.72rem;color:#888;margin:.2rem 0 0">
                    Einleitungssatz vor der Etsy-Beschreibung. <code>{noun}</code> = Prefix + Produktname + Suffix.
                    Beispiel: <em>{noun} – handgetöpfert und ein echtes Unikat aus unserer Werkstatt im Schwabenland.</em>
                </p>
            </div>

            <!-- SERP snippet preview -->
            <div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:6px;padding:.8rem 1rem">
                <div style="font-size:.68rem;color:#888;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.4rem">SERP-Vorschau</div>
                <div class="serp-title" style="color:#1a0dab;font-size:.95rem;line-height:1.3;margin-bottom:.15rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:580px">
                    <?php
                    $ex_noun = trim(implode(' ', array_filter([$prefix, $term->name, $suffix])));
                    echo esc_html($ex_noun) . ' – ' . esc_html($term->name) . ' | ' . esc_html($site_name);
                    ?>
                </div>
                <div style="color:#006621;font-size:.78rem;margin-bottom:.25rem">
                    <?php echo esc_html($site_host); ?>/produkte/<?php echo esc_html(sanitize_title($term->name)); ?>/beispiel-produkt
                </div>
                <div class="serp-desc" style="color:#545454;font-size:.83rem;line-height:1.5">
                    <?php
                    echo esc_html($ex_noun);
                    if ($desc_sfx) echo ' – ' . esc_html(mb_substr($desc_sfx, 0, 80));
                    echo '.';
                    ?>
                </div>
            </div>

        </div>
        <?php endforeach; ?>

        <?php submit_button('Alle Kategorien speichern', 'primary', 'submit', false,
            ['style' => 'font-size:.95rem;padding:.55rem 1.75rem']); ?>
    </form>

    <script>
    (function(){
        document.querySelectorAll('.seo-cat-card').forEach(function(card){
            var pfxEl  = card.querySelector('.an-prefix');
            var sfxEl  = card.querySelector('.an-suffix');
            var descEl = card.querySelector('.an-desc-sfx');
            var fkwEl  = card.querySelector('.an-fkw-add');
            var title  = card.querySelector('.serp-title');
            var desc   = card.querySelector('.serp-desc');
            var site   = card.dataset.site || '';
            var cat    = card.dataset.cat  || '';

            function refresh(){
                var pfx   = pfxEl  ? pfxEl.value.trim()  : '';
                var sfx   = sfxEl  ? sfxEl.value.trim()  : '';
                var dsfx  = descEl ? descEl.value.trim()  : '';
                var fkwA  = fkwEl  ? fkwEl.value.trim()   : '';
                var parts = [pfx, cat, sfx].filter(Boolean);
                var noun  = parts.join(' ');
                if (title) title.textContent = noun + ' – ' + cat + ' | ' + site;
                if (desc)  desc.textContent  = noun + (dsfx ? ' – ' + dsfx.substring(0, 80) : '') + '.';
                // Update legend live example
                var leg = document.getElementById('legend-example-noun');
                var legFkw = document.getElementById('legend-example-fkw');
                if (leg)    leg.textContent    = noun || '…';
                if (legFkw) legFkw.textContent = [pfx, cat, fkwA].filter(Boolean).join(' ') || '…';
            }

            [pfxEl, sfxEl, descEl, fkwEl].forEach(function(el){
                if (el) el.addEventListener('input', refresh);
            });
        });
    })();
    </script>
    </div><!-- /form column -->

    <!-- Right: sticky legend card -->
    <div style="position:sticky;top:52px">
        <div style="background:#fff;border:1px solid #e2e4e7;border-radius:8px;padding:1.1rem 1.2rem">
            <h3 style="margin:0 0 .7rem;font-size:.9rem;font-weight:700;color:#1d2327;padding-bottom:.55rem;border-bottom:1px solid #f0f0f0">
                Felder-Legende
            </h3>

            <?php
            // Use first category as live example base, fallback to generic "Tasse"
            $ex_term   = !empty($terms) ? $terms[0] : null;
            $ex_cat    = $ex_term ? esc_html($ex_term->name) : 'Keramikwerkstatt';
            $ex_prefix = $ex_term ? esc_html((string) get_term_meta($ex_term->term_id, '_annyhase_title_prefix', true)) : 'Keramik';
            $ex_suffix = $ex_term ? esc_html((string) get_term_meta($ex_term->term_id, '_annyhase_title_suffix', true)) : '';
            $ex_fkwA   = $ex_term ? esc_html((string) get_term_meta($ex_term->term_id, '_annyhase_focuskw_addon', true)) : 'handgetöpfert';
            $noun_parts = array_filter([$ex_prefix, 'Tasse', $ex_suffix]);
            $ex_noun    = implode(' ', $noun_parts) ?: 'Keramik Tasse';
            $ex_fkw     = trim(implode(' ', array_filter([$ex_prefix, 'Tasse', $ex_fkwA]))) ?: 'Keramik Tasse handgetöpfert';
            $cs = 'background:#f3f4f5;padding:1px 5px;border-radius:3px;font-size:.78rem';
            ?>

            <dl style="margin:0;font-size:.8rem;color:#444;line-height:1.55">

                <dt style="font-weight:700;margin-top:.6rem;color:#c4704a">Titel-Prefix</dt>
                <dd style="margin:.1rem 0 .1rem .6rem;color:#666">Steht VOR dem Produktnamen im SEO-Titel.</dd>
                <dd style="margin:0 0 .3rem .6rem"><code style="<?php echo $cs; ?>"><?php echo $ex_prefix ?: '(leer)'; ?></code> + <em>Tasse</em> → <strong><?php echo $ex_prefix ? esc_html($ex_prefix) . ' Tasse' : 'Tasse'; ?></strong></dd>

                <dt style="font-weight:700;margin-top:.6rem;color:#c4704a">Titel-Suffix</dt>
                <dd style="margin:.1rem 0 .1rem .6rem;color:#666">Steht NACH dem Produktnamen im SEO-Titel.</dd>
                <dd style="margin:0 0 .3rem .6rem"><em><?php echo $ex_prefix ? esc_html($ex_prefix) . ' Tasse' : 'Tasse'; ?></em> + <code style="<?php echo $cs; ?>"><?php echo $ex_suffix ?: 'nach Maß'; ?></code> → <strong><span id="legend-example-noun"><?php echo esc_html($ex_noun); ?></span></strong></dd>

                <dt style="font-weight:700;margin-top:.6rem;color:#c4704a">Suffix wenn personalisierbar</dt>
                <dd style="margin:.1rem 0 .1rem .6rem;color:#666">Ersetzt Titel-Suffix wenn Etsy-Listing <code style="<?php echo $cs; ?>">is_personalizable = true</code>.</dd>
                <dd style="margin:0 0 .3rem .6rem"><code style="<?php echo $cs; ?>">mit Namen</code> → <em><?php echo $ex_prefix ? esc_html($ex_prefix) . ' ' : ''; ?>Tasse mit Namen</em></dd>

                <dt style="font-weight:700;margin-top:.6rem;color:#c4704a">Fallback Focus KW</dt>
                <dd style="margin:.1rem 0 .1rem .6rem;color:#666">Nur verwendet wenn aus den Tags kein Produktname ableitbar ist.</dd>
                <dd style="margin:0 0 .3rem .6rem"><code style="<?php echo $cs; ?>">handgetöpferte Keramik</code></dd>

                <dt style="font-weight:700;margin-top:.6rem;color:#c4704a">Focus KW Zusatz</dt>
                <dd style="margin:.1rem 0 .1rem .6rem;color:#666">Wird hinter Prefix + Titel in der Fokusphrase ergänzt.</dd>
                <dd style="margin:0 0 .3rem .6rem">Fokusphrase: <strong><span id="legend-example-fkw"><?php echo esc_html($ex_fkw); ?></span></strong></dd>

                <dt style="font-weight:700;margin-top:.6rem;color:#c4704a">Meta-Desc. Suffix</dt>
                <dd style="margin:.1rem 0 .1rem .6rem;color:#666">Letzter Satz der automatischen Meta-Description.</dd>
                <dd style="margin:0 0 .3rem .6rem"><code style="<?php echo $cs; ?>">Hochbrand, spülmaschinengeeignet.</code></dd>

                <dt style="font-weight:700;margin-top:.6rem;color:#c4704a">SEO Intro-Template</dt>
                <dd style="margin:.1rem 0 .1rem .6rem;color:#666">Einleitungssatz VOR der Etsy-Beschreibung. <code style="<?php echo $cs; ?>">{noun}</code> = Prefix + Titel + Suffix.</dd>
                <dd style="margin:0 0 .3rem .6rem">
                    <code style="<?php echo $cs; ?>">{noun} – ein echtes Unikat.</code><br>
                    → <em><?php echo esc_html($ex_noun); ?> – ein echtes Unikat.</em>
                </dd>

            </dl>

            <!-- SEO-Titel Aufbau -->
            <div style="margin-top:.9rem;padding:.65rem .75rem;background:#f0f6fc;border:1px solid #b8d4ea;border-radius:6px;font-size:.76rem;color:#444">
                <strong style="display:block;margin-bottom:.3rem">Yoast SEO-Titel:</strong>
                <span style="font-family:monospace;color:#888">[Prefix] [Produktname] [Suffix]</span><br>
                <span style="color:#1a0dab;font-weight:600"><?php echo esc_html($ex_noun); ?> – <?php echo $ex_cat; ?> | <?php echo esc_html(get_bloginfo('name')); ?></span>
            </div>

            <div style="margin-top:.75rem;padding:.55rem .75rem;background:#fffbea;border:1px solid #f0c040;border-radius:6px;font-size:.76rem;color:#666">
                <strong>Neue Kategorien</strong> erscheinen hier automatisch sobald der Etsy-Sync sie anlegt — kein Code-Update nötig.
            </div>

        </div>
    </div><!-- /legend -->

    </div><!-- /grid -->
    <?php
}

/* =================================================================
   TAB 3 – SEO Diagnose
   ================================================================= */

function annyhase_seo_hub_tab_diagnostics(): void {
    $products = get_posts([
        'post_type'      => 'produkt',
        'post_status'    => ['publish', 'draft', 'private'],
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    if (empty($products)) {
        echo '<p>Keine Produkte vorhanden. Zuerst den <a href="'
            . esc_url(admin_url('admin.php?page=etsy-shop-sync')) . '">Etsy Sync</a> ausführen.</p>';
        return;
    }

    $total      = count($products);
    $cnt_ct     = 0;
    $cnt_fkw    = 0;
    $cnt_md     = 0;
    $cnt_cat    = 0;
    $cnt_price  = 0;

    foreach ($products as $p) {
        if (get_post_meta($p->ID, '_annyhase_clean_title',  true)) $cnt_ct++;
        if (get_post_meta($p->ID, '_yoast_wpseo_focuskw',  true)) $cnt_fkw++;
        if (get_post_meta($p->ID, '_yoast_wpseo_metadesc', true)) $cnt_md++;
        $cat_check = get_the_terms($p->ID, 'produktkategorie');
        if ($cat_check && !is_wp_error($cat_check))                $cnt_cat++;
        if (get_post_meta($p->ID, '_produkt_preis',         true)) $cnt_price++;
    }

    $dot = static function (bool $ok): string {
        $c = $ok ? '#22c55e' : '#ef4444';
        return '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' . $c . '"></span>';
    };

    $pct_bar = static function (int $ok, int $total): string {
        $pct = $total ? (int) round($ok / $total * 100) : 0;
        $c   = $pct >= 100 ? '#22c55e' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
        return '<div style="background:#e5e7eb;border-radius:4px;height:5px;margin-top:3px;overflow:hidden">'
            . '<div style="background:' . $c . ';height:100%;width:' . $pct . '%"></div></div>';
    };
    ?>
    <div style="max-width:1100px">

    <!-- Coverage summary -->
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.75rem;margin-bottom:1.5rem">
        <?php
        $summary = [
            ['Clean Title',  $cnt_ct,    $total],
            ['Focus KW',     $cnt_fkw,   $total],
            ['Meta Desc',    $cnt_md,    $total],
            ['Kategorie',    $cnt_cat,   $total],
            ['Preis',        $cnt_price, $total],
        ];
        foreach ($summary as [$label, $ok, $tot]):
            $pct = $tot ? (int) round($ok / $tot * 100) : 0;
            $col = $pct >= 100 ? '#22c55e' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
        ?>
        <div style="background:#fff;border:1px solid #e2e4e7;border-radius:8px;padding:.85rem 1rem;text-align:center">
            <div style="font-size:1.55rem;font-weight:700;color:<?php echo esc_attr($col); ?>"><?php echo esc_html($pct); ?>%</div>
            <div style="font-size:.78rem;color:#555;margin:.15rem 0 .1rem;font-weight:600"><?php echo esc_html($label); ?></div>
            <div style="font-size:.72rem;color:#999"><?php echo esc_html($ok); ?>/<?php echo esc_html($tot); ?></div>
            <?php echo $pct_bar($ok, $tot); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Per-product table -->
    <table class="wp-list-table widefat fixed striped" style="font-size:.82rem">
        <thead>
            <tr>
                <th style="width:190px">Produkt</th>
                <th style="width:150px">Clean Title</th>
                <th style="width:165px">Focus KW</th>
                <th>Meta Description</th>
                <th style="width:50px;text-align:center">Kat</th>
                <th style="width:50px;text-align:center">€</th>
                <th style="width:50px;text-align:center">Sync</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p):
            $clean    = (string) get_post_meta($p->ID, '_annyhase_clean_title',  true);
            $focuskw  = (string) get_post_meta($p->ID, '_yoast_wpseo_focuskw',  true);
            $metadesc = (string) get_post_meta($p->ID, '_yoast_wpseo_metadesc', true);
            $price    = (string) get_post_meta($p->ID, '_produkt_preis',        true);
            $is_etsy  = (string) get_post_meta($p->ID, '_is_etsy_produkt',      true);
            $cats     = get_the_terms($p->ID, 'produktkategorie');
            $cat_name = ($cats && !is_wp_error($cats)) ? $cats[0]->name : '';
        ?>
            <tr>
                <td>
                    <a href="<?php echo esc_url((string) get_edit_post_link($p->ID)); ?>"
                       style="font-weight:600;color:#1d2327;text-decoration:none">
                        <?php echo esc_html(mb_substr($p->post_title, 0, 40) . (mb_strlen($p->post_title) > 40 ? '…' : '')); ?>
                    </a>
                    <br><span style="color:#999;font-size:.72rem"><?php echo esc_html($p->post_status); ?></span>
                </td>
                <td><?php echo esc_html($clean ?: '—'); ?></td>
                <td style="color:#555"><?php echo esc_html($focuskw ? (mb_substr($focuskw, 0, 30) . (mb_strlen($focuskw) > 30 ? '…' : '')) : '—'); ?></td>
                <td style="color:#666;font-size:.78rem"><?php echo esc_html($metadesc ? (mb_substr($metadesc, 0, 90) . (mb_strlen($metadesc) > 90 ? '…' : '')) : '—'); ?></td>
                <td style="text-align:center"><?php echo $dot((bool) $cat_name); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
                <td style="text-align:center"><?php echo $dot((bool) $price); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
                <td style="text-align:center"><?php echo $dot((bool) $is_etsy); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    </div>
    <?php
}
