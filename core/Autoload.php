<?php

/**
 * Registers a simple PSR-4 style autoloader for project namespaces.
 *
 * Namespace prefix to base directory mappings (relative to this file):
 * - App\     => ../app/
 * - Core\    => ../core/
 * - Modules\ => ../modules/
 *
 * When a class is requested, its prefix is matched, the remainder of the
 * fully qualified class name is converted from namespace separators (\)
 * to directory separators (/), ".php" is appended, and the file is loaded
 * if it exists.
 *
 * @param string $class Fully qualified class name to autoload.
 * @return void
 */

spl_autoload_register(function ($class) {
    $prefixes = [
        'App\\'  => __DIR__ . '/../app/',
        'Core\\' => __DIR__ . '/../core/',
        "Modules\\" => __DIR__ . '/../modules/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) === 0) {
            $relative = substr($class, $len);
            $relative = str_replace('\\', '/', $relative);
            $file = $baseDir . $relative . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});
