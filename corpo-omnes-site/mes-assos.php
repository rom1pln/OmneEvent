<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/billetterie.php';

requireLogin();

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'retirer_demande') {
    $stType = $_POST['structure_type'] ?? '';
    $stId   = (int)($_POST['structure_id'] ?? 0);
    if (in_array($stType, ['asso','bde','bds','sport'], true) && $stId > 0) {
        $pdo->prepare(
            "DELETE FROM demandes_adhesion WHERE user_id = ? AND structure_type = ? AND structure_id = ? AND statut = 'en_attente'"
        )->execute([$userId, $stType, $stId]);
    }
    header('Location: mes-assos.php?retire=1');
    exit;
}

$stmtStruct = $pdo->prepare(
    "SELECT sm.structure_type, sm.structure_id, sm.role_in_struct, sm.statut,
            a.nom, a.slug, a.type AS asso_type, a.ecole, a.color, a.campus, a.description, a.logo
     FROM structure_membres sm
     JOIN associations a ON a.id = sm.structure_id
     WHERE sm.user_id = ? AND sm.structure_type IN ('asso','bde','bds') AND sm.statut = 'actif'
     ORDER BY sm.structure_type, a.nom"
);
$stmtStruct->execute([$userId]);
$mesStructures = $stmtStruct->fetchAll();

$stmtDem = $pdo->prepare(
    "SELECT da.*, a.nom AS struct_nom
     FROM demandes_adhesion da
     JOIN associations a ON a.id = da.structure_id
     WHERE da.user_id = ? AND da.statut = 'en_attente'
     ORDER BY da.created_at DESC"
);
$stmtDem->execute([$userId]);
$demandesAttente = $stmtDem->fetchAll();

$structIds = array_column($mesStructures, 'structure_id');

$actus = $events = [];
if (!empty($structIds)) {
    $in = implode(',', array_fill(0, count($structIds), '?'));

    $stmtA = $pdo->prepare(
        "SELECT ac.*, a.nom AS struct_nom, a.color
         FROM actualites ac
         JOIN associations a ON a.id = ac.structure_id
         WHERE ac.structure_type = 'asso' AND ac.structure_id IN ($in) AND ac.statut = 'publie'
           AND (IFNULL(ac.visibilite,'public') = 'public' OR IFNULL(ac.visibilite,'public') = 'membres')
         ORDER BY ac.created_at DESC LIMIT 10"
    );
    $stmtA->execute($structIds);
    $actus = $stmtA->fetchAll();

    $stmtE = $pdo->prepare(
        "SELECT ev.*, a.nom AS struct_nom, a.color
         FROM evenements ev
         JOIN associations a ON a.id = ev.structure_id
         WHERE ev.structure_type = 'asso' AND ev.structure_id IN ($in)
           AND ev.statut = 'publie' AND ev.date >= CURDATE()
         ORDER BY ev.date ASC LIMIT 10"
    );
    $stmtE->execute($structIds);
    $events = $stmtE->fetchAll();
}

