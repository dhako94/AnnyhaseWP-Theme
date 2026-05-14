<?php
/**
 * Annyhase Etsy Product Media Management
 *
 * @package Annyhase
 *
 * Keeps auto-imported product media separate from the main WordPress Media Library:
 *  - Hides attachments flagged with _etsy_product_media from the upload screen
 *    and the media picker modal — except on 'produkt' post edit screens where
 *    the picker must show product images so gallery images can be selected.
 *  - Provides a dedicated admin page (Produkte → Produkt-Medien) for reviewing
 *    and deleting imported photos/videos per product.
 */
defined('ABSPATH') || exit;

/* =================================================================
   HIDE FROM WP MEDIA LIBRARY
   ================================================================= */

/**
 * Builds a meta_query clause that excludes attachments marked as Etsy product media.
 * Safe to merge into any existing meta_query array.
 */
function etsy_media_exclude_clause(array $mq = []): array {
    $mq[] = [
        'relation' => 'OR',
        ['key' => '_etsy_product_media', 'compare' => 'NOT EXISTS'],
        ['key' => '_etsy_product_media', 'value' => '1', 'compare' => '!='],
    ];
    return $mq;
}

/* Exclude Etsy media from the Media Library upload screen (admin list view) */
add_action('pre_get_posts', function (WP_Query $query): void {
    if (!is_admin() || !$query->is_main_query()) return;
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'upload') return;
    $query->set('meta_query', etsy_media_exclude_clause((array) $query->get('meta_query')));
});

/* Exclude Etsy media from the media picker modal (AJAX), with two bypass rules:
   1. Our Produkt-Medien page passes etsy_media_context — bypass so it sees everything.
   2. When editing a 'produkt' post, WordPress sends post_id with every media AJAX
      request — bypass so the gallery image picker shows the product's own photos. */
add_filter('ajax_query_attachments_args', function (array $query): array {
    if (!empty($_REQUEST['etsy_media_context']) && current_user_can('upload_files')) return $query;

    $post_id = (int) ($_REQUEST['post_id'] ?? 0);
    if ($post_id && get_post_type($post_id) === 'produkt') return $query;

    $query['meta_query'] = etsy_media_exclude_clause($query['meta_query'] ?? []);
    return $query;
});

/* =================================================================
   ADMIN MENU
   ================================================================= */

add_action('admin_menu', function (): void {
    add_submenu_page(
        'edit.php?post_type=produkt',
        __('Produkt-Medien', 'annyhase'),
        __('Produkt-Medien', 'annyhase'),
        'upload_files',
        'etsy-produkt-medien',
        'etsy_media_admin_page'
    );
}, 20);

/* Untermenü-Eintrag ausblenden – Seite intern weiterhin nutzbar */
add_action('admin_menu', function (): void {
    remove_submenu_page('edit.php?post_type=produkt', 'etsy-produkt-medien');
}, 99);

/* =================================================================
   ADMIN PAGE
   ================================================================= */

/**
 * Renders the "Produkt-Medien" admin page.
 *
 * Lists all products that have an `_etsy_listing_id` meta value, showing their
 * imported photos and optional video. Provides nonce-secured per-image delete
 * links and paginates results at 15 items per page.
 *
 * Accessible at: Produkte → Produkt-Medien (hidden from the menu; linked
 * internally).
 */
