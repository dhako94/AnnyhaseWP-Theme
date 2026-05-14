<?php defined('ABSPATH') || exit; get_header(); ?>

<!-- ============================================================
     HERO SECTION
     ============================================================ -->
<section class="hero-section">
    <div class="container">
        <div class="hero reveal">
            <span class="hero__badge"><?php echo esc_html(get_theme_mod('annyhase_hero_badge_icon', '🏺')); ?> <?php echo esc_html(get_theme_mod('annyhase_hero_badge', 'Handgemachte Keramik aus der Schwäbischen Alb')); ?></span>
            <h1 class="hero__title">
                <?php echo esc_html(get_theme_mod('annyhase_hero_title', 'Unikate mit')); ?>
                <em><?php echo esc_html(get_theme_mod('annyhase_hero_title_em', 'Herz & Handwerk')); ?></em>
            </h1>
            <p class="hero__desc">
                <?php echo esc_html(get_theme_mod('annyhase_hero_tagline', 'Jedes Stück ein Unikat – handgefertigt am Fuße der Schwäbischen Alb, mit Liebe zum Detail und hochwertigen Materialien.')); ?>
            </p>
            <div class="hero__actions">
                <a href="#produkte" class="btn btn-primary"><?php echo esc_html(get_theme_mod('annyhase_hero_btn_primary', 'Produkte entdecken')); ?></a>
                <a href="#ueber-mich" class="btn btn-outline"><?php echo esc_html(get_theme_mod('annyhase_hero_btn_secondary', 'Meine Geschichte')); ?></a>
            </div>
        </div>
    </div>

    <?php $etsy = etsy_sync_get_stats(); ?>
    <!-- Stats-Leiste -->
    <div class="hero__stats-bar">
        <div class="container">
            <div class="hero__stats-grid">
                <div class="hero__stat">
                    <span class="hero__stat-num"><?php echo esc_html(get_theme_mod('annyhase_hero_stat1_value', '100%')); ?></span>
                    <span class="hero__stat-label"><?php echo esc_html(get_theme_mod('annyhase_hero_stat1_label', 'Handgemacht')); ?></span>
                </div>
                <div class="hero__stat">
                    <span class="hero__stat-num">⭐ <?php echo esc_html($etsy['rating']); ?></span>
                    <span class="hero__stat-label">
                        <?php esc_html_e('Etsy-Bewertung', 'annyhase'); ?>
                        <?php if (!empty($etsy['reviews'])): ?>
                            <small style="display:block;font-size:.7rem;opacity:.7">(<?php echo esc_html($etsy['reviews']); ?> Bewertungen)</small>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="hero__stat">
                    <span class="hero__stat-num"><?php echo esc_html($etsy['sales']); ?></span>
                    <span class="hero__stat-label"><?php esc_html_e('Verkäufe', 'annyhase'); ?></span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     PRODUKTE SECTION
     ============================================================ -->
