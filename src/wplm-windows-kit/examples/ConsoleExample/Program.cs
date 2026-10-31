using System;
using System.Threading.Tasks;
using WPLM.LicenseKit;

namespace WPLM.WindowsKit.Example
{
    /// <summary>
    /// نمونه استفاده از کیت لایسنس ویندوز.
    ///
    /// فقط ۳ خط تغییر می‌خواهد:
    ///   1. PRODUCT_SLUG — slug محصول در سامانه فروش
    ///   2. LICENSE_KEY   — کلید لایسنس خریداری‌شده
    ///   3. VERSION       — نسخه فعلی برنامه
    /// </summary>
    internal static class Program
    {
        private const string PRODUCT_SLUG = "my-app";
        private const string LICENSE_KEY = "XXXXXX-XXXXXX-XXXXXX-XXXXXX";
        private const string VERSION = "1.0.0";

        private static async Task Main(string[] args)
        {
            using var client = new LicenseClient(
                productSlug: PRODUCT_SLUG,
                licenseKey: LICENSE_KEY,
                cacheFile: null); // machine_id cache می‌شود

            Console.WriteLine("machine_id کامپیوتر: " + client.MachineId);

            if (args.Length > 0 && args[0] == "request")
            {
                // درخواست لایسنس جدید: dotnet run -- request
                var email = Console.ReadLine();
                var req = await client.RequestAsync(email);
                Console.WriteLine($"success={req.Success} request_id={req.RequestId} message={req.Message}");
                return;
            }

            if (args.Length > 0 && args[0] == "update")
            {
                // بررسی نسخه جدید: dotnet run -- update
                var update = await client.GetUpdateAsync(VERSION);
                if (update.UpdateAvailable)
                {
                    Console.WriteLine($"نسخه جدید: {update.Version}");
                    Console.WriteLine($"لینک دانلود: {update.PackageUrl}");
                    Console.WriteLine($"changelog: {update.Changelog}");
                }
                else
                {
                    Console.WriteLine($"{"بروزرسانی موجود نیست."}");
                }
                return;
            }

            // فعال‌سازی یا بررسی
            var result = await client.ActivateAsync();
            if (result.Success)
            {
                Console.WriteLine("✅ لایسنس فعال است.");
                Console.WriteLine("machine_id: " + result.MachineId);
                if (result.ExpiresAt.Length > 0)
                {
                    Console.WriteLine("انقضا: " + result.ExpiresAt);
                }
            }
            else
            {
                Console.WriteLine("❌ " + result.Message + " (کد: " + result.Code + ")");
                // با CheckAsync دوباره بررسی کن
                var check = await client.CheckAsync();
                Console.WriteLine("وضعیت بررسی: " + check.Message);
            }
        }
    }
}
