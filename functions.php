<?php
/**
 * Annyhase Theme Functions
 *
 * Core theme bootstrap: feature support, asset enqueuing, custom post types,
 * admin columns, contact-form handler, SEO meta hooks, and template-tag helpers.
 *
 * @package Annyhase
 */

defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/etsy-api.php';
require_once get_template_directory() . '/inc/etsy-media.php';
require_once get_template_directory() . '/inc/plugin-setup.php';

/* -------------------------------------------------------
   Kommentare & Beiträge vollständig deaktivieren
------------------------------------------------------- */

// Kommentare an allen Post-Types schließen
add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open',    '__return_false', 20, 2);
add_filter('comments_array', '__return_empty_array', 20, 2);

// Comment-Support von allen Post-Types entfernen
add_action('init', function (): void {
    foreach (get_post_types() as $pt) {
        if (post_type_supports($pt, 'comments')) {
            remove_post_type_support($pt, 'comments');
            remove_post_type_support($pt, 'trackbacks');
        }
    }
});

// Admin-Menü: Kommentare & Beiträge ausblenden
add_action('admin_menu', function (): void {
    remove_menu_page('edit-comments.php');
    remove_menu_page('edit.php');
}, 99);

// Admin-Bar: Kommentare entfernen
add_action('wp_before_admin_bar_render', function (): void {
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('comments');
    $wp_admin_bar->remove_menu('new-post');
});

// Dashboard-Widgets entfernen
add_action('wp_dashboard_setup', function (): void {
    remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    remove_meta_box('dashboard_activity',        'dashboard', 'normal');
});

// Weiterleitungen falls jemand die URLs direkt aufruft
add_action('admin_init', function (): void {
    global $pagenow;
    if ($pagenow === 'edit-comments.php' || $pagenow === 'comment.php') {
        wp_redirect(admin_url());
        exit;
    }
    if ($pagenow === 'edit.php' && (empty($_GET['post_type']) || $_GET['post_type'] === 'post')) {
        wp_redirect(admin_url());
        exit;
    }
});

// Kommentar-Zähler im Admin-Menü-Icon entfernen
add_filter('wp_count_comments', function (): object {
    return (object) ['approved' => 0, 'spam' => 0, 'trash' => 0,
                     'post-trashed' => 0, 'total_comments' => 0,
                     'all' => 0, 'moderated' => 0];
});

/* -------------------------------------------------------
   Theme Setup
------------------------------------------------------- */
/**
 * Registers theme support features, image sizes, and navigation menus.
 *
 * Hooked to `after_setup_theme`.
 */
function annyhase_setup(): void {
    load_theme_textdomain('annyhase', get_template_directory() . '/languages');

    add_theme_support('title-tag');
    add_theme_support('automatic-feed-links');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', [
        'height'      => 80,
        'width'       => 300,
        'flex-height' => true,
        'flex-width'  => true,
        'header-text' => ['site-title', 'site-description'],
    ]);
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('customize-selective-refresh-widgets');
    add_theme_support('wp-block-styles');
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');

    add_image_size('product-thumb', 600, 600, true);
    add_image_size('product-wide',  900, 600, true);
    add_image_size('hero-image',   1000, 1250, true);

    register_nav_menus([
        'primary'  => __('Hauptnavigation', 'annyhase'),
        'footer_1' => __('Footer Navigation', 'annyhase'),
        'footer_2' => __('Footer Rechtliches', 'annyhase'),
        'footer_3' => __('Footer Produkte', 'annyhase'),
    ]);
}
add_action('after_setup_theme', 'annyhase_setup');

/* -------------------------------------------------------
   Wartungsmodus
------------------------------------------------------- */
add_action('template_redirect', function (): void {
    if (!get_theme_mod('annyhase_maintenance_mode', false)) return;
    if (is_user_logged_in()) return;

    status_header(503);
    header('Retry-After: 3600');
    $site_name = esc_html(get_bloginfo('name'));
    $msg_title = esc_html(get_theme_mod('annyhase_maintenance_title', 'Gleich wieder für euch da!'));
    $msg_text  = esc_html(get_theme_mod('annyhase_maintenance_text',  'Die Website wird gerade aktualisiert und ist vorübergehend nicht erreichbar. Schau bald wieder vorbei!'));
    ?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo $site_name; ?> – Wartung</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#fdf8f4;color:#3d2b1f;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem;text-align:center}
.wrap{max-width:500px}
.brand{font-size:2.2rem;font-weight:800;letter-spacing:-.02em;margin-bottom:1.75rem}
.brand span{color:#c4704a}
h1{font-size:1.9rem;font-weight:700;margin-bottom:.85rem;line-height:1.25}
h1 span{color:#c4704a}
p{color:#7a5c4f;line-height:1.75;font-size:1.05rem}
.badge{display:inline-block;margin-top:2rem;background:#c4704a;color:#fff;padding:.45rem 1.4rem;border-radius:999px;font-size:.82rem;letter-spacing:.04em;text-transform:uppercase;font-weight:600}
</style>
</head>
<body>
<div class="wrap">
    <div class="brand">Anny<span>hase</span></div>
    <h1><?php echo $msg_title; ?></h1>
    <p><?php echo $msg_text; ?></p>
    <div class="badge">Wartungsmodus aktiv</div>
</div>
</body>
</html><?php
    exit;
});

/* -------------------------------------------------------
   Navigation: Etsy-Link automatisch als Button stylen
   + aktive Klasse für OnePager-Anchor-Links
------------------------------------------------------- */
add_filter('nav_menu_link_attributes', function (array $atts, WP_Post $item): array {
    if (!empty($item->url) && str_contains($item->url, 'etsy.com')) {
        $atts['class']  = trim(($atts['class'] ?? '') . ' nav-etsy-btn');
        $atts['target'] = '_blank';
        $atts['rel']    = 'noopener noreferrer';
    }
    return $atts;
}, 10, 2);

/*
 * Single nav_menu_css_class callback handling two concerns:
 *  1. Flag Etsy links so CSS can style the <li> as a pill button.
 *  2. On the front page, strip WordPress active-state classes from anchor
 *     links and the home link — JS scroll-spy manages the active state.
 */
add_filter('nav_menu_css_class', function (array $classes, WP_Post $item): array {
    if (!empty($item->url) && str_contains($item->url, 'etsy.com')) {
        $classes[] = 'nav-etsy-item';
    }

    if (is_front_page() && !empty($item->url)) {
        $path     = rtrim(parse_url($item->url, PHP_URL_PATH) ?? '', '/');
        $fragment = parse_url($item->url, PHP_URL_FRAGMENT);
        $home     = rtrim(parse_url(home_url('/'), PHP_URL_PATH) ?? '', '/');
        if ($fragment || $path === $home || $path === '') {
            $classes = array_diff($classes, ['current-menu-item', 'current_page_item', 'current-menu-ancestor']);
        }
    }

    return $classes;
}, 10, 2);

/* -------------------------------------------------------
   Standard-Menü automatisch anlegen (einmalig)
------------------------------------------------------- */
/**
 * Creates and assigns a default primary navigation menu on first activation.
 *
 * Only runs once — subsequent calls are skipped if a primary location is already set.
 * Also creates the /kontakt page with the correct page template if it does not exist.
 */
function annyhase_create_default_menu(): void {
    $locations = get_nav_menu_locations();
    if (!empty($locations['primary'])) return;

    $existing = get_term_by('name', 'Hauptnavigation', 'nav_menu');
    $menu_id  = $existing ? (int) $existing->term_id : wp_create_nav_menu('Hauptnavigation');

    if (is_wp_error($menu_id)) return;

    /* Alte Einträge löschen wenn Menü schon existierte */
    if ($existing) {
        foreach ((array) wp_get_nav_menu_items($menu_id) as $old) {
            wp_delete_post($old->ID, true);
        }
    }

    $etsy_url = get_theme_mod('annyhase_etsy_shop_url', 'https://www.etsy.com/shop/Annyhase');

    $items = [
        ['title' => 'Start',     'url' => home_url('/')],
        ['title' => 'Produkte',  'url' => home_url('/#produkte')],
        ['title' => 'Galerie',   'url' => home_url('/#galerie')],
        ['title' => 'Über mich', 'url' => home_url('/#ueber-mich')],
        ['title' => 'Kontakt',   'url' => home_url('/kontakt')],
        ['title' => 'Etsy Shop', 'url' => $etsy_url],
    ];

    foreach ($items as $i => $item) {
        wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title'    => $item['title'],
            'menu-item-url'      => $item['url'],
            'menu-item-status'   => 'publish',
            'menu-item-type'     => 'custom',
            'menu-item-position' => $i + 1,
        ]);
    }

    $locs           = (array) get_theme_mod('nav_menu_locations', []);
    $locs['primary'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locs);
}
add_action('init', function () {
    $locs = get_nav_menu_locations();
    if (empty($locs['primary'])) annyhase_create_default_menu();

    /* Pflichtseiten einmalig anlegen falls nicht vorhanden */
    if (!get_transient('annyhase_pages_ready')) {
        $stub_pages = [
            'kontakt'    => ['title' => 'Kontakt',    'template' => 'page-kontakt.php'],
            'impressum'  => ['title' => 'Impressum',  'template' => ''],
            'datenschutz'=> ['title' => 'Datenschutz','template' => ''],
        ];
        foreach ($stub_pages as $slug => $cfg) {
            if (get_page_by_path($slug)) continue;
            $id = wp_insert_post([
                'post_title'   => $cfg['title'],
                'post_name'    => $slug,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => $cfg['template'] ? '' : sprintf(
                    '<p><em>Bitte füge hier deinen %s-Text ein.</em></p>',
                    $cfg['title']
                ),
            ]);
            if ($id && !is_wp_error($id) && $cfg['template']) {
                update_post_meta($id, '_wp_page_template', $cfg['template']);
            }
        }
        set_transient('annyhase_pages_ready', 1, WEEK_IN_SECONDS);
    }
});

/* -------------------------------------------------------
   Standard-Assets einmalig in Mediathek importieren
   (Header-Logo, Footer-Logo, Favicon)
------------------------------------------------------- */
add_action('admin_init', function (): void {
    if (get_option('annyhase_default_assets_imported')) return;

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $assets = [
        ['file' => 'HeaderLogo.png', 'mod' => 'custom_logo',         'option' => ''],
        ['file' => 'FooterLogo.png', 'mod' => 'annyhase_footer_logo', 'option' => ''],
        ['file' => 'favicon.png',    'mod' => '',                     'option' => 'site_icon'],
    ];

    foreach ($assets as $item) {
        $path = get_template_directory() . '/assets/img/' . $item['file'];
        if (!file_exists($path)) continue;

        if ($item['mod']    && get_theme_mod($item['mod']))     continue;
        if ($item['option'] && get_option($item['option']))     continue;

        $upload = wp_upload_bits($item['file'], null, file_get_contents($path));
        if (!empty($upload['error'])) continue;

        $type    = wp_check_filetype($item['file']);
        $att_id  = wp_insert_attachment([
            'post_mime_type' => $type['type'],
            'post_title'     => pathinfo($item['file'], PATHINFO_FILENAME),
            'post_status'    => 'inherit',
        ], $upload['file']);
        if (!$att_id || is_wp_error($att_id)) continue;

        wp_update_attachment_metadata($att_id, wp_generate_attachment_metadata($att_id, $upload['file']));

        if ($item['mod'])    set_theme_mod($item['mod'], $att_id);
        if ($item['option']) update_option($item['option'], $att_id);
    }

    update_option('annyhase_default_assets_imported', 1);
});

/* -------------------------------------------------------
   Standard-Blogbeiträge einmalig löschen
------------------------------------------------------- */
add_action('admin_init', function (): void {
    if (get_option('annyhase_posts_cleaned')) return;

    // Alle Standard-Blogbeiträge löschen
    $posts = get_posts(['post_type' => 'post', 'numberposts' => -1, 'post_status' => 'any']);
    foreach ($posts as $post) {
        wp_delete_post($post->ID, true);
    }

    // Alle Standard-Kategorien (taxonomy: category) löschen
    $terms = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);
    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            wp_delete_term($term->term_id, 'category');
        }
    }

    update_option('annyhase_posts_cleaned', 1);
});

/* -------------------------------------------------------
   Enqueue Assets
------------------------------------------------------- */
/**
 * Enqueues front-end stylesheets and scripts.
 *
 * - Google Fonts (Playfair Display + Lato) locally hosted in assets/fonts/.
 * - Main stylesheet versioned by theme version string.
 * - main.js deferred to footer; AJAX URL and nonce passed as `annyhaseData`.
 *
 * Hooked to `wp_enqueue_scripts`.
 */
