# WPLM Windows Kit — کیت لایسنس‌گذاری برای برنامه‌های ویندوز

کیت آماده برای قرارگیری در برنامه‌های ویندوز (C#/.NET) و اتصال به سامانه فروش و لایسنس **wp-plugin-market-manager** روی `https://amolnovin.ir`.

لایسنس ویندوز بر اساس **شناسهٔ ماشین (machine_id)** فعال می‌شود — هر کامپیوتر یک machine_id یکتا دارد (مثلاً `4d75bfe14a964dcfb15b6741b2ef6bc2`) و لایسنس روی همان کامپیوتر معتبر است.

## شامل

```text
wplm-windows-kit/
├── WPLM.LicenseKit/                  # library اصلی (netstandard2.0)
│   ├── WPLM.LicenseKit.csproj
│   ├── LicenseClient.cs              # کلاس اصلی: ActivateAsync/CheckAsync/RequestAsync/GetUpdateAsync
│   ├── MachineIdProvider.cs          # تولید machine_id یکتا برای هر کامپیوتر
│   ├── Models.cs                     # LicenseResult، UpdateInfo، خطاها
│   └── WplmJson.cs                   # JSON parser/serializer بدون وابستگی
├── examples/
│   └── ConsoleExample/               # نمونه برنامه console
├── README.md
└── docs/
    ├── USAGE.md                      # آموزش استفاده و اتصال برنامه
    └── INTEGRATION.md                # اتصال به سرور و APIها
```

## امکانات

- **machine_id یکتا** — ترکیب MachineGuid ویندوز + کارت شبکه + نام ماشین، تبدیل به ۳۲ کاراکتر هگز
- **فعال‌سازی آنلاین** — `POST /license/activate` با machine_id
- **بررسی دوره‌ای** — `POST /license/check` با machine_id
- **درخواست لایسنس** — `POST /license/request`
- **بررسی آپدیت** — `POST /updates/check`
- **HTTPS اجباری** (برای محیط تست می‌توان `allowInsecure` نکرد)
- **message فارسی** برای تمام کدهای پاسخ سرور
- **بدون وابستگی خارجی** (فقط Microsoft.Win32.Registry برای machine_id)

## استفاده سریع

۱. کپی پوشه `WPLM.LicenseKit` را در پروژه خود بگذارید (یا از NuGet استفاده کنید):

```xml
<ProjectReference Include="WPLM.LicenseKit\WPLM.LicenseKit.csproj" />
```

۲. در فایل اصلی برنامه:

```csharp
using WPLM.LicenseKit;
using System;
using System.Threading.Tasks;

internal static class Program
{
    private static async Task Main()
    {
        using var client = new LicenseClient(
            productSlug: "my-app",
            licenseKey: "XXXXXX-XXXXXX-XXXXXX-XXXXXX");

        Console.WriteLine("machine_id: " + client.MachineId);

        var result = await client.ActivateAsync();
        if (result.Success)
        {
            // لایسنس فعال است — اجرای امکانات اصلی برنامه
            Console.WriteLine("✅ لایسنس فعال است.");
        }
        else
        {
            Console.WriteLine("❌ " + result.Message);
            // اینجا می‌توانی صفحه فعال‌سازی یا خرید نمایش دهی
        }
    }
}
```

۳. برای بررسی دوره‌ای (مثلاً هر ۷ روز):

```csharp
var check = await client.CheckAsync();
if (!check.Success)
{
    // لایسنس معتبر نیست — قفل برنامه یا درخواست تمدید
}
```

## machine_id چیست؟

machine_id یک شناسه یکتا برای هر کامپیوتر است و برای لایسنس‌های ویندوز استفاده می‌شود:

- شبیه به «دامنه» در لایسنس plugin/theme، اما بر اساسฮาร์ด‌ویر
- ۳۲ کاراکتر هگز کوچک (مثل `4d75bfe14a964dcfb15b6741b2ef6bc2`)
- از MachineGuid ویندوز + کارت شبکه + نام ماشین تولید می‌شود
- در حافظه cache می‌شود و می‌تواند روی فایل ذخیره گردد (`cacheFile` بگذارید)

## سرور

این کیت با سرور `https://amolnovin.ir` (افزونه wp-plugin-manager) ارتباط برقرار می‌کند:

```text
License API: https://amolnovin.ir/wp-json/wplm/v1
Update API:  https://amolnovin.ir/wp-json/wplm/v1/updates/check
```

برای جزئیات کامل، [docs/USAGE.md](docs/USAGE.md) و [docs/INTEGRATION.md](docs/INTEGRATION.md) را بخوانید.

## محدودیت‌ها

- machine_id باید ۳۲ کاراکتر a-f0-9 باشد (کیت این کار را خودکار انجام می‌دهد)
- لایسنس ویندوز باید روی محصول با نوع **برنامه ویندوز** در پنل مدیریت ایجاد شده باشد
- سقف فعال‌سازی machine_ids با فیلد «تعداد فعال‌سازی» در لایسنس کنترل می‌شود
- APIهای سرور rate limited هستند (activate: 40/5min، check: 80/5min، request: 15/5min)

## نگارش

نسخه: **1.0.0** | .NET Standard 2.0 (.NET Framework 4.6.1+، .NET Core 2.0+، .NET 5/6/7/8+) | GPLv2
