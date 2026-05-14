<?php
/**
 * Annyhase Plugin Setup Notices
 *
 * Displays an admin notice recommending the WP Mail SMTP plugin for reliable
 * contact-form email delivery, with a one-click install / activate link.
 * The notice can be dismissed per-user and resets on theme re-activation.
 *
 * @package Annyhase
 */
defined('ABSPATH') || exit;

/* -------------------------------------------------------
   Recommended plugins – admin notice with one-click install
------------------------------------------------------- */

add_action('admin_notices', 'annyhase_plugin_notices');

/**
 * Entry point: checks which plugin notices should be displayed and calls
 * the individual notice functions.
 *
 * Only runs for users with the `install_plugins` capability.
 */
function annyhase_plugin_notices(): void {
    if (!current_user_can('install_plugins')) return;

    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    annyhase_notice_wp_mail_smtp();
}

/**
 * Renders the WP Mail SMTP recommendation notice.
 *
 * Shows a dismissible warning if the `smtp-mailer` plugin is not active.
 * Offers a nonce-secured install or activate action URL and an AJAX dismiss
 * link that stores the user's choice in `annyhase_dismiss_smtp_notice`.
 */
function annyhase_notice_wp_mail_smtp(): void {
    if (is_plugin_active('smtp-mailer/main.php')) return;
    if (get_option('annyhase_dismiss_smtp_notice')) return;

    $installed = file_exists(WP_PLUGIN_DIR . '/smtp-mailer/main.php');

    if ($installed) {
        $action_url  = wp_nonce_url(
            admin_url('plugins.php?action=activate&plugin=smtp-mailer%2Fmain.php'),
            'activate-plugin_smtp-mailer/main.php'
        );
        $action_text = 'Jetzt aktivieren';
    } else {
        $action_url  = wp_nonce_url(
            admin_url('update.php?action=install-plugin&plugin=smtp-mailer'),
            'install-plugin_smtp-mailer'
        );
        $action_text = 'Jetzt installieren';
    }

    $dismiss_nonce = wp_create_nonce('annyhase_dismiss_smtp');
    ?>
    <div class="notice notice-warning annyhase-notice" id="annyhase-smtp-notice" style="display:flex;align-items:center;gap:1rem;padding:.85rem 1.25rem">
        <span style="font-size:1.4rem;line-height:1" aria-hidden="true">✉️</span>
        <div style="flex:1">
            <strong>Annyhase Theme</strong> — Das Plugin <strong>SMTP Mailer</strong> wird benötigt,
            damit das Kontaktformular zuverlässig E-Mails verschickt.
            <a href="<?php echo esc_url($action_url); ?>" class="button button-primary" style="margin-left:.75rem"><?php echo esc_html($action_text); ?></a>
            <a href="#" class="annyhase-notice-dismiss" data-nonce="<?php echo esc_attr($dismiss_nonce); ?>" style="margin-left:.5rem;font-size:.85em;color:#666">Hinweis ausblenden</a>
        </div>
    </div>
    <script>
    document.querySelector('.annyhase-notice-dismiss')?.addEventListener('click', function(e) {
        e.preventDefault();
        fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=annyhase_dismiss_smtp_notice&nonce=' + this.dataset.nonce
        }).then(function() {
            document.getElementById('annyhase-smtp-notice')?.remove();
        });
    });
    </script>
    <?php
}

add_action('wp_ajax_annyhase_dismiss_smtp_notice', 'annyhase_dismiss_smtp_notice');

/**
 * AJAX handler that persists the "dismiss SMTP notice" preference.
 *
 * Verifies the `annyhase_dismiss_smtp` nonce and requires `manage_options`
 * capability before writing the option, to prevent privilege escalation.
 */
function annyhase_dismiss_smtp_notice(): void {
    check_ajax_referer('annyhase_dismiss_smtp', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }
    update_option('annyhase_dismiss_smtp_notice', true);
    wp_die();
}

/* Hinweis zurücksetzen wenn Theme neu aktiviert wird */
add_action('after_switch_theme', function (): void {
    delete_option('annyhase_dismiss_smtp_notice');
});
