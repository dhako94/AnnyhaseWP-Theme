<?php defined('ABSPATH') || exit; get_header(); ?>

<?php while (have_posts()): the_post(); ?>

<?php
$is_produkt = is_singular('produkt');
$post_id    = get_the_ID();

$gallery_ids = $is_produkt ? annyhase_get_gallery_ids($post_id) : [];
if (!$is_produkt && has_post_thumbnail()) $gallery_ids = [get_post_thumbnail_id()];

$terms       = $is_produkt ? get_the_terms($post_id, 'produktkategorie') : [];
$price       = $is_produkt ? annyhase_product_price()  : '';
$etsy_url    = $is_produkt ? annyhase_etsy_url()       : '';
$is_etsy     = $is_produkt ? annyhase_is_etsy()        : false;
$badge       = $is_produkt ? annyhase_product_badge()  : '';
$video_url   = $is_produkt ? (string) get_post_meta($post_id, '_etsy_video_url',           true) : '';
$video_thumb = $is_produkt ? (string) get_post_meta($post_id, '_etsy_video_thumbnail_url', true) : '';
$has_video   = !empty($video_url);

// Ordered list of all gallery items: image 0, then video, then images 1..n
$gallery_items = [];
foreach ($gallery_ids as $i => $img_id) {
    $gallery_items[] = ['type' => 'image', 'id' => $img_id];
    if ($i === 0 && $has_video) {
        $gallery_items[] = ['type' => 'video'];
    }
}
if ($has_video && empty($gallery_ids)) {
    $gallery_items[] = ['type' => 'video'];
}
$has_multi = count($gallery_items) > 1;
?>

<?php if ($is_produkt): ?>

<!-- ══════════════════════════════════════════
     PRODUKT-SEITE (Etsy-Style)
     ══════════════════════════════════════════ -->

<!-- Breadcrumb row -->
<div class="product-breadcrumb">
    <div class="container">
        <nav class="product-breadcrumb__inner" aria-label="Breadcrumb">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Start', 'annyhase'); ?></a>
            <span class="product-breadcrumb__sep" aria-hidden="true">›</span>
            <a href="<?php echo esc_url(get_post_type_archive_link('produkt')); ?>"><?php esc_html_e('Produkte', 'annyhase'); ?></a>
            <?php if ($terms && !is_wp_error($terms)): ?>
            <span class="product-breadcrumb__sep" aria-hidden="true">›</span>
            <a href="<?php echo esc_url(get_term_link($terms[0])); ?>"><?php echo esc_html($terms[0]->name); ?></a>
            <?php endif; ?>
        </nav>
    </div>
</div>

