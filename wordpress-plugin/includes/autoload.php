<?php
/**
 * PSR-4 autoloader for the Sog\ namespace.
 *
 * Maps Sog\ClassName to includes/ClassName.php so the plugin works
 * as a standalone WordPress plugin with zero external dependencies.
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'Sog\\';
    $base_dir = __DIR__ . '/';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
