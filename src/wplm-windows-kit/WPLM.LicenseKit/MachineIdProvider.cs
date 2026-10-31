using System;
using System.IO;
using System.Linq;
using System.Net.NetworkInformation;
using System.Security.Cryptography;
using System.Text;
using Microsoft.Win32;

namespace WPLM.LicenseKit
{
    /// <summary>
    /// تولید شناسهٔ ماشین (machine_id) یکتا برای هر کامپیوتر.
    /// machine_id باید ۳۲ کاراکتر هگز کوچک باشد (مثلاً 4d75bfe14a964dcfb15b6741b2ef6bc2).
    ///
    /// منابع شناسهٔ ماشین:
    ///   1. MachineGuidregistry (HKLM\SOFTWARE\Microsoft\Cryptography\MachineGuid)
    ///   2. کارت شبکه اول (MAC)
    ///   3. نام ماشین + نام کاربر + نسخه ویندوز
    ///
    /// تمام منابع با هم ترکیب و با SHA256 تبدیل به ۳۲ کاراکتر هگز می‌شوند.
    /// نتیجه در حافظه cache می‌شود و می‌تواند روی فایل ذخیره شود.
    /// </summary>
    public static class MachineIdProvider
    {
        private static readonly object Sync = new object();
        private static string _cached;

        /// <summary>
        /// دریافت machine_id. اگر <paramref name="cacheFile"/> داده شده، اول از فایل
        /// و در صورت نبود، تولید می‌شود و روی فایل ذخیره می‌گردد.
        /// </summary>
        public static string Get(string cacheFile = null)
        {
            lock (Sync)
            {
                if (!string.IsNullOrEmpty(_cached))
                {
                    return _cached;
                }

                if (!string.IsNullOrEmpty(cacheFile) && File.Exists(cacheFile))
                {
                    try
                    {
                        var fromFile = File.ReadAllText(cacheFile).Trim().ToLowerInvariant();
                        if (IsValid(fromFile))
                        {
                            _cached = fromFile;
                            return _cached;
                        }
                    }
                    catch
                    {
                        // فایل cache معتبر نیست -> تولید مجدد
                    }
                }

                _cached = Generate();

                if (!string.IsNullOrEmpty(cacheFile))
                {
                    try
                    {
                        var dir = Path.GetDirectoryName(cacheFile);
                        if (!string.IsNullOrEmpty(dir) && !Directory.Exists(dir))
                        {
                            Directory.CreateDirectory(dir);
                        }
                        File.WriteAllText(cacheFile, _cached);
                    }
                    catch
                    {
                        // ذخیره cache الزامی نیست
                    }
                }

                return _cached;
            }
        }

        /// <summary>
        /// ارزیابی validity machine_id: ۳۲ کاراکتر a-f0-9.
        /// </summary>
        public static bool IsValid(string machineId)
        {
            if (string.IsNullOrEmpty(machineId) || machineId.Length != 32)
            {
                return false;
            }

            foreach (var c in machineId)
            {
                if (!((c >= '0' && c <= '9') || (c >= 'a' && c <= 'f') || (c >= 'A' && c <= 'F')))
                {
                    return false;
                }
            }

            return true;
        }

        /// <summary>
        /// تولید machine_id از منابع سیستم.
        /// </summary>
        public static string Generate()
        {
            var parts = new System.Collections.Generic.List<string>();

            // ۱. MachineGuid ویندوز
            try
            {
                using (var key = Registry.LocalMachine.OpenSubKey(@"SOFTWARE\Microsoft\Cryptography"))
                {
                    var guid = key?.GetValue("MachineGuid") as string;
                    if (!string.IsNullOrEmpty(guid))
                    {
                        parts.Add(guid);
                    }
                }
            }
            catch
            {
                // در محیط‌های غیر ویندوز یا با دسترسی محدود
            }

            // ۲. کارت شبکه اول با MAC
            try
            {
                var mac = NetworkInterface.GetAllNetworkInterfaces()
                    .Where(ni => ni.OperationalStatus == OperationalStatus.Up)
                    .Select(ni => ni.GetPhysicalAddress().ToString())
                    .FirstOrDefault(a => !string.IsNullOrEmpty(a));

                if (!string.IsNullOrEmpty(mac))
                {
                    parts.Add(mac);
                }
            }
            catch
            {
            }

            // ۳. نام ماشین + نام کاربر + نسخه ویندوز
            parts.Add(Environment.MachineName);
            parts.Add(Environment.UserName);
            parts.Add(Environment.OSVersion.ToString());

            var raw = string.Join("|", parts);
            return ToHex32(raw);
        }

        /// <summary>
        /// تبدیل string به ۳۲ کاراکتر هگز (نصف pertama hash SHA256).
        /// </summary>
        public static string ToHex32(string input)
        {
            using (var sha = SHA256.Create())
            {
                var bytes = sha.ComputeHash(Encoding.UTF8.GetBytes(input ?? ""));
                var sb = new StringBuilder(32);
                for (var i = 0; i < 16; i++)
                {
                    sb.Append(bytes[i].ToString("x2"));
                }
                return sb.ToString();
            }
        }
    }
}