<div class="product-page">
    <div class="container">
        <div class="product-layout">

            <!-- ── GALLERY (left) ── -->
            <div class="product-gallery reveal">

                <!-- Main image -->
                <div class="product-gallery__main" id="pg-main" data-index="0">
                    <?php if (!empty($gallery_ids)):
                        $main_src = wp_get_attachment_image_url($gallery_ids[0], 'full') ?: wp_get_attachment_image_url($gallery_ids[0], 'product-wide');
                    ?>
                        <img src="<?php echo esc_url($main_src); ?>"
                             id="pg-main-img"
                             alt="<?php echo esc_attr(get_the_title()); ?>"
                             loading="eager">
                    <?php else: ?>
                        <div class="product-gallery__empty">🏺</div>
                    <?php endif; ?>

                    <?php if (count($gallery_items) > 1): ?>
                    <button type="button" class="product-gallery__nav product-gallery__nav--prev" id="pg-nav-prev" aria-label="Vorheriges Bild"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
                    <button type="button" class="product-gallery__nav product-gallery__nav--next" id="pg-nav-next" aria-label="Nächstes Bild"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></button>
                    <?php endif; ?>

                    <?php if ($has_video): ?>
                    <div id="pg-main-video" style="display:none;position:absolute;inset:0;background:#000;border-radius:inherit">
                        <video id="pg-video-player"
                               <?php if ($video_thumb): ?>poster="<?php echo esc_url($video_thumb); ?>"<?php endif; ?>
                               controls playsinline
                               style="width:100%;height:100%;object-fit:contain;display:block">
                            <source src="<?php echo esc_url($video_url); ?>" type="video/mp4">
                        </video>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Thumbnails (horizontal strip below main image) -->
                <?php if ($has_multi): ?>
                <div class="product-gallery__thumbs-nav" id="pg-thumbs-nav">
                    <button type="button" class="product-gallery__thumbs-btn product-gallery__thumbs-btn--prev" id="pg-thumbs-prev" aria-label="Vorheriges Bild"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
                    <div class="product-gallery__thumbs" id="pg-thumbs">
                        <?php foreach ($gallery_items as $gi => $gitem): ?>
                        <?php if ($gitem['type'] === 'image'):
                            $thumb_url = wp_get_attachment_image_url($gitem['id'], [90, 90]);
                        ?>
                        <button type="button"
                                class="product-gallery__thumb <?php echo $gi === 0 ? 'is-active' : ''; ?>"
                                data-index="<?php echo $gi; ?>"
                                aria-label="Bild <?php echo $gi + 1; ?> anzeigen">
                            <img src="<?php echo esc_url($thumb_url); ?>" alt="" loading="lazy">
                        </button>
                        <?php elseif ($gitem['type'] === 'video'): ?>
                        <button type="button"
                                class="product-gallery__thumb product-gallery__thumb--video"
                                data-index="<?php echo $gi; ?>"
                                aria-label="Video abspielen">
                            <?php if ($video_thumb): ?><img src="<?php echo esc_url($video_thumb); ?>" alt="" loading="lazy"><?php endif; ?>
                            <span class="product-gallery__play-icon" aria-hidden="true">
                                <svg width="38" height="38" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="19" cy="19" r="18" fill="var(--color-terracotta)" fill-opacity=".88"/>
                                    <polygon points="15,12 15,26 28,19" fill="#fff"/>
                                </svg>
                            </span>
                        </button>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="product-gallery__thumbs-btn product-gallery__thumbs-btn--next" id="pg-thumbs-next" aria-label="Nächstes Bild"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></button>
                </div>
                <?php endif; ?>

            </div><!-- /product-gallery -->

            <!-- ── INFO (right, sticky) ── -->
            <div class="product-info reveal" style="transition-delay:.12s">

                <!-- Shop name -->
                <p class="product-info__shop"><?php echo esc_html(get_bloginfo('name')); ?></p>

                <!-- Product title -->
                <h1 class="product-info__title"><?php the_title(); ?></h1>

                <!-- Badge -->
                <?php if ($badge): ?>
                <div class="product-info__badges">
                    <span class="tag <?php echo $badge === 'Ausverkauft' ? '' : 'tag--terracotta'; ?>">
                        <?php echo esc_html($badge); ?>
                    </span>
                </div>
                <?php endif; ?>

                <!-- Price -->
                <?php if ($price): ?>
                <div class="product-info__price-wrap">
                    <span class="product-info__price"><?php echo esc_html($price); ?></span>
                </div>
                <?php endif; ?>

                <!-- CTA buttons -->
                <div class="product-info__actions">
                    <?php if ($is_etsy && $etsy_url): ?>
                    <a href="<?php echo esc_url($etsy_url); ?>"
                       target="_blank" rel="noopener noreferrer"
                       class="product-btn product-btn--primary">
                        <?php esc_html_e('Auf Etsy kaufen', 'annyhase'); ?>
                        <svg class="product-btn__arrow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="15" height="15" aria-hidden="true"><path d="M4 10h12"/><path d="M10 4l6 6-6 6"/></svg>
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(home_url('/kontakt')); ?>"
                       class="product-btn product-btn--outline">
                        <?php esc_html_e('Anfrage / Sonderwunsch', 'annyhase'); ?>
                    </a>
                </div>

                <!-- Description (collapsible) -->
                <?php
                $content = get_the_content();
                $desc_text = $content ? wp_kses_post(wpautop($content)) : ('<p>' . esc_html(get_the_excerpt()) . '</p>');
                if ($desc_text):
                ?>
                <div class="product-info__desc-wrap">
                    <div class="product-info__desc" id="pg-desc"><?php echo $desc_text; ?></div>
                    <div class="product-info__desc-fade" id="pg-desc-fade"></div>
                    <button type="button" class="product-info__desc-toggle" id="pg-desc-toggle">
                        <?php esc_html_e('Mehr lesen', 'annyhase'); ?>
                    </button>
                </div>
                <?php endif; ?>

                <!-- Product specs: delivery time, materials, personalisation -->
                <?php
                $spec_proc_min = (int) get_post_meta($post_id, '_etsy_processing_min', true);
                $spec_proc_max = (int) get_post_meta($post_id, '_etsy_processing_max', true);
                $spec_mat      = (string) get_post_meta($post_id, '_etsy_materials', true);
                $spec_pers     = get_post_meta($post_id, '_etsy_personalizable', true);
                $spec_pers_txt = (string) get_post_meta($post_id, '_etsy_personalization_instructions', true);

                $has_specs = ($spec_proc_min > 0) || $spec_mat || ($spec_pers === '1');
                if ($has_specs):
                ?>
                <div class="product-specs">
                    <?php if ($spec_proc_min > 0): ?>
                    <div class="product-specs__row">
                        <span class="product-specs__icon">⏱</span>
                        <span class="product-specs__label">Lieferzeit:</span>
                        <span class="product-specs__value">
                            <?php
                            if ($spec_proc_max > $spec_proc_min) {
                                echo esc_html("Fertig in {$spec_proc_min}–{$spec_proc_max} Werktagen");
                            } else {
                                echo esc_html("Fertig in {$spec_proc_min} Werktagen");
                            }
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ($spec_mat): ?>
                    <div class="product-specs__row">
                        <span class="product-specs__icon">🧱</span>
                        <span class="product-specs__label">Material:</span>
                        <span class="product-specs__value"><?php echo esc_html($spec_mat); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($spec_pers === '1'): ?>
                    <div class="product-specs__row">
                        <span class="product-specs__icon">✏️</span>
                        <span class="product-specs__label">Personalisierung:</span>
                        <span class="product-specs__value">
                            <?php echo $spec_pers_txt ? esc_html($spec_pers_txt) : esc_html__('Auf Anfrage möglich', 'annyhase'); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Details: handmade + shipping -->
                <?php
                $d1_icon = get_theme_mod('annyhase_detail_row1_icon', '🤲');
                $d1_text = get_theme_mod('annyhase_detail_row1_text', 'Handgemachtes Unikat – kleine Abweichungen in Form und Farbe sind Ausdruck echter Handarbeit.');
                $d2_icon = get_theme_mod('annyhase_detail_row2_icon', '📦');
                $d2_text = get_theme_mod('annyhase_detail_row2_text', 'Versand aus Deutschland');
                $d3_icon = get_theme_mod('annyhase_detail_row3_icon', '✉️');
                $d3_text = get_theme_mod('annyhase_detail_row3_text', 'Bei Fragen einfach schreiben');
                ?>
                <div class="product-info__details">
                    <?php if ($d1_text): ?>
                    <div class="product-info__detail-row">
                        <?php if ($d1_icon): ?><span class="product-info__detail-icon"><?php echo esc_html($d1_icon); ?></span><?php endif; ?>
                        <span><?php echo esc_html($d1_text); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($d2_text): ?>
                    <div class="product-info__detail-row">
                        <?php if ($d2_icon): ?><span class="product-info__detail-icon"><?php echo esc_html($d2_icon); ?></span><?php endif; ?>
                        <span><?php echo esc_html($d2_text); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($d3_text): ?>
                    <div class="product-info__detail-row">
                        <?php if ($d3_icon): ?><span class="product-info__detail-icon"><?php echo esc_html($d3_icon); ?></span><?php endif; ?>
                        <a href="<?php echo esc_url(home_url('/kontakt')); ?>" style="color:inherit"><?php echo esc_html($d3_text); ?></a>
                    </div>
                    <?php endif; ?>
                </div>


            </div><!-- /product-info -->

        </div><!-- /product-layout -->
    </div>
