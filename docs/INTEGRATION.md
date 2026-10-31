# راهنمای اتصال افزونه مشتری (INTEGRATION)

این راهنما نحوه قرار دادن `wplm-client-kit` در افزونه‌های قابل فروش و اتصال آن‌ها به سامانه فروش و لایسنس آمل نوین را توضیح می‌دهد.

## 0. پیش‌نیازها

- سایت فروش باید افزونه `wp-plugin-market-manager` نصب و تنظیم شده باشد.
- محصول باید در پنل مدیریت ثبت شده باشد و `slug` آن یادداشت شده باشد.
- افزونه جداگانه `wplm-amolnovin-license-manager` روی سایت فروش نصب و فعال باشد.

## 1. قرار دادن کیت در افزونه مشتری

پوشه `wplm-client-kit` را داخل افزونه مشتری قرار دهید:

```text
my-plugin/
├── my-plugin.php
└── wplm-client-kit/
    ├── includes/
    │   ├── wplm-license-client.php
    │   └── wplm-plugin-updater.php
    └── ...
```

## 2. اتصال افزونه لایسنس‌دار

در فایل اصلی افزونه مشتری:

```php
<?php
/**
 * Plugin Name: افزونه من
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

define('MY_PLUGIN_VERSION', '1.0.0');

require_once plugin_dir_path(__FILE__) . 'wplm-client-kit/includes/wplm-license-client.php';
require_once plugin_dir_path(__FILE__) . 'wplm-client-kit/includes/wplm-plugin-updater.php';

$my_license = new WPLM_Client_Kit_License([
    'api_base'       => 'https://amolnovin.ir/wp-json/wplm/v1',
    'product_slug'   => 'PRODUCT-SLUG-IN-AMOLNOVIN',
    'plugin_file'    => __FILE__,
    'plugin_name'    => 'نام افزونه شما',
    'license_option' => 'my_plugin_license_key',
    'status_option'  => 'my_plugin_license_status',
    'data_option'    => 'my_plugin_license_data',
    'manager_required' => true,
]);

new WPLM_Client_Kit_Updater([
    'api_url'        => 'https://amolnovin.ir/wp-json/wplm/v1/updates/check',
    'plugin_file'    => __FILE__,
    'plugin_slug'    => 'YOUR-PLUGIN-FOLDER',
    'product_slug'   => 'PRODUCT-SLUG-IN-AMOLNOVIN',
    'version'        => MY_PLUGIN_VERSION,
    'license_option' => 'my_plugin_license_key',
    'name'           => 'نام افزونه شما',
    'author'         => 'آمل نوین',
]);

add_action('plugins_loaded', function () use ($my_license) {
    if (!$my_license->is_active()) {
        return; // افزونه قفل است؛ فقط صفحه فعال‌سازی لایسنس نمایش داده می‌شود
    }
    // اجرای امکانات اصلی افزونه شما
    // My_Plugin_Core::boot();
});
```

## 3. اتصال افزونه بدون لایسنس

برای افزونه‌های فعال بدون لایسنس (`license_mode = active_no_license`) فقط آپدیتر کافی است:

```php
require_once plugin_dir_path(__FILE__) . 'wplm-client-kit/includes/wplm-plugin-updater.php';

new WPLM_Client_Kit_Updater([
    'api_url'          => 'https://amolnovin.ir/wp-json/wplm/v1/updates/check',
    'plugin_file'      => __FILE__,
    'plugin_slug'      => 'your-plugin-folder',
    'product_slug'     => 'your-product-slug',
    'version'          => YOUR_PLUGIN_VERSION,
    'license_required' => false,
]);
```

## 4. جدول پیکربندی

### `WPLM_Client_Kit_License`

| کلید | الزاری | توضیح |
|---|---|---|
| `api_base` | ✅ | `https://amolnovin.ir/wp-json/wplm/v1` |
| `product_slug` | ✅ | slug محصول در سامانه فروش |
| `plugin_file` | ✅ | `__FILE__` |
| `plugin_name` | — | نام افزونه برای نمایش |
| `license_option` | ✅ | نام option ذخیره کلید لایسنس |
| `status_option` | — | نام option ذخیره وضعیت |
| `data_option` | — | نام option ذخیره اطلاعات لایسنس (timecode, domain, message) |
| `manager_required` | — | `true` نمایش notice اگر منیجر فعال نباشد |
| `manager_download_url` | — | لینک دانلود افزونه منیجر برای notice |
| `individual_page` | — | `true` ساخت صفحه مجزای مدیریت برای این افزونه |
| `manager_page` | — | `false` غیرفعال‌سازی صفحه مرکزی |

### `WPLM_Client_Kit_Updater`

| کلید | الزاری | توضیح |
|---|---|---|
| `api_url` | ✅ | `https://amolnovin.ir/wp-json/wplm/v1/updates/check` |
| `plugin_file` | ✅ | `__FILE__` |
| `plugin_slug` | — | نام پوشه افزونه |
| `product_slug` | — | slug محصول |
| `version` | — | نسخه افزونه مشتری |
| `license_option` | — | option ذخیره کلید لایسنس |
| `license_required` | — | `false` برای محصولات بدون لایسنس |
| `name` / `author` | — | اطلاعات افزونه برای plugins_api |

