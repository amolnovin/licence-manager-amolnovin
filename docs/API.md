# مستند REST API — wp-plugin-market-manager

همه APIها روی سرویس‌دهنده `https://amolnovin.ir` قرار دارند و از namespace `wplm/v1` استفاده می‌کنند.

## پایه URL

```text
https://amolnovin.ir/wp-json/wplm/v1
```

## 1. درخواست لایسنس — `POST /wp-json/wplm/v1/license/request`

ایجاد درخواست لایسنس برای یک محصول. این درخواست در جدول `wp_wplm_license_requests` ثبت می‌شود و می‌تواند از پنل مدیریت تأیید شود.

**پارامترهای body (JSON یا form):**

| پارامتر | الزاری | توضیح |
|---|---|---|
| `product_slug` | ✅ | slug محصول در سامانه فروش |
| `email` | ✅ | ایمیل درخواست‌دهنده |
| `mobile` | — | شماره موبایل |
| `domain` | — | دامنه مجاز برای لایسنس (plugin/theme) |
| `machine_id` | — | شناسهٔ ماشین کامپیوتر برای لایسنس ویندوز (برنامه ویندوز) |

**rate limit:** 15 درخواست در 300 ثانیه

**پاسخ:**

```json
{ "success": true, "code": "ok", "license_request": { ... }, "message": "..." }
```

## 2. فعال‌سازی لایسنس — `POST /wp-json/wplm/v1/license/activate`

فعال‌سازی لایسنس و ثبت دامنه فعلی در لایسنس.

**پارامترهای body:**

| پارامتر | الزاری | توضیح |
|---|---|---|
| `license_key` | ✅ | کلید لایسنس (format `XXXX-XXXX-XXXX-XXXX`) |
| `product_slug` | ✅ | slug محصول |
| `domain` | — | دامنه رایج در صورت نبود (plugin/theme) |
| `machine_id` | — | شناسهٔ ماشین کامپیوتر (برنامه ویندوز) |

**پاسخ موفق:**

```json
{
  "success": true,
  "code": "ok",
  "license": {
    "license_key": "XXXX-XXXX-XXXX-XXXX",
    "status": "active",
    "expire_date": "2027-10-31",
    "domains": ["example.com"],
    "max_domains": 1
  },
  "message": "لایسنس فعال شد"
}
```

**کدهای پاسخ اشتباه احتمالی:**

| کد | توضیح |
|---|---|
| `invalid_key` | کلید نامعتبر |
| `product_not_found` | محصولی با این slug وجود ندارد |
| `license_suspended` | لایسنس مسدود است |
| `license_expired` | لایسنس منقضی است |
| `domain_limit_reached` | سقف دامنه تکمیل شده است |
| `domain_not_allowed` | دامنه در مجازهای لایسنس نیست |
| `rate_limited` | بیش از حد درخواست شده (HTTP 429) |

## 3. بررسی وضعیت — `POST /wp-json/wplm/v1/license/check`

بررسی دوره‌ای وضعیت لایسنس و فعال‌سازی دامنه.

**پارامترهای body:**

| پارامتر | الزاری | توضیح |
|---|---|---|
| `license_key` | ✅ | کلید لایسنس |
| `product_slug` | ✅ | slug محصول |
| `domain` | — | دامنه رایج در صورت نبود (plugin/theme) |
| `machine_id` | — | شناسهٔ ماشین کامپیوتر (برنامه ویندوز) |

**پاسخ:** همان ساختار `activate` با وضعیت لحظه‌ای (status، expire_date، domain).

## 4. بررسی آپدیت — `POST /wp-json/wplm/v1/updates/check`

بررسی نسخه جدید محصول. این endpoint توسط آپدیتر افزونه مشتری (`WPLM_Client_Kit_Updater` / `WPLM_Plugin_Updater`) استفاده می‌شود.

**پارامترهای body:**

| پارامتر | الزاری | توضیح |
|---|---|---|
| `product_slug` | ✅ | slug محصول |
| `license_key` | — | کلید لایسنس (برای محصولات لایسنس‌دار الزاری است) |
| `license_required` | — | `0`/`false` برای محصولات بدون لایسنس |
| `version` | — | نسخه فعلی افزونه مشتری |