function annyhase_enqueue(): void {
    $ver = wp_get_theme()->get('Version');

    wp_enqueue_style(
        'annyhase-fonts',
        get_template_directory_uri() . '/assets/fonts/fonts.css',
        [],
        $ver
    );

    wp_enqueue_style('annyhase-style', get_stylesheet_uri(), ['annyhase-fonts'], $ver);

    wp_enqueue_script('annyhase-main', get_template_directory_uri() . '/assets/js/main.js', [], $ver, true);

    $recaptcha_site_key = get_theme_mod('annyhase_recaptcha_site_key', '');
    if ($recaptcha_site_key && is_page_template('page-kontakt.php')) {
        wp_enqueue_script(
            'google-recaptcha',
            'https://www.google.com/recaptcha/api.js?render=' . esc_attr($recaptcha_site_key),
            [],
            null,
            true
        );
    }

    wp_localize_script('annyhase-main', 'annyhaseData', [
        'ajaxUrl'          => admin_url('admin-ajax.php'),
        'nonce'            => wp_create_nonce('annyhase_nonce'),
        'themeUrl'         => get_template_directory_uri(),
        'recaptchaSiteKey' => $recaptcha_site_key,
    ]);
}
add_action('wp_enqueue_scripts', 'annyhase_enqueue');

/* -------------------------------------------------------
   SEO: Resource hints – dns-prefetch (Etsy)
   Fonts are self-hosted, so no Google Fonts preconnect needed.
------------------------------------------------------- */
add_action('wp_head', function (): void {
    echo '<link rel="dns-prefetch" href="//www.etsy.com">' . "\n";
}, 1);

/* -------------------------------------------------------
   SEO: Canonical URL tag
------------------------------------------------------- */
add_action('wp_head', function (): void {
    $canonical = '';

    if (is_singular()) {
        $canonical = get_permalink();
    } elseif (is_post_type_archive()) {
        $canonical = get_post_type_archive_link(get_queried_object()->name ?? '');
    } elseif (is_tax() || is_category() || is_tag()) {
        $canonical = get_term_link(get_queried_object());
    } elseif (is_front_page()) {
        $canonical = home_url('/');
    }

    if ($canonical && !is_wp_error($canonical)) {
        echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
    }
}, 5);

/* -------------------------------------------------------
   SEO: robots noindex for 404 and search result pages
------------------------------------------------------- */
add_action('wp_head', function (): void {
    if (is_404() || is_search()) {
        echo '<meta name="robots" content="noindex, follow">' . "\n";
    }
}, 5);

/* -------------------------------------------------------
   SEO: Google Search Console Verification
------------------------------------------------------- */
add_action('wp_head', function (): void {
    $google = get_theme_mod('annyhase_google_site_verification', '');
    if ($google) {
        echo '<meta name="google-site-verification" content="' . esc_attr($google) . '">' . "\n";
    }
}, 2);

/* -------------------------------------------------------
   Google Analytics 4 (GA4) – nur laden wenn Measurement ID
   gesetzt und Cookie-Consent erteilt wurde.
   Hinweis: Benötigt ein aktives Consent-Plugin (z.B. Complianz).
------------------------------------------------------- */
add_action('wp_head', function (): void {
    $ga_id = get_theme_mod('annyhase_google_analytics_id', '');
    if (!$ga_id || !preg_match('/^G-[A-Z0-9]+$/', $ga_id)) return;
    $ga_id = esc_js($ga_id);
    echo <<<HTML
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$ga_id}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '{$ga_id}', { anonymize_ip: true });
</script>
HTML;
}, 20);

/* -------------------------------------------------------
   SEO: Open Graph + Twitter Card Meta-Tags
------------------------------------------------------- */
add_action('wp_head', function (): void {
    global $post;

    $site_name = get_bloginfo('name');
    $site_desc = get_bloginfo('description');

    if (is_singular('produkt') && $post) {
        $title   = get_the_title($post->ID);
        $desc    = wp_strip_all_tags(get_the_excerpt($post->ID) ?: wp_trim_words(get_the_content(null, false, $post->ID), 25));
        $url     = get_permalink($post->ID);
        $og_type = 'product';
        $image   = get_the_post_thumbnail_url($post->ID, 'product-wide') ?: '';
    } elseif (is_front_page()) {
        $title   = $site_name;
        $desc    = $site_desc ?: get_theme_mod('annyhase_hero_tagline', 'Handgemachte Keramik & Unikate aus dem Schwabenland');
        $url     = home_url('/');
        $og_type = 'website';
        $image   = '';
    } elseif (is_page()) {
        $title   = get_the_title();
        $desc    = wp_strip_all_tags(get_the_excerpt() ?: wp_trim_words(get_the_content(), 25));
        $url     = get_permalink();
        $og_type = 'website';
        $image   = get_the_post_thumbnail_url(null, 'product-wide') ?: '';
    } else {
        $title   = wp_get_document_title();
        $desc    = $site_desc;
        $url     = home_url(add_query_arg([]));
        $og_type = 'website';
        $image   = '';
    }

    $t = esc_attr(wp_strip_all_tags($title));
    $d = esc_attr(wp_trim_words(wp_strip_all_tags($desc), 30));
    $u = esc_url($url);
    $i = $image ? esc_url($image) : '';

    echo '<meta name="description"          content="' . $d . '">' . "\n";
    echo '<meta property="og:site_name"    content="' . esc_attr($site_name) . '">' . "\n";
    echo '<meta property="og:locale"       content="de_DE">' . "\n";
    echo '<meta property="og:type"         content="' . esc_attr($og_type) . '">' . "\n";
    echo '<meta property="og:title"        content="' . $t . '">' . "\n";
    echo '<meta property="og:description"  content="' . $d . '">' . "\n";
    echo '<meta property="og:url"          content="' . $u . '">' . "\n";
    if ($i) {
        echo '<meta property="og:image"        content="' . $i . '">' . "\n";
        echo '<meta property="og:image:width"  content="900">' . "\n";
        echo '<meta property="og:image:height" content="600">' . "\n";
        echo '<meta property="og:image:type"   content="image/jpeg">' . "\n";
    }
    echo '<meta name="twitter:card"        content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title"       content="' . $t . '">' . "\n";
    echo '<meta name="twitter:description" content="' . $d . '">' . "\n";
    if ($i) {
        echo '<meta name="twitter:image" content="' . $i . '">' . "\n";
    }
}, 5);

/* -------------------------------------------------------
   SEO: Schema.org JSON-LD (Organization, WebSite, Product, BreadcrumbList)
------------------------------------------------------- */
add_action('wp_head', function (): void {
    global $post;

    $site_name = get_bloginfo('name');
    $site_url  = home_url('/');
    $site_desc = get_bloginfo('description') ?: get_theme_mod('annyhase_hero_tagline', 'Handgemachte Keramik & Unikate aus dem Schwabenland');
    $etsy_url  = get_theme_mod('annyhase_etsy_shop_url', 'https://www.etsy.com/shop/Annyhase');
    $instagram = get_theme_mod('annyhase_instagram_url', 'https://www.instagram.com/annyhase_official');

    $schemas = [];

    $local_biz = [
        '@type'        => 'LocalBusiness',
        '@id'          => $site_url . '#organization',
        'name'         => $site_name,
        'url'          => $site_url,
        'description'  => $site_desc,
        'sameAs'       => array_values(array_filter([$etsy_url, $instagram])),
        'contactPoint' => [
            '@type'             => 'ContactPoint',
            'contactType'       => 'customer service',
            'areaServed'        => 'DE',
            'availableLanguage' => 'German',
        ],
    ];
    $schemas[] = $local_biz;

    $schemas[] = [
        '@type'           => 'WebSite',
        '@id'             => $site_url . '#website',
        'url'             => $site_url,
        'name'            => $site_name,
        'publisher'       => ['@id' => $site_url . '#organization'],
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => $site_url . 'produkte/?ps={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];

    if (is_singular('produkt') && $post) {
        $price_raw  = get_post_meta($post->ID, '_produkt_preis', true);
        $price_raw  = trim(str_replace(['€', ' '], '', (string) $price_raw));
        $etsy_prod  = get_post_meta($post->ID, '_etsy_url', true);
        $img_url    = get_the_post_thumbnail_url($post->ID, 'product-wide');
        $prod_url   = get_permalink($post->ID);
        $prod_desc  = wp_strip_all_tags(get_the_excerpt($post->ID) ?: wp_trim_words(get_the_content(null, false, $post->ID), 40));

        $product = [
            '@type'        => 'Product',
            'name'         => get_the_title($post->ID),
            'description'  => $prod_desc,
            'url'          => $prod_url,
            'brand'        => ['@type' => 'Brand', 'name' => $site_name],
            'manufacturer' => ['@id' => $site_url . '#organization'],
        ];
        if ($img_url) {
            $product['image'] = $img_url;
        }
        if ($price_raw) {
            $product['offers'] = [
                '@type'         => 'Offer',
                'price'         => $price_raw,
                'priceCurrency' => 'EUR',
                'availability'  => $etsy_prod
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/PreOrder',
                'url'           => $etsy_prod ?: $prod_url,
                'seller'        => ['@id' => $site_url . '#organization'],
            ];
        }

        $terms    = get_the_terms($post->ID, 'produktkategorie');
        $cat_name = ($terms && !is_wp_error($terms)) ? $terms[0]->name : '';
        $cat_link = ($terms && !is_wp_error($terms)) ? get_term_link($terms[0]) : '';

        $crumbs = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Start',    'item' => $site_url],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Produkte', 'item' => get_post_type_archive_link('produkt') ?: $site_url],
        ];
        if ($cat_name && !is_wp_error($cat_link)) {
            $crumbs[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $cat_name, 'item' => $cat_link];
            $crumbs[] = ['@type' => 'ListItem', 'position' => 4, 'name' => get_the_title($post->ID), 'item' => $prod_url];
        } else {
            $crumbs[] = ['@type' => 'ListItem', 'position' => 3, 'name' => get_the_title($post->ID), 'item' => $prod_url];
        }

        $schemas[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $crumbs];
        $schemas[] = $product;
    }

    if (is_post_type_archive('produkt')) {
        $schemas[] = [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Start',    'item' => $site_url],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Produkte', 'item' => get_post_type_archive_link('produkt')],
            ],
        ];
    }

    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode(
        ['@context' => 'https://schema.org', '@graph' => $schemas],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );
    echo "\n</script>\n";
}, 10);

/* -------------------------------------------------------
   SEO: rel="prev" / rel="next" für Archive-Seiten
------------------------------------------------------- */
add_action('wp_head', function (): void {
    if (!is_archive() && !is_home()) return;

    global $paged, $wp_query;
    $current = max(1, (int) $paged);
    $total   = (int) ($wp_query->max_num_pages ?? 1);

    if ($current > 1) {
        echo '<link rel="prev" href="' . esc_url(get_pagenum_link($current - 1)) . '">' . "\n";
    }
    if ($current < $total) {
        echo '<link rel="next" href="' . esc_url(get_pagenum_link($current + 1)) . '">' . "\n";
    }
}, 5);

/* -------------------------------------------------------
   Widgets
------------------------------------------------------- */
/**
 * Registers the optional sidebar widget area.
 *
 * Hooked to `widgets_init`. The sidebar is available for use in custom templates
 * but is not rendered in any of the default theme templates.
 */
