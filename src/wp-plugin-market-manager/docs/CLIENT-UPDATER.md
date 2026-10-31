# راهنمای اتصال بروزرسانی افزونه مشتری

برای اینکه بروزرسانی محصول در بخش «افزونه‌ها»ی وردپرس مشتری نمایش داده شود، فایل زیر را داخل افزونه مشتری قرار دهید:

```text
client/wplm-plugin-updater.php
```

نمونه استفاده در فایل اصلی افزونه مشتری:

```php
require_once plugin_dir_path(__FILE__) . 'includes/wplm-plugin-updater.php';

new WPLM_Plugin_Updater([
    'api_url'        => 'https://YOUR-SHOP.com/wp-json/wplm/v1/updates/check',
    'plugin_file'    => __FILE__,
    'plugin_slug'    => 'my-plugin',
    'product_slug'   => 'my-plugin',
    'version'        => MY_PLUGIN_VERSION,
    'license_option' => 'my_plugin_license_key',
    'name'           => 'نام افزونه',
    'author'         => 'نام شما',
]);
```

کلاس بالا به فیلترهای استاندارد وردپرس وصل می‌شود:

- `pre_set_site_transient_update_plugins`
- `plugins_api`

بنابراین اگر نسخه جدید موجود باشد، بروزرسانی در صفحه «افزونه‌ها» و صفحه «بروزرسانی‌های وردپرس» نمایش داده می‌شود.
