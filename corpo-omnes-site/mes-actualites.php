<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = 'mes-actualites.php';
    header('Location: admin/login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

$stmtCorpo = $pdo->prepare(
    "SELECT a.*, 'Corpo Omnes' AS source_nom, 'corpo' AS source_type, NULL AS source_slug
     FROM actualites a
     WHERE a.structure_type IS NULL AND a.statut = 'publie' AND IFNULL(a.visibilite,'public') = 'public'
     ORDER BY a.created_at DESC LIMIT 10"
);
$stmtCorpo->execute();
$actusCorpo = $stmtCorpo->fetchAll();

$stmtAsso = $pdo->prepare(
    "SELECT a.*, s.nom AS source_nom, sm.structure_type AS source_type, s.slug AS source_slug
     FROM actualites a
     JOIN structure_membres sm ON sm.user_id = ? AND sm.statut = 'actif'
                               AND a.structure_type = sm.structure_type
                               AND a.structure_id   = sm.structure_id
     JOIN associations s ON s.id = sm.structure_id
     WHERE a.statut = 'publie'
       AND (IFNULL(a.visibilite,'public') = 'public' OR IFNULL(a.visibilite,'public') = 'membres')
     ORDER BY a.created_at DESC LIMIT 20"
);
$stmtAsso->execute([$userId]);
$actusAsso = $stmtAsso->fetchAll();

$stmtSport = $pdo->prepare(
    "SELECT a.*, sp.nom AS source_nom, 'sport' AS source_type, sp.slug AS source_slug
     FROM actualites a
     JOIN inscriptions_sport ins ON ins.user_id = ? AND ins.statut = 'confirme'
                                 AND a.structure_type = 'sport'
                                 AND a.structure_id   = ins.sport_id
     JOIN sports sp ON sp.id = ins.sport_id
     WHERE a.statut = 'publie'
       AND (IFNULL(a.visibilite,'public') = 'public' OR IFNULL(a.visibilite,'public') = 'membres')
     ORDER BY a.created_at DESC LIMIT 10"
);
$stmtSport->execute([$userId]);
$actusSport = $stmtSport->fetchAll();

$all = array_merge($actusCorpo, $actusAsso, $actusSport);
usort($all, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

$title = corpo_t('mes_actu.h1');
$page  = '';
require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section class="page-hero">
    <div class="container">
      <nav class="breadcrumb"><a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a><span>›</span><span><?= htmlspecialchars(corpo_t('mes_actu.crumb')) ?></span></nav>
      <h1><?= htmlspecialchars(corpo_t('mes_actu.h1')) ?></h1>
      <p class="page-hero__sub"><?= htmlspecialchars(corpo_t('mes_actu.sub')) ?></p>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1.2rem">
        <button class="btn btn--sm actu-filter active" data-filter="all"><?= htmlspecialchars(corpo_t('mes_actu.f_all')) ?></button>
        <button class="btn btn--sm actu-filter" data-filter="corpo"><?= htmlspecialchars(corpo_t('mes_actu.f_corpo')) ?></button>
        <button class="btn btn--sm actu-filter" data-filter="asso"><?= htmlspecialchars(corpo_t('mes_actu.f_asso')) ?></button>
        <button class="btn btn--sm actu-filter" data-filter="sport"><?= htmlspecialchars(corpo_t('mes_actu.f_sport')) ?></button>
      </div>
    </div>
  </section>

  <section class="container" style="padding-top:var(--s8);padding-bottom:var(--s12)">

    <?php if (empty($all)): ?>
      <div class="mes-empty">
        <p style="font-size:2rem">📭</p>
        <p><?= htmlspecialchars(corpo_t('mes_actu.empty1')) ?></p>
        <p style="font-size:.85rem;color:var(--text-muted)"><?= htmlspecialchars(corpo_t('mes_actu.empty2')) ?></p>
        <a href="associations.php" class="btn btn--primary btn--sm" style="margin-top:var(--s4)"><?= htmlspecialchars(corpo_t('mes_actu.btn_assos')) ?></a>
      </div>
    <?php else: ?>
      <div class="mes-actu-feed">
        <?php foreach ($all as $ac):
          $sourceType = $ac['source_type'] ?? 'corpo';
          $sourceNom  = $ac['source_nom'] ?? 'Corpo Omnes';
          $sourceSlug = $ac['source_slug'] ?? '';
          $dt = new DateTime($ac['created_at']);
          $isPrivActu = (($ac['visibilite'] ?? 'public') === 'membres');
          $labelColor = match($sourceType) {
              'sport'  => '#FF9500',
              'asso','bde','bds' => '#5D0282',
              default  => '#1a6fb5',
          };
        ?>
        <article class="actu-feed-item" data-type="<?= htmlspecialchars($sourceType) ?>">
          <div class="actu-feed-meta">
            <span class="actu-feed-source" style="background:<?= $labelColor ?>22;color:<?= $labelColor ?>;border-color:<?= $labelColor ?>44">
              <?php if ($sourceSlug): ?>
                <a href="structure.php?<?= $sourceType === 'sport' ? 'sport' : 'slug' ?>=<?= urlencode($sourceSlug) ?>" style="color:inherit;text-decoration:none"><?= htmlspecialchars($sourceNom) ?></a>
              <?php else: ?>
                <?= htmlspecialchars($sourceNom) ?>
              <?php endif; ?>
            </span>
            <time class="actu-feed-date"><?= $dt->format('d/m/Y') ?></time>
            <?php if ($isPrivActu): ?>
              <span class="badge badge--pending" style="font-size:.65rem;margin-left:.35rem">Membres</span>
            <?php endif; ?>
          </div>

          <?php if (!empty($ac['image'])): ?>
            <div class="actu-feed-img-wrap">
              <img src="<?= htmlspecialchars($ac['image']) ?>" alt="" loading="lazy">
            </div>
          <?php endif; ?>

          <div class="actu-feed-content">
            <h3><?= htmlspecialchars($ac['titre']) ?></h3>
            <p><?= nl2br(htmlspecialchars(mb_substr($ac['contenu'] ?? '', 0, 280))) ?>…</p>
          </div>
        </article>
        <?php endforeach; ?>
      </div>    <?php endif; ?>

  </section>
</main>

<script>

document.querySelectorAll('.actu-filter').forEach(btn => {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.actu-filter').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    const f = this.dataset.filter;
    document.querySelectorAll('.actu-feed-item').forEach(item => {
      const t = item.dataset.type;
      const show = f === 'all'
        || f === t
        || (f === 'asso' && ['asso','bde','bds'].includes(t));
      item.style.display = show ? '' : 'none';
    });
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
