<?php defined('ABSPATH') || exit; get_header(); ?>

<section class="section" style="min-height:60vh;display:flex;align-items:center">
    <div class="container text-center">
        <div style="font-size:5rem;margin-bottom:1.5rem">🏺</div>
        <h1 style="font-size:5rem;color:var(--color-terracotta);line-height:1">404</h1>
        <h2 style="margin-bottom:1rem"><?php esc_html_e('Seite nicht gefunden', 'annyhase'); ?></h2>
        <p style="color:var(--color-text-muted);max-width:44ch;margin-inline:auto;margin-bottom:2rem">
            <?php esc_html_e('Diese Seite existiert leider nicht. Vielleicht findest du im Shop etwas Schönes?', 'annyhase'); ?>
        </p>
        <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn-primary"><?php esc_html_e('Zur Startseite', 'annyhase'); ?></a>
            <a href="<?php echo esc_url(get_theme_mod('annyhase_etsy_shop_url', 'https://www.etsy.com/shop/Annyhase')); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline">🛍️ Etsy Shop</a>
        </div>
    </div>
</section>

<?php get_footer(); ?>
