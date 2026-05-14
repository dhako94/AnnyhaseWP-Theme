<?php
/*
 * Template Name: Kontakt
 */
defined('ABSPATH') || exit;
get_header();

$email         = get_theme_mod('annyhase_contact_email',  get_option('admin_email'));
$etsy_url      = get_theme_mod('annyhase_etsy_shop_url',  'https://www.etsy.com/shop/Annyhase');
$instagram_url = get_theme_mod('annyhase_instagram_url',  'https://www.instagram.com/annyhase_official');
$ig_handle     = '@' . ltrim(rtrim(parse_url($instagram_url, PHP_URL_PATH), '/'), '/');
$products_url  = get_post_type_archive_link('produkt') ?: home_url('/#produkte');
$success_msg   = get_theme_mod('annyhase_contact_success_msg', 'Vielen Dank für deine Nachricht! Ich melde mich so schnell wie möglich bei dir.');
$kf_hero_title = get_theme_mod('annyhase_kf_hero_title', 'Schreib mir!');
$kf_hero_sub   = get_theme_mod('annyhase_kf_hero_sub',   'Fragen, Bestellwünsche oder einfach Hallo – ich freue mich über jede Nachricht.');
$kf_note_text  = get_theme_mod('annyhase_kf_note_text',  'Jede Nachricht wird von mir persönlich gelesen und beantwortet – ich freue mich wirklich über eure Nachrichten!');
$kf_note_sig   = get_theme_mod('annyhase_kf_note_sig',   '– Anny');
$kf_badge      = get_theme_mod('annyhase_kf_response_badge', 'Antwortet innerhalb von 1–2 Werktagen');
?>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<div class="kf-hero">
    <div class="container">
        <div class="kf-hero__inner reveal">
            <span class="section-label"><?php esc_html_e('Kontakt', 'annyhase'); ?></span>
            <h1 class="kf-hero__title"><?php echo esc_html($kf_hero_title); ?></h1>
            <p class="kf-hero__sub"><?php echo esc_html($kf_hero_sub); ?></p>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     INHALT