function annyhase_widgets(): void {
    register_sidebar([
        'name'          => __('Sidebar', 'annyhase'),
        'id'            => 'sidebar-1',
        'before_widget' => '<div class="widget">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ]);
}
add_action('widgets_init', 'annyhase_widgets');

/* -------------------------------------------------------
   Custom Post Types
------------------------------------------------------- */
/**
 * Registers the 'produkt' and 'bewertung' custom post types and the
 * 'produktkategorie' taxonomy.
 *
 * Hooked to `init`.
 */
function annyhase_register_cpts(): void {

    // --- Produkte ---
    register_post_type('produkt', [
        'labels' => [
            'name'          => __('Produkte', 'annyhase'),
            'singular_name' => __('Produkt', 'annyhase'),
            'add_new_item'  => __('Produkt hinzufügen', 'annyhase'),
            'edit_item'     => __('Produkt bearbeiten', 'annyhase'),
            'not_found'     => __('Keine Produkte gefunden', 'annyhase'),
        ],
        'public'       => true,
        'has_archive'  => true,
        'rewrite'      => ['slug' => 'produkte'],
        'supports'     => ['title', 'editor', 'thumbnail', 'excerpt'],
        'show_in_rest' => true,
        'menu_icon'    => 'dashicons-products',
        'menu_position'=> 5,
    ]);

    register_taxonomy('produktkategorie', 'produkt', [
        'labels'       => ['name' => __('Kategorien', 'annyhase'), 'singular_name' => __('Kategorie', 'annyhase')],
        'public'       => true,
        'hierarchical' => true,
        'rewrite'      => ['slug' => 'produktkategorie'],
        'show_in_rest' => true,
    ]);

    // --- Bewertungen (Kundenstimmen) ---
    register_post_type('bewertung', [
        'labels' => [
            'name'          => __('Bewertungen', 'annyhase'),
            'singular_name' => __('Bewertung', 'annyhase'),
            'add_new_item'  => __('Bewertung hinzufügen', 'annyhase'),
            'edit_item'     => __('Bewertung bearbeiten', 'annyhase'),
            'not_found'     => __('Keine Bewertungen gefunden', 'annyhase'),
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'supports'      => ['title', 'editor'],
        'show_in_rest'  => true,
        'menu_icon'     => 'dashicons-star-filled',
        'menu_position' => 6,
    ]);
}
add_action('init', 'annyhase_register_cpts');

/* -------------------------------------------------------
   Admin-Listenansicht: Spalten für den Produkt-CPT
------------------------------------------------------- */

/* Spalten definieren */
add_filter('manage_produkt_posts_columns', function (array $cols): array {
    $new = [];
    // Vorschaubild ganz links (nach Checkbox)
    $new['cb']                   = $cols['cb'];
    $new['produkt_thumb']        = __('Bild', 'annyhase');
    $new['produkt_highlight']    = '⭐';
    $new['title']                = $cols['title'];
    $new['produktkategorie']     = __('Kategorie', 'annyhase');
    $new['produkt_preis']        = __('Preis', 'annyhase');
    $new['produkt_badge']        = __('Badge', 'annyhase');
    $new['produkt_galerie_count']= __('Galerie', 'annyhase');
    $new['produkt_video']        = __('Video', 'annyhase');
    $new['date']                 = $cols['date'] ?? __('Datum', 'annyhase');
    return $new;
});

/* Spalteninhalte ausgeben */
add_action('manage_produkt_posts_custom_column', function (string $col, int $post_id): void {
    switch ($col) {

        case 'produkt_highlight':
            $hl  = get_post_meta($post_id, '_produkt_highlight', true) === '1';
            $url = wp_nonce_url(add_query_arg([
                'produkt_action' => 'toggle_highlight',
                'produkt_id'     => $post_id,
            ], admin_url('edit.php?post_type=produkt')), 'toggle_hl_' . $post_id);
            echo '<a href="' . esc_url($url) . '" title="' . ($hl ? 'Highlight entfernen' : 'Als Highlight markieren') . '" style="font-size:22px;text-decoration:none;line-height:1">'
                 . ($hl ? '⭐' : '<span style="opacity:.25;filter:grayscale(1)">⭐</span>')
                 . '</a>';
            break;

        case 'produkt_thumb':
            $thumb = get_the_post_thumbnail($post_id, [56, 56]);
            if ($thumb) {
                echo '<div style="width:56px;height:56px;overflow:hidden;border-radius:4px;border:1px solid #e2e4e7">' . $thumb . '</div>';
            } else {
                echo '<div style="width:56px;height:56px;background:#f0f0f0;border-radius:4px;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;color:#bbb;font-size:18px">📷</div>';
            }
            break;

        case 'produktkategorie':
            $terms = get_the_terms($post_id, 'produktkategorie');
            if ($terms && !is_wp_error($terms)) {
                $links = array_map(function (WP_Term $t): string {
                    $url = add_query_arg([
                        'post_type'        => 'produkt',
                        'produktkategorie' => $t->term_id,
                    ], admin_url('edit.php'));
                    return '<a href="' . esc_url($url) . '">' . esc_html($t->name) . '</a>';
                }, $terms);
                echo implode(', ', $links);
            } else {
                echo '<span style="color:#bbb">–</span>';
            }
            break;

        case 'produkt_preis':
            $preis = get_post_meta($post_id, '_produkt_preis', true);
            if ($preis) {
                $preis = trim(str_replace(['€', ' '], '', $preis));
                echo '<strong>' . esc_html($preis) . ' €</strong>';
            } else {
                echo '<span style="color:#bbb">–</span>';
            }
            break;

        case 'produkt_badge':
            $badge = get_post_meta($post_id, '_produkt_badge', true);
            if ($badge) {
                echo '<span style="display:inline-block;background:#c4704a;color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;white-space:nowrap">'
                     . esc_html($badge) . '</span>';
            } else {
                echo '<span style="color:#bbb">–</span>';
            }
            break;

        case 'produkt_galerie_count':
            $meta = get_post_meta($post_id, '_produkt_galerie', true);
            $ids  = array_filter(array_map('intval', explode(',', $meta ?: '')));
            $cnt  = count($ids);
            $has_thumb = (bool) get_post_thumbnail_id($post_id);
            $total = $cnt + ($has_thumb ? 1 : 0);
            if ($total > 0) {
                echo '<span title="' . esc_attr($cnt . ' Galerie + ' . ($has_thumb ? '1 Cover' : '0 Cover')) . '">'
                     . '🖼 ' . esc_html($total)
                     . '</span>';
            } else {
                echo '<span style="color:#bbb">–</span>';
            }
            break;

        case 'produkt_video':
            $video = get_post_meta($post_id, '_etsy_video_url', true);
            echo $video
                ? '<span title="' . esc_attr($video) . '" style="font-size:18px">🎬</span>'
                : '<span style="color:#bbb">–</span>';
            break;
    }
}, 10, 2);

/* Vorschaubild-Spalte schmal halten */
add_action('admin_head', function (): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'edit-produkt') return;
    echo '<style>
        .column-produkt_thumb        { width:68px !important; }
        .column-produkt_highlight    { width:44px; text-align:center; }
        .column-produkt_preis        { width:80px; white-space:nowrap; }
        .column-produkt_badge        { width:100px; }
        .column-produkt_galerie_count{ width:60px; text-align:center; }
        .column-produkt_video        { width:55px; text-align:center; }
        .column-produktkategorie     { width:130px; }
    </style>';
});

/* Highlight-Toggle per Klick auf den Stern in der Spalte */
add_action('admin_init', function (): void {
    $action = sanitize_key($_GET['produkt_action'] ?? '');
    if ($action !== 'toggle_highlight' || empty($_GET['produkt_id'])) return;

    $post_id = (int) $_GET['produkt_id'];
    if (
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'toggle_hl_' . $post_id) ||
        !current_user_can('edit_post', $post_id)
    ) wp_die('Ungültige Anfrage.');

    $current = get_post_meta($post_id, '_produkt_highlight', true);
    update_post_meta($post_id, '_produkt_highlight', $current === '1' ? '0' : '1');

    wp_safe_redirect(admin_url('edit.php?post_type=produkt'));
    exit;
});

/**
 * SQL clauses filter that floats highlighted products to the top of a query.
 *
 * Adds a LEFT JOIN on postmeta for `_produkt_highlight` and replaces the
 * ORDER BY clause so highlighted items (value = '1') appear before others,
 * with newest-first as the secondary sort.
 *
 * @param  array $clauses Associative array of SQL clause fragments.
 * @return array Modified clauses.
 */
function annyhase_highlight_order_clauses(array $clauses): array {
    global $wpdb;
    $clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} AS hl_meta"
                        . " ON hl_meta.post_id = {$wpdb->posts}.ID"
                        . " AND hl_meta.meta_key = '_produkt_highlight'";
    $clauses['orderby'] = "CAST(COALESCE(hl_meta.meta_value,'0') AS UNSIGNED) DESC,"
                        . " {$wpdb->posts}.post_date DESC";
    return $clauses;
}

/* -------------------------------------------------------
   Admin-Listenansicht: Spalten für den Bewertungs-CPT
------------------------------------------------------- */

add_filter('manage_bewertung_posts_columns', function (array $cols): array {
    return [
        'cb'                  => $cols['cb'],
        'bewertung_highlight' => '⭐',
        'title'               => $cols['title'],
        'bewertung_sterne'    => __('Sterne', 'annyhase'),
        'bewertung_vorschau'  => __('Text-Vorschau', 'annyhase'),
        'date'                => $cols['date'] ?? __('Datum', 'annyhase'),
    ];
});

add_action('manage_bewertung_posts_custom_column', function (string $col, int $post_id): void {
    switch ($col) {

        case 'bewertung_highlight':
            $hl  = get_post_meta($post_id, '_bewertung_highlight', true) === '1';
            $url = wp_nonce_url(add_query_arg([
                'bewertung_action' => 'toggle_highlight',
                'bewertung_id'     => $post_id,
            ], admin_url('edit.php?post_type=bewertung')), 'toggle_bhl_' . $post_id);
            echo '<a href="' . esc_url($url) . '" title="' . ($hl ? 'Highlight entfernen' : 'Als Highlight markieren') . '" style="font-size:22px;text-decoration:none;line-height:1">'
                 . ($hl ? '⭐' : '<span style="opacity:.25;filter:grayscale(1)">⭐</span>')
                 . '</a>';
            break;

        case 'bewertung_sterne':
            $stars = (int) (get_post_meta($post_id, '_bewertung_sterne', true) ?: 5);
            echo '<span style="color:#f0c040;font-size:14px;letter-spacing:1px">'
                 . str_repeat('★', $stars) . str_repeat('☆', 5 - $stars)
                 . '</span>';
            break;

        case 'bewertung_vorschau':
            $post    = get_post($post_id);
            $content = wp_strip_all_tags($post->post_content ?: $post->post_excerpt);
            echo '<span style="color:#555;font-size:12px">'
                 . esc_html(wp_trim_words($content, 12, '…'))
                 . '</span>';
            break;
    }
}, 10, 2);

add_action('admin_head', function (): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'edit-bewertung') return;
    echo '<style>
        .column-bewertung_highlight { width:44px; text-align:center; }
        .column-bewertung_sterne    { width:100px; }
        .column-bewertung_vorschau  { color:#555; }
    </style>';
});

/* Bewertungs-Highlight-Toggle */
add_action('admin_init', function (): void {
    $action = sanitize_key($_GET['bewertung_action'] ?? '');
    if ($action !== 'toggle_highlight' || empty($_GET['bewertung_id'])) return;

    $post_id = (int) $_GET['bewertung_id'];
    if (
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'toggle_bhl_' . $post_id) ||
        !current_user_can('edit_post', $post_id)
    ) wp_die('Ungültige Anfrage.');

    $current = get_post_meta($post_id, '_bewertung_highlight', true);
    update_post_meta($post_id, '_bewertung_highlight', $current === '1' ? '0' : '1');

    wp_safe_redirect(admin_url('edit.php?post_type=bewertung'));
    exit;
});

/* Sortierbare Spalten: Produkte */
add_filter('manage_edit-produkt_sortable_columns', function (array $cols): array {
    $cols['produkt_preis']     = 'produkt_preis';
    $cols['produkt_highlight'] = 'produkt_highlight';
    return $cols;
});

/* Sortierbare Spalten: Bewertungen */
add_filter('manage_edit-bewertung_sortable_columns', function (array $cols): array {
    $cols['bewertung_highlight'] = 'bewertung_highlight';
    $cols['bewertung_sterne']    = 'bewertung_sterne';
    return $cols;
});

/* Sortierung: Preis + Sterne via meta_key (INNER JOIN ok – Wert immer vorhanden) */
add_action('pre_get_posts', function (WP_Query $q): void {
    if (!is_admin() || !$q->is_main_query()) return;
    global $pagenow;
    if ($pagenow !== 'edit.php') return;

    $post_type = sanitize_text_field($_GET['post_type'] ?? '');
    $orderby   = $q->get('orderby');

    if ($post_type === 'produkt' && $orderby === 'produkt_preis') {
        $q->set('meta_key', '_produkt_preis');
        $q->set('orderby',  'meta_value_num');
    }
    if ($post_type === 'bewertung' && $orderby === 'bewertung_sterne') {
        $q->set('meta_key', '_bewertung_sterne');
        $q->set('orderby',  'meta_value_num');
    }
});

/*
 * Highlight-Sortierung via LEFT JOIN + COALESCE:
 * Einträge ohne das Meta-Feld werden als '0' behandelt und erscheinen hinten.
 * Ein normaler meta_key-Ansatz würde diese Einträge per INNER JOIN ausblenden.
 */
add_filter('posts_clauses', function (array $clauses, WP_Query $q): array {
    if (!is_admin() || !$q->is_main_query()) return $clauses;
    global $wpdb, $pagenow;
    if ($pagenow !== 'edit.php') return $clauses;

    $orderby = $q->get('orderby');
    $map     = [
        'produkt_highlight'   => '_produkt_highlight',
        'bewertung_highlight' => '_bewertung_highlight',
    ];
    if (!isset($map[$orderby])) return $clauses;

    $meta_key = $map[$orderby];
    $order    = strtoupper($q->get('order')) === 'ASC' ? 'ASC' : 'DESC';

    $clauses['join']   .= $wpdb->prepare(
        " LEFT JOIN {$wpdb->postmeta} AS hl_sort ON hl_sort.post_id = {$wpdb->posts}.ID AND hl_sort.meta_key = %s",
        $meta_key
    );
    $clauses['orderby'] = "CAST(COALESCE(hl_sort.meta_value, '0') AS UNSIGNED) {$order},"
                        . " {$wpdb->posts}.post_date DESC";

    return $clauses;
}, 10, 2);

