<?php
/**
 * SEO Template System
 *
 * Provides per-category SEO metadata and a configurable template engine
 * that auto-generates Yoast SEO fields (title, meta description, focus
 * keyphrase) during the Etsy product sync.
 *
 * Available placeholders in templates:
 *   {title}       – Etsy product title
 *   {seo_keyword} – Category SEO keyword (set on the term edit page)
 *   {seo_desc}    – Category SEO description snippet
 *   {category}    – Category display name
 *   {sitename}    – WordPress site name
 *
 * If a category has no SEO data filled in yet, {seo_keyword} falls back
 * to the category name and {seo_desc} falls back to the first ~80 chars
 * of the Etsy product description so the sync always produces output.
 */
defined('ABSPATH') || exit;

/* =================================================================
   SETTINGS REGISTRATION
   ================================================================= */

add_action('admin_init', function (): void {
    $defaults = [
        'annyhase_seo_title_tpl' => '{title} – {seo_keyword} | {sitename}',
        'annyhase_seo_desc_tpl'  => '{title}: {seo_desc}. Handgemacht von Annyhase.',
        'annyhase_seo_kw_tpl'    => '{seo_keyword}',
        'annyhase_seo_title_max' => '60',
        'annyhase_seo_desc_max'  => '155',
    ];
    foreach ($defaults as $key => $default) {
        register_setting('annyhase_seo_tpl_group', $key, [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => $default,
        ]);
    }
});

/* =================================================================
   SETTINGS FORM  (called from the Etsy Sync admin page)
   ================================================================= */

function annyhase_seo_settings_form(): void {
    $title_tpl = get_option('annyhase_seo_title_tpl', '{title} – {seo_keyword} | {sitename}');
    $desc_tpl  = get_option('annyhase_seo_desc_tpl',  '{title}: {seo_desc}. Handgemacht von Annyhase.');
    $kw_tpl    = get_option('annyhase_seo_kw_tpl',    '{seo_keyword}');
    $title_max = (int) get_option('annyhase_seo_title_max', 60);
    $desc_max  = (int) get_option('annyhase_seo_desc_max',  155);
    ?>
    <div style="background:#fff;border:1px solid #e2e4e7;border-radius:8px;padding:1.25rem;margin-top:1.25rem">

        <h3 style="margin-top:0;font-size:.95rem;font-weight:700;color:#444">
            SEO-Templates
            <span style="font-weight:400;color:#888;font-size:.85em">– Yoast-Felder beim Etsy-Sync</span>
        </h3>
        <p style="font-size:.82rem;color:#555;margin:.25rem 0 1rem;line-height:1.6">
            Platzhalter: <code>{title}</code> <code>{seo_keyword}</code> <code>{seo_desc}</code> <code>{category}</code> <code>{sitename}</code><br>
            <strong>{seo_keyword}</strong> und <strong>{seo_desc}</strong> werden pro Kategorie unter
            <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=produktkategorie&post_type=produkt')); ?>">Produkte → Kategorien</a> gepflegt.<br>
            Ohne Kategorie-Daten: <code>{seo_keyword}</code> → Kategoriename, <code>{seo_desc}</code> → erste ~80&thinsp;Zeichen der Etsy-Beschreibung.
        </p>

        <form method="post" action="options.php">
            <?php settings_fields('annyhase_seo_tpl_group'); ?>
            <table class="form-table" style="margin-top:0">
                <tr>
                    <th style="width:210px;font-size:.85rem;padding:.6rem 0">
                        <label for="seo_title_tpl">SEO-Titel Template</label>
                    </th>
                    <td style="padding:.5rem 0">
                        <input type="text" id="seo_title_tpl" name="annyhase_seo_title_tpl"
                               value="<?php echo esc_attr($title_tpl); ?>" class="large-text">
                        <p class="description">
                            Max.&thinsp;<input type="number" name="annyhase_seo_title_max"
                                value="<?php echo esc_attr($title_max); ?>"
                                style="width:55px;text-align:center"> Zeichen (empfohlen: 60).
                            Wird als <code>_yoast_wpseo_title</code> gespeichert.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th style="font-size:.85rem;padding:.6rem 0">
                        <label for="seo_desc_tpl">Meta-Description Template</label>
                    </th>
                    <td style="padding:.5rem 0">
                        <textarea id="seo_desc_tpl" name="annyhase_seo_desc_tpl"
                                  class="large-text" rows="2"><?php echo esc_textarea($desc_tpl); ?></textarea>
                        <p class="description">
                            Max.&thinsp;<input type="number" name="annyhase_seo_desc_max"
                                value="<?php echo esc_attr($desc_max); ?>"
                                style="width:55px;text-align:center"> Zeichen (empfohlen: 155).
                            Wird als <code>_yoast_wpseo_metadesc</code> gespeichert.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th style="font-size:.85rem;padding:.6rem 0">
                        <label for="seo_kw_tpl">Focus-Keyphrase Template</label>
                    </th>
                    <td style="padding:.5rem 0">
                        <input type="text" id="seo_kw_tpl" name="annyhase_seo_kw_tpl"
                               value="<?php echo esc_attr($kw_tpl); ?>" class="regular-text">
                        <p class="description">Wird als <code>_yoast_wpseo_focuskw</code> gespeichert.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Templates speichern', 'secondary', 'submit', false); ?>
        </form>
    </div>
    <?php
}

