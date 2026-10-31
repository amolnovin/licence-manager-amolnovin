# licence-manager-amolnovin

سامانه فروش، لایسنس‌دهی و بروزرسانی افزونه‌های وردپرس آمل نوین (Amol Novin) — پیش‌نصب‌شده روی `https://amolnovin.ir`.

این repository حاصل دانلود و بررسی سه بسته zip از پوشه **licence manager** در گوگل درایو است.

## محتوای repository

```text
licence-manager-amolnovin/
├── src/
│   ├── wp-plugin-market-manager/       # افزونه اصلی: سامانه فروش و لایسنس افزونه و قالب (نسخه 1.3.0)
│   ├── wplm-client-kit/                # کیت کلاینت: قفل لایسنس و آپدیتر افزونه مشتری (نسخه 1.4.0/1.1.0)
│   └── wplm-amolnovin-license-manager/ # افزونه مستقل: لایسنس منیجر آمل نوین (نسخه 1.0.7)
├── releases/                           # بسته‌های zip اصلی
│   ├── wp-plugin-market-manager-20.zip  # نسخه 1.2.3
│   ├── wp-plugin-market-manager-21.zip  # نسخه 1.3.0 (پشتیبانی ویندوز و machine_id)
│   ├── wplm-amolnovin-license-manager-7.zip
│   └── wplm-client-kit-7.zip
└── docs/
    ├── ARCHITECTURE.md                 # معماری و ساختار کامل هر بخش
    ├── API.md                          # مستند APIهای REST
    └── INTEGRATION.md                  # راهنمای اتصال افزونه مشتری
```

## سه جزء اصلی

| جز | نقش | نصب در | نسخه |
|---|---|---|---|
| `wp-plugin-market-manager` | سایت فروش اصلی (فروش، پرداخت زیبال/زرین‌پال، صدور لایسنس، آپلود نسخه، کد تخفیف، پیامک) | **سرویس‌دهنده** (`amolnovin.ir`) | 1.2.3 |
| `wplm-amolnovin-license-manager` | پنل مرکزی ثبت/بررسی/حذف لایسنس همه افزونه‌های متصل + بررسی ارتباط با سرور | **سرویس‌دهنده** (یا مشتری) | 1.0.7 |
| `wplm-client-kit` | قفل لایسنس، فرم فعال‌سازی و آپدیتر استاندارد برای افزونه‌های قابل فروش | **هر افزونه مشتری** | 1.4.0 / 1.1.0 |

## نقاط اتصال

- **API لایسنس:** `POST https://amolnovin.ir/wp-json/wplm/v1/license/{request,activate,check}`
- **API آپدیت:** `POST https://amolnovin.ir/wp-json/wplm/v1/updates/check`
- **صفحه مرکزی لایسنس منیجر:** `تنظیمات ← لایسنس منیجر آمل نوین`

## پشتیبانی از برنامه‌های ویندوز (نسخه 1.3.0)

- نوع محصول جدید **«برنامه ویندوز»** در مودال محصولات قابل ثبت است.
- لایسنس ویندوز بر اساس **شناسهٔ ماشین (machine_id)** فعال می‌شود؛ هر کامپیوتر یک machine_id یکتا دارد (مثلاً `4d75bfe14a964dcfb15b6741b2ef6bc2`).
- در صفحه لایسنس‌ها (`page=wplm-licenses`)، بخش «دامنه‌ها / machine_ids» هر دو را نمایش می‌دهد و امکان حذف هر کدوم فراهم است.
- محدودیت تعداد فعال‌سازی (سقف machine_ids) با فیلد `allowed_domains` کنترل می‌شود.
- APIهای activate/check/request و آپدیت از پارامتر `machine_id` پشتیبانی می‌کنند.
- در سبد خرید، برای محصولات ویندوز فیلد `machine_id` نمایش داده می‌شود و پس از پرداخت لایسنس روی همان کامپیوتر صادر می‌شود.

## فرآیند کلی

1. فروشنده محصول را در `wp-plugin-market-manager` ثبت می‌کند (افزونه، قالب یا برنامه ویندوز).
2. خریدار افزونه را از فروشگاه می‌خرد و پس از پرداخت، لایسنس صادر می‌شود.
3. برای برنامه ویندوز، خریدار machine_id کامپیوتر خود را وارد می‌کند و لایسنس روی همان کامپیوتر فعال می‌شود.
4. افزونه مشتری (با `wplm-client-kit`) با درج کلید لایسنس فعال می‌شود.
5. آپدیت افزونه از طریق صفحه استاندارد «افزونه‌ها»ی وردپرس نمایش داده می‌شود.
6. لایسنس‌ها از پنل مرکزی `wplm-amolnovin-license-manager` مدیریت می‌شوند.

برای اطلاعات تک‌تک بخش‌ها، [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) را بخوانید.

## منبع فایل‌ها

فایل‌ها از پوشه **licence manager** در گوگل درایو دانلود شدند:

- `wp-plugin-market-manager (20).zip` — 106,549 byte — نسخه 1.2.3
- `wplm-amolnovin-license-manager (7).zip` — 12,807 byte — نسخه 1.0.7
- `wplm-client-kit (7).zip` — 21,628 byte — نسخه 1.4.0/1.1.0

## بسته‌هایใหม่

- `releases/wp-plugin-market-manager-21.zip` — نسخه 1.3.0 (پشتیبانی برنامه ویندوز با machine_id)، ساخته‌شده از کدهای `src/wp-plugin-market-manager`

## تاریخچه نسخه‌ها

### wp-plugin-market-manager — نسخه 1.3.0

- نوع محصول جدید «برنامه ویندوز» با فعال‌سازی بر اساس `machine_id`.
- فیلد `machine_ids` در جدول لایسنس‌ها و `machine_id` در جدول درخواست‌ها.
- پشتیبانی machine_id در APIهای license و updates، صفحه لایسنس‌ها، مودال لایسنس و سبد خرید.

### wplm-amolnovin-license-manager — نسخه 1.0.7

- تشخیص خطای 404 سمت وب‌سرور در تست ارتباط و بررسی وضعیت rewrite/permalink.
- fallback با `?rest_route=` برای تایید سالم بودن REST API حتی وقتی `/wp-json` 404 می‌دهد.
- بخش «بررسی ارتباط با سرور» در بالای صفحه مدیریت.
- نمایش وضعیت آنلاین/آفلاین سرور، بررسی گواهی SSL، زمان پاسخ و کد HTTP.
- دکمه آزمایش ارتباط با Ajax.

### wplm-client-kit — نسخه 1.4.0 / 1.1.0

- مقاوم‌سازی در برابر خطاهای موقت سمت سرور (status حفظ، grace، fallback `?rest_route=`).
- آپدیتر نهایی کلاس `WPLM_Client_Kit_Updater` با `class_alias` برای alias قدیمی `WPLM_Plugin_Updater`.

## مجوز

GPLv2 or later (افزونه‌های وردپرس).