<section class="section section--alt" id="produkte">
    <div class="container">
        <div class="section-header centered reveal">
            <span class="section-label"><?php echo esc_html(get_theme_mod('annyhase_products_label', 'Meine Produkte')); ?></span>
            <h2 class="section-title"><?php echo esc_html(get_theme_mod('annyhase_products_title', 'Handgefertigte Unikate')); ?></h2>
            <p class="section-desc"><?php echo esc_html(get_theme_mod('annyhase_products_desc', 'Jedes Stück ist ein Einzelstück – gefertigt mit professionellem Handwerk und der Inspiration aus Farben, Formen und der Natur.')); ?></p>
        </div>

        <?php
        $products_per_page = absint(get_theme_mod('annyhase_products_per_page', 8));
        $products_columns  = absint(get_theme_mod('annyhase_products_columns', 4));
        if ($products_columns < 2 || $products_columns > 5) $products_columns = 4;

        add_filter('posts_clauses', 'annyhase_highlight_order_clauses');
        $products = new WP_Query([
            'post_type'      => 'produkt',
            'posts_per_page' => $products_per_page,
            'post_status'    => 'publish',
        ]);
        remove_filter('posts_clauses', 'annyhase_highlight_order_clauses');
        ?>

        <?php if ($products->have_posts()): ?>
        <div class="grid-<?php echo $products_columns; ?> reveal" style="transition-delay:.1s">
            <?php while ($products->have_posts()): $products->the_post(); ?>
            <article class="product-card">
                <a href="<?php the_permalink(); ?>" class="product-card__img-wrap" tabindex="-1" aria-hidden="true">
                    <?php if (has_post_thumbnail()): ?>
                        <?php the_post_thumbnail('product-thumb', ['class' => 'product-card__img', 'loading' => 'lazy']); ?>
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
                    <h3 class="product-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                    <?php $price = annyhase_product_price(); if ($price): ?>
                    <div class="product-card__price"><?php echo esc_html($price); ?></div>
                    <?php endif; ?>
                </div>
                <div class="product-card__footer">
                    <?php if (annyhase_is_etsy() && annyhase_etsy_url()): ?>
                        <a href="<?php echo annyhase_etsy_url(); ?>" target="_blank" rel="noopener noreferrer" class="product-card__link">
                            🛍️ <?php esc_html_e('Auf Etsy kaufen', 'annyhase'); ?>
                        </a>
                    <?php else: ?>
                        <a href="<?php the_permalink(); ?>" class="product-card__link"><?php esc_html_e('Mehr erfahren', 'annyhase'); ?> &rarr;</a>
                    <?php endif; ?>
                </div>
            </article>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>

        <div class="text-center" style="margin-top:3rem">
            <a href="<?php echo esc_url(get_post_type_archive_link('produkt')); ?>" class="btn btn-outline">
                <?php esc_html_e('Alle Produkte ansehen', 'annyhase'); ?>
            </a>
        </div>

        <?php else: ?>
        <div class="text-center" style="padding:3rem;background:var(--color-surface);border-radius:var(--radius-lg);border:1px solid var(--color-border)">
            <div style="font-size:3rem;margin-bottom:1rem">🏺</div>
            <h3><?php esc_html_e('Produkte folgen bald!', 'annyhase'); ?></h3>
            <p class="text-muted"><?php esc_html_e('Im Adminbereich können Produkte hinzugefügt werden.', 'annyhase'); ?></p>
            <a href="<?php echo esc_url(get_theme_mod('annyhase_etsy_shop_url', 'https://www.etsy.com/shop/Annyhase')); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="margin-top:1.5rem">
                <?php esc_html_e('Jetzt auf Etsy stöbern', 'annyhase'); ?>
            </a>
        </div>
        <?php endif; ?>

    </div>
</section>

<!-- ============================================================
     GALERIE SECTION
     ============================================================ -->
