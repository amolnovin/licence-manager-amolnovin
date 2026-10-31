# آموزش استفاده از کیت لایسنس ویندوز (USAGE)

این راهنما نحوه قرارگیری `WPLM.LicenseKit` در برنامه ویندوز و استفاده از آن را توضیح می‌دهد.

## ۱. پیش‌نیازها

- برنامه ویندوز با C# و .NET (Framework 4.6.1+ یا .NET Core/5/6/7/8+)
- محصول در سامانه فروش `wp-plugin-market-manager` با نوع **برنامه ویندوز** ثبت شده باشد
- کلید لایسنس خریداری‌شده یا درخواست داده شده باشد

## ۲. اضافه کردن کیت به پروژه

### روش A: ProjectReference

کپی پوشه `WPLM.LicenseKit` را در پروژه قرار دهید:

```text
my-app/
├── my-app.csproj
├── Program.cs
└── WPLM.LicenseKit/
    ├── WPLM.LicenseKit.csproj
    ├── LicenseClient.cs
    ├── MachineIdProvider.cs
    ├── Models.cs
    └── WplmJson.cs
```

در `my-app.csproj`:

```xml
<ProjectReference Include="WPLM.LicenseKit\WPLM.LicenseKit.csproj" />
```

### روش B: שליחת dll

برنامه بسازید (`dotnet build -c Release`) و `WPLM.LicenseKit.dll` را در پوشه خروجی قرار دهید.

## ۳. ساخت LicenseClient

```csharp
using WPLM.LicenseKit;

var client = new LicenseClient(
    productSlug: "my-app",            // slug محصول در سامانه فروش
    licenseKey: "XXXXXX-XXXXXX-XXXXXX-XXXXXX", // کلید لایسنس
    cacheFile: null);                 // machine_id cache شود
```

پارامترهای اختیاری:

| پارامتر | پیش‌فرض | توضیح |
|---|---|---|
| `apiBase` | `https://amolnovin.ir/wp-json/wplm/v1` | آدرس API سرور |
| `updateUrl` | `https://amolnovin.ir/wp-json/wplm/v1/updates/check` | آدرس API آپدیت |
| `machineId` | خودکار | machine_id خاص (اگر خالی بود خودکار تولید می‌شود) |
| `cacheFile` | null | فایل cache machine_id (مثلاً `%ProgramData%\MyApp\machine.id`) |
| `timeoutSeconds` | 20 | timeout HTTP |
| `allowInsecure` | false | allow HTTP برای تست (در محیط واقعی activate نکنید) |

## ۴. دریافت machine_id

machine_id self-detect می‌شود:

```csharp
Console.WriteLine(client.MachineId); // مثلاً 4d75bfe14a964dcfb15b6741b2ef6bc2
```

این شناسهٔ one-time produced از MachineGuid ویندوز + کارت شبکه + نام ماشین است و برای هر کامپیوتر یکتاست.

برای ذخیره ماندگار (توصیه می‌شود):

```csharp
var cacheFile = Path.Combine(
    Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),
    "MyApp", "machine.id");

var client = new LicenseClient("my-app", licenseKey: KEY, cacheFile: cacheFile);
```

## ۵. فعال‌سازی لایسنس

```csharp
var result = await client.ActivateAsync();

if (result.Success)
{
    // ✅ لایسنس فعال است
    Console.WriteLine("machine_id: " + result.MachineId);
    if (!string.IsNullOrEmpty(result.ExpiresAt))
        Console.WriteLine("انقضا: " + result.ExpiresAt);
    // اجرای امکانات اصلی برنامه
}
else
{
    // ❌ لایسنس معتبر نیست
    Console.WriteLine(result.Message); // پیام فارسی (مثلاً «سقف machine_id تکمیل شده است»)
}
```

### كدهای پاسخ

| کد | معنی |
|---|---|
| `ok` / `activated` | معتبر / فعال‌سازی موفق |
| `not_found` | کلید لایسنس یافت نشد |
| `product_mismatch` | کلید به محصول دیگری تعلق دارد |
| `inactive` / `expired` / `suspended` | وضعیت نامناسب |
| `invalid_machine_id` | machine_id نامعتبر |
| `product_not_windows` | محصول ویندوز نیست |
| `machine_limit` | سقف machine_ids تکمیل شده |
| `rate_limited` | بیش از حد درخواست |

