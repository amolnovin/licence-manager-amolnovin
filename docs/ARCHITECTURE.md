# معماری و ساختار (ARCHITECTURE)

این مستند معماری و ساختار هر سه جزء system را توضیح می‌دهد.

---

## 1. wp-plugin-market-manager — سامانه فروش و لایسنس افزونه و قالب

نسخه: **1.2.3** | WordPress ≥ 6.2 | PHP ≥ 7.4 | GPLv2

افزونه اصلی که روی سایت فروش (`amolnovin.ir`) نصب می‌شود و در الویتhapی‌های زیر کاملاً مستقل عمل می‌کند: فروش، معرفی، لایسنس آنلاین، بروزرسانی و پیامک برای افزونه‌ها و قالب‌های وردپرس.

### اصول طراحی

- بدون وابستگی به افزونه فروشگاهی دیگر.
- عدم تداخل با وردپرس: namespace اختصاصی `WPLM`، prefix دیتابیس `wplm_` و capability اختصاصی `manage_wplm`.
- امنیت: nonce، capability، sanitize/escape، `$wpdb->prepare` و rate limit روی APIها و شروع خرید.
- سرعت: assetهای مدیریت فقط در صفحات افزونه و asset عمومی فقط هنگام shortcodeها.
- توسعه‌پذیری: جداسازی Repository، REST Controller، Service و View.

### ساختار فایل‌ها

```text
wp-plugin-market-manager/
├── wp-plugin-market-manager.php      # گیرجری اصلی، constants، فعال‌سازی
├── uninstall.php                     # پاک‌سازی روی ایجایونتال (اختیاری)
├── assets/
│   ├── admin.css                     # اسلایدر پنل مدیریت
│   ├── admin.js                      # مودال محصول/لایسنس، AJAX
│   └── public.css                    # استایل فروشگاه، کارت، سبد، حساب کاربری
├── admin/views/
│   ├── dashboard.php                 # داشبورد: آمار فروش، لایسنس، کلیک
│   ├── products.php                  # محصولات (لیست + مودال add/edit)
│   ├── categories.php                # دسته‌بندی
│   ├── sales.php                     # فهرستی سفارشات + آمار
│   ├── discounts.php                 # کدهای تخفیف و کمپین
│   ├── requests.php                  # درخواست‌های لایسنس отклики
│   ├── licenses.php                  # مدیریت لایسنس (create/edit/extend/remove)
│   ├── updates.php                   # مدیریت نسخه‌ها و آپلود ZIP
│   ├── sms.php                       # تنظیمات پیامک IPPanel
│   ├── settings.php                  # تنظیمات عمومی (درگاه، قیمت، دامنه)
│   ├── header.php / footer.php       # header و footer پنل مدیریت
├── client/
│   ├── wplm-plugin-updater.php       # آپدیتر قدیمی (class WPLM_Plugin_Updater)
│   └── wplm-amolnovin-license-manager.zip  # بسته افزونه جداگانه (همان نسخه 1.0.7)
├── includes/
│   ├── Autoloader.php                # autoloader namespace WPLM
│   ├── Plugin.php                    # boot،  registracija hooks/shortcodes/routes
│   ├── Activator.php                 # ایجاد جدول‌ها، capability، صفحات default
│   ├── Database.php                  # helpers: table(), now()
│   ├── Options.php                   # تنظیمات (wplm_settings)
│   ├── Security.php                  # can(), require_cap(), nonce, rate_limit, key gen
│   ├── Assets.php                    # admin() و public()
│   ├── Shortcodes.php                # [wplm_store] [wplm_account] [wplm_payment_result] ...
│   ├── Admin/
│   │   ├── Menu.php                  # منوهای admin + view loader
│   │   └── Actions.php               # handlers admin-post و ajax
│   ├── Repositories/
│   │   ├── ProductRepository.php     # جدول wp_wplm_products
│   │   ├── CategoryRepository.php    # جدول wp_wplm_categories
│   │   ├── OrderRepository.php       # جدول‌های wp_wplm_orders و wp_wplm_order_items
│   │   ├── LicenseRepository.php     # لایسنس: create/save/status/extend/validate/domains
│   │   ├── VersionRepository.php     # نسخه‌ها + make_current + download count
│   │   └── DiscountRepository.php    # تخفیف: validate_for_product/calculate_amount
│   ├── REST/
│   │   ├── LicenseController.php     # /wplm/v1/license/{request,activate,check}
│   │   └── UpdateController.php      # /wplm/v1/updates/check
│   ├── Services/
│   │   ├── PurchaseService.php       # سبد، ثبت سفارش، callback زیبال/زرین‌پال، صدور لایسنس
│   │   ├── ZarinpalService.php       # درگاه زرین‌پال
│   │   ├── ZibalService.php          # درگاه زیبال
│   │   ├── PackageService.php        # آپلود امن ZIP، لینک دانلود زمان‌دار، token
│   │   ├── AccountService.php        # تغییر رمز عبور حساب کاربری
│   │   ├── LicenseNotificationService.php  # ایمیل/پیامک لایسنس
│   │   ├── SmsService.php            # پیامک IPPanel Core
│   │   └── Vendor/ippanel-core-addon-helper.php  # SDK پیامک IPPanel
│   └── Vendor/
│       └── ippanel-core-addon-helper.php
├── languages/.gitkeep
└── docs/
    ├── STRUCTURE.md
    └── CLIENT-UPDATER.md
```