/* =================================================================
   TERM META – per-category SEO fields
   ================================================================= */

/* Fields on the "Edit category" page */
add_action('produktkategorie_edit_form_fields', function (WP_Term $term): void {
    $kw   = (string) get_term_meta($term->term_id, '_annyhase_seo_kw',   true);
    $desc = (string) get_term_meta($term->term_id, '_annyhase_seo_desc', true);
    ?>
    <tr class="form-field">
        <th scope="row"><label for="annyhase_seo_kw">SEO-Keyword</label></th>
        <td>
            <input type="text" id="annyhase_seo_kw" name="annyhase_seo_kw"
                   value="<?php echo esc_attr($kw); ?>" class="large-text">
            <p class="description">
                Haupt-Keyphrase für diese Kategorie, z.&thinsp;B.
                <em>handgemachte Keramik Tassen</em> oder <em>Keramik Schüsseln Unikat</em>.<br>
                Wird im SEO-Titel und als Focus-Keyword in Yoast verwendet.
            </p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="annyhase_seo_desc">SEO-Beschreibungstext</label></th>
        <td>
            <textarea id="annyhase_seo_desc" name="annyhase_seo_desc"
                      class="large-text" rows="2"><?php echo esc_textarea($desc); ?></textarea>
            <p class="description">
                Kurzer Zusatztext für die Meta-Description, z.&thinsp;B.
                <em>Einzigartiges Unikat aus Ton, handgefertigt im Schwabenland</em>.<br>
                Empfehlung: max. ~80&thinsp;Zeichen, damit die Meta-Description nicht zu lang wird.
            </p>
        </td>
    </tr>
    <?php
});

/* Fields on the "Add new category" form */
add_action('produktkategorie_add_form_fields', function (): void {
    ?>
    <div class="form-field">
        <label for="annyhase_seo_kw">SEO-Keyword</label>
        <input type="text" id="annyhase_seo_kw" name="annyhase_seo_kw" value="">
        <p>Haupt-Keyphrase für diese Kategorie (z.&thinsp;B. <em>handgemachte Keramik Tassen</em>).</p>
    </div>
    <div class="form-field">
        <label for="annyhase_seo_desc">SEO-Beschreibungstext</label>
        <textarea id="annyhase_seo_desc" name="annyhase_seo_desc" rows="2"></textarea>
        <p>Kurzer Zusatztext für die Meta-Description (max. ~80 Zeichen).</p>
    </div>
    <?php
});

/* Save on edit */
add_action('edit_term', function (int $term_id, int $tt_id, string $taxonomy): void {
    if ($taxonomy !== 'produktkategorie' || !current_user_can('manage_categories')) return;
    if (isset($_POST['annyhase_seo_kw'])) {
        update_term_meta($term_id, '_annyhase_seo_kw', sanitize_text_field(wp_unslash($_POST['annyhase_seo_kw'])));
    }
    if (isset($_POST['annyhase_seo_desc'])) {
        update_term_meta($term_id, '_annyhase_seo_desc', sanitize_textarea_field(wp_unslash($_POST['annyhase_seo_desc'])));
    }
}, 10, 3);

