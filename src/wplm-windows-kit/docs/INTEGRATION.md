# اتصال کیت ویندوز به سرور (INTEGRATION)

این راهنما نحوه اتصال `WPLM.LicenseKit` به سامانه فروش و لایسنس wp-plugin-manager روی `https://amolnovin.ir` را توضیح می‌دهد.

## ۱. سرور

سامانه فروش باید افزونه **wp-plugin-manager** نصب و فعال باشد. در سرور:

1. محصول را با نوع **برنامه ویندوز** ثبت کنید.
2. slug محصول را یادداشت کنید (مثلاً `my-app`).
3. پس از خرید، لایسنس با machine_id صادر می‌شود.
4. برای آپدیت، در بخش «بروزرسانی‌ها» نسخه جدید و فایل ZIP را آپلود کنید.

## ۲. APIها

### ۲.۱ فعال‌سازی — `POST /wp-json/wplm/v1/license/activate`

فعال‌سازی لایسنس روی machine_id کامپیوتر فعلی.

**Body (JSON):**

| پارامتر | الزاری | توضیح |
|---|---|---|
| `license_key` | ✅ | کلید لایسنس (format `XXXX-XXXX-XXXX-XXXX`) |
| `product_slug` | ✅ | slug محصول |
| `machine_id` | — | شناسهٔ ماشین (برای برنامه ویندوز) |

**پاسخ موفق:**

```json
{
  "success": true,
  "code": "activated",
  "machine_id": "4d75bfe14a964dcfb15b6741b2ef6bc2"
}
```

### ۲.۲ بررسی — `POST /wp-json/wplm/v1/license/check`

بررسی وضعیت لایسنس با machine_id.

**Body:** همان activate (license_key، product_slug، machine_id)

**پاسخ:** `{ "success": true, "code": "ok", "machine_id": "..." }`

### ۲.۳ درخواست لایسنس — `POST /wp-json/wplm/v1/license/request`

ایجاد درخواست لایسنس برای مشتری جدید (پشتیبان manager تأیید می‌کند).

**Body:**

| پارامتر | الزاری | توضیح |
|---|---|---|
| `customer_email` | ✅ | ایمیل مشتری |
| `product_slug` | ✅ | slug محصول |
| `customer_name` | — | نام مشتری |
| `customer_mobile` | — | موبایل مشتری |
| `machine_id` | — | شناسهٔ ماشین |

**پاسخ:** `{ "success": true, "request_id": 12, "status": "pending" }`

### ۲.۴ بررسی آپدیت — `POST /wp-json/wplm/v1/updates/check`

بررسی نسخه جدید.

**Body:**

| پارامتر | الزاری | توضیح |
|---|---|---|
| `product_slug` | ✅ | slug محصول |
| `license_key` | — | کلید لایسنس |
| `machine_id` | — | شناسهٔ ماشین |
| `version` | — | نسخه فعلی برنامه ویندوز |

**پاسخ وقتی آپدیت موجود است:**

```json
{
  "success": true,
  "code": "update_available",
  "update": {
    "version": "2.0.0",
    "url": "https://amolnovin.ir/wp-admin/admin-post.php?action=wplm_download_package&token=...",
    "changelog": "..."
  }
}
```

## ۳. machine_id

machine_id شناسهٔ one-time produced برای هر کامپیوتر است:

- ۳۲ کاراکتر هگز کوچک (مثل `4d75bfe14a964dcfb15b6741b2ef6bc2`)
- از MachineGuid ویندوز + کارت شبکه + نام ماشین تولید می‌شود
- در حافظه cache می‌شود و می‌تواند روی فایل ذخیره گردد
- سقف فعال‌سازی machine_ids با فیلد «تعداد فعال‌سازی» در لایسنس کنترل می‌شود

### تولید دستی machine_id

