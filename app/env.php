<?php
declare(strict_types=1);

/**
 * Load environment variables from .env file
 */
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        throw new RuntimeException(".env file not found at: " . $path);
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            // Set as environment variable and make it accessible via getenv()
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

/**
 * Get environment variable with optional default
 *
 * Sengaja tanpa type hint `mixed` supaya tetap jalan di PHP 7.4 maupun 8.x.
 *
 * @param  mixed $default
 * @return mixed
 */
function env(string $key, $default = null)
{
    $value = getenv($key);

    if ($value === false) {
        return $default;
    }

    // Convert string boolean values
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'empty':
        case '(empty)':
            return '';
        case 'null':
        case '(null)':
            return null;
    }

    return $value;
}
