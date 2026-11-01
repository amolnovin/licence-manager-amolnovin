<?php
namespace WPLM\Support;

defined('ABSPATH') || exit;

/**
 * پل فونت قالب.
 *
 * افزونه هیچ فونت ثابتی (پیش‌تر Tahoma) تحمیل نمی‌کند و تلاش می‌کند فونت قالب فعال را از سه منبع پیدا کند:
 *   ۱) فیلتر `wplm_theme_font_family` (بالاترین اولویت؛ برای قالب‌ها و سایت‌های سفارشی)
 *   ۲) `theme.json` → `settings.typography.fontFamilies` (تم‌های بلوکی و قالب‌هایی مثل قالب آمل نوین)
 *   ۳) متغیر CSS ‏`--font` یا `--font-family` در `style.css` قالب
 *
 * خروجی به سمت شیت‌های افزونه به‌صورت متغیر CSS ‏`--wplm-font` تزریق می‌شود؛ اگر فونتی شناسایی نشود،
 * همه‌چیز روی `inherit` می‌ماند تا فونت والد (قالب یا پیشخوان وردپرس) استفاده شود.
 *
 * در پیشخوان وردپرس، استایل قالب بارگذاری نمی‌شود؛ بنابراین اگر فایل فونت قالب (woff2/woff) داخل پوشه‌ی
 * قالب پیدا شود، همان فایل با `@font-face` ثبت می‌شود تا صفحات مدیریت افزونه هم دقیقاً با فونت قالب دیده شوند.
 */
final class ThemeFont
{
    /** @var string|null کش استک فونت قالب */
    private static $stack_cache = null;

    /** @var string|null کش @font-face فونت قالب */
    private static $face_cache = null;

    /** پوشه‌های متداول فونت در قالب‌ها (نسبت به ریشه‌ی قالب) */
    private const FONT_DIRS = [
        'resources/fonts',
        'assets/fonts',
        'fonts',
        'public/build/assets',
        'build/assets',
        'dist/assets',
        'dist/fonts',
        'assets',
    ];

    /** خانواده‌های فونت سیستمی که نیازی به @font-face ندارند */
    private const SYSTEM_FAMILIES = [
        'inherit', 'initial', 'unset', 'revert', 'revert-layer',
        'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy',
        'system-ui', 'ui-sans-serif', 'ui-serif', 'ui-monospace', 'ui-rounded',
        '-apple-system', 'blinkmacsystemfont', 'segoe ui', 'tahoma', 'arial',
        'helvetica', 'helvetica neue', 'verdana', 'roboto', 'noto sans',
        'sans', 'sans serif', 'sansserif', 'emojis',
    ];

    /** استک فونت قالب به‌صورت مقدار آمادهٔ `font-family` ('' یعنی از والد ارث ببرد) */
    public static function stack(): string
    {
        return self::resolve_stack();
    }

    /** آیا فونت اختصاصی قالب شناسایی شد؟ */
    public static function detected(): bool
    {
        return '' !== self::resolve_stack();
    }

    /** CSS ‏`@font-face` فونت قالب ('' اگر فایلی پیدا نشود یا فونت سیستمی باشد) */
    public static function face_css(): string
    {
        if (null !== self::$face_cache) {
            return self::$face_cache;
        }

        $stack = self::stack();
        self::$face_cache = '' === $stack ? '' : self::face_css_for($stack);

        return self::$face_cache;
    }

    /** CSS آماده برای صفحات پیشخوان افزونه ('' وقتی فونتی شناسایی نشده باشد) */
    public static function admin_css(): string
    {
        return self::css_for(['.wplm-wrap', '.wplm-admin']);
    }

    /** CSS برای فروشگاه و شورت‌کدها؛ فقط یک متغیر محلی روی خود ریشه‌ی افزونه ست می‌کند */
    public static function public_css(): string
    {
        $stack = self::stack();

        if ('' === $stack) {
            return '';
        }

        // در فرانت خود قالب فونت را بارگذاری می‌کند؛ فقط متغیر CSS لازم است.
        return '.wplm-store{--wplm-font:' . $stack . '}';
    }