/* Produkte pro Seite im Archiv und in Taxonomie-Seiten */
add_action('pre_get_posts', function (WP_Query $q): void {
    if (is_admin() || !$q->is_main_query()) return;
    if (!$q->is_post_type_archive('produkt') && !$q->is_tax('produktkategorie')) return;
    $per = absint(get_theme_mod('annyhase_archive_per_page', 12));
    if ($per > 0) $q->set('posts_per_page', $per);
});

/* Product archive full-text search via ?ps= (searches across all pages) */
add_action('pre_get_posts', function (WP_Query $q): void {
    if (is_admin() || !$q->is_main_query()) return;
    if (!$q->is_post_type_archive('produkt') && !$q->is_tax('produktkategorie')) return;
    $ps = sanitize_text_field(wp_unslash($_GET['ps'] ?? ''));
    if ($ps !== '') {
        $q->set('s', $ps);
        $q->set('post_type', 'produkt');
    }
}, 5);

/* -------------------------------------------------------
   Meta Boxes: Produkt Details
------------------------------------------------------- */
function annyhase_meta_boxes(): void {
    add_meta_box('produkt_details',  __('Produkt Details', 'annyhase'),  'annyhase_produkt_details_cb',  'produkt',  'normal', 'high');
    add_meta_box('produkt_galerie',  __('📸 Produkt-Galerie', 'annyhase'), 'annyhase_galerie_cb',         'produkt',  'normal', 'default');
    add_meta_box('bewertung_details',__('Bewertungs-Details', 'annyhase'),'annyhase_bewertung_details_cb','bewertung','normal', 'high');
}
add_action('add_meta_boxes', 'annyhase_meta_boxes');

