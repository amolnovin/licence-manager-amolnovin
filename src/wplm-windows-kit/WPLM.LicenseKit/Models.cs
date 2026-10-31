using System;
using System.Collections.Generic;

namespace WPLM.LicenseKit
{
    /// <summary>
    /// وضعیت لایسنس.
    /// </summary>
    public enum LicenseStatus
    {
        Unknown = 0,
        Active = 1,
        Inactive = 2,
        Suspended = 3,
        Expired = 4
    }

    /// <summary>
    /// نتیجه فعال‌سازی/بررسی لایسنس.
    /// </summary>
    public class LicenseResult
    {
        public bool Success { get; set; }

        /// <summary>کد پاسخ سرور: ok، activated، not_found، invalid_machine_id، machine_limit و ...</summary>
        public string Code { get; set; } = "";

        /// <summary>پیام Formatted برای نمایش در UI</summary>
        public string Message { get; set; } = "";

        /// <summary>شناسه ماشین فعلی</summary>
        public string MachineId { get; set; } = "";

        /// <summary>تاریخ انقضا (Y-m-d H:i:s)</summary>
        public string ExpiresAt { get; set; } = "";

        /// <summary>دامنه‌های فعال‌شده (برای لایسنس‌های مبتنی بر دامنه)</summary>
        public List<string> Domains { get; set; } = new List<string>();

        /// <summary>تعداد مجاز فعال‌سازی</summary>
        public int MaxActivations { get; set; }

        /// <summary>وضعیت برداشته‌شده از سرور</summary>
        public LicenseStatus Status { get; set; } = LicenseStatus.Unknown;

        /// <summary>نسخه جدید اگر آپدیت موجود باشد</summary>
        public string UpdateVersion { get; set; } = "";

        /// <summary>لینک دانلود نسخه جدید</summary>
        public string UpdateUrl { get; set; } = "";

        /// <summary>changelog نسخه جدید</summary>
        public string UpdateChangelog { get; set; } = "";

        /// <summary>نیاز وردپرس/PHP نسخه جدید</summary>
        public string UpdateRequires { get; set; } = "";
    }

    /// <summary>
    /// نتیجه درخواست لایسنس جدید (POST /license/request).
    /// </summary>
    public class LicenseRequestResult
    {
        public bool Success { get; set; }
        public string Code { get; set; } = "";
        public string Message { get; set; } = "";
        public long RequestId { get; set; }
        public string RequestStatus { get; set; } = "";
    }

    /// <summary>
    /// information about available update (POST /updates/check).
    /// </summary>
    public class UpdateInfo
    {
        public bool Success { get; set; }
        public bool UpdateAvailable { get; set; }
        public string Code { get; set; } = "";
        public string Message { get; set; } = "";
        public string Version { get; set; } = "";
        public string PackageUrl { get; set; } = "";
        public string Changelog { get; set; } = "";
        public string RequiresWp { get; set; } = "";
        public string TestedWp { get; set; } = "";
        public string RequiresPhp { get; set; } = "";
    }

    /// <summary>
    /// خطای API سرور با کد و پیام.
    /// </summary>
    public class WplmApiException : Exception
    {
        public string ErrorCode { get; }

        public WplmApiException(string message, string errorCode) : base(message)
        {
            ErrorCode = errorCode ?? "";
        }

        public WplmApiException(string message) : base(message)
        {
            ErrorCode = "error";
        }
    }

    /// <summary>
    /// پیام‌های فارسی برای کدهای پاسخ سرور.
    /// </summary>
    public static class WplmMessages
    {
        public static string For(string code)
        {
            switch (code)
            {
                case "ok":
                    return "لایسنس معتبر است.";
                case "activated":
                    return "لایسنس با موفقیت فعال شد.";
                case "not_found":
                    return "کلید لایسنس یافت نشد.";
                case "product_mismatch":
                    return "کلید لایسنس به محصول دیگری تعلق دارد.";
                case "product_not_found":
                    return "محصول در سامانه فروش یافت نشد.";
                case "inactive":
                    return "لایسنس غیرفعال است.";
                case "expired":
                    return "لایسنس منقضی شده است.";
                case "suspended":
                    return "لایسنس مسدود شده است.";
                case "invalid_machine_id":
                    return "شناسه ماشین نامعتبر است (باید ۳۲ کاراکتر هگز باشد).";
                case "product_not_windows":
                    return "این محصول برای برنامه‌های ویندوز نیست.";
                case "machine_limit":
                    return "سقف machine_id تکمیل شده است.";
                case "domain_limit":
                    return "سقف دامنه مجاز تکمیل شده است.";
                case "invalid_email":
                    return "ایمیل نامعتبر است.";
                case "rate_limited":
                    return "درخواست‌های شما بیش از حد مجاز است.";
                case "license_missing":
                    return "کلید لایسنس وارد نشده است.";
                case "network_error":
                    return "خطای شبکه در ارتباط با سرور.";
                case "server_error":
                    return "خطای موقت سمت سرور.";
                case "server_db_error":
                    return "خطای موقت پایگاه‌داده سرور.";
                case "insecure_api":
                    return "آدرس API باید HTTPS باشد.";
                case "update_available":
                    return "نسخه جدید موجود است.";
                case "no_update":
                    return "نسخه شما جدید است.";
                default:
                    return string.IsNullOrEmpty(code) ? "خطای ناشناخته." : code;
            }
        }
    }
}