/* Save on create */
add_action('create_term', function (int $term_id, int $tt_id, string $taxonomy): void {
    if ($taxonomy !== 'produktkategorie' || !current_user_can('manage_categories')) return;
    if (isset($_POST['annyhase_seo_kw'])) {
        update_term_meta($term_id, '_annyhase_seo_kw', sanitize_text_field(wp_unslash($_POST['annyhase_seo_kw'])));
    }
    if (isset($_POST['annyhase_seo_desc'])) {
        update_term_meta($term_id, '_annyhase_seo_desc', sanitize_textarea_field(wp_unslash($_POST['annyhase_seo_desc'])));
    }
}, 10, 3);

/* =================================================================
   TEMPLATE ENGINE
   ================================================================= */

/**
 * Builds Yoast SEO fields for a product using per-category metadata and
 * configurable templates. Returns ['focuskw', 'title', 'metadesc'].
 *
 * Falls back gracefully when category SEO data has not been filled in yet:
 * {seo_keyword} → category name, {seo_desc} → first ~80 chars of Etsy desc.
 *
 * @param int    $post_id    WP post ID (must already have its category assigned)
 * @param string $etsy_title Raw listing title from Etsy
 * @param string $etsy_desc  Raw listing description from Etsy
 */
function annyhase_build_yoast_fields(int $post_id, string $etsy_title, string $etsy_desc): array {
    $terms    = get_the_terms($post_id, 'produktkategorie');
    $term     = ($terms && !is_wp_error($terms)) ? $terms[0] : null;
    $cat_name = $term ? $term->name : '';
    $cat_kw   = $term ? (string) get_term_meta($term->term_id, '_annyhase_seo_kw',   true) : '';
    $cat_desc = $term ? (string) get_term_meta($term->term_id, '_annyhase_seo_desc', true) : '';

    // Fallbacks when per-category data isn't configured yet
    if (!$cat_kw)   $cat_kw   = $cat_name;
    if (!$cat_desc) {
        $cat_desc = trim(mb_substr(
            preg_replace('/\s+/', ' ', wp_strip_all_tags($etsy_desc)),
            0, 80
        ));
    }

    $title_tpl = get_option('annyhase_seo_title_tpl', '{title} – {seo_keyword} | {sitename}');
    $desc_tpl  = get_option('annyhase_seo_desc_tpl',  '{title}: {seo_desc}. Handgemacht von Annyhase.');
    $kw_tpl    = get_option('annyhase_seo_kw_tpl',    '{seo_keyword}');
    $title_max = max(30, (int) get_option('annyhase_seo_title_max', 60));
    $desc_max  = max(60, (int) get_option('annyhase_seo_desc_max',  155));

    $replacements = [
        '{title}'       => $etsy_title,
        '{seo_keyword}' => $cat_kw,
        '{seo_desc}'    => $cat_desc,
        '{category}'    => $cat_name,
        '{sitename}'    => get_bloginfo('name'),
    ];

    return [
        'focuskw'  => annyhase_seo_apply_tpl($kw_tpl,    $replacements, 0),
        'title'    => annyhase_seo_apply_tpl($title_tpl, $replacements, $title_max),
        'metadesc' => annyhase_seo_apply_tpl($desc_tpl,  $replacements, $desc_max),
    ];
}

/**
 * Applies placeholder replacements to a template string and enforces a
 * maximum byte length, cutting at the last word boundary.
 * Leftover unfilled placeholders are stripped cleanly.
 */
function annyhase_seo_apply_tpl(string $template, array $replacements, int $max_len): string {
    $result = str_replace(array_keys($replacements), array_values($replacements), $template);

    // Remove any placeholder that wasn't replaced (no value available)
    $result = (string) preg_replace('/\s*–?\s*\{[^}]+\}\s*/', ' ', $result);
    $result = trim((string) preg_replace('/\s{2,}/', ' ', $result));
    $result = rtrim($result, ' –-|');

    if ($max_len > 0 && mb_strlen($result) > $max_len) {
        $cut        = mb_substr($result, 0, $max_len);
        $last_space = mb_strrpos($cut, ' ');
        if ($last_space !== false && $last_space > (int) ($max_len * 0.5)) {
            $cut = mb_substr($cut, 0, $last_space);
        }
        $result = rtrim($cut, ' –-,.:;|');
    }

    return $result;
}