</div>

<?php
/* ── Related products + categories ── */
$current_term_ids = [];
if (!empty($terms) && !is_wp_error($terms)) {
    $current_term_ids = wp_list_pluck($terms, 'term_id');
}

$related_args = [
    'post_type'      => 'produkt',
    'posts_per_page' => 8,
    'post__not_in'   => [$post_id],
    'orderby'        => 'date',
    'order'          => 'DESC',
    'no_found_rows'  => true,
];
if (!empty($current_term_ids)) {
    $related_args['tax_query'] = [[
        'taxonomy' => 'produktkategorie',
        'field'    => 'term_id',
        'terms'    => $current_term_ids,
    ]];
}
$related_query = new WP_Query($related_args);
$all_cats       = get_terms(['taxonomy' => 'produktkategorie', 'hide_empty' => true]);
$products_url   = get_post_type_archive_link('produkt') ?: home_url('/#produkte');
$has_related    = $related_query->have_posts();
$has_cats       = !empty($all_cats) && !is_wp_error($all_cats);
?>
<?php if ($has_related || $has_cats): ?>
<section class="related-section">
    <div class="container">
        <div class="related-layout <?php echo (!$has_cats || !$has_related) ? 'related-layout--single' : ''; ?>">

            <?php if ($has_cats): ?>
            <!-- ── Kategorien-Sidebar ── -->
            <aside class="related-cats">
                <h3 class="related-cats__title"><?php esc_html_e('Kategorien', 'annyhase'); ?></h3>
                <ul class="related-cats__list">
                    <li>
                        <a href="<?php echo esc_url($products_url); ?>" class="related-cats__link">
                            <?php esc_html_e('Alle Produkte', 'annyhase'); ?>
                        </a>
                    </li>
                    <?php foreach ($all_cats as $cat): ?>
                    <li>
                        <a href="<?php echo esc_url(get_term_link($cat)); ?>"
                           class="related-cats__link <?php echo in_array($cat->term_id, $current_term_ids) ? 'is-active' : ''; ?>">
                            <?php echo esc_html($cat->name); ?>
                            <span class="related-cats__count"><?php echo absint($cat->count); ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </aside>
            <?php endif; ?>

            <?php if ($has_related): ?>
            <!-- ── Produkt-Slider ── -->
            <div class="related-slider-wrap">
                <div class="related-slider-header">
                    <h3 class="related-slider__title">
                        <?php esc_html_e('Mehr aus', 'annyhase'); ?>
                        <?php if (!empty($terms) && !is_wp_error($terms)): ?>
                            <em><?php echo esc_html($terms[0]->name); ?></em>
                        <?php else: ?>
                            <?php esc_html_e('dem Shop', 'annyhase'); ?>
                        <?php endif; ?>
                    </h3>
                    <div class="related-slider__btns">
                        <button class="related-slider__btn" id="rs-prev" aria-label="Zurück">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <button class="related-slider__btn" id="rs-next" aria-label="Weiter">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    </div>
                </div>
                <div class="related-slider__track" id="rs-track">
                    <?php while ($related_query->have_posts()): $related_query->the_post(); ?>
                    <a href="<?php the_permalink(); ?>" class="related-card">
                        <div class="related-card__img-wrap">
                            <?php if (has_post_thumbnail()): ?>
                                <?php the_post_thumbnail('product-thumb', ['class' => 'related-card__img', 'loading' => 'lazy', 'alt' => get_the_title()]); ?>
                            <?php else: ?>
                                <div class="related-card__img--empty">🏺</div>
                            <?php endif; ?>
                            <?php $rbadge = annyhase_product_badge(); if ($rbadge): ?>
                                <span class="related-card__badge"><?php echo esc_html($rbadge); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="related-card__body">
                            <p class="related-card__title"><?php the_title(); ?></p>
                            <?php $rprice = annyhase_product_price(); if ($rprice): ?>
                            <p class="related-card__price"><?php echo esc_html($rprice); ?></p>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</section>
