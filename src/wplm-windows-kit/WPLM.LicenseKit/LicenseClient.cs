using System;
using System.Collections.Generic;
using System.Net.Http;
using System.Threading.Tasks;

namespace WPLM.LicenseKit
{
    /// <summary>
    /// کلاینت لایسنس برای برنامه‌های ویندوز.
    ///
    /// این کلاس با سامانه فروش و لایسنس wp-plugin-market-manager روی
    /// https://amolnovin.ir ارتباط برقرار می‌کند:
    ///
    ///   POST /wp-json/wplm/v1/license/activate   -> فعال‌سازی با machine_id
    ///   POST /wp-json/wplm/v1/license/check       -> بررسی دوره‌ای با machine_id
    ///   POST /wp-json/wplm/v1/license/request     -> درخواست لایسنس جدید
    ///   POST /wp-json/wplm/v1/updates/check       -> بررسی نسخه جدید
    ///
    /// مثال:
    /// <code>
    /// var client = new LicenseClient("my-product");
    /// var result = await client.ActivateAsync(licenseKey);
    /// if (result.Success) { /* لایسنس فعال است */ }
    /// </code>
    /// </summary>
    public class LicenseClient : IDisposable
    {
        private const string DefaultApiBase = "https://amolnovin.ir/wp-json/wplm/v1";
        private const string DefaultUpdateUrl = "https://amolnovin.ir/wp-json/wplm/v1/updates/check";

        private readonly HttpClient _http;
        private readonly string _apiBase;
        private readonly string _updateUrl;
        private readonly string _productSlug;
        private string _licenseKey;
        private string _machineId;
        private readonly TimeSpan _timeout;
        private readonly bool _allowInsecure;

        /// <summary>
        /// ساخت LicenseClient.
        /// </summary>
        /// <param name="productSlug">slug محصول در سامانه فروش (مثلاً my-app).</param>
        /// <param name="apiBase">آدرس API (پیش‌فرض https://amolnovin.ir/wp-json/wplm/v1).</param>
        /// <param name="updateUrl">آدرس API آپدیت (پیش‌فرض .../updates/check).</param>
        /// <param name="licenseKey">کلید لایسنس (اختیاری، می‌توان بعداً با ActivateAsync/CheckAsyncᠸارد کرد).</param>
        /// <param name="machineId">machine_id کامپیوتر (اختیاری؛ اگر خالی باشد خودکار تولید می‌شود).</param>
        /// <param name="cacheFile">فایل cache machine_id (اختیاری؛ چون machine_id ماندگار است).</param>
        /// <param name="timeoutSeconds">timeout HTTP (پیش‌فرض 20 ثانیه).</param>
        /// <param name="allowInsecure">allow HTTP در محیط تست (پیش‌فرض false).</param>
        public LicenseClient(
            string productSlug,
            string apiBase = null,
            string updateUrl = null,
            string licenseKey = null,
            string machineId = null,
            string cacheFile = null,
            int timeoutSeconds = 20,
            bool allowInsecure = false)
        {
            if (string.IsNullOrWhiteSpace(productSlug))
            {
                throw new ArgumentException("productSlug الزامی است.", nameof(productSlug));
            }

            _productSlug = productSlug;
            _apiBase = string.IsNullOrWhiteSpace(apiBase) ? DefaultApiBase : apiBase.TrimEnd('/');
            _updateUrl = string.IsNullOrWhiteSpace(updateUrl) ? DefaultUpdateUrl : updateUrl.TrimEnd('/');
            _licenseKey = licenseKey ?? "";
            _timeout = TimeSpan.FromSeconds(Math.Max(5, timeoutSeconds));
            _allowInsecure = allowInsecure;

            // machine_id: اگر داده شده استفاده کن، در غير این صورت خودکار تولید کن.
            if (!string.IsNullOrWhiteSpace(machineId))
            {
                _machineId = machineId.Trim().ToLowerInvariant();
            }
            else
            {
                _machineId = MachineIdProvider.Get(cacheFile);
            }

            if (!MachineIdProvider.IsValid(_machineId))
            {
                _machineId = MachineIdProvider.Generate();
            }

            _http = new HttpClient
            {
                Timeout = _timeout
            };
            _http.DefaultRequestHeaders.Add("User-Agent", "WPLM-Windows-Kit/1.0");
            _http.DefaultRequestHeaders.Add("Accept", "application/json");

            ValidateApiBase(_apiBase);
            ValidateApiBase(_updateUrl);
        }

