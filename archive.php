<?php
defined('ABSPATH') || exit;
$archive_label   = get_theme_mod('annyhase_archive_label', 'Alle Produkte');
$archive_columns = get_theme_mod('annyhase_archive_columns', '4');
$grid_class      = 'grid-' . absint($archive_columns);

if (is_tax('produktkategorie')) {
    $queried     = get_queried_object();
    $page_label  = esc_html__('Kategorie', 'annyhase');
    $page_title  = esc_html($queried->name);
    $page_desc   = $queried->description;
} else {
    $page_label  = esc_html($archive_label);
    $page_title  = post_type_archive_title('', false);
    $page_desc   = '';
}

get_header();
?>

<div class="archive-hero">
    <div class="container">
        <span class="section-label"><?php echo esc_html($page_label); ?></span>
        <h1><?php echo esc_html($page_title); ?></h1>
        <?php if ($page_desc): ?>
            <p style="color:var(--color-text-muted);margin-top:.5rem"><?php echo esc_html($page_desc); ?></p>
        <?php endif; ?>
    </div>
</div>

<section class="section">
    <div class="container">

        <?php
        $ps_term       = sanitize_text_field(wp_unslash($_GET['ps'] ?? ''));
        $archive_url   = get_post_type_archive_link('produkt') ?: home_url('/produkt');
        ?>
        <!-- Product search (server-side: searches across all products) -->
        <form class="ps-wrap" method="get" action="<?php echo esc_url($archive_url); ?>" role="search">
            <div class="ps-field">
                <svg class="ps-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" x2="16.65" y1="21" y2="16.65"/></svg>
                <input type="search" id="ps-input" name="ps" class="ps-input"
                       value="<?php echo esc_attr($ps_term); ?>"
                       placeholder="<?php esc_attr_e('Produkt suchen …', 'annyhase'); ?>"
                       aria-label="<?php esc_attr_e('Produkte suchen', 'annyhase'); ?>"
                       autocomplete="off">
                <?php if ($ps_term): ?>
                <a href="<?php echo esc_url($archive_url); ?>" class="ps-clear" aria-label="<?php esc_attr_e('Suche leeren', 'annyhase'); ?>">&times;</a>
                <?php else: ?>
                <button type="submit" class="ps-submit" aria-label="<?php esc_attr_e('Suchen', 'annyhase'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>
                <?php endif; ?>
            </div>
        </form>
        <?php if ($ps_term): ?>
        <p class="ps-active-hint">
            <?php printf(
                esc_html__('Suchergebnisse für: %s', 'annyhase'),
                '<strong>' . esc_html($ps_term) . '</strong>'
            ); ?>
            <a href="<?php echo esc_url($archive_url); ?>" style="margin-left:.5rem;font-size:.8em"><?php esc_html_e('Zurücksetzen', 'annyhase'); ?></a>
        </p>
        <?php endif; ?>

        <?php
        $tax_terms = get_terms(['taxonomy' => 'produktkategorie', 'hide_empty' => true]);
        if (!is_wp_error($tax_terms) && $tax_terms):
        ?>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:2.5rem">
            <a href="<?php echo esc_url(get_post_type_archive_link('produkt')); ?>"
               class="tag <?php echo !is_tax() ? 'tag--terracotta' : ''; ?>">
                <?php esc_html_e('Alle', 'annyhase'); ?>
            </a>
            <?php foreach ($tax_terms as $term): ?>
            <a href="<?php echo esc_url(get_term_link($term)); ?>"
               class="tag <?php echo is_tax('produktkategorie', $term) ? 'tag--terracotta' : ''; ?>">
                <?php echo esc_html($term->name); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (have_posts()): ?>
        <div class="<?php echo esc_attr($grid_class); ?>">
            <?php while (have_posts()): the_post(); ?>
            <article class="product-card reveal">
                <a href="<?php the_permalink(); ?>" class="product-card__img-wrap">
                    <?php if (has_post_thumbnail()): ?>
                        <?php the_post_thumbnail('product-thumb', ['class' => 'product-card__img', 'loading' => 'lazy', 'alt' => get_the_title()]); ?>
                    <?php else: ?>
                        <div class="product-card__img" style="background:var(--color-cream);display:flex;align-items:center;justify-content:center;font-size:3rem">🏺</div>
                    <?php endif; ?>
                    <?php $badge = annyhase_product_badge(); if ($badge): ?>
                        <span class="product-card__badge"><?php echo esc_html($badge); ?></span>
                    <?php endif; ?>
                </a>
                <div class="product-card__body">
                    <?php
                    $terms = get_the_terms(get_the_ID(), 'produktkategorie');
                    if ($terms && !is_wp_error($terms)):
                    ?>
                    <div class="product-card__category"><?php echo esc_html($terms[0]->name); ?></div>
                    <?php endif; ?>
                    <h2 class="product-card__title" style="font-size:1.05rem">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h2>
                    <?php $price = annyhase_product_price(); if ($price): ?>
                    <div class="product-card__price"><?php echo esc_html($price); ?></div>
                    <?php endif; ?>
                </div>
                <div class="product-card__footer">
                    <?php if (annyhase_is_etsy() && annyhase_etsy_url()): ?>
                        <a href="<?php echo esc_url(annyhase_etsy_url()); ?>" target="_blank" rel="noopener noreferrer" class="product-card__link">
                            🛍️ <?php esc_html_e('Auf Etsy', 'annyhase'); ?>
                        </a>
                    <?php else: ?>
                        <a href="<?php the_permalink(); ?>" class="product-card__link"><?php esc_html_e('Details', 'annyhase'); ?> &rarr;</a>
                    <?php endif; ?>
                </div>
            </article>
            <?php endwhile; ?>
        </div>

        <div class="pagination-wrap"><?php the_posts_pagination([
            'mid_size'           => 2,
            'prev_text'          => '&larr; <span>' . esc_html__('Vorherige', 'annyhase') . '</span>',
            'next_text'          => '<span>' . esc_html__('Nächste', 'annyhase') . '</span> &rarr;',
            'screen_reader_text' => ' ',
            'add_args'           => $ps_term ? ['ps' => $ps_term] : [],
        ]); ?></div>
        <?php else: ?>
        <div class="text-center" style="padding:4rem;background:var(--color-surface);border-radius:var(--radius-lg);border:1px solid var(--color-border)">
            <?php if ($ps_term): ?>
            <div style="font-size:3rem;margin-bottom:1rem">🔍</div>
            <h3><?php printf(esc_html__('Kein Produkt für „%s" gefunden', 'annyhase'), esc_html($ps_term)); ?></h3>
            <p class="text-muted"><?php esc_html_e('Versuche einen anderen Suchbegriff.', 'annyhase'); ?></p>
            <a href="<?php echo esc_url($archive_url); ?>" class="btn btn-outline" style="margin-top:1rem"><?php esc_html_e('Alle Produkte anzeigen', 'annyhase'); ?></a>
            <?php else: ?>
            <div style="font-size:3rem;margin-bottom:1rem">🏺</div>
            <h3><?php esc_html_e('Noch keine Produkte', 'annyhase'); ?></h3>
            <p class="text-muted"><?php esc_html_e('Schau bald wieder vorbei!', 'annyhase'); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php get_template_part('inc/testimonials'); ?>

<?php get_footer(); ?>
