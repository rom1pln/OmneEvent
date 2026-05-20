<?php
$adminTitle = 'Calendrier scolaire';
$adminPage  = 'calendrier';
require_once '../includes/db.php';
require_once 'includes/admin-header.php';

$flash = '';

// listes de référence (écoles, types, couleurs)
$ECOLES_ALL = ['ECE','ESCE','HEIP','INSEEC Bachelor','INSEEC BBA','INSEEC BTS','INSEEC GE','INSEEC MSc','Sup de Pub'];
$TYPES      = ['vacances','examens','rattrapages','rentree','evenement_academique','autre'];
$TYPES_LABELS = [
    'vacances'            => 'Vacances',
    'examens'             => 'Examens',
    'rattrapages'         => 'Rattrapages',
    'rentree'             => 'Rentrée',
    'evenement_academique'=> 'Événement académique',
    'autre'               => 'Autre',
];
$TYPE_COLORS = [
    'vacances'            => '#3ECF8E',
    'examens'             => '#E52521',
    'rattrapages'         => '#FF9500',
    'rentree'             => '#007179',
    'evenement_academique'=> '#8B2FC9',
    'autre'               => '#888',
];

/* Promotions suggérées (chips cliquables dans le formulaire) */
$PROMOS_SUGGESTIONS = ['B1','B2','B3','M1','M2','1A','2A','3A','4A','5A','BTS1','BTS2','Prépa'];

// permissions
$canEditCalendrier = canManageCalendrier($pdo);
$ecolesGerables    = $canEditCalendrier ? getEcolesCalendrier($pdo, $ECOLES_ALL) : [];

// nettoie la liste de promos saisies
function _normalizePromos(?string $raw): array {
    if (!$raw) return [];
    $parts = preg_split('/[,;]+/u', $raw) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '' || mb_strtolower($p) === 'toutes') continue;
        // borne la longueur pour éviter les abus
        $out[] = mb_substr($p, 0, 20);
    }
    // unique, max 30 promos
    $out = array_values(array_unique($out));
    return array_slice($out, 0, 30);
}

// actions POST (BDE, fédérations et admin corpo seulement)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEditCalendrier) {
        $flash = '<div class="flash flash--err">Seuls les BDE et les Fédérations peuvent gérer le calendrier scolaire.</div>';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $ecole = trim($_POST['ecole'] ?? '');
            if (!in_array($ecole, $ecolesGerables, true) && !isAdminCorpo()) {
                $flash = '<div class="flash flash--err">Tu n\'as pas les droits sur cette école.</div>';
            } else {
                $promos = _normalizePromos($_POST['promotions'] ?? '');
                $pdo->prepare(
                    "INSERT INTO calendrier_scolaire (ecole, type, titre, date_debut, date_fin, notes, promotions, auteur_id)
                     VALUES (?,?,?,?,?,?,?,?)"
                )->execute([
                    $ecole,
                    in_array($_POST['type'] ?? '', $TYPES) ? $_POST['type'] : 'autre',
                    trim($_POST['titre'] ?? ''),
                    $_POST['date_debut'] ?? '',
                    trim($_POST['date_fin'] ?? '') ?: null,
                    trim($_POST['notes'] ?? '') ?: null,
                    $promos ? json_encode(array_values($promos), JSON_UNESCAPED_UNICODE) : null,
                    currentUserId(),
                ]);
                $flash = '<div class="flash flash--ok">Période ajoutée.</div>';
            }
        }

        if ($action === 'delete' && !empty($_POST['id'])) {
            $id  = (int)$_POST['id'];
            $row = $pdo->prepare("SELECT ecole FROM calendrier_scolaire WHERE id=?");
            $row->execute([$id]);
            $entry = $row->fetch();
            if ($entry && (isAdminCorpo() || in_array($entry['ecole'], $ecolesGerables, true))) {
                $pdo->prepare("DELETE FROM calendrier_scolaire WHERE id=?")->execute([$id]);
                $flash = '<div class="flash flash--ok">Période supprimée.</div>';
            } else {
                $flash = '<div class="flash flash--err">Action non autorisée.</div>';
            }
        }

        if ($action === 'update' && !empty($_POST['id'])) {
            $id    = (int)$_POST['id'];
            $ecole = trim($_POST['ecole'] ?? '');
            if (!in_array($ecole, $ecolesGerables, true) && !isAdminCorpo()) {
                $flash = '<div class="flash flash--err">Non autorisé.</div>';
            } else {
                $promos = _normalizePromos($_POST['promotions'] ?? '');
                $pdo->prepare(
                    "UPDATE calendrier_scolaire SET ecole=?,type=?,titre=?,date_debut=?,date_fin=?,notes=?,promotions=? WHERE id=?"
                )->execute([
                    $ecole,
                    in_array($_POST['type'] ?? '', $TYPES) ? $_POST['type'] : 'autre',
                    trim($_POST['titre'] ?? ''),
                    $_POST['date_debut'] ?? '',
                    trim($_POST['date_fin'] ?? '') ?: null,
                    trim($_POST['notes'] ?? '') ?: null,
                    $promos ? json_encode(array_values($promos), JSON_UNESCAPED_UNICODE) : null,
                    $id,
                ]);
                $flash = '<div class="flash flash--ok">Période mise à jour.</div>';
            }
        }
    }
}

