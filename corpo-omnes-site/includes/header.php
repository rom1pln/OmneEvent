<?php
/*
 * includes/header.php - En-tête commun + navigation
 *
 * Variables attendues (définir AVANT require_once) :
 *   $title  string  Titre de la page
 *   $pageStyles  string[]  Optionnel : feuilles supplémentaires après style.css (ex. ['css/guide-page.css']).
 *                   'sport' | 'partenaires' | 'boutique' | 'todo' | 'demande-partenariat' |
 *                   'register' | 'mon-profil' | 'mes-commandes' | …
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// Charge auth.php si pas encore fait (pages publiques ne l'incluent pas toujours)
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/auth.php';
}
require_once __DIR__ . '/i18n.php';

// Rafraîchit les permissions de l'utilisateur connecté à chaque page si $pdo
// est disponible - propage immédiatement les changements de rôles/structures
// sans nécessiter une déconnexion / reconnexion.
if (isset($pdo) && $pdo instanceof PDO && function_exists('refreshUserSession')) {
    refreshUserSession($pdo);
}

$inAdmin = strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false;
$base    = $inAdmin ? '../' : '';

$p = $page ?? '';
$partenairesDropdown = in_array($p, ['partenaires', 'demande-partenariat']);
$corpoDropdown       = in_array($p, ['apropos', 'guide-site'], true);
$eventsDropdown      = in_array($p, ['evenements', 'actualites'], true);
$assoDropdown        = in_array($p, ['associations', 'boutique', 'proposer-asso'], true);
$htmlLang = corpo_current_lang() === 'en' ? 'en' : 'fr';
$hereLang = $_SERVER['REQUEST_URI'] ?? '/';
$langFrUrl = $base . 'set-lang.php?lang=fr&redirect=' . urlencode($hereLang);
$langEnUrl = $base . 'set-lang.php?lang=en&redirect=' . urlencode($hereLang);

function navLnk(string $href, string $label, string $current, string $key): string {
    $cls = $current === $key ? ' active' : '';
    return "<li><a href=\"$href\" class=\"nav__link$cls\">$label</a></li>";
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLang) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#190038">
  <meta name="mobile-web-app-capable" content="yes">
  <title><?= htmlspecialchars($title ?? corpo_t('index.meta_title')) ?> - <?= htmlspecialchars(corpo_t('meta.site_suffix')) ?></title>
  <?php
    // Cache-busting basé sur la date de modification : invalide le cache navigateur
    // dès qu'on touche au CSS (utile en dev quand on itère).
    $cssPath = __DIR__ . '/../css/style.css';
    $cssVer  = file_exists($cssPath) ? filemtime($cssPath) : '1';
  ?>
  <link rel="stylesheet" href="<?= $base ?>css/style.css?v=<?= $cssVer ?>">
  <?php
    $mobileCssPath = __DIR__ . '/../css/mobile-first.css';
    $mobileCssVer  = file_exists($mobileCssPath) ? filemtime($mobileCssPath) : '1';
  ?>
  <link rel="stylesheet" href="<?= $base ?>css/mobile-first.css?v=<?= $mobileCssVer ?>">
  <?php
  if (!empty($pageStyles) && is_array($pageStyles)) {
      foreach ($pageStyles as $rel) {
          $cssRel = ltrim((string)$rel, '/');
          $full   = __DIR__ . '/../' . $cssRel;
          $ver    = is_file($full) ? (string)filemtime($full) : '1';
          echo '<link rel="stylesheet" href="' . htmlspecialchars($base . $cssRel) . '?v=' . htmlspecialchars($ver) . '">' . "\n  ";
      }
  }
  ?>
</head>
<body>

  <nav class="nav" aria-label="<?= htmlspecialchars(corpo_t('nav.main_aria')) ?>">
    <div class="nav__inner container">

      <!-- Logo -->
      <a href="<?= $base ?>index.php" class="nav__brand" aria-label="<?= htmlspecialchars(corpo_t('nav.brand_home')) ?>">
        <img src="<?= $base ?>images/logo-corpo-omnes.png" alt="" class="nav__logo" aria-hidden="true">
        <div>
          <span class="nav__brand-name">Corpo Omnes</span>
          <span class="nav__brand-sub">Lyon</span>
        </div>
      </a>

      <!-- Burger mobile -->
      <button class="nav__toggle" id="nav-toggle" aria-expanded="false" aria-controls="nav-menu" aria-label="<?= htmlspecialchars(corpo_t('nav.open_menu')) ?>">
        <span></span><span></span><span></span>
      </button>

      <!-- Liens de navigation -->
      <ul class="nav__menu" id="nav-menu" role="list">

        <?= navLnk($base . 'index.php',        corpo_t('nav.home'),       $p, 'index') ?>
        <li class="nav__item--dropdown<?= $corpoDropdown ? ' nav__item--dropdown--active' : '' ?>">
          <button class="nav__link nav__dropdown-toggle<?= in_array($p, ['apropos', 'guide-site'], true) ? ' active' : '' ?>"
                  aria-expanded="false" aria-haspopup="true">
            <?= htmlspecialchars(corpo_t('nav.corpo')) ?> <span class="nav__arrow" aria-hidden="true">▾</span>
          </button>
          <ul class="nav__dropdown" role="menu">
            <li>
              <a href="<?= $base ?>apropos.php"
                 class="nav__dropdown-link<?= $p === 'apropos' ? ' active' : '' ?>"
                 role="menuitem"><?= htmlspecialchars(corpo_t('nav.corpo_mission')) ?></a>
            </li>
            <li>
              <a href="<?= $base ?>guide-site.php"
                 class="nav__dropdown-link nav__dropdown-link--sub<?= $p === 'guide-site' ? ' active' : '' ?>"
                 role="menuitem"><?= htmlspecialchars(corpo_t('nav.corpo_site_guide')) ?></a>
            </li>
          </ul>
        </li>
        <li class="nav__item--dropdown<?= $eventsDropdown ? ' nav__item--dropdown--active' : '' ?>">
          <button class="nav__link nav__dropdown-toggle<?= in_array($p, ['evenements', 'actualites'], true) ? ' active' : '' ?>"
                  aria-expanded="false" aria-haspopup="true">
            <?= htmlspecialchars(corpo_t('nav.events')) ?> <span class="nav__arrow" aria-hidden="true">▾</span>
          </button>
          <ul class="nav__dropdown" role="menu">
            <li>
              <a href="<?= $base ?>evenements.php"
                 class="nav__dropdown-link<?= $p === 'evenements' ? ' active' : '' ?>"
                 role="menuitem"><?= htmlspecialchars(corpo_t('nav.events_list')) ?></a>
            </li>
            <li>
              <a href="<?= $base ?>actualites.php"
                 class="nav__dropdown-link nav__dropdown-link--sub<?= $p === 'actualites' ? ' active' : '' ?>"
                 role="menuitem"><?= htmlspecialchars(corpo_t('nav.news')) ?></a>
            </li>
          </ul>
        </li>
        <li class="nav__item--dropdown<?= $assoDropdown ? ' nav__item--dropdown--active' : '' ?>">
          <button class="nav__link nav__dropdown-toggle<?= in_array($p, ['associations', 'boutique'], true) ? ' active' : '' ?>"
                  aria-expanded="false" aria-haspopup="true">
            <?= htmlspecialchars(corpo_t('nav.assos')) ?> <span class="nav__arrow" aria-hidden="true">▾</span>
          </button>
          <ul class="nav__dropdown" role="menu">
            <li>
              <a href="<?= $base ?>associations.php"
                 class="nav__dropdown-link<?= $p === 'associations' ? ' active' : '' ?>"
                 role="menuitem"><?= htmlspecialchars(corpo_t('nav.assos_directory')) ?></a>
            </li>
            <li>
              <a href="<?= $base ?>boutique.php"
                 class="nav__dropdown-link nav__dropdown-link--sub<?= $p === 'boutique' ? ' active' : '' ?>"
                 role="menuitem"><?= htmlspecialchars(corpo_t('nav.shop')) ?></a>
            </li>
            <li>
              <a href="<?= $base ?>proposer-asso.php"
                 class="nav__dropdown-link nav__dropdown-link--sub<?= $p === 'proposer-asso' ? ' active' : '' ?>"
                 role="menuitem"><?= htmlspecialchars(corpo_t('contact.link_propose_asso')) ?></a>
            </li>
          </ul>
        </li>
        <?= navLnk($base . 'sport.php',        corpo_t('nav.sport'),      $p, 'sport') ?>

        <!-- Partenaires avec sous-item "Devenir partenaire" -->
        <li class="nav__item--dropdown<?= $partenairesDropdown ? ' nav__item--dropdown--active' : '' ?>">
          <button class="nav__link nav__dropdown-toggle<?= $p === 'partenaires' ? ' active' : '' ?>"
                  aria-expanded="false" aria-haspopup="true">
            <?= htmlspecialchars(corpo_t('nav.partners')) ?> <span class="nav__arrow" aria-hidden="true">▾</span>
          </button>
          <ul class="nav__dropdown" role="menu">
            <li>
              <a href="<?= $base ?>partenaires.php"
                 class="nav__dropdown-link<?= $p === 'partenaires' ? ' active' : '' ?>"
                 role="menuitem"><?= htmlspecialchars(corpo_t('nav.partners')) ?></a>
            </li>
            <li>
              <a href="<?= $base ?>demande-partenariat.php"
                 class="nav__dropdown-link nav__dropdown-link--sub<?= $p === 'demande-partenariat' ? ' active' : '' ?>"
                 role="menuitem"><?= htmlspecialchars(corpo_t('nav.become_partner')) ?></a>
            </li>
          </ul>
        </li>

        <li class="nav__item nav__item--search">
          <button type="button" class="nav__search-btn" data-global-search-open aria-label="<?= htmlspecialchars(corpo_t('search.open')) ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
          </button>
        </li>

        <!-- Dropdown Compte -->
        <?php
          // Utilise les fonctions auth.php (chargées ci-dessus)
          $isConnected  = isLoggedIn();
          // Accès panel : admin_corpo+ OU admin d'au moins une structure OU resp. fonctionnelle (resp_*)
          $isAdminPanel = hasAdminPanelAccess();
          // Libellé du menu Compte : prénom > identifiant (jamais l’email)
          $displayName = htmlspecialchars(
              ($_SESSION['user_prenom'] ?? '') ?:
              ($_SESSION['user_login']     ?? '') ?:
              corpo_t('nav.account')
          );
          $compteActive = in_array($p, ['register', 'mon-profil', 'mes-commandes'], true) ? ' nav__item--dropdown--active' : '';
        ?>
        <li class="nav__item--dropdown nav__item--dropdown--account<?= $compteActive ?>">
          <button class="nav__link nav__login-btn nav__dropdown-toggle"
                  aria-expanded="false" aria-haspopup="true">
            <?php if ($isAdminPanel): ?>
              <span class="nav__account-icon nav__account-icon--admin" aria-hidden="true"></span><?= $displayName ?>
            <?php elseif ($isConnected): ?>
              <span class="nav__account-icon" aria-hidden="true"></span><?= $displayName ?>
            <?php else: ?>
              <?= htmlspecialchars(corpo_t('nav.account')) ?>
            <?php endif; ?>
            <span class="nav__arrow" aria-hidden="true">▾</span>
          </button>
          <ul class="nav__dropdown" role="menu">
            <?php if ($isAdminPanel): ?>
              <li><a href="<?= $base ?>admin/index.php"       class="nav__dropdown-link" role="menuitem"><?= htmlspecialchars(corpo_t('account.admin')) ?></a></li>
              <li><a href="<?= $base ?>mon-profil.php"      class="nav__dropdown-link<?= $p === 'mon-profil' ? ' active' : '' ?>" role="menuitem"><?= htmlspecialchars(corpo_t('account.profile')) ?></a></li>
              <li><a href="<?= $base ?>mes-actualites.php"    class="nav__dropdown-link" role="menuitem"><?= htmlspecialchars(corpo_t('account.news')) ?></a></li>
              <li><a href="<?= $base ?>mes-evenements.php"    class="nav__dropdown-link" role="menuitem"><?= htmlspecialchars(corpo_t('account.my_events')) ?></a></li>
              <li><a href="<?= $base ?>mes-commandes.php"     class="nav__dropdown-link<?= $p === 'mes-commandes' ? ' active' : '' ?>" role="menuitem"><?= htmlspecialchars(corpo_t('account.my_orders')) ?></a></li>
              <li><a href="<?= $base ?>mes-assos.php"         class="nav__dropdown-link" role="menuitem"><?= htmlspecialchars(corpo_t('account.my_assos')) ?></a></li>
              <li><a href="<?= $base ?>mes-sports.php"        class="nav__dropdown-link" role="menuitem"><?= htmlspecialchars(corpo_t('account.my_sports')) ?></a></li>
              <li class="nav__dropdown-lang" role="none">
                <span class="nav__dropdown-lang-label"><?= htmlspecialchars(corpo_t('account.lang_hint')) ?></span>
                <a href="<?= htmlspecialchars($langFrUrl) ?>" class="nav__dropdown-link nav__dropdown-link--inline<?= corpo_current_lang() === 'fr' ? ' active' : '' ?>" role="menuitem" hreflang="fr">FR</a>
                <span class="nav__dropdown-lang-sep" aria-hidden="true">·</span>
                <a href="<?= htmlspecialchars($langEnUrl) ?>" class="nav__dropdown-link nav__dropdown-link--inline<?= corpo_current_lang() === 'en' ? ' active' : '' ?>" role="menuitem" hreflang="en">EN</a>
              </li>
              <li><a href="<?= $base ?>admin/logout.php"      class="nav__dropdown-link nav__dropdown-link--sub" role="menuitem"><?= htmlspecialchars(corpo_t('account.logout')) ?></a></li>
            <?php elseif ($isConnected): ?>
              <li><a href="<?= $base ?>mon-profil.php"      class="nav__dropdown-link<?= $p === 'mon-profil' ? ' active' : '' ?>" role="menuitem"><?= htmlspecialchars(corpo_t('account.profile')) ?></a></li>
              <li><a href="<?= $base ?>mes-actualites.php"    class="nav__dropdown-link" role="menuitem"><?= htmlspecialchars(corpo_t('account.news')) ?></a></li>
              <li><a href="<?= $base ?>mes-evenements.php"    class="nav__dropdown-link" role="menuitem"><?= htmlspecialchars(corpo_t('account.my_events')) ?></a></li>
              <li><a href="<?= $base ?>mes-commandes.php"     class="nav__dropdown-link<?= $p === 'mes-commandes' ? ' active' : '' ?>" role="menuitem"><?= htmlspecialchars(corpo_t('account.my_orders')) ?></a></li>
              <li><a href="<?= $base ?>mes-assos.php"         class="nav__dropdown-link" role="menuitem"><?= htmlspecialchars(corpo_t('account.my_assos')) ?></a></li>
              <li><a href="<?= $base ?>mes-sports.php"        class="nav__dropdown-link" role="menuitem"><?= htmlspecialchars(corpo_t('account.my_sports')) ?></a></li>
              <li class="nav__dropdown-lang" role="none">
                <span class="nav__dropdown-lang-label"><?= htmlspecialchars(corpo_t('account.lang_hint')) ?></span>
                <a href="<?= htmlspecialchars($langFrUrl) ?>" class="nav__dropdown-link nav__dropdown-link--inline<?= corpo_current_lang() === 'fr' ? ' active' : '' ?>" role="menuitem" hreflang="fr">FR</a>
                <span class="nav__dropdown-lang-sep" aria-hidden="true">·</span>
                <a href="<?= htmlspecialchars($langEnUrl) ?>" class="nav__dropdown-link nav__dropdown-link--inline<?= corpo_current_lang() === 'en' ? ' active' : '' ?>" role="menuitem" hreflang="en">EN</a>
              </li>
              <li><a href="<?= $base ?>admin/logout.php"      class="nav__dropdown-link nav__dropdown-link--sub" role="menuitem"><?= htmlspecialchars(corpo_t('account.logout')) ?></a></li>
            <?php else: ?>
              <li><a href="<?= $base ?>admin/login.php"       class="nav__dropdown-link" role="menuitem"><?= htmlspecialchars(corpo_t('account.login')) ?></a></li>
              <li><a href="<?= $base ?>register.php"          class="nav__dropdown-link nav__dropdown-link--sub<?= $p === 'register' ? ' active' : '' ?>" role="menuitem"><?= htmlspecialchars(corpo_t('account.register')) ?></a></li>
              <li class="nav__dropdown-lang" role="none">
                <span class="nav__dropdown-lang-label"><?= htmlspecialchars(corpo_t('account.lang_hint')) ?></span>
                <a href="<?= htmlspecialchars($langFrUrl) ?>" class="nav__dropdown-link nav__dropdown-link--inline<?= corpo_current_lang() === 'fr' ? ' active' : '' ?>" role="menuitem" hreflang="fr">FR</a>
                <span class="nav__dropdown-lang-sep" aria-hidden="true">·</span>
                <a href="<?= htmlspecialchars($langEnUrl) ?>" class="nav__dropdown-link nav__dropdown-link--inline<?= corpo_current_lang() === 'en' ? ' active' : '' ?>" role="menuitem" hreflang="en">EN</a>
              </li>
            <?php endif; ?>
          </ul>
        </li>

      </ul>
    </div>
  </nav>

  <div id="global-search" class="global-search" aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?= htmlspecialchars(corpo_t('search.title')) ?>"
       data-api-base="<?= htmlspecialchars($base) ?>api/search.php"
       data-msg-hint="<?= htmlspecialchars(corpo_t('search.hint')) ?>"
       data-msg-loading="<?= htmlspecialchars(corpo_t('search.loading')) ?>"
       data-msg-empty="<?= htmlspecialchars(corpo_t('search.empty')) ?>"
       data-msg-err="<?= htmlspecialchars(corpo_t('search.error')) ?>">
    <div class="global-search__backdrop" data-global-search-close></div>
    <div class="global-search__panel">
      <div class="global-search__head">
        <label class="global-search__label" for="global-search-input"><?= htmlspecialchars(corpo_t('search.title')) ?></label>
        <button type="button" class="global-search__close" data-global-search-close aria-label="<?= htmlspecialchars(corpo_t('search.close')) ?>">×</button>
      </div>
      <input type="search" id="global-search-input" class="global-search__input admin-input" placeholder="<?= htmlspecialchars(corpo_t('search.placeholder')) ?>" autocomplete="off" spellcheck="false">
      <p class="global-search__kbd-hint"><?= htmlspecialchars(corpo_t('search.shortcut')) ?></p>
      <div id="global-search-results" class="global-search__results"></div>
    </div>
  </div>
