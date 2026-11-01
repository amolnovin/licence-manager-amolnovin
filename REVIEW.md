# بررسی فنی مخزن `licence-manager-amolnovin`

تاریخ بررسی: ۲۰۲۶-۱۱-۰۱ · شاخه: `arena/db517ce8-licence-manager-amolnovin` · مبنای بررسی: `356b891`

این سند نتیجهٔ بازخوانی کامل کدها، مستندات و بسته‌های ZIP موجود در مخزن است. یافته‌ها بر اساس شدت مرتب شده‌اند و برای هر مورد، مسیر و شماره خط دقیق ذکر شده است.

---

## ۱. خلاصهٔ مدیریتی

| موضوع | وضعیت |
|---|---|
| ساختار پروژه | سه جزء مستقل و درست تفکیک‌شده (سرور فروش، پنل لایسنس، کیت کلاینت) + کیت ویندوز |
| کیفیت پایهٔ کد | خوب: namespace اختصاصی، Repository/Service/REST، prepared statements، nonce و capability در همهٔ اکشن‌ها، escape در ویوها |
| باگ بحرانی | **۴ مورد** که ۳ تای آن‌ها محصول را عملاً از کار می‌اندازد (صفحهٔ فروشگاه، سبد خرید، نصب تازه، فعال‌سازی لایسنس) |
| قرارداد API | **بین سه پیاده‌سازی (سرور / کیت PHP / کیت C#) و مستندات ناسازگار است** — مهم‌ترین ریسک بلندمدت پروژه |
| امنیت | پایهٔ خوب، اما ۴ ضعف واقعی (rate-limit قابل دور زدن، endpoint بدون احراز هویت، بسته‌های قابل دانلود روی Nginx، نشت کلید در URL) |
| نسخه‌ها و مستندات | ناهماهنگ (README می‌گوید 1.3.0 ولی کد 1.3.1 است؛ کیت 1.4.0/1.1.0 در حالی که کد 1.5.0/1.2.0) |
| تست و CI | هیچ تستی، لینتری و CI وجود ندارد — به همین دلیل باگ‌های فتال زیر به بستهٔ انتشار هم راه پیدا کرده‌اند |

> **نکتهٔ مهم:** بستهٔ `releases/wp-plugin-market-manager-21.zip` (نسخهٔ 1.3.1) دقیقاً از همین کد ساخته شده و **همین باگ فتال را دارد**. یعنی نصب این بسته روی سایت اصلی، صفحهٔ فروشگاه و سبد خرید را از کار می‌اندازد.

### وضعیت بسته‌های انتشار

| فایل | نسخه | مقایسه با `src/` |
|---|---|---|
| `releases/wp-plugin-market-manager-21.zip` | 1.3.1 | **کاملاً یکسان** با `src/wp-plugin-market-manager` (شامل باگ فتال) |
| `releases/wp-plugin-market-manager-20.zip` | 1.2.3 | نسخهٔ قدیمی (بدون پشتیبانی ویندوز) |
| `releases/wplm-client-kit-7.zip` | 1.4.0 / 1.1.0 | فقط README تفاوت دارد؛ **کد ارسال دامنه در آن درست است** (رجوع به یافتهٔ C4) |
| `releases/wplm-amolnovin-license-manager-7.zip` | 1.0.7 | یکسان با `src/` و با فایل داخل `client/` (md5 برابر: `1ecba28b…`) |
| `releases/wplm-windows-kit-1.0.0.zip` | 1.0.0 | مطابق `src/wplm-windows-kit` |

---

## ۲. باگ‌های بحرانی

### C1 — خطای فتال در صفحهٔ فروشگاه و کاروسل (فراخوانی تابع ناموجود)

**جا:** `src/wp-plugin-market-manager/includes/Shortcodes.php:103, 208, 319`

```php
$type_label = self_type_label($product->type);   // ← تابع وجود ندارد
echo self_type_label($product->type);            // ← همان اشتباه
```

متد واقعی `private static function type_label()` است (خط ۱۸). `self_type_label()` هیچ‌جا تعریف نشده، پس PHP خطای
`Uncaught Error: Call to undefined function WPLM\self_type_label()` می‌دهد.

- خط ۱۰۳ → شورتکد `[wplm_latest_carousel]` (به‌محض وجود یک محصول فعال).
- خط ۲۰۸ → شورتکد `[wplm_store]` و `[wplm_products]` (صفحهٔ فروشگاه).
- خط ۳۱۹ → رندر سبد خرید.

**ریشه:** به‌نظر یک `search/replace` اشتباه از `self::` به `self_` انجام شده (همین الگو در ویوها هم دیده می‌شود، ولی آنجا توابع `self_*_type_label()` واقعاً تعریف شده‌اند).

**وضعیت: رفع شد** (نسخه 1.4.0) → `self::type_label(...)` در هر سه نقطه.

---

### C2 — خطای فتال در سبد خرید

**جا:** `src/wp-plugin-market-manager/includes/Shortcodes.php:339`

```php
<?php if (self_cart_has_windows($products)) : ?>
```

متد واقعی `self::cart_has_windows()` است. هر بار که سبد خرید حداقل یک محصول داشته باشد، صفحهٔ سبد فتال می‌شود (فرم پرداخت هرگز رندر نمی‌شود).

**وضعیت: رفع شد** (نسخه 1.4.0 — با `php-wasm` هم تست شد).

---

### C3 — جدول `categories` ساخته نمی‌شود (کلید ایندکس روی ستون ناموجود)

**جا:** `src/wp-plugin-market-manager/includes/Activator.php:126–127`

```sql
CREATE TABLE wp_wplm_categories (
    id …, slug …, name …, type …, description …, status …, created_at …, updated_at …,
    KEY license_mode (license_mode),   -- ← این ستون‌ها در این جدول نیستند
    KEY is_visible (is_visible)        -- ← کپی‌شده از جدول products
) …;
```

MySQL خطای `1072: Key column 'license_mode' doesn't exist in table` می‌دهد و `dbDelta()` جدول دسته‌بندی را ایجاد نمی‌کند. نتیجه:

- نصب تازه: `wp_wplm_categories` ساخته نمی‌شود → صفحات «دسته‌بندی‌ها»، فیلتر دسته در فروشگاه و منوی فرانت با خطای دیتابیس مواجه می‌شوند.
- آپدیت از نسخه‌های قبل: `dbDelta` تلاش می‌کند این ایندکس‌ها را اضافه کند و با خطا برمی‌گردد (بقیهٔ جدول‌ها سالم می‌مانند).

**وضعیت: رفع شد** (نسخه 1.4.0) → دو خط `KEY license_mode` و `KEY is_visible` از جدول `categories` حذف شد (در `products` سر جای خود هستند).

---

### C4 — کیت کلاینت، «دامنه» را در فیلد `machine_id` می‌فرستد؛ فعال‌سازی همیشه شکست می‌خورد

**جا:** `src/wplm-client-kit/includes/wplm-license-client.php:270, 290`

```php
$result = $this->request('/license/activate', [
    'product_slug' => …,
    'license_key'  => $license,
    'machine_id'   => $this->activation_id(),   // ← در حالت domain، مقدارش دامنه است
]);
```

`activation_id()` (خط ۵۵۳) در حالت پیش‌فرض `activation => 'domain'` همان دامنه را برمی‌گرداند، و پارامتر `domain` هم اصلاً ارسال نمی‌شود. سمت سرور:

1. `LicenseController::activate()` مقدار را با `Security::sanitize_machine_id()` پاکسازی می‌کند → `example.com` تبدیل می‌شود به `eaec`.
2. `LicenseRepository::validate()` چون `machine_id` غیرخالی است وارد شاخهٔ ویندوز می‌شود.
3. `looks_like_machine_id('eaec')` رد می‌شود → پاسخ `invalid_machine_id` با کد 403.

یعنی **هیچ افزونهٔ مشتری با حالت دامنه نمی‌تواند فعال شود** و در پنل «لایسنس غیرفعال» نشان داده می‌شود. این یک **regression** است: نسخهٔ 1.4.0 (داخل `releases/wplm-client-kit-7.zip`) درست `'domain' => $this->domain()` می‌فرستاد و در بازنویسی برای پشتیبانی `machine_id` از دست رفت.

**وضعیت: رفع شد** (نسخه 1.4.0) →

```php
'domain'     => 'domain' === $this->config['activation'] ? $this->domain() : '',
'machine_id' => 'machine' === $this->config['activation'] ? $this->machine_id() : '',
```

(هم در `activate()` و هم در `check()`.) این با قرارداد سرور و با `wplm-plugin-updater.php:204-205` هم‌راستا است. نسخه کیت به 1.5.1 ارتقا یافت.

---

## ۳. ناسازگاری قرارداد API (بالا)

سه کلاینت و دو سند، سه نسخهٔ متفاوت از یک API را توصیف می‌کنند. این جدی‌ترین ریسک نگهداری پروژه است.

### H1 — پاسخ `activate` / `check` هیچ شیء `license` ندارد

**سرور:** `src/wp-plugin-market-manager/includes/REST/LicenseController.php:85, 105`

```php
return new WP_REST_Response([
    'success' => (bool) $result['valid'],
    'code'    => $result['code'],
    'machine_id' => $result['machine_id'] ?? null,
], …);
```

**مستندات:** `docs/API.md:48-58` این ساختار را وعده می‌دهد:

```json
{ "success": true, "code": "ok",
  "license": { "license_key": "...", "status": "active", "expire_date": "2027-10-31",
               "domains": ["example.com"], "max_domains": 1 } }
```

**کیت ویندوز:** `src/wplm-windows-kit/WPLM.LicenseKit/LicenseClient.cs:307-330` دقیقاً همین `license` را می‌خواند.

نتیجه: در اپلیکیشن ویندوز، `ExpiresAt`، `MaxActivations`، `Domains` و `Status` همیشه خالی/`Unknown` می‌مانند و کاربر تاریخ انقضا نمی‌بیند. `LicenseRepository::validate()` و `activate_domain()` این داده‌ها را در دست دارند و برگرداندنشان کار سختی نیست.

### H2 — پاسخ `updates/check` تخت است، ولی کلاینت‌ها ساختار تودرتو می‌خوانند

**سرور:** `src/wp-plugin-market-manager/includes/REST/UpdateController.php:60-95` فیلدهای تخت برمی‌گرداند (`update_available`, `version`, `package`, `requires`, …).

**کیت ویندوز:** `LicenseClient.cs:245-260`

```csharp
if (json.TryGetValue("update", out var updateRaw) && updateRaw is Dictionary<string, object> update)
{
    info.UpdateAvailable = true;   // ← فقط با شیء تودرتوی "update"
```

و `docs/API.md:100-120` هم همان ساختار تودرتو با `code: "update_available"` را مستند کرده است.

نتیجه: **به‌روزرسانی خودکار برنامه‌های ویندوز هرگز نمایش داده نمی‌شود** (همیشه `UpdateAvailable = false`). کیت PHP (`wplm-plugin-updater.php:64-70`) و آپدیتر داخلی «لایسنس منیجر» بر ساختار تخت تکیه می‌کنند، پس هر اصلاحی باید هم‌زمان در هر سه جا انجام شود.

### H3 — پارامتر ایمیل در `license/request` مستند نشده

- کد: `LicenseController.php:45` فقط `customer_email` را می‌خواند و در غیر این صورت `invalid_email` (422) می‌دهد.
- مستند: `docs/API.md:20` فیلد الزامی را `email` نوشته است.

هر کسی طبق مستندات درخواست بفرستد، 422 می‌گیرد. (کیت ویندوز درست `customer_email` می‌فرستد.)

### H4 — `updates/check` برای محصول ویندوز `machine_id` را اعتبارسنجی نمی‌کند

`UpdateController::check()` پارامتر `machine_id` را نمی‌خواند و لایسنس را فقط با `domain` می‌سنجد. برای محصول ویندوز (`domains = []`) این یعنی `validate()` بدون هیچ بررسی فعال‌سازی `valid` برمی‌گرداند؛ در حالی که منطق `machine_limit` در مسیر activate وجود دارد. توصیه: همان مسیر `validate($key, $product_id, $domain, $machine_id)` استفاده شود.

### H5 — ساختار `license_mode` محصول با `license_required` کلاینت هم‌خوان نیست

`UpdateController.php:41` از `$product->license_mode !== 'none'` استفاده می‌کند و پارامتر `license_required` کلاینت را نادیده می‌گیرد. این درست است (منبع حقیقت باید سرور باشد) ولی مستندات `docs/API.md:96` آن را به‌عنوان پارامتر مؤثر معرفی کرده؛ بهتر است حذف یا «غیرفعال» علامت بخورد.

---

## ۴. امنیت

| # | مورد | جا | شدت | توضیح / پیشنهاد |
|---|---|---|---|---|
| S1 | rate-limit قابل دور زدن | `includes/Security.php:101-124` | متوسط | IP از `CF-Connecting-IP` / `X-Forwarded-For` بدون اعتبارسنجی خوانده می‌شود؛ هر کسی با یک هدر جعلی، پنجرهٔ محدودیت را عوض می‌کند. علاوه بر آن، شمارنده با read-modify-write روی transient است و زیر بار همزمان قابل عبور است. پیشنهاد: فقط `REMOTE_ADDR` یا لیست پروکسی‌های مورد اعتماد، و شمارنده اتمیک در دیتابیس. |
| S2 | بازنویسی توکن پیامک بدون احراز هویت | `includes/Vendor/ippanel-core-addon-helper.php:75, 109` | متوسط | `POST /wp-json/wplm-ippanel/v1/receive-key` با `permission_callback => __return_true` هر `api_key` را در option ذخیره می‌کند. تنها «راز» این endpoint، `slug` و `integration_code` هستند که هر دو در صفحهٔ تنظیمات به‌صورت عمومی نمایش داده می‌شوند (`admin/views/settings.php`). نتیجه: هر کسی می‌تواند توکن IPPanel را با مقدار دلخواه جای‌گزین کند (خرابکاری/جعل پیامک). پیشنهاد: secret مشترک، امضای HMAC یا محدودیت IP. |
| S3 | بسته‌های ZIP روی Nginx قابل دانلود مستقیم‌اند | `includes/Services/PackageService.php:14-40, 71` | متوسط | مسیر `uploads/wplm-secure-packages/<slug>/<version>/<filename>` کاملاً قابل حدس است و حفاظت فقط با `.htaccess` انجام می‌شود که Apache-only است. روی Nginx این فایل‌ها با URL مستقیم بدون لایسنس دانلود می‌شوند. پیشنهاد: نام فایل تصادفی + دستور سرور (location deny) + مستندسازی. |
| S4 | کلید لایسنس داخل توکن دانلود | `PackageService.php:92-105` | کم/متوسط | توکن `base64+HMAC` است و payload شامل `license_key` است؛ پس کلید در لاگ‌های وب‌سرور و Referer جابه‌جا می‌شود. پیشنهاد: استفاده از `license_id` به‌جای کلید. |
| S5 | اعتبارسنجی دامنه از نوع trust-on-first-use | `includes/Repositories/LicenseRepository.php` (`domain_allowed`) | کم | تا وقتی سهمیه پر نشده، هر دامنه‌ای `valid` است؛ یعنی کلید به دامنه «قفل» نمی‌شود. مستند `docs/API.md:198` خودش این را پذیرفته است، اما برای حجم بالا بهتر است check فقط دامنه‌های ثبت‌شده را قبول کند. |
| S6 | کلید در HTML پنل + option بدون رمزنگاری | `admin/views/licenses.php` (`data-license_key`) | کم | بد نیست، ولی با توجه به capability موجود، بهتر است مودال ویرایش کلید را نمایش ندهد و فقط اجازهٔ تولید مجدد بدهد. |
| S7 | نبود محافظت CSRF در `remove_from_cart` | `includes/Services/PurchaseService.php:44` | — | بررسی شد: nonce دارد (`wplm_remove_cart_item`). ایرادی نیست. |

**موارد مثبتی که بررسی و تأیید شد:** همهٔ اکشن‌های `admin-post` / `ajax` هم `Security::require_cap()` و هم nonce دارند؛ کوئری‌ها `$wpdb->prepare` شده‌اند؛ خروجی ویوها با `esc_html/esc_attr/esc_url/esc_textarea` چاپ می‌شود (`grep` خط ۹۰ مسیر بدون escape پیدا نکرد)؛ توکن دانلود با `hash_equals` بررسی و مسیر فایل با `realpath` + prefix check در برابر path-traversal ایمن شده است؛ آپلود ZIP فقط MIME/پسوند را می‌پذیرد و extract نمی‌کند (پس zip-slip ندارد).

---

## ۵. باگ‌های کم‌اهمیت و بدهی فنی

| # | مورد | جا | توضیح |
|---|---|---|---|
| L1 | ناهماهنگی نسخه‌ها | `README.md:12-14, 31-32` · `docs/ARCHITECTURE.md:9, 229` | مستندات 1.3.0 / 1.4.0 / 1.1.0 می‌گویند؛ کد 1.3.1 / 1.5.0 / 1.2.0 است. |
| L2 | طول کلید لایسنس | `Security.php:125-129` · `docs/API.md` · placeholderها | `wp_generate_password(24)` → ۶ گروه ۴تایی (`XXXX-XXXX-XXXX-XXXX-XXXX-XXXX`)، اما همه‌جا `XXXX-XXXX-XXXX-XXXX` نوشته شده. |
| L3 | کلید تایپ‌شده نرمال‌سازی نمی‌شود | `LicenseRepository::find_by_key` | کلید با حروف کوچک یا فاصله → `not_found`. یک `strtoupper(trim())` کافی است. |
| L4 | نوع «برنامهٔ ویندوز» در حساب کاربری «افزونه» نمایش داده می‌شود | `includes/Shortcodes.php` (تب خریدها) | `'theme' === … ? 'قالب' : 'افزونه'` — حالت `windows` جامانده. |
| L5 | کد تخفیف نامعتبر → مرگ صفحه | `PurchaseService::calculate_cart()` (`wp_die`) | بهتر است با کوئری/پیام فرم برگردد، نه صفحهٔ `wp_die`. |
| L6 | تکرار کد آپدیتر | `client/wplm-plugin-updater.php` و `includes/wplm-plugin-updater.php` | دو پیاده‌سازی موازی؛ یکی باید حذف یا alias شود. |
| L7 | `machine_id` در سبد خرید اعتبارسنجی نمی‌شود | `PurchaseService::start_purchase()` | اگر کاربر مقدار نامعتبر بدهد، سفارش ثبت و لایسنس بدون machine_id صادر می‌شود. کافی است `Security::looks_like_machine_id()` چک شود. |
| L8 | نبود تست/CI/linter | کل مخزن | هیچ `composer.json`، تست، `phpcs` یا workflow وجود ندارد. با وجود یک lint ساده، هر ۴ باگ بحرانی قبل از انتشار گرفته می‌شد. |
| L9 | باینری در Git | `releases/*` و `client/*.zip` | دو فایل ZIP با md5 یکسان در دو مسیر (`releases/wplm-amolnovin-license-manager-7.zip` و `client/…zip`). |
| L10 | مستندات جزئی قدیمی | `docs/ARCHITECTURE.md` | پوشهٔ `Vendor/` دو بار فهرست شده؛ تبدیل نسخه‌ها به 1.3.x به‌روز نشده. |
| L11 | `autoload` نبودن در `map_meta_cap` | `Security::map_meta_cap()` | `$user->has_cap('manage_options')` برای نقش contracts هم درست کار می‌کند، ولی در PHP 8 با `$user` نال بدون بررسی کامل — فعلاً OK است. |

---

## ۶. نقاط قوت (که باید حفظ شوند)

1. **معماری تمیز:** جداسازی `Repository` / `Service` / `REST` / `View`، autoloader اختصاصی، namespace `WPLM` و prefix دیتابیس `wplm_`.
2. **مدل دسترسی درست:** capability اختصاصی `manage_wplm` + `map_meta_cap` برای نقش «مدیر پروژه» — بدون scatter کردن چک‌ها.
3. **امنیت پایهٔ محکم:** nonce در همهٔ فرم‌ها و اکشن‌ها، بررسی capability، `$wpdb->prepare`، و escape در ویوها.
4. **دانلود امن:** توکن HMAC یک‌ساعته با `hash_equals`، مسیر امن خارج از دسترس مستقیم (روی Apache) و `realpath` برای جلوگیری از traversal.
5. **مقاوم‌سازی کلاینت:** `grace_on_error`، fallback `?rest_route=`، تمایز خطای موقت/قطعی، کش با TTL متفاوت — این بخش‌ها واقعاً خوب نوشته شده‌اند.
6. **پشتیبانی machine_id نسبتاً یکپارچه:** دیتابیس، API، پنل و کیت ویندوز همه به آن مجهز شده‌اند (به‌جز موارد H4 و C4).
7. **مستندات داخلی خوب:** `docs/ARCHITECTURE.md`، `API.md` و `INTEGRATION.md` ساختار را دقیق توصیف می‌کنند (فقط از کد عقب افتاده‌اند).

---

## ۷. برنامهٔ پیشنهادی (به ترتیب اولویت)

**گام ۱ — همین امروز**
- [x] رفع `self_type_label` / `self_cart_has_windows` (C1, C2)
- [x] حذف کلیدهای نامعتبر جدول `categories` (C3)
- [x] ارسال درست `domain` / `machine_id` در کیت کلاینت (C4)
- [x] بازسازی بسته‌ها از کد اصلاح‌شده → `releases/wp-plugin-market-manager-22.zip` (1.4.0) و `releases/wplm-client-kit-8.zip` (1.5.1)
- [x] به‌روزرسانی نسخه‌ها در README/ARCHITECTURE
- [x] مسیر ارتقا: ستون `sale_price` روی نصب‌های موجود هم اضافه می‌شود، چون `admin_init` → `Activator::maybe_upgrade()` → `create_tables()` از `dbDelta()` استفاده می‌کند و `dbDelta` ستون‌های جدید را با `ALTER TABLE` اضافه می‌کند (نیازی به مایگریشن دستی نیست).

**گام ۲ — یکسان‌سازی قرارداد API (مهم‌تر از هر قابلیت جدید)**
- [ ] یک پاسخ استاندارد برای `activate`/`check` تعریف شود: `{ success, code, message, license: { license_key, status, expire_date, max_domains, domains, machine_ids } }`
- [ ] پاسخ `updates/check` یا به `update` تودرتو تبدیل شود یا هر سه کلاینت (PHP updater، آپدیتر لایسنس‌منیجر، کیت C#) به ساختار تخت منتقل شوند — **یک ساختار، نه دو تا**
- [ ] `machine_id` به `updates/check` اضافه و در `validate()` لحاظ شود
- [ ] `customer_email` ↔ `email` و طول کلید در مستندات اصلاح شود
- [ ] یک نمونهٔ پاسخ واقعی (fixture JSON) در مستندات قرار گیرد تا کد و سند از هم جدا نشوند

**گام ۳ — امنیت**
- [ ] `Security::client_ip()` فقط `REMOTE_ADDR` (یا لیست پروکسی معتبر)
- [ ] احراز هویت/HMAC برای `receive-key`
- [ ] نام فایل تصادفی + قاعدهٔ deny در Nginx برای `wplm-secure-packages`
- [ ] حذف `license_key` از توکن دانلود (استفاده از `license_id`)

**گام ۴ — زیرساخت کیفیت**
- [ ] `composer.json` + `phpcs` (WordPress Coding Standards) + `php -l` در CI
- [ ] چند تست قراردادی برای `LicenseController` و `UpdateController` (بدون نیاز به WordPress، با stub کردن `$wpdb`)
- [ ] حذف باینری‌های تکراری از Git و ساخت ZIP در CI

---

## ۸. روش بررسی و محدودیت‌ها

- کدها به‌طور کامل خوانده شد: `includes/` (همهٔ ۲۶ فایل)، `admin/views/` (همهٔ ۱۲ ویو)، `assets/admin.js`، کیت PHP (۴ فایل)، پنل لایسنس (تک‌فایل ۸۵۹ خطی)، کیت ویندوز (۴ فایل C# + پروژه) و هر سه سند.
- همهٔ بسته‌های ZIP باز و با `src/` مقایسه شدند (تفاوت‌ها در بخش ۱ آمده است).
- **تست خودکار:** پس از اعمال اصلاحات، همهٔ ۴۶ فایل PHP با یک runtime واقعی PHP (php-wasm) از نظر خطای نحوی بررسی شد (۰ خطا) و یک هارنس مستقل با stub کردن توابع وردپرس، شورتکدهای `[wplm_store]`/`[wplm_popular]`/`[wplm_latest_carousel]`، صفحه محصول، سبد خرید و سه ویو ادمین را رندر کرد (۴۳ بررسی، همه PASS).
- **محدودیت:** PHP بومی محیط نصب نبود؛ بنابراین اجرای واقعی وردپرس/MySQL/قالب انجام نشد. رفع‌های انجام‌شده جای‌گذاری متنی ساده و کم‌ریسک هستند، اما پیش از انتشار باید روی محیط استیجینگ تست شوند (به‌خصوص نصب تازه برای تأیید ساخت جدول `categories`).