/* -------------------------------------------------------
   Galerie Meta Box
------------------------------------------------------- */
function annyhase_galerie_cb(WP_Post $post): void {
    wp_nonce_field('annyhase_galerie_meta', 'annyhase_galerie_nonce');
    $ids = get_post_meta($post->ID, '_produkt_galerie', true); // comma-separated attachment IDs
    $id_arr = array_filter(array_map('intval', explode(',', $ids ?: '')));
    ?>
    <style>
    #galerie-preview{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px;min-height:60px;padding:8px;background:#f9f9f9;border:1px dashed #ddd;border-radius:4px}
    #galerie-preview .gal-thumb{position:relative;width:80px;height:80px;cursor:grab}
    #galerie-preview .gal-thumb img{width:80px;height:80px;object-fit:cover;border-radius:3px;border:2px solid #ddd}
    #galerie-preview .gal-thumb .remove-img{position:absolute;top:-6px;right:-6px;background:#c4704a;color:#fff;border:none;border-radius:50%;width:20px;height:20px;font-size:13px;line-height:20px;text-align:center;cursor:pointer;padding:0}
    #galerie-preview .gal-thumb .order-badge{position:absolute;bottom:2px;left:2px;background:rgba(0,0,0,.55);color:#fff;font-size:10px;padding:1px 4px;border-radius:2px}
    #galerie-add-btn{margin-right:8px}
    .galerie-hint{color:#666;font-size:12px;margin-top:8px}
    </style>

    <p class="galerie-hint">
        <strong>Tipp:</strong> Das <em>Vorschaubild</em> (rechts oben → „Beitragsbild festlegen") wird als <strong>erstes Bild</strong> der Galerie angezeigt.<br>
        Hier fügst du weitere Galerie-Bilder hinzu. Die Reihenfolge kannst du per Drag &amp; Drop ändern.
    </p>

    <div id="galerie-preview">
        <?php foreach ($id_arr as $i => $img_id): ?>
            <?php $thumb = wp_get_attachment_image_url($img_id, 'thumbnail'); if (!$thumb) continue; ?>
            <div class="gal-thumb" data-id="<?php echo $img_id; ?>">
                <img src="<?php echo esc_url($thumb); ?>" alt="">
                <button type="button" class="remove-img" title="Entfernen">&times;</button>
                <span class="order-badge"><?php echo $i + 1; ?></span>
            </div>
        <?php endforeach; ?>
        <?php if (empty($id_arr)): ?>
            <span style="color:#999;font-size:13px;align-self:center;padding:4px">Noch keine Galerie-Bilder – klicke „Bilder hinzufügen"</span>
        <?php endif; ?>
    </div>

    <input type="hidden" id="produkt_galerie_ids" name="produkt_galerie_ids" value="<?php echo esc_attr($ids ?: ''); ?>">

    <button type="button" id="galerie-add-btn" class="button button-primary">+ Bilder hinzufügen</button>
    <button type="button" id="galerie-clear-btn" class="button">Alle entfernen</button>

    <?php
    $vid_url   = get_post_meta($post->ID, '_etsy_video_url',           true);
    $vid_thumb = get_post_meta($post->ID, '_etsy_video_thumbnail_url', true);
    if ($vid_url):
    ?>
    <div style="margin-top:14px;padding:12px;background:#f0f6fc;border:1px solid #b8d4ea;border-radius:6px">
        <strong style="font-size:13px;display:block;margin-bottom:8px">🎬 Etsy-Video</strong>
        <div style="display:flex;align-items:center;gap:12px">
            <?php if ($vid_thumb): ?>
            <div style="position:relative;width:100px;height:72px;border-radius:4px;overflow:hidden;flex-shrink:0;background:#000">
                <img src="<?php echo esc_url($vid_thumb); ?>" style="width:100%;height:100%;object-fit:cover" alt="">
                <span style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.35);color:#fff;font-size:1.5rem;pointer-events:none">&#9654;</span>
            </div>
            <?php endif; ?>
            <div>
                <a href="<?php echo esc_url($vid_url); ?>" target="_blank" rel="noopener noreferrer" class="button button-small">Video öffnen ↗</a>
                <p style="font-size:12px;color:#666;margin:.4rem 0 0">Automatisch von Etsy importiert. Wird auf der Produktseite in der Galerie-Reihe angezeigt.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    jQuery(function($){
        var frame;
        var $preview = $('#galerie-preview');
        var $input   = $('#produkt_galerie_ids');

        function syncIds(){
            var ids = [];
            $preview.find('.gal-thumb').each(function(){ids.push($(this).data('id'));});
            $input.val(ids.join(','));
            // update order badges
            $preview.find('.gal-thumb').each(function(i){ $(this).find('.order-badge').text(i+1); });
        }

        function addThumb(id, url){
            if ($preview.find('.gal-thumb[data-id="'+id+'"]').length) return; // no dupe
            var n = $preview.find('.gal-thumb').length + 1;
            $preview.find('span[style*="color"]').remove();
            $preview.append(
                '<div class="gal-thumb" data-id="'+id+'">'
                + '<img src="'+url+'" alt="">'
                + '<button type="button" class="remove-img" title="Entfernen">&times;</button>'
                + '<span class="order-badge">'+n+'</span>'
                + '</div>'
            );
            syncIds();
        }

        // Open media frame
        $('#galerie-add-btn').on('click', function(){
            if(frame){frame.open();return;}
            frame = wp.media({
                title: 'Galerie-Bilder auswählen',
                button: {text: 'Bilder hinzufügen'},
                multiple: true
            });
            frame.on('select', function(){
                frame.state().get('selection').each(function(att){
                    var sizes = att.get('sizes');
                    var url = (sizes && sizes.thumbnail) ? sizes.thumbnail.url : att.get('url');
                    addThumb(att.get('id'), url);
                });
            });
            frame.open();
        });

        // Remove single image
        $preview.on('click', '.remove-img', function(){
            $(this).closest('.gal-thumb').remove();
            syncIds();
            if(!$preview.find('.gal-thumb').length){
                $preview.append('<span style="color:#999;font-size:13px;align-self:center;padding:4px">Noch keine Galerie-Bilder – klicke „Bilder hinzufügen"</span>');
            }
        });

        // Clear all
        $('#galerie-clear-btn').on('click', function(){
            $preview.empty().append('<span style="color:#999;font-size:13px;align-self:center;padding:4px">Noch keine Galerie-Bilder – klicke „Bilder hinzufügen"</span>');
            $input.val('');
        });

        // Drag & Drop reorder (native HTML5)
        var dragged = null;
        $preview.on('dragstart','.gal-thumb',function(e){dragged=this;$(this).css('opacity',.4);});
        $preview.on('dragend',  '.gal-thumb',function(){$(this).css('opacity',1);dragged=null;syncIds();});
        $preview.on('dragover', '.gal-thumb',function(e){e.preventDefault();});
        $preview.on('drop',     '.gal-thumb',function(e){
            e.preventDefault();
            if(dragged && dragged!==this){$(this).before($(dragged));}
        });
        $preview.find('.gal-thumb').attr('draggable','true');
        $preview.on('DOMNodeInserted','.gal-thumb',function(){$(this).attr('draggable','true');});
    });
    </script>
    <?php
}

function annyhase_save_galerie_meta(int $post_id): void {
    if (
        !isset($_POST['annyhase_galerie_nonce']) ||
        !wp_verify_nonce($_POST['annyhase_galerie_nonce'], 'annyhase_galerie_meta') ||
        (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
        !current_user_can('edit_post', $post_id)
    ) return;

    $raw = $_POST['produkt_galerie_ids'] ?? '';
    // Sanitize: only keep comma-separated integers
    $ids = implode(',', array_filter(array_map('intval', explode(',', $raw))));
    update_post_meta($post_id, '_produkt_galerie', $ids);
}
add_action('save_post_produkt', 'annyhase_save_galerie_meta');

/* -------------------------------------------------------
   Galerie-Helper: alle Bild-IDs für ein Produkt
------------------------------------------------------- */
/**
 * Returns an ordered array of attachment IDs for a product's image gallery.
 *
 * Resolution order:
 *  1. Featured image (always first).
 *  2. IDs from the `_produkt_galerie` meta field (comma-separated).
 *  3. Fallback: images attached to the post (used when only 0–1 IDs are found).
 *
 * @param  int $post_id Post ID; defaults to the current post in the Loop.
 * @return int[]        Ordered, deduplicated list of attachment IDs.
 */
function annyhase_get_gallery_ids(int $post_id = 0): array {
    $id  = $post_id ?: get_the_ID();
    $ids = [];

    // 1. Featured Image immer zuerst
    $thumb = get_post_thumbnail_id($id);
    if ($thumb) $ids[] = (int)$thumb;

    // 2. Explizit gespeicherte Galerie-IDs
    $meta = get_post_meta($id, '_produkt_galerie', true);
    foreach (array_filter(array_map('intval', explode(',', $meta ?: ''))) as $gid) {
        if (!in_array($gid, $ids)) $ids[] = $gid;
    }

    // 3. Fallback: automatisch angehängte Bilder (vom Import) – nur wenn gar kein Bild vorhanden
    if (count($ids) === 0) {
        $attached = get_children([
            'post_parent'    => $id,
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => 10,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ]);
        foreach ($attached as $att) {
            if (!in_array($att->ID, $ids)) $ids[] = $att->ID;
        }
    }

    return $ids;
}

function annyhase_produkt_details_cb(WP_Post $post): void {
    wp_nonce_field('annyhase_produkt_meta', 'annyhase_meta_nonce');
    $price     = get_post_meta($post->ID, '_produkt_preis',      true);
    $etsy_url  = get_post_meta($post->ID, '_etsy_url',           true);
    $is_etsy   = get_post_meta($post->ID, '_is_etsy_produkt',    true);
    $badge     = get_post_meta($post->ID, '_produkt_badge',      true);
    $highlight = get_post_meta($post->ID, '_produkt_highlight',  true);
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:8px 0">
        <div>
            <label for="produkt_preis"><strong><?php esc_html_e('Preis (nur Zahl, z.B. 28,00 – € wird automatisch ergänzt)', 'annyhase'); ?></strong></label><br>
            <input type="text" id="produkt_preis" name="produkt_preis" value="<?php echo esc_attr($price); ?>" style="width:100%;margin-top:4px" placeholder="z.B. 28,00">
        </div>
        <div>
            <label for="produkt_badge"><strong><?php esc_html_e('Badge (z.B. Neu, Bestseller)', 'annyhase'); ?></strong></label><br>
            <input type="text" id="produkt_badge" name="produkt_badge" value="<?php echo esc_attr($badge); ?>" style="width:100%;margin-top:4px">
        </div>
        <div style="grid-column:span 2">
            <label for="etsy_url"><strong><?php esc_html_e('Etsy-Link zu diesem Produkt', 'annyhase'); ?></strong></label><br>
            <input type="url" id="etsy_url" name="etsy_url" value="<?php echo esc_url($etsy_url); ?>" style="width:100%;margin-top:4px" placeholder="https://www.etsy.com/listing/...">
        </div>
        <div>
            <label style="display:flex;align-items:center;gap:8px;margin-top:4px">
                <input type="checkbox" name="is_etsy_produkt" value="1" <?php checked($is_etsy, '1'); ?>>
                <?php esc_html_e('Produkt auf Etsy kaufbar', 'annyhase'); ?>
            </label>
        </div>
        <div style="grid-column:span 2;padding:10px 12px;background:#fffbea;border:1px solid #f0c040;border-radius:6px">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin:0">
                <input type="checkbox" name="produkt_highlight" value="1" <?php checked($highlight, '1'); ?> style="width:16px;height:16px">
                <span>
                    <strong style="font-size:13px">⭐ <?php esc_html_e('Als Highlight markieren', 'annyhase'); ?></strong><br>
                    <span style="font-size:12px;color:#666"><?php esc_html_e('Highlights erscheinen auf der Startseite immer ganz vorne.', 'annyhase'); ?></span>
                </span>
            </label>
        </div>
    </div>
    <?php
}

function annyhase_bewertung_details_cb(WP_Post $post): void {
    wp_nonce_field('annyhase_bewertung_meta', 'annyhase_bewertung_nonce');
    $stars      = get_post_meta($post->ID, '_bewertung_sterne',    true) ?: '5';
    $highlight  = get_post_meta($post->ID, '_bewertung_highlight', true);
    $review_url = get_post_meta($post->ID, '_etsy_review_url',     true);
    ?>
    <p style="color:#666;font-size:13px;margin-bottom:12px">
        <?php esc_html_e('Der Titel wird als Kurzüberschrift angezeigt. Den eigentlichen Bewertungstext schreibe ins Textfeld oben.', 'annyhase'); ?>
    </p>
    <div style="padding:8px 0">
        <label for="bewertung_sterne"><strong><?php esc_html_e('Sterne (1–5)', 'annyhase'); ?></strong></label><br>
        <select id="bewertung_sterne" name="bewertung_sterne" style="width:120px;margin-top:4px">
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <option value="<?php echo $i; ?>" <?php selected($stars, (string)$i); ?>>
                    <?php echo str_repeat('★', $i) . ' (' . $i . ')'; ?>
                </option>
            <?php endfor; ?>
        </select>
    </div>
    <div style="margin-top:12px;padding:10px 12px;background:#fffbea;border:1px solid #f0c040;border-radius:6px">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin:0">
            <input type="checkbox" name="bewertung_highlight" value="1" <?php checked($highlight, '1'); ?> style="width:16px;height:16px">
            <span>
                <strong style="font-size:13px">⭐ <?php esc_html_e('Als Highlight markieren', 'annyhase'); ?></strong><br>
                <span style="font-size:12px;color:#666"><?php esc_html_e('Highlights erscheinen auf der Startseite immer ganz vorne.', 'annyhase'); ?></span>
            </span>
        </label>
    </div>
    <?php if ($review_url): ?>
    <div style="margin-top:10px;padding:8px 12px;background:#f0f6fc;border:1px solid #b8d4ea;border-radius:6px;font-size:12px">
        <strong>Etsy-Link:</strong>
        <a href="<?php echo esc_url($review_url); ?>" target="_blank" rel="noopener noreferrer" style="word-break:break-all"><?php echo esc_url($review_url); ?></a>
        <span style="color:#666"> (automatisch importiert)</span>
    </div>
    <?php endif; ?>
    <?php
}

/* -------------------------------------------------------
   Save Meta
------------------------------------------------------- */
function annyhase_save_produkt_meta(int $post_id): void {
    if (
        !isset($_POST['annyhase_meta_nonce']) ||
        !wp_verify_nonce($_POST['annyhase_meta_nonce'], 'annyhase_produkt_meta') ||
        (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
        !current_user_can('edit_post', $post_id)
    ) return;

    update_post_meta($post_id, '_produkt_preis',     sanitize_text_field($_POST['produkt_preis'] ?? ''));
    update_post_meta($post_id, '_etsy_url',          esc_url_raw($_POST['etsy_url'] ?? ''));
    update_post_meta($post_id, '_is_etsy_produkt',   isset($_POST['is_etsy_produkt'])  ? '1' : '0');
    update_post_meta($post_id, '_produkt_badge',     sanitize_text_field($_POST['produkt_badge'] ?? ''));
    update_post_meta($post_id, '_produkt_highlight', isset($_POST['produkt_highlight']) ? '1' : '0');
}
add_action('save_post_produkt', 'annyhase_save_produkt_meta');

function annyhase_save_bewertung_meta(int $post_id): void {
    if (
        !isset($_POST['annyhase_bewertung_nonce']) ||
        !wp_verify_nonce($_POST['annyhase_bewertung_nonce'], 'annyhase_bewertung_meta') ||
        (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
        !current_user_can('edit_post', $post_id)
    ) return;

    $stars = intval($_POST['bewertung_sterne'] ?? 5);
    update_post_meta($post_id, '_bewertung_sterne',    max(1, min(5, $stars)));
    update_post_meta($post_id, '_bewertung_highlight', isset($_POST['bewertung_highlight']) ? '1' : '0');
}
add_action('save_post_bewertung', 'annyhase_save_bewertung_meta');

/* -------------------------------------------------------
   E-Mail HTML-Template Helpers
------------------------------------------------------- */
/**
 * Wraps an HTML email body in the branded Annyhase email shell.
 *
 * Generates a table-based, inline-styled HTML email template suitable for all
 * major email clients. Includes a terracotta header, white content area, and
 * dark footer with a website link.
 *
 * @param  string $body_html  Inner HTML to place in the white content cell.
 * @param  string $preheader  Optional preview text hidden from the visible body.
 * @return string             Full HTML email document as a string.
 */
function annyhase_email_wrap(string $body_html, string $preheader = ''): string {
    $site_name = get_bloginfo('name') ?: 'Annyhase';
    $name_esc  = esc_html($site_name);
    $site_url  = esc_url(home_url('/'));
    $pre       = $preheader
        ? '<div style="display:none;font-size:1px;max-height:0;overflow:hidden;mso-hide:all;">' . esc_html($preheader) . '</div>'
        : '';

    return '<!DOCTYPE html>
<html lang="de" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . $name_esc . '</title>
</head>
<body style="margin:0;padding:0;background:#f5f0e8;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;">
' . $pre . '
<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="background:#f5f0e8;">
  <tr>
    <td align="center" style="padding:40px 16px;">
      <table width="600" cellpadding="0" cellspacing="0" border="0" role="presentation" style="max-width:600px;width:100%;">
        <tr>
          <td align="center" style="background:#c4704a;border-radius:12px 12px 0 0;padding:32px 40px;">
            <a href="' . $site_url . '" style="text-decoration:none;">
              <div style="font-family:Georgia,\'Times New Roman\',serif;font-size:28px;font-weight:400;color:#ffffff;letter-spacing:0.04em;line-height:1;">' . $name_esc . '</div>
              <div style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:11px;color:rgba(255,255,255,0.70);letter-spacing:0.15em;text-transform:uppercase;margin-top:7px;">Handgemachte Keramik &amp; Unikate</div>
            </a>
          </td>
        </tr>
        <tr>
          <td style="background:#ffffff;padding:40px 40px 36px;">' . $body_html . '</td>
        </tr>
        <tr>
          <td align="center" style="background:#3d2c1e;border-radius:0 0 12px 12px;padding:22px 40px;">
            <p style="font-size:12px;color:rgba(255,255,255,0.45);margin:0 0 6px;line-height:1.5;">' . $name_esc . '</p>
            <a href="' . $site_url . '" style="font-size:11px;color:rgba(255,255,255,0.35);text-decoration:none;">Website besuchen &rarr;</a>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>';
}

/**
 * Builds the auto-reply HTML email sent to the contact form submitter.
 *
 * The email body text is configurable via the Customizer
 * (setting: annyhase_contact_autoreply_body). Placeholders {name}, {email},
 * {subject}, and {message} are replaced with sanitized user values.
 *
 * @param  string $name    Sender's display name.
 * @param  string $email   Sender's email address.
 * @param  string $subject Selected subject chip value.
 * @param  string $message Message body.
 * @return string          Full HTML email document.
 */
function annyhase_email_autoreply(string $name, string $email, string $subject, string $message): string {
    $fill = fn(string $tpl): string => str_replace(
        ['{name}', '{email}', '{subject}', '{message}'],
        [esc_html($name), esc_html($email), esc_html($subject), nl2br(esc_html($message))],
        $tpl
    );

    $raw   = get_theme_mod(
        'annyhase_contact_autoreply_body',
        "Hallo {name},\n\nvielen Dank für deine Nachricht! Ich habe sie erhalten und melde mich so schnell wie möglich bei dir.\n\nViele Grüße,\nAnny"
    );
    $paras = array_filter(array_map('trim', explode("\n\n", $fill($raw))));
    $body  = implode('', array_map(
        fn($p) => '<p style="font-size:15px;line-height:1.75;color:#5a4a3a;margin:0 0 20px;">' . nl2br($p) . '</p>',
        $paras
    ));

    $recap = '';
    if (get_theme_mod('annyhase_contact_autoreply_show_message', 1) && $message) {
        $s_row = $subject
            ? '<p style="font-size:12px;color:#7a8c6e;margin:0 0 8px;"><strong>Thema:</strong> ' . esc_html($subject) . '</p>'
            : '';
        $recap = '
        <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:4px 0 24px;">
          <tr>
            <td style="background:#f5f0e8;border-left:3px solid #c4704a;padding:16px 20px;border-radius:0 8px 8px 0;">
              <p style="font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:#7a8c6e;font-weight:600;margin:0 0 10px;">Deine Nachricht</p>
              ' . $s_row . '
              <p style="font-size:14px;line-height:1.65;color:#3d2c1e;margin:0;">' . nl2br(esc_html($message)) . '</p>
            </td>
          </tr>
        </table>';
    }

    return annyhase_email_wrap(
        $body . $recap,
        'Hallo ' . $name . ' – deine Nachricht ist angekommen!'
    );
}

/* Benachrichtigungsmail an die Shopbetreiberin */
function annyhase_email_notification(string $name, string $email, string $subject, string $message): string {
    $s_row = $subject ? '
      <tr>
        <td style="padding:14px 0;border-bottom:1px solid #f0ebe3;">
          <p style="font-size:11px;text-transform:uppercase;letter-spacing:0.1em;color:#7a8c6e;font-weight:600;margin:0 0 4px;">Thema</p>
          <p style="font-size:15px;color:#3d2c1e;margin:0;">' . esc_html($subject) . '</p>
        </td>
      </tr>' : '';

    $body = '
    <p style="font-family:Georgia,\'Times New Roman\',serif;font-size:20px;font-weight:400;color:#3d2c1e;margin:0 0 28px;">Neue Kontaktanfrage &#128236;</p>
    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
      <tr>
        <td style="padding:14px 0;border-bottom:1px solid #f0ebe3;">
          <p style="font-size:11px;text-transform:uppercase;letter-spacing:0.1em;color:#7a8c6e;font-weight:600;margin:0 0 4px;">Name</p>
          <p style="font-size:15px;color:#3d2c1e;margin:0;">' . esc_html($name) . '</p>
        </td>
      </tr>
      <tr>
        <td style="padding:14px 0;border-bottom:1px solid #f0ebe3;">
          <p style="font-size:11px;text-transform:uppercase;letter-spacing:0.1em;color:#7a8c6e;font-weight:600;margin:0 0 4px;">E-Mail</p>
          <p style="font-size:15px;color:#3d2c1e;margin:0;"><a href="mailto:' . esc_attr($email) . '" style="color:#c4704a;text-decoration:none;">' . esc_html($email) . '</a></p>
        </td>
      </tr>
      ' . $s_row . '
      <tr>
        <td style="padding:14px 0;">
          <p style="font-size:11px;text-transform:uppercase;letter-spacing:0.1em;color:#7a8c6e;font-weight:600;margin:0 0 12px;">Nachricht</p>
          <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
            <tr>
              <td style="background:#f5f0e8;border-radius:8px;padding:18px 20px;">
                <p style="font-size:14px;line-height:1.7;color:#3d2c1e;margin:0;">' . nl2br(esc_html($message)) . '</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin-top:28px;">
      <tr>
        <td>
          <a href="mailto:' . esc_attr($email) . '" style="display:inline-block;background:#c4704a;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;letter-spacing:0.03em;">Direkt antworten &rarr;</a>
        </td>
      </tr>
    </table>';

    return annyhase_email_wrap(
        $body,
        'Neue Nachricht von ' . $name . ($subject ? ' – ' . $subject : '')
    );
}

/* -------------------------------------------------------
   Contact Form AJAX Handler
------------------------------------------------------- */
/**
 * Handles the front-end contact form submission via AJAX.
 *
 * Validates the nonce (`annyhase_nonce`), runs honeypot, field, email, and
 * privacy checks, then dispatches a notification email to the shop owner and
 * an optional auto-reply to the submitter. Responds with wp_send_json_success /
 * wp_send_json_error.
 *
 * Registered for both `wp_ajax_annyhase_contact` and `wp_ajax_nopriv_annyhase_contact`.
 */
function annyhase_contact_submit(): void {
    check_ajax_referer('annyhase_nonce', 'nonce');

    // Rate limiting: max 5 submissions per IP per hour
    $ip_key = 'annyhase_cf_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $count  = (int) get_transient($ip_key);
    if ($count >= 5) {
        wp_send_json_error(['message' => __('Zu viele Anfragen. Bitte warte eine Stunde und versuche es erneut.', 'annyhase')]);
        return;
    }
    set_transient($ip_key, $count + 1, HOUR_IN_SECONDS);

    // reCAPTCHA v3 – nur prüfen wenn Secret Key konfiguriert
    $recaptcha_secret = get_theme_mod('annyhase_recaptcha_secret_key', '');
    if ($recaptcha_secret) {
        $recaptcha_token = sanitize_text_field(wp_unslash($_POST['recaptcha_token'] ?? ''));
        if (!$recaptcha_token) {
            wp_send_json_error(['message' => __('reCAPTCHA-Fehler. Bitte versuche es erneut.', 'annyhase')]);
            return;
        }
        $verify = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret'   => $recaptcha_secret,
                'response' => $recaptcha_token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ],
        ]);
        if (!is_wp_error($verify)) {
            $verify_body = json_decode(wp_remote_retrieve_body($verify), true);
            if (empty($verify_body['success']) || ($verify_body['score'] ?? 0) < 0.5) {
                wp_send_json_error(['message' => __('reCAPTCHA-Prüfung fehlgeschlagen. Bitte versuche es erneut.', 'annyhase')]);
                return;
            }
        }
    }

    $name    = sanitize_text_field($_POST['name']    ?? '');
    $email   = sanitize_email($_POST['email']        ?? '');
    $subject = sanitize_text_field($_POST['subject'] ?? '');
    $message = sanitize_textarea_field($_POST['message'] ?? '');
    $privacy = !empty($_POST['privacy']);

    if (!empty($_POST['website'])) {
        wp_send_json_error(['message' => __('Bitte fülle alle Pflichtfelder aus.', 'annyhase')]);
        return;
    }
    if (!$name || !is_email($email) || !$message) {
        wp_send_json_error(['message' => __('Bitte fülle alle Pflichtfelder aus.', 'annyhase')]);
        return;
    }
    if (!$privacy) {
        wp_send_json_error(['message' => __('Bitte stimme der Datenschutzerklärung zu.', 'annyhase')]);
        return;
    }

    $fill = fn(string $tpl): string => str_replace(
        ['{name}', '{email}', '{subject}', '{message}'],
        [$name, $email, $subject, $message],
        $tpl
    );

    // ── Benachrichtigung an Shopbetreiberin ──
    $to             = get_theme_mod('annyhase_contact_email', get_option('admin_email'));
    $notify_subject = $fill(get_theme_mod('annyhase_contact_notify_subject', 'Neue Kontaktanfrage: {subject}'));
    // Strip any newlines from name to prevent email header injection.
    $safe_name      = str_replace(["\r", "\n"], '', $name);
    $notify_headers = [
        'Content-Type: text/html; charset=UTF-8',
        'Reply-To: =?UTF-8?B?' . base64_encode($safe_name) . '?= <' . $email . '>',
    ];

    $sent = wp_mail($to, $notify_subject, annyhase_email_notification($name, $email, $subject, $message), $notify_headers);

    if (!$sent) {
        wp_send_json_error(['message' => __('E-Mail konnte nicht gesendet werden. Bitte versuche es erneut.', 'annyhase')]);
        return;
    }

    // ── Automatische Bestätigungsmail an Absender ──
    $autoreply_on = (bool) get_theme_mod('annyhase_contact_autoreply', true);
    if ($autoreply_on) {
        $from_name     = get_theme_mod('annyhase_contact_autoreply_from', get_bloginfo('name'));
        $reply_subject = $fill(get_theme_mod('annyhase_contact_autoreply_subject', 'Deine Nachricht ist angekommen ✨'));
        $reply_headers = ['Content-Type: text/html; charset=UTF-8'];
        if ($from_name) {
            $reply_headers[] = 'From: =?UTF-8?B?' . base64_encode($from_name) . '?= <' . $to . '>';
        }
        $reply_sent = wp_mail($email, $reply_subject, annyhase_email_autoreply($name, $email, $subject, $message), $reply_headers);
        if (!$reply_sent && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Annyhase] Auto-reply failed for: ' . $email);
        }
    }

    // ── Erfolgsmeldung ──
    $success = get_theme_mod(
        'annyhase_contact_success_msg',
        'Vielen Dank für deine Nachricht! Ich melde mich so schnell wie möglich bei dir.'
    );
    wp_send_json_success(['message' => $success]);
}
add_action('wp_ajax_annyhase_contact',        'annyhase_contact_submit');
add_action('wp_ajax_nopriv_annyhase_contact', 'annyhase_contact_submit');