// lecture - tout le monde peut voir le calendrier
$entries = $pdo->query("SELECT * FROM calendrier_scolaire ORDER BY date_debut ASC")->fetchAll();

/* Prépare le payload JSON pour le calendrier interactif (côté JS) */
$jsEntries = [];
$promosFromEntries = [];
foreach ($entries as &$en) {
    $promos = [];
    if (!empty($en['promotions'])) {
        $decoded = json_decode($en['promotions'], true);
        if (is_array($decoded)) $promos = array_values(array_filter($decoded, 'is_string'));
    }
    $en['promotions_arr'] = $promos;
    foreach ($promos as $p) $promosFromEntries[$p] = true;

    $jsEntries[] = [
        'id'         => (int)$en['id'],
        'ecole'      => $en['ecole'],
        'type'       => $en['type'],
        'type_label' => $TYPES_LABELS[$en['type']] ?? $en['type'],
        'color'      => $TYPE_COLORS[$en['type']] ?? '#888',
        'titre'      => $en['titre'],
        'date_debut' => $en['date_debut'],
        'date_fin'   => $en['date_fin'] ?: $en['date_debut'],
        'notes'      => $en['notes'] ?: '',
        'promotions' => $promos,
    ];
}
unset($en);

/* Liste agrégée des promos disponibles (suggestions + existantes en base + users) */
try {
    $stmt = $pdo->query("SELECT DISTINCT promotion FROM users WHERE promotion IS NOT NULL AND promotion <> ''");
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $p) {
        $p = trim((string)$p);
        if ($p !== '') $promosFromEntries[$p] = true;
    }
} catch (Throwable $e) { /* ignore */ }

$promosFilter = array_merge($PROMOS_SUGGESTIONS, array_keys($promosFromEntries));
$promosFilter = array_values(array_unique(array_map('strval', $promosFilter)));
sort($promosFilter, SORT_NATURAL | SORT_FLAG_CASE);
?>

<h1 class="admin-page-title">Calendrier scolaire</h1>

<?php if ($canEditCalendrier): ?>
  <div class="flash flash--info">
    <strong>Mode édition</strong> - tu peux ajouter, modifier ou supprimer les périodes
    de <?= htmlspecialchars(implode(', ', $ecolesGerables)) ?: 'toutes les écoles' ?>.
  </div>
<?php else: ?>
  <div class="flash flash--info">
    <strong>Consultation</strong> - seuls les BDE et les Fédérations peuvent éditer le calendrier scolaire.
  </div>
<?php endif; ?>

<?= $flash ?>