        /// <summary>
        /// machine_id کامپیوتر فعلی.
        /// </summary>
        public string MachineId => _machineId;

        /// <summary>
        /// slug محصول.
        /// </summary>
        public string ProductSlug => _productSlug;

        /// <summary>
        /// کلید لایسنس ذخیره‌شده.
        /// </summary>
        public string LicenseKey => _licenseKey;

        /// <summary>
        /// تنظیم کلید لایسنس.
        /// </summary>
        public void SetLicenseKey(string licenseKey)
        {
            _licenseKey = licenseKey ?? "";
        }

        /// <summary>
        /// فعال‌سازی لایسنس با machine_id کامپیوتر فعلی.
        /// POST /license/activate
        /// </summary>
        /// <param name="licenseKey">کلید لایسنس. اگر خالی بود، کلید ذخیره‌شده استفاده می‌شود.</param>
        public async Task<LicenseResult> ActivateAsync(string licenseKey = null)
        {
            var key = licenseKey ?? _licenseKey;
            if (string.IsNullOrWhiteSpace(key))
            {
                throw new WplmApiException("کلید لایسنس الزامی است.", "license_missing");
            }

            var body = new Dictionary<string, object>
            {
                ["product_slug"] = _productSlug,
                ["license_key"] = key,
                ["machine_id"] = _machineId
            };

            var json = await PostAsync(_apiBase + "/license/activate", body);
            var result = ParseLicenseResult(json);
            result.Message = WplmMessages.For(result.Code);
            return result;
        }

        /// <summary>
        /// بررسی وضعیت لایسنس با machine_id کامپیوتر فعلی.
        /// POST /license/check
        /// </summary>
        /// <param name="licenseKey">کلید لایسنس. اگر خالی بود، کلید ذخیره‌شده استفاده می‌شود.</param>
        public async Task<LicenseResult> CheckAsync(string licenseKey = null)
        {
            var key = licenseKey ?? _licenseKey;
            if (string.IsNullOrWhiteSpace(key))
            {
                return new LicenseResult
                {
                    Success = false,
                    Code = "license_missing",
                    Message = WplmMessages.For("license_missing")
                };
            }

            var body = new Dictionary<string, object>
            {
                ["product_slug"] = _productSlug,
                ["license_key"] = key,
                ["machine_id"] = _machineId
            };

            var json = await PostAsync(_apiBase + "/license/check", body);
            var result = ParseLicenseResult(json);
            result.Message = WplmMessages.For(result.Code);
            return result;
        }

        /// <summary>
        /// درخواست لایسنس جدید از سرور.
        /// POST /license/request
        /// </summary>
        /// <param name="email">ایمیل خریدار (الزامی).</param>
        /// <param name="name">نام خریدار (اختیاری).</param>
        /// <param name="mobile">موبایل خریدار (اختیاری).</param>
        public async Task<LicenseRequestResult> RequestAsync(string email, string name = null, string mobile = null)
        {
            if (string.IsNullOrWhiteSpace(email))
            {
                throw new ArgumentException("email الزامی است.", nameof(email));
            }

            var body = new Dictionary<string, object>
            {
                ["product_slug"] = _productSlug,
                ["customer_email"] = email,
                ["machine_id"] = _machineId
            };
            if (!string.IsNullOrWhiteSpace(name)) body["customer_name"] = name;
            if (!string.IsNullOrWhiteSpace(mobile)) body["customer_mobile"] = mobile;

            var json = await PostAsync(_apiBase + "/license/request", body);
            var result = new LicenseRequestResult
            {
                Success = WplmJson.GetBool(json, "success"),
                Code = WplmJson.GetString(json, "code"),
                Message = WplmJson.GetString(json, "message"),
                RequestId = WplmJson.GetLong(json, "request_id"),
                RequestStatus = WplmJson.GetString(json, "status")
            };

            if (!result.Success && result.Message.Length == 0)
            {
                result.Message = WplmMessages.For(result.Code);
            }
            return result;
        }