<section class="section" id="galerie">
    <div class="container">
        <div class="section-header centered reveal">
            <span class="section-label"><?php echo esc_html(get_theme_mod('annyhase_gallery_label', 'Galerie')); ?></span>
            <h2 class="section-title"><?php echo esc_html(get_theme_mod('annyhase_gallery_title', 'Einblicke aus der Werkstatt')); ?></h2>
            <p class="section-desc"><?php echo esc_html(get_theme_mod('annyhase_gallery_desc', 'Sieh zu, wie Ton zu Kunst wird – von der ersten Form bis zum fertigen Unikat.')); ?></p>
        </div>

        <?php
        $gallery_max = max(1, min(12, absint(get_theme_mod('annyhase_gallery_count', 6))));
        $gallery_ids = [];
        for ($i = 1; $i <= 12; $i++) {
            $id = absint(get_theme_mod('annyhase_gallery_image_' . $i, 0));
            if ($id) $gallery_ids[] = $id;
        }
        $gallery_ids = array_slice($gallery_ids, 0, $gallery_max);
        $has_gallery = !empty($gallery_ids);
        ?>

        <?php if ($has_gallery): ?>
        <!-- Lightbox -->
        <div class="gallery-lb" id="gallery-lb" role="dialog" aria-modal="true" aria-label="Galerie">
            <button class="gallery-lb__close" id="lb-close" aria-label="Schließen">&times;</button>
            <button class="gallery-lb__prev"  id="lb-prev"  aria-label="Vorheriges Bild">&#8249;</button>
            <div class="gallery-lb__inner">
                <img class="gallery-lb__img" id="lb-img" src="" alt="">
                <p class="gallery-lb__caption" id="lb-caption"></p>
            </div>
            <button class="gallery-lb__next" id="lb-next" aria-label="Nächstes Bild">&#8250;</button>
        </div>
        <?php endif; ?>

        <div class="gallery-grid reveal" style="transition-delay:.1s">
            <?php if ($has_gallery): ?>
                <?php foreach ($gallery_ids as $img_id):
                    $caption  = wp_get_attachment_caption($img_id);
                    if (!$caption) $caption = get_post_meta($img_id, '_wp_attachment_image_alt', true);
                    $alt      = get_post_meta($img_id, '_wp_attachment_image_alt', true) ?: 'Annyhase Galerie';
                    $full_src = wp_get_attachment_image_src($img_id, 'full');
                    $full_url = $full_src ? esc_url($full_src[0]) : '';
                ?>
                <div class="gallery-item"
                     data-lb-src="<?php echo $full_url; ?>"
                     data-lb-caption="<?php echo esc_attr($caption); ?>"
                     role="button" tabindex="0"
                     aria-label="<?php echo esc_attr($caption ?: $alt); ?>">
                    <?php echo wp_get_attachment_image($img_id, 'product-wide', false, ['loading' => 'lazy', 'alt' => esc_attr($alt)]); ?>
                    <div class="gallery-item__overlay">✨ <?php echo esc_html($caption); ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php
                $placeholders = [
                    ['emoji' => '🏺', 'label' => 'Vase'],
                    ['emoji' => '☕', 'label' => 'Tasse'],
                    ['emoji' => '🫙', 'label' => 'Schale'],
                    ['emoji' => '🌿', 'label' => 'Dekoration'],
                    ['emoji' => '✨', 'label' => 'Unikat'],
                ];
                foreach ($placeholders as $ph):
                ?>
                <div class="gallery-item">
                    <div style="width:100%;height:100%;background:var(--color-cream);display:flex;align-items:center;justify-content:center;font-size:3rem">
                        <?php echo $ph['emoji']; ?>
                    </div>
                    <div class="gallery-item__overlay">✨ <?php echo esc_html($ph['label']); ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="text-center" style="margin-top:2rem">
            <a href="<?php echo esc_url(get_theme_mod('annyhase_instagram_url', 'https://www.instagram.com/annyhase_official')); ?>"
               target="_blank" rel="noopener noreferrer" class="btn btn-sage">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-.2em;margin-right:.35em" aria-hidden="true">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                </svg>
                <?php echo esc_html(get_theme_mod('annyhase_gallery_btn_text', 'Mehr auf Instagram')); ?>
            </a>
        </div>
    </div>
</section>

<?php if ($has_gallery): ?>
<script>
(function () {
    const items   = Array.from(document.querySelectorAll('.gallery-item[data-lb-src]'));
    const lb      = document.getElementById('gallery-lb');
    if (!items.length || !lb) return;

    const lbImg     = document.getElementById('lb-img');
    const lbCaption = document.getElementById('lb-caption');
    const lbClose   = document.getElementById('lb-close');
    const lbPrev    = document.getElementById('lb-prev');
    const lbNext    = document.getElementById('lb-next');
    let current     = 0;

    function show(index) {
        current = (index + items.length) % items.length;
        const item = items[current];
        lbImg.src         = item.dataset.lbSrc;
        lbImg.alt         = item.dataset.lbCaption || '';
        lbCaption.textContent = item.dataset.lbCaption || '';
        lbPrev.style.display = items.length > 1 ? '' : 'none';
        lbNext.style.display = items.length > 1 ? '' : 'none';
    }

    function open(index) {
        show(index);
        lb.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        lbClose.focus();
    }

    function close() {
        lb.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    items.forEach((item, i) => {
        item.addEventListener('click', () => open(i));
        item.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(i); } });
    });

    lbClose.addEventListener('click', close);
    lbPrev.addEventListener('click', () => show(current - 1));
    lbNext.addEventListener('click', () => show(current + 1));

    lb.addEventListener('click', e => { if (e.target === lb) close(); });

    document.addEventListener('keydown', e => {
        if (!lb.classList.contains('is-open')) return;
        if (e.key === 'Escape')     close();
        if (e.key === 'ArrowLeft')  show(current - 1);
        if (e.key === 'ArrowRight') show(current + 1);
    });

    let startX = 0;
    lb.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', e => {
        const dx = e.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 40) show(dx < 0 ? current + 1 : current - 1);
    });
})();
</script>
<?php endif; ?>