function etsy_media_admin_page(): void {

    /* Handle single-attachment delete via nonce-secured GET link */
    if (
        isset($_GET['etsy_delete_att']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'etsy_delete_att')
    ) {
        $att_id = (int) $_GET['etsy_delete_att'];
        if (get_post_meta($att_id, '_etsy_product_media', true) === '1') {
            wp_delete_attachment($att_id, true);
            echo '<div class="notice notice-success is-dismissible"><p>Attachment deleted.</p></div>';
        }
    }

    /* Paginate the product list — 15 products per page */
    $paged    = max(1, (int) ($_GET['paged'] ?? 1));
    $per_page = 15;

    $products = new WP_Query([
        'post_type'      => 'produkt',
        'post_status'    => 'any',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [['key' => '_etsy_listing_id', 'compare' => 'EXISTS']],
    ]);

    $total_pages = $products->max_num_pages;
    ?>
    <div class="wrap">
    <h1 style="margin-bottom:.25rem"><?php esc_html_e('Produkt-Medien', 'annyhase'); ?></h1>
    <p style="color:#666;margin-top:0">Automatically imported Etsy photos &amp; videos. Not visible in the regular Media Library.</p>

    <?php if (!$products->have_posts()): ?>
    <div style="background:#fff;border:1px solid #e2e4e7;border-radius:8px;padding:2rem;text-align:center;color:#666">
        <p style="font-size:1.1rem;margin:0">No product media imported yet.</p>
        <p style="margin:.5rem 0 0">Go to <a href="<?php echo esc_url(admin_url('admin.php?page=etsy-shop-sync')); ?>">Etsy Shop Sync</a> and start a product sync.</p>
    </div>
    <?php else: ?>

    <style>
    .etsy-media-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #e2e4e7; border-radius:8px; overflow:hidden; }
    .etsy-media-table th { background:#f6f7f7; padding:.75rem 1rem; text-align:left; font-size:.82rem; color:#555; font-weight:600; border-bottom:2px solid #e2e4e7; }
    .etsy-media-table td { padding:.75rem 1rem; border-bottom:1px solid #f0f0f0; vertical-align:top; }
    .etsy-media-table tr:last-child td { border-bottom:0; }
    .etsy-media-thumbs { display:flex; flex-wrap:wrap; gap:6px; }
    .etsy-media-thumb { position:relative; width:72px; height:72px; border-radius:4px; overflow:hidden; border:1px solid #e2e4e7; flex-shrink:0; background:#f6f7f7; }
    .etsy-media-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
    .etsy-media-thumb--video::after { content:"▶"; position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,.45); color:#fff; font-size:1.4rem; }
    .etsy-media-del { display:flex; align-items:center; justify-content:center; width:18px; height:18px; background:#dc2626; border-radius:50%; color:#fff; font-size:.65rem; text-decoration:none; position:absolute; top:2px; right:2px; line-height:1; }
    .etsy-media-del:hover { background:#b91c1c; color:#fff; }
    .etsy-media-empty { color:#999; font-size:.85rem; font-style:italic; }
    .etsy-video-link { display:inline-flex; align-items:center; gap:4px; font-size:.8rem; color:var(--color-accent,#c07); text-decoration:none; }
    .etsy-video-link:hover { text-decoration:underline; }
    </style>

    <table class="etsy-media-table">
        <thead>
            <tr>
                <th style="width:260px">Product</th>
                <th>Photos</th>
                <th style="width:140px">Video</th>
                <th style="width:90px">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($products->have_posts()): $products->the_post();
            $post_id     = get_the_ID();
            $thumb_id    = get_post_thumbnail_id($post_id);
            $gallery_raw = get_post_meta($post_id, '_produkt_galerie', true);
            $gallery_ids = $gallery_raw ? array_filter(array_map('intval', explode(',', $gallery_raw))) : [];
            $all_img_ids = array_filter(array_merge($thumb_id ? [$thumb_id] : [], $gallery_ids));

            $video_url   = get_post_meta($post_id, '_etsy_video_url',           true);
            $video_thumb = get_post_meta($post_id, '_etsy_video_thumbnail_url', true);
            $etsy_url    = get_post_meta($post_id, '_etsy_url',                 true);
            $price       = get_post_meta($post_id, '_produkt_preis',            true);
        ?>
        <tr>
            <td>
                <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>" style="font-weight:600;font-size:.9rem"><?php the_title(); ?></a>
                <?php if ($price): ?><br><span style="font-size:.8rem;color:#666"><?php echo esc_html($price); ?> €</span><?php endif; ?>
                <br><span style="font-size:.75rem;color:#999">ID: <?php echo esc_html(get_post_meta($post_id, '_etsy_listing_id', true) ?: '–'); ?></span>
            </td>
            <td>
                <?php if ($all_img_ids): ?>
                <div class="etsy-media-thumbs">
                    <?php foreach ($all_img_ids as $att_id):
                        $att_url   = wp_get_attachment_url($att_id);
                        $thumb_src = wp_get_attachment_image_url($att_id, [72, 72]);
                        $del_url   = wp_nonce_url(add_query_arg([
                            'page'            => 'etsy-produkt-medien',
                            'etsy_delete_att' => $att_id,
                        ], admin_url('edit.php?post_type=produkt')), 'etsy_delete_att');
                    ?>
                    <div class="etsy-media-thumb" title="<?php echo esc_attr($att_url); ?>">
                        <?php if ($thumb_src): ?>
                            <img src="<?php echo esc_url($thumb_src); ?>" alt="" loading="lazy">
                        <?php endif; ?>
                        <?php if ($att_id === $thumb_id): ?>
                            <span style="position:absolute;bottom:2px;left:2px;background:rgba(0,0,0,.55);color:#fff;font-size:.55rem;padding:1px 4px;border-radius:2px">Cover</span>
                        <?php endif; ?>
                        <a href="<?php echo esc_url($del_url); ?>" class="etsy-media-del" title="Delete" onclick="return confirm('Delete this image permanently?')">✕</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <span class="etsy-media-empty">No photos</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($video_url): ?>
                <div>
                    <?php if ($video_thumb): ?>
                    <div class="etsy-media-thumb etsy-media-thumb--video" style="margin-bottom:6px">
                        <img src="<?php echo esc_url($video_thumb); ?>" alt="" loading="lazy">
                    </div>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($video_url); ?>" target="_blank" rel="noopener noreferrer" class="etsy-video-link">
                        ▶ View video
                    </a>
                </div>
                <?php else: ?>
                    <span class="etsy-media-empty">No video</span>
                <?php endif; ?>
            </td>
            <td style="white-space:nowrap">
                <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>" class="button button-small">Edit</a>
                <?php if ($etsy_url): ?>
                <br><a href="<?php echo esc_url($etsy_url); ?>" target="_blank" rel="noopener noreferrer" class="button button-small" style="margin-top:4px">Etsy ↗</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; wp_reset_postdata(); ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <div style="margin-top:1rem">
        <?php echo paginate_links([
            'base'      => add_query_arg('paged', '%#%'),
            'format'    => '',
            'current'   => $paged,
            'total'     => $total_pages,
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
        ]); ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
    </div>
    <?php
}
