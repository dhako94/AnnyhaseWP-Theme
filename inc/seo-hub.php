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
   admin_post – save all category SEO fields at once
   ================================================================= */

add_action('admin_post_annyhase_save_seo_categories', function (): void {
    check_admin_referer('annyhase_save_seo_categories');
    if (!current_user_can('manage_categories')) wp_die('Unauthorized');

    $terms = get_terms(['taxonomy' => 'produktkategorie', 'hide_empty' => false]);
    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            $kw     = sanitize_text_field(wp_unslash($_POST['seo_kw_'   . $term->term_id] ?? ''));
            $suffix = sanitize_textarea_field(wp_unslash($_POST['seo_desc_' . $term->term_id] ?? ''));
            update_term_meta($term->term_id, '_annyhase_seo_kw',   $kw);
            update_term_meta($term->term_id, '_annyhase_seo_desc', $suffix);
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
    <div style="max-width:960px">

    <p style="color:#555;margin-bottom:1.5rem;font-size:.9rem">
        <strong>Focus Keyword</strong> – wird beim Etsy-Sync als Yoast-Fokusphrase für alle Produkte dieser Kategorie gesetzt.<br>
        <strong>Meta-Desc. Suffix</strong> – ein Satz, der ans Ende der automatisch generierten Meta-Description angehängt wird (z.&thinsp;B. Materialeigenschaften).
    </p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('annyhase_save_seo_categories'); ?>
        <input type="hidden" name="action" value="annyhase_save_seo_categories">

        <?php foreach ($terms as $term):
            $kw     = (string) get_term_meta($term->term_id, '_annyhase_seo_kw',   true);
            $suffix = (string) get_term_meta($term->term_id, '_annyhase_seo_desc', true);
            $tid    = (int) $term->term_id;
        ?>
        <div class="seo-cat-card" data-site="<?php echo esc_attr($site_name); ?>"
             style="background:#fff;border:1px solid #e2e4e7;border-radius:8px;padding:1.25rem;margin-bottom:1.25rem">

            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.9rem">
                <h3 style="margin:0;font-size:1rem;font-weight:700">
                    <?php echo esc_html($term->name); ?>
                    <span style="font-weight:400;color:#777;font-size:.82rem">(<?php echo (int) $term->count; ?> Produkte)</span>
                </h3>
                <a href="<?php echo esc_url(get_term_link($term)); ?>" target="_blank"
                   style="font-size:.8rem;color:#666;text-decoration:none">Archiv ↗</a>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
                <div>
                    <label style="display:block;font-size:.83rem;font-weight:600;margin-bottom:.25rem"
                           for="kw_<?php echo esc_attr($tid); ?>">Focus Keyword</label>
                    <input type="text" id="kw_<?php echo esc_attr($tid); ?>"
                           name="seo_kw_<?php echo esc_attr($tid); ?>"
                           value="<?php echo esc_attr($kw); ?>"
                           placeholder="z.B. handgetöpferte Keramik Tassen"
                           class="regular-text seo-kw-field" style="width:100%">
                </div>
                <div>
                    <label style="display:block;font-size:.83rem;font-weight:600;margin-bottom:.25rem"
                           for="sfx_<?php echo esc_attr($tid); ?>">Meta-Desc. Suffix</label>
                    <textarea id="sfx_<?php echo esc_attr($tid); ?>"
                              name="seo_desc_<?php echo esc_attr($tid); ?>"
                              rows="2" class="regular-text seo-sfx-field"
                              style="width:100%;resize:vertical"><?php echo esc_textarea($suffix); ?></textarea>
                </div>
            </div>

            <!-- SERP snippet preview -->
            <div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:6px;padding:.8rem 1rem">
                <div style="font-size:.68rem;color:#888;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.4rem">SERP-Vorschau</div>
                <div class="serp-title" style="color:#1a0dab;font-size:.95rem;line-height:1.3;margin-bottom:.15rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:580px">
                    Keramik <?php echo esc_html($term->name); ?> – <?php echo esc_html($term->name); ?> | <?php echo esc_html($site_name); ?>
                </div>
                <div style="color:#006621;font-size:.78rem;margin-bottom:.25rem">
                    <?php echo esc_html($site_host); ?>/produkte/<?php echo esc_html(sanitize_title($term->name)); ?>/beispiel-produkt
                </div>
                <div class="serp-desc" style="color:#545454;font-size:.83rem;line-height:1.5">
                    Keramik <?php echo esc_html($term->name); ?><?php echo $suffix ? ' – ' . esc_html(mb_substr($suffix, 0, 80)) : ''; ?>.
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
            var kw    = card.querySelector('.seo-kw-field');
            var sfx   = card.querySelector('.seo-sfx-field');
            var title = card.querySelector('.serp-title');
            var desc  = card.querySelector('.serp-desc');
            var site  = card.dataset.site || '';
            var cat   = card.querySelector('h3').childNodes[0].nodeValue.trim();

            function refresh(){
                var kwVal  = kw  ? kw.value.trim()  : '';
                var sfxVal = sfx ? sfx.value.trim() : '';
                var noun   = kwVal || ('Keramik ' + cat);
                if (title) title.textContent = noun + ' – ' + cat + ' | ' + site;
                if (desc)  desc.textContent  = 'Keramik ' + cat + (sfxVal ? ' – ' + sfxVal.substring(0, 80) : '') + '.';
            }
            if (kw)  kw.addEventListener('input',  refresh);
            if (sfx) sfx.addEventListener('input', refresh);
        });
    })();
    </script>
    </div>
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