## ۶. بررسی دوره‌ای لایسنس

مثلاً هر ۷ روز:

```csharp
var check = await client.CheckAsync();
if (!check.Success)
{
    // لایسنس معتبر نیست:
    // - فعال‌سازی جدید بخواه
    // - یا قفل برنامه
    Console.WriteLine("لایسنس معتبر نیست: " + check.Message);
}
```

## ۷. درخواست لایسنس جدید (برای مشتریان جدید)

```csharp
var req = await client.RequestAsync(
    email: "customer@example.com",
    name: "علی امروزی",
    mobile: "09123456789");

if (req.Success)
{
    Console.WriteLine($"درخواست ثبت شد: #{req.RequestId} (انتظار تأیید مدیر)");
}
```

## ۸. بررسی آپدیت

```csharp
var update = await client.GetUpdateAsync("1.0.0"); // نسخه فعلی

if (update.UpdateAvailable)
{
    Console.WriteLine($"نسخه جدید: {update.Version}");
    Console.WriteLine($"لینک دانلود: {update.PackageUrl}");
    Console.WriteLine($"changelog: {update.Changelog}");
}
```

## ۹. ساختار کد برنامه اصلی (نمونه کامل)

```csharp
using System;
using System.IO;
using System.Threading.Tasks;
using WPLM.LicenseKit;

internal static class Program
{
    private static LicenseClient _client;

    private static async Task Main()
    {
        var cacheFile = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),
            "MyApp", "machine.id");

        _client = new LicenseClient(
            productSlug: "my-app",
            licenseKey: LoadSavedLicenseKey(), // از config/registry
            cacheFile: cacheFile);

        var result = await _client.ActivateAsync();
        if (!result.Success)
        {
            ShowActivationForm(); // فرم فعال‌سازی/خرید
            await ActivateFromUserInput();
        }

        // اجرای امکانات اصلی
        RunApplication();

        // بررسی دوره‌ای
        await PeriodicCheck();
    }

    private static void RunApplication()
    {
        // وقتی لایسنس فعال است
        Console.WriteLine("برنامه شروع شد ✅");
    }
}
```

## ۱۰. عیب‌یابی

### پیام «آدرس API باید HTTPS باشد»

`allowInsecure` را روی `true` نکنید مگر برای تست محلی. برای محیط واقعی همیشه `https://` استفاده کنید.

### پیام «شناسه ماشین نامعتبر است»

machine_id باید دقیقاً ۳۲ کاراکتر a-f0-9 باشد. کیت این کار را خودکار انجام می‌دهد؛ در صورت لزوم `machineId` را مستقیم بدهید:

```csharp
var client = new LicenseClient("my-app", machineId: "4d75bfe14a964dcfb15b6741b2ef6bc2");
```

### پیام «سقف machine_id تکمیل شده است»

تعداد machine_ids مجاز لایسنس تکمیل شده. در پنل مدیریت (`page=wplm-licenses`) machine_id فعلی را حذف کنید یا لایسنس تمدید کنید.

### پیام «خطای شبکه در ارتباط با سرور»

- فایروال کامپیوتر یا سرور دسترسی به `amolnovin.ir:443` را مسدود می‌کند؟
- سرور در دسترس است؟
- اگر سرور HTTPS مشکل داشت، با `allowInsecure: true` تست کنید.

### خطای JSON / پاسخ HTML

سرور پاسخ JSON برگرداند. بررسی کنید:
- سرور که install شده (`wp-plugin-manager`)؟
- permalink/rewrite برقرار است؟
- API `/wp-json/wplm/v1` قابل دسترسی است؟

## ۱۱. چک‌لیست نهایی

- [ ] `WPLM.LicenseKit` در پروژه قرار دارد.
- [ ] `productSlug` دقیقاً برابر slug محصول در سامانه فروش است.
- [ ] `licenseKey` معتبر است.
- [ ] machine_id به‌صورت self-detect شده و cache شده است.
- [ ] بر روی یک کامپیوتر تست، فعال‌سازی موفقیت‌آمیز بوده است.
- [ ] بررسی دوره‌ای و آپدیت تست شده است.
- [ ] کدهای پاسخ سرور در UI به‌صورت فارسی نمایش داده می‌شوند.
