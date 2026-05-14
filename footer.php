<?php
defined('ABSPATH') || exit;
$etsy_url      = get_theme_mod('annyhase_etsy_shop_url', 'https://www.etsy.com/shop/Annyhase');
$instagram_url = get_theme_mod('annyhase_instagram_url', 'https://www.instagram.com/annyhase_official');
$year          = wp_date('Y');

// Footer Brand
$ftr_mode    = get_theme_mod('annyhase_footer_logo_mode', 'logo_text');
$ftr_logo_id = absint(get_theme_mod('annyhase_footer_logo', 0));
$ftr_title   = get_theme_mod('annyhase_footer_brand_title', 'Annyhase');
$ftr_split   = absint(get_theme_mod('annyhase_footer_brand_split', 4));
$ftr_p1      = mb_substr($ftr_title, 0, $ftr_split);
$ftr_p2      = mb_substr($ftr_title, $ftr_split);
$ftr_tag     = get_theme_mod('annyhase_footer_brand_tagline', 'Handgemachte Keramik & Unikate aus dem Schwabenland');
$ftr_claim   = get_theme_mod('annyhase_footer_tagline', 'Handgemacht mit Liebe');
$ftr_emoji   = get_theme_mod('annyhase_footer_tagline_emoji', '🤍');
$ftr_fallback = get_template_directory() . '/assets/img/FooterLogo.png';
$ftr_has_any  = $ftr_logo_id || file_exists($ftr_fallback);
$ftr_haslogo  = $ftr_has_any && in_array($ftr_mode, ['logo_only', 'logo_text'], true);
$ftr_hastext  = $ftr_mode !== 'logo_only' || !$ftr_has_any;
?>

<!-- Etsy Banner -->
<section class="etsy-banner">
    <div class="container">
        <div class="etsy-banner__inner">
            <div class="etsy-banner__icon"><?php annyhase_etsy_banner_logo(); ?></div>
            <div class="etsy-banner__text">
                <h2><?php echo esc_html(get_theme_mod('annyhase_etsy_banner_title', 'Auch auf Etsy erhältlich')); ?></h2>
                <p><?php echo esc_html(get_theme_mod('annyhase_etsy_banner_desc', 'Bestell bequem über meinen Etsy-Shop – sichere Zahlung, weltweiter Versand.')); ?></p>
            </div>
            <a href="<?php echo esc_url($etsy_url); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-white">
                <?php echo esc_html(get_theme_mod('annyhase_etsy_banner_btn', 'Zum Etsy Shop')); ?> &rarr;
            </a>
        </div>
    </div>
</section>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">

            <!-- Brand -->
            <div class="footer-brand">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo">
                    <?php if ($ftr_haslogo): ?>
                        <?php if ($ftr_logo_id): ?>
                            <?php echo wp_get_attachment_image($ftr_logo_id, [240, 60], false, [
                                'style' => 'height:44px;width:auto;object-fit:contain',
                                'alt'   => esc_attr($ftr_title),
                            ]); ?>
                        <?php else: ?>
                            <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/img/FooterLogo.png'); ?>" alt="<?php echo esc_attr($ftr_title); ?>" style="height:44px;width:auto;object-fit:contain">
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($ftr_hastext): ?>
                    <span class="site-logo__text"><?php echo esc_html($ftr_p1); ?><span style="color:var(--color-terracotta)"><?php echo esc_html($ftr_p2); ?></span></span>
                    <?php endif; ?>
                </a>
                <p><?php echo esc_html($ftr_tag); ?></p>
                <div class="footer-social-links">
                    <a href="<?php echo esc_url($etsy_url); ?>" target="_blank" rel="noopener noreferrer" class="footer-social-btn">
                        <?php echo wp_kses(annyhase_etsy_svg(16), ['svg' => ['xmlns' => [], 'width' => [], 'height' => [], 'viewbox' => [], 'fill' => [], 'aria-hidden' => []], 'path' => ['d' => []]]); ?> Etsy
                    </a>
                    <a href="<?php echo esc_url($instagram_url); ?>" target="_blank" rel="noopener noreferrer" class="footer-social-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                        Instagram
                    </a>
                </div>
            </div>

            <!-- Navigation -->
            <div class="footer-col">
                <h4><?php esc_html_e('Navigation', 'annyhase'); ?></h4>
                <?php if (has_nav_menu('footer_1')): ?>
                    <?php wp_nav_menu([
                        'theme_location' => 'footer_1',
                        'container'      => false,
                        'items_wrap'     => '<ul>%3$s</ul>',
                        'fallback_cb'    => false,
                        'depth'          => 1,
                    ]); ?>
                <?php else: ?>
                <ul>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Start', 'annyhase'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/#produkte')); ?>"><?php esc_html_e('Produkte', 'annyhase'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/#galerie')); ?>"><?php esc_html_e('Galerie', 'annyhase'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/#ueber-mich')); ?>"><?php esc_html_e('Über mich', 'annyhase'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/kontakt')); ?>"><?php esc_html_e('Kontakt', 'annyhase'); ?></a></li>
                </ul>
                <?php endif; ?>
            </div>

            <!-- Produkte -->
            <div class="footer-col">
                <h4><?php esc_html_e('Produkte', 'annyhase'); ?></h4>
                <?php if (has_nav_menu('footer_3')): ?>
                    <?php wp_nav_menu([
                        'theme_location' => 'footer_3',
                        'container'      => false,
                        'items_wrap'     => '<ul>%3$s</ul>',
                        'fallback_cb'    => false,
                        'depth'          => 1,
                    ]); ?>
                <?php else:
                    $terms = get_terms(['taxonomy' => 'produktkategorie', 'hide_empty' => false, 'number' => 5]);
                ?>
                <ul>
                    <?php if (!is_wp_error($terms) && $terms):
                        foreach ($terms as $term): ?>
                        <li><a href="<?php echo esc_url(get_term_link($term)); ?>"><?php echo esc_html($term->name); ?></a></li>
                    <?php endforeach; else: ?>
                        <li><a href="<?php echo esc_url(home_url('/produkte')); ?>"><?php esc_html_e('Alle Produkte', 'annyhase'); ?></a></li>
                        <li><a href="<?php echo esc_url($etsy_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Etsy Shop', 'annyhase'); ?></a></li>
                    <?php endif; ?>
                </ul>
                <?php endif; ?>
            </div>

            <!-- Rechtliches -->
            <div class="footer-col">
                <h4><?php esc_html_e('Rechtliches', 'annyhase'); ?></h4>
                <?php if (has_nav_menu('footer_2')): ?>
                    <?php wp_nav_menu([
                        'theme_location' => 'footer_2',
                        'container'      => false,
                        'items_wrap'     => '<ul>%3$s</ul>',
                        'fallback_cb'    => false,
                        'depth'          => 1,
                    ]); ?>
                <?php else: ?>
                <ul>
                    <li><a href="<?php echo esc_url(home_url('/impressum')); ?>"><?php esc_html_e('Impressum', 'annyhase'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/datenschutz')); ?>"><?php esc_html_e('Datenschutz', 'annyhase'); ?></a></li>
                </ul>
                <?php endif; ?>
            </div>

        </div>

        <div class="footer-bottom">
            <span>&copy; <?php echo esc_html($year); ?> <?php echo esc_html(get_bloginfo('name')); ?> &mdash; <?php echo esc_html($ftr_claim); ?> <?php echo esc_html($ftr_emoji); ?></span>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
