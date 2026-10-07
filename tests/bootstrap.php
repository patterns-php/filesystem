<?php

declare(strict_types=1);

/**
 * Test bootstrap.
 *
 * A minimal PSR-4 autoloader for this package, so the suite runs without
 * `composer install`. When vendor/autoload.php is present, Composer has
 * registered first and wins.
 *
 * `Patterns\Tests\` is a LONGER prefix than `Patterns\`, and the map is scanned
 * in insertion order: the test namespace has to come first, or `Patterns\Tests\*`
 * resolves against `src/` and nothing loads.
 */

spl_autoload_register(static function (string $class): void {
    /** @var array<string, list<string>> $prefixes */
    $prefixes = [
        'Patterns\\Tests\\' => [__DIR__ . '/'],
        'Patterns\\'        => [__DIR__ . '/../src/'],
    ];

    foreach ($prefixes as $prefix => $roots) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        foreach ($roots as $root) {
            if (is_file($root . $relative)) {
                require $root . $relative;

                return;
            }
        }

        return;
    }
});