### جداولی دیتابیس

| جدول | کاربرد |
|---|---|
| `wp_wplm_categories` | دسته‌بندی افزونه و قالب |
| `wp_wplm_products` | محصولات، قیمت، دسته، توضیحات، نسخه و تصویر |
| `wp_wplm_orders` | سفارش‌ها، وضعیت پرداخت، trackId زیبال، اطلاعات مشتری |
| `wp_wplm_order_items` | آیتم‌های سفارش چندآیتمی + مبلغ |
| `wp_wplm_license_requests` | درخواست‌های ایجاد لایسنس |
| `wp_wplm_licenses` | لایسنس‌ها (keys، دامنه‌ها، انقضا، وضعیت) |
| `wp_wplm_versions` | نسخه‌ها، changelog، requires، tested، requires_php، file_path |
| `wp_wplm_discounts` | کد تخفیف، محدودیت، تاریخ، سقف استفاده |
| `wp_wplm_logs` | لاگ رویدادها |

### صفحات عمومی (shortcode)

| Shortcode | کاربرد |
|---|---|
| `[wplm_store]` | فروشگاه (لیست + دسته + نوار navigation + سبد با `?cart=1`) |
| `[wplm_products]` | فقط لیست محصولات |
| `[wplm_latest_carousel]` / `[wplm_product_carousel]` | کاروسل 5 محصول جدید |
| `[wplm_account]` | حساب کاربری: پیشخوان، سفارشات، خریدها، تنظیمات |
| `[wplm_payment_result]` | نتیجه خرید |

### تنظیمات کلیدی (Options.php — `wplm_settings`)

```text
license_expire_days      365            # مدت اعتبار لایسنس
allowed_domains          1              # تعداد دامنه مجاز
currency                 IRT
price_unit               toman
sms_enabled / sms_provider / sms_api_key / sms_sender
sms_license_created_mode   text/template
notify_admin_email
default_gateway          zibal          # یا zarinpal
zibal_merchant / zibal_test_mode
zarinpal_merchant / zarinpal_sandbox
store_page_id / account_page_id / payment_result_page_id
```

### فرآیند خرید اختصاصی