اگر می‌خواهید machine_id را خودتان تولید کنید (مثلاً از WMI/Registry در C#):

```csharp
using System;
using System.Security.Cryptography;
using System.Text;
using Microsoft.Win32;

public static string GetMachineId()
{
    var key = Registry.LocalMachine.OpenSubKey(@"SOFTWARE\Microsoft\Cryptography");
    var guid = (string)key.GetValue("MachineGuid");
    using var sha = SHA256.Create();
    var bytes = sha.ComputeHash(Encoding.UTF8.GetBytes(guid + Environment.MachineName));
    return BitConverter.ToString(bytes).Replace("-", "").ToLowerInvariant().Substring(0, 32);
}
```

## ۴. خطای پاسخ سرور

| کد | معنی |
|---|---|
| `ok` | معتبر |
| `activated` | فعال‌سازی موفق |
| `not_found` | کلید لایسنس یافت نشد |
| `product_mismatch` | کلید به محصول دیگری تعلق دارد |
| `inactive` | غیرفعال |
| `expired` | منقضی |
| `suspended` | مسدود |
| `invalid_machine_id` | format machine_id نامعتبر |
| `product_not_windows` | محصول ویندوز نیست |
| `machine_limit` | سقف machine_ids تکمیل شده |
| `invalid_email` | ایمیل نامعتبر |
| `rate_limited` | بیش از حد درخواست |
| `network_error` | خطای شبکه |
| `server_error` | خطای سرور |
| `insecure_api` | آدرس باید HTTPS باشد |

## ۵. مثال کامل با LicenseClient

```csharp
using System;
using System.Threading.Tasks;
using WPLM.LicenseKit;

internal static class Program
{
    private static async Task Main()
    {
        using var client = new LicenseClient("my-app", licenseKey: "XXXXXX-XXXXXX-XXXXXX-XXXXXX");

        // فعال‌سازی
        var activate = await client.ActivateAsync();
        if (activate.Success)
        {
            Console.WriteLine("✅ " + activate.Message);
        }

        // بررسی آپدیت
        var update = await client.GetUpdateAsync("1.0.0");
        if (update.UpdateAvailable)
        {
            Console.WriteLine($"نسخه جدید: {update.Version}");
        }
    }
}
```

## ۶. خرید و صدور لایسنس

فرآیند خرید از فروشگاه (WordPress):

1. مشتری در فروشگاه محصول ویندوز را خریداری می‌کند.
2. در سبد خرید، فیلد **machine_id** را وارد می‌کند (برنامه ویندوز machine_id را در خود تولید می‌کند).
3. پس از پرداخت موفق، لایسنس صادر می‌شود و machine_id در لایسنس ثبت می‌شود.
4. مشتری ماشین machine_id را در برنامه ویندوز وارد می‌کند (یا برنامه خودکار آن را می‌خواند).
5. لایسنس فعال می‌شود.

برای فعال‌سازی دستی پس از خرید:

```csharp
var client = new LicenseClient("my-app", licenseKey: boughtKey);
var result = await client.ActivateAsync(); // machine_id خودکار ارسال می‌شود
```

## ۷. امنیت

- همیشه HTTPS استفاده کنید (`allowInsecure` فقط برای تست محلی).
- machine_id را در config ذخیره نکنید؛ آن را self-detect کنید و cache کنید.
- کلید لایسنس را رمزنگاری کنید (مثلاً AES با key برنامه) — در هر صورت این یک مقیاس جلوگیری از کپی است،ไม่ใช่ security کامل.
- APIهای سرور rate limited هستند — درخواست‌های تکراری را مدیریت کنید.

## ۸. عیب‌یابی سرور

### پاسخ HTML به‌جای JSON

سرور `/wp-json` روی amolnovin.ir 404 می‌دهد (تنظیمات permalink). کلاینت خطای JSON را به‌صورت «خطای سرور» نمایش می‌دهد. برای اطلاعات بیشتر، [استاندارد REST APIهای سرور](../../../src/wp-plugin-manager/docs/CLIENT-UPDATER.md) را بررسی کنید.

### machine_id معتبر نیست

machine_id باید دقیقاً ۳۲ کاراکتر a-f0-9 باشد. اگر از `MachineIdProvider.Get()` استفاده می‌کنید، این شرط برقرار است. در صورت لزوم، مستقیم `machineId` دهید.