**rate limit:** 120 درخواست در 300 ثانیه

**پاسخ موفق (وقتی نسخه جدید وجود دارد):**

```json
{
  "success": true,
  "code": "update_available",
  "update": {
    "version": "2.0.0",
    "url": "https://amolnovin.ir/wp-admin/admin-post.php?action=wplm_download_package&token=...",
    "package": "https://amolnovin.ir/wp-admin/admin-post.php?action=wplm_download_package&token=...",
    "changelog": "...",
    "requires": "6.2",
    "tested": "6.6",
    "requires_php": "7.4"
  }
}
```

**پاسخ وقتی آپدیت وجود ندارد:**

```json
{ "success": true, "code": "no_update", "message": "نسخه شما جدید است" }
```

## 4b. فعال‌سازی و بررسی machine_id برای ویندوز

برای محصولات نوع `windows`:

**activate** با machine_id:
```json
POST /wp-json/wplm/v1/license/activate
{ "license_key": "XXXX-XXXX-XXXX-XXXX", "product_slug": "my-app", "machine_id": "4d75bfe14a964dcfb15b6741b2ef6bc2" }
```

**check** با machine_id:
```json
POST /wp-json/wplm/v1/license/check
{ "license_key": "XXXX-XXXX-XXXX-XXXX", "product_slug": "my-app", "machine_id": "4d75bfe14a964dcfb15b6741b2ef6bc2" }
```

**کدهای پاسخ:**

| کد | توضیح |
|---|---|
| `ok` / `activated` | لایسنس معتبر / فعال‌سازی موفق |
| `not_found` | کلید لایسنس وجود ندارد |
| `product_mismatch` | کلید به محصول دیگری تعلق دارد |
| `inactive` / `expired` | وضعیت نامناسب |
| `invalid_machine_id` | format machine_id نامعتبر است |
| `product_not_windows` | محصول ویندوز نیست |
| `machine_limit` | سقف machine_ids تکمیل شده است |

## 5. callback IPPanel Core — `POST /wp-json/wplm-ippanel/v1/receive-key`

callback دریافت کد لایسنس از پیامک IPPanel Core (integration_code: `WPLM-LICENSE-HUB`).

## 6. لینک دانلود امن

```text
/wp-admin/admin-post.php?action=wplm_download_package&token=...
```

token به صورت base64url با sign HMAC و انقضای یک‌ساعته تولید می‌شود. اعتبارسنجی بر اساس license_key، محصول، دامنه و زمان انقضای token انجام می‌شود.

## 7. helpers تعویض آدرس (client kit)

اگر `/wp-json` روی سرور فروش 404 بدهد (تنظیمات permalink)، کلاینت با fallback زیر تلاش می‌کند:

```text
https://amolnovin.ir/?rest_route=/wplm/v1/license/activate
```

## 8. مثال درخواست با WP_Http (وردپرس)

```php
$response = wp_remote_post('https://amolnovin.ir/wp-json/wplm/v1/license/activate', [
    'body' => [
        'license_key'  => 'XXXX-XXXX-XXXX-XXXX',
        'product_slug' => 'my-product',
        'domain'       => 'example.com',
    ],
    'headers' => ['Content-Type' => 'application/json'],
    'sslverify' => true,
    'timeout'  => 15,
]);

$body = json_decode(wp_remote_retrieve_body($response), true);
$success = !empty($body['success']);
```

## 9. نکات امنیتی

- همه endpointها rate limited.
- فعال‌سازی و بررسی لایسنس پالایش credentials ندارند (permission_callback `__return_true`) و به‌صورت trust-on-first-use روی license_key و دامنه اعتبارسنجی می‌شوند.
- کلید لایسنس در option اختصاصی هر افزونه ذخیره می‌شود؛ رمزنگاری strong ندارد.
- ZIP نسخه‌ها روی مسیر امن `wp-content/uploads/wplm-secure-packages` ذخیره می‌شوند و با `.htaccess` + `index.php` از دانلود مستقیم جلوگیری می‌شود.
