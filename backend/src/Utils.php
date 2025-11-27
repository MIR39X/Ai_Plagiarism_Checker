<?php

class Utils
{
    /** Send a JSON response and end execution. */
    public static function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_PRETTY_PRINT);
        exit;
    }

    /** Send a standardized error response. */
    public static function error(string $message, int $status = 400, array $extra = []): void
    {
        $body = array_merge(['error' => $message], $extra);
        self::jsonResponse($body, $status);
    }

    /** Ensure a directory exists and is writable. */
    public static function ensureDir(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            self::error("Failed to create directory: {$path}", 500);
        }
        if (!is_writable($path)) {
            self::error("Directory not writable: {$path}", 500);
        }
    }

    /** Read JSON from disk. */
    public static function readJson(string $path): array
    {
        if (!file_exists($path)) {
            self::error("File not found: {$path}", 404);
        }
        $contents = file_get_contents($path);
        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            self::error("Invalid JSON in {$path}", 500);
        }
        return $decoded;
    }

    /** Generate a simple download token. */
    public static function makeToken(string $prefix = 'dl'): string
    {
        return sprintf('%s-%s', $prefix, bin2hex(random_bytes(6)));
    }

    /** Load key=value pairs from a .env file into environment variables. */
    public static function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$key, $value] = $parts;
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /** Allow CORS for browser-based calls (simple permissive policy). */
    public static function allowCors(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}

