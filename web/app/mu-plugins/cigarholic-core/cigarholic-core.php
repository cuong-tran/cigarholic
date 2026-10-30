<?php

/**
 * Plugin Name:  Cigarholic Core
 * Description:  Site plugin for the Cigarholic store. One feature per class in src/, named by feature ID.
 * Version:      0.1.0
 * Requires PHP: 8.3
 * Author:       Cigarholic
 */

namespace Cigarholic\Core;

defined('ABSPATH') || exit;

spl_autoload_register(static function (string $class): void {
    $prefix = __NAMESPACE__ . '\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

MailGuard::register();
