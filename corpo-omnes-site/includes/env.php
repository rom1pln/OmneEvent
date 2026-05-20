<?php
// charge le .env et expose corpo_env(key, default)

if (!function_exists('corpo_env_load')) {
    function corpo_env_load(?string $path = null): void {
        static $loaded = false;
        if ($loaded) return;
        $loaded = true;

        $candidates = [
            $path,
            __DIR__ . '/.env',          // includes/.env (emplacement plus sûr - non servi par Apache si .htaccess)
            __DIR__ . '/../.env',       // corpo-omnes-site/.env (racine du site)
            __DIR__ . '/../../.env',    // un cran au-dessus
        ];
        foreach ($candidates as $file) {
            if (!$file || !is_file($file) || !is_readable($file)) continue;
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') continue;
                if (!str_contains($line, '=')) continue;
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                if ($k === '') continue;
                // Strip wrapping quotes
                if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[strlen($v)-1] === $v[0]) {
                    $v = substr($v, 1, -1);
                }
                if (getenv($k) === false) {
                    putenv("$k=$v");
                    $_ENV[$k] = $v;
                }
            }
            return; // first match wins
        }
    }
}

if (!function_exists('corpo_env')) {
    function corpo_env(string $key, $default = null) {
        corpo_env_load();
        $v = getenv($key);
        if ($v === false || $v === '') {
            return $_ENV[$key] ?? $default;
        }
        return $v;
    }
}

corpo_env_load();
