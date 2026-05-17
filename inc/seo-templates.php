<?php
/**
 * SEO per-category metadata
 *
 * Adds configurable SEO fields to each produktkategorie term. All fields are
 * read during Etsy sync to build product titles, focus keyphrases and meta
 * descriptions automatically — no code changes needed when categories change.
 *
 * Fields stored as term meta:
 *   _annyhase_seo_kw              – Yoast focus keyphrase (category level)
 *   _annyhase_seo_desc            – meta description suffix
 *   _annyhase_title_prefix        – prepended to the clean product noun (e.g. "Keramik")
 *   _annyhase_title_suffix        – appended to the clean product noun (e.g. "nach Maß")
 *   _annyhase_focuskw_addon       – added after clean title in focus KW (e.g. "handgetöpfert")
 *   _annyhase_personalizable_sfx  – replaces title_suffix when is_personalizable=true
 *   _annyhase_intro_template      – SEO intro sentence; use {noun} as placeholder
 */
defined('ABSPATH') || exit;

/* =================================================================
   TERM META KEYS — centralised list used by edit/create/save hooks
   ================================================================= */

/** Returns meta key → sanitize callback map for all per-category SEO fields. */
function annyhase_seo_term_meta_keys(): array {
    return [
        '_annyhase_seo_kw'             => 'sanitize_text_field',
        '_annyhase_seo_desc'           => 'sanitize_textarea_field',
        '_annyhase_title_prefix'       => 'sanitize_text_field',
        '_annyhase_title_suffix'       => 'sanitize_text_field',
        '_annyhase_focuskw_addon'      => 'sanitize_text_field',
        '_annyhase_personalizable_sfx' => 'sanitize_text_field',
        '_annyhase_intro_template'     => 'sanitize_textarea_field',
    ];
}

/* =================================================================
   EDIT FORM FIELDS (existing category)
   ================================================================= */

add_action('produktkategorie_edit_form_fields', function (WP_Term $term): void {
    $tid = $term->term_id;
    $v   = static function (string $key) use ($tid): string {
        return (string) get_term_meta($tid, $key, true);
    };
    ?>
    <tr class="form-field">
        <th scope="row"><label for="an_seo_kw">SEO-Keyword</label></th>
        <td>
            <input type="text" id="an_seo_kw" name="an_seo_kw"
                   value="<?php echo esc_attr($v('_annyhase_seo_kw')); ?>" class="large-text">
            <p class="description">Fallback-Keyphrase für Produkte ohne abgeleiteten Clean Title (z.&thinsp;B. <em>handgemachte Keramik Tassen</em>).</p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="an_title_prefix">Titel-Prefix</label></th>
        <td>
            <input type="text" id="an_title_prefix" name="an_title_prefix"
                   value="<?php echo esc_attr($v('_annyhase_title_prefix')); ?>" class="regular-text"
                   placeholder="z.B. Keramik">
            <p class="description">Wird vor den Produktnamen gestellt. Leer lassen wenn kein Prefix nötig.</p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="an_title_suffix">Titel-Suffix</label></th>
        <td>
            <input type="text" id="an_title_suffix" name="an_title_suffix"
                   value="<?php echo esc_attr($v('_annyhase_title_suffix')); ?>" class="regular-text"
                   placeholder="z.B. nach Maß">
            <p class="description">Wird nach dem Produktnamen angehängt (z.&thinsp;B. <em>nach Maß</em>, <em>personalisiert</em>).</p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="an_personalizable_sfx">Suffix wenn personalisierbar</label></th>
        <td>
            <input type="text" id="an_personalizable_sfx" name="an_personalizable_sfx"
                   value="<?php echo esc_attr($v('_annyhase_personalizable_sfx')); ?>" class="regular-text"
                   placeholder="z.B. personalisiert mit Namen">
            <p class="description">Überschreibt den Titel-Suffix wenn das Etsy-Listing personalisierbar ist (<code>is_personalizable = true</code>).</p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="an_focuskw_addon">Focus-KW Zusatz</label></th>
        <td>
            <input type="text" id="an_focuskw_addon" name="an_focuskw_addon"
                   value="<?php echo esc_attr($v('_annyhase_focuskw_addon')); ?>" class="regular-text"
                   placeholder="z.B. handgetöpfert">
            <p class="description">Wird hinter dem Clean Title zur Focus-Keyphrase ergänzt (z.&thinsp;B. <em>handgetöpfert</em>, <em>mit Namen</em>).</p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="an_seo_desc">Meta-Desc. Suffix</label></th>
        <td>
            <textarea id="an_seo_desc" name="an_seo_desc"
                      class="large-text" rows="2"><?php echo esc_textarea($v('_annyhase_seo_desc')); ?></textarea>
            <p class="description">
                Wird ans Ende der auto-generierten Meta-Description angehängt.<br>
                Beispiel: <em>Hochbrand gebrannt, dicht und spülmaschinengeeignet.</em>
            </p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="an_intro_template">SEO-Intro-Template</label></th>
        <td>
            <textarea id="an_intro_template" name="an_intro_template"
                      class="large-text" rows="2"><?php echo esc_textarea($v('_annyhase_intro_template')); ?></textarea>
            <p class="description">
                Einleitungssatz der beim Sync vor die Produktbeschreibung gestellt wird. <code>{noun}</code> wird durch den Produktnamen (mit Prefix/Suffix) ersetzt.<br>
                Beispiel: <em>{noun} – handgetöpfert und ein echtes Unikat aus unserer Werkstatt im Schwabenland.</em>
            </p>
        </td>
    </tr>
    <?php
});

