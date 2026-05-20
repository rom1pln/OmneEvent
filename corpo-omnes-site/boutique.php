<?php
declare(strict_types=1);

$page = 'boutique';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/boutique.php';
require_once __DIR__ . '/includes/i18n.php';

// redirige après action panier pour éviter la re-soumission
function boutique_cart_redirect_back(): void {
    $qs = http_build_query($_GET);
    header('Location: boutique.php' . ($qs !== '' ? '?' . $qs : ''), true, 303);
    exit;
}

function boutique_desc_excerpt(string $text, int $max = 160): string {
    $t = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
    if ($t === '') {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($t) > $max) {
        return mb_substr($t, 0, max(1, $max - 1)) . '…';
    }
    if (strlen($t) > $max) {
        return substr($t, 0, max(1, $max - 3)) . '…';
    }
    return $t;
}

$title   = corpo_t('nav.shop');
$userId  = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
$flash   = '';
if (!empty($_SESSION['boutique_flash_html'])) {
    $flash = (string)$_SESSION['boutique_flash_html'];
    unset($_SESSION['boutique_flash_html']);
}
$bdReady = boutique_db_ready($pdo);

$filters = [
    'ecole'      => trim((string)($_GET['ecole'] ?? '')),
    'asso'       => (int)($_GET['asso'] ?? 0),
    'pmin'       => $_GET['pmin'] ?? '',
    'pmax'       => $_GET['pmax'] ?? '',
    'taille'     => trim((string)($_GET['taille'] ?? '')),
    'categorie'  => trim((string)($_GET['categorie'] ?? '')),
    'q'          => trim((string)($_GET['q'] ?? '')),
    'tri'        => trim((string)($_GET['tri'] ?? 'recent')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $bdReady) {
    $act = $_POST['act'] ?? '';
    if ($act === 'add_cart') {
        $pid = (int)($_POST['produit_id'] ?? 0);
        $q   = max(1, min(99, (int)($_POST['q'] ?? 1)));
        if ($pid) {
            boutique_cart_add($pid, $q);
            $_SESSION['boutique_flash_html'] = '<div class="shop-flash shop-flash--ok">' . htmlspecialchars(corpo_t('shop.flash_added')) . '</div>';
            boutique_cart_redirect_back();
        }
    }
    if ($act === 'remove_cart') {
        boutique_cart_remove((int)($_POST['produit_id'] ?? 0));
        $_SESSION['boutique_flash_html'] = '<div class="shop-flash shop-flash--ok">' . htmlspecialchars(corpo_t('shop.flash_removed')) . '</div>';
        boutique_cart_redirect_back();
    }
    if ($act === 'clear_cart') {
        boutique_cart_clear();
        $_SESSION['boutique_flash_html'] = '<div class="shop-flash shop-flash--ok">' . htmlspecialchars(corpo_t('shop.flash_cleared')) . '</div>';
        boutique_cart_redirect_back();
    }
    if ($act === 'update_qty') {
        $pid = (int)($_POST['produit_id'] ?? 0);
        $q   = max(1, min(99, (int)($_POST['q'] ?? 1)));
        $found = false;
        if ($pid) {
            $cart = boutique_cart_get();
            foreach ($cart as &$row) {
                if ((int)$row['id'] === $pid) {
                    $row['q'] = $q;
                    $found    = true;
                    break;
                }
            }
            unset($row);
            if ($found) {
                boutique_cart_set($cart);
                $_SESSION['boutique_flash_html'] = '<div class="shop-flash shop-flash--ok">' . htmlspecialchars(corpo_t('shop.flash_qty_updated')) . '</div>';
                boutique_cart_redirect_back();
            }
        }
    }
    if ($act === 'checkout') {
        $cart = boutique_cart_get();
        $v    = boutique_cart_validate($pdo, $cart);
        if (empty($v['ok']) || empty($v['lignes'])) {
            $flash = '<div class="shop-flash shop-flash--err">' . htmlspecialchars((string)($v['msg'] ?? 'Panier invalide.')) . '</div>';
        } else {
            $r = boutique_create_checkout(
                $pdo,
                $v['lignes'],
                trim((string)($_POST['email'] ?? '')),
                trim((string)($_POST['nom'] ?? '')),
                trim((string)($_POST['prenom'] ?? '')),
                $userId ?: null
            );
            if (!empty($r['ok']) && !empty($r['redirect'])) {
                header('Location: ' . $r['redirect']);
                exit;
            }
            $flash = '<div class="shop-flash shop-flash--err">' . htmlspecialchars((string)($r['msg'] ?? 'Erreur.')) . '</div>';
        }
    }
}

$orderView = null;
$orderPoll = null;
if ($bdReady && !empty($_GET['order'])) {
    $oid = (int)$_GET['order'];
    if ($oid > 0) {
        $st = $pdo->prepare('SELECT * FROM boutique_commandes WHERE id = ? LIMIT 1');
        $st->execute([$oid]);
        $orderView = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($orderView && ($orderView['statut'] ?? '') === 'en_attente' && (string)($_GET['stripe'] ?? '') === 'cancel') {
            $pdo->prepare(
                "UPDATE boutique_commandes SET statut = 'annule', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND statut = 'en_attente'"
            )->execute([$oid]);
            $st->execute([$oid]);
            $orderView = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if ($orderView && in_array($orderView['statut'], ['init', 'en_attente'], true)) {
            $orderPoll = boutique_poll_order_payment(
                $pdo,
                $oid,
                (!empty($_GET['mock_paid']) && (isAdminCorpo() || isSuperAdmin()))
                || ((string)($_GET['mock'] ?? '') === '1')
            );
            if ($orderPoll['state'] === 'paid') {
                header('Location: boutique.php?order=' . $oid);
                exit;
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';

if (!$bdReady) {
    echo '<main><section class="section"><div class="container"><div class="shop-flash shop-flash--warn" style="margin-top:var(--s4)">' . htmlspecialchars(corpo_t('shop.not_ready')) . '</div></div></section></main>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$shopPostAction = htmlspecialchars($base . 'boutique.php');

$produits   = boutique_catalog_list($pdo, $filters);
$ecoles     = boutique_catalog_distinct_ecoles($pdo);
$assosFilt  = boutique_catalog_assos_for_ecole($pdo, $filters['ecole'] !== '' ? $filters['ecole'] : null);
$tailles    = boutique_catalog_distinct_tailles($pdo);
$categories = boutique_catalog_distinct_categories($pdo);
$nbShop     = count($produits);

$cart      = boutique_cart_get();
$cartRows  = [];
$cartTotal = 0.0;
if ($cart) {
    $ids = array_column($cart, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $st  = $pdo->prepare(
        "SELECT p.id, p.titre, p.prix, p.stock, p.image, a.nom AS asso_nom
         FROM boutique_produits p
         JOIN associations a ON a.id = p.structure_id
         WHERE p.id IN ($ph) AND p.statut = 'publie'"
    );
    $st->execute($ids);
    $byId = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $byId[(int)$p['id']] = $p;
    }
    foreach ($cart as $c) {
        $pid = (int)$c['id'];
        $q   = (int)$c['q'];
        if (!isset($byId[$pid])) {
            continue;
        }
        $p = $byId[$pid];
        $line = (float)$p['prix'] * $q;
        $cartTotal += $line;
        $cartRows[] = ['p' => $p, 'q' => $q, 'line' => $line];
    }
}
$cartTotal = round($cartTotal, 2);

$prefillEmail  = '';
$prefillNom    = '';
$prefillPrenom = '';
if ($userId) {
    $stU = $pdo->prepare('SELECT email, nom, prenom FROM users WHERE id = ? LIMIT 1');
    $stU->execute([$userId]);
    if ($urow = $stU->fetch(PDO::FETCH_ASSOC)) {
        $prefillEmail  = (string)($urow['email'] ?? '');
        $prefillNom    = (string)($urow['nom'] ?? '');
        $prefillPrenom = (string)($urow['prenom'] ?? '');
    }
}
?>

<main>

  <section class="page-hero">
    <div class="container">
      <nav class="breadcrumb" aria-label="<?= htmlspecialchars(corpo_t('nav.main_aria')) ?>">
        <a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a><span aria-hidden="true">›</span><span><?= htmlspecialchars(corpo_t('shop.crumb')) ?></span>
      </nav>
      <h1><?= htmlspecialchars(corpo_t('nav.shop')) ?></h1>
      <p class="page-hero__sub"><?= htmlspecialchars(corpo_t('shop.hero_sub')) ?></p>
      <?php if ($flash !== ''): ?>
        <div class="shop-flash-wrap"><?= $flash ?></div>
      <?php endif; ?>
      <div class="evt-hero-stats" style="margin-top:var(--s5)" role="group" aria-label="<?= htmlspecialchars(corpo_t('shop.stat_items')) ?>">
        <div class="evt-stat"><strong><?= (int)$nbShop ?></strong><span><?= htmlspecialchars(corpo_t('shop.stat_items')) ?></span></div>
      </div>
    </div>
  </section>

  <?php if ($orderView): ?>
  <section class="section">
    <div class="container">
      <div class="shop-order">
        <?php if ($orderView['statut'] === 'en_attente' && $orderPoll && ($orderPoll['state'] ?? '') === 'pending'): ?>
          <script>setTimeout(function () { window.location.reload(); }, 3000);</script>
        <?php endif; ?>
        <h2><?= htmlspecialchars(sprintf(corpo_t('shop.order_title'), (int)$orderView['id'])) ?></h2>
        <?php if ($orderView['statut'] === 'paye'): ?>
          <p><?= htmlspecialchars(sprintf(corpo_t('shop.order_paid'), (string)$orderView['email'])) ?></p>
        <?php elseif ($orderView['statut'] === 'en_attente'): ?>
          <p><strong><?= htmlspecialchars(corpo_t('shop.order_pending')) ?></strong></p>
          <?php if ($orderPoll && ($orderPoll['state'] ?? '') === 'error'): ?>
            <p class="shop-flash shop-flash--err"><?= htmlspecialchars((string)($orderPoll['msg'] ?? '')) ?></p>
          <?php endif; ?>
        <?php elseif ($orderView['statut'] === 'echec'): ?>
          <p><strong><?= htmlspecialchars(corpo_t('shop.order_failed')) ?></strong></p>
        <?php elseif ($orderView['statut'] === 'annule'): ?>
          <p><strong><?= htmlspecialchars(corpo_t('shop.order_cancelled')) ?></strong></p>
        <?php elseif ($orderView['statut'] === 'init'): ?>
          <p><strong><?= htmlspecialchars(corpo_t('shop.order_init')) ?></strong></p>
        <?php else: ?>
          <p><strong><?= htmlspecialchars((string)$orderView['statut']) ?></strong></p>
        <?php endif; ?>
        <p><strong><?= htmlspecialchars(corpo_t('shop.order_total')) ?> :</strong> <?= number_format((float)$orderView['montant_total'], 2, ',', ' ') ?> €</p>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <div class="evt-filterbar">
    <div class="container">
      <form method="get" action="boutique.php" id="shop-filter-form">
        <div class="evt-filterbar__row evt-filterbar__row--top">
          <div class="evt-search-wrap">
            <svg class="evt-search-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
              <circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.6"/>
              <path d="M13 13l3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
            <input type="search" name="q" class="evt-search-input" value="<?= htmlspecialchars($filters['q']) ?>"
                   placeholder="<?= htmlspecialchars(corpo_t('shop.search_ph')) ?>"
                   aria-label="<?= htmlspecialchars(corpo_t('shop.search_ph')) ?>">
          </div>
          <button type="submit" class="btn btn--primary btn--sm"><?= htmlspecialchars(corpo_t('shop.btn_filter')) ?></button>
          <a href="boutique.php" class="evt-reset-btn"><?= htmlspecialchars(corpo_t('evt.reset')) ?></a>
        </div>
        <div class="evt-filterbar__row evt-filterbar__row--filters">
          <div class="evt-filter-group">
            <span class="evt-filter-label"><?= htmlspecialchars(corpo_t('shop.label_school')) ?></span>
            <select name="ecole" class="evt-select" aria-label="<?= htmlspecialchars(corpo_t('shop.label_school')) ?>">
              <option value=""><?= htmlspecialchars(corpo_t('shop.opt_all_f')) ?></option>
              <?php foreach ($ecoles as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>" <?= $filters['ecole'] === $e ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="evt-filter-group">
            <span class="evt-filter-label"><?= htmlspecialchars(corpo_t('shop.label_asso')) ?></span>
            <select name="asso" class="evt-select" aria-label="<?= htmlspecialchars(corpo_t('shop.label_asso')) ?>">
              <option value="0"><?= htmlspecialchars(corpo_t('shop.opt_all_f')) ?></option>
              <?php foreach ($assosFilt as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= $filters['asso'] === (int)$a['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$a['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="evt-filter-group">
            <span class="evt-filter-label"><?= htmlspecialchars(corpo_t('shop.label_price_min')) ?></span>
            <input type="number" step="0.01" name="pmin" class="shop-num" value="<?= htmlspecialchars((string)$filters['pmin']) ?>">
          </div>
          <div class="evt-filter-group">
            <span class="evt-filter-label"><?= htmlspecialchars(corpo_t('shop.label_price_max')) ?></span>
            <input type="number" step="0.01" name="pmax" class="shop-num" value="<?= htmlspecialchars((string)$filters['pmax']) ?>">
          </div>
          <div class="evt-filter-group">
            <span class="evt-filter-label"><?= htmlspecialchars(corpo_t('shop.label_size')) ?></span>
            <select name="taille" class="evt-select">
              <option value=""><?= htmlspecialchars(corpo_t('shop.opt_all_f')) ?></option>
              <?php foreach ($tailles as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= $filters['taille'] === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="evt-filter-group">
            <span class="evt-filter-label"><?= htmlspecialchars(corpo_t('shop.label_cat')) ?></span>
            <select name="categorie" class="evt-select">
              <option value=""><?= htmlspecialchars(corpo_t('shop.opt_all_f')) ?></option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $filters['categorie'] === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="evt-filter-group evt-filter-group--end">
            <span class="evt-filter-label"><?= htmlspecialchars(corpo_t('shop.label_sort')) ?></span>
            <select name="tri" class="evt-select">
              <option value="recent" <?= $filters['tri'] === 'recent' ? 'selected' : '' ?>><?= htmlspecialchars(corpo_t('shop.sort_recent')) ?></option>
              <option value="prix_asc" <?= $filters['tri'] === 'prix_asc' ? 'selected' : '' ?>><?= htmlspecialchars(corpo_t('shop.sort_price_asc')) ?></option>
              <option value="prix_desc" <?= $filters['tri'] === 'prix_desc' ? 'selected' : '' ?>><?= htmlspecialchars(corpo_t('shop.sort_price_desc')) ?></option>
              <option value="nom" <?= $filters['tri'] === 'nom' ? 'selected' : '' ?>><?= htmlspecialchars(corpo_t('shop.sort_name')) ?></option>
            </select>
          </div>
        </div>
      </form>
    </div>
  </div>

  <section class="section">
    <div class="container shop-main-grid">
      <div>
        <p class="evt-list-header" style="margin-bottom:var(--s5)">
          <?= htmlspecialchars(sprintf(corpo_t('shop.list_found'), (int)$nbShop)) ?>
        </p>

        <?php if (!$produits): ?>
          <p class="lead"><?= htmlspecialchars(corpo_t('shop.empty')) ?></p>
          <p class="shop-empty-hint"><?= htmlspecialchars(corpo_t('shop.empty_hint')) ?></p>
        <?php else: ?>
          <div class="shop-grid">
            <?php foreach ($produits as $pr):
                $img = trim((string)($pr['image'] ?? ''));
                if ($img === '') {
                    $img = 'images/logo-corpo-omnes.png';
                }
                $stk = (int)($pr['stock']);
                $maxAdd = $stk > 0 ? min(99, $stk) : 1;
                $badge = trim((string)($pr['categorie'] ?? '')) !== '' ? (string)$pr['categorie'] : corpo_t('shop.card_badge_default');
                ?>
            <article class="shop-card">
              <div class="shop-card__media">
                <span class="shop-card__badge"><?= htmlspecialchars($badge) ?></span>
                <img src="<?= htmlspecialchars($img) ?>" alt="" loading="lazy" width="400" height="300">
              </div>
              <div class="shop-card__body">
                <p class="shop-card__meta">
                  <strong><?= htmlspecialchars((string)$pr['asso_nom']) ?></strong>
                  · <?= htmlspecialchars((string)$pr['asso_ecole']) ?>
                  <?php if (!empty($pr['taille'])): ?>
                    · <?= htmlspecialchars(corpo_t('shop.label_size')) ?> <?= htmlspecialchars((string)$pr['taille']) ?>
                  <?php endif; ?>
                </p>
                <h2 class="shop-card__title"><?= htmlspecialchars((string)$pr['titre']) ?></h2>
                <?php if (trim((string)($pr['description'] ?? '')) !== ''): ?>
                  <p class="shop-card__desc"><?= nl2br(htmlspecialchars(boutique_desc_excerpt((string)$pr['description']))) ?></p>
                <?php endif; ?>
                <div class="shop-card__price"><?= number_format((float)$pr['prix'], 2, ',', ' ') ?> €</div>
                <?php if ($stk < 1): ?>
                  <p class="shop-card__stock shop-card__stock--out"><?= htmlspecialchars(corpo_t('shop.stock_out')) ?></p>
                <?php else: ?>
                  <p class="shop-card__stock"><?= htmlspecialchars(corpo_t('shop.stock_label')) ?> : <?= $stk ?></p>
                <?php endif; ?>
                <form method="post" action="<?= $shopPostAction ?>" class="shop-card__cta">
                  <input type="hidden" name="act" value="add_cart">
                  <input type="hidden" name="produit_id" value="<?= (int)$pr['id'] ?>">
                  <input type="number" name="q" value="1" min="1" max="<?= $maxAdd ?>" aria-label="Quantité">
                  <button type="submit" class="btn btn--primary btn--sm" <?= $stk < 1 ? 'disabled' : '' ?>><?= htmlspecialchars(corpo_t('shop.add_cart')) ?></button>
                </form>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <aside class="shop-cart">
        <h2><?= htmlspecialchars(corpo_t('shop.cart_title')) ?></h2>
        <?php if (!$cartRows): ?>
          <p style="font-size:.88rem;color:var(--blue-light);margin:0"><?= htmlspecialchars(corpo_t('shop.cart_empty')) ?></p>
        <?php else: ?>
          <?php foreach ($cartRows as $cr): ?>
            <?php $p = $cr['p']; ?>
            <div class="shop-cart-line">
              <span class="shop-cart-line__title"><?= htmlspecialchars((string)$p['titre']) ?></span>
              <span class="shop-cart-line__meta"><?= htmlspecialchars((string)$p['asso_nom']) ?></span>
              <div style="display:flex;align-items:center;gap:8px;margin-top:8px;flex-wrap:wrap">
                <form method="post" action="<?= $shopPostAction ?>" style="display:flex;align-items:center;gap:6px">
                  <input type="hidden" name="act" value="update_qty">
                  <input type="hidden" name="produit_id" value="<?= (int)$p['id'] ?>">
                  <label style="font-size:.72rem;color:var(--text-muted)"><?= htmlspecialchars(corpo_t('shop.lbl_qty')) ?></label>
                  <input type="number" name="q" value="<?= (int)$cr['q'] ?>" min="1" max="<?= min(99, max(1, (int)$p['stock'])) ?>" class="shop-num" aria-label="<?= htmlspecialchars(corpo_t('shop.lbl_qty')) ?>">
                  <button type="submit" class="btn btn--ghost btn--sm">OK</button>
                </form>
                <form method="post" action="<?= $shopPostAction ?>" style="display:inline">
                  <input type="hidden" name="act" value="remove_cart">
                  <input type="hidden" name="produit_id" value="<?= (int)$p['id'] ?>">
                  <button type="submit" class="btn btn--ghost btn--sm btn-outline-danger"><?= htmlspecialchars(corpo_t('shop.cart_remove')) ?></button>
                </form>
              </div>
              <div style="margin-top:6px;font-weight:700;color:var(--purple-light)"><?= number_format($cr['line'], 2, ',', ' ') ?> €</div>
            </div>
          <?php endforeach; ?>
          <p style="font-size:1rem;font-weight:700;color:#fff;margin:var(--s4) 0 var(--s2)"><?= htmlspecialchars(corpo_t('shop.cart_total_est')) ?> : <?= number_format($cartTotal, 2, ',', ' ') ?> €</p>
          <p style="font-size:.75rem;color:var(--text-muted);line-height:1.5;margin:0 0 var(--s4)"><?= htmlspecialchars(corpo_t('shop.cart_fees_note')) ?></p>

          <form method="post" action="<?= $shopPostAction ?>">
            <input type="hidden" name="act" value="checkout">
            <div class="shop-field">
              <label for="shop-email"><?= htmlspecialchars(corpo_t('shop.lbl_email')) ?> *</label>
              <input id="shop-email" type="email" name="email" required value="<?= htmlspecialchars($prefillEmail) ?>">
            </div>
            <div class="shop-field">
              <label for="shop-nom"><?= htmlspecialchars(corpo_t('shop.lbl_nom')) ?> *</label>
              <input id="shop-nom" type="text" name="nom" required value="<?= htmlspecialchars($prefillNom) ?>">
            </div>
            <div class="shop-field">
              <label for="shop-prenom"><?= htmlspecialchars(corpo_t('shop.lbl_prenom')) ?> *</label>
              <input id="shop-prenom" type="text" name="prenom" required value="<?= htmlspecialchars($prefillPrenom) ?>">
            </div>
            <button type="submit" class="btn btn--primary" style="width:100%;margin-top:var(--s2)"><?= htmlspecialchars(corpo_t('shop.checkout_pay')) ?></button>
          </form>
          <form method="post" action="<?= $shopPostAction ?>" style="margin-top:var(--s3)">
            <input type="hidden" name="act" value="clear_cart">
            <button type="submit" class="btn btn--outline" style="width:100%"><?= htmlspecialchars(corpo_t('shop.checkout_clear')) ?></button>
          </form>
        <?php endif; ?>
      </aside>
    </div>
  </section>

</main>

<script>
(function () {
  var f = document.getElementById('shop-filter-form');
  if (!f) return;
  f.querySelectorAll('select.evt-select').forEach(function (s) {
    s.addEventListener('change', function () { f.submit(); });
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
