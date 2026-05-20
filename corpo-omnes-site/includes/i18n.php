<?php
declare(strict_types=1);
// gestion de la langue (cookie FR/EN)

const CORPO_LANG_COOKIE = 'corpo_lang';
const CORPO_LANG_ALLOWED = ['fr', 'en'];

function corpo_current_lang(): string {
    $l = $_COOKIE[CORPO_LANG_COOKIE] ?? '';
    return in_array($l, CORPO_LANG_ALLOWED, true) ? $l : 'fr';
}

function corpo_set_lang_cookie(string $lang): void {
    if (!in_array($lang, CORPO_LANG_ALLOWED, true)) {
        return;
    }
    $secure = function_exists('is_https_request') ? is_https_request() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(CORPO_LANG_COOKIE, $lang, [
        'expires'  => time() + 365 * 24 * 3600,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[CORPO_LANG_COOKIE] = $lang;
}

function corpo_clear_lang_cookie(): void {
    $secure = function_exists('is_https_request') ? is_https_request() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(CORPO_LANG_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[CORPO_LANG_COOKIE]);
}

// prénom ou username pour les messages
function corpo_user_display_name(): string {
    $p = trim((string)($_SESSION['user_prenom'] ?? ''));
    if ($p !== '') {
        return $p;
    }
    $u = trim((string)($_SESSION['user_login'] ?? ''));
    return $u !== '' ? $u : '';
}

// strings de la page login selon la langue
function corpo_login_strings(string $lang): array {
    if ($lang === 'en') {
        return [
            'html_title'      => 'Sign in - Corpo Omnes Lyon',
            'brand_sub'       => 'Administrator panel',
            'title'           => 'Sign in',
            'subtitle'        => 'Reserved for active accounts.',
            'welcome'         => 'Welcome to the Corpo Omnes Lyon sign-in page.',
            'lang_label'      => 'Language',
            'lang_fr'         => 'French',
            'lang_en'         => 'English',
            'email_label'     => 'Email or username',
            'email_ph'        => 'firstname.lastname@school.fr or username',
            'password_label'  => 'Password',
            'submit'          => 'Sign in →',
            'back_site'       => '← Back to website',
            'csrf_invalid'    => 'Invalid security token. Please try again.',
            'empty_fields'    => 'Please fill in both fields.',
            'bad_creds'       => 'Incorrect credentials.',
            'inactive'        => 'Your account is pending validation. Contact an administrator.',
            'attempts_line'   => 'Consecutive failed attempts: %d',
            'blocked_wait'    => 'Three incorrect attempts in a row. The server made you wait 5 seconds. The attempt counter has been reset.',
        ];
    }
    return [
        'html_title'      => 'Connexion - Corpo Omnes Lyon',
        'brand_sub'       => 'Panel administrateur',
        'title'           => 'Connexion',
        'subtitle'        => 'Accès réservé aux comptes actifs.',
        'welcome'         => 'Bienvenue sur la page de connexion Corpo Omnes Lyon.',
        'lang_label'      => 'Langue',
        'lang_fr'         => 'Français',
        'lang_en'         => 'English',
        'email_label'     => 'Email ou identifiant',
        'email_ph'        => 'prenom.nom@ecole.fr ou identifiant',
        'password_label'  => 'Mot de passe',
        'submit'          => 'Se connecter →',
        'back_site'       => '← Retour au site',
        'csrf_invalid'    => 'Jeton de sécurité invalide. Réessayez.',
        'empty_fields'    => 'Veuillez remplir les deux champs.',
        'bad_creds'       => 'Identifiants incorrects.',
        'inactive'        => 'Ton compte est en attente de validation. Contacte un administrateur.',
        'attempts_line'   => 'Tentatives incorrectes consécutives : %d',
        'blocked_wait'    => 'Trois identifications incorrectes de suite. Le serveur vous a fait patienter 5 secondes. Le compteur de tentatives a été réinitialisé.',
    ];
}

// strings du coin user (bonjour / déco)
function corpo_corner_strings(string $lang): array {
    if ($lang === 'en') {
        return [
            'hello'  => 'Hello, %s',
            'logout' => 'Sign out',
        ];
    }
    return [
        'hello'  => 'Bonjour, %s',
        'logout' => 'Déconnexion',
    ];
}

// dictionnaire du site selon la langue
function corpo_site_dict(string $lang): array {
    static $cache = ['fr' => null, 'en' => null];
    if ($cache[$lang] === null) {
        $base = __DIR__ . '/lang/site-' . ($lang === 'en' ? 'en' : 'fr') . '.php';
        $main = file_exists($base) ? require $base : [];
        $extraPath = __DIR__ . '/lang/site-' . ($lang === 'en' ? 'en' : 'fr') . '-extra.php';
        $extra = file_exists($extraPath) ? require $extraPath : [];
        $cache[$lang] = array_merge($main, $extra);
    }
    return $cache[$lang];
}

function corpo_t(string $key): string {
    $lang = corpo_current_lang();
    $dict = corpo_site_dict($lang);
    if (isset($dict[$key])) {
        return $dict[$key];
    }
    $fallback = corpo_site_dict('fr');
    return $fallback[$key] ?? $key;
}

// noms de mois 1-12 selon la langue
function corpo_month_names_full(): array {
    if (corpo_current_lang() === 'en') {
        return [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
            7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];
    }
    return [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];
}

/** Abréviations 3 lettres pour calendrier / cartes */
function corpo_month_abbr(int $month): string {
    $m = max(1, min(12, $month));
    if (corpo_current_lang() === 'en') {
        $a = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];
        return $a[$m];
    }
    $a = [1=>'janv.',2=>'févr.',3=>'mars',4=>'avr.',5=>'mai',6=>'juin',7=>'juil.',8=>'août',9=>'sept.',10=>'oct.',11=>'nov.',12=>'déc.'];
    return $a[$m];
}

// noms de jours complets (ISO 1=lundi, 7=dimanche)
function corpo_weekday_names_full(): array
{
    if (corpo_current_lang() === 'en') {
        return [
            1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
            5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
        ];
    }
    return [
        1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi',
        5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche',
    ];
}

// date longue localisée ex. "lundi 15 janvier 2026"
function corpo_format_date_long(string $dateYmd, bool $ucfirst = true): string
{
    try {
        $dt = new DateTime($dateYmd);
    } catch (Throwable $e) {
        return $dateYmd;
    }
    $wd = (int)$dt->format('N');
    $day = (int)$dt->format('j');
    $months = corpo_month_names_full();
    $month = $months[(int)$dt->format('n')] ?? $dt->format('F');
    $year = $dt->format('Y');
    $weekdays = corpo_weekday_names_full();

    if (corpo_current_lang() === 'en') {
        $s = ($weekdays[$wd] ?? '') . ', ' . $month . ' ' . $day . ', ' . $year;
    } else {
        $s = ($weekdays[$wd] ?? '') . ' ' . $day . ' ' . mb_strtolower($month, 'UTF-8') . ' ' . $year;
    }
    if (!$ucfirst || $s === '') {
        return $s;
    }
    return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
}

// labels courts pour le calendrier (lun-dim)
function corpo_weekday_short_labels(): array {
    if (corpo_current_lang() === 'en') {
        return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    }
    return ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
}