    /**
     * ساخت CSS برای یک یا چند انتخابگر.
     *
     * @param string|string[] $selectors
     */
    public static function css_for($selectors): string
    {
        $stack = self::stack();

        if ('' === $stack) {
            return '';
        }

        $list = is_array($selectors) ? implode(',', $selectors) : (string) $selectors;
        $list = self::sanitize_selectors($list);

        if ('' === $list) {
            return '';
        }

        $css = self::face_css();
        $css .= $list . '{font-family:' . $stack . '}';

        // فیلدهای فرم در مرورگرها فونت را ارث نمی‌برند
        $controls = [];

        foreach (explode(',', $list) as $selector) {
            $selector = trim($selector);

            if ('' === $selector) {
                continue;
            }

            foreach (['input', 'select', 'textarea', 'button', '.button'] as $tag) {
                $controls[] = $selector . ' ' . $tag;
            }
        }

        if (! empty($controls)) {
            $css .= implode(',', $controls) . '{font-family:inherit}';
        }

        return $css;
    }

    /** پاک‌کردن کش (مثلاً بعد از تغییر قالب) */
    public static function flush(): void
    {
        self::$stack_cache = null;
        self::$face_cache = null;
    }

    private static function resolve_stack(): string
    {
        if (null === self::$stack_cache) {
            /** @var mixed $filtered */
            $filtered = apply_filters('wplm_theme_font_family', '');
            $stack = is_string($filtered) ? self::sanitize_stack($filtered) : '';

            if ('' === $stack) {
                $stack = self::from_theme_json();
            }

            if ('' === $stack) {
                $stack = self::from_stylesheet();
            }

            self::$stack_cache = $stack;
        }

        return self::$stack_cache;
    }

    /** خواندن استک فونت از theme.json */
    private static function from_theme_json(): string
    {
        if (! function_exists('wp_get_global_settings')) {
            return '';
        }

        $families = wp_get_global_settings(['typography', 'fontFamilies']);

        if (! is_array($families) || empty($families)) {
            return '';
        }

        $preferred = '';

        if (function_exists('wp_get_global_styles')) {
            $styles = wp_get_global_styles();

            if (is_array($styles) && isset($styles['typography']['fontFamily']) && is_string($styles['typography']['fontFamily'])) {
                if (preg_match('/--wp--preset--font-family--([a-z0-9_-]+)/i', $styles['typography']['fontFamily'], $match)) {
                    $preferred = strtolower($match[1]);
                }
            }
        }

        $chosen = null;

        foreach ($families as $family) {
            if (! is_array($family) || empty($family['fontFamily']) || ! is_string($family['fontFamily'])) {
                continue;
            }

            if (null === $chosen) {
                $chosen = $family;
            }

            if ('' !== $preferred && isset($family['slug']) && strtolower((string) $family['slug']) === $preferred) {
                $chosen = $family;
                break;
            }
        }

        return $chosen ? self::sanitize_stack((string) $chosen['fontFamily']) : '';
    }

