<?php
// fil d’actus public (corpo + structures)
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

$stmt = $pdo->query(
    "SELECT a.*,
            COALESCE(sp.nom, ass.nom, 'Corpo Omnes') AS source_nom,
            COALESCE(a.structure_type, 'corpo') AS source_type,
            COALESCE(sp.slug, ass.slug, '') AS source_slug
     FROM actualites a
     LEFT JOIN associations ass ON a.structure_type IN ('asso','bde','bds') AND ass.id = a.structure_id
     LEFT JOIN sports sp ON a.structure_type = 'sport' AND sp.id = a.structure_id
     WHERE a.statut = 'publie' AND IFNULL(a.visibilite, 'public') = 'public'
     ORDER BY a.created_at DESC
     LIMIT 120"
);
$actus = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = corpo_t('actus.meta_title');
$page  = 'actualites';
require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section class="page-hero">
    <div class="container">
      <nav class="breadcrumb" aria-label="<?= htmlspecialchars(corpo_t('apr.breadcrumb_aria')) ?>">
        <a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a>
        <span aria-hidden="true">›</span>
        <a href="evenements.php"><?= htmlspecialchars(corpo_t('nav.events')) ?></a>
        <span aria-hidden="true">›</span>
        <span><?= htmlspecialchars(corpo_t('nav.news')) ?></span>
      </nav>
      <h1><?= htmlspecialchars(corpo_t('actus.h1')) ?></h1>
      <p class="page-hero__sub"><?= htmlspecialchars(corpo_t('actus.sub')) ?></p>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:var(--s4)">
        <button type="button" class="btn btn--sm actu-filter active" data-filter="all"><?= htmlspecialchars(corpo_t('mes_actu.f_all')) ?></button>
        <button type="button" class="btn btn--sm actu-filter" data-filter="corpo"><?= htmlspecialchars(corpo_t('mes_actu.f_corpo')) ?></button>
        <button type="button" class="btn btn--sm actu-filter" data-filter="asso"><?= htmlspecialchars(corpo_t('mes_actu.f_asso')) ?></button>
        <button type="button" class="btn btn--sm actu-filter" data-filter="sport"><?= htmlspecialchars(corpo_t('mes_actu.f_sport')) ?></button>
      </div>
      <?php if (isLoggedIn()): ?>
        <p style="margin-top:var(--s3);font-size:.85rem;color:var(--text-muted)">
          <?= htmlspecialchars(corpo_t('actus.logged_hint')) ?>
          <a href="mes-actualites.php" style="color:var(--purple-light)"><?= htmlspecialchars(corpo_t('account.news')) ?></a>
        </p>
      <?php endif; ?>
    </div>
  </section>

  <section class="container" style="padding-top:var(--s8);padding-bottom:var(--s12)">
    <?php if (empty($actus)): ?>
      <div class="mes-empty">
        <p><?= htmlspecialchars(corpo_t('actus.empty')) ?></p>
      </div>
    <?php else: ?>
      <div class="mes-actu-feed">
        <?php foreach ($actus as $ac):
            $sourceType = $ac['source_type'] ?? 'corpo';
            $sourceNom  = $ac['source_nom'] ?? 'Corpo Omnes';
            $sourceSlug = $ac['source_slug'] ?? '';
            $dt = new DateTime($ac['created_at']);
            $labelColor = match ($sourceType) {
                'sport' => '#FF9500',
                'asso', 'bde', 'bds' => '#5D0282',
                default => '#1a6fb5',
            };
            ?>
        <article class="actu-feed-item" id="actu-<?= (int)$ac['id'] ?>" data-type="<?= htmlspecialchars($sourceType) ?>">
          <div class="actu-feed-meta">
            <span class="actu-feed-source" style="background:<?= htmlspecialchars($labelColor) ?>22;color:<?= htmlspecialchars($labelColor) ?>;border-color:<?= htmlspecialchars($labelColor) ?>44">
              <?php if ($sourceSlug !== ''): ?>
                <a href="structure.php?<?= $sourceType === 'sport' ? 'sport' : 'slug' ?>=<?= urlencode($sourceSlug) ?>" style="color:inherit;text-decoration:none"><?= htmlspecialchars($sourceNom) ?></a>
              <?php else: ?>
                <?= htmlspecialchars($sourceNom) ?>
              <?php endif; ?>
            </span>
            <time class="actu-feed-date"><?= $dt->format('d/m/Y') ?></time>
          </div>
          <?php if (!empty($ac['image'])): ?>
            <div class="actu-feed-img-wrap">
              <img src="<?= htmlspecialchars($ac['image']) ?>" alt="" loading="lazy">
            </div>
          <?php endif; ?>
          <div class="actu-feed-content">
            <h2 style="font-size:1.15rem;margin:0 0 var(--s2)"><?= htmlspecialchars($ac['titre']) ?></h2>
            <div><?= nl2br(htmlspecialchars($ac['contenu'] ?? '')) ?></div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
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
      const show = f === 'all' || f === t || (f === 'asso' && ['asso','bde','bds'].includes(t));
      item.style.display = show ? '' : 'none';
    });
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