        /// <summary>
        /// بررسی نسخه جدید از سرور.
        /// POST /updates/check
        /// </summary>
        /// <param name="currentVersion">نسخه فعلی برنامه ویندوز.</param>
        public async Task<UpdateInfo> GetUpdateAsync(string currentVersion)
        {
            var body = new Dictionary<string, object>
            {
                ["product_slug"] = _productSlug,
                ["license_key"] = _licenseKey,
                ["machine_id"] = _machineId,
                ["version"] = currentVersion
            };

            var json = await PostAsync(_updateUrl, body);
            var info = new UpdateInfo
            {
                Success = WplmJson.GetBool(json, "success"),
                Code = WplmJson.GetString(json, "code"),
                Message = WplmJson.GetString(json, "message")
            };

            // بررسی update object
            if (json != null && json.TryGetValue("update", out var updateRaw) && updateRaw is Dictionary<string, object> update)
            {
                info.UpdateAvailable = true;
                info.Version = WplmJson.GetString(update, "version");
                info.PackageUrl = WplmJson.GetString(update, "url") != ""
                    ? WplmJson.GetString(update, "url")
                    : WplmJson.GetString(update, "package");
                info.Changelog = WplmJson.GetString(update, "changelog");
                info.RequiresWp = WplmJson.GetString(update, "requires");
                info.TestedWp = WplmJson.GetString(update, "tested");
                info.RequiresPhp = WplmJson.GetString(update, "requires_php");
            }

            if (info.Code == "no_update")
            {
                info.UpdateAvailable = false;
            }
            if (info.Message.Length == 0)
            {
                info.Message = WplmMessages.For(info.Code);
            }
            return info;
        }

        // ---------- private helpers ----------

        private async Task<Dictionary<string, object>> PostAsync(string url, Dictionary<string, object> body)
        {
            ValidateApiBase(url);

            var content = new StringContent(WplmJson.Serialize(body), System.Text.Encoding.UTF8, "application/json");

            HttpResponseMessage response;
            try
            {
                response = await _http.PostAsync(url, content).ConfigureAwait(false);
            }
            catch (Exception ex)
            {
                throw new WplmApiException("خطای شبکه: " + ex.Message, "network_error");
            }

            var raw = await response.Content.ReadAsStringAsync().ConfigureAwait(false);
            var parsed = TryParse(raw);

            if (parsed == null)
            {
                // سرور خطای HTML (مثلاً خطای پایگاه‌داده) برگرداند.
                throw new WplmApiException("پاسخ سرور JSON نبود: " + Truncate(raw, 200), "server_error");
            }

            return parsed;
        }

        private Dictionary<string, object> ParseLicenseResult(Dictionary<string, object> json)
        {
            var result = new LicenseResult
            {
                Success = WplmJson.GetBool(json, "success"),
                Code = WplmJson.GetString(json, "code"),
                MachineId = WplmJson.GetString(json, "machine_id") != ""
                    ? WplmJson.GetString(json, "machine_id")
                    : _machineId
            };

            // license object اگر موجود باشد
            if (json.TryGetValue("license", out var licenseRaw) && licenseRaw is Dictionary<string, object> license)
            {
                result.ExpiresAt = WplmJson.GetString(license, "expire_date");
                result.MaxActivations = (int)WplmJson.GetLong(license, "max_domains");
                if (license.TryGetValue("domains", out var domainsRaw) && domainsRaw is List<object> domains)
                {
                    foreach (var d in domains)
                    {
                        result.Domains.Add(Convert.ToString(d));
                    }
                }
                var status = WplmJson.GetString(license, "status");
                result.Status = status.ToLowerInvariant() switch
                {
                    "active" => LicenseStatus.Active,
                    "inactive" => LicenseStatus.Inactive,
                    "suspended" => LicenseStatus.Suspended,
                    "expired" => LicenseStatus.Expired,
                    _ => LicenseStatus.Unknown
                };
            }

            return result;
        }

        private static Dictionary<string, object> TryParse(string raw)
        {
            try
            {
                return WplmJson.ParseObject(raw);
            }
            catch
            {
                return null;
            }
        }

        private void ValidateApiBase(string url)
        {
            if (_allowInsecure)
            {
                return;
            }

            if (!url.StartsWith("https://", StringComparison.OrdinalIgnoreCase))
            {
                throw new WplmApiException("آدرس API باید HTTPS باشد: " + url, "insecure_api");
            }
        }

        private static string Truncate(string s, int max)
        {
            if (s == null) return "";
            return s.Length <= max ? s : s.Substring(0, max) + "...";
        }

        /// <summary>
        /// release underlying resources.
        /// </summary>
        public void Dispose()
        {
            _http?.Dispose();
        }
    }
}