<?php endif; ?>

<?php else: ?>

<!-- ══ Normaler Beitrag / Seite ══ -->
<div style="background:var(--color-cream);padding:2.5rem 0 1.75rem">
    <div class="container">
        <h1 style="font-size:clamp(1.3rem,2.5vw,1.9rem);max-width:60ch;line-height:1.3"><?php the_title(); ?></h1>
    </div>
</div>
<section class="section" style="padding-top:2.5rem">
    <div class="container">
        <div style="max-width:820px">
            <?php if (has_post_thumbnail()): ?>
            <div style="margin-bottom:2.5rem;border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-md)">
                <?php the_post_thumbnail('product-wide', ['style' => 'width:100%;max-height:450px;object-fit:cover;display:block', 'loading' => 'lazy']); ?>
            </div>
            <?php endif; ?>
            <div class="entry-content" style="line-height:1.85"><?php the_content(); ?></div>
            <div style="margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid var(--color-border)">
                <a href="javascript:history.back()" class="btn btn-outline">&larr; <?php esc_html_e('Zurück', 'annyhase'); ?></a>
            </div>
        </div>
    </div>
</section>

<?php endif; ?>

<?php endwhile; ?>

<?php if ($is_produkt): get_template_part('inc/testimonials'); endif; ?>

