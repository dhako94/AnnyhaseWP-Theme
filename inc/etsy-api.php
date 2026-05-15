<?php
/**
 * Etsy API Integration
 *
 * Connects to the Etsy API v3 to:
 *  - Fetch and cache shop statistics (sales, rating, reviews)
 *  - Sync product listings as 'produkt' posts with images, videos and categories
 *  - Import shop reviews as 'bewertung' posts
 *
 * Background tasks run via WordPress cron at a configurable interval.
 * Media imports use AJAX batching (1 product/request) to avoid PHP timeouts.
 */
defined('ABSPATH') || exit;

/* =================================================================
   SETTINGS REGISTRATION + ADMIN MENU
   ================================================================= */

add_filter('cron_schedules', function (array $schedules): array {
    $schedules['every_minute'] = ['interval' => 60, 'display' => 'Every minute'];
    return $schedules;
});

add_action('admin_menu', function () {
    add_menu_page(
        'Etsy Shop Sync',
        'Etsy Shop Sync',
        'manage_options',
        'etsy-shop-sync',
        'etsy_sync_admin_page',
        'dashicons-store',
        7
    );
});

add_action('admin_init', function () {
    register_setting('etsy_sync_group', 'etsy_sync_keystring',     ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('etsy_sync_group', 'etsy_sync_secret',        ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('etsy_sync_group', 'etsy_sync_shop_name', [
        'sanitize_callback' => function (string $v): string {
            $v = sanitize_text_field($v);
            if (strpos($v, 'etsy.com/shop/') !== false) {
                $v = trim(basename((string) parse_url($v, PHP_URL_PATH)), '/');
            }
            return $v;
        },
        'default' => '',
    ]);
    register_setting('etsy_sync_group', 'etsy_sync_shop_id',       ['sanitize_callback' => 'absint']);
    register_setting('etsy_sync_group', 'etsy_sync_interval', ['sanitize_callback' => 'sanitize_text_field', 'default' => 'daily']);
});

/* Clear cached stats when credentials or shop ID change */
foreach (['etsy_sync_keystring', 'etsy_sync_secret', 'etsy_sync_shop_name', 'etsy_sync_shop_id'] as $_opt) {
    add_action("update_option_{$_opt}", 'etsy_sync_clear_cache');
}

/* Reschedule the cron job when the sync interval is changed and saved */
add_action('update_option_etsy_sync_interval', function ($old_val, $new_val): void {
    wp_clear_scheduled_hook('etsy_sync_daily_refresh');
    wp_schedule_event(time(), $new_val, 'etsy_sync_daily_refresh');
}, 10, 2);

function etsy_sync_clear_cache(): void {
    delete_transient('etsy_sync_stats');
}

/* =================================================================
   ADMIN PAGE
   ================================================================= */

function etsy_sync_admin_page(): void {

    $stats       = get_transient('etsy_sync_stats');
    $last_update = get_option('etsy_sync_last_updated', 0);
    $last_error  = get_option('etsy_sync_last_error', '');
    $keystring   = get_option('etsy_sync_keystring', '');
    $secret      = get_option('etsy_sync_secret', '');
    $shop_name   = get_option('etsy_sync_shop_name', '');
    $shop_id     = (int) get_option('etsy_sync_shop_id', 0);
    $interval    = get_option('etsy_sync_interval', 'daily');
    $rev_status  = etsy_sync_get_review_sync_status();
    $prod_status = etsy_sync_get_product_sync_status();
    $next_cron    = wp_next_scheduled('etsy_sync_daily_refresh');
    $next_catchup = wp_next_scheduled('etsy_sync_media_catchup');
    $pending_media = (int) count(get_posts([
        'post_type'      => 'produkt',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => '_etsy_listing_id', 'compare' => 'EXISTS'],
            ['key' => '_thumbnail_id',    'compare' => 'NOT EXISTS'],
        ],
    ]));

    $interval_labels = [
        'every_minute' => 'Every minute',
        'hourly'       => 'Every hour',
        'twicedaily'   => 'Twice daily',
        'daily'        => 'Once daily',
        'weekly'       => 'Once a week',
    ];

    /* Status dot colours derived from last known state */
    $dot_stats = $last_error       ? '#ef4444' : ($last_update             ? '#22c55e' : '#d1d5db');
    $dot_rev   = $rev_status['last_error']  ? '#ef4444' : ($rev_status['last_sync']  ? '#22c55e' : '#d1d5db');
    $dot_prod  = $prod_status['last_error'] ? '#ef4444' : ($prod_status['last_sync'] ? '#22c55e' : '#d1d5db');
    ?>
    <div class="wrap">
    <h1>Etsy Shop Sync</h1>
    <p style="color:#666;margin-top:-.5rem">Connects your Etsy shop – keeps stats, products and reviews in sync automatically.</p>

    <?php settings_errors('etsy_sync_group'); ?>

    <!-- Sync All banner -->
    <div style="background:#f0f6fc;border:1px solid #b8d4ea;border-radius:8px;padding:.85rem 1.25rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
        <div style="flex:1;min-width:180px">
            <strong style="font-size:.95rem">Sync Everything</strong>
            <p style="margin:.15rem 0 0;font-size:.82rem;color:#555">Shop Info → Reviews → Products in one go.</p>
        </div>
        <span id="all-status" style="font-size:.82rem;color:#444;font-weight:600;min-width:140px;text-align:right"></span>
        <button id="all-btn" class="button button-primary">↺ Sync All</button>
    </div>

    <!-- 3 sync cards -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.25rem;margin-bottom:1.5rem">

        <!-- Card: Shop Info Sync -->
        <div style="background:#fff;border:1px solid #e2e4e7;border-radius:8px;padding:1.5rem;display:flex;flex-direction:column">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
                <h2 style="margin:0;font-size:1.05rem;font-weight:700">Shop Info Sync</h2>
                <span id="dot-stats" style="width:9px;height:9px;border-radius:50%;flex-shrink:0;background:<?php echo esc_attr($dot_stats); ?>"></span>
            </div>
            <?php if ($last_error): ?>
            <div style="background:#fef2f2;border:1px solid #f87171;border-radius:5px;padding:.5rem .75rem;margin-bottom:.65rem;font-size:.79rem;word-break:break-all">
                <strong style="color:#b91c1c">Last error:</strong>
                <code style="color:#991b1b;display:block;margin-top:2px"><?php echo esc_html($last_error); ?></code>
            </div>
            <?php endif; ?>
            <?php if ($stats && $stats['source'] !== 'api'): ?>
            <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:5px;padding:.45rem .75rem;margin-bottom:.65rem;font-size:.79rem;color:#92400e">
                Manual fallback values active.
            </div>
            <?php endif; ?>
            <table style="width:100%;border-collapse:collapse;font-size:.84rem;margin-bottom:.75rem">
                <tr style="border-bottom:1px solid #f0f0f0">
                    <td style="padding:.4rem 0;color:#666">Imported shop info</td>
                    <td style="padding:.4rem 0;text-align:right;font-weight:700;font-size:.95rem;white-space:nowrap">
                        <span id="tile-sales"><?php echo esc_html($stats ? $stats['sales'] : '—'); ?></span> sales &nbsp;·&nbsp; <span style="color:#f59e0b">★</span>&thinsp;<span id="tile-rating"><?php echo esc_html($stats ? $stats['rating'] : '—'); ?></span> &nbsp;·&nbsp; <span id="tile-reviews"><?php echo esc_html($stats ? $stats['reviews'] : '—'); ?></span> reviews
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f0">
                    <td style="padding:.4rem 0;color:#666">Last sync</td>
                    <td style="padding:.4rem 0;text-align:right;font-weight:600">
                        <?php echo $last_update ? esc_html(date_i18n('d.m.Y H:i', $last_update)) : '<span style="color:#999;font-weight:400">never</span>'; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:.4rem 0;color:#666">Next auto-sync</td>
                    <td style="padding:.4rem 0;text-align:right">
                        <?php if ($next_cron): ?>
                        <strong><?php echo esc_html(date_i18n('d.m.Y H:i', $next_cron)); ?></strong>
                        <span style="color:#999;font-size:.79rem"> (in <?php echo round(($next_cron - time()) / 3600, 1); ?> h)</span>
                        <?php else: ?>
                        <span style="color:#856404">No cron — reload.</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <p style="font-size:.8rem;color:#666;margin:0 0 .8rem;flex-grow:1">Syncs shop stats automatically. Use the button for an immediate update.</p>
            <div id="prog-stats" style="display:none;margin-bottom:.65rem">
                <div style="background:#e5e7eb;border-radius:4px;height:6px;overflow:hidden">
                    <div id="fill-stats" style="background:#2e7d32;height:100%;width:0%;transition:width .35s;border-radius:4px"></div>
                </div>
                <p id="phase-stats" style="font-size:.78rem;font-weight:600;color:#333;margin:.3rem 0 1px"></p>
                <p id="msg-stats"   style="font-size:.76rem;color:#666;margin:0"></p>
            </div>
            <button id="btn-stats" class="button button-primary" style="width:100%">Refresh from Etsy</button>
        </div>

        <!-- Card: Reviews Sync -->
        <div style="background:#fff;border:1px solid #e2e4e7;border-radius:8px;padding:1.5rem;display:flex;flex-direction:column">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
                <h2 style="margin:0;font-size:1.05rem;font-weight:700">Reviews Sync</h2>
                <span id="dot-rev" style="width:9px;height:9px;border-radius:50%;flex-shrink:0;background:<?php echo esc_attr($dot_rev); ?>"></span>
            </div>
            <?php if ($rev_status['last_error']): ?>
            <div style="background:#fef2f2;border:1px solid #f87171;border-radius:5px;padding:.5rem .75rem;margin-bottom:.65rem;font-size:.79rem;word-break:break-all">
                <strong style="color:#b91c1c">Last error:</strong>
                <code style="color:#991b1b;display:block;margin-top:2px"><?php echo esc_html($rev_status['last_error']); ?></code>
            </div>
            <?php endif; ?>
            <table style="width:100%;border-collapse:collapse;font-size:.84rem;margin-bottom:.75rem">
                <tr style="border-bottom:1px solid #f0f0f0">
                    <td style="padding:.4rem 0;color:#666">Imported reviews</td>
                    <td style="padding:.4rem 0;text-align:right;font-weight:700;font-size:.95rem"><?php echo esc_html($rev_status['total']); ?></td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f0">
                    <td style="padding:.4rem 0;color:#666">Last sync</td>
                    <td style="padding:.4rem 0;text-align:right;font-weight:600">
                        <?php echo $rev_status['last_sync'] ? esc_html(date_i18n('d.m.Y H:i', $rev_status['last_sync'])) : '<span style="color:#999;font-weight:400">never</span>'; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:.4rem 0;color:#666">Next auto-sync</td>
                    <td style="padding:.4rem 0;text-align:right">
                        <?php if ($next_cron): ?>
                        <strong><?php echo esc_html(date_i18n('d.m.Y H:i', $next_cron)); ?></strong>
                        <span style="color:#999;font-size:.79rem"> (in <?php echo round(($next_cron - time()) / 3600, 1); ?> h)</span>
                        <?php else: ?>
                        <span style="color:#856404">No cron — reload.</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <p style="font-size:.8rem;color:#666;margin:0 0 .8rem;flex-grow:1">Only reviews rated ★★★★ or higher are imported. Duplicates are skipped.</p>
            <div id="prog-rev" style="display:none;margin-bottom:.65rem">
                <div style="background:#e5e7eb;border-radius:4px;height:6px;overflow:hidden">
                    <div id="fill-rev" style="background:#2e7d32;height:100%;width:0%;transition:width .35s;border-radius:4px"></div>
                </div>
                <p id="phase-rev" style="font-size:.78rem;font-weight:600;color:#333;margin:.3rem 0 1px"></p>
                <p id="msg-rev"   style="font-size:.76rem;color:#666;margin:0"></p>
            </div>
            <button id="btn-rev" class="button button-primary" style="width:100%">Import new reviews</button>
        </div>

        <!-- Card: Product Sync -->
        <div style="background:#fff;border:1px solid #e2e4e7;border-radius:8px;padding:1.5rem;display:flex;flex-direction:column">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
                <h2 style="margin:0;font-size:1.05rem;font-weight:700">Product Sync</h2>
                <span id="dot-prod" style="width:9px;height:9px;border-radius:50%;flex-shrink:0;background:<?php echo esc_attr($dot_prod); ?>"></span>
            </div>
            <?php if ($prod_status['last_error']): ?>
            <div style="background:#fef2f2;border:1px solid #f87171;border-radius:5px;padding:.5rem .75rem;margin-bottom:.65rem;font-size:.79rem;word-break:break-all">
                <strong style="color:#b91c1c">Last error:</strong>
                <code style="color:#991b1b;display:block;margin-top:2px"><?php echo esc_html($prod_status['last_error']); ?></code>
            </div>
            <?php endif; ?>
            <?php $media_err = get_option('etsy_sync_products_media_last_error', ''); if ($media_err): ?>
            <div style="background:#fef2f2;border:1px solid #f87171;border-radius:5px;padding:.5rem .75rem;margin-bottom:.65rem;font-size:.79rem;word-break:break-all">
                <strong style="color:#b91c1c">Last media error:</strong>
                <code style="color:#991b1b;display:block;margin-top:2px"><?php echo esc_html($media_err); ?></code>
            </div>
            <?php endif; ?>
            <table style="width:100%;border-collapse:collapse;font-size:.84rem;margin-bottom:.75rem">
                <tr style="border-bottom:1px solid #f0f0f0">
                    <td style="padding:.4rem 0;color:#666">Imported products</td>
                    <td style="padding:.4rem 0;text-align:right;font-weight:700;font-size:.95rem"><?php echo esc_html($prod_status['total']); ?></td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f0">
                    <td style="padding:.4rem 0;color:#666">Last sync</td>
                    <td style="padding:.4rem 0;text-align:right;font-weight:600">
                        <?php echo $prod_status['last_sync'] ? esc_html(date_i18n('d.m.Y H:i', $prod_status['last_sync'])) : '<span style="color:#999;font-weight:400">never</span>'; ?>
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f0">
                    <td style="padding:.4rem 0;color:#666">Next auto-sync</td>
                    <td style="padding:.4rem 0;text-align:right">
                        <?php if ($next_cron): ?>
                        <strong><?php echo esc_html(date_i18n('d.m.Y H:i', $next_cron)); ?></strong>
                        <span style="color:#999;font-size:.79rem"> (in <?php echo round(($next_cron - time()) / 3600, 1); ?> h)</span>
                        <?php else: ?>
                        <span style="color:#856404">No cron — reload.</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:.4rem 0;color:#666">Media catch-up</td>
                    <td style="padding:.4rem 0;text-align:right;font-size:.79rem">
                        <?php if ($next_catchup): ?>
                        <span style="display:inline-flex;align-items:center;gap:5px">
                            <span style="width:7px;height:7px;border-radius:50%;background:#f59e0b;display:inline-block;animation:esync-pulse 1.2s ease-in-out infinite"></span>
                            <strong style="color:#92400e"><?php echo esc_html($pending_media); ?> products</strong>
                            <span style="color:#999">— in <?php echo max(1, (int) round(($next_catchup - time()) / 60)); ?> min</span>
                        </span>
                        <?php elseif ($pending_media > 0): ?>
                        <span style="color:#856404"><?php echo esc_html($pending_media); ?> without image</span>
                        <?php else: ?>
                        <span style="color:#22c55e;font-weight:600">✓ All media imported</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <style>@keyframes esync-pulse{0%,100%{opacity:1}50%{opacity:.3}}</style>
            <p style="font-size:.8rem;color:#666;margin:0 0 .8rem;flex-grow:1">Syncs all active Etsy listings including title, price and images. Inactive listings are automatically deactivated.</p>
            <div id="prog-prod" style="display:none;margin-bottom:.65rem">
                <div style="background:#e5e7eb;border-radius:4px;height:6px;overflow:hidden">
                    <div id="fill-prod" style="background:#2e7d32;height:100%;width:0%;transition:width .35s;border-radius:4px"></div>
                </div>
                <p id="phase-prod" style="font-size:.78rem;font-weight:600;color:#333;margin:.3rem 0 1px"></p>
                <p id="msg-prod"   style="font-size:.76rem;color:#666;margin:0"></p>
            </div>
            <button id="btn-prod" class="button button-primary" style="width:100%">Sync products</button>
        </div>

    </div>

    <!-- API credentials + setup guide -->
    <div style="display:grid;grid-template-columns:1fr 280px;gap:1.25rem;align-items:start">

        <div style="background:#fff;border:1px solid #e2e4e7;border-radius:8px;padding:1.25rem">
            <h3 style="margin-top:0;font-size:.95rem;font-weight:700;color:#444">API Credentials</h3>
            <form method="post" action="options.php">
                <?php settings_fields('etsy_sync_group'); ?>
                <table class="form-table" style="margin-top:0">
                    <tr>
                        <th style="width:150px;font-size:.85rem"><label for="ks">Keystring (API Key)</label></th>
                        <td>
                            <input type="text" id="ks" name="etsy_sync_keystring"
                                   value="<?php echo esc_attr($keystring); ?>"
                                   class="regular-text" placeholder="e.g. a1b2c3d4e5f6…">
                            <p class="description">Find it at <a href="https://www.etsy.com/developers/your-apps" target="_blank">etsy.com/developers/your-apps</a> → your app → copy <strong>Keystring</strong>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th style="font-size:.85rem"><label for="sec">Shared Secret</label></th>
                        <td>
                            <div style="display:flex;align-items:center;gap:.5rem">
                                <input type="password" id="sec" name="etsy_sync_secret"
                                       value="<?php echo esc_attr($secret); ?>"
                                       class="regular-text" placeholder="your shared secret"
                                       autocomplete="new-password">
                                <button type="button" style="background:#f6f7f7;border:1px solid #ccc;border-radius:4px;padding:.4rem .7rem;cursor:pointer;color:#555;font-size:.8rem"
                                        onclick="var f=document.getElementById('sec');var s=f.type==='password';f.type=s?'text':'password';this.textContent=s?'Hide':'Show';">Show</button>
                            </div>
                            <p class="description">Used together with the Keystring (<code>keystring:secret</code>).</p>
                        </td>
                    </tr>
                    <tr>
                        <th style="font-size:.85rem"><label for="sn">Shop Name</label></th>
                        <td>
                            <input type="text" id="sn" name="etsy_sync_shop_name"
                                   value="<?php echo esc_attr($shop_name); ?>"
                                   class="regular-text" placeholder="YourShopName">
                            <p class="description">Exactly as in the URL: etsy.com/shop/<strong><?php echo esc_html($shop_name ?: 'YourShopName'); ?></strong></p>
                        </td>
                    </tr>
                    <tr>
                        <th style="font-size:.85rem"><label for="sid">Shop ID <small style="font-weight:400">(numeric)</small></label></th>
                        <td>
                            <input type="number" id="sid" name="etsy_sync_shop_id"
                                   value="<?php echo esc_attr($shop_id ?: ''); ?>"
                                   class="regular-text" placeholder="auto-detected on first sync">
                            <?php if ($shop_id): ?>
                            <span style="color:#2e7d32;font-size:.85rem">&#10003; Auto-detected: <strong><?php echo esc_html($shop_id); ?></strong></span>
                            <?php else: ?>
                            <p class="description">Set automatically on first sync. If it fails: view page source → search for <code>shop_id</code>.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th style="font-size:.85rem"><label for="sync-int">Auto-Sync Interval</label></th>
                        <td>
                            <select id="sync-int" name="etsy_sync_interval">
                                <?php foreach ($interval_labels as $val => $label): ?>
                                <option value="<?php echo esc_attr($val); ?>"<?php selected($interval, $val); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">How often WordPress automatically syncs stats, reviews and products in the background.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings', 'secondary', 'submit', false); ?>
            </form>
        </div>

        <div style="background:#f0f6fc;border:1px solid #b8d4ea;border-radius:8px;padding:1.25rem">
            <h3 style="margin-top:0;font-size:.95rem;font-weight:700;color:#444">How to get an API Key</h3>
            <ol style="font-size:.83rem;color:#444;margin:0;padding-left:1.25rem;line-height:1.9">
                <li>Sign in at <a href="https://www.etsy.com/developers/your-apps" target="_blank">etsy.com/developers/your-apps</a></li>
                <li>Click "Create a new app"</li>
                <li>Fill in app name &amp; description</li>
                <li>Copy <strong>Keystring</strong> and <strong>Shared Secret</strong> → paste left</li>
                <li>Save → click "Refresh from Etsy"</li>
            </ol>
        </div>

    </div>

    </div>

    <script>
    (function () {
        var AJAX = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        var NS   = '<?php echo esc_js(wp_create_nonce('etsy_sync_stats_now')); ?>';
        var NR   = '<?php echo esc_js(wp_create_nonce('etsy_sync_reviews_now')); ?>';
        var N1   = '<?php echo esc_js(wp_create_nonce('etsy_sync_products_batch')); ?>';
        var N2   = '<?php echo esc_js(wp_create_nonce('etsy_sync_media_batch')); ?>';

        function post(action, nonce, extra) {
            var fd = new FormData();
            fd.append('action', action);
            fd.append('nonce',  nonce);
            for (var k in extra) fd.append(k, extra[k]);
            return fetch(AJAX, { method: 'POST', body: fd }).then(function (r) { return r.json(); });
        }
        function el(id)            { return document.getElementById(id); }
        function dot(id, c)        { if (el(id)) el(id).style.background = c; }
        function dis(id, v)        { if (el(id)) el(id).disabled = v; }
        function showProg(p)       { if (el('prog-' + p)) el('prog-' + p).style.display = ''; }
        function bar(p, pct, ph, msg, col) {
            var f = el('fill-'  + p), ph2 = el('phase-' + p), m = el('msg-' + p);
            if (f)   { f.style.width = pct + '%'; if (col) f.style.background = col; }
            if (ph2) ph2.textContent = ph;
            if (m)   m.textContent   = msg;
        }

        /* ---- STATS ---- */
        if (el('btn-stats')) el('btn-stats').addEventListener('click', function () { runStats(null); });
        function runStats(done) {
            dis('btn-stats', true); dot('dot-stats', '#f59e0b'); showProg('stats');
            bar('stats', 35, 'Fetching shop data…', '', '#2e7d32');
            post('etsy_sync_stats_now', NS, {}).then(function (res) {
                if (!res.success) {
                    bar('stats', 100, 'Error', res.data && res.data.msg || 'Request failed.', '#dc2626');
                    dot('dot-stats', '#ef4444'); dis('btn-stats', false);
                    if (done) done(false); return;
                }
                var d = res.data;
                bar('stats', 100, 'Done!', d.sales + ' sales · ★ ' + d.rating, '#2e7d32');
                dot('dot-stats', '#22c55e');
                if (el('tile-sales'))   el('tile-sales').textContent   = d.sales;
                if (el('tile-rating'))  el('tile-rating').textContent  = d.rating;
                if (el('tile-reviews')) el('tile-reviews').textContent = d.reviews;
                dis('btn-stats', false);
                if (done) { done(true); return; }
                setTimeout(function () { location.reload(); }, 1800);
            }).catch(function () {
                bar('stats', 100, 'Error', 'Network error.', '#dc2626');
                dot('dot-stats', '#ef4444'); dis('btn-stats', false);
                if (done) done(false);
            });
        }

        /* ---- REVIEWS ---- */
        if (el('btn-rev')) el('btn-rev').addEventListener('click', function () { runReviews(null); });
        function runReviews(done) {
            dis('btn-rev', true); dot('dot-rev', '#f59e0b'); showProg('rev');
            bar('rev', 35, 'Importing reviews…', '', '#2e7d32');
            post('etsy_sync_reviews_now', NR, {}).then(function (res) {
                if (!res.success) {
                    bar('rev', 100, 'Error', res.data && res.data.msg || 'Request failed.', '#dc2626');
                    dot('dot-rev', '#ef4444'); dis('btn-rev', false);
                    if (done) done(false); return;
                }
                var n = res.data.imported;
                bar('rev', 100, 'Done!', n > 0 ? n + ' new review(s) imported.' : 'No new reviews found.', '#2e7d32');
                dot('dot-rev', '#22c55e'); dis('btn-rev', false);
                if (done) { done(true); return; }
                setTimeout(function () { location.reload(); }, 1800);
            }).catch(function () {
                bar('rev', 100, 'Error', 'Network error.', '#dc2626');
                dot('dot-rev', '#ef4444'); dis('btn-rev', false);
                if (done) done(false);
            });
        }

        /* ---- PRODUCTS ---- */
        var pst = { new: 0, upd: 0, deact: 0, imgOk: 0, imgErr: 0 };
        if (el('btn-prod')) el('btn-prod').addEventListener('click', function () { runProdFull(null); });
        function runProdFull(done) {
            dis('btn-prod', true); dot('dot-prod', '#f59e0b'); showProg('prod');
            pst = { new: 0, upd: 0, deact: 0, imgOk: 0, imgErr: 0 };
            bar('prod', 0, 'Phase 1: Fetching listings…', '', '#2e7d32');
            runProducts(0, done);
        }
        function runProducts(offset, done) {
            post('etsy_sync_products_batch', N1, {
                offset:          offset,
                update_existing: '1',
            }).then(function (res) {
                if (!res.success) {
                    bar('prod', 100, 'Error', res.data && res.data.msg || 'Error', '#dc2626');
                    dot('dot-prod', '#ef4444'); dis('btn-prod', false);
                    if (done) done(false); return;
                }
                var d = res.data;
                pst.new  += d.new     || 0;
                pst.upd  += d.updated || 0;
                pst.deact = d.deactivated || 0;
                var pct = d.total > 0 ? Math.min(49, Math.round(d.next_offset / d.total * 50)) : 50;
                bar('prod', pct, 'Phase 1: Fetching listings…',
                    d.next_offset + ' / ' + d.total + ' — ' + pst.new + ' new, ' + pst.upd + ' updated', '');
                if (d.done) {
                    bar('prod', 50, 'Phase 2: Importing images…', '', '');
                    runMedia(0, true, done);
                } else {
                    setTimeout(function () { runProducts(d.next_offset, done); }, 200);
                }
            }).catch(function (e) {
                bar('prod', 100, 'Error', 'Network error: ' + e, '#dc2626');
                dot('dot-prod', '#ef4444'); dis('btn-prod', false);
                if (done) done(false);
            });
        }
        function runMedia(offset, init, done) {
            post('etsy_sync_media_batch', N2, {
                offset: offset,
                init:   init ? '1' : '0',
            }).then(function (res) {
                if (!res.success) {
                    bar('prod', 100, 'Error', res.data && res.data.msg || 'Error', '#dc2626');
                    dot('dot-prod', '#ef4444'); dis('btn-prod', false);
                    if (done) done(false); return;
                }
                var d = res.data;
                pst.imgOk  += d.ok  || 0;
                pst.imgErr += d.err || 0;
                var pct = d.total > 0 ? Math.min(100, 50 + Math.round(d.processed / d.total * 50)) : 100;
                bar('prod', pct, 'Phase 2: Importing images…',
                    d.processed + ' / ' + d.total + ' products — ' + pst.imgOk + ' images', '');
                if (d.done) {
                    var s = pst.new + ' new, ' + pst.upd + ' updated'
                        + (pst.deact > 0 ? ', ' + pst.deact + ' deactivated' : '')
                        + ', ' + pst.imgOk + ' images'
                        + (pst.imgErr > 0 ? ', ' + pst.imgErr + ' errors' : '') + '.';
                    bar('prod', 100, 'Done!', s, '#2e7d32');
                    dot('dot-prod', '#22c55e'); dis('btn-prod', false);
                    if (done) { done(true); return; }
                    setTimeout(function () { location.reload(); }, 2500);
                } else {
                    setTimeout(function () { runMedia(d.processed, false, done); }, 300);
                }
            }).catch(function (e) {
                bar('prod', 100, 'Error', 'Network error: ' + e, '#dc2626');
                dot('dot-prod', '#ef4444'); dis('btn-prod', false);
                if (done) done(false);
            });
        }

        /* ---- SYNC ALL ---- */
        if (el('all-btn')) el('all-btn').addEventListener('click', function () {
            dis('all-btn', true); dis('btn-stats', true); dis('btn-rev', true); dis('btn-prod', true);
            function upd(t) { if (el('all-status')) el('all-status').textContent = t; }
            upd('1/3 — Shop Info…');
            runStats(function () {
                upd('2/3 — Reviews…');
                runReviews(function () {
                    upd('3/3 — Products…');
                    runProdFull(function () {
                        upd('All done!');
                        dis('all-btn', false);
                        setTimeout(function () { location.reload(); }, 2500);
                    });
                });
            });
        });
    })();
    </script>
    <?php
}

