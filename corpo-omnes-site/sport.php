<?php
require_once 'includes/i18n.php';
$title = corpo_t('sport.meta_title');
$page  = 'sport';
require_once 'includes/db.php';
require_once 'includes/auth.php';

// ── Inscription rapide depuis la page sport (AJAX-free) ──────
$userId    = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
$flashSport = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId) {
    $sportId = (int)($_POST['sport_id'] ?? 0);
    $action  = $_POST['action'] ?? '';
    $motiv   = trim($_POST['message'] ?? '');

    if ($sportId && $action === 'rejoindre') {
        // Sport club → demande de validation (pas d'inscription directe)
        $chk = $pdo->prepare("SELECT id FROM demandes_adhesion WHERE user_id=? AND structure_type='sport' AND structure_id=? AND statut='en_attente'");
        $chk->execute([$userId, $sportId]);
        if ($chk->fetchColumn()) {
            $flashSport = corpo_t('sport.flash_pending');
        } else {
            $pdo->prepare("INSERT INTO demandes_adhesion (user_id, structure_type, structure_id, message, statut)
                           VALUES (?, 'sport', ?, ?, 'en_attente')")->execute([$userId, $sportId, $motiv]);
            $flashSport = corpo_t('sport.flash_sent');
        }
    }
    if ($sportId && $action === 'quitter') {
        $pdo->prepare("DELETE FROM inscriptions_sport WHERE user_id=? AND sport_id=?")->execute([$userId, $sportId]);
        $pdo->prepare("UPDATE sports SET inscrits = GREATEST(0, inscrits-1) WHERE id=?")->execute([$sportId]);
        $pdo->prepare("DELETE FROM structure_membres WHERE user_id=? AND structure_type='sport' AND structure_id=?")->execute([$userId, $sportId]);
        // Si l'user perd son dernier statut admin via ce retrait, ramène son rôle global
        // de 'membre_corpo' à 'user' pour qu'il n'ait plus accès au panneau admin.
        syncGlobalRoleAfterStructChange($pdo, $userId);
        $flashSport = corpo_t('sport.flash_left');
    }
    $sports = $pdo->query("SELECT * FROM sports WHERE categorie = 'club' ORDER BY id")->fetchAll();
}

// Mes inscriptions : map sport_id → statut
$mesInscriptions = [];
if ($userId) {
    $stmtMi = $pdo->prepare("SELECT sport_id, statut FROM inscriptions_sport WHERE user_id = ?");
    $stmtMi->execute([$userId]);
    foreach ($stmtMi->fetchAll() as $row) {
        $mesInscriptions[$row['sport_id']] = $row['statut'];
    }
}

require_once 'includes/header.php';

// Sports clubs avec leurs entraînements
$sports  = $pdo->query("SELECT * FROM sports WHERE categorie = 'club' ORDER BY id")->fetchAll();
$libres  = $pdo->query("SELECT * FROM sports WHERE categorie = 'individuel' ORDER BY nom")->fetchAll();

// Entraînements par sport_id
$entrainements = [];
foreach ($pdo->query("SELECT * FROM sport_entrainements ORDER BY sport_id, id") as $row) {
    $entrainements[$row['sport_id']][] = $row;
}

// Derniers résultats (tous sports confondus, 6 max)
$resultats = $pdo->query(
    "SELECT r.*, s.nom AS sport_nom, s.icon AS sport_icon, s.couleur
     FROM sport_resultats r
     JOIN sports s ON s.id = r.sport_id
     ORDER BY r.date DESC LIMIT 6"
)->fetchAll();
?>

  <main>

    <section class="page-hero">
      <div class="container">
        <nav class="breadcrumb" aria-label="<?= htmlspecialchars(corpo_t('sport.breadcrumb_aria')) ?>">
          <a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a><span aria-hidden="true">›</span><span><?= htmlspecialchars(corpo_t('sport.crumb')) ?></span>
        </nav>
        <h1><?= htmlspecialchars(corpo_t('sport.hero_h1')) ?></h1>
        <p class="page-hero__sub"><?= htmlspecialchars(corpo_t('sport.hero_sub')) ?></p>
        <?php if ($userId): ?>
          <a href="mes-sports.php" class="btn btn--ghost btn--sm" style="margin-top:1rem"><?= htmlspecialchars(corpo_t('sport.link_mes_sports')) ?></a>
        <?php endif; ?>
        <?php if ($flashSport): ?>
          <div style="margin-top:1rem;padding:.6rem 1rem;background:rgba(39,174,96,.15);border:1px solid rgba(39,174,96,.4);border-radius:var(--r-md);font-size:.85rem;color:#2ecc71"><?= htmlspecialchars($flashSport) ?></div>
        <?php endif; ?>
      </div>
    </section>

    <!-- CLUBS -->
    <section class="section" aria-labelledby="clubs-title">
      <div class="container">
        <span class="section-label"><?= htmlspecialchars(corpo_t('sport.section_clubs_label')) ?></span>
        <h2 class="section-title" id="clubs-title"><?= htmlspecialchars(sprintf(corpo_t('sport.section_clubs_title'), count($sports))) ?></h2>
        <p class="lead"><?= htmlspecialchars(corpo_t('sport.section_clubs_lead')) ?></p>

        <div class="sport-hub-grid">
          <?php foreach ($sports as $s):
            $dispo = $s['places'] - $s['inscrits'];
            $pct   = $s['places'] > 0 ? round(($s['inscrits'] / $s['places']) * 100) : 0;
            $ent   = $entrainements[$s['id']] ?? [];
          ?>
          <article class="sport-hub-card" style="--sport-color: <?= htmlspecialchars($s['couleur']) ?>">
            <div class="sport-hub-card__left" aria-hidden="true">
              <?php if (!empty($s['logo'])): ?>
                <img src="<?= htmlspecialchars($s['logo']) ?>" alt="" style="width:56px;height:56px;object-fit:contain">
              <?php else: ?>
                <span class="sport-hub-card__initials"><?= mb_strtoupper(mb_substr($s['nom'], 0, 2)) ?></span>
              <?php endif; ?>
            </div>
            <div class="sport-hub-card__right">
              <div class="sport-hub-card__header">
                <h3 class="sport-hub-card__name"><?= htmlspecialchars($s['nom']) ?></h3>
                <span class="sport-hub-card__badge"><?= htmlspecialchars($s['campus']) ?></span>
              </div>
              <p class="sport-hub-card__desc"><?= htmlspecialchars($s['description']) ?></p>
              <ul class="sport-hub-card__schedule">
                <?php foreach ($ent as $e): ?>
                  <li><strong><?= htmlspecialchars($e['jour']) ?></strong> · <?= htmlspecialchars($e['heure']) ?></li>
                <?php endforeach; ?>
              </ul>
              <div class="sport-hub-card__footer">
                <div class="sport-hub-card__places">
                  <div class="sport-hub-card__bar-track">
                    <div class="sport-hub-card__bar-fill" style="width:<?= $pct ?>%"></div>
                  </div>
                  <span><?= $dispo > 0 ? htmlspecialchars(sprintf(corpo_t('sport.places_avail'), $dispo)) : htmlspecialchars(corpo_t('common.full')) ?></span>
                </div>
                <?php
                  // Vérifie demande en attente
                  $enAttente = false;
                  if ($userId) {
                      $chkPend = $pdo->prepare("SELECT id FROM demandes_adhesion WHERE user_id=? AND structure_type='sport' AND structure_id=? AND statut='en_attente'");
                      $chkPend->execute([$userId, $s['id']]);
                      $enAttente = (bool)$chkPend->fetchColumn();
                  }
                ?>
                <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                  <?php if (!$userId): ?>
                    <a href="admin/login.php" class="btn btn--primary btn--sm"><?= htmlspecialchars(corpo_t('sport.btn_join')) ?></a>
                  <?php elseif (isset($mesInscriptions[$s['id']]) && $mesInscriptions[$s['id']] === 'confirme'): ?>
                    <form method="post" style="margin:0">
                      <input type="hidden" name="sport_id" value="<?= $s['id'] ?>">
                      <input type="hidden" name="action"   value="quitter">
                      <button class="btn btn--sm" style="border-color:#e74c3c;color:#e74c3c;background:transparent"><?= htmlspecialchars(corpo_t('sport.btn_leave')) ?></button>
                    </form>
                    <span style="font-size:.7rem;color:#27ae60;font-weight:700"><?= htmlspecialchars(corpo_t('sport.status_member')) ?></span>
                  <?php elseif ($enAttente): ?>
                    <span style="font-size:.7rem;color:#e67e22"><?= htmlspecialchars(corpo_t('sport.status_pending')) ?></span>
                  <?php else: ?>
                    <button class="btn btn--primary btn--sm" onclick="toggleMotiv('motiv-<?= $s['id'] ?>')">
                      <?= $dispo <= 0 && $s['places'] > 0 ? htmlspecialchars(corpo_t('sport.waitlist')) : htmlspecialchars(corpo_t('sport.btn_join')) ?>
                    </button>
                    <div id="motiv-<?= $s['id'] ?>" style="display:none;width:100%;margin-top:.5rem">
                      <form method="post" style="display:flex;flex-direction:column;gap:.4rem">
                        <input type="hidden" name="sport_id" value="<?= $s['id'] ?>">
                        <input type="hidden" name="action"   value="rejoindre">
                        <textarea name="message" placeholder="Dis-nous ta motivation (niveau, expérience…)" rows="2"
                          style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:6px;padding:.4rem;color:#fff;font-size:.78rem;resize:vertical"></textarea>
                        <button class="btn btn--primary btn--sm" style="align-self:flex-start"><?= htmlspecialchars(corpo_t('sport.btn_send_request')) ?></button>
                      </form>
                    </div>
                  <?php endif; ?>
                  <a href="structure.php?sport=<?= htmlspecialchars($s['slug']) ?>" class="btn btn--sm" style="background:rgba(255,255,255,.06);border-color:var(--border)">Voir →</a>
                </div>
              </div>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- RÉSULTATS -->
    <section class="section section--alt" aria-labelledby="resultats-title">
      <div class="container">
        <span class="section-label">Résultats</span>
        <h2 class="section-title section-title--center" id="resultats-title">Derniers matchs</h2>
        <div class="results-grid">
          <?php foreach ($resultats as $r):
            $vic = $r['victoire'];
            $cls = $vic === null ? 'draw' : ($vic ? 'win' : 'loss');
            $label = $vic === null ? 'Nul' : ($vic ? 'Victoire' : 'Défaite');
          ?>
          <div class="result-card result-card--<?= $cls ?>" style="--sport-color: <?= htmlspecialchars($r['couleur']) ?>">
            <div class="result-card__sport"><?= htmlspecialchars($r['sport_icon']) ?> <?= htmlspecialchars($r['sport_nom']) ?></div>
            <div class="result-card__score"><?= htmlspecialchars($r['score']) ?></div>
            <div class="result-card__vs">vs <?= htmlspecialchars($r['adversaire']) ?></div>
            <div class="result-card__outcome result-card__outcome--<?= $cls ?>"><?= $label ?></div>
            <div class="result-card__date"><?= date('d/m/Y', strtotime($r['date'])) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- SPORTS EN ACCÈS LIBRE ──────────────────────────────── -->
    <section class="section" aria-labelledby="indiv-title">
      <div class="container">
        <span class="section-label">Sans inscription</span>
        <h2 class="section-title" id="indiv-title">Sports en accès libre</h2>
        <p class="lead">En dehors des clubs, certaines activités sont accessibles librement sur les campus ou chez des partenaires. Rejoins le groupe WhatsApp pour être informé des créneaux.</p>

        <?php if (empty($libres)): ?>
          <div class="sport-indiv-grid">
            <div class="sport-indiv-card sport-indiv-card--more">
              <span class="sport-indiv-card__icon"></span>
              <div>
                <h3 class="sport-indiv-card__name">Section en cours de construction</h3>
                <p class="sport-indiv-card__info">Les sports en accès libre (ping-pong, badminton, musculation…) seront ajoutés par les BDS à la rentrée 2026–2027.</p>
              </div>
              <a href="mailto:sport.omnes.lyon@gmail.com?subject=Proposition sport individuel"
                 class="btn btn--ghost btn--sm">Proposer un sport</a>
            </div>
          </div>
        <?php else: ?>
          <div class="sport-indiv-grid">
            <?php foreach ($libres as $l): ?>
              <div class="sport-indiv-card">
                <span class="sport-indiv-card__icon" style="color:<?= htmlspecialchars($l['couleur']) ?>">
                  <?= htmlspecialchars($l['icon']) ?>
                </span>
                <div style="flex:1">
                  <h3 class="sport-indiv-card__name"><?= htmlspecialchars($l['nom']) ?></h3>
                  <p class="sport-indiv-card__info"><?= htmlspecialchars($l['description'] ?? '') ?></p>
                  <?php if ($l['infra_partenaire']): ?>
                    <span style="font-size:.72rem;color:var(--purple-light);display:block;margin-top:.3rem">
                      🏢 <?= htmlspecialchars($l['infra_partenaire']) ?>
                    </span>
                  <?php endif; ?>
                  <span style="font-size:.7rem;color:var(--text-muted)"><?= htmlspecialchars($l['campus']) ?></span>
                </div>
                <?php if (!empty($l['lien_acces'])): ?>
                  <a href="<?= htmlspecialchars($l['lien_acces']) ?>" target="_blank" rel="noopener"
                     class="btn btn--sm"
                     style="background:rgba(37,211,102,.12);border-color:rgba(37,211,102,.4);color:#25d366;white-space:nowrap;flex-shrink:0">
                    Rejoindre
                  </a>
                <?php elseif (!empty($l['slug'])): ?>
                  <a href="structure.php?sport=<?= urlencode((string)$l['slug']) ?>"
                     class="btn btn--sm btn--ghost"
                     style="white-space:nowrap;flex-shrink:0"
                     title="Pas de groupe WhatsApp / lien renseigné : horaires et infos sur la fiche du sport.">
                    Voir la fiche
                  </a>
                <?php else: ?>
                  <span class="sport-indiv-card__badge"
                        title="Aucun lien de contact n’a été renseigné par le BDS pour ce sport (ce n’est pas une demande d’inscription en attente).">
                    Pas de lien en ligne
                  </span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>

            <!-- Carte "Proposer" toujours en dernier -->
            <div class="sport-indiv-card sport-indiv-card--more">
              <span class="sport-indiv-card__icon">＋</span>
              <div>
                <h3 class="sport-indiv-card__name">Proposer un sport</h3>
                <p class="sport-indiv-card__info">Une idée d'activité libre sur ton campus ? Contacte l'Omnes Sport.</p>
              </div>
              <a href="mailto:sport.omnes.lyon@gmail.com?subject=Proposition sport individuel"
                 class="btn btn--ghost btn--sm">Écrire</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="cta-section">
      <div class="container">
        <h2 class="cta-section__title">Inscriptions 2026–2027</h2>
        <?php if ($userId): ?>
          <p class="cta-section__sub">Tu es connecté(e) - inscris-toi directement depuis les cards ci-dessus ou retrouve tes sports dans ton espace perso.</p>
          <div class="cta-section__actions">
            <a href="mes-sports.php" class="btn btn--primary btn--lg">🏃 Mes sports</a>
            <a href="mailto:sport.omnes.lyon@gmail.com" class="btn btn--ghost btn--lg">Une question ?</a>
          </div>
        <?php else: ?>
          <p class="cta-section__sub">Connecte-toi pour t'inscrire aux clubs directement depuis la plateforme.</p>
          <div class="cta-section__actions">
            <a href="admin/login.php" class="btn btn--primary btn--lg">Se connecter</a>
            <a href="register.php" class="btn btn--ghost btn--lg">Créer un compte</a>
          </div>
        <?php endif; ?>
      </div>
    </section>

  </main>

<?php
// Partenaires des sports clubs
$partenairesSport = $pdo->query(
    "SELECT p.*, sp.nom AS sport_nom, sp.slug AS sport_slug, sp.icon AS sport_icon
     FROM partenaires p
     JOIN sports sp ON p.structure_type = 'sport' AND sp.id = p.structure_id
     WHERE p.statut = 'publie'
     ORDER BY sp.nom, p.nom LIMIT 30"
)->fetchAll();
?>
<?php if (!empty($partenairesSport)): ?>
<section class="section" style="border-top:1px solid var(--border);padding-top:var(--s10)">
  <div class="container">
    <h2 class="section-title" style="font-size:1.1rem;margin-bottom:var(--s5)">Partenaires des clubs sportifs</h2>
    <div style="display:flex;flex-wrap:wrap;gap:var(--s3)">
      <?php foreach ($partenairesSport as $pt): ?>
        <a href="structure.php?sport=<?= urlencode($pt['sport_slug'] ?? '') ?>"
           title="Partenaire de <?= htmlspecialchars($pt['sport_nom'] ?? '') ?>"
           style="display:flex;align-items:center;gap:.5rem;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-md);padding:.4rem .9rem;text-decoration:none;color:var(--text);font-size:.78rem;transition:border-color var(--ease)"
           class="partner-pill">
          <?php if (!empty($pt['logo'])): ?>
            <img src="<?= htmlspecialchars($pt['logo']) ?>" alt="" style="height:20px;width:auto;object-fit:contain">
          <?php endif; ?>
          <span><?= htmlspecialchars($pt['sport_icon'] ?? '') ?></span>
          <span><?= htmlspecialchars($pt['nom']) ?></span>
          <span style="font-size:.65rem;color:var(--text-muted)"><?= htmlspecialchars($pt['sport_nom'] ?? '') ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<script>
function toggleMotiv(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>