/* -------------------------------------------------------
   Template Tag Helpers
------------------------------------------------------- */

/**
 * Returns the Etsy logo as an inline SVG string (SimpleIcons design).
 *
 * All attributes are attribute-escaped internally, so the return value is
 * safe to echo directly in a template.
 *
 * @param  int    $size  Width and height in pixels. Default 18.
 * @param  string $color CSS color value for the fill. Default 'currentColor'.
 * @return string        Inline `<svg>` element.
 */
/**
 * Encodes an email address as HTML character entities to deter naive scrapers.
 * The mailto: href should still use the plain address; use this only for display.
 *
 * @param  string $email Plain e-mail address.
 * @return string        HTML-entity-encoded string (safe to echo without escaping).
 */
function annyhase_obfuscate_email(string $email): string {
    $out = '';
    foreach (str_split($email) as $char) {
        $out .= '&#' . ord($char) . ';';
    }
    return $out;
}

function annyhase_etsy_svg(int $size = 18, string $color = 'currentColor'): string {
    $s = esc_attr($size);
    $c = esc_attr($color);
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="' . $c . '" aria-hidden="true"><path d="M11.562 2.197C6.328 2.197 2 6.524 2 11.759c0 5.234 4.328 9.562 9.562 9.562 5.235 0 9.562-4.328 9.562-9.562 0-5.235-4.327-9.562-9.562-9.562zm4.146 13.209H9.141v-.641l.749-.116V8.573l-.749-.116v-.64h6.567v2.077l-.656.016-.358-1.078H13.12v2.436h1.601l.219-.656h.593v1.912h-.593l-.219-.671H13.12v2.624h1.601l.358-1.093.609.031-.18 2.108z"/></svg>';
}

/**
 * Renders the Etsy banner logo: a customisable icon image (or SVG fallback)
 * plus the "| Etsy" wordmark as a decorative text element.
 *
 * Uses the `annyhase_etsy_banner_logo` Customizer image setting when set;
 * falls back to `assets/etsy-logo.png` bundled with the theme.
 * Output is echoed directly.
 */
function annyhase_etsy_banner_logo(): void {
    $custom_id = absint(get_theme_mod('annyhase_etsy_banner_logo', 0));

    echo '<div style="display:flex;align-items:center;gap:1rem">';

    if ($custom_id) {
        // Hochgeladenes Bild als Icon-Teil
        echo wp_get_attachment_image($custom_id, [200, 64], false, [
            'style' => 'height:52px;width:auto;object-fit:contain',
            'alt'   => '',
        ]);
    } else {
        // Standard: mitgeliefertes Etsy-Logo aus dem Theme
        $default_logo = get_template_directory_uri() . '/assets/img/etsy-logo.png';
        echo '<img src="' . esc_url($default_logo) . '" alt="" height="52" style="height:52px;width:auto;object-fit:contain">';
    }

    // Trennlinie + "Etsy" Wordmark – immer sichtbar
    echo '<span style="display:inline-flex;align-items:center;gap:.85rem;color:rgba(255,255,255,0.4);font-size:1.8rem;line-height:1;user-select:none">|</span>';
    echo '<span style="font-family:Georgia,\'Times New Roman\',serif;font-size:2rem;font-weight:700;color:#fff;letter-spacing:.02em;line-height:1">Etsy</span>';

    echo '</div>';
}

/**
 * Returns the formatted, HTML-escaped price string for a product (e.g. "24,90 €").
 *
 * @param  int $post_id Post ID; defaults to the current post in the Loop.
 * @return string       Price string ready for output, or empty string if not set.
 */
function annyhase_product_price(int $post_id = 0): string {
    $id = $post_id ?: get_the_ID();
    $v  = get_post_meta($id, '_produkt_preis', true);
    if (!$v) return '';
    $v = trim(str_replace(['€', ' '], '', $v));
    return esc_html($v . ' €');
}

/**
 * Returns the esc_url-escaped Etsy listing URL for a product, or empty string.
 *
 * @param  int $post_id Post ID; defaults to the current post in the Loop.
 * @return string       Escaped URL, or empty string if not set.
 */
function annyhase_etsy_url(int $post_id = 0): string {
    $id  = $post_id ?: get_the_ID();
    $url = get_post_meta($id, '_etsy_url', true);
    return $url ? esc_url($url) : '';
}

/**
 * Returns true if the product was imported from Etsy.
 *
 * @param  int $post_id Post ID; defaults to the current post in the Loop.
 * @return bool
 */
function annyhase_is_etsy(int $post_id = 0): bool {
    return get_post_meta($post_id ?: get_the_ID(), '_is_etsy_produkt', true) === '1';
}

/**
 * Returns the HTML-escaped badge label for a product (e.g. "Neu", "Bestseller").
 *
 * @param  int $post_id Post ID; defaults to the current post in the Loop.
 * @return string       Escaped badge text, or empty string if not set.
 */
function annyhase_product_badge(int $post_id = 0): string {
    $v = get_post_meta($post_id ?: get_the_ID(), '_produkt_badge', true);
    return $v ? esc_html($v) : '';
}

/* -------------------------------------------------------
   Excerpt
------------------------------------------------------- */
add_filter('excerpt_length', fn() => 20);
add_filter('excerpt_more',   fn() => '…');

/* -------------------------------------------------------
   Theme Customizer
   Sections visible under: Design → Anpassen
------------------------------------------------------- */
/**
 * Registers all Customizer sections, settings, and controls for the theme.
 *
 * Covers hero text, product section labels, gallery, about/studio, testimonials,
 * contact form, footer branding, Etsy shop URL, and Open Graph fallback image.
 * All text settings are sanitised with `sanitize_text_field`; rich text with
 * `wp_kses_post`; image fields with `absint`.
 *
 * Hooked to `customize_register`.
 *
 * @param WP_Customize_Manager $c The Customizer manager instance.
 */