/* =================================================================
   WORDPRESS CRON – background auto-sync
   ================================================================= */

add_action('init', function () {
    /* Schedule the cron event if not already scheduled; uses the saved interval */
    if (!wp_next_scheduled('etsy_sync_daily_refresh')) {
        $interval = get_option('etsy_sync_interval', 'daily');
        wp_schedule_event(time(), $interval, 'etsy_sync_daily_refresh');
    }
});

/* Full background sync: stats → reviews → products, then schedule media catch-up */
add_action('etsy_sync_daily_refresh', function () {
    etsy_sync_clear_cache();
    etsy_sync_fetch_stats();
    etsy_sync_reviews();
    etsy_sync_products(true);
    if (!wp_next_scheduled('etsy_sync_media_catchup')) {
        wp_schedule_single_event(time() + 30, 'etsy_sync_media_catchup');
    }
});

/*
 * Media catch-up: processes up to 5 products per run (one API call each),
 * then reschedules itself until all products have a featured image.
 */
add_action('etsy_sync_media_catchup', function () {
    if (!function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }
    if (function_exists('set_time_limit')) set_time_limit(120);

    $api_key = etsy_sync_get_api_key();
    if (!$api_key) return;

    $no_thumb_query = [
        'post_type'   => 'produkt',
        'post_status' => 'any',
        'fields'      => 'ids',
        'meta_query'  => [
            'relation' => 'AND',
            ['key' => '_etsy_listing_id', 'compare' => 'EXISTS'],
            ['key' => '_thumbnail_id',    'compare' => 'NOT EXISTS'],
        ],
    ];

    $ids = get_posts(array_merge($no_thumb_query, ['posts_per_page' => 5]));

    foreach ($ids as $post_id) {
        $listing_id = (int) get_post_meta($post_id, '_etsy_listing_id', true);
        if (!$listing_id) continue;

        $res = etsy_sync_request(
            'https://openapi.etsy.com/v3/application/listings/' . $listing_id
            . '?includes%5B%5D=Images&includes%5B%5D=Videos',
            $api_key
        );

        if (is_wp_error($res) || (int) wp_remote_retrieve_response_code($res) !== 200) continue;

        $listing = json_decode(wp_remote_retrieve_body($res), true) ?? [];
        if (!empty($listing)) {
            etsy_sync_import_listing_media($post_id, $listing, false);
        }
    }

    /* If products without thumbnails still exist, run again in 60 seconds */
    $remaining = get_posts(array_merge($no_thumb_query, ['posts_per_page' => 1]));
    if (!empty($remaining)) {
        wp_schedule_single_event(time() + 60, 'etsy_sync_media_catchup');
    }
});

