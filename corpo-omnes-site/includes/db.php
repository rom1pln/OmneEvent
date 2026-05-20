<?php
// Connexion PDO - adapter les constantes selon la config XAMPP

define('DB_HOST',    'localhost');
define('DB_NAME',    'corpoomneshtmlprojet');
define('DB_USER',    'root');
define('DB_PASS',    '');           // mot de passe XAMPP (vide par défaut)
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // important pour les types corrects en retour
    ]);
    corpo_pdo_sync_timezone($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    if (defined('CORPO_API_PLAIN') && CORPO_API_PLAIN) {
        header('Content-Type: text/plain; charset=utf-8');
        exit('Database connection failed.');
    }
    exit('<p style="font-family:sans-serif;color:red;padding:2rem">
        Connexion à la base de données impossible.<br>
        Vérifiez que MySQL est démarré dans XAMPP et que <code>database.sql</code> a bien été importé.<br>
        <small>' . htmlspecialchars($e->getMessage()) . '</small>
    </p>');
}

// synchronise le fuseau horaire MySQL sur PHP
// sans ça les tokens de reset expirent à tort selon la config serveur
function corpo_pdo_sync_timezone(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (is_file(__DIR__ . '/env.php')) {
        require_once __DIR__ . '/env.php';
        corpo_env_load();
        $tzName = function_exists('corpo_env') ? (string)corpo_env('APP_TIMEZONE', 'Europe/Paris') : 'Europe/Paris';
        if ($tzName !== '' && @timezone_open($tzName)) {
            date_default_timezone_set($tzName);
        }
    } elseif (!ini_get('date.timezone')) {
        date_default_timezone_set('Europe/Paris');
    }

    try {
        $offset = (new DateTimeImmutable('now'))->format('P');
        $pdo->exec('SET time_zone = ' . $pdo->quote($offset));
    } catch (Throwable $e) {
        // pas grave si ça plante, MySQL reste cohérent en interne
    }
}

// extrait un token hex depuis une URL (gère les espaces et encodages URL)
function corpo_normalize_hex_token(string $raw, int $length = 64): string
{
    $raw = trim(rawurldecode($raw));
    $raw = preg_replace('/\s+/', '', $raw) ?? $raw;
    $pattern = '/([a-f0-9]{' . max(8, $length) . '})/i';
    if (preg_match($pattern, $raw, $m)) {
        return strtolower(substr($m[1], 0, $length));
    }
    return '';
}

function corpo_password_resets_table_ready(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $pdo->query('SELECT 1 FROM password_resets LIMIT 0');
        $cache = true;
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function corpo_email_verifications_table_ready(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $pdo->query('SELECT 1 FROM email_verifications LIMIT 0');
        $cache = true;
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

// vérifie si une date est dépassée, via MySQL (plus fiable que comparer côté PHP)
function corpo_db_datetime_is_past(PDO $pdo, string $table, int $id, string $column = 'expires_at'): bool
{
    $allowed = ['password_resets', 'email_verifications'];
    if (!in_array($table, $allowed, true) || $id <= 0) {
        return true;
    }
    try {
        $st = $pdo->prepare("SELECT IF({$column} <= NOW(), 1, 0) FROM {$table} WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $v = $st->fetchColumn();
        if ($v === false) {
            return true;
        }
        return (bool)(int)$v;
    } catch (Throwable $e) {
        return true;
    }
}

// charge la demande de reset correspondant au token du lien mail
function corpo_password_reset_lookup(PDO $pdo, string $tokenHash): ?array
{
    if ($tokenHash === '' || strlen($tokenHash) !== 64) {
        return null;
    }
    $st = $pdo->prepare(
        "SELECT pr.id AS reset_id, pr.user_id, pr.expires_at, pr.used_at,
                u.email, u.prenom, u.nom,
                (pr.expires_at <= NOW()) AS is_expired
         FROM password_resets pr
         INNER JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ?
         LIMIT 1"
    );
    $st->execute([$tokenHash]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// idem pour la vérification d'email
function corpo_email_verification_lookup(PDO $pdo, string $tokenHash): ?array
{
    if ($tokenHash === '' || strlen($tokenHash) !== 64) {
        return null;
    }
    $st = $pdo->prepare(
        "SELECT ev.id AS verification_id, ev.user_id, ev.expires_at, ev.used_at,
                u.statut, u.email_verified_at, u.email, u.prenom,
                (ev.expires_at <= NOW()) AS is_expired
         FROM email_verifications ev
         INNER JOIN users u ON u.id = ev.user_id
         WHERE ev.token_hash = ?
         LIMIT 1"
    );
    $st->execute([$tokenHash]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// vérifie si la colonne visibilite existe (migration pas encore jouée sur certains postes)
function corpo_actu_has_visibilite_column(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $st = $pdo->query(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'actualites' AND COLUMN_NAME = 'visibilite'
             LIMIT 1"
        );
        $cache = $st !== false && (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

// ajoute le point à l'alias si nécessaire ("a" → "a.")
function corpo_actu_sql_prefix(string $alias): string
{
    if ($alias === '') {
        return '';
    }
    $len = strlen($alias);
    if ($len > 0 && $alias[$len - 1] === '.') {
        return $alias;
    }
    return $alias . '.';
}

// condition SQL pour les actus publiques
function corpo_actu_sql_public_only(PDO $pdo, string $tableAlias = ''): string
{
    if (!corpo_actu_has_visibilite_column($pdo)) {
        return '1=1';
    }
    $c = corpo_actu_sql_prefix($tableAlias) . 'visibilite';
    return "IFNULL({$c},'public')='public'";
}

// actus publiques ou réservées aux membres (pour les utilisateurs connectés)
function corpo_actu_sql_public_or_members(PDO $pdo, string $tableAlias): string
{
    if (!corpo_actu_has_visibilite_column($pdo)) {
        return '1=1';
    }
    $c = corpo_actu_sql_prefix($tableAlias) . 'visibilite';
    return "(IFNULL({$c},'public')='public' OR IFNULL({$c},'public')='membres')";
}