    /** خواندن متغیر `--font` از style.css قالب (و قالب مادر) */
    private static function from_stylesheet(): string
    {
        $files = [];

        if (function_exists('get_stylesheet_directory')) {
            $files[] = get_stylesheet_directory() . '/style.css';
        }

        if (function_exists('get_template_directory')) {
            $files[] = get_template_directory() . '/style.css';
        }

        foreach (array_unique($files) as $file) {
            if (! is_readable($file)) {
                continue;
            }

            $css = @file_get_contents($file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- خواندن یک فایل محلی قالب، بدون درخواست شبکه.

            if (! is_string($css) || '' === $css) {
                continue;
            }

            if (preg_match_all('/--font(?:-family)?\s*:\s*([^;{}]+)/i', $css, $matches)) {
                foreach ($matches[1] as $value) {
                    $stack = self::sanitize_stack((string) $value);

                    if ('' !== $stack && ! self::is_system_stack($stack)) {
                        return $stack;
                    }
                }
            }
        }

        return '';
    }

    /** ساخت `@font-face` از فایل فونت داخل پوشه‌ی قالب */
    private static function face_css_for(string $stack): string
    {
        $family = self::first_family($stack);

        if ('' === $family || self::is_system_stack($family)) {
            return '';
        }

        $needle = preg_replace('/[^a-z0-9]/', '', strtolower($family));

        if (! is_string($needle) || strlen($needle) < 3 || ! function_exists('glob')) {
            return '';
        }

        $url = '';

        foreach (['woff2', 'woff'] as $extension) {
            foreach (self::FONT_DIRS as $dir) {
                $dir = trim($dir, '/');
                $found = glob(trailingslashit(get_theme_file_path($dir)) . '*.' . $extension);
                $found = is_array($found) ? $found : [];

                sort($found);

                foreach ($found as $path) {
                    $name = preg_replace('/[^a-z0-9]/', '', strtolower(basename($path)));

                    if (! is_string($name) || false === strpos($name, $needle)) {
                        continue;
                    }

                    $candidate = $dir . '/' . basename($path);

                    if (! is_readable(get_theme_file_path($candidate))) {
                        continue;
                    }

                    $url = (string) get_theme_file_uri($candidate);
                    break 3;
                }
            }
        }

        /** @var mixed $url */
        $url = apply_filters('wplm_theme_font_url', $url, $family);

        if (! is_string($url) || '' === $url) {
            return '';
        }

        return sprintf(
            '@font-face{font-family:%1$s;src:url("%2$s") format("%3$s");font-weight:100 900;font-style:normal;font-display:swap}',
            self::quote_family($family),
            esc_url_raw($url),
            false !== strpos($url, '.woff2') ? 'woff2' : 'woff'
        );
    }

    private static function first_family(string $stack): string
    {
        $parts = explode(',', $stack);
        $first = trim((string) $parts[0]);
        $first = trim($first, "\"' \t\n\r\0\x0B");

        return $first;
    }

    private static function quote_family(string $family): string
    {
        $family = str_replace(['"', "'"], '', $family);
        $family = trim($family);

        return '"' . $family . '"';
    }

    private static function is_system_stack(string $stack): bool
    {
        $family = self::first_family($stack);

        return in_array(strtolower(trim($family)), self::SYSTEM_FAMILIES, true);
    }

    /** حذف کاراکترهای خطرناک تا مقدار فونت نتواند از CSS فرار کند */
    private static function sanitize_stack(string $stack): string
    {
        $stack = str_replace(["\r", "\n", "\t", '{', '}', '<', '>', ';', '\\', 'url('], ' ', $stack);
        $stack = trim(preg_replace('/\s+/', ' ', $stack));

        if ('' === $stack) {
            return '';
        }

        if (strlen($stack) > 300) {
            $stack = substr($stack, 0, 300);
        }

        // یک استک معتبر باید حداقل یک نام فونت داشته باشد
        $family = self::first_family($stack);

        if ('' === $family) {
            return '';
        }

        return $stack;
    }

    private static function sanitize_selectors(string $selectors): string
    {
        $selectors = str_replace(["\r", "\n", '{', '}', '<', '>', ';'], '', $selectors);
        $parts = [];

        foreach (explode(',', $selectors) as $selector) {
            $selector = trim($selector);

            if ('' === $selector) {
                continue;
            }

            if (! preg_match('/^[\w\s.#:\[\]="\'*+>~-]+$/', $selector)) {
                continue;
            }

            $parts[] = $selector;
        }

        return implode(',', $parts);
    }
}
