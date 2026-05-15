<?php
/**
 * SEO per-category metadata
 *
 * Adds an SEO keyword and a reference description to each produktkategorie
 * term. The keyword is written as the Yoast focus keyphrase during Etsy
 * sync. Title and meta description format is controlled entirely by Yoast's
 * own template settings (Content Types → Produkt), keeping configuration
 * in one place.
 *
 * Note: also fill the native WordPress "Beschreibung" field on each
 * category so Yoast's %%term_description%% variable works on category
 * archive pages.
 */
defined('ABSPATH') || exit;

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
                Haupt-Keyphrase für diese Kategorie, z.&thinsp;B. <em>handgemachte Keramik Tassen</em>.<br>
                Wird beim Etsy-Sync als Yoast Focus-Keyword für alle Produkte dieser Kategorie gesetzt.
            </p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="annyhase_seo_desc">SEO-Notiz <small style="font-weight:400">(Referenz)</small></label></th>
        <td>
            <textarea id="annyhase_seo_desc" name="annyhase_seo_desc"
                      class="large-text" rows="2"><?php echo esc_textarea($desc); ?></textarea>
            <p class="description">
                Eigene Notiz / Referenztext für diese Kategorie. Wird nicht automatisch verwendet.<br>
                Für <code>%%term_description%%</code> in Yoast das native Feld <strong>Beschreibung</strong> (weiter oben) befüllen.
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
        <label for="annyhase_seo_desc">SEO-Notiz <small>(Referenz)</small></label>
        <textarea id="annyhase_seo_desc" name="annyhase_seo_desc" rows="2"></textarea>
        <p>Eigene Notiz. Für Yoast <code>%%term_description%%</code> das native Feld "Beschreibung" befüllen.</p>
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
   FOCUS KEYPHRASE BUILDER
   ================================================================= */

/**
 * Returns the Yoast focus keyphrase for a product based on its primary
 * produktkategorie term's SEO keyword. Falls back to the category name
 * if no keyword has been set yet.
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
