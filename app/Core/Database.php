<?php

final class Database
{
    private static ?PDO $pdo = null;
    private static array $dotenv = [];

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        self::$dotenv = self::loadDotenv(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env');
        $databaseUrl = (string) self::env('DATABASE_URL', '');

        if ($databaseUrl === '') {
            throw new RuntimeException('Falta DATABASE_URL en el archivo .env.');
        }

        $parts = parse_url($databaseUrl);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['path'])) {
            throw new RuntimeException('DATABASE_URL no tiene un formato válido.');
        }

        $host = (string) ($parts['host'] ?? '');
        $port = (int) ($parts['port'] ?? 5432);
        $db = ltrim((string) ($parts['path'] ?? ''), '/');
        $user = urldecode((string) ($parts['user'] ?? ''));
        $pass = urldecode((string) ($parts['pass'] ?? ''));

        $dsn = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $db . ';sslmode=require';

        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }

    private static function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        if (array_key_exists($key, self::$dotenv) && self::$dotenv[$key] !== '') {
            return self::$dotenv[$key];
        }

        return $default;
    }

    private static function loadDotenv(string $filePath): array
    {
        if (!is_file($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $vars = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $k = trim(substr($line, 0, $pos));
            $v = trim(substr($line, $pos + 1));

            if ($v !== '' && (($v[0] === '"' && str_ends_with($v, '"')) || ($v[0] === "'" && str_ends_with($v, "'")))) {
                $v = substr($v, 1, -1);
            }

            if ($k !== '') {
                $vars[$k] = $v;
            }
        }

        return $vars;
    }
}
