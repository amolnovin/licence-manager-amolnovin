<?php
/**
 * Plugin Name: لایسنس منیجر آمل نوین
 * Plugin URI: https://amolnovin.ir
 * Description: مدیریت مرکزی لایسنس افزونه‌هایی که با WPLM Client Kit و wp-plugin-market-manager کار می‌کنند.
 * Version: 1.0.7
 * Author: Amol Novin
 * Text Domain: wplm-amolnovin-license-manager
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WPLM_Amolnovin_License_Manager')) {
    final class WPLM_Amolnovin_License_Manager
    {
        private static $clients = [];
        private static $booted = false;
        private static $page_slug = 'wplm-amolnovin-license-manager';
        private static $capability = 'manage_options';

        public static function boot(): void
        {
            if (self::$booted) {
                return;
            }
            self::$booted = true;
            add_action('admin_menu', [__CLASS__, 'register_menu']);
            add_action('admin_post_wplm_client_manager_save', [__CLASS__, 'handle_save']);
            add_action('admin_post_wplm_client_manager_check', [__CLASS__, 'handle_check']);
            add_action('admin_post_wplm_client_manager_clear', [__CLASS__, 'handle_clear']);
            add_action('admin_post_wplm_client_manager_connection_test', [__CLASS__, 'handle_connection_test']);
            add_action('wp_ajax_wplm_manager_check_server_version', [__CLASS__, 'ajax_check_server_version']);
            add_action('wp_ajax_wplm_manager_test_connection', [__CLASS__, 'ajax_test_connection']);
        }

        public static function register($client): void
        {
            if (!is_object($client) || !method_exists($client, 'get_product_slug')) {
                return;
            }
            self::boot();
            $slug = sanitize_key((string) $client->get_product_slug());
            self::$clients[$slug] = $client;
            self::persist_client($client);
        }

        public static function register_menu(): void
        {
            add_options_page(
                'لایسنس منیجر آمل نوین',
                'لایسنس منیجر آمل نوین',
                self::$capability,
                self::$page_slug,
                [__CLASS__, 'render_page']
            );
        }

        public static function page_url(array $args = []): string
        {
            return add_query_arg(array_merge(['page' => self::$page_slug], $args), admin_url('options-general.php'));
        }

        public static function render_page(): void
        {
            if (!current_user_can(self::$capability)) {
                wp_die(esc_html__('دسترسی غیرمجاز است.', 'wplm-amolnovin-license-manager'));
            }
            $clients = self::$clients;
            $registered = get_option('wplm_client_kit_registered_products', []);
            $registered = is_array($registered) ? $registered : [];
            ?>
            <div class="wrap" dir="rtl">
                <?php if (!empty($_GET['wplm_saved'])) : ?><div class="notice notice-success is-dismissible"><p>لایسنس ذخیره و بررسی شد.</p></div><?php endif; ?>
                <?php if (!empty($_GET['wplm_checked'])) : ?><div class="notice notice-success is-dismissible"><p>وضعیت لایسنس دوباره بررسی شد.</p></div><?php endif; ?>
                <?php if (!empty($_GET['wplm_cleared'])) : ?><div class="notice notice-warning is-dismissible"><p>لایسنس محلی حذف شد.</p></div><?php endif; ?>
                <?php if (!empty($_GET['wplm_error'])) : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html(wp_unslash($_GET['wplm_error'])); ?></p></div><?php endif; ?>

                <style>
                    .wplm-manager-hero{background:linear-gradient(135deg,#111827,#4f46e5 55%,#06b6d4);color:#fff;border-radius:20px;padding:22px 24px;margin:18px 0;box-shadow:0 18px 45px rgba(79,70,229,.18)}
                    .wplm-manager-hero h2{color:#fff;margin:0 0 8px;font-size:24px}.wplm-manager-hero p{margin:0;opacity:.92}
                    .wplm-manager-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:18px;margin:16px 0;box-shadow:0 12px 35px rgba(15,23,42,.06)}
                    .wplm-manager-card-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px}.wplm-manager-card-head h2{margin:0}.wplm-manager-count{background:#eef2ff;color:#3730a3;border-radius:999px;padding:7px 12px;font-weight:700}
                    .wplm-manager-table{border:0!important}.wplm-manager-table thead th{background:#f8fafc;color:#334155;font-weight:800}.wplm-manager-table td,.wplm-manager-table th{vertical-align:middle;padding:14px!important}.wplm-manager-table input[type=text]{width:100%;max-width:420px;direction:ltr;text-align:left;border:1px solid #d1d5db;border-radius:12px;padding:9px 12px}
                    .wplm-plugin-title{display:flex;align-items:center;gap:10px}.wplm-plugin-icon{width:42px;height:42px;border-radius:14px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#4f46e5,#06b6d4);color:#fff;font-weight:900;box-shadow:0 8px 18px rgba(79,70,229,.2)}
                    .wplm-status-pill{display:inline-flex;border-radius:999px;padding:6px 12px;font-weight:800;font-size:12px;background:#f0f0f1;color:#50575e}.wplm-status-pill.active{background:#dcfce7;color:#166534}.wplm-status-pill.inactive{background:#fee2e2;color:#991b1b}.wplm-version-pill{display:inline-flex;border-radius:999px;padding:5px 10px;background:#eef2ff;color:#3730a3;font-weight:800;font-size:12px}.wplm-version-pill.update{background:#fef3c7;color:#92400e}.wplm-version-note{display:block;margin-top:5px;color:#64748b;line-height:1.7}
                    .wplm-manager-actions{display:flex;gap:7px;flex-wrap:wrap}.wplm-manager-actions form{display:inline-flex;gap:6px;align-items:center;margin:0}.wplm-icon-btn{width:36px;height:36px;display:inline-flex!important;align-items:center;justify-content:center;border-radius:11px!important;padding:0!important}.wplm-icon-btn .dashicons{font-size:18px;width:18px;height:18px}.wplm-icon-btn.is-primary{background:#4f46e5!important;border-color:#4f46e5!important;color:#fff!important}.wplm-icon-btn.is-danger{color:#b91c1c!important;border-color:#fecaca!important;background:#fff5f5!important}.wplm-icon-btn.is-version{color:#0369a1!important;border-color:#bae6fd!important;background:#f0f9ff!important}.wplm-icon-btn.is-loading .dashicons{animation:wplmSpin 1s linear infinite}@keyframes wplmSpin{to{transform:rotate(360deg)}}.wplm-license-hash{display:block;margin-top:6px;color:#64748b;font-size:11px;direction:ltr;text-align:left}
.wplm-muted{color:#646970}.wplm-license-form{margin:0}.wplm-license-message{display:block;margin-top:5px;color:#64748b;line-height:1.7}
                    .wplm-connection-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:20px;margin:16px 0;box-shadow:0 12px 35px rgba(15,23,42,.06)}
                    .wplm-connection-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:16px}
                    .wplm-conn-stat{background:#f8fafc;border-radius:14px;padding:16px;text-align:center;border:1px solid #e5e7eb}
                    .wplm-conn-stat-icon{font-size:28px;margin-bottom:8px}.wplm-conn-stat-label{font-weight:700;color:#334155;font-size:13px}
                    .wplm-conn-stat-value{font-size:22px;font-weight:900;margin-top:4px}.wplm-conn-stat-value.success{color:#16a34a}.wplm-conn-stat-value.error{color:#dc2626}.wplm-conn-stat-value.warning{color:#d97706}.wplm-conn-stat-value.neutral{color:#64748b}
                    .wplm-conn-stat-detail{font-size:11px;color:#64748b;margin-top:4px}
                    .wplm-conn-details{margin-top:16px;padding:16px;background:#f8fafc;border-radius:12px;border:1px solid #e5e7eb}
                    .wplm-conn-details h4{margin:0 0 10px;color:#334155;font-size:14px}.wplm-conn-details pre{background:#1e293b;color:#e2e8f0;padding:14px;border-radius:10px;overflow:auto;max-height:300px;font-size:12px;line-height:1.6;direction:ltr;text-align:left;margin:0}
                    .wplm-conn-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:14px}
                    .wplm-test-btn{background:#4f46e5!important;border-color:#4f46e5!important;color:#fff!important}.wplm-test-btn:hover{background:#4338ca!important}
                    .wplm-test-btn.is-loading .dashicons{animation:wplmSpin 1s linear infinite}
                    .wplm-conn-status{position:relative;padding-right:36px}.wplm-conn-status::before{content:'';position:absolute;right:0;top:50%;transform:translateY(-50%);width:24px;height:24px;border-radius:50%}.wplm-conn-status.online::before{background:#22c55e}.wplm-conn-status.offline::before{background:#ef4444}.wplm-conn-status.checking::before{background:#f59e0b;animation:wplmPulse 1.5s infinite}@keyframes wplmPulse{0%,100%{opacity:1}50%{opacity:.4}}
@media(max-width:782px){.wplm-manager-card{padding:12px;background:transparent;border:0;box-shadow:none}.wplm-manager-card-head{display:block}.wplm-manager-table,.wplm-manager-table tbody,.wplm-manager-table tr,.wplm-manager-table td{display:block;width:100%}.wplm-manager-table thead{display:none}.wplm-manager-table tr{background:#fff;border:1px solid #e5e7eb;border-radius:18px;margin:12px 0;padding:10px;box-shadow:0 10px 26px rgba(15,23,42,.06)}.wplm-manager-table td{border:0!important;padding:10px!important}.wplm-manager-table td:before{content:attr(data-label);display:block;font-weight:800;color:#334155;margin-bottom:6px}.wplm-manager-actions{justify-content:flex-start}.wplm-manager-table input[type=text]{max-width:100%}.wplm-connection-grid{grid-template-columns:1fr 1fr}.wplm-conn-details pre{max-height:200px}}
                </style>

                <div class="wplm-manager-hero"><h2>لایسنس منیجر آمل نوین</h2><p>ثبت، فعال‌سازی و بررسی لایسنس افزونه‌های متصل در یک صفحه مرکزی.</p></div>

                <?php
                // دریافت وضعیت کش‌شده ارتباط
                $cached_conn = get_option('wplm_last_connection_check', []);
                $cached_conn = is_array($cached_conn) ? $cached_conn : [];
                $last_check = !empty($cached_conn['checked_at']) ? mysql2date('Y/m/d H:i', $cached_conn['checked_at']) : '—';
                $last_status = $cached_conn['success'] ?? null;
                ?>

                <div class="wplm-connection-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
                        <div>
                            <h3 style="margin:0 0 4px;color:#1e293b"><span class="dashicons dashicons-networking" style="margin-left:8px;color:#4f46e5"></span>بررسی ارتباط با سرور</h3>
                            <p style="margin:0;color:#64748b;font-size:13px">وضعیت اتصال این سایت به سرور آمل نوین (amolnovin.ir)</p>
                        </div>
                        <div class="wplm-conn-actions">
                            <span style="color:#64748b;font-size:12px">آخرین بررسی: <strong><?php echo esc_html($last_check); ?></strong></span>
                            <button type="button" id="wplm-test-connection-btn" class="button wplm-test-btn" data-nonce="<?php echo esc_attr(wp_create_nonce('wplm_manager_connection_test')); ?>">
                                <span class="dashicons dashicons-yes-alt" style="margin-left:4px"></span>آزمایش ارتباط
                            </button>
                        </div>
                    </div>

                    <div class="wplm-connection-grid" id="wplm-connection-stats">
                        <div class="wplm-conn-stat">
                            <div class="wplm-conn-stat-icon">🌐</div>
                            <div class="wplm-conn-stat-label">وضعیت سرور</div>
                            <div class="wplm-conn-stat-value <?php echo $last_status === true ? 'success' : ($last_status === false ? 'error' : 'neutral'); ?>" id="wplm-stat-server">
                                <?php echo $last_status === true ? 'آنلاین' : ($last_status === false ? 'آفلاین' : '—'); ?>
                            </div>
                            <div class="wplm-conn-stat-detail" id="wplm-stat-server-detail">بررسی نشده</div>
                        </div>
                        <div class="wplm-conn-stat">
                            <div class="wplm-conn-stat-icon">🔒</div>
                            <div class="wplm-conn-stat-label">گواهی SSL</div>
                            <div class="wplm-conn-stat-value <?php echo !empty($cached_conn['ssl_valid']) ? 'success' : (!empty($cached_conn['ssl_checked']) ? 'error' : 'neutral'); ?>" id="wplm-stat-ssl">
                                <?php echo !empty($cached_conn['ssl_valid']) ? 'معتبر' : (!empty($cached_conn['ssl_checked']) ? 'نامعتبر' : '—'); ?>
                            </div>
                            <div class="wplm-conn-stat-detail" id="wplm-stat-ssl-detail"><?php echo esc_html($cached_conn['ssl_message'] ?? 'بررسی نشده'); ?></div>
                        </div>
                        <div class="wplm-conn-stat">
                            <div class="wplm-conn-stat-icon">⚡</div>
                            <div class="wplm-conn-stat-label">زمان پاسخ</div>
                            <div class="wplm-conn-stat-value neutral" id="wplm-stat-time"><?php echo !empty($cached_conn['response_time']) ? esc_html(round($cached_conn['response_time'], 2) . ' ثانیه') : '—'; ?></div>
                            <div class="wplm-conn-stat-detail" id="wplm-stat-time-detail"><?php echo !empty($cached_conn['response_code']) ? 'کد HTTP: ' . esc_html($cached_conn['response_code']) : 'بدون پاسخ'; ?></div>
                        </div>
                        <div class="wplm-conn-stat">
                            <div class="wplm-conn-stat-icon">🔑</div>
                            <div class="wplm-conn-stat-label">API لایسنس</div>
                            <div class="wplm-conn-stat-value <?php echo !empty($cached_conn['license_api_ok']) ? 'success' : (!empty($cached_conn['license_api_checked']) ? 'error' : 'neutral'); ?>" id="wplm-stat-api">
                                <?php echo !empty($cached_conn['license_api_ok']) ? 'پاسخ صحیح' : (!empty($cached_conn['license_api_checked']) ? 'خطا' : '—'); ?>
                            </div>
                            <div class="wplm-conn-stat-detail" id="wplm-stat-api-detail"><?php echo esc_html($cached_conn['license_message'] ?? 'بررسی نشده'); ?></div>
                        </div>
                    </div>

                    <div class="wplm-conn-details" id="wplm-connection-details" style="display:none">
                        <h4>📋 جزئیات پاسخ سرور</h4>
                        <pre id="wplm-connection-response">در حال دریافت...</pre>
                    </div>

                    <div id="wplm-connection-notice" style="display:none;margin-top:14px"></div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded',function(){
                    var btn=document.getElementById('wplm-test-connection-btn');
                    if(!btn)return;
                    btn.addEventListener('click',function(e){
                        e.preventDefault();
                        var nonce=btn.getAttribute('data-nonce')||'';
                        btn.disabled=true;
                        btn.classList.add('is-loading');
                        btn.innerHTML='<span class="dashicons dashicons-update" style="animation:wplmSpin 1s linear infinite"></span> در حال بررسی...';
                        document.getElementById('wplm-stat-server').textContent='...';
                        document.getElementById('wplm-stat-server').className='wplm-conn-stat-value warning';
                        document.getElementById('wplm-stat-server-detail').textContent='در حال اتصال...';
                        var body=new URLSearchParams();
                        body.append('action','wplm_manager_test_connection');
                        body.append('nonce',nonce);
                        fetch(ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()})
                        .then(function(r){return r.json();})
                        .then(function(res){
                            var details=document.getElementById('wplm-connection-details');
                            var notice=document.getElementById('wplm-connection-notice');
                            details.style.display='block';
                            if(res&&res.success&&res.data){
                                var d=res.data;
                                // سرور
                                var sv=document.getElementById('wplm-stat-server');
                                sv.textContent=d.server_online?'آنلاین':'آفلاین';
                                sv.className='wplm-conn-stat-value '+(d.server_online?'success':'error');
                                document.getElementById('wplm-stat-server-detail').textContent=d.server_message||' ';
                                // SSL
                                document.getElementById('wplm-stat-ssl').textContent=d.ssl_valid?'معتبر':'نامعتبر';
                                document.getElementById('wplm-stat-ssl').className='wplm-conn-stat-value '+(d.ssl_valid?'success':'error');
                                document.getElementById('wplm-stat-ssl-detail').textContent=d.ssl_message||' ';
                                // زمان
                                document.getElementById('wplm-stat-time').textContent=d.response_time?d.response_time.toFixed(2)+' ثانیه':'—';
                                document.getElementById('wplm-stat-time-detail').textContent=d.response_code?'کد HTTP: '+d.response_code:'بدون پاسخ';
                                // API
                                document.getElementById('wplm-stat-api').textContent=d.license_api_ok?'پاسخ صحیح':'خطا';
                                document.getElementById('wplm-stat-api').className='wplm-conn-stat-value '+(d.license_api_ok?'success':'error');
                                document.getElementById('wplm-stat-api-detail').textContent=d.license_message||' ';
                                // جزئیات
                                document.getElementById('wplm-connection-response').textContent=d.raw_response||'بدون محتوا';
                                // notice
                                if(d.server_online&&d.ssl_valid){
                                    notice.style.display='block';
                                    notice.innerHTML='<div class="notice notice-success"><p>✅ ارتباط با سرور با موفقیت برقرار شد. تمام سرویس‌ها فعال هستند.</p></div>';
                                }else if(d.server_online&&!d.ssl_valid){
                                    notice.style.display='block';
                                    notice.innerHTML='<div class="notice notice-warning"><p>⚠️ سرور پاسخ می‌دهد اما گواهی SSL نامعتبر است. با هاست خود برای بررسی SSL تماس بگیرید.</p></div>';
                                }else{
                                    notice.style.display='block';
                                    notice.innerHTML='<div class="notice notice-error"><p>❌ ارتباط با سرور برقرار نیست. لطفاً موارد زیر را بررسی کنید:</p><ul style="margin:8px 0"><li>آیا هاست شما دسترسی به اینترنت دارد؟</li><li>آیا فایروال هاست outbound به amolnovin.ir:443 را مسدود نمی‌کند؟</li><li>آیا CDN یا WAF خاصی فعال است؟</li></ul></div>';
                                }
                            }else{
                                var msg=(res&&res.data&&res.data.message)||'خطا در بررسی ارتباط';
                                document.getElementById('wplm-stat-server').textContent='خطا';
                                document.getElementById('wplm-stat-server').className='wplm-conn-stat-value error';
                                document.getElementById('wplm-stat-server-detail').textContent=msg;
                                document.getElementById('wplm-connection-response').textContent=JSON.stringify(res,null,2);
                                notice.style.display='block';
                                notice.innerHTML='<div class="notice notice-error"><p>❌ '+msg+'</p></div>';
                            }
                        }).catch(function(err){
                            document.getElementById('wplm-stat-server').textContent='خطا';
                            document.getElementById('wplm-stat-server').className='wplm-conn-stat-value error';
                            document.getElementById('wplm-stat-server-detail').textContent='خطا در اتصال';
                            document.getElementById('wplm-connection-details').style.display='block';
                            document.getElementById('wplm-connection-response').textContent='خطای JavaScript: '+err.message;
                        }).finally(function(){
                            btn.disabled=false;
                            btn.classList.remove('is-loading');
                            btn.innerHTML='<span class="dashicons dashicons-yes-alt" style="margin-left:4px"></span>آزمایش ارتباط';
                        });
                    });
                });
                </script>
                <script>
                document.addEventListener('click',function(e){
                    var btn=e.target.closest('[data-wplm-version-check]');
                    if(!btn){return;}
                    e.preventDefault();
                    var slug=btn.getAttribute('data-product')||'';
                    var row=document.getElementById('wplm-product-'+slug);
                    var target=row?row.querySelector('[data-wplm-latest-version]'):null;
                    btn.classList.add('is-loading');
                    var body=new URLSearchParams();
                    body.append('action','wplm_manager_check_server_version');
                    body.append('nonce',btn.getAttribute('data-nonce')||'');
                    body.append('product_slug',slug);
                    fetch(ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()})
                    .then(function(r){return r.json();})
                    .then(function(res){
                        if(target){
                            if(res&&res.success){
                                target.innerHTML='<span class=\"wplm-version-pill '+(res.data.update_available?'update':'')+'\">'+res.data.latest_version+'</span><small class=\"wplm-version-note\">'+res.data.message+'</small>';
                            }else{
                                target.innerHTML='<span class=\"wplm-version-pill\">—</span><small class=\"wplm-version-note\">'+((res&&res.data&&res.data.message)?res.data.message:'خطا در بررسی')+'</small>';
                            }
                        }
                    }).catch(function(){if(target){target.innerHTML='<span class=\"wplm-version-pill\">—</span><small class=\"wplm-version-note\">خطا در ارتباط</small>';}})
                    .finally(function(){btn.classList.remove('is-loading');});
                });
                </script>
                <div class="wplm-manager-card">
                    <div class="wplm-manager-card-head"><h2>افزونه‌های متصل فعال</h2><span class="wplm-manager-count"><?php echo esc_html(count($clients)); ?> افزونه</span></div>
                    <div style="overflow:auto">
                    <table class="widefat striped wplm-manager-table">
                        <thead><tr><th>افزونه</th><th>وضعیت</th><th>کلید لایسنس</th><th>آخرین نسخه سرور</th><th>آخرین بررسی</th><th>عملیات</th></tr></thead>
                        <tbody>
                        <?php foreach ($clients as $slug => $client) :
                            $config = method_exists($client, 'get_config') ? $client->get_config() : [];
                            $status = method_exists($client, 'status') ? $client->status() : 'inactive';
                            $data = method_exists($client, 'data') ? $client->data() : [];
                            $license = method_exists($client, 'get_license_key') ? $client->get_license_key() : '';
                            $form_id = 'wplm-license-form-' . sanitize_key($slug);
                            $initial = function_exists('mb_substr') ? mb_substr((string) ($config['plugin_name'] ?? $slug), 0, 1) : substr((string) ($config['plugin_name'] ?? $slug), 0, 1);
                            $latest = self::client_latest_version($slug, $client);
                        ?>
                            <tr id="wplm-product-<?php echo esc_attr($slug); ?>">
                                <td data-label="افزونه"><div class="wplm-plugin-title"><span class="wplm-plugin-icon"><?php echo esc_html($initial); ?></span><div><strong><?php echo esc_html($config['plugin_name'] ?? $slug); ?></strong><br><span class="wplm-muted"><?php echo esc_html($config['plugin_file'] ?? ''); ?></span></div></div></td>
                                <td data-label="وضعیت"><span class="wplm-status-pill <?php echo esc_attr($status); ?>"><?php echo 'active' === $status ? 'فعال' : 'غیرفعال'; ?></span><small class="wplm-license-message"><?php echo esc_html($data['message'] ?? ''); ?></small></td>
                                <td data-label="کلید لایسنس"><form id="<?php echo esc_attr($form_id); ?>" class="wplm-license-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="wplm_client_manager_save"><input type="hidden" name="product_slug" value="<?php echo esc_attr($slug); ?>"><?php wp_nonce_field('wplm_client_manager_action'); ?><input type="text" name="license_key" value="<?php echo esc_attr($license); ?>" autocomplete="off" placeholder="XXXX-XXXX-XXXX-XXXX"></form></td>
                                <td data-label="آخرین نسخه سرور" data-wplm-latest-version><span class="wplm-version-pill <?php echo !empty($latest['update_available']) ? 'update' : ''; ?>"><?php echo esc_html($latest['latest_version'] ?? '—'); ?></span><small class="wplm-version-note"><?php echo esc_html($latest['message'] ?? ''); ?></small></td><td data-label="آخرین بررسی"><?php echo !empty($data['checked_at']) ? esc_html(mysql2date('Y/m/d H:i', $data['checked_at'])) : '—'; ?><br><code><?php echo esc_html($data['code'] ?? '—'); ?></code></td>
                                <td data-label="عملیات"><div class="wplm-manager-actions"><button form="<?php echo esc_attr($form_id); ?>" class="button wplm-icon-btn is-primary" title="ذخیره و فعال‌سازی" aria-label="ذخیره و فعال‌سازی"><span class="dashicons dashicons-yes-alt"></span></button><button type="button" class="button wplm-icon-btn is-version" title="بررسی نسخه جدید سرور" aria-label="بررسی نسخه جدید سرور" data-wplm-version-check data-product="<?php echo esc_attr($slug); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('wplm_manager_version_check')); ?>"><span class="dashicons dashicons-cloud"></span></button><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="wplm_client_manager_check"><input type="hidden" name="product_slug" value="<?php echo esc_attr($slug); ?>"><?php wp_nonce_field('wplm_client_manager_action'); ?><button class="button wplm-icon-btn" title="بررسی مجدد" aria-label="بررسی مجدد"><span class="dashicons dashicons-update"></span></button></form><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('لایسنس ذخیره‌شده حذف شود؟')"><input type="hidden" name="action" value="wplm_client_manager_clear"><input type="hidden" name="product_slug" value="<?php echo esc_attr($slug); ?>"><?php wp_nonce_field('wplm_client_manager_action'); ?><button class="button wplm-icon-btn is-danger" title="حذف لایسنس" aria-label="حذف لایسنس"><span class="dashicons dashicons-trash"></span></button></form></div></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$clients) : ?><tr><td colspan="6">هنوز افزونه فعالی که از WPLM Client Kit استفاده کند شناسایی نشده است.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>

            </div>
            <?php
        }

        private static function client_latest_version(string $slug, $client, bool $force = false): array
        {
            $config = method_exists($client, 'get_config') ? $client->get_config() : [];
            $license = method_exists($client, 'get_license_key') ? $client->get_license_key() : '';
            $plugin_file = (string) ($config['plugin_file'] ?? '');
            $local_version = self::local_plugin_version($plugin_file);
            $api_base = !empty($config['api_base']) ? untrailingslashit((string) $config['api_base']) : 'https://amolnovin.ir/wp-json/wplm/v1';
            $api_url = $api_base . '/updates/check';
            $domain = method_exists($client, 'domain') ? $client->domain() : self::domain();
            $cache_key = 'wplm_manager_latest_' . md5($slug . '|' . $plugin_file . '|' . $local_version . '|' . $license);
            if ($force) {
                delete_transient($cache_key);
            }
            $cached = get_transient($cache_key);
            if (!$force && is_array($cached)) {
                return $cached;
            }

            $response = wp_remote_post($api_url, [
                'timeout' => 12,
                'redirection' => 3,
                'sslverify' => true,
                'headers' => ['Accept' => 'application/json', 'User-Agent' => 'WPLM-Amolnovin-License-Manager/1.0; ' . home_url('/')],
                'body' => [
                    'product_slug' => $slug,
                    'plugin_file' => $plugin_file,
                    'version' => $local_version ?: '0.0.0',
                    'license_key' => $license,
                    'domain' => $domain,
                ],
            ]);

            if (is_wp_error($response)) {
                $result = ['success' => false, 'latest_version' => '—', 'update_available' => false, 'message' => $response->get_error_message()];
                set_transient($cache_key, $result, 30 * MINUTE_IN_SECONDS);
                return $result;
            }

            $raw = (string) wp_remote_retrieve_body($response);
            $body = json_decode($raw, true);
            $is_json = is_array($body);
            $body = $body ?? [];
            $ok = !empty($body['success']);
            $latest = $body['latest_version'] ?? $body['new_version'] ?? $body['version'] ?? '—';

            if (!$is_json) {
                // سرور صفحه خطا (مثلاً خطای پایگاه‌داده وردپرس) برگردانده -> خطای موقت
                $result = [
                    'success' => false,
                    'latest_version' => '—',
                    'update_available' => false,
                    'message' => 'خطای موقت سرور؛ در بررسی دوباره تلاش کنید.',
                ];
            } else {
                $result = [
                    'success' => $ok,
                    'latest_version' => $latest,
                    'update_available' => !empty($body['update_available']),
                    'message' => $ok ? (!empty($body['update_available']) ? 'نسخه جدید موجود است' : 'به‌روز است') : ($body['code'] ?? 'خطا در دریافت نسخه'),
                ];
            }
            set_transient($cache_key, $result, $ok ? 3 * HOUR_IN_SECONDS : 30 * MINUTE_IN_SECONDS);
            return $result;
        }

        private static function local_plugin_version(string $plugin_file): string
        {
            if (!$plugin_file || !defined('WP_PLUGIN_DIR')) {
                return '';
            }
            $path = trailingslashit(WP_PLUGIN_DIR) . $plugin_file;
            if (!is_readable($path)) {
                return '';
            }
            if (!function_exists('get_plugin_data')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $data = get_plugin_data($path, false, false);
            return isset($data['Version']) ? (string) $data['Version'] : '';
        }

        private static function domain(): string
        {
            $host = wp_parse_url(home_url(), PHP_URL_HOST);
            $host = $host ? strtolower($host) : home_url();
            $host = preg_replace('/^www\./', '', (string) $host);
            return sanitize_text_field($host);
        }

        public static function ajax_check_server_version(): void
        {
            if (!current_user_can(self::$capability)) {
                wp_send_json_error(['message' => 'دسترسی غیرمجاز است.'], 403);
            }
            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            if (!wp_verify_nonce($nonce, 'wplm_manager_version_check')) {
                wp_send_json_error(['message' => 'بررسی امنیتی ناموفق بود.'], 403);
            }
            $slug = isset($_POST['product_slug']) ? sanitize_key(wp_unslash($_POST['product_slug'])) : '';
            $client = self::$clients[$slug] ?? null;
            if (!$client) {
                wp_send_json_error(['message' => 'افزونه قابل شناسایی نیست.'], 404);
            }
            $result = self::client_latest_version($slug, $client, true);
            delete_site_transient('update_plugins');
            if (function_exists('wp_update_plugins')) {
                wp_update_plugins();
            }
            wp_send_json_success([
                'latest_version' => $result['latest_version'] ?? '—',
                'update_available' => !empty($result['update_available']),
                'message' => $result['message'] ?? '',
            ]);
        }

        public static function ajax_test_connection(): void
        {
            if (!current_user_can(self::$capability)) {
                wp_send_json_error(['message' => 'دسترسی غیرمجاز است.'], 403);
            }
            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            if (!wp_verify_nonce($nonce, 'wplm_manager_connection_test')) {
                wp_send_json_error(['message' => 'بررسی امنیتی ناموفق بود.'], 403);
            }
            $result = self::test_connection(true);
            wp_send_json_success($result);
        }

        public static function test_connection(bool $save_cache = false): array
        {
            $api_base = 'https://amolnovin.ir/wp-json/wplm/v1';
            $rest_route_base = 'https://amolnovin.ir/?rest_route=/wplm/v1';
            $domain = self::domain();
            $result = [
                'success' => false,
                'server_online' => false,
                'server_message' => '',
                'ssl_valid' => false,
                'ssl_message' => '',
                'response_time' => 0,
                'response_code' => 0,
                'license_api_ok' => false,
                'license_message' => '',
                'raw_response' => '',
                'message' => '',
            ];

            // 1. بررسی SSL
            $ssl_context = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ]);
            $ssl_check = @stream_socket_client(
                'ssl://amolnovin.ir:443',
                $ssl_err_no,
                $ssl_err_str,
                5,
                STREAM_CLIENT_CONNECT,
                $ssl_context
            );
            if ($ssl_check) {
                $result['ssl_valid'] = true;
                $result['ssl_message'] = 'گواهی SSL معتبر است';
                fclose($ssl_check);
            } else {
                $result['ssl_message'] = 'خطا: ' . ($ssl_err_str ?: 'بررسی SSL ناموفق بود');
            }

            // 2. بررسی ارتباط با API با استفاده از wp_remote_post
            $start_time = microtime(true);
            $response = wp_remote_post($api_base . '/license/check', [
                'timeout' => 15,
                'redirection' => 3,
                'sslverify' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'WPLM-Connection-Test/1.0; ' . home_url('/'),
                ],
                'body' => json_encode([
                    'product_slug' => 'connection-test',
                    'license_key' => 'TEST-ONLY-CHECK',
                    'domain' => $domain,
                ]),
            ]);
            $result['response_time'] = microtime(true) - $start_time;

            if (is_wp_error($response)) {
                $result['server_message'] = $response->get_error_message();
                $result['message'] = 'خطا در اتصال به سرور: ' . $response->get_error_message();
                $result['raw_response'] = 'WP_Error: ' . $response->get_error_message() . "\nکد خطا: " . $response->get_error_code();
            } else {
                $result['response_code'] = (int) wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $result['raw_response'] = $body;

                $html_404 = $result['response_code'] === 404 && !preg_match('/[{\[\"]/i', $body);
                $is_json = is_array(json_decode((string) $body, true));

                if ($result['response_code'] >= 200 && $result['response_code'] < 300) {
                    $result['server_online'] = true;
                    $result['server_message'] = 'سرور پاسخ می‌دهد (کد ' . $result['response_code'] . ')';
                    $json = json_decode((string) $body, true);
                    if (is_array($json)) {
                        $result['license_api_ok'] = true;
                        $result['license_message'] = 'API لایسنس فعال است';
                    }
                } elseif ($html_404) {
                    // 404 صفحه HTML یعنی Rewrite در وب‌سرور خراب است؛ REST از طریق ?rest_route تلاش می‌شود
                    $result['server_online'] = true;
                    $result['server_message'] = 'سرور آنلاین است اما Pretty Permalink/rewrite سمت وب‌سرور خراب است';
                    $result['message'] = 'سرور آنلاین است ولی مسیر /wp-json و لینک‌های تمیز 404 می‌دهند (احتمالاً .htaccess یا تنظیم permalink).';
                    $result['rest_route_fallback'] = self::test_rest_route_fallback($rest_route_base, $domain, $result);
                } elseif ($result['response_code'] === 400 || $is_json) {
                    // پاسخ JSON دریافت شد - API کار می‌کند
                    $result['server_online'] = true;
                    $result['server_message'] = 'API پاسخ می‌دهد (کد ' . $result['response_code'] . ')';
                    $json = json_decode($body, true);
                    if (is_array($json) && !empty($json['code'])) {
                        $result['license_api_ok'] = true;
                        $result['license_message'] = 'API لایسنس فعال است: ' . $json['code'];
                    }
                } else {
                    $result['server_message'] = 'کد پاسخ: ' . $result['response_code'];
                    $result['message'] = 'سرور پاسخ داد اما با کد: ' . $result['response_code'];
                }
            }

            // 3. اگر wp_remote_post کار نکرد، روش جایگزین با curl
            if (!$result['server_online'] && !is_wp_error($response)) {
                // احتمالاً پاسخ JSON موفق داشتیم
                $body = wp_remote_retrieve_body($response);
                $json = json_decode($body, true);
                if (is_array($json)) {
                    $result['server_online'] = true;
                    if (!empty($json['success']) || !empty($json['code'])) {
                        $result['license_api_ok'] = true;
                        $result['license_message'] = 'API لایسنس: ' . ($json['code'] ?? $json['message'] ?? 'OK');
                    }
                }
            }

            // اگر /wp-json 404 سمت سرور داد، مسیر جایگزین ?rest_route= را امتحان کن
            if (!empty($result['html_404_detected']) && empty($result['license_api_ok'])) {
                $result['server_online'] = true;
            }

            // اگر هیچ روشی کار نکرد
            if (!$result['server_online'] && is_wp_error($response)) {
                // تلاش با file_get_contents به عنوان آخرین راه
                $alt_context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/json\r\nAccept: application/json\r\nUser-Agent: WPLM-Connection-Test/1.0\r\n",
                        'content' => json_encode(['product_slug' => 'test', 'domain' => $domain]),
                        'timeout' => 10,
                        'ignore_errors' => true,
                    ],
                    'ssl' => ['verify_peer' => true],
                ]);
                $alt_response = @file_get_contents($api_base . '/license/check', false, $alt_context);
                if ($alt_response !== false && !empty($http_response_header)) {
                    $result['server_online'] = true;
                    $result['server_message'] = 'سرور با روش جایگزین پاسخ داد';
                    $result['raw_response'] = $alt_response;
                    $result['license_message'] = 'ارتباط از طریق fallback برقرار شد';
                }
            }

            // تنظیم پیام نهایی
            if ($result['server_online'] && $result['ssl_valid']) {
                $result['success'] = true;
                $result['message'] = 'ارتباط با سرور با موفقیت برقرار شد';
            } elseif ($result['server_online'] && !$result['ssl_valid']) {
                $result['success'] = true;
                $result['message'] = 'سرور پاسخ می‌دهد اما SSL نامعتبر است';
            } else {
                $result['message'] = $result['server_message'] ?: 'امکان اتصال به سرور وجود ندارد';
            }

            // ذخیره در کش
            if ($save_cache) {
                $cache_data = [
                    'success' => $result['success'],
                    'server_online' => $result['server_online'],
                    'ssl_valid' => $result['ssl_valid'],
                    'ssl_message' => $result['ssl_message'],
                    'response_time' => $result['response_time'],
                    'response_code' => $result['response_code'],
                    'license_api_ok' => $result['license_api_ok'],
                    'license_message' => $result['license_message'],
                    'checked_at' => current_time('mysql'),
                ];
                update_option('wplm_last_connection_check', $cache_data, false);
            }

            return $result;
        }

        private static function test_rest_route_fallback(string $base, string $domain, array &$result): array
        {
            $response = wp_remote_get($base, [
                'timeout' => 12,
                'sslverify' => true,
                'headers' => ['Accept' => 'application/json', 'User-Agent' => 'WPLM-Connection-Test-Fallback/1.0; ' . home_url('/')],
            ]);
            if (is_wp_error($response)) {
                return ['ok' => false, 'message' => $response->get_error_message()];
            }
            $code = (int) wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $json = json_decode((string) $body, true);
            if ($code === 200 && is_array($json) && isset($json['routes'])) {
                $result['license_api_ok'] = true;
                $result['license_message'] = 'REST از طریق ?rest_route= سالم است؛ فقط rewrite سرور باید اصلاح شود';
                return ['ok' => true, 'message' => 'REST با fallback سالم است'];
            }
            return ['ok' => false, 'message' => 'پاسخ غیرمنتظره کد ' . $code];
        }

        public static function handle_save(): void { self::handle('save'); }
        public static function handle_check(): void { self::handle('check'); }
        public static function handle_clear(): void { self::handle('clear'); }
        public static function handle_connection_test(): void
        {
            if (!current_user_can(self::$capability)) {
                wp_die(esc_html__('دسترسی غیرمجاز است.', 'wplm-amolnovin-license-manager'));
            }
            check_admin_referer('wplm_client_manager_action');
            $result = self::test_connection(true);
            if (!empty($result['success'])) {
                wp_safe_redirect(self::page_url(['wplm_connection_ok' => 1]));
            } else {
                $msg = rawurlencode($result['message'] ?? 'خطا در ارتباط');
                wp_safe_redirect(self::page_url(['wplm_error' => $msg]));
            }
            exit;
        }

        private static function handle(string $mode): void
        {
            if (!current_user_can(self::$capability)) {
                wp_die(esc_html__('دسترسی غیرمجاز است.', 'wplm-amolnovin-license-manager'));
            }
            check_admin_referer('wplm_client_manager_action');
            $slug = isset($_POST['product_slug']) ? sanitize_key(wp_unslash($_POST['product_slug'])) : '';
            $client = self::$clients[$slug] ?? null;
            if (!$client) {
                wp_safe_redirect(self::page_url(['wplm_error' => rawurlencode('افزونه موردنظر فعال یا قابل شناسایی نیست.')])) ;
                exit;
            }
            if ('save' === $mode) {
                $license = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
                if (!$license && method_exists($client, 'get_license_key')) {
                    $license = (string) $client->get_license_key();
                }
                if (method_exists($client, 'save_and_activate')) {
                    $client->save_and_activate($license);
                }
                wp_safe_redirect(self::page_url(['wplm_saved' => 1, 'product' => $slug]) . '#wplm-product-' . rawurlencode($slug));
                exit;
            }
            if ('check' === $mode) {
                if (method_exists($client, 'is_active')) {
                    $client->is_active(true);
                }
                wp_safe_redirect(self::page_url(['wplm_checked' => 1, 'product' => $slug]) . '#wplm-product-' . rawurlencode($slug));
                exit;
            }
            if ('clear' === $mode) {
                if (method_exists($client, 'clear_saved_license')) {
                    $client->clear_saved_license();
                }
                wp_safe_redirect(self::page_url(['wplm_cleared' => 1, 'product' => $slug]) . '#wplm-product-' . rawurlencode($slug));
                exit;
            }
        }

        private static function persist_client($client): void
        {
            if (!is_object($client) || !method_exists($client, 'get_product_slug') || !method_exists($client, 'get_config')) {
                return;
            }
            $config = $client->get_config();
            $slug = sanitize_key((string) $client->get_product_slug());
            $items = get_option('wplm_client_kit_registered_products', []);
            $items = is_array($items) ? $items : [];
            $items[$slug] = ['product_slug' => $slug, 'plugin_name' => $config['plugin_name'] ?? $slug, 'plugin_file' => $config['plugin_file'] ?? '', 'api_base' => $config['api_base'] ?? '', 'registered_at' => current_time('mysql')];
            update_option('wplm_client_kit_registered_products', $items, false);
        }
    }
}

WPLM_Amolnovin_License_Manager::boot();

if (!class_exists('WPLM_Amolnovin_License_Manager_Updater')) {
    final class WPLM_Amolnovin_License_Manager_Updater
    {
        private $api_url = 'https://amolnovin.ir/wp-json/wplm/v1/updates/check';
        private $product_slug = 'wplm-amolnovin-license-manager';
        private $plugin_file = 'wplm-amolnovin-license-manager/wplm-amolnovin-license-manager.php';
        private $plugin_slug = 'wplm-amolnovin-license-manager';
        private $version = '1.0.7';
        private $name = 'لایسنس منیجر آمل نوین';
        private $last_response = null;

        public function __construct()
        {
            add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
            add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
            add_action('upgrader_process_complete', [$this, 'clear_cache_after_update'], 10, 2);
        }

        public function check_update($transient)
        {
            if (!is_object($transient)) {
                $transient = new stdClass();
            }
            if (empty($transient->checked)) {
                return $transient;
            }

            $data = $this->request_update();
            if (empty($data['success'])) {
                return $transient;
            }

            $this->last_response = $data;
            $version = $data['new_version'] ?? $data['version'] ?? '';
            if (!empty($data['update_available']) && $version) {
                $transient->response[$this->plugin_file] = (object) [
                    'id' => $this->product_slug,
                    'slug' => $this->plugin_slug,
                    'plugin' => $this->plugin_file,
                    'new_version' => $version,
                    'url' => $data['url'] ?? 'https://amolnovin.ir',
                    'package' => $data['package'] ?? $data['download_url'] ?? '',
                    'tested' => $data['tested'] ?? '',
                    'requires' => $data['requires'] ?? '',
                    'requires_php' => $data['requires_php'] ?? '',
                ];
            } else {
                $transient->no_update[$this->plugin_file] = (object) [
                    'id' => $this->product_slug,
                    'slug' => $this->plugin_slug,
                    'plugin' => $this->plugin_file,
                    'new_version' => $this->version,
                    'url' => $data['url'] ?? 'https://amolnovin.ir',
                    'package' => '',
                ];
            }
            return $transient;
        }

        public function plugin_info($result, $action, $args)
        {
            if ('plugin_information' !== $action || empty($args->slug) || $args->slug !== $this->plugin_slug) {
                return $result;
            }

            $data = $this->last_response ?: $this->request_update();
            if (empty($data['success'])) {
                return $result;
            }

            return (object) [
                'name' => $data['name'] ?? $this->name,
                'slug' => $this->plugin_slug,
                'version' => $data['version'] ?? $this->version,
                'author' => 'آمل نوین',
                'homepage' => $data['homepage'] ?? $data['url'] ?? 'https://amolnovin.ir',
                'requires' => $data['requires'] ?? '',
                'tested' => $data['tested'] ?? '',
                'requires_php' => $data['requires_php'] ?? '',
                'download_link' => $data['download_url'] ?? $data['package'] ?? '',
                'sections' => $data['sections'] ?? [
                    'description' => 'مدیریت مرکزی لایسنس افزونه‌های آمل نوین.',
                    'changelog' => $data['changelog'] ?? '',
                ],
            ];
        }

        public function clear_cache_after_update($upgrader, $options): void
        {
            if (!empty($options['plugins']) && is_array($options['plugins']) && in_array($this->plugin_file, $options['plugins'], true)) {
                delete_site_transient($this->cache_key());
            }
        }

        private function request_update(): array
        {
            $force = $this->is_forced_check();
            if (!$force) {
                $cached = get_site_transient($this->cache_key());
                if (is_array($cached)) {
                    return $cached;
                }
            }

            $response = wp_remote_post($this->api_url, [
                'timeout' => 15,
                'redirection' => 3,
                'sslverify' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'WPLM-Amolnovin-License-Manager-Updater/1.0; ' . home_url('/'),
                ],
                'body' => [
                    'product_slug' => $this->product_slug,
                    'plugin_file' => $this->plugin_file,
                    'version' => $this->version,
                    'license_key' => '',
                    'license_required' => '0',
                    'manager_update' => '1',
                    'domain' => $this->domain(),
                ],
            ]);

            if (is_wp_error($response)) {
                return ['success' => false, 'code' => $response->get_error_code(), 'message' => $response->get_error_message()];
            }

            $data = json_decode((string) wp_remote_retrieve_body($response), true);
            $data = is_array($data) ? $data : ['success' => false, 'code' => 'bad_response'];
            set_site_transient($this->cache_key(), $data, !empty($data['success']) ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS);
            return $data;
        }

        private function is_forced_check(): bool
        {
            return !empty($_GET['force-check']) || !empty($_GET['wplm_force_update_check']) || (defined('WP_CLI') && WP_CLI);
        }

        private function cache_key(): string
        {
            return 'wplm_amn_manager_update_' . md5($this->product_slug . '|' . $this->version);
        }

        private function domain(): string
        {
            $host = wp_parse_url(home_url(), PHP_URL_HOST);
            $host = $host ? strtolower($host) : home_url();
            $host = preg_replace('/^www\./', '', (string) $host);
            return sanitize_text_field($host);
        }
    }
}

new WPLM_Amolnovin_License_Manager_Updater();