/* Remove cron events when the theme is switched away */
add_action('switch_theme', function () {
    wp_clear_scheduled_hook('etsy_sync_daily_refresh');
    wp_clear_scheduled_hook('etsy_sync_media_catchup');
});

/* =================================================================
   API REQUEST HELPER
   ================================================================= */

/**
 * Makes a GET request to the Etsy API v3.
 * The API key is passed in the x-api-key header as "keystring:secret".
 *
 * @return array|WP_Error WP HTTP response or WP_Error
 */
function etsy_sync_request(string $url, string $api_key) {
    return wp_remote_get($url, [
        'headers' => ['x-api-key' => $api_key, 'Accept' => 'application/json'],
        'timeout' => 10,
    ]);
}

/* =================================================================
   SHOP STATS
   ================================================================= */

/**
 * Fetches shop statistics (sales, rating, reviews) from the Etsy API.
 * Results are cached as a transient for 24 hours.
 * On failure, returns manual fallback values from theme customizer.
 */
function etsy_sync_fetch_stats(): array {
    $keystring = get_option('etsy_sync_keystring', '');
    $secret    = get_option('etsy_sync_secret', '');
    $shop_name = get_option('etsy_sync_shop_name', '');
    if ($shop_name && strpos($shop_name, 'etsy.com/shop/') !== false) {
        $shop_name = trim(basename((string) parse_url($shop_name, PHP_URL_PATH)), '/');
    }
    $shop_id   = (int) get_option('etsy_sync_shop_id', 0);

    $fallback = [
        'sales'   => get_theme_mod('annyhase_hero_sales',  '150+'),
        'rating'  => str_replace(',', '.', get_theme_mod('annyhase_hero_rating', '5,0')),
        'reviews' => '',
        'source'  => 'manual',
    ];

    if (!$keystring) {
        update_option('etsy_sync_last_error', 'No API key entered.');
        return $fallback;
    }

    $api_key = $secret ? $keystring . ':' . $secret : $keystring;

    /* Resolve numeric shop ID via findShops — runs once, then auto-saved */
    if (!$shop_id && $shop_name) {
        $res = etsy_sync_request(
            'https://openapi.etsy.com/v3/application/shops?shop_name=' . rawurlencode($shop_name) . '&limit=25',
            $api_key
        );
        if (!is_wp_error($res) && (int) wp_remote_retrieve_response_code($res) === 200) {
            $found = json_decode(wp_remote_retrieve_body($res), true);
            foreach ($found['results'] ?? [] as $shop) {
                if (strcasecmp($shop['shop_name'], $shop_name) === 0) {
                    $shop_id = (int) $shop['shop_id'];
                    update_option('etsy_sync_shop_id', $shop_id);
                    $stats = [
                        'sales'   => number_format((int)   ($shop['transaction_sold_count'] ?? 0), 0, ',', '.'),
                        'rating'  => number_format((float) ($shop['review_average']         ?? 5.0), 1, ',', ''),
                        'reviews' => (int) ($shop['review_count'] ?? 0),
                        'source'  => 'api',
                    ];
                    set_transient('etsy_sync_stats', $stats, DAY_IN_SECONDS);
                    update_option('etsy_sync_last_updated', time());
                    delete_option('etsy_sync_last_error');
                    return $stats;
                }
            }
        }
    }

    if (!$shop_id) {
        update_option('etsy_sync_last_error', 'Shop "' . $shop_name . '" not found. Please enter the Shop ID manually.');
        return $fallback;
    }

    $response = etsy_sync_request(
        'https://openapi.etsy.com/v3/application/shops/' . $shop_id,
        $api_key
    );

    if (is_wp_error($response)) {
        update_option('etsy_sync_last_error', 'WordPress error: ' . $response->get_error_message());
        return $fallback;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($code !== 200) {
        $msg = json_decode($body, true)['error'] ?? wp_strip_all_tags($body);
        update_option('etsy_sync_last_error', "HTTP {$code} – " . substr($msg, 0, 300));
        return $fallback;
    }

    $data = json_decode($body, true);

    if (empty($data['shop_id'])) {
        update_option('etsy_sync_last_error', 'Unexpected response: ' . substr($body, 0, 300));
        return $fallback;
    }

    delete_option('etsy_sync_last_error');

    $stats = [
        'sales'   => number_format((int)   ($data['transaction_sold_count'] ?? 0), 0, ',', '.'),
        'rating'  => number_format((float) ($data['review_average']         ?? 5.0), 1, ',', ''),
        'reviews' => (int) ($data['review_count'] ?? 0),
        'source'  => 'api',
    ];

    set_transient('etsy_sync_stats', $stats, DAY_IN_SECONDS);
    update_option('etsy_sync_last_updated', time());

    return $stats;
}

/** Returns cached stats, fetching fresh data from Etsy if the cache has expired. */
function etsy_sync_get_stats(): array {
    $cached = get_transient('etsy_sync_stats');
    if ($cached !== false) return $cached;
    return etsy_sync_fetch_stats();
}

/* =================================================================
   REVIEW SYNC
   ================================================================= */

/**
 * Formats an Etsy login_name into a "First L." display name.
 * Purely cosmetic — Etsy does not expose full buyer names via the API.
 */
function etsy_sync_format_buyer_name(string $login): string {
    $clean = preg_replace('/\d+$/', '', $login);
    $clean = trim(str_replace(['_', '-', '.'], ' ', $clean));

    if (!$clean || strlen($clean) < 2) return 'Etsy Käufer';
    if (stripos($clean, 'etsy') === 0 || stripos($clean, 'user') === 0) return 'Etsy Käufer';

    $parts = array_values(array_filter(explode(' ', $clean)));
    if (count($parts) >= 2) {
        return ucfirst(strtolower($parts[0])) . ' ' . strtoupper(substr($parts[1], 0, 1)) . '.';
    }
    if (preg_match('/^([A-Z][a-z]+)([A-Z][a-zA-Z]*)/', $login, $m)) {
        return $m[1] . ' ' . strtoupper(substr($m[2], 0, 1)) . '.';
    }
    return ucfirst(strtolower($clean));
}

/**
 * Fetches Etsy shop reviews and imports new ones as 'bewertung' posts.
 * Only imports reviews rated >= $min_rating; skips already-imported entries
 * (matched by Etsy transaction_id stored in meta).
 *
 * @return int Number of newly created review posts
 */
function etsy_sync_reviews(int $min_rating = 4): int {
    $keystring = get_option('etsy_sync_keystring', '');
    $secret    = get_option('etsy_sync_secret', '');
    $shop_id   = (int) get_option('etsy_sync_shop_id', 0);

    if (!$keystring || !$shop_id) {
        update_option('etsy_sync_reviews_last_error', 'Missing API key or Shop ID – save credentials and run a stats sync first.');
        return 0;
    }

    $api_key        = $secret ? $keystring . ':' . $secret : $keystring;
    $imported       = 0;
    $deleted        = 0;
    $all_etsy_ids   = [];
    $offset         = 0;
    $batch          = 100;

    /* Paginate through all Etsy reviews to collect every transaction_id */
    do {
        $response = etsy_sync_request(
            'https://openapi.etsy.com/v3/application/shops/' . $shop_id
            . '/reviews?limit=' . $batch . '&offset=' . $offset,
            $api_key
        );

        if (is_wp_error($response)) {
            update_option('etsy_sync_reviews_last_error', 'WordPress error: ' . $response->get_error_message());
            return 0;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            $msg = json_decode($body, true)['error'] ?? wp_strip_all_tags($body);
            update_option('etsy_sync_reviews_last_error', "HTTP {$code} – " . substr($msg, 0, 300));
            return 0;
        }

        $data    = json_decode($body, true);
        $reviews = $data['results'] ?? [];

        foreach ($reviews as $review) {
            $rating         = (int)    ($review['rating']         ?? 0);
            $transaction_id = (string) ($review['transaction_id'] ?? '');
            $listing_id     = (int)    ($review['listing_id']     ?? 0);
            $text           = trim((string) ($review['review'] ?? ''));

            if ($transaction_id) $all_etsy_ids[] = $transaction_id;

            if ($rating < $min_rating || !$transaction_id || !$text) continue;

            if (get_posts(['post_type' => 'bewertung', 'posts_per_page' => 1, 'fields' => 'ids',
                    'meta_key' => '_etsy_transaction_id', 'meta_value' => $transaction_id])) continue;

            $post_id = wp_insert_post([
                'post_type'    => 'bewertung',
                'post_title'   => wp_trim_words($text, 8, '…'),
                'post_content' => sanitize_textarea_field($text),
                'post_status'  => 'publish',
            ]);

            if (!$post_id || is_wp_error($post_id)) continue;

            update_post_meta($post_id, '_bewertung_sterne',    $rating);
            update_post_meta($post_id, '_bewertung_autor',     'Etsy Käufer');
            update_post_meta($post_id, '_bewertung_quelle',    'Etsy ✓');
            update_post_meta($post_id, '_etsy_transaction_id', $transaction_id);
            update_post_meta($post_id, '_etsy_buyer_user_id',  (int) ($review['buyer_user_id'] ?? 0));
            if ($listing_id) update_post_meta($post_id, '_etsy_listing_id', $listing_id);

            $imported++;
        }

        $offset += count($reviews);
    } while (count($reviews) === $batch);

    /* Delete ALL WP reviews not found in current Etsy reviews */
    if (!empty($all_etsy_ids)) {
        $wp_reviews = get_posts([
            'post_type'      => 'bewertung',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        foreach ($wp_reviews as $rid) {
            $tid = (string) get_post_meta($rid, '_etsy_transaction_id', true);
            if (!$tid || !in_array($tid, $all_etsy_ids, true)) {
                wp_delete_post($rid, true);
                $deleted++;
            }
        }
    }

    update_option('etsy_sync_reviews_last_sync',  time());
    update_option('etsy_sync_reviews_last_count', $imported);
    update_option('etsy_sync_reviews_last_deleted', $deleted);
    if ($imported > 0 || $deleted > 0) delete_option('etsy_sync_reviews_last_error');

    return $imported;
}

/** Returns review sync status for the admin dashboard. */
function etsy_sync_get_review_sync_status(): array {
    $counts = wp_count_posts('bewertung');
    return [
        'total'      => isset($counts->publish) ? (int) $counts->publish : 0,
        'last_sync'  => (int)    get_option('etsy_sync_reviews_last_sync',  0),
        'last_count' => (int)    get_option('etsy_sync_reviews_last_count', 0),
        'last_error' => (string) get_option('etsy_sync_reviews_last_error', ''),
    ];
}

/* =================================================================
   PRODUCT SYNC – helpers
   ================================================================= */

/**
 * Builds a clean excerpt from an Etsy listing description.
 * Strips HTML, collapses whitespace, trims to $max chars at a word boundary.
 * Written to post_excerpt so Yoast's %%excerpt%% template variable works.
 */
function etsy_sync_build_excerpt(string $desc, int $max = 155): string {
    $clean = trim((string) preg_replace('/\s+/', ' ', wp_strip_all_tags($desc)));
    if (mb_strlen($clean) <= $max) return $clean;
    $cut        = mb_substr($clean, 0, $max);
    $last_space = mb_strrpos($cut, ' ');
    if ($last_space !== false && $last_space > (int) ($max * 0.5)) {
        $cut = mb_substr($cut, 0, $last_space);
    }
    return rtrim($cut, '.,;:–-');
}

/**
 * Sets the Yoast focus keyphrase from the product's primary category SEO
 * keyword. Clears any previously auto-generated title/metadesc overrides so
 * Yoast's own Content Types templates control the format going forward.
 * No-op when Yoast SEO is not active.
 */
function etsy_sync_auto_yoast_meta(int $post_id): void {
    if (!defined('WPSEO_VERSION')) return;

    // Remove auto-generated overrides — Yoast templates handle formatting.
    delete_post_meta($post_id, '_yoast_wpseo_title');
    delete_post_meta($post_id, '_yoast_wpseo_metadesc');

    if (!function_exists('annyhase_build_yoast_fields')) return;
    $fields = annyhase_build_yoast_fields($post_id);
    // Always overwrite — replaces stale or auto-generated values from old syncs.
    // Only skipped when no category keyword is available (empty string).
    if ($fields['focuskw']) {
        update_post_meta($post_id, '_yoast_wpseo_focuskw', $fields['focuskw']);
    }
}

/** Returns the combined API key string (keystring:secret or keystring alone). */
function etsy_sync_get_api_key(): string {
    $ks  = get_option('etsy_sync_keystring', '');
    $sec = get_option('etsy_sync_secret', '');
    if (!$ks) return '';
    return $sec ? $ks . ':' . $sec : $ks;
}

/**
 * Creates or retrieves a 'produktkategorie' term matching a given Etsy shop
 * section and assigns it to the product post.
 *
 * @param int   $post_id    WP post ID of the product
 * @param int   $section_id Etsy shop_section_id from the listing data
 * @param array $sections   Map of section_id => section title
 */
function etsy_sync_set_product_term(int $post_id, int $section_id, array $sections): void {
    if (!$section_id || empty($sections[$section_id])) return;

    $term_name = $sections[$section_id];
    $term      = term_exists($term_name, 'produktkategorie');
    if (!$term) {
        $term = wp_insert_term($term_name, 'produktkategorie');
    }
    if ($term && !is_wp_error($term)) {
        wp_set_post_terms($post_id, [(int) ($term['term_id'] ?? 0)], 'produktkategorie');
    }
}

/**
 * Downloads and attaches all images and the video thumbnail for a product post.
 *
 * Skips image download if the featured image is already set (unless $force = true).
 * Every imported attachment is flagged with _etsy_product_media = 1 so it stays
 * hidden from the main Media Library (filtered in etsy-media.php).
 *
 * @return array { images_ok, images_err, video_ok }
 */
function etsy_sync_import_listing_media(int $post_id, array $listing, bool $force = false): array {
    $counts = ['images_ok' => 0, 'images_err' => 0, 'video_ok' => 0];

    if (!function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $title  = get_the_title($post_id);
    $images = $listing['images'] ?? [];

    /* Skip image re-import when a featured image already exists */
    if (!$force && get_post_thumbnail_id($post_id)) {
        $images = [];
    }

    $gallery_ids = [];
    foreach ($images as $idx => $img) {
        $img_url = $img['url_570xN'] ?? ($img['url_fullxfull'] ?? '');
        if (!$img_url) continue;

        $att_id = media_sideload_image($img_url, $post_id, $title, 'id');
        if (is_wp_error($att_id)) {
            $counts['images_err']++;
            update_option('etsy_sync_products_media_last_error',
                "Image import failed (post {$post_id}): " . $att_id->get_error_message());
            continue;
        }

        update_post_meta($att_id, '_etsy_product_media', '1');
        $counts['images_ok']++;

        if ($idx === 0) {
            set_post_thumbnail($post_id, $att_id);
        } else {
            $gallery_ids[] = $att_id;
        }
    }

    if ($gallery_ids) {
        $existing_raw = get_post_meta($post_id, '_produkt_galerie', true);
        $existing_ids = $existing_raw ? array_filter(array_map('intval', explode(',', $existing_raw))) : [];
        update_post_meta($post_id, '_produkt_galerie', implode(',', array_unique(array_merge($existing_ids, $gallery_ids))));
    }

    /* Skip video re-import when the URL is already stored */
    if (!$force && get_post_meta($post_id, '_etsy_video_url', true)) {
        return $counts;
    }

    $videos = $listing['videos'] ?? [];
    if (!empty($videos)) {
        $video     = $videos[0];
        $video_url = esc_url_raw($video['video_url']     ?? '');
        $thumb_url = esc_url_raw($video['thumbnail_url'] ?? '');

        if ($video_url) update_post_meta($post_id, '_etsy_video_url',           $video_url);
        if ($thumb_url) update_post_meta($post_id, '_etsy_video_thumbnail_url', $thumb_url);

        if ($thumb_url) {
            $vid_att = media_sideload_image($thumb_url, $post_id, $title . ' – Video', 'id');
            if (!is_wp_error($vid_att)) {
                update_post_meta($vid_att, '_etsy_product_media',   '1');
                update_post_meta($vid_att, '_etsy_video_thumb_for', $post_id);
                $counts['video_ok'] = 1;
            }
        }
    }

    return $counts;
}

/**
 * Full synchronous product sync — used by the daily cron.
 * Fetches all active Etsy listings, creates/updates 'produkt' posts, imports media,
 * assigns categories, and drafts any products no longer active on Etsy.
 *
 * @return array { new, updated, skipped, deactivated, images_ok, images_err, errors }
 */
function etsy_sync_products(bool $update_existing = false): array {
    $keystring = get_option('etsy_sync_keystring', '');
    $secret    = get_option('etsy_sync_secret', '');
    $shop_id   = (int) get_option('etsy_sync_shop_id', 0);

    $result = ['new' => 0, 'updated' => 0, 'skipped' => 0, 'deactivated' => 0,
               'images_ok' => 0, 'images_err' => 0, 'errors' => 0];

    if (!$keystring || !$shop_id) {
        update_option('etsy_sync_products_last_error', 'No API key or Shop ID – save credentials and run a stats sync first.');
        return $result;
    }

    $api_key = $secret ? $keystring . ':' . $secret : $keystring;
    if (function_exists('set_time_limit')) set_time_limit(300);

    /* Fetch shop sections once for category assignment */
    $sections = [];
    $sec_res  = etsy_sync_request(
        'https://openapi.etsy.com/v3/application/shops/' . $shop_id . '/sections', $api_key
    );
    if (!is_wp_error($sec_res) && (int) wp_remote_retrieve_response_code($sec_res) === 200) {
        foreach (json_decode(wp_remote_retrieve_body($sec_res), true)['results'] ?? [] as $s) {
            $sections[(int) ($s['shop_section_id'] ?? 0)] = sanitize_text_field($s['title'] ?? '');
        }
    }

    $offset     = 0;
    $limit      = 25;
    $fetched    = 0;
    $api_total  = PHP_INT_MAX;
    $active_ids = [];

    while ($offset < $api_total && $fetched < 500) {
        $url = 'https://openapi.etsy.com/v3/application/shops/' . $shop_id
             . '/listings/active?includes%5B%5D=Images&includes%5B%5D=Videos'
             . '&limit=' . $limit . '&offset=' . $offset;

        $response = etsy_sync_request($url, $api_key);

        if (is_wp_error($response)) {
            update_option('etsy_sync_products_last_error', 'WordPress error: ' . $response->get_error_message());
            $result['errors']++;
            break;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            $msg = json_decode($body, true)['error'] ?? wp_strip_all_tags($body);
            update_option('etsy_sync_products_last_error', "HTTP {$code} – " . substr($msg, 0, 300));
            $result['errors']++;
            break;
        }

        $data      = json_decode($body, true);
        $listings  = $data['results'] ?? [];
        $api_total = (int) ($data['count'] ?? 0);
        if (empty($listings)) break;

        foreach ($listings as $listing) {
            $listing_id = (int)    ($listing['listing_id']      ?? 0);
            $title      = sanitize_text_field($listing['title'] ?? '');
            $desc       = sanitize_textarea_field($listing['description'] ?? '');
            $etsy_url   = esc_url_raw($listing['url']            ?? '');
            $section_id = (int)    ($listing['shop_section_id'] ?? 0);

            if (!$listing_id || !$title) { $result['skipped']++; continue; }

            $active_ids[] = $listing_id;

            $price_str = '';
            if (!empty($listing['price']['amount']) && !empty($listing['price']['divisor'])) {
                $price_str = number_format(
                    (int) $listing['price']['amount'] / (int) $listing['price']['divisor'], 2, ',', '.'
                );
            }

            $existing = get_posts([
                'post_type' => 'produkt', 'posts_per_page' => 1, 'fields' => 'ids',
                'post_status' => 'any',
                'meta_query' => [['key' => '_etsy_listing_id', 'value' => $listing_id]],
            ]);

            if ($existing) {
                $post_id = $existing[0];
                if ($update_existing) {
                    wp_update_post(['ID' => $post_id, 'post_title' => $title,
                        'post_content' => $desc, 'post_excerpt' => etsy_sync_build_excerpt($desc), 'post_status' => 'publish']);
                    if ($price_str) update_post_meta($post_id, '_produkt_preis', $price_str);
                    if ($etsy_url)  update_post_meta($post_id, '_etsy_url',      $etsy_url);
                    etsy_sync_set_product_term($post_id, $section_id, $sections);
                    etsy_sync_auto_yoast_meta($post_id);
                    $result['updated']++;
                }
                if (!get_post_thumbnail_id($post_id)) {
                    $mc = etsy_sync_import_listing_media($post_id, $listing, false);
                    $result['images_ok']  += $mc['images_ok'];
                    $result['images_err'] += $mc['images_err'];
                }
                if (!$update_existing) $result['skipped']++;
            } else {
                $post_id = wp_insert_post(['post_type' => 'produkt', 'post_title' => $title,
                    'post_content' => $desc, 'post_excerpt' => etsy_sync_build_excerpt($desc), 'post_status' => 'publish']);
                if (!$post_id || is_wp_error($post_id)) { $result['errors']++; continue; }
                update_post_meta($post_id, '_etsy_listing_id', $listing_id);
                update_post_meta($post_id, '_is_etsy_produkt',  '1');
                if ($price_str) update_post_meta($post_id, '_produkt_preis', $price_str);
                if ($etsy_url)  update_post_meta($post_id, '_etsy_url',      $etsy_url);
                etsy_sync_set_product_term($post_id, $section_id, $sections);
                etsy_sync_auto_yoast_meta($post_id);
                $mc = etsy_sync_import_listing_media($post_id, $listing, false);
                $result['images_ok']  += $mc['images_ok'];
                $result['images_err'] += $mc['images_err'];
                $result['new']++;
            }
        }

        $fetched += count($listings);
        $offset  += count($listings);
        if (count($listings) < $limit) break;
    }

    /* Delete ALL WP products not found in the current Etsy listings */
    if (!empty($active_ids) && $result['errors'] === 0) {
        $all_wp = get_posts([
            'post_type' => 'produkt', 'posts_per_page' => -1, 'fields' => 'ids',
            'post_status' => 'any',
        ]);
        foreach ($all_wp as $pid) {
            $lid = (int) get_post_meta($pid, '_etsy_listing_id', true);
            if (!$lid || !in_array($lid, $active_ids, true)) {
                wp_delete_post($pid, true);
                $result['deactivated']++;
            }
        }
    }

    update_option('etsy_sync_products_last_sync',   time());
    update_option('etsy_sync_products_last_result', $result);
    if ($result['errors'] === 0) delete_option('etsy_sync_products_last_error');

    return $result;
}

/** Returns product sync status for the admin dashboard. */
function etsy_sync_get_product_sync_status(): array {
    $counts = wp_count_posts('produkt');
    return [
        'total'       => isset($counts->publish) ? (int) $counts->publish : 0,
        'last_sync'   => (int)    get_option('etsy_sync_products_last_sync',   0),
        'last_result' => (array)  get_option('etsy_sync_products_last_result', []),
        'last_error'  => (string) get_option('etsy_sync_products_last_error',  ''),
    ];
}

/* =================================================================
   AJAX – PHASE 1: Fetch listings → create/update product posts
   No media download in this phase; images are handled in Phase 2.
   Runs in 25-listing batches to stay within PHP time limits.
   Tracks all seen listing IDs so stale products can be drafted on completion.
   ================================================================= */

add_action('wp_ajax_etsy_sync_products_batch', function (): void {
    check_ajax_referer('etsy_sync_products_batch', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['msg' => 'Unauthorized']);

    $offset          = max(0, (int) ($_POST['offset'] ?? 0));
    $update_existing = !empty($_POST['update_existing']);
    $limit           = 25;
    $api_key         = etsy_sync_get_api_key();
    $shop_id         = (int) get_option('etsy_sync_shop_id', 0);

    if (!$api_key || !$shop_id) {
        wp_send_json_error(['msg' => 'No API key or Shop ID configured.']);
    }

    if ($offset === 0) {
        /* Reset the active-IDs accumulator at the start of each sync run */
        delete_transient('etsy_sync_active_ids');

        /* Fetch shop sections on the first batch and cache for the full run */
        $sec_res = etsy_sync_request(
            'https://openapi.etsy.com/v3/application/shops/' . $shop_id . '/sections', $api_key
        );
        if (!is_wp_error($sec_res) && (int) wp_remote_retrieve_response_code($sec_res) === 200) {
            $sections = [];
            foreach (json_decode(wp_remote_retrieve_body($sec_res), true)['results'] ?? [] as $s) {
                $sections[(int) ($s['shop_section_id'] ?? 0)] = sanitize_text_field($s['title'] ?? '');
            }
            set_transient('etsy_shop_sections', $sections, HOUR_IN_SECONDS);
        }
    }

    $sections = get_transient('etsy_shop_sections') ?: [];

    $url      = 'https://openapi.etsy.com/v3/application/shops/' . $shop_id
              . '/listings/active?limit=' . $limit . '&offset=' . $offset;
    $response = etsy_sync_request($url, $api_key);

    if (is_wp_error($response)) wp_send_json_error(['msg' => $response->get_error_message()]);

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        $msg = json_decode(wp_remote_retrieve_body($response), true)['error'] ?? "HTTP $code";
        wp_send_json_error(['msg' => $msg]);
    }

    $data     = json_decode(wp_remote_retrieve_body($response), true);
    $listings = $data['results'] ?? [];
    $total    = (int) ($data['count'] ?? 0);
    $new = $updated = 0;

    /* Accumulate listing IDs across batches for stale-product detection */
    $active_ids = get_transient('etsy_sync_active_ids') ?: [];

    foreach ($listings as $listing) {
        $listing_id = (int)    ($listing['listing_id']      ?? 0);
        $title      = sanitize_text_field($listing['title'] ?? '');
        $desc       = sanitize_textarea_field($listing['description'] ?? '');
        $etsy_url   = esc_url_raw($listing['url']            ?? '');
        $section_id = (int)    ($listing['shop_section_id'] ?? 0);

        if (!$listing_id || !$title) continue;

        $active_ids[] = $listing_id;

        $price_str = '';
        if (!empty($listing['price']['amount']) && !empty($listing['price']['divisor'])) {
            $price_str = number_format(
                (int) $listing['price']['amount'] / (int) $listing['price']['divisor'], 2, ',', '.'
            );
        }

        $existing = get_posts([
            'post_type' => 'produkt', 'posts_per_page' => 1, 'fields' => 'ids',
            'post_status' => 'any',
            'meta_query' => [['key' => '_etsy_listing_id', 'value' => $listing_id]],
        ]);

        if ($existing) {
            $post_id = $existing[0];
            if ($update_existing) {
                wp_update_post(['ID' => $post_id, 'post_title' => $title,
                    'post_content' => $desc, 'post_status' => 'publish']);
                if ($price_str) update_post_meta($post_id, '_produkt_preis', $price_str);
                if ($etsy_url)  update_post_meta($post_id, '_etsy_url',      $etsy_url);
                etsy_sync_set_product_term($post_id, $section_id, $sections);
                etsy_sync_auto_yoast_meta($post_id);
                $updated++;
            }
        } else {
            $post_id = wp_insert_post(['post_type' => 'produkt', 'post_title' => $title,
                'post_content' => $desc, 'post_status' => 'publish']);
            if ($post_id && !is_wp_error($post_id)) {
                update_post_meta($post_id, '_etsy_listing_id', $listing_id);
                update_post_meta($post_id, '_is_etsy_produkt',  '1');
                if ($price_str) update_post_meta($post_id, '_produkt_preis', $price_str);
                if ($etsy_url)  update_post_meta($post_id, '_etsy_url',      $etsy_url);
                etsy_sync_set_product_term($post_id, $section_id, $sections);
                etsy_sync_auto_yoast_meta($post_id);
                $new++;
            }
        }
    }

    /* Persist the growing active-IDs list for the next batch */
    set_transient('etsy_sync_active_ids', array_unique($active_ids), 2 * HOUR_IN_SECONDS);

    $next_offset = $offset + count($listings);
    $done        = empty($listings) || $next_offset >= $total;
    $deactivated = 0;

    /* On completion: delete ALL WP products not found in this sync run */
    if ($done && !empty($active_ids)) {
        $all_wp = get_posts([
            'post_type' => 'produkt', 'posts_per_page' => -1, 'fields' => 'ids',
            'post_status' => 'any',
        ]);
        foreach ($all_wp as $pid) {
            $lid = (int) get_post_meta($pid, '_etsy_listing_id', true);
            if (!$lid || !in_array($lid, $active_ids, true)) {
                wp_delete_post($pid, true);
                $deactivated++;
            }
        }
        delete_transient('etsy_sync_active_ids');
        update_option('etsy_sync_products_last_sync', time());
        delete_option('etsy_sync_products_last_error');
    }

    wp_send_json_success([
        'new'         => $new,
        'updated'     => $updated,
        'deactivated' => $deactivated,
        'total'       => $total,
        'next_offset' => $next_offset,
        'done'        => $done,
    ]);
});

/* =================================================================
   AJAX – PHASE 2: Import media for products missing a featured image.
   Processes one product per request to avoid PHP timeout.
   The ID list is frozen on the first call so the total count never jumps.
   ================================================================= */

add_action('wp_ajax_etsy_sync_media_batch', function (): void {
    check_ajax_referer('etsy_sync_media_batch', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['msg' => 'Unauthorized']);

    if (!function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }
    if (function_exists('set_time_limit')) set_time_limit(120);

    $offset = max(0, (int) ($_POST['offset'] ?? 0));
    $init   = !empty($_POST['init']);

    /* First call: build and freeze the list of products that need images */
    if ($init) {
        $ids = get_posts([
            'post_type' => 'produkt', 'posts_per_page' => -1, 'fields' => 'ids',
            'post_status' => 'any',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => '_etsy_listing_id', 'compare' => 'EXISTS'],
                ['key' => '_thumbnail_id',    'compare' => 'NOT EXISTS'],
            ],
        ]);
        set_transient('etsy_media_batch_ids', array_values($ids), 2 * HOUR_IN_SECONDS);
    }

    $ids   = get_transient('etsy_media_batch_ids') ?: [];
    $total = count($ids);

    if ($total === 0) {
        wp_send_json_success(['total' => 0, 'processed' => 0, 'done' => true, 'ok' => 0, 'err' => 0]);
    }

    $api_key = etsy_sync_get_api_key();
    $batch   = array_slice($ids, $offset, 1);
    $ok = $err = 0;

    foreach ($batch as $post_id) {
        $listing_id = (int) get_post_meta($post_id, '_etsy_listing_id', true);
        if (!$listing_id || !$api_key) { $err++; continue; }

        /* Fetch the individual listing with images and videos included */
        $url = 'https://openapi.etsy.com/v3/application/listings/' . $listing_id
             . '?includes%5B%5D=Images&includes%5B%5D=Videos';
        $res = etsy_sync_request($url, $api_key);

        if (is_wp_error($res) || (int) wp_remote_retrieve_response_code($res) !== 200) {
            $err++; continue;
        }

        $listing = json_decode(wp_remote_retrieve_body($res), true) ?? [];
        if (empty($listing)) { $err++; continue; }

        $mc   = etsy_sync_import_listing_media($post_id, $listing, false);
        $ok  += $mc['images_ok'];
        $err += $mc['images_err'];
    }

    $processed = $offset + count($batch);
    $done      = empty($batch) || $processed >= $total;

    if ($done) {
        delete_transient('etsy_media_batch_ids');
        update_option('etsy_sync_products_last_sync', time());
    }

    wp_send_json_success([
        'total'     => $total,
        'processed' => $processed,
        'done'      => $done,
        'ok'        => $ok,
        'err'       => $err,
    ]);
});

/* =================================================================
   AJAX – Immediate shop stats refresh
   ================================================================= */

add_action('wp_ajax_etsy_sync_stats_now', function (): void {
    check_ajax_referer('etsy_sync_stats_now', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['msg' => 'Unauthorized']);
    etsy_sync_clear_cache();
    $stats = etsy_sync_fetch_stats();
    wp_send_json_success($stats);
});

/* =================================================================
   AJAX – Immediate reviews import
   ================================================================= */

add_action('wp_ajax_etsy_sync_reviews_now', function (): void {
    check_ajax_referer('etsy_sync_reviews_now', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['msg' => 'Unauthorized']);
    $imported = etsy_sync_reviews();
    wp_send_json_success(['imported' => $imported]);
});