$title = corpo_t('mes_asso.title');
$page  = 'associations';
require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section class="page-hero">
    <div class="container">
      <nav class="breadcrumb"><a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a><span>›</span><span><?= htmlspecialchars(corpo_t('mes_asso.title')) ?></span></nav>
      <h1><?= htmlspecialchars(corpo_t('mes_asso.title')) ?></h1>
      <p class="page-hero__sub"><?= htmlspecialchars(corpo_t('mes_asso.sub')) ?></p>
      <a href="associations.php" class="btn btn--ghost btn--sm" style="margin-top:1rem"><?= htmlspecialchars(corpo_t('mes_asso.link_all')) ?></a>
    </div>
  </section>

  <div class="mes-page container" style="padding-top:var(--s8);padding-bottom:var(--s12)">

    <?php if (empty($mesStructures) && empty($demandesAttente)): ?>
    <div class="mes-empty">
      <p><?= htmlspecialchars(corpo_t('mes_asso.empty1')) ?></p>
      <a href="associations.php" class="btn-pill btn-primary"><?= htmlspecialchars(corpo_t('mes_asso.btn_explore')) ?></a>
    </div>
  <?php else: ?>

    <?php if (!empty($demandesAttente)): ?>
    <section class="mes-section">
      <h2><?= htmlspecialchars(corpo_t('mes_asso.pending_h2')) ?></h2>
      <div class="mes-cards-grid">
        <?php foreach ($demandesAttente as $dem): ?>
          <div class="mes-card pending" style="display:flex;flex-direction:column;gap:.4rem;align-items:flex-start">
            <strong><?= htmlspecialchars($dem['struct_nom']) ?></strong>
            <span><?= htmlspecialchars(corpo_t('mes_asso.pending_line')) ?> <?= date('d/m/Y', strtotime($dem['created_at'])) ?></span>
            <form method="post" onsubmit="return confirm('Retirer cette demande ?')" style="margin-top:.2rem">
              <input type="hidden" name="action" value="retirer_demande">
              <input type="hidden" name="structure_type" value="<?= htmlspecialchars($dem['structure_type']) ?>">
              <input type="hidden" name="structure_id"   value="<?= (int)$dem['structure_id'] ?>">
              <button class="btn btn--sm" style="background:transparent;border:1px solid rgba(231,76,60,.5);color:#e74c3c;padding:.3rem .7rem;border-radius:6px;font-size:.75rem">
                Retirer ma demande
              </button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="mes-section">
      <h2><?= htmlspecialchars(corpo_t('mes_asso.structures_h2')) ?></h2>
      <div class="mes-cards-grid">
        <?php foreach ($mesStructures as $s): ?>
          <?php $color = $s['color'] ?? '#5D0282'; ?>
          <a href="structure.php?slug=<?= urlencode($s['slug']) ?>" class="mes-card"
             style="border-top:4px solid <?= htmlspecialchars($color) ?>">
            <div class="mes-card__logo" style="background:<?= htmlspecialchars($color) ?>22;color:<?= htmlspecialchars($color) ?>">
              <?php if (!empty($s['logo'])): ?>
                <img src="<?= htmlspecialchars($s['logo']) ?>" alt="Logo <?= htmlspecialchars($s['nom']) ?>">
              <?php else: ?>
                <?= mb_strtoupper(mb_substr($s['nom'], 0, 2)) ?>
              <?php endif; ?>
            </div>
            <div class="mes-card__body">
              <strong><?= htmlspecialchars($s['nom']) ?></strong>
              <span><?= htmlspecialchars($s['asso_type'] ?? $s['structure_type']) ?> · <?= htmlspecialchars($s['ecole']) ?></span>
              <?php if ($s['role_in_struct'] === 'admin'): ?>
                <span class="badge-admin">Bureau</span>
              <?php elseif ($s['role_in_struct'] === 'membre'): ?>
                <span class="badge-admin" style="background:rgba(124,224,176,.18);color:#7ce0b0">Membre</span>
              <?php else: ?>
                <span class="badge-admin" style="background:rgba(255,255,255,.06);color:#aaa">Adhérent</span>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

  <?php endif; ?>

    <section class="mes-section">
    <h2><?= htmlspecialchars(corpo_t('mes_asso.events_h2')) ?></h2>
    <?php if (empty($events)): ?>
      <p class="mes-empty-inline"><?= htmlspecialchars(corpo_t('mes_asso.empty_events')) ?></p>
    <?php else: ?>
      <div class="mes-events-list">
        <?php foreach ($events as $ev): ?>
          <?php $evColor = $ev['color'] ?? '#5D0282'; ?>
          <div class="mes-event-item">
            <div class="mes-event-date" style="background:<?= htmlspecialchars($evColor) ?>">
              <strong><?= date('d', strtotime($ev['date'])) ?></strong>
              <span><?= date('M', strtotime($ev['date'])) ?></span>
            </div>
            <div class="mes-event-info">
              <strong><?= htmlspecialchars($ev['titre']) ?></strong>
              <span><?= htmlspecialchars($ev['struct_nom']) ?> <?= $ev['heure'] ? '· ' . htmlspecialchars($ev['heure']) : '' ?></span>
              <?php if ($ev['lieu']): ?><span class="lieu"><?= htmlspecialchars($ev['lieu']) ?></span><?php endif; ?>
            </div>
            <?php $evMode = function_exists('evt_normalize_mode') ? evt_normalize_mode($ev['mode_inscription'] ?? 'aucune') : ($ev['mode_inscription'] ?? 'aucune'); ?>
            <?php if ($evMode === 'externe' && $ev['lien_billetterie']): ?>
              <a href="<?= htmlspecialchars($ev['lien_billetterie']) ?>" target="_blank" class="btn-sm"><?= htmlspecialchars(corpo_t('mes_asso.btn_ticketing')) ?></a>
            <?php elseif (in_array($evMode, ['email','connexion','billetterie_email','billetterie_connexion'], true)): ?>
              <a href="evenement.php?id=<?= $ev['id'] ?>" class="btn-sm btn-primary"><?= htmlspecialchars(corpo_t('mes_asso.btn_join_evt')) ?></a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

    <section class="mes-section">
    <h2><?= htmlspecialchars(corpo_t('mes_asso.actus_h2')) ?></h2>
    <?php if (empty($actus)): ?>
      <p class="mes-empty-inline"><?= htmlspecialchars(corpo_t('mes_asso.empty_actus')) ?></p>
    <?php else: ?>
      <div class="mes-actus-grid">
        <?php foreach ($actus as $a): ?>
          <?php $aColor = $a['color'] ?? '#5D0282'; ?>
          <article class="mes-actu-card">
            <div class="mes-actu-badge" style="background:<?= htmlspecialchars($aColor) ?>22;color:<?= htmlspecialchars($aColor) ?>">
              <?= htmlspecialchars($a['struct_nom']) ?>
            </div>
            <h3><?= htmlspecialchars($a['titre']) ?></h3>
            <p><?= htmlspecialchars(mb_substr(strip_tags($a['contenu']), 0, 160)) ?>…</p>
            <time><?= date('d/m/Y', strtotime($a['created_at'])) ?></time>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  </div></main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
