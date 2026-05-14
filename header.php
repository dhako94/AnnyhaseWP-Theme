<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#c4704a">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php if (file_exists(get_template_directory() . '/assets/img/apple-touch-icon.png')): ?>
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/img/apple-touch-icon.png">
    <?php endif; ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a href="#main-content" class="skip-link"><?php esc_html_e('Zum Inhalt springen', 'annyhase'); ?></a>

<header class="site-header" id="site-header">
    <div class="container">
        <div class="header-inner">

            <?php
            $hdr_mode  = get_theme_mod('annyhase_header_logo_mode', 'logo_text');
            $hdr_split = absint(get_theme_mod('annyhase_header_brand_split', 4));
            $hdr_name  = get_bloginfo('name');
            $hdr_p1    = mb_substr($hdr_name, 0, $hdr_split);
            $hdr_p2    = mb_substr($hdr_name, $hdr_split);
            $hdr_logo  = has_custom_logo() && in_array($hdr_mode, ['logo_only', 'logo_text'], true);
            $hdr_text  = $hdr_mode !== 'logo_only' || !has_custom_logo();
            ?>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo" aria-label="<?php echo esc_attr($hdr_name); ?>">
                <?php if ($hdr_logo):
                    preg_match('/<img[^>]+>/i', get_custom_logo(), $m);
                    echo $m[0] ?? '';
                endif; ?>
                <?php if ($hdr_text): ?>
                <span class="site-logo__text"><?php echo esc_html($hdr_p1); ?><span><?php echo esc_html($hdr_p2); ?></span></span>
                <?php endif; ?>
            </a>

            <nav class="site-nav" aria-label="<?php esc_attr_e('Hauptnavigation', 'annyhase'); ?>">
                <button class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="nav-menu" aria-label="<?php esc_attr_e('Menü öffnen', 'annyhase'); ?>">
                    <span></span><span></span><span></span>
                </button>

                <?php
                wp_nav_menu([
                    'theme_location' => 'primary',
                    'menu_id'        => 'nav-menu',
                    'container'      => false,
                    'menu_class'     => 'nav-menu',
                    'fallback_cb'    => 'annyhase_fallback_nav',
                ]);
                ?>
            </nav>

        </div>
    </div>
</header>
<div id="main-content" tabindex="-1"></div>

<?php
/**
 * Renders a hard-coded fallback navigation menu when no menu is assigned to
 * the 'primary' location in the Customizer.
 *
 * Outputs the five main anchors plus an Etsy shop CTA button.
 * Uses the `annyhase_etsy_shop_url` Customizer setting for the Etsy link.
 */
function annyhase_fallback_nav(): void {
    $etsy_url = get_theme_mod('annyhase_etsy_shop_url', 'https://www.etsy.com/shop/Annyhase');
    echo '<ul class="nav-menu" id="nav-menu">';
    echo '<li><a href="' . esc_url(home_url('/')) . '">' . esc_html__('Start', 'annyhase') . '</a></li>';
    echo '<li><a href="' . esc_url(home_url('/#produkte')) . '">' . esc_html__('Produkte', 'annyhase') . '</a></li>';
    echo '<li><a href="' . esc_url(home_url('/#galerie')) . '">' . esc_html__('Galerie', 'annyhase') . '</a></li>';
    echo '<li><a href="' . esc_url(home_url('/#ueber-mich')) . '">' . esc_html__('Über mich', 'annyhase') . '</a></li>';
    echo '<li><a href="' . esc_url(home_url('/kontakt')) . '">' . esc_html__('Kontakt', 'annyhase') . '</a></li>';
    echo '<li class="nav-etsy-item"><a href="' . esc_url($etsy_url) . '" target="_blank" rel="noopener noreferrer" class="nav-etsy-btn">' . esc_html__('Etsy Shop', 'annyhase') . '</a></li>';
    echo '</ul>';
}