<!-- =================== CALENDRIER INTERACTIF =================== -->
<div class="admin-card adcal-card">
  <div class="adcal-toolbar">
    <div class="adcal-nav">
      <button type="button" class="btn btn--sm" id="cal-prev" aria-label="Mois précédent">←</button>
      <button type="button" class="btn btn--sm" id="cal-today">Aujourd'hui</button>
      <button type="button" class="btn btn--sm" id="cal-next" aria-label="Mois suivant">→</button>
      <h2 class="adcal-title" id="cal-title" aria-live="polite"></h2>
    </div>
    <div class="adcal-filters">
      <label class="adcal-filter">
        <span>École</span>
        <select id="cal-filter-ecole">
          <option value="">Toutes</option>
          <?php foreach ($ECOLES_ALL as $e): ?>
            <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
          <?php endforeach; ?>
          <option value="Tous">Tous (toutes écoles)</option>
        </select>
      </label>
      <label class="adcal-filter">
        <span>Promo</span>
        <select id="cal-filter-promo">
          <option value="">Toutes</option>
          <?php foreach ($promosFilter as $p): ?>
            <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="adcal-filter">
        <span>Type</span>
        <select id="cal-filter-type">
          <option value="">Tous</option>
          <?php foreach ($TYPES as $t): ?>
            <option value="<?= $t ?>"><?= htmlspecialchars($TYPES_LABELS[$t]) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="button" class="btn btn--sm" id="cal-reset" style="background:var(--surface);border-color:var(--border)">Réinitialiser</button>
    </div>
  </div>

  <div class="adcal-legend" aria-label="Légende">
    <?php foreach ($TYPES as $t): ?>
      <span class="adcal-legend-item">
        <span class="adcal-dot" style="background:<?= $TYPE_COLORS[$t] ?>"></span><?= htmlspecialchars($TYPES_LABELS[$t]) ?>
      </span>
    <?php endforeach; ?>
  </div>

  <div class="adcal-weekdays" aria-hidden="true">
    <div>Lun</div><div>Mar</div><div>Mer</div><div>Jeu</div><div>Ven</div><div>Sam</div><div>Dim</div>
  </div>
  <div class="adcal-grid" id="cal-grid" role="grid" aria-labelledby="cal-title"></div>

  <div class="adcal-empty" id="cal-empty" hidden>
    <p style="color:var(--text-muted);text-align:center;padding:var(--s4)">Aucune période ne correspond aux filtres.</p>
  </div>
</div>

