<?php
defined('ABSPATH') || exit;
/**
 * Template part: customer reviews / testimonial slider
 * Include via: get_template_part('inc/testimonials');
 */

/* Unique cache key; invalidated by save_post_bewertung / before_delete_post hooks in functions.php */
$cards = get_transient('annyhase_reviews_data');

if (false === $cards) {
    $cards = [];

    $reviews_query = new WP_Query([
        'post_type'      => 'bewertung',
        'posts_per_page' => 50,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    if ($reviews_query->have_posts()):
        while ($reviews_query->have_posts()): $reviews_query->the_post();
            $stars     = intval(get_post_meta(get_the_ID(), '_bewertung_sterne',    true) ?: 5);
            $text      = wp_kses_post(get_the_content() ?: get_the_excerpt());
            $highlight = (bool) get_post_meta(get_the_ID(), '_bewertung_highlight', true);
            $cards[]   = compact('stars', 'text', 'highlight');
        endwhile; wp_reset_postdata();
    else:
        $cards = [
            ['stars' => 5, 'text' => 'Absolut wunderschöne Keramik! Die Qualität ist hervorragend und das Paket kam liebevoll verpackt an.',            'highlight' => false],
            ['stars' => 5, 'text' => 'Jedes Stück ist ein kleines Kunstwerk. Ich bin begeistert von der Verarbeitung und dem einzigartigen Charakter.', 'highlight' => false],
            ['stars' => 5, 'text' => 'Super schnelle Lieferung, tolle Qualität und ein sehr persönlicher Service. Wärmstens empfohlen!',                'highlight' => false],
        ];
    endif;

    usort($cards, static fn($a, $b) => (int)($b['highlight'] ?? 0) - (int)($a['highlight'] ?? 0));

    set_transient('annyhase_reviews_data', $cards, 5 * MINUTE_IN_SECONDS);
}

$reviews_total     = max(1, min(50, absint(get_theme_mod('annyhase_reviews_total',     6))));
$reviews_per_slide = max(1, min(5,  absint(get_theme_mod('annyhase_reviews_per_slide', 3))));
$reviews_speed     = absint(get_theme_mod('annyhase_reviews_speed', 6));
$cards             = array_slice($cards, 0, $reviews_total);
$card_count        = count($cards);

if (!$card_count) return;

/* Unique ID so multiple instances per page are possible */
$uid = 'ts-' . substr(md5(uniqid('', true)), 0, 6);
?>

<section class="section section--alt" style="padding-bottom:0">
    <div class="container">
        <div class="section-header centered reveal">
            <span class="section-label"><?php echo esc_html(get_theme_mod('annyhase_reviews_label', 'Kundenstimmen')); ?></span>
            <h2 class="section-title"><?php echo esc_html(get_theme_mod('annyhase_reviews_title', 'Was meine Kunden sagen')); ?></h2>
        </div>

        <div class="testimonial-slider reveal" style="transition-delay:.1s">
            <div class="testimonial-track" id="<?php echo esc_attr($uid); ?>-track">
                <?php foreach ($cards as $card): ?>
                <div class="ts-slide">
                    <div class="testimonial-card">
                        <div class="testimonial-card__stars"><?php echo str_repeat('★', (int) $card['stars']); ?></div>
                        <div class="testimonial-card__text"><?php echo $card['text']; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($card_count > 1): ?>
            <div class="testimonial-controls" id="<?php echo esc_attr($uid); ?>-controls">
                <button class="testimonial-btn" id="<?php echo esc_attr($uid); ?>-prev" aria-label="<?php esc_attr_e('Vorherige Bewertung', 'annyhase'); ?>">&#8249;</button>
                <div class="testimonial-dots" id="<?php echo esc_attr($uid); ?>-dots"></div>
                <button class="testimonial-btn" id="<?php echo esc_attr($uid); ?>-next" aria-label="<?php esc_attr_e('Nächste Bewertung', 'annyhase'); ?>">&#8250;</button>
            </div>
            <?php endif; ?>
        </div>

        <script>
        (function () {
            const track    = document.getElementById('<?php echo esc_js($uid); ?>-track');
            const prevBtn  = document.getElementById('<?php echo esc_js($uid); ?>-prev');
            const nextBtn  = document.getElementById('<?php echo esc_js($uid); ?>-next');
            const dotsEl   = document.getElementById('<?php echo esc_js($uid); ?>-dots');
            const controls = document.getElementById('<?php echo esc_js($uid); ?>-controls');
            if (!track) return;

            const slides     = Array.from(track.children);
            const total      = slides.length;
            const cfgPerView = <?php echo (int) $reviews_per_slide; ?>;
            const cfgSpeed   = <?php echo (int) $reviews_speed; ?> * 1000;
            let current = 0, perView = cfgPerView, maxPos = 0, timer = null;

            function getPerView() {
                if (window.innerWidth < 640)  return 1;
                if (window.innerWidth < 1024) return Math.min(2, cfgPerView);
                return cfgPerView;
            }

            function buildDots() {
                if (!dotsEl) return;
                dotsEl.innerHTML = '';
                for (let i = 0; i <= maxPos; i++) {
                    const d = document.createElement('button');
                    d.className = 'testimonial-dot' + (i === current ? ' is-active' : '');
                    d.setAttribute('aria-label', 'Bewertung ' + (i + 1));
                    d.addEventListener('click', () => go(i));
                    dotsEl.appendChild(d);
                }
            }

            function go(n) {
                current = Math.max(0, Math.min(n, maxPos));
                track.style.transform = 'translateX(-' + (current * (100 / perView)) + '%)';
                if (dotsEl) dotsEl.querySelectorAll('.testimonial-dot').forEach((d, i) =>
                    d.classList.toggle('is-active', i === current));
            }

            function startTimer() {
                if (!cfgSpeed) return;
                timer = setInterval(() => go(current >= maxPos ? 0 : current + 1), cfgSpeed);
            }
            function stopTimer() { clearInterval(timer); timer = null; }

            function init() {
                perView = getPerView();
                maxPos  = Math.max(0, total - perView);
                current = Math.min(current, maxPos);
                slides.forEach(s => { s.style.flex = '0 0 calc(100% / ' + perView + ')'; });
                if (controls) controls.style.display = maxPos > 0 ? '' : 'none';
                buildDots();
                go(current);
            }

            if (prevBtn) prevBtn.addEventListener('click', () => go(current - 1));
            if (nextBtn) nextBtn.addEventListener('click', () => go(current + 1));

            let startX = 0;
            track.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
            track.addEventListener('touchend',   e => {
                const dx = e.changedTouches[0].clientX - startX;
                if (Math.abs(dx) > 40) go(dx < 0 ? current + 1 : current - 1);
            });

            const slider = track.closest('.testimonial-slider');
            slider.addEventListener('mouseenter', stopTimer);
            slider.addEventListener('mouseleave', startTimer);

            window.addEventListener('resize', () => {
                track.style.transition = 'none';
                init();
                requestAnimationFrame(() => { track.style.transition = ''; });
            }, { passive: true });
            init();
            startTimer();

            track.querySelectorAll('.testimonial-card__text').forEach(el => {
                requestAnimationFrame(() => {
                    if (el.scrollHeight <= el.clientHeight + 2) return;
                    const btn = document.createElement('button');
                    btn.className = 'testimonial-card__more';
                    btn.textContent = '<?php echo esc_js(__('Weiterlesen', 'annyhase')); ?>';
                    btn.addEventListener('click', () => {
                        const expanded = el.classList.toggle('is-expanded');
                        btn.textContent = expanded
                            ? '<?php echo esc_js(__('Weniger anzeigen', 'annyhase')); ?>'
                            : '<?php echo esc_js(__('Weiterlesen', 'annyhase')); ?>';
                    });
                    el.insertAdjacentElement('afterend', btn);
                });
            });
        })();
        </script>
    </div>
</section>