══════════════════════════════════════ -->
<section class="section">
    <div class="container">
        <div class="kf-layout reveal" style="transition-delay:.08s">

            <!-- ── Sidebar ── -->
            <aside class="kf-sidebar">

                <div class="kf-note">
                    <span class="kf-note__quote" aria-hidden="true">"</span>
                    <p class="kf-note__text"><?php echo esc_html($kf_note_text); ?></p>
                    <span class="kf-note__sig"><?php echo esc_html($kf_note_sig); ?></span>
                </div>

                <nav class="kf-contact-list" aria-label="<?php esc_attr_e('Kontaktwege', 'annyhase'); ?>">

                    <a href="mailto:<?php echo esc_attr($email); ?>" class="kf-contact-item">
                        <span class="kf-contact-item__icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        </span>
                        <span class="kf-contact-item__body">
                            <span class="kf-contact-item__label"><?php esc_html_e('E-Mail', 'annyhase'); ?></span>
                            <span class="kf-contact-item__value"><?php echo annyhase_obfuscate_email($email); ?></span>
                        </span>
                        <svg class="kf-contact-item__arrow" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>

                    <a href="<?php echo esc_url($etsy_url); ?>" target="_blank" rel="noopener noreferrer" class="kf-contact-item">
                        <span class="kf-contact-item__icon">
                            <?php echo wp_kses(annyhase_etsy_svg(18), ['svg' => ['xmlns' => [], 'width' => [], 'height' => [], 'viewbox' => [], 'fill' => [], 'aria-hidden' => []], 'path' => ['d' => []]]); ?>
                        </span>
                        <span class="kf-contact-item__body">
                            <span class="kf-contact-item__label"><?php esc_html_e('Etsy Shop', 'annyhase'); ?></span>
                            <span class="kf-contact-item__value"><?php echo esc_html(preg_replace('#^https?://(www\.)?#', '', rtrim($etsy_url, '/'))); ?></span>
                        </span>
                        <svg class="kf-contact-item__arrow" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>

                    <a href="<?php echo esc_url($instagram_url); ?>" target="_blank" rel="noopener noreferrer" class="kf-contact-item">
                        <span class="kf-contact-item__icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                        </span>
                        <span class="kf-contact-item__body">
                            <span class="kf-contact-item__label">Instagram</span>
                            <span class="kf-contact-item__value"><?php echo esc_html($ig_handle); ?></span>
                        </span>
                        <svg class="kf-contact-item__arrow" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>

                </nav>

                <div class="kf-response-badge">
                    <span class="kf-response-badge__dot"></span>
                    <?php echo esc_html($kf_badge); ?>
                </div>

            </aside>

            <!-- ── Form card ── -->
            <div class="kf-card">

                <!-- Success view (replaces the form) -->
                <div id="kf-success" class="kf-success" hidden role="status" aria-live="polite" aria-atomic="true">
                    <div class="kf-success__circle">
                        <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    </div>
                    <h3 class="kf-success__title"><?php esc_html_e('Nachricht gesendet!', 'annyhase'); ?></h3>
                    <p class="kf-success__msg" id="kf-success-text"><?php echo esc_html($success_msg); ?></p>
                    <p class="kf-success__spam"><?php esc_html_e('Du hast eine Bestätigungsmail erhalten – bitte prüfe ggf. auch deinen Spam-Ordner.', 'annyhase'); ?></p>
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn-outline">
                        ← <?php esc_html_e('Zurück zur Startseite', 'annyhase'); ?>
                    </a>
                </div>

                <!-- Form -->
                <form id="kf-form" class="kf-form" novalidate>

                    <!-- Honeypot (Anti-Spam) -->
                    <div class="kf-hp" aria-hidden="true" tabindex="-1">
                        <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <h3 class="kf-form__title"><?php esc_html_e('Deine Nachricht', 'annyhase'); ?></h3>

                    <!-- Error alert -->
                    <div id="kf-error" class="kf-alert kf-alert--error" hidden role="alert" aria-live="assertive" aria-atomic="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                        <span id="kf-error-msg"></span>
                    </div>

                    <!-- Subject chips -->
                    <div class="kf-field">
                        <span class="kf-label"><?php esc_html_e('Betreff', 'annyhase'); ?></span>
                        <div class="kf-chips" role="group" aria-label="<?php esc_attr_e('Betreff auswählen', 'annyhase'); ?>">
                            <input type="radio" name="subject" id="kf-s1" value="Bestellung" class="kf-chip-input">
                            <label for="kf-s1" class="kf-chip">🛍️ <?php esc_html_e('Bestellung', 'annyhase'); ?></label>

                            <input type="radio" name="subject" id="kf-s2" value="Sonderanfertigung" class="kf-chip-input">
                            <label for="kf-s2" class="kf-chip">✨ <?php esc_html_e('Sonderanfertigung', 'annyhase'); ?></label>

                            <input type="radio" name="subject" id="kf-s3" value="Fragen" class="kf-chip-input">
                            <label for="kf-s3" class="kf-chip">❓ <?php esc_html_e('Fragen', 'annyhase'); ?></label>

                            <input type="radio" name="subject" id="kf-s4" value="Sonstiges" class="kf-chip-input">
                            <label for="kf-s4" class="kf-chip">💬 <?php esc_html_e('Sonstiges', 'annyhase'); ?></label>
                        </div>
                    </div>

                    <!-- Name + email -->
                    <div class="kf-row">
                        <div class="kf-field">
                            <label class="kf-label" for="kf-name">
                                <?php esc_html_e('Name', 'annyhase'); ?> <span class="kf-req" aria-hidden="true">*</span>
                            </label>
                            <input class="kf-input" type="text" id="kf-name" name="name"
                                   placeholder="<?php esc_attr_e('Anna Muster', 'annyhase'); ?>"
                                   required autocomplete="name">
                        </div>
                        <div class="kf-field">
                            <label class="kf-label" for="kf-email">
                                <?php esc_html_e('E-Mail', 'annyhase'); ?> <span class="kf-req" aria-hidden="true">*</span>
                            </label>
                            <input class="kf-input" type="email" id="kf-email" name="email"
                                   placeholder="<?php esc_attr_e('anna@beispiel.de', 'annyhase'); ?>"
                                   required autocomplete="email">
                        </div>
                    </div>

                    <!-- Message -->
                    <div class="kf-field">
                        <label class="kf-label" for="kf-message">
                            <?php esc_html_e('Nachricht', 'annyhase'); ?> <span class="kf-req" aria-hidden="true">*</span>
                        </label>
                        <textarea class="kf-input kf-textarea" id="kf-message" name="message"
                                  placeholder="<?php esc_attr_e('Hallo Anny, ich würde gerne …', 'annyhase'); ?>"
                                  required rows="6"></textarea>
                    </div>

                    <!-- Privacy -->
                    <div class="kf-privacy">
                        <input type="checkbox" id="kf-privacy" name="privacy" required class="kf-privacy__check">
                        <label for="kf-privacy" class="kf-privacy__label">
                            <?php printf(
                                esc_html__('Ich habe die %s gelesen und stimme zu.', 'annyhase'),
                                '<a href="' . esc_url(home_url('/datenschutz')) . '" target="_blank">' . esc_html__('Datenschutzerklärung', 'annyhase') . '</a>'
                            ); ?>
                            <span class="kf-req" aria-hidden="true"> *</span>
                        </label>
                    </div>

                    <!-- Submit -->
                    <button type="submit" id="kf-submit" class="btn btn-primary kf-submit-btn">
                        <span id="kf-submit-text"><?php esc_html_e('Nachricht senden', 'annyhase'); ?></span>
                        <span id="kf-submit-loader" class="kf-loader" hidden aria-hidden="true"></span>
                        <svg id="kf-arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </button>

                </form>

            </div><!-- /kf-card -->

        </div><!-- /kf-layout -->
    </div>
</section>

<?php get_footer(); ?>