function annyhase_customizer(WP_Customize_Manager $c): void {

    // ── Helper: add a text setting+control ──────────────────────
    $text = function (string $id, string $label, string $section, string $default = '', string $type = 'text', string $description = '') use ($c): void {
        $c->add_setting('annyhase_' . $id, ['default' => $default, 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'refresh']);
        $args = ['label' => $label, 'section' => $section, 'type' => $type];
        if ($description !== '') $args['description'] = $description;
        $c->add_control('annyhase_' . $id, $args);
    };

    // ── Helper: add a textarea setting+control ───────────────────
    $textarea = function (string $id, string $label, string $section, string $default = '') use ($c): void {
        $c->add_setting('annyhase_' . $id, ['default' => $default, 'sanitize_callback' => 'wp_kses_post', 'transport' => 'refresh']);
        $c->add_control('annyhase_' . $id, ['label' => $label, 'section' => $section, 'type' => 'textarea']);
    };

    // ── Helper: add an image (media) setting+control ─────────────
    $image = function (string $id, string $label, string $section) use ($c): void {
        $c->add_setting('annyhase_' . $id, ['default' => 0, 'sanitize_callback' => 'absint', 'transport' => 'refresh']);
        $c->add_control(new WP_Customize_Media_Control($c, 'annyhase_' . $id, [
            'label'     => $label,
            'section'   => $section,
            'mime_type' => 'image',
        ]));
    };

    // ════════════════════════════════════════════
    // SEKTION 1: Startseite – Hero
    // ════════════════════════════════════════════
    $c->add_section('annyhase_hero', [
        'title'       => '🏠 Startseite – Hero',
        'description' => 'Der große Bereich ganz oben auf der Startseite.',
        'priority'    => 30,
    ]);
    $text('hero_badge_icon', 'Badge-Icon (Emoji)',                     'annyhase_hero', '🏺');
    $text('hero_badge',      'Badge-Text',                              'annyhase_hero', 'Handgemachte Keramik aus der Schwäbischen Alb');
    $text('hero_title',      'Titel – erster Teil',                    'annyhase_hero', 'Unikate mit');
    $text('hero_title_em',   'Titel – hervorgehobener Teil (kursiv)',   'annyhase_hero', 'Herz & Handwerk');
    $text('hero_tagline',    'Untertitel-Text',                         'annyhase_hero', 'Jedes Stück ein Unikat – handgefertigt am Fuße der Schwäbischen Alb.');
    $text('hero_btn_primary',   'Button 1 – Text',                     'annyhase_hero', 'Produkte entdecken');
    $text('hero_btn_secondary', 'Button 2 – Text',                     'annyhase_hero', 'Meine Geschichte');
    $text('hero_stat1_value', 'Stat Links – Zahl/Wert',                'annyhase_hero', '100%');
    $text('hero_stat1_label', 'Stat Links – Bezeichnung',               'annyhase_hero', 'Handgemacht');
    $text('hero_rating', 'Stat Mitte – Etsy-Bewertung',                 'annyhase_hero', '5,0', 'text', 'Fallback-Wert – wird automatisch von Etsy geladen, wenn die API verbunden ist.');
    $text('hero_sales',  'Stat Rechts – Anzahl Verkäufe',               'annyhase_hero', '150+', 'text', 'Fallback-Wert – wird automatisch von Etsy geladen, wenn die API verbunden ist.');

    // ════════════════════════════════════════════
    // SEKTION 2: Produkte
    // ════════════════════════════════════════════
    $c->add_section('annyhase_products', [
        'title'       => '🛍️ Produkte (Startseite)',
        'description' => 'Der Produkte-Block auf der Startseite.',
        'priority'    => 31,
    ]);
    $text('products_label', 'Abschnitts-Label (klein, über dem Titel)', 'annyhase_products', 'Meine Produkte');
    $text('products_title', 'Überschrift',                               'annyhase_products', 'Handgefertigte Unikate');
    $textarea('products_desc', 'Beschreibungstext',                      'annyhase_products', 'Jedes Stück ist ein Einzelstück – gefertigt mit professionellem Handwerk und der Inspiration aus Farben, Formen und der Natur.');

    $c->add_setting('annyhase_products_per_page', ['default' => 8, 'sanitize_callback' => 'absint', 'transport' => 'refresh']);
    $c->add_control('annyhase_products_per_page', [
        'label'       => 'Anzahl Produkte',
        'description' => 'Wie viele Produkte werden angezeigt?',
        'section'     => 'annyhase_products',
        'type'        => 'number',
        'input_attrs' => ['min' => 1, 'max' => 24, 'step' => 1],
    ]);

    $c->add_setting('annyhase_products_columns', ['default' => '4', 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'refresh']);
    $c->add_control('annyhase_products_columns', [
        'label'   => 'Spalten pro Reihe',
        'section' => 'annyhase_products',
        'type'    => 'select',
        'choices' => ['2' => '2 Spalten', '3' => '3 Spalten', '4' => '4 Spalten', '5' => '5 Spalten'],
    ]);


    // ════════════════════════════════════════════
    // SEKTION 4: Galerie
    // ════════════════════════════════════════════
    $c->add_section('annyhase_gallery', [
        'title'       => '🖼️ Galerie',
        'description' => 'Werkstatt-Einblicke: Texte, Bilder und Instagram-Button.',
        'priority'    => 32,
    ]);
    $text    ('gallery_label', 'Abschnitts-Label (klein, über dem Titel)', 'annyhase_gallery', 'Galerie');
    $text    ('gallery_title', 'Überschrift',                               'annyhase_gallery', 'Einblicke aus der Werkstatt');
    $textarea('gallery_desc',  'Beschreibungstext',                         'annyhase_gallery', 'Sieh zu, wie Ton zu Kunst wird – von der ersten Form bis zum fertigen Unikat.');

    $c->add_setting('annyhase_gallery_count', ['default' => 6, 'sanitize_callback' => 'absint', 'transport' => 'refresh']);
    $c->add_control('annyhase_gallery_count', [
        'label'       => 'Anzahl anzuzeigender Bilder',
        'description' => 'Maximal 12 Bilder möglich.',
        'section'     => 'annyhase_gallery',
        'type'        => 'number',
        'input_attrs' => ['min' => 1, 'max' => 12, 'step' => 1],
    ]);

    for ($i = 1; $i <= 12; $i++) {
        $image('gallery_image_' . $i, 'Galerie-Bild ' . $i, 'annyhase_gallery');
    }

    $text('gallery_btn_text', 'Instagram-Button Text', 'annyhase_gallery', 'Mehr auf Instagram');

    // ════════════════════════════════════════════
    // SEKTION 5: Über mich
    // ════════════════════════════════════════════
    $c->add_section('annyhase_about', [
        'title'       => '👩‍🎨 Über mich',
        'description' => 'Dein Portrait und dein persönlicher Text.',
        'priority'    => 33,
    ]);
    $text    ('about_label', 'Abschnitts-Label (klein)',  'annyhase_about', 'Über mich');
    $text    ('about_title', 'Überschrift',                'annyhase_about', 'Hallo, ich bin Anny');
    $image   ('about_image', 'Portrait-Foto',              'annyhase_about');
    $textarea('about_text',  'Text',                       'annyhase_about', 'Am Fuße der Schwäbischen Alb erschaffe ich mit Leidenschaft handgefertigte Keramik und Unikate. Jedes Stück entsteht mit professionellem Handwerk und der Inspiration, die ich in Farben, Formen, Materialien und der Natur finde.');
    $text    ('about_btn_primary', 'Button 1 – Text',      'annyhase_about', 'Kontakt aufnehmen');
    $text    ('about_btn_etsy',    'Button 2 – Text',      'annyhase_about', 'Zum Etsy Shop');

    // 4 Werte-Karten
    $text('about_val1_icon',  'Wert 1 – Icon',        'annyhase_about', '🤲');
    $text('about_val1_title', 'Wert 1 – Titel',       'annyhase_about', 'Handgemacht');
    $text('about_val1_desc',  'Wert 1 – Beschreibung','annyhase_about', 'Jedes Stück mit Sorgfalt von Hand gefertigt');
    $text('about_val2_icon',  'Wert 2 – Icon',        'annyhase_about', '🌿');
    $text('about_val2_title', 'Wert 2 – Titel',       'annyhase_about', 'Naturinspiriert');
    $text('about_val2_desc',  'Wert 2 – Beschreibung','annyhase_about', 'Farben und Formen aus der Natur entlehnt');
    $text('about_val3_icon',  'Wert 3 – Icon',        'annyhase_about', '✨');
    $text('about_val3_title', 'Wert 3 – Titel',       'annyhase_about', 'Hochwertig');
    $text('about_val3_desc',  'Wert 3 – Beschreibung','annyhase_about', 'Nur die besten Materialien und Werkzeuge');
    $text('about_val4_icon',  'Wert 4 – Icon',        'annyhase_about', '💌');
    $text('about_val4_title', 'Wert 4 – Titel',       'annyhase_about', 'Mit Liebe');
    $text('about_val4_desc',  'Wert 4 – Beschreibung','annyhase_about', 'Jeder Auftrag wird mit Herzblut ausgeführt');

    // ════════════════════════════════════════════
    // SEKTION 6: Kundenstimmen
    // ════════════════════════════════════════════
    $c->add_section('annyhase_reviews', [
        'title'       => '⭐ Kundenstimmen',
        'description' => 'Die Überschriften der Bewertungs-Sektion.',
        'priority'    => 34,
    ]);
    $text('reviews_label', 'Abschnitts-Label (klein)', 'annyhase_reviews', 'Kundenstimmen');
    $text('reviews_title', 'Überschrift',               'annyhase_reviews', 'Was meine Kunden sagen');

    $c->add_setting('annyhase_reviews_total', ['default' => 6, 'sanitize_callback' => 'absint', 'transport' => 'refresh']);
    $c->add_control('annyhase_reviews_total', [
        'label'       => 'Gesamt angezeigte Bewertungen',
        'section'     => 'annyhase_reviews',
        'type'        => 'number',
        'input_attrs' => ['min' => 1, 'max' => 12, 'step' => 1],
    ]);

    $c->add_setting('annyhase_reviews_per_slide', ['default' => 3, 'sanitize_callback' => 'absint', 'transport' => 'refresh']);
    $c->add_control('annyhase_reviews_per_slide', [
        'label'       => 'Bewertungen pro Ansicht (Desktop)',
        'description' => 'Auf Tablet werden maximal 2 gezeigt, auf dem Handy immer 1.',
        'section'     => 'annyhase_reviews',
        'type'        => 'number',
        'input_attrs' => ['min' => 1, 'max' => 5, 'step' => 1],
    ]);

    $c->add_setting('annyhase_reviews_speed', ['default' => 6, 'sanitize_callback' => 'absint', 'transport' => 'refresh']);
    $c->add_control('annyhase_reviews_speed', [
        'label'       => 'Auto-Slide Geschwindigkeit (Sekunden)',
        'description' => '0 = Auto-Slide deaktiviert.',
        'section'     => 'annyhase_reviews',
        'type'        => 'number',
        'input_attrs' => ['min' => 0, 'max' => 30, 'step' => 1],
    ]);

    // ════════════════════════════════════════════
    // SEKTION 7: Etsy-Banner (Footer)
    // ════════════════════════════════════════════
    $c->add_section('annyhase_etsy_banner', [
        'title'       => '🛍️ Etsy-Banner',
        'description' => 'Der Banner direkt über dem Footer.',
        'priority'    => 35,
    ]);
    $text    ('etsy_banner_title', 'Überschrift',    'annyhase_etsy_banner', 'Auch auf Etsy erhältlich');
    $textarea('etsy_banner_desc',  'Beschreibung',   'annyhase_etsy_banner', 'Bestell bequem über meinen Etsy-Shop – sichere Zahlung, weltweiter Versand.');
    $text    ('etsy_banner_btn',   'Button Text',     'annyhase_etsy_banner', 'Zum Etsy Shop');
    $image   ('etsy_banner_logo',  'Eigenes Logo hochladen (optional – ersetzt das Standard-Etsy-Logo)', 'annyhase_etsy_banner');

    // ════════════════════════════════════════════
    // SEKTION 8: Links & Kontakt
    // Alle hier eingetragenen Links gelten theme-weit
    // (Footer, Header, Startseite, Kontaktseite, 404)
    // ════════════════════════════════════════════
    $c->add_section('annyhase_links', [
        'title'       => '🔗 Links & Kontakt',
        'description' => 'Diese Links gelten überall im Theme – im Footer, Header, auf der Startseite, der Kontaktseite und der 404-Seite.',
        'priority'    => 42,
    ]);
    $text('etsy_shop_url',    '🛍️ Etsy Shop URL',   'annyhase_links', 'https://www.etsy.com/shop/Annyhase');
    $text('instagram_url',    '📸 Instagram URL',     'annyhase_links', 'https://www.instagram.com/annyhase_official');
    $text('contact_email',    '✉️ Kontakt E-Mail',    'annyhase_links', get_option('admin_email'));

    // ════════════════════════════════════════════
    // SEKTION: Header & Logo
    // ════════════════════════════════════════════
    $c->add_section('annyhase_header', [
        'title'    => '🔝 Header & Logo',
        'priority' => 25,
    ]);

    $c->add_setting('annyhase_header_logo_mode', [
        'default'           => 'logo_text',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $c->add_control('annyhase_header_logo_mode', [
        'label'   => 'Logo-Anzeige',
        'section' => 'annyhase_header',
        'type'    => 'select',
        'choices' => [
            'logo_text'  => 'Logo + Text',
            'text_only'  => 'Nur Text',
            'logo_only'  => 'Nur Logo',
        ],
    ]);

    $c->add_setting('annyhase_header_brand_split', [
        'default'           => 4,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $c->add_control('annyhase_header_brand_split', [
        'label'       => 'Ab Zeichen X andere Farbe (z.B. 4 → „Anny|hase")',
        'description' => 'Basiert auf dem Website-Titel unter Einstellungen → Allgemein.',
        'section'     => 'annyhase_header',
        'type'        => 'number',
        'input_attrs' => ['min' => 0, 'max' => 30, 'step' => 1],
    ]);

    // ════════════════════════════════════════════
    // SEKTION: Footer
    // ════════════════════════════════════════════
    $c->add_section('annyhase_footer', [
        'title'    => '🦶 Footer',
        'priority' => 36,
    ]);

    $image('footer_logo', 'Logo hochladen (optional)', 'annyhase_footer');

    $c->add_setting('annyhase_footer_logo_mode', [
        'default'           => 'logo_text',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $c->add_control('annyhase_footer_logo_mode', [
        'label'   => 'Logo-Anzeige',
        'section' => 'annyhase_footer',
        'type'    => 'select',
        'choices' => [
            'logo_text'  => 'Logo + Text',
            'text_only'  => 'Nur Text',
            'logo_only'  => 'Nur Logo',
        ],
    ]);

    $text('footer_brand_title', 'Markenname', 'annyhase_footer', 'Annyhase');

    $c->add_setting('annyhase_footer_brand_split', [
        'default'           => 4,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $c->add_control('annyhase_footer_brand_split', [
        'label'       => 'Ab Zeichen X andere Farbe (z.B. 4 → „Anny|hase")',
        'section'     => 'annyhase_footer',
        'type'        => 'number',
        'input_attrs' => ['min' => 0, 'max' => 30, 'step' => 1],
    ]);

    $text    ('footer_brand_tagline',  'Tagline unter dem Logo',        'annyhase_footer', 'Handgemachte Keramik & Unikate aus dem Schwabenland');
    $text    ('footer_tagline',        'Footer-Claim',                  'annyhase_footer', 'Handgemacht mit Liebe');
    $text    ('footer_tagline_emoji',  'Emoji nach dem Claim',          'annyhase_footer', '🤍');

    // ════════════════════════════════════════════
    // SEKTION: Kontaktformular
    // ════════════════════════════════════════════
    $c->add_section('annyhase_contact_form', [
        'title'       => '📬 Kontaktformular',
        'description' => 'Texte auf der Kontaktseite und alle E-Mail-Einstellungen.',
        'priority'    => 41,
    ]);

    // ── Kontaktseite: Texte ──
    $text('kf_hero_title',      'Seitentitel',                'annyhase_contact_form', 'Schreib mir!');
    $text('kf_hero_sub',        'Untertitel',                 'annyhase_contact_form', 'Fragen, Bestellwünsche oder einfach Hallo – ich freue mich über jede Nachricht.');
    $text('kf_note_text',       'Zitat-Text (Sidebar)',       'annyhase_contact_form', 'Jede Nachricht wird von mir persönlich gelesen und beantwortet – ich freue mich wirklich über eure Nachrichten!');
    $text('kf_note_sig',        'Signatur unter dem Zitat',   'annyhase_contact_form', '– Anny');
    $text('kf_response_badge',  'Antwortzeit-Badge',          'annyhase_contact_form', 'Antwortet innerhalb von 1–2 Werktagen');

    // ── Wohin gehen die Nachrichten? ──
    $c->add_setting('annyhase_contact_email', [
        'default'           => get_option('admin_email'),
        'sanitize_callback' => 'sanitize_email',
        'transport'         => 'refresh',
    ]);
    $c->add_control('annyhase_contact_email', [
        'label'       => '✉️ Deine E-Mail-Adresse (Nachrichten kommen hier an)',
        'description' => 'Alle Kontaktanfragen werden an diese Adresse gesendet.',
        'section'     => 'annyhase_contact_form',
        'type'        => 'email',
    ]);

    // Erfolgsmeldung auf der Seite
    $text('contact_success_msg',
        'Erfolgsmeldung (sichtbar nach dem Absenden)',
        'annyhase_contact_form',
        'Vielen Dank für deine Nachricht! Ich melde mich so schnell wie möglich bei dir.'
    );

    // Benachrichtigung an dich
    $text('contact_notify_subject',
        'Betreff der Benachrichtigungs-E-Mail (du bekommst diese)',
        'annyhase_contact_form',
        'Neue Kontaktanfrage: {subject}',
        'text',
        'Verfügbare Platzhalter: {name}, {email}, {subject}'
    );

    // ── Auto-Antwort ──
    $c->add_setting('annyhase_contact_autoreply', [
        'default'           => 1,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $c->add_control('annyhase_contact_autoreply', [
        'label'       => 'Automatische Bestätigungsmail an Absender senden',
        'section'     => 'annyhase_contact_form',
        'type'        => 'checkbox',
    ]);

    $text('contact_autoreply_from',
        'Absendername der Bestätigungsmail',
        'annyhase_contact_form',
        get_bloginfo('name')
    );

    $text('contact_autoreply_subject',
        'Betreff der Bestätigungsmail',
        'annyhase_contact_form',
        'Deine Nachricht ist angekommen ✨',
        'text',
        'Verfügbare Platzhalter: {name}, {subject}'
    );

    $c->add_setting('annyhase_contact_autoreply_body', [
        'default'           => "Hallo {name},\n\nvielen Dank für deine Nachricht! Ich habe sie erhalten und melde mich so schnell wie möglich bei dir.\n\nViele Grüße,\nAnny",
        'sanitize_callback' => 'wp_kses_post',
        'transport'         => 'refresh',
    ]);
    $c->add_control('annyhase_contact_autoreply_body', [
        'label'       => 'Text der Bestätigungsmail',
        'description' => 'Verfügbare Platzhalter: {name}, {email}, {subject}. Zwei Leerzeilen = neuer Absatz.',
        'section'     => 'annyhase_contact_form',
        'type'        => 'textarea',
    ]);

    $c->add_setting('annyhase_contact_autoreply_show_message', [
        'default'           => 1,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $c->add_control('annyhase_contact_autoreply_show_message', [
        'label'       => 'Originale Nachricht in der Bestätigungsmail anzeigen',
        'description' => 'Zeigt am Ende der Mail die gesendete Nachricht in einer Zitierbox.',
        'section'     => 'annyhase_contact_form',
        'type'        => 'checkbox',
    ]);

    // ── reCAPTCHA v3 ──
    foreach ([
        ['annyhase_recaptcha_site_key',   'reCAPTCHA v3 – Site Key (öffentlich)',
         'Den Site Key aus google.com/recaptcha → reCAPTCHA v3. Leer lassen um reCAPTCHA zu deaktivieren.'],
        ['annyhase_recaptcha_secret_key', 'reCAPTCHA v3 – Secret Key (privat)',
         'Den Secret Key aus der reCAPTCHA-Konsole. Wird nur serverseitig verwendet und nie ausgegeben.'],
    ] as [$id, $label, $desc]) {
        $c->add_setting($id, ['default' => '', 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'refresh']);
        $c->add_control($id, ['label' => $label, 'description' => $desc, 'section' => 'annyhase_contact_form', 'type' => 'text']);
    }

    // ════════════════════════════════════════════
    // SEKTION: Wartungsmodus
    // ════════════════════════════════════════════
    $c->add_section('annyhase_maintenance', [
        'title'       => '🔧 Wartungsmodus',
        'description' => 'Wenn aktiv, sehen nur eingeloggte Benutzer die Website. Alle anderen erhalten eine Wartungsseite.',
        'priority'    => 41,
    ]);

    $c->add_setting('annyhase_maintenance_mode', [
        'default'           => false,
        'sanitize_callback' => 'rest_sanitize_boolean',
        'transport'         => 'refresh',
    ]);
    $c->add_control('annyhase_maintenance_mode', [
        'label'       => '⚠️ Wartungsmodus aktivieren',
        'description' => 'Eingeloggte Admins sehen die Seite normal – alle anderen sehen die Wartungsseite.',
        'section'     => 'annyhase_maintenance',
        'type'        => 'checkbox',
    ]);

    $text('maintenance_title', 'Überschrift der Wartungsseite', 'annyhase_maintenance', 'Gleich wieder für euch da!');
    $text('maintenance_text',  'Text der Wartungsseite',        'annyhase_maintenance', 'Die Website wird gerade aktualisiert und ist vorübergehend nicht erreichbar. Schau bald wieder vorbei!');

    // ════════════════════════════════════════════
    // SEKTION: SEO & Webmaster-Tools
    // ════════════════════════════════════════════
    $c->add_section('annyhase_seo', [
        'title'       => '🔍 SEO & Webmaster-Tools',
        'description' => 'Verifikationscode für Google Search Console und Google Analytics.',
        'priority'    => 42,
    ]);

    foreach ([
        ['annyhase_google_site_verification', 'Google Search Console – Verifikationscode',
         'Den Code aus Google Search Console → Einstellungen → Eigentumsnachweis → HTML-Tag. Nur den Wert des content-Attributs eingeben.'],
        ['annyhase_google_analytics_id',      'Google Analytics 4 – Measurement ID',
         'Format: G-XXXXXXXXXX. Nur eintragen wenn du ein aktives Cookie-Consent-Plugin (z.B. Complianz) eingebunden hast, da GA4 ohne Einwilligung gegen die DSGVO verstößt.'],
    ] as [$id, $label, $desc]) {
        $c->add_setting($id, ['default' => '', 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'refresh']);
        $c->add_control($id, ['label' => $label, 'description' => $desc, 'section' => 'annyhase_seo', 'type' => 'text']);
    }

    // ════════════════════════════════════════════
    // SEKTION: Archiv & Produktdetails
    // ════════════════════════════════════════════
    $c->add_section('annyhase_products_advanced', [
        'title'       => '📦 Produkt-Übersicht & Details',
        'description' => 'Einstellungen für die Produkt-Übersichtsseite (/produkte/) und die Info-Zeilen auf Produktdetailseiten.',
        'priority'    => 40,
    ]);

    // ── Produkt-Übersicht ──
    $text('archive_label', 'Seitentitel der Übersicht', 'annyhase_products_advanced', 'Alle Produkte');

    $c->add_setting('annyhase_archive_per_page', ['default' => 12, 'sanitize_callback' => 'absint', 'transport' => 'refresh']);
    $c->add_control('annyhase_archive_per_page', [
        'label'       => 'Produkte pro Seite (Übersicht)',
        'section'     => 'annyhase_products_advanced',
        'type'        => 'number',
        'input_attrs' => ['min' => 1, 'max' => 100, 'step' => 1],
    ]);

    $c->add_setting('annyhase_archive_columns', ['default' => '4', 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'refresh']);
    $c->add_control('annyhase_archive_columns', [
        'label'   => 'Spalten pro Reihe',
        'section' => 'annyhase_products_advanced',
        'type'    => 'select',
        'choices' => ['2' => '2 Spalten', '3' => '3 Spalten', '4' => '4 Spalten', '5' => '5 Spalten'],
    ]);

    // ── Produktdetailseite – Info-Zeilen ──
    $text('detail_row1_icon', 'Info-Zeile 1 – Icon (Emoji)', 'annyhase_products_advanced', '🤲');
    $text('detail_row1_text', 'Info-Zeile 1 – Text',          'annyhase_products_advanced', 'Handgemachtes Unikat – kleine Abweichungen in Form und Farbe sind Ausdruck echter Handarbeit.');
    $text('detail_row2_icon', 'Info-Zeile 2 – Icon (Emoji)', 'annyhase_products_advanced', '📦');
    $text('detail_row2_text', 'Info-Zeile 2 – Text',          'annyhase_products_advanced', 'Versand aus Deutschland');
    $text('detail_row3_icon', 'Info-Zeile 3 – Icon (Emoji)', 'annyhase_products_advanced', '✉️');
    $text('detail_row3_text', 'Info-Zeile 3 – Text',          'annyhase_products_advanced', 'Bei Fragen einfach schreiben');



}
add_action('customize_register', 'annyhase_customizer');

/* -------------------------------------------------------
   Customizer: Standard-Sektionen aufräumen
   Läuft bei Priority 20, damit WP-Core zuerst registriert
------------------------------------------------------- */
add_action('customize_register', function (WP_Customize_Manager $c): void {

    // ── Website-Informationen → Header & Logo verschieben ──
    $move = [
        'blogname'    => ['priority' => 5, 'label' => 'Website-Titel'],
        'custom_logo' => ['priority' => 7, 'label' => 'Logo'],
        'site_icon'   => ['priority' => 8, 'label' => 'Favicon (Website-Icon)'],
    ];
    foreach ($move as $id => $opts) {
        $ctrl = $c->get_control($id);
        if (!$ctrl) continue;
        $ctrl->section  = 'annyhase_header';
        $ctrl->label    = $opts['label'];
        $ctrl->priority = $opts['priority'];
    }

    // Tagline wird im Theme nicht verwendet → entfernen
    $c->remove_control('blogdescription');

    // ── Standard-Sektionen ausblenden ──
    foreach (['title_tagline', 'static_front_page', 'custom_css'] as $id) {
        if ($sec = $c->get_section($id)) {
            $sec->active_callback = '__return_false';
        }
    }

    // ── Menüs-Panel: schönes Icon ──
    if ($panel = $c->get_panel('nav_menus')) {
        $panel->title = '🧭 Menüs';
    }

    // ── Widgets-Panel/-Sektion ausblenden ──
    $c->remove_panel('widgets');
    if ($sec = $c->get_section('widgets')) {
        $sec->active_callback = '__return_false';
    }

}, 99);
