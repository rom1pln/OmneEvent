<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/boutique.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = 'mes-commandes.php';
    header('Location: admin/login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$bdReady = boutique_db_ready($pdo);
$orders  = $bdReady ? boutique_orders_list_for_user($pdo, $userId) : [];

$title = corpo_t('mes_cmd.title');
$page  = 'mes-commandes';

function mes_cmd_statut_label(string $s): string {
    return match ($s) {
        'paye'        => corpo_t('mes_cmd.st_paye'),
        'en_attente'  => corpo_t('mes_cmd.st_en_attente'),
        'init'        => corpo_t('mes_cmd.st_init'),
        'echec'       => corpo_t('mes_cmd.st_echec'),
        'annule'      => corpo_t('mes_cmd.st_annule'),
        default       => $s,
    };
}

require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section class="page-hero">
    <div class="container">
      <nav class="breadcrumb" aria-label="<?= htmlspecialchars(corpo_t('nav.main_aria')) ?>">
        <a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a><span aria-hidden="true">›</span>
        <span><?= htmlspecialchars(corpo_t('mes_cmd.title')) ?></span>
      </nav>
      <h1><?= htmlspecialchars(corpo_t('mes_cmd.title')) ?></h1>
      <p class="page-hero__sub"><?= htmlspecialchars(corpo_t('mes_cmd.sub')) ?></p>
    </div>
  </section>

  <div class="container" style="padding-top:var(--s8);padding-bottom:var(--s12)">
    <?php if (!$bdReady): ?>
      <p class="shop-flash shop-flash--warn" style="margin:0"><?= htmlspecialchars(corpo_t('shop.not_ready')) ?></p>
    <?php elseif (empty($orders)): ?>
      <p class="mes-empty" style="text-align:left;padding:var(--s5);background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg)">
        <?= htmlspecialchars(corpo_t('mes_cmd.empty')) ?>
        <a href="boutique.php" style="color:var(--purple-light);margin-left:.35rem"><?= htmlspecialchars(corpo_t('mes_cmd.link_shop')) ?></a>
      </p>
    <?php else: ?>
      <p style="font-size:.82rem;color:var(--text-muted);margin:0 0 var(--s5)"><?= htmlspecialchars(corpo_t('mes_cmd.email_hint')) ?></p>
      <div style="display:flex;flex-direction:column;gap:var(--s4)">
        <?php foreach ($orders as $c):
            $sid = (int)($c['id'] ?? 0);
            $st  = (string)($c['statut'] ?? '');
            $badgeStyle = match ($st) {
                'paye'       => 'color:#2ecc71;border-color:rgba(46,204,113,.35)',
                'en_attente', 'init' => 'color:#e67e22;border-color:rgba(230,126,34,.35)',
                'echec', 'annule' => 'color:#e74c3c;border-color:rgba(231,76,60,.35)',
                default      => 'color:var(--text-muted);border-color:var(--border)',
            };
            $ts = strtotime((string)($c['created_at'] ?? '')) ?: 0;
            $dLabel = $ts ? date('d/m/Y H:i', $ts) : '-';
            $nbL   = (int)($c['nb_lignes'] ?? 0);
            ?>
        <article class="evt-card" style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:var(--s4);padding:var(--s5)">
          <div style="min-width:min(100%,220px)">
            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.35rem">
              <span class="tag" style="font-size:.65rem;<?= $badgeStyle ?>"><?= htmlspecialchars(mes_cmd_statut_label($st)) ?></span>
              <span style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars(sprintf(corpo_t('mes_cmd.order_num'), $sid)) ?></span>
            </div>
            <p style="margin:0;font-size:.88rem;color:var(--blue-light)"><?= htmlspecialchars($dLabel) ?></p>
            <p style="margin:.35rem 0 0;font-size:.85rem;color:var(--text-muted)">
              <?= (int)$nbL ?> <?= $nbL > 1 ? htmlspecialchars(corpo_t('mes_cmd.items_many')) : htmlspecialchars(corpo_t('mes_cmd.items_one')) ?>
            </p>
          </div>
          <div style="text-align:right;min-width:min(100%,140px)">
            <p style="margin:0;font-weight:800;font-size:1.1rem;color:var(--purple-light)">
              <?= number_format((float)($c['montant_total'] ?? 0), 2, ',', ' ') ?> €
            </p>
            <a href="boutique.php?order=<?= $sid ?>" class="btn btn--primary btn--sm" style="margin-top:var(--s3)"><?= htmlspecialchars(corpo_t('mes_cmd.btn_detail')) ?></a>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