<!-- ══════════════════════════════════════════
     LIGHTBOX
     ══════════════════════════════════════════ -->
<?php if (!empty($gallery_ids)): ?>

<script>
var annyhaseGallery = [
    <?php foreach ($gallery_items as $gitem):
        if ($gitem['type'] === 'image'):
            $full  = wp_get_attachment_image_url($gitem['id'], 'full');
            $large = $full ?: wp_get_attachment_image_url($gitem['id'], 'product-wide');
            $alt   = get_post_meta($gitem['id'], '_wp_attachment_image_alt', true) ?: get_the_title();
    ?>
    { type: 'image', large: "<?php echo esc_js($large); ?>", full: "<?php echo esc_js($full); ?>", alt: "<?php echo esc_js($alt); ?>" },
    <?php elseif ($gitem['type'] === 'video'): ?>
    { type: 'video', url: "<?php echo esc_js($video_url); ?>", thumb: "<?php echo esc_js($video_thumb); ?>" },
    <?php endif; endforeach; ?>
];
</script>

<div class="lightbox-overlay" id="lightbox" role="dialog" aria-modal="true" aria-label="Bildvorschau">
    <button class="lightbox-close" id="lb-close" aria-label="Schließen">&times;</button>
    <button class="lightbox-prev" id="lb-prev" aria-label="Zurück"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
    <img src="" alt="" class="lightbox-img" id="lb-img">
    <video id="lb-video" class="lightbox-video" controls playsinline style="display:none">
        <source id="lb-video-src" src="" type="video/mp4">
    </video>
    <button class="lightbox-next" id="lb-next" aria-label="Weiter"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></button>
    <div class="lightbox-counter" id="lb-counter"></div>
</div>

<?php endif; ?>

<?php get_footer(); ?>