1. محصول فعال در `[wplm_store]` نمایش داده می‌شود.
2. کاربر وارد تک‌صفحه محصول می‌شود: `?product=product-slug`.
3. فرم خرید را تکمیل می‌کند (نام، ایمیل، موبایل، دامنه، کد تخفیف).
4. حساب کاربری، در صورت نبود، ایجاد می‌شود.
5. سفارش `pending` در `wplm_orders` ساخته می‌شود.
6. درخواست پرداخت به درگاه (زیبال/زرین‌پال) ارسال می‌شود.
7. کاربر به درگاه هدایت می‌شود.
8. callback به `admin-post.php?action=wplm_zibal_callback` (یا zarinpal) برمی‌گردد.
9. تراکنش verify می‌شود.
10. برای هر آیتم لایسنس‌دار، لایسنس صادر و به سفارش وصل می‌شود.
11. نتیجه خرید و خریدهای حساب کاربری نمایش داده می‌شود.

### license_mode محصول

- `draft` — پیش‌نویس
- `active_license` — فعال لایسنس‌دار (پس از خرید لایسنس صادر می‌شود)
- `active_no_license` — فعال بدون لایسنس (خرید/ثبت سفارش انجام می‌شود اما لایسنس صادر نمی‌شود)

### IPPanel Core

```text
slug: wp-plugin-market-manager
name: سامانه فروش و لایسنس افزونه و قالب
integration_code: WPLM-LICENSE-HUB
namespace: wplm-ippanel/v1
callback_url: /wp-json/wplm-ippanel/v1/receive-key
```

---

## 2. wplm-amolnovin-license-manager — لایسنس منیجر آمل نوین (افزونه مستقل)

نسخه: **1.0.7** | WordPress ≥ 6.0 | PHP ≥ 7.4

افزونه مستقل که پنل مرکزی مدیریت لایسنس‌ها را می‌سازد. افزونه‌های مشتری که از `wplm-client-kit` استفاده می‌کنند، از طریق hooks به این افزونه ثبت می‌شوند (`WPLM_Amolnovin_License_Manager::register($client)`) و اگر نصب/فعال نباشد، notice نصب/فعال‌سازی نمایش می‌دهد.

### کلاس‌ها

```text
WPLM_Amolnovin_License_Manager
  ├── boot()                      # ثبت hooks
  ├── register($client)           # ثبت افزونه متصل (slug => client) + persist
  ├── register_menu()             # add_options_page: تنظیمات ← لایسنس منیجر آمل نوین
  ├── render_page()               # جدول افزونه‌های متصل: وضعیت/کلیدها/نسخه سرور + تنظیمات
  ├── client_latest_version()     # بررسی نسخه جدید سرور برای هر افزونه
  ├── local_plugin_version()      # خواندن نسخه محلی افزونه
  ├── ajax_check_server_version() # AJAX بررسی نسخه
  ├── ajax_test_connection()      # AJAX تست ارتباط
  ├── test_connection()           # بررسی REST، SSL، code HTTP، زمان پاسخ
  ├── test_rest_route_fallback()  # fallback ?rest_route= اگر /wp-json 404 بدهد
  ├── handle_save() / handle_check() / handle_clear()   # admin-post handlers
  ├── handle_connection_test()    # admin-post handler
  └── persist_client()            # ذخیره client در option wplm_client_kit_registered_products

WPLM_Amolnovin_License_Manager_Updater
  ├── __construct()               # hooks pre_set_site_transient_update_plugins / plugins_api
  ├── check_update()              # بررسی آپدیت لایسنس منیجر از سرور amolnovin.ir
  ├── plugin_info()               # plugins_api
  ├── clear_cache_after_update()  # پاک‌سازی کش بعد از تکمیل آپدیت
  ├── request_update()            # درخواست API با license_required=0 برای force-check
  ├── is_forced_check() / cache_key() / domain()
```

### پنل مرکزی

صفحه `تنظیمات ← لایسنس منیجر آمل نوین` (option page، capability `manage_options`) شامل:

- کارت hero + وضعیت ارتباط با سرور.
- بخش «بررسی ارتباط با سرور»: آنلاین/آفلاین، گواهی SSL، کد HTTP، زمان پاسخ، response JSON، دکمه آزمایش AJAX.
- جدول افزونه‌های متصل فعال: نام، وضعیت (فعال/غیرفعال)، کلید لایسنس (قابل ویرایش)، آخرین نسخه سرور، آخرین بررسی، عملیات (ذخیره و فعال‌سازی، بررسی نسخه، بررسی مجدد، حذف لایسنس).
- انباره نتیجه تست در option ذخیره می‌شود.

### REST APIهای مورد استفاده توسط منیجر

- `POST /wp-json/wplm/v1/license/activate` — فعال‌سازی/ذخیره‌سازی کلید
- `POST /wp-json/wplm/v1/license/check` — بررسی وضعیت
- `POST /wp-json/wplm/v1/updates/check` — بررسی نسخه

---

## 3. wplm-client-kit — کیت کلاینت افزونه‌های قابل فروش

نسخه: License Client **1.4.0** / Updater **1.1.0**

کیت آماده برای قرار گرفتن داخل افزونه‌های قابل فروش و اتصال به `wp-plugin-market-manager` روی `amolnovin.ir`.

### ساختار

```text
wplm-client-kit/
├── includes/
│   ├── wplm-license-client.php      # class WPLM_Client_Kit_License
│   └── wplm-plugin-updater.php      # class WPLM_Client_Kit_Updater (+ alias WPLM_Plugin_Updater)
├── config-amolnovin-sample.php      # sample constants API base/update
├── example-plugin-main.php          # نمونه فایل اصلی افزونه مشتری
├── README.md
└── docs/
    ├── README-FA.md                 # آموزش کامل فارسی
    ├── QUICK-INTEGRATION.md         # راهنمای سریع
    ├── AMOLNOVIN-CONFIG.md          # تنظیمات آماده amolnovin.ir
    └── SECURITY.md                  # نکات امنیتی
```

### کلاس `WPLM_Client_Kit_License`

متدها:

```text
__construct($config)        # ثبت hooks: menu، admin-post، notices
register_menu()             # صفحه فعال‌سازی (تنظیمات ← لایسنس افزونه)
plugin_action_links()       # لینک‌های تکمیلی در صفحه افزونه‌ها
render_page()               # فرم فعال‌سازی + وضعیت
handle_save()               # ذخیره و فعال‌سازی لایسنس
admin_notice()              # اعلانات status
is_active($force)           # آیا لایسنس فعال است؟ (با cache)
guard($callback)            # سرپوش ندار defactivate: اجرای callback فقط اگر فعال
activate($license)          # POST /license/activate
check($license)             # POST /license/check
request($endpoint, $body)   # wrapper API
do_request()                # wp_remote_post + fallback ?rest_route=
looks_like_server_error()   # تشخیص HTMLخطای پایگاه‌داده به‌جای JSON
fallback_url()              # تبدیل /wp-json/... به ?rest_route=
store_result() / message_for_code()
register_with_manager()     # ثبت با افزونه لایسنس منیجر
manager_missing_notice()    # notice اگر منیجر نصب/فعال نباشد
get_config() / get_product_slug() / status() / data()
save_and_activate() / clear_saved_license()
normalize_api_url() / api_is_allowed() / page_url() / cache_key()
get_license_key() / set_license_key() / get_status() / set_status() / get_data()
clear_license() / domain()
```

### کلاس `WPLM_Client_Kit_Updater` (final)

```text
__construct($config)        # hooks: pre_set_site_transient_update_plugins / plugins_api / upgrader
check_update($transient)    # افزودن نسخه جدید به transient update_plugins
plugin_info($result, $action, $args)
clear_cache_after_update()
request_update()            # POST /updates/check
do_request()                # با license_key و fallback ?rest_route=
last_good_key() / get_license_key() / cache_key()
normalize_api_url() / api_is_allowed() / domain()
```

### پیکربندی