## 4b. اتصال افزونه برای برنامه ویندوز (machine_id)

برای فروش برنامه‌های ویندوز، محصول را با نوع **برنامه ویندوز** در پنل مدیریت ثبت کنید. فعال‌سازی لایسنس بر اساس شناسهٔ ماشین کامپیوتر انجام می‌شود.

### ساختار کار

1. برنامه ویندوز در اولین اجرا machine_id را تولید می‌کند (مثلاً با hash کارت گرافیک + مادربرد، 32 کاراکتری هگز).
2. ماشین کاربر machine_id را به سامانه فروش ارسال می‌دهد:
   - در خرید از فروشگاه: فیلد machine_id در سبد خرید.
   - فعال‌سازی دستی: `POST /wp-json/wplm/v1/license/activate` با `machine_id`.
   - بررسی دوره‌ای: `POST /wp-json/wplm/v1/license/check` با `machine_id`.
3. لایسنس روی همان کامپیوتر (machine_id) فعال می‌شود و در صفحه لایسنس‌ها (`page=wplm-licenses`) در بخش دامنه‌ها / machine_ids نمایش داده می‌شود.
4. سقف فعال‌سازی machine_ids با فیلد تعداد فعال‌سازی (`allowed_domains`) کنترل می‌شود.

### مثال API (برنامه ویندوز)

```http
POST https://amolnovin.ir/wp-json/wplm/v1/license/activate
Content-Type: application/json

{
  "license_key": "XXXX-XXXX-XXXX-XXXX",
  "product_slug": "my-app",
  "machine_id": "4d75bfe14a964dcfb15b6741b2ef6bc2"
}
```

### client kit با machine_id (از WP)

اگر بخواهید کلاینت وردپرسی هم machine_id ارسال کند (مثلاً برای محصولات ویندوز که از WP قابل مدیریت هستند):

```php
$license = new WPLM_Client_Kit_License([
    'api_base'       => 'https://amolnovin.ir/wp-json/wplm/v1',
    'product_slug'   => 'my-app',
    'plugin_file'    => __FILE__,
    'activation'     => 'machine',
    'license_option' => 'my_plugin_license_key',
    // machine_id می‌تواند از config یا option reading:
    'machine_id'     => '4d75bfe14a964dcfb15b6741b2ef6bc2',
]);
```

با این تنظیمات، درخواست‌های activate/check پارامتر `machine_id` (و نه domain) ارسال می‌کنند و در صفحه فعال‌سازی فیلد machine_id نمایش داده می‌شود.

## 5. چک‌لیست نهایی قبل از انتشار افزونه مشتری

- [ ] پوشه `wplm-client-kit` داخل افزونه قرار گرفته است.
- [ ] `api_base` و `api_url` روی دامنه واقعی `https://amolnovin.ir` تنظیم شده است.
- [ ] `product_slug` دقیقاً برابر slug محصول ثبت‌شده در پنل `wp-plugin-market-manager` است.
- [ ] `plugin_slug` برابر نام پوشه افزونه است.
- [ ] نسخه افزونه با ثابت `VERSION` هماهنگ است.
- [ ] اجرای امکانات اصلی افزونه پشت شرط `$license->is_active()` قرار گرفته است.
- [ ] روی سایت تست، فعال‌سازی لایسنس تست شده است.
- [ ] بروزرسانی از صفحه «افزونه‌ها»ی وردپرس تست شده است.
- [ ] افزونه `wplm-amolnovin-license-manager` نصب و فعال است (در غیر این صورت notice نمایش داده می‌شود).

## 6. عیب‌یابی

### آپدیت نمایش داده نمی‌شود

- `product_slug` درست باشد.
- لایسنس فعال باشد (برای محصولات لایسنس‌دار).
- نسخه افزونه مشتری کمتر از نسخه ثبت‌شده در سایت فروش باشد.
- فایل ZIP نسخه جدید آپلود شده باشد.
- نسخه جدید در سایت فروش به عنوان نسخه فعلی انتخاب شده باشد.
- کش بروزرسانی وردپرس (transient `update_plugins`) پاک شود.

### لایسنس فعال نمی‌شود

- دامنه در لایسنس مجاز باشد.
- سقف دامنه تکمیل نشده باشد.
- لایسنس منقضی یا مسدود نباشد.
- محصول در سایت فروش فعال باشد.

### پیام «آدرس API امن نیست»

از `api_is_allowed()` در `wplm-license-client.php` نمایش داده می‌شود:

1. `api_base` خالی است یا مقدار نمونه `amolnovin.ir` باقی مانده است.
2. آدرس با `http://` وارد شده است (در محیط واقعی باید `https://` باشد).
3. `https://` جا افتاده است (نسخه 1.0.1+ به‌صورت خودکار برطرف می‌کند).
4. آدرس API اشتباه است:

```php
'api_base' => 'https://amolnovin.ir/wp-json/wplm/v1'
'api_url'  => 'https://amolnovin.ir/wp-json/wplm/v1/updates/check'
```

## 7. نمونه آماده

`example-plugin-main.php` و `config-amolnovin-sample.php` در کیت نمونه‌های کامل این موارد را نشان می‌دهند.