<!-- Détail du jour (panneau qui s'ouvre au clic) -->
<div class="adcal-day-detail admin-card" id="cal-day-detail" hidden>
  <div class="adcal-day-detail__head">
    <h2 id="cal-day-detail-title"></h2>
    <button type="button" class="btn btn--sm" id="cal-day-close" style="background:var(--surface);border-color:var(--border)">Fermer</button>
  </div>
  <div id="cal-day-detail-list"></div>
</div>

<?php if ($canEditCalendrier && !empty($ecolesGerables)): ?>
<!-- =================== FORMULAIRE AJOUT =================== -->
<div class="admin-card">
  <h2>Ajouter une période</h2>
  <form method="post" class="admin-form" id="cal-add-form">
    <input type="hidden" name="action" value="add">
    <div class="form-row">
      <div class="form-col">
        <label>École</label>
        <select name="ecole">
          <?php foreach ($ecolesGerables as $e): ?>
            <option><?= htmlspecialchars($e) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-col">
        <label>Type</label>
        <select name="type">
          <?php foreach ($TYPES as $t): ?>
            <option value="<?= $t ?>"><?= htmlspecialchars($TYPES_LABELS[$t]) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-col" style="flex:2">
        <label>Titre</label>
        <input type="text" name="titre" placeholder="ex: Vacances de Noël, Partiels S1…" required>
      </div>
    </div>
    <div class="form-row">
      <div class="form-col"><label>Date début</label><input type="date" name="date_debut" required></div>
      <div class="form-col"><label>Date fin <small style="color:var(--text-muted)">(opt.)</small></label><input type="date" name="date_fin"></div>
      <div class="form-col" style="flex:2"><label>Notes <small style="color:var(--text-muted)">(opt.)</small></label><input type="text" name="notes" placeholder="Précisions…"></div>
    </div>
    <div class="form-row">
      <div class="form-col" style="flex:3">
        <label>Promotions concernées <small style="color:var(--text-muted)">(laisser vide = toutes les promos de l'école)</small></label>
        <input type="text" name="promotions" data-promo-input
               placeholder="ex: B1, B2, M1"
               list="cal-promos-datalist">
        <div class="adcal-promo-chips" data-promo-chips>
          <?php foreach ($PROMOS_SUGGESTIONS as $p): ?>
            <button type="button" class="adcal-promo-chip" data-promo="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <button type="submit" class="btn btn--primary">Ajouter →</button>
  </form>
</div>
<?php endif; ?>

<!-- Datalist commun (suggestions de promos) -->
<datalist id="cal-promos-datalist">
  <?php foreach ($promosFilter as $p): ?>
    <option value="<?= htmlspecialchars($p) ?>">
  <?php endforeach; ?>
</datalist>

<!-- =================== TABLEAU LISTE =================== -->
<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead>
      <tr><th>École</th><th>Type</th><th>Titre</th><th>Début</th><th>Fin</th><th>Promos</th><th>Notes</th><?php if ($canEditCalendrier): ?><th>Actions</th><?php endif; ?></tr>
    </thead>
    <tbody>
      <?php if (empty($entries)): ?>
        <tr><td colspan="<?= $canEditCalendrier ? 8 : 7 ?>" style="text-align:center;color:var(--text-muted);padding:var(--s6)">Aucune période enregistrée.</td></tr>
      <?php endif; ?>
      <?php foreach ($entries as $en):
        $userCanEditEntry = $canEditCalendrier && (isAdminCorpo() || in_array($en['ecole'], $ecolesGerables, true));
        $promoStr = !empty($en['promotions_arr']) ? implode(', ', $en['promotions_arr']) : '';
      ?>
        <tr id="row-cal-<?= $en['id'] ?>">
          <td><strong><?= htmlspecialchars($en['ecole']) ?></strong></td>
          <td>
            <span class="adcal-type-badge" style="--c:<?= $TYPE_COLORS[$en['type']] ?? '#888' ?>">
              <?= htmlspecialchars($TYPES_LABELS[$en['type']] ?? $en['type']) ?>
            </span>
          </td>
          <td><strong><?= htmlspecialchars($en['titre']) ?></strong></td>
          <td><?= date('d/m/Y', strtotime($en['date_debut'])) ?></td>
          <td><?= $en['date_fin'] ? date('d/m/Y', strtotime($en['date_fin'])) : '<span style="color:var(--text-muted)">-</span>' ?></td>
          <td style="font-size:.78rem">
            <?php if (!empty($en['promotions_arr'])): ?>
              <?php foreach ($en['promotions_arr'] as $p): ?>
                <span class="adcal-promo-tag"><?= htmlspecialchars($p) ?></span>
              <?php endforeach; ?>
            <?php else: ?>
              <span style="color:var(--text-muted)">Toutes</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.78rem;color:var(--text-muted)"><?= htmlspecialchars($en['notes'] ?? '') ?></td>
          <?php if ($canEditCalendrier): ?>
          <td>
            <div class="actions">
              <?php if ($userCanEditEntry): ?>
                <button class="btn btn--sm" onclick="toggleEdit('cal-<?= $en['id'] ?>')"
                        style="background:var(--surface);border-color:var(--border)">Modifier</button>
                <form method="post" style="display:inline" onsubmit="return confirm('Supprimer cette période ?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $en['id'] ?>">
                  <button class="btn btn--sm btn--danger">Suppr.</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
          <?php endif; ?>
        </tr>
        <?php if ($userCanEditEntry): ?>
        <tr id="edit-cal-<?= $en['id'] ?>" style="display:none">
          <td colspan="8" style="background:rgba(255,255,255,.02);padding:var(--s5)">
            <form method="post" class="admin-form" style="margin-top:0">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= $en['id'] ?>">
              <div class="form-row">
                <div class="form-col">
                  <label>École</label>
                  <select name="ecole">
                    <?php foreach ($ecolesGerables as $e): ?>
                      <option<?= $en['ecole']===$e?' selected':'' ?>><?= htmlspecialchars($e) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-col">
                  <label>Type</label>
                  <select name="type">
                    <?php foreach ($TYPES as $t): ?>
                      <option value="<?= $t ?>"<?= $en['type']===$t?' selected':'' ?>><?= htmlspecialchars($TYPES_LABELS[$t]) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-col" style="flex:2">
                  <label>Titre</label>
                  <input type="text" name="titre" value="<?= htmlspecialchars($en['titre']) ?>" required>
                </div>
              </div>
              <div class="form-row">
                <div class="form-col"><label>Date début</label><input type="date" name="date_debut" value="<?= $en['date_debut'] ?>" required></div>
                <div class="form-col"><label>Date fin</label><input type="date" name="date_fin" value="<?= $en['date_fin'] ?? '' ?>"></div>
                <div class="form-col" style="flex:2"><label>Notes</label><input type="text" name="notes" value="<?= htmlspecialchars($en['notes'] ?? '') ?>"></div>
              </div>
              <div class="form-row">
                <div class="form-col" style="flex:3">
                  <label>Promotions concernées <small style="color:var(--text-muted)">(vide = toutes)</small></label>
                  <input type="text" name="promotions" data-promo-input
                         value="<?= htmlspecialchars($promoStr) ?>"
                         placeholder="ex: B1, B2, M1"
                         list="cal-promos-datalist">
                  <div class="adcal-promo-chips" data-promo-chips>
                    <?php foreach ($PROMOS_SUGGESTIONS as $p): ?>
                      <button type="button" class="adcal-promo-chip" data-promo="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></button>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
              <button type="submit" class="btn btn--primary">Enregistrer</button>
              <button type="button" class="btn" onclick="toggleEdit('cal-<?= $en['id'] ?>')"
                      style="background:var(--surface);border-color:var(--border)">Annuler</button>
            </form>
          </td>
        </tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script id="cal-data" type="application/json"><?= json_encode([
    'entries' => $jsEntries,
    'today'   => date('Y-m-d'),
    'months'  => ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'],
    'canEdit' => $canEditCalendrier,
], JSON_UNESCAPED_UNICODE) ?></script>

<script>
// affiche/masque la ligne d'édition inline d'un événement
function toggleEdit(id) {
  const row = document.getElementById('edit-' + id);
  if (!row) return;
  const isHidden = row.style.display === 'none';
  document.querySelectorAll('tr[id^="edit-cal-"]').forEach(r => r.style.display = 'none');
  row.style.display = isHidden ? '' : 'none';
  if (isHidden) row.scrollIntoView({ behavior:'smooth', block:'nearest' });
}

// chips de promo : clic pour ajouter/retirer dans le champ texte
document.querySelectorAll('[data-promo-chips]').forEach(box => {
  const input = box.parentElement.querySelector('[data-promo-input]');
  if (!input) return;

  function getPromos() {
    return input.value.split(/[,;]+/).map(s => s.trim()).filter(Boolean);
  }
  function setPromos(arr) {
    input.value = arr.join(', ');
    syncChips();
  }
  function syncChips() {
    const current = getPromos().map(s => s.toLowerCase());
    box.querySelectorAll('.adcal-promo-chip').forEach(btn => {
      btn.classList.toggle('is-active', current.includes(btn.dataset.promo.toLowerCase()));
    });
  }
  box.querySelectorAll('.adcal-promo-chip').forEach(btn => {
    btn.addEventListener('click', () => {
      const p = btn.dataset.promo;
      const cur = getPromos();
      const idx = cur.findIndex(x => x.toLowerCase() === p.toLowerCase());
      if (idx >= 0) cur.splice(idx, 1); else cur.push(p);
      setPromos(cur);
    });
  });
  input.addEventListener('input', syncChips);
  syncChips();
});

// calendrier interactif (navigation mois / affichage événements)
(function () {
  const dataEl = document.getElementById('cal-data');
  if (!dataEl) return;
  const data = JSON.parse(dataEl.textContent);
  const ENTRIES = data.entries;
  const MONTHS  = data.months;
  const TODAY   = new Date(data.today + 'T00:00:00');

  const grid     = document.getElementById('cal-grid');
  const titleEl  = document.getElementById('cal-title');
  const emptyEl  = document.getElementById('cal-empty');
  const fEcole   = document.getElementById('cal-filter-ecole');
  const fPromo   = document.getElementById('cal-filter-promo');
  const fType    = document.getElementById('cal-filter-type');
  const detail   = document.getElementById('cal-day-detail');
  const detailT  = document.getElementById('cal-day-detail-title');
  const detailL  = document.getElementById('cal-day-detail-list');

  let cursor = new Date(TODAY.getFullYear(), TODAY.getMonth(), 1);

  function ymd(d) {
    const y = d.getFullYear(), m = String(d.getMonth() + 1).padStart(2, '0'), j = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${j}`;
  }
  function parseYMD(s) {
    const [y, m, d] = s.split('-').map(Number);
    return new Date(y, m - 1, d);
  }
  function inRange(date, start, end) {
    const t = date.getTime();
    return t >= start.getTime() && t <= end.getTime();
  }
  function matchFilters(e) {
    const fe = fEcole.value, fp = fPromo.value, ft = fType.value;
    if (fe && e.ecole !== fe) return false;
    if (ft && e.type !== ft) return false;
    if (fp) {
      // Si l'événement n'a pas de promo listée → considère qu'il s'applique à toutes
      if (e.promotions && e.promotions.length > 0) {
        const has = e.promotions.some(p => p.toLowerCase() === fp.toLowerCase());
        if (!has) return false;
      }
    }
    return true;
  }
  function entriesForDay(d) {
    const ds = ymd(d);
    return ENTRIES.filter(e => matchFilters(e) && ds >= e.date_debut && ds <= e.date_fin);
  }
  function totalVisible() {
    return ENTRIES.filter(matchFilters).length;
  }

  function render() {
    grid.innerHTML = '';
    const y = cursor.getFullYear(), m = cursor.getMonth();
    titleEl.textContent = `${MONTHS[m]} ${y}`;

    // Premier jour de la semaine (lundi=0)
    const first = new Date(y, m, 1);
    let startDow = (first.getDay() + 6) % 7;
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const daysInPrev  = new Date(y, m, 0).getDate();

    // Construit 6 semaines * 7 jours = 42 cellules
    const cells = [];
    for (let i = 0; i < startDow; i++) {
      cells.push({ date: new Date(y, m - 1, daysInPrev - startDow + 1 + i), other: true });
    }
    for (let d = 1; d <= daysInMonth; d++) {
      cells.push({ date: new Date(y, m, d), other: false });
    }
    while (cells.length < 42) {
      const last = cells[cells.length - 1].date;
      cells.push({ date: new Date(last.getFullYear(), last.getMonth(), last.getDate() + 1), other: true });
    }

    cells.forEach(({ date, other }) => {
      const cell = document.createElement('div');
      cell.className = 'adcal-cell' + (other ? ' is-other' : '');
      if (ymd(date) === ymd(TODAY)) cell.classList.add('is-today');
      cell.setAttribute('role', 'gridcell');
      cell.dataset.date = ymd(date);

      const num = document.createElement('div');
      num.className = 'adcal-cell__num';
      num.textContent = date.getDate();
      cell.appendChild(num);

      const evs = entriesForDay(date);
      if (evs.length > 0) {
        const list = document.createElement('div');
        list.className = 'adcal-cell__events';
        const max = 3;
        evs.slice(0, max).forEach(e => {
          const eDeb = parseYMD(e.date_debut);
          const eFin = parseYMD(e.date_fin);
          const isStart = ymd(date) === e.date_debut;
          const isEnd   = ymd(date) === e.date_fin;
          const isMulti = e.date_debut !== e.date_fin;
          const bar = document.createElement('div');
          bar.className = 'adcal-event'
            + (isMulti ? ' is-multi' : '')
            + (isStart && isMulti ? ' is-start' : '')
            + (isEnd   && isMulti ? ' is-end'   : '');
          bar.style.setProperty('--c', e.color);
          bar.textContent = e.titre;
          bar.title = `${e.titre} - ${e.ecole}${e.promotions.length ? ' (' + e.promotions.join(', ') + ')' : ''}`;
          list.appendChild(bar);
        });
        if (evs.length > max) {
          const more = document.createElement('div');
          more.className = 'adcal-event-more';
          more.textContent = `+${evs.length - max} autre${evs.length - max > 1 ? 's' : ''}`;
          list.appendChild(more);
        }
        cell.appendChild(list);
      }

      cell.addEventListener('click', () => openDayDetail(date, evs));
      grid.appendChild(cell);
    });

    emptyEl.hidden = totalVisible() > 0;
  }

  function openDayDetail(date, evs) {
    if (!evs.length) { detail.hidden = true; return; }
    detail.hidden = false;
    const dStr = date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    detailT.textContent = dStr.charAt(0).toUpperCase() + dStr.slice(1);
    detailL.innerHTML = '';
    evs.forEach(e => {
      const promos = e.promotions.length
        ? `<span class="adcal-day-promos">${e.promotions.map(p => `<span class="adcal-promo-tag">${escapeHtml(p)}</span>`).join('')}</span>`
        : '<span style="color:var(--text-muted);font-size:.8rem">Toutes les promos</span>';
      const fin = e.date_fin && e.date_fin !== e.date_debut
        ? ` → ${formatDateFR(e.date_fin)}`
        : '';
      const editBtn = data.canEdit
        ? `<button type="button" class="btn btn--sm" onclick="toggleEdit('cal-${e.id}')" style="background:var(--surface);border-color:var(--border)">Modifier</button>`
        : '';
      const card = document.createElement('div');
      card.className = 'adcal-day-item';
      card.innerHTML = `
        <span class="adcal-type-badge" style="--c:${e.color}">${escapeHtml(e.type_label)}</span>
        <div class="adcal-day-item__body">
          <div class="adcal-day-item__title">${escapeHtml(e.titre)} <small style="color:var(--text-muted)">- ${escapeHtml(e.ecole)}</small></div>
          <div class="adcal-day-item__meta">${formatDateFR(e.date_debut)}${fin} ${promos}</div>
          ${e.notes ? `<div class="adcal-day-item__notes">${escapeHtml(e.notes)}</div>` : ''}
        </div>
        <div class="adcal-day-item__actions">${editBtn}</div>
      `;
      detailL.appendChild(card);
    });
    detail.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }
  function formatDateFR(s) {
    const [y, m, d] = s.split('-');
    return `${d}/${m}/${y}`;
  }

  document.getElementById('cal-prev').addEventListener('click', () => { cursor.setMonth(cursor.getMonth() - 1); render(); });
  document.getElementById('cal-next').addEventListener('click', () => { cursor.setMonth(cursor.getMonth() + 1); render(); });
  document.getElementById('cal-today').addEventListener('click', () => { cursor = new Date(TODAY.getFullYear(), TODAY.getMonth(), 1); render(); });
  document.getElementById('cal-reset').addEventListener('click', () => {
    fEcole.value = ''; fPromo.value = ''; fType.value = '';
    detail.hidden = true;
    render();
  });
  document.getElementById('cal-day-close').addEventListener('click', () => { detail.hidden = true; });

  [fEcole, fPromo, fType].forEach(s => s.addEventListener('change', () => { detail.hidden = true; render(); }));

  render();
})();
</script>

<?php require_once 'includes/admin-footer.php'; ?>
