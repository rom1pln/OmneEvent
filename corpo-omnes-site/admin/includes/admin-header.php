<?php

require_once __DIR__ . '/../../includes/auth.php';

if (isset($pdo) && $pdo instanceof PDO) {
    refreshUserSession($pdo);
}

requireBureau();

if (isset($pdo) && $pdo instanceof PDO && isset($adminPage)) {
    requireAdminPanelDelegationRoute($pdo, (string)$adminPage);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#190038">
  <title><?= htmlspecialchars($adminTitle ?? 'Admin') ?> - Admin Corpo Omnes Lyon</title>
  <?php

    $admCssPath = __DIR__ . '/../../css/style.css';
    $admCssVer  = file_exists($admCssPath) ? filemtime($admCssPath) : '1';
    $admMobPath = __DIR__ . '/../../css/mobile-first.css';
    $admMobVer  = file_exists($admMobPath) ? filemtime($admMobPath) : '1';
  ?>
  <link rel="stylesheet" href="../css/style.css?v=<?= $admCssVer ?>">
  <link rel="stylesheet" href="../css/mobile-first.css?v=<?= $admMobVer ?>">
  <style>

    body { display: flex; min-height: 100vh; }

    .admin-sidebar {
      width: 230px; flex-shrink: 0;
      background: var(--surface);
      border-right: 1px solid var(--border);
      display: flex; flex-direction: column;
      padding: var(--s6) 0;

      min-height: 100vh;
    }
    .admin-sidebar__brand {
      display: flex; align-items: center; gap: var(--s3);
      padding: 0 var(--s5) var(--s6);
      border-bottom: 1px solid var(--border);
      margin-bottom: var(--s5);
    }
    .admin-sidebar__brand img { height: 28px; }
    .admin-sidebar__brand-label { font-size: .75rem; font-weight: 700; line-height: 1.3; }
    .admin-sidebar__brand-sub { font-size: .65rem; color: var(--text-muted); }

    .admin-nav { list-style: none; padding: 0 var(--s3); flex: 1; }
    .admin-nav a {
      display: flex; align-items: center; gap: var(--s3);
      padding: .55rem var(--s4);
      border-radius: var(--r-md);
      font-size: .8rem; font-weight: 600;
      color: var(--blue-light);
      transition: background var(--ease), color var(--ease);
    }
    .admin-nav a:hover { background: rgba(255,255,255,.06); color: #fff; }
    .admin-nav a.active { background: var(--purple); color: #fff; }
    .admin-nav__sep {
      font-size: .6rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .1em; color: var(--text-muted);
      padding: var(--s4) var(--s4) var(--s2);
      margin-top: var(--s3);
    }
    .admin-nav__sep:first-child { margin-top: 0; }
    .admin-nav__group {
      font-size: .58rem; font-weight: 600; text-transform: uppercase;
      letter-spacing: .08em; color: rgba(159, 158, 183, .75);
      padding: var(--s2) var(--s4) 0;
      margin-top: var(--s1);
    }
    .admin-nav__group:first-child { margin-top: 0; }
    .admin-nav__badge {
      margin-left: auto;
      background: var(--purple);
      color: #fff;
      border-radius: 999px;
      padding: 1px 7px;
      font-size: .65rem;
      font-weight: 700;
      line-height: 1.35;
    }

    .admin-sidebar__footer {
      padding: var(--s5);
      border-top: 1px solid var(--border);
      font-size: .72rem; color: var(--text-muted);
    }
    .admin-sidebar__footer a { color: var(--blue-light); }
    .admin-sidebar__footer a:hover { color: #fff; }

    .admin-main { flex: 1; padding: var(--s8); max-width: calc(100vw - 230px); min-width: 0; }

    .admin-page-title { font-size: 1.6rem; font-weight: 700; margin-bottom: var(--s6); }
    .admin-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--r-xl);
      padding: var(--s6);
      margin-bottom: var(--s6);
    }
    .admin-card h2 { font-size: 1rem; font-weight: 700; margin-bottom: var(--s4); }

    .flash {
      padding: var(--s3) var(--s5);
      border-radius: var(--r-md);
      margin-bottom: var(--s5);
      font-size: .85rem;
      line-height: 1.45;
      display: flex; align-items: flex-start; gap: var(--s3);
      border: 1px solid transparent;
    }
    .flash strong { font-weight: 700; }
    .flash--ok   { background: rgba(34,197,94,.12);  border-color: rgba(34,197,94,.4);  color: #86efac; }
    .flash--err  { background: rgba(239,68,68,.12);  border-color: rgba(239,68,68,.4);  color: #fca5a5; }
    .flash--warn { background: rgba(251,191,36,.12); border-color: rgba(251,191,36,.4); color: #fcd34d; }
    .flash--info { background: rgba(99,102,241,.12); border-color: rgba(99,102,241,.4); color: #a5b4fc; }
    .flash--scope{ background: rgba(139,47,201,.10); border-color: rgba(139,47,201,.35); color: var(--purple-light); }

    .admin-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
    .admin-table th, .admin-table td {
      padding: var(--s3) var(--s4);
      border-bottom: 1px solid var(--border);
      text-align: left;
    }
    .admin-table th { color: var(--blue-light); font-weight: 700; font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; }
    .admin-table tr:hover td { background: rgba(255,255,255,.02); }
    .admin-table .actions { display: flex; gap: var(--s2); flex-wrap: wrap; }
    .btn--danger { background: rgba(239,68,68,.2); color: #fca5a5; }
    .btn--danger:hover { background: rgba(239,68,68,.4); }
    .btn--success { background: rgba(34,197,94,.2); color: #86efac; }
    .btn--success:hover { background: rgba(34,197,94,.4); }
    .btn--warn { background: rgba(251,191,36,.2); color: #fcd34d; }
    .btn--warn:hover { background: rgba(251,191,36,.4); }
    .btn--sm { padding: .25rem .6rem; font-size: .75rem; }

    .admin-form .form-row { display: flex; gap: var(--s4); flex-wrap: wrap; margin-bottom: var(--s4); }
    .admin-form .form-col { flex: 1; min-width: 200px; }
    .admin-form label { display: block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--blue-light); margin-bottom: var(--s2); }
    .admin-form input[type=text],
    .admin-form input[type=email],
    .admin-form input[type=date],
    .admin-form input[type=url],
    .admin-form input[type=number],
    .admin-form input[type=password],
    .admin-form select,
    .admin-form textarea {
      width: 100%; background: rgba(255,255,255,.04); border: 1px solid var(--border);
      border-radius: var(--r-md); padding: .55rem var(--s4); color: var(--text);
      outline: none; transition: border-color var(--ease); box-sizing: border-box;
    }
    .admin-form input:focus,
    .admin-form select:focus,
    .admin-form textarea:focus { border-color: var(--purple); }
    .admin-form textarea { resize: vertical; min-height: 80px; }
    .admin-form select option,
    .admin-form select optgroup { background: #190038; }

    .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: .72rem; font-weight: 600; }
    .badge--pending { background: rgba(251,191,36,.2); color: #fcd34d; }
    .badge--ok      { background: rgba(34,197,94,.2); color: #86efac; }
    .badge--ko      { background: rgba(239,68,68,.2); color: #fca5a5; }

    .admin-back-btn {
      display: flex; align-items: center; gap: var(--s2);
      margin: 0 var(--s3) var(--s3);
      padding: .45rem var(--s4);
      border-radius: var(--r-md);
      font-size: .78rem; font-weight: 600;
      color: var(--text-muted);
      border: 1px solid var(--border);
      transition: color var(--ease), border-color var(--ease), background var(--ease);
    }
    .admin-back-btn:hover { color: #fff; }

    .admin-burger {
      display: none;
      position: fixed;
      top: calc(12px + env(safe-area-inset-top, 0px));
      left: calc(12px + env(safe-area-inset-left, 0px));
      z-index: 60;
      width: 44px; height: 44px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      cursor: pointer;
      align-items: center;
      justify-content: center;
      gap: 4px;
      flex-direction: column;
      padding: 0;
      box-shadow: 0 4px 12px rgba(0,0,0,.3);
    }
    .admin-burger span {
      display: block;
      width: 22px; height: 2px;
      background: #fff;
      border-radius: 2px;
      transition: transform .25s, opacity .15s;
    }
    body.admin-sidebar-open .admin-burger span:nth-child(1) { transform: translateY(6px) rotate(45deg); }
    body.admin-sidebar-open .admin-burger span:nth-child(2) { opacity: 0; }
    body.admin-sidebar-open .admin-burger span:nth-child(3) { transform: translateY(-6px) rotate(-45deg); }

    .admin-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.55);
      backdrop-filter: blur(2px);
      z-index: 50;
      opacity: 0;
      transition: opacity .25s ease;
    }
    body.admin-sidebar-open .admin-overlay {
      display: block;
      opacity: 1;
    }

    @media (max-width: 980px) {
      body { display: block; }
      .admin-burger { display: flex; }
      .admin-sidebar {
        position: fixed;
        top: 0; left: 0;
        height: 100vh;
        height: 100dvh;
        max-height: 100dvh;
        z-index: 55;
        transform: translateX(-100%);
        transition: transform .28s ease;
        box-shadow: 4px 0 24px rgba(0,0,0,.4);
        width: min(280px, 86vw);

        background: var(--surface);
        background-image: linear-gradient(180deg, #220050 0%, #190038 50%, #0D001F 100%);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        padding-top: calc(var(--s6) + env(safe-area-inset-top, 0px));
        padding-bottom: env(safe-area-inset-bottom, 0px);
        box-sizing: border-box;
      }
      .admin-sidebar__brand,
      .admin-back-btn,
      .admin-sidebar__footer {
        flex-shrink: 0;
      }
      .admin-nav {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        padding-bottom: var(--s4);
      }
      body.admin-sidebar-open .admin-sidebar { transform: translateX(0); }
      .admin-main {
        max-width: 100vw;
        padding: 72px var(--s4) var(--s5);
      }
      .admin-page-title { font-size: 1.3rem; margin-bottom: var(--s4); }
    }

    @media (max-width: 540px) {
      .admin-main { padding: 68px var(--s3) var(--s4); }
      .admin-card { padding: var(--s4); margin-bottom: var(--s4); }
      .admin-card h2 { font-size: .95rem; }
      .admin-page-title { font-size: 1.15rem; }

      .admin-form .form-row { gap: var(--s3); margin-bottom: var(--s3); }
      .admin-form .form-col { min-width: 100%; flex-basis: 100%; }

      .flash { font-size: .8rem; padding: var(--s2) var(--s3); }

      .admin-table,
      .admin-table thead,
      .admin-table tbody,
      .admin-table tr,
      .admin-table td,
      .admin-table th { display: block; }

      .admin-table thead { display: none; }

      .admin-table tr {
        background: rgba(255,255,255,.025);
        border: 1px solid var(--border);
        border-radius: var(--r-md);
        padding: var(--s3);
        margin-bottom: var(--s3);
        display: flex;
        flex-direction: column;
        gap: 6px;
      }
      .admin-table tr:hover td { background: transparent; }

      .admin-table td {
        border: none !important;
        padding: 0 !important;
        font-size: .82rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--s2);
        flex-wrap: wrap;
      }
      .admin-table td::before {
        content: attr(data-label);
        font-size: .65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--blue-light);
        flex-shrink: 0;
      }
      .admin-table td:empty,
      .admin-table td[data-label=""]::before { display: none; }

      .admin-table .actions {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 6px;
        width: 100%;
        margin-left: auto;
        justify-content: flex-end;
      }
      .admin-table .actions .btn { font-size: .72rem; padding: .3rem .55rem; }
    }

  </style>
</head>
<body>

    <button type="button" class="admin-burger" id="adminBurger" aria-label="Ouvrir le menu" aria-controls="adminSidebar" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>

    <div class="admin-overlay" id="adminOverlay" aria-hidden="true"></div>

    <aside class="admin-sidebar" id="adminSidebar">

        <div class="admin-sidebar__brand">
      <img src="../images/logo-corpo-omnes.png" alt="Logo">
      <div>
        <div class="admin-sidebar__brand-label">Corpo Omnes</div>
        <div class="admin-sidebar__brand-sub">Panel Admin</div>
      </div>
    </div>

        <a href="../index.php" class="admin-back-btn">← Retour au site</a>

    <?php
    $ap = $adminPage ?? '';
    $pdoNav = (isset($pdo) && $pdo instanceof PDO) ? $pdo : null;
    $navAllow = static function (string $page) use ($pdoNav): bool {
        return $pdoNav instanceof PDO ? adminPanelDelegationAllows($pdoNav, $page) : true;
    };
    $canSeeSportsNav = false;
    if ($pdoNav instanceof PDO) {
        try {
            $canSeeSportsNav = canAccessSportAdmin($pdoNav) && $navAllow('sports');
        } catch (Throwable $e) {
            $canSeeSportsNav = isAdminCorpo() && $navAllow('sports');
        }
    }
    $showContenuNav = !isAdminPanelNotesFraisOnly()
        && (!$pdoNav instanceof PDO
        || !isAdminPanelDelegationOnly()
        || ($navAllow('associations') || $navAllow('evenements') || $navAllow('boutique') || $navAllow('calendrier') || $canSeeSportsNav
            || $navAllow('partenaires') || $navAllow('actualites')));
    $showMesMembresNav = (isAdminCorpo() || hasAnyAdminRole()) && $navAllow('mes-membres');
    $showComptaNav     = (isAdminCorpo() || hasAnyAdminRole() || hasExplicitTreasuryDelegation()) && $navAllow('comptabilite');
    $showNotesFraisNav = false;
    if ($pdoNav instanceof PDO) {
        require_once __DIR__ . '/../../includes/notes-frais.php';
        $showNotesFraisNav = nf_table_ready($pdoNav)
            && nf_can_access_admin_notes_page($pdoNav, (int)($_SESSION['user_id'] ?? 0))
            && adminPanelDelegationAllows($pdoNav, 'notes-frais');
    }

    $showVieCampusNav = $showContenuNav && (
        $navAllow('evenements') || $navAllow('actualites') || $navAllow('calendrier')
        || $canSeeSportsNav || $navAllow('associations') || $navAllow('partenaires')
    );
    $showBoutiqueNav = $showContenuNav && $navAllow('boutique');

    $validationBadge = 0;
    if (isAdminCorpo() && $pdoNav instanceof PDO) {
        try {
            $validationBadge = (int) $pdoNav->query(
                "SELECT COUNT(*) FROM demandes_validation WHERE statut='en_attente'"
            )->fetchColumn();
        } catch (Throwable $e) {
            $validationBadge = 0;
        }
    }

    $mailErrBadge = 0;
    if (isSuperAdmin()) {
        try {
            $logPath = __DIR__ . '/../../logs/mail.log';
            if (is_file($logPath)) {
                $cut = strtotime('-1 day');
                $fh = @fopen($logPath, 'r');
                if ($fh) {
                    $maxBytes = 256 * 1024;
                    $size = filesize($logPath) ?: 0;
                    if ($size > $maxBytes) {
                        fseek($fh, $size - $maxBytes);
                    }
                    while (($ln = fgets($fh)) !== false) {
                        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+\[(?:[^\]]*ERR[^\]]*)\]/', $ln, $m)) {
                            $t = strtotime($m[1]);
                            if ($t !== false && $t >= $cut) {
                                $mailErrBadge++;
                            }
                        }
                    }
                    fclose($fh);
                }
            }
        } catch (Throwable $e) {
            $mailErrBadge = 0;
        }
    }

    $adminNavItem = static function (
        string $href,
        string $label,
        string $pageKey,
        int $badge = 0,
        array $alsoActive = []
    ) use ($ap): void {
        $isActive = ($ap === $pageKey) || in_array($ap, $alsoActive, true);
        $cls = $isActive ? 'active' : '';
        $badgeHtml = $badge > 0
            ? '<span class="admin-nav__badge">' . (int) $badge . '</span>'
            : '';
        $justify = $badge > 0 ? ' style="justify-content:space-between"' : '';
        echo '<li><a href="' . htmlspecialchars($href) . '" class="' . $cls . '"' . $justify . '>';
        echo '<span>' . htmlspecialchars($label) . '</span>' . $badgeHtml;
        echo '</a></li>';
    };
    ?>
    <ul class="admin-nav">

      <li class="admin-nav__sep">Accueil</li>
      <?php $adminNavItem('index.php', 'Tableau de bord', 'dashboard'); ?>

      <?php if ($showVieCampusNav): ?>
      <li class="admin-nav__sep">Vie campus</li>
      <?php if ($navAllow('evenements')): ?>
        <?php $adminNavItem('evenements.php', 'Événements', 'evenements'); ?>
      <?php endif; ?>
      <?php if ($navAllow('actualites')): ?>
        <?php $adminNavItem('actualites.php', 'Actualités', 'actualites'); ?>
      <?php endif; ?>
      <?php if ($navAllow('calendrier')): ?>
        <?php $adminNavItem('calendrier.php', 'Calendrier scolaire', 'calendrier'); ?>
      <?php endif; ?>
      <?php if ($canSeeSportsNav): ?>
        <?php $adminNavItem('sports.php', 'Sports', 'sports'); ?>
      <?php endif; ?>
      <?php if ($navAllow('associations')): ?>
        <?php $adminNavItem('associations.php', 'Associations', 'associations'); ?>
      <?php endif; ?>
      <?php if ($navAllow('partenaires')): ?>
        <?php $adminNavItem('partenaires.php', 'Partenaires', 'partenaires'); ?>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($showBoutiqueNav): ?>
      <li class="admin-nav__sep">Boutique</li>
      <?php $adminNavItem('boutique.php', 'Catalogue', 'boutique'); ?>
      <?php $adminNavItem('boutique-commandes.php', 'Commandes', 'boutique-commandes'); ?>
      <?php endif; ?>

      <?php if ($showMesMembresNav || $showComptaNav || $showNotesFraisNav): ?>
      <li class="admin-nav__sep"><?= isAdminCorpo() ? 'Structures' : 'Ma structure' ?></li>
      <?php if ($showMesMembresNav): ?>
        <?php $adminNavItem(
            'mes-membres.php',
            isAdminCorpo() ? 'Membres des structures' : 'Mes membres',
            'mes-membres'
        ); ?>
      <?php endif; ?>
      <?php if ($showNotesFraisNav): ?>
        <?php $adminNavItem('notes-frais.php', 'Notes de frais', 'notes-frais'); ?>
      <?php endif; ?>
      <?php if ($showComptaNav): ?>
        <?php $adminNavItem('comptabilite.php', 'Comptabilité', 'comptabilite'); ?>
      <?php endif; ?>
      <?php endif; ?>

      <?php if (isAdminCorpo()): ?>
      <li class="admin-nav__sep">Administration</li>
      <?php $adminNavItem('validation.php', 'Validation contenus', 'validation', $validationBadge); ?>
      <?php $adminNavItem('users.php', 'Utilisateurs', 'users'); ?>
      <?php $adminNavItem('demandes.php', 'Demandes partenariat', 'demandes'); ?>
      <?php endif; ?>

      <?php if (isSuperAdmin()): ?>
      <li class="admin-nav__sep">Technique</li>
      <?php $adminNavItem('mails.php', 'Mails', 'mails', $mailErrBadge, ['test-mail']); ?>
      <?php $adminNavItem('migrate.php', 'Migrations DB', 'migrate'); ?>
      <?php endif; ?>

    </ul>

        <div class="admin-sidebar__footer">
      <?= roleBadge(currentRole()) ?>
      <?php
        $adminFullName = trim(($_SESSION['user_prenom'] ?? '') . ' ' . ($_SESSION['user_nom'] ?? ''));
        if ($adminFullName === '') {
            $adminFullName = $_SESSION['user_login'] ?? 'Compte';
        }
      ?>
      <strong style="display:block;margin-top:.3rem"><?= htmlspecialchars($adminFullName) ?></strong>
      <a href="../mon-profil.php" style="margin-top:.35rem;display:inline-block">Mon profil (site)</a>
      <a href="logout.php" style="margin-top:.4rem;display:inline-block">Déconnexion</a>
    </div>
  </aside>

  <main class="admin-main">
    <?php if (isAdminPanelNotesFraisOnly()): ?>
      <div class="flash flash--info" style="margin-bottom:var(--s5)">
        <strong>Accès limité.</strong> Tu peux déposer et suivre tes notes de frais depuis ce panneau.
      </div>
    <?php elseif (isAdminPanelDelegationOnly()): ?>
      <div class="flash flash--info" style="margin-bottom:var(--s5)">
        <strong>Accès délégué.</strong> Tu n’affiches que les sections pour lesquelles tu as une responsabilité (événements, partenariats, actualités ou trésorerie).
      </div>
    <?php endif; ?>