/* =================================================================
   ADD FORM FIELDS (new category)
   ================================================================= */

add_action('produktkategorie_add_form_fields', function (): void {
    $fields = [
        ['an_seo_kw',            'SEO-Keyword',               'text',     'handgemachte Keramik Tassen',     'Fallback-Keyphrase für Produkte ohne Clean Title.'],
        ['an_title_prefix',      'Titel-Prefix',              'text',     'Keramik',                         'Vor den Produktnamen gestellt (z.B. "Keramik").'],
        ['an_title_suffix',      'Titel-Suffix',              'text',     'nach Maß',                        'Nach dem Produktnamen angehängt (z.B. "nach Maß").'],
        ['an_personalizable_sfx','Suffix wenn personalisierbar','text',   'personalisiert mit Namen',        'Überschreibt Titel-Suffix wenn is_personalizable=true.'],
        ['an_focuskw_addon',     'Focus-KW Zusatz',           'text',     'handgetöpfert',                   'Ergänzung zur Focus-Keyphrase (z.B. "handgetöpfert").'],
        ['an_seo_desc',          'Meta-Desc. Suffix',         'textarea', '',                                'Ans Ende der Meta-Description angehängt.'],
        ['an_intro_template',    'SEO-Intro-Template',        'textarea', '',                                'Einleitungssatz; {noun} = Produktname mit Prefix/Suffix.'],
    ];
    foreach ($fields as [$id, $label, $type, $placeholder, $desc]):
        ?>
        <div class="form-field">
            <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
            <?php if ($type === 'textarea'): ?>
            <textarea id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($id); ?>" rows="2"></textarea>
            <?php else: ?>
            <input type="text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($id); ?>"
                   value="" placeholder="<?php echo esc_attr($placeholder); ?>">
            <?php endif; ?>
            <p><?php echo esc_html($desc); ?></p>
        </div>
        <?php
    endforeach;
});

/* =================================================================
   SAVE HOOKS (shared logic for edit + create)
   ================================================================= */

/**
 * Persists all per-category SEO term meta from $_POST.
 * Called by both edit_term and create_term hooks.
 */
function annyhase_save_seo_term_meta(int $term_id): void {
    $map = [
        'an_seo_kw'             => ['_annyhase_seo_kw',             'sanitize_text_field'],
        'an_seo_desc'           => ['_annyhase_seo_desc',           'sanitize_textarea_field'],
        'an_title_prefix'       => ['_annyhase_title_prefix',       'sanitize_text_field'],
        'an_title_suffix'       => ['_annyhase_title_suffix',       'sanitize_text_field'],
        'an_focuskw_addon'      => ['_annyhase_focuskw_addon',      'sanitize_text_field'],
        'an_personalizable_sfx' => ['_annyhase_personalizable_sfx', 'sanitize_text_field'],
        'an_intro_template'     => ['_annyhase_intro_template',     'sanitize_textarea_field'],
    ];
    foreach ($map as $post_key => [$meta_key, $sanitizer]) {
        if (isset($_POST[$post_key])) {
            update_term_meta($term_id, $meta_key, $sanitizer(wp_unslash($_POST[$post_key])));
        }
    }
}

add_action('edit_term', function (int $term_id, int $tt_id, string $taxonomy): void {
    if ($taxonomy !== 'produktkategorie' || !current_user_can('manage_categories')) return;
    annyhase_save_seo_term_meta($term_id);
}, 10, 3);

add_action('create_term', function (int $term_id, int $tt_id, string $taxonomy): void {
    if ($taxonomy !== 'produktkategorie' || !current_user_can('manage_categories')) return;
    annyhase_save_seo_term_meta($term_id);
}, 10, 3);

/* =================================================================
   FOCUS KEYPHRASE BUILDER
   ================================================================= */

/**
 * Returns the Yoast focus keyphrase for a product based on its primary
 * produktkategorie term's SEO keyword. Falls back to the category name.
 *
 * @param int $post_id WP post ID of the product
 * @return array { focuskw: string }
 */
function annyhase_build_yoast_fields(int $post_id): array {
    $terms  = get_the_terms($post_id, 'produktkategorie');
    $term   = ($terms && !is_wp_error($terms)) ? $terms[0] : null;
    $cat_kw = $term ? (string) get_term_meta($term->term_id, '_annyhase_seo_kw', true) : '';
    if (!$cat_kw && $term) $cat_kw = $term->name;

    return ['focuskw' => $cat_kw];
}

/**
 * Reads all per-category SEO config fields for a given term.
 * Returns an array with all configurable keys; empty string when not set.
 *
 * @param int $term_id produktkategorie term ID
 * @return array<string,string>
 */
function annyhase_get_category_seo_config(int $term_id): array {
    $keys = [
        'prefix'          => '_annyhase_title_prefix',
        'suffix'          => '_annyhase_title_suffix',
        'focuskw_addon'   => '_annyhase_focuskw_addon',
        'pers_sfx'        => '_annyhase_personalizable_sfx',
        'intro_template'  => '_annyhase_intro_template',
        'meta_desc_sfx'   => '_annyhase_seo_desc',
        'seo_kw'          => '_annyhase_seo_kw',
    ];
    $result = [];
    foreach ($keys as $alias => $meta_key) {
        $result[$alias] = (string) get_term_meta($term_id, $meta_key, true);
    }
    return $result;
}
