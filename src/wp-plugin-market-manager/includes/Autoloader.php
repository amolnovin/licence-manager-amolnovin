<?php
namespace WPLM;

defined('ABSPATH') || exit;

final class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    private static function autoload(string $class): void
    {
        $prefix = __NAMESPACE__ . '\\';
        if (strpos($class, $prefix) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
        $file = WPLM_PATH . 'includes/' . $relative . '.php';

        if (is_readable($file)) {
            require_once $file;
        }
    }
}