<!-- ============================================================
     ÜBER MICH SECTION
     ============================================================ -->
<section class="section section--cream" id="ueber-mich">
    <div class="container">
        <div class="about-grid">
            <!-- Image -->
            <div class="about__img-wrap reveal">
                <?php
                $about_img_id = absint(get_theme_mod('annyhase_about_image', 0));
                if ($about_img_id):
                    echo wp_get_attachment_image($about_img_id, 'product-wide', false, ['class' => 'about__img', 'alt' => 'Annyhase Portrait']);
                else:
                ?>
                <img src="https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=600&h=750&fit=crop&q=80"
                     alt="Töpferin bei der Arbeit – Platzhalterbild"
                     class="about__img"
                     loading="lazy">
                <?php endif; ?>
            </div>

            <!-- Text -->
            <div class="reveal" style="transition-delay:.15s">
                <span class="section-label"><?php echo esc_html(get_theme_mod('annyhase_about_label', 'Über mich')); ?></span>
                <h2 class="section-title"><?php echo esc_html(get_theme_mod('annyhase_about_title', 'Hallo, ich bin Anny')); ?></h2>
                <div style="color:var(--color-text-muted);line-height:1.8">
                    <?php echo wpautop(wp_kses_post(get_theme_mod('annyhase_about_text', 'Am Fuße der Schwäbischen Alb erschaffe ich mit Leidenschaft handgefertigte Keramik und Unikate. Jedes Stück entsteht mit professionellem Handwerk und der Inspiration, die ich in Farben, Formen, Materialien und der Natur finde.'))); ?>
                </div>

                <div class="about__values">
                    <?php
                    $values = [
                        [get_theme_mod('annyhase_about_val1_icon', '🤲'), get_theme_mod('annyhase_about_val1_title', 'Handgemacht'),    get_theme_mod('annyhase_about_val1_desc', 'Jedes Stück mit Sorgfalt von Hand gefertigt')],
                        [get_theme_mod('annyhase_about_val2_icon', '🌿'), get_theme_mod('annyhase_about_val2_title', 'Naturinspiriert'), get_theme_mod('annyhase_about_val2_desc', 'Farben und Formen aus der Natur entlehnt')],
                        [get_theme_mod('annyhase_about_val3_icon', '✨'), get_theme_mod('annyhase_about_val3_title', 'Hochwertig'),      get_theme_mod('annyhase_about_val3_desc', 'Nur die besten Materialien und Werkzeuge')],
                        [get_theme_mod('annyhase_about_val4_icon', '💌'), get_theme_mod('annyhase_about_val4_title', 'Mit Liebe'),       get_theme_mod('annyhase_about_val4_desc', 'Jeder Auftrag wird mit Herzblut ausgeführt')],
                    ];
                    foreach ($values as [$icon, $title, $desc]):
                    ?>
                    <div class="about__value">
                        <div class="about__value-icon"><?php echo esc_html($icon); ?></div>
                        <div>
                            <strong><?php echo esc_html($title); ?></strong>
                            <div style="font-size:.9rem;color:var(--color-text-muted)"><?php echo esc_html($desc); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top:2rem;display:flex;gap:1rem;flex-wrap:wrap">
                    <a href="<?php echo esc_url(home_url('/kontakt')); ?>" class="btn btn-primary">
                        <?php echo esc_html(get_theme_mod('annyhase_about_btn_primary', 'Kontakt aufnehmen')); ?>
                    </a>
                    <a href="<?php echo esc_url(get_theme_mod('annyhase_etsy_shop_url', 'https://www.etsy.com/shop/Annyhase')); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline">
                        🛍️ <?php echo esc_html(get_theme_mod('annyhase_about_btn_etsy', 'Zum Etsy Shop')); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     BEWERTUNGEN / TESTIMONIALS
     ============================================================ -->
<div id="bewertungen">
<?php get_template_part('inc/testimonials'); ?>
</div>

<?php get_footer(); ?>