```php
$license = new WPLM_Client_Kit_License([
    'api_base'       => 'https://amolnovin.ir/wp-json/wplm/v1',
    'product_slug'   => 'your-product-slug',
    'plugin_file'    => __FILE__,
    'plugin_name'    => 'نام افزونه شما',
    'license_option' => 'your_plugin_license_key',
    'status_option'  => 'your_plugin_license_status',
    'data_option'    => 'your_plugin_license_data',
    'manager_required' => true,          # نمایش notice اگر منیجر فعال نباشد
    'manager_download_url' => 'https://amolnovin.ir/wp-content/plugins/.../wplm-amolnovin-license-manager.zip',
]);

new WPLM_Client_Kit_Updater([
    'api_url'        => 'https://amolnovin.ir/wp-json/wplm/v1/updates/check',
    'plugin_file'    => __FILE__,
    'plugin_slug'    => 'your-plugin-folder',
    'product_slug'   => 'your-product-slug',
    'version'        => YOUR_PLUGIN_VERSION,
    'license_option' => 'your_plugin_license_key',
    'license_required' => true,          # false برای محصولات بدون لایسنس
    'name'           => 'نام افزونه',
    'author'         => 'نام شما',
]);
```

### نکات مهندسی کیت

- HTTPS اجباری به‌صورت پیش‌فرض (`sslverify => true`).
- نؤnce و capability برای ذخیره لایسنس.
- cache وضعیت لایسنس برای جلوگیری از کندی.
- grace کوتاه هنگام خطای شبکه: اگر لایسنس قبلاً فعال بوده، وضعیت فعال حفظ می‌شود.
- fallback `?rest_route=` هنگام 404 `/wp-json`.
- HTML خام خطای پایگاه‌داده never در UI نمایش/ذخیره نمی‌شود.
- آپدیتر با آخرین پاسخ موفق (last good key) در خطای موقت ادامه می‌دهد.

---

## 4. تعامل بین اجزاء

```text
[افزونه مشتری (با wplm-client-kit)]
        │
        │ 1. فعال‌سازی: POST /license/activate
        │ 2. بررسی دوره‌ای: POST /license/check
        │ 3. آپدیت: POST /updates/check
        ▼
[wp-plugin-market-manager روی amolnovin.ir]
        │
        │ 4. لایسنس صادر/بررسی می‌شود، نسخه و ZIP آپلود می‌شود
        ▼
[wplm-amolnovin-license-manager روی amolnovin.ir]
        │
        │ 5. پنل مرکزی: ثبت/بررسی/حذف لایسنس همه افزونه‌ها
        ▼
[pnl admin: تنظیمات ← لایسنس منیجر آمل نوین]
```

## 5. مسیرهای مهم

| مسیر | توضیح |
|---|---|
| `/wp-json/wplm/v1/license/request` | درخواست لایسنس جدید (POST) |
| `/wp-json/wplm/v1/license/activate` | فعال‌سازی/ذخیره‌سازی لایسنس (POST) |
| `/wp-json/wplm/v1/license/check` | بررسی وضعیت لایسنس (POST) |
| `/wp-json/wplm/v1/updates/check` | بررسی نسخه/آپدیت (POST) |
| `/wp-json/wplm-ippanel/v1/receive-key` | callback IPPanel Core (POST) |
| `/wp-admin/admin-post.php?action=wplm_download_package&token=...` | لینک دانلود امن ZIP (زمان‌دار) |
| `/wp-admin/admin-post.php?action=wplm_zibal_callback` | callback زیبال |
| `/wp-admin/admin-post.php?action=wplm_zarinpal_callback` | callback زرین‌پال |
| `admin-post.php?action=wplm_add_to_cart` / `wplm_start_purchase` | سبد و شروع خرید |

## 6. محدودیت‌ها و نکات

- APIها rate limited (license_request: 15/5min، update_check: 120/5min).
- دانلود ZIP فقط از لینک امن سرور فروش؛ فایلی از افزونه مشتری مستقیم دانلود نمی‌شود.
-لینس منیجر بخشی از client kit نیست و باید جداگانه نصب شود.
