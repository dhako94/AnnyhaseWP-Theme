<?php defined('ABSPATH') || exit; get_header(); ?>

<div style="padding:4rem 0 2rem;background:var(--color-cream)">
    <div class="container">
        <h1><?php
            if (is_archive()) { the_archive_title(); }
            elseif (is_search()) { printf(esc_html__('Suchergebnisse für: %s', 'annyhase'), get_search_query()); }
            else { esc_html_e('Blog', 'annyhase'); }
        ?></h1>
    </div>
</div>

<section class="section">
    <div class="container">
        <?php if (have_posts()): ?>
        <div class="grid-3">
            <?php while (have_posts()): the_post(); ?>
            <article class="product-card">
                <?php if (has_post_thumbnail()): ?>
                <a href="<?php the_permalink(); ?>" class="product-card__img-wrap">
                    <?php the_post_thumbnail('product-thumb', ['class' => 'product-card__img', 'loading' => 'lazy']); ?>
                </a>
                <?php endif; ?>
                <div class="product-card__body">
                    <div class="product-card__category"><?php echo esc_html(get_the_date()); ?></div>
                    <h2 class="product-card__title" style="font-size:1.1rem">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h2>
                    <?php the_excerpt(); ?>
                </div>
                <div class="product-card__footer">
                    <a href="<?php the_permalink(); ?>" class="product-card__link"><?php esc_html_e('Weiterlesen', 'annyhase'); ?> &rarr;</a>
                </div>
            </article>
            <?php endwhile; ?>
        </div>
        <div style="margin-top:3rem"><?php the_posts_pagination(['mid_size' => 2]); ?></div>
        <?php else: ?>
        <p><?php esc_html_e('Keine Beiträge gefunden.', 'annyhase'); ?></p>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
