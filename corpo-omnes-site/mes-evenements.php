<?php
// événements de l'user : ses inscriptions + events de ses structures
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/billetterie.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = 'mes-evenements.php';
    header('Location: admin/login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$now    = date('Y-m-d');

// events où l'user est inscrit
$stmtInscrits = $pdo->prepare(
    "SELECT e.*, ie.statut AS insc_statut, ie.created_at AS insc_date,
            COALESCE(s.nom, a.nom, 'Corpo Omnes') AS source_nom,
            COALESCE(e.structure_type, 'corpo')    AS source_type
     FROM inscriptions_evenement ie
     JOIN evenements e ON e.id = ie.evenement_id
     LEFT JOIN sports      s ON e.structure_type = 'sport' AND e.structure_id = s.id
     LEFT JOIN associations a ON e.structure_type IN ('asso','bde','bds') AND e.structure_id = a.id
     WHERE ie.user_id = ? AND ie.statut IN ('confirme','en_attente')
     ORDER BY e.date ASC, e.heure ASC"
);
$stmtInscrits->execute([$userId]);
$evtsInscrits = $stmtInscrits->fetchAll();

// events des assos de l'user
$stmtAsso = $pdo->prepare(
    "SELECT DISTINCT e.*, 'confirme' AS insc_statut, NULL AS insc_date,
            a.nom AS source_nom, sm.structure_type AS source_type
     FROM evenements e
     JOIN structure_membres sm ON sm.user_id = ? AND sm.statut = 'actif'
                               AND e.structure_type = sm.structure_type
                               AND e.structure_id   = sm.structure_id
     JOIN associations a ON a.id = sm.structure_id
     WHERE e.statut = 'publie' AND e.date >= ?
       AND e.id NOT IN (SELECT evenement_id FROM inscriptions_evenement WHERE user_id = ?)
     ORDER BY e.date ASC LIMIT 20"
);
$stmtAsso->execute([$userId, $now, $userId]);
$evtsAsso = $stmtAsso->fetchAll();

// events des sports de l'user
$stmtSport = $pdo->prepare(
    "SELECT DISTINCT e.*, 'confirme' AS insc_statut, NULL AS insc_date,
            sp.nom AS source_nom, 'sport' AS source_type
     FROM evenements e
     JOIN inscriptions_sport ins ON ins.user_id = ? AND ins.statut = 'confirme'
                                 AND e.structure_type = 'sport'
                                 AND e.structure_id   = ins.sport_id
     JOIN sports sp ON sp.id = ins.sport_id
     WHERE e.statut = 'publie' AND e.date >= ?
       AND e.id NOT IN (SELECT evenement_id FROM inscriptions_evenement WHERE user_id = ?)
     ORDER BY e.date ASC LIMIT 10"
);
$stmtSport->execute([$userId, $now, $userId]);
$evtsSport = $stmtSport->fetchAll();

// sépare à venir vs passés
$aVenir = array_filter($evtsInscrits, fn($e) => $e['date'] >= $now);
$passes = array_filter($evtsInscrits, fn($e) => $e['date'] < $now);

$title = corpo_t('mes_evt.title');
$page  = '';
require_once __DIR__ . '/includes/header.php';

function renderEventCard(array $ev, bool $showInscBadge = true): void {
    $dt    = new DateTime($ev['date']);
    $color = '#5D0282';
    $sourceType = $ev['source_type'] ?? 'corpo';
    $sourceNom  = $ev['source_nom']  ?? 'Corpo Omnes';
    $modeInsc   = function_exists('evt_normalize_mode')
        ? evt_normalize_mode($ev['mode_inscription'] ?? 'aucune')
        : ($ev['mode_inscription'] ?? 'aucune');
    $statut     = $ev['insc_statut'] ?? null;
    ?>
    <article class="mes-evt-card">
      <div class="mes-evt-card__date">
        <strong><?= $dt->format('d') ?></strong>
        <span><?= strftime_compat('%b', $dt->getTimestamp()) ?></span>
      </div>
      <div class="mes-evt-card__body">
        <div class="mes-evt-card__meta">
          <span class="tag" style="font-size:.65rem;padding:.1rem .5rem"><?= htmlspecialchars($sourceNom) ?></span>
          <?php if ($showInscBadge && $statut === 'en_attente'): ?>
            <span class="tag" style="font-size:.65rem;padding:.1rem .5rem;color:#e67e22;border-color:rgba(230,126,34,.3)"><?= htmlspecialchars(corpo_t('mes_evt.badge_wait')) ?></span>
          <?php elseif ($showInscBadge && $statut === 'confirme'): ?>
            <span class="tag" style="font-size:.65rem;padding:.1rem .5rem;color:#2ecc71;border-color:rgba(46,204,113,.3)"><?= htmlspecialchars(corpo_t('mes_evt.badge_ok')) ?></span>
          <?php endif; ?>
        </div>
        <h3 class="mes-evt-card__title"><?= htmlspecialchars($ev['titre']) ?></h3>
        <div class="mes-evt-card__info">
          <?php if ($ev['heure']): ?><span><?= htmlspecialchars($ev['heure']) ?></span><?php endif; ?>
          <?php if ($ev['lieu']):  ?><span><?= htmlspecialchars($ev['lieu']) ?></span><?php endif; ?>
          <?php if ($ev['campus']): ?><span><?= htmlspecialchars($ev['campus']) ?></span><?php endif; ?>
        </div>
      </div>
      <div class="mes-evt-card__actions">
        <?php if ($showInscBadge && in_array($statut, ['confirme', 'en_attente'], true)): ?>
          <a href="api/event-ics.php?id=<?= (int)$ev['id'] ?>" class="btn btn--ghost btn--sm" download="evenement-<?= (int)$ev['id'] ?>.ics" title="<?= htmlspecialchars(corpo_t('mes_evt.btn_ics_title')) ?>">📅 <?= htmlspecialchars(corpo_t('mes_evt.btn_ics')) ?></a>
        <?php endif; ?>
        <?php if ($modeInsc === 'externe' && !empty($ev['lien_billetterie'])): ?>
          <a href="<?= htmlspecialchars($ev['lien_billetterie']) ?>" target="_blank" class="btn btn--primary btn--sm"><?= htmlspecialchars(corpo_t('mes_evt.btn_ticketing')) ?></a>
        <?php elseif (in_array($modeInsc, ['email','connexion','billetterie_email','billetterie_connexion'], true)): ?>
          <a href="evenement.php?id=<?= $ev['id'] ?>" class="btn btn--primary btn--sm">🎟 Mon billet</a>
        <?php endif; ?>
        <a href="evenement.php?id=<?= $ev['id'] ?>" class="btn btn--sm"><?= htmlspecialchars(corpo_t('mes_evt.btn_view')) ?></a>
      </div>
    </article>
    <?php
}

// Helper mois court sans strftime (compatible PHP 8.1+)
function strftime_compat(string $format, int $ts): string {
    if ($format === '%b') {
        return corpo_month_abbr((int)date('n', $ts));
    }
    return date($format, $ts);
}
?>

<main>
  <section class="page-hero">
    <div class="container">
      <nav class="breadcrumb"><a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a><span>›</span><span><?= htmlspecialchars(corpo_t('mes_evt.title')) ?></span></nav>
      <h1><?= htmlspecialchars(corpo_t('mes_evt.title')) ?></h1>
      <p class="page-hero__sub"><?= htmlspecialchars(corpo_t('mes_evt.sub')) ?></p>
    </div>
  </section>

  <div class="container" style="padding-top:var(--s8);padding-bottom:var(--s12);display:flex;flex-direction:column;gap:var(--s10)">

    <!-- Mes inscriptions à venir -->
    <section>
      <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:var(--s4);display:flex;align-items:center;gap:.5rem">
        <span style="width:4px;height:1.1em;background:var(--purple);border-radius:2px;display:inline-block"></span>
        <?= htmlspecialchars(corpo_t('mes_evt.insc_venir')) ?>
      </h2>
      <?php if (empty($aVenir)): ?>
        <p class="mes-empty" style="text-align:left;padding:var(--s4);background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg)">
          <?= htmlspecialchars(corpo_t('mes_evt.empty_upcoming')) ?> <a href="evenements.php" style="color:var(--purple-light)"><?= htmlspecialchars(corpo_t('mes_evt.link_all_evts')) ?></a>
        </p>
      <?php else: ?>
        <div class="mes-evt-grid">
          <?php foreach ($aVenir as $ev) renderEventCard($ev, true); ?>
        </div>
      <?php endif; ?>
    </section>

    <!-- Événements de mes assos et sports -->
    <?php if (!empty($evtsAsso) || !empty($evtsSport)): ?>
    <section>
      <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:var(--s4);display:flex;align-items:center;gap:.5rem">
        <span style="width:4px;height:1.1em;background:#FF9500;border-radius:2px;display:inline-block"></span>
        <?= htmlspecialchars(corpo_t('mes_evt.section_structures')) ?>
      </h2>
      <div class="mes-evt-grid">
        <?php foreach (array_merge($evtsAsso, $evtsSport) as $ev) renderEventCard($ev, false); ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- Événements passés -->
    <?php if (!empty($passes)): ?>
    <section>
      <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:var(--s4);display:flex;align-items:center;gap:.5rem;opacity:.7">
        <span style="width:4px;height:1.1em;background:var(--text-muted);border-radius:2px;display:inline-block"></span>
        <?= htmlspecialchars(corpo_t('mes_evt.section_past')) ?>
      </h2>
      <div class="mes-evt-grid" style="opacity:.6">
        <?php foreach ($passes as $ev) renderEventCard($ev, true); ?>
      </div>
    </section>
    <?php endif; ?>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
