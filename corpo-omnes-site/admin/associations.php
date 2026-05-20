<?php
$adminTitle = 'Associations';
$adminPage  = 'associations';
require_once '../includes/db.php';
require_once '../includes/upload-logo.php';
require_once '../includes/associations-activity.php';
require_once 'includes/admin-header.php';

$mandatColsReady = asso_has_mandat_columns($pdo);

$flash = '';

function makeAssoSlug(string $nom, PDO $pdo, ?int $excludeId = null): string {
    $base = preg_replace('/[^a-z0-9]+/', '-',
        strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($nom)))
    );
    $base = trim($base, '-') ?: 'asso';

    $slug = $base;
    $i    = 2;
    while (true) {
        $q = $pdo->prepare(
            "SELECT id FROM associations WHERE slug = ?" .
            ($excludeId ? " AND id != $excludeId" : "")
        );
        $q->execute([$slug]);
        if (!$q->fetchColumn()) break;
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'sync_parents' && isAdminCorpo()) {
        $ecoles = $pdo->query(
            "SELECT DISTINCT TRIM(ecole) AS ecole FROM associations
             WHERE ecole IS NOT NULL AND TRIM(ecole) <> '' AND TRIM(ecole) <> 'Toutes'
             ORDER BY ecole"
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $total = 0;
        foreach ($ecoles as $ec) {
            $total += asso_sync_parents_for_ecole($pdo, (string)$ec);
        }
        $flash = '<div class="flash flash--ok">' . (int)$total
               . ' association(s) : rattachement parent recalculé selon les mandats BDE / EchoFed actifs.</div>';
    }

    if ($action === 'add') {
        if (!canCreateAssociation($pdo)) {
            $flash = '<div class="flash flash--err">Tu n\'as pas le droit de créer une association.</div>';
        } else {
            $typeIn = trim($_POST['type'] ?? '');
            $reservedTypes = ['BDE', 'BDS', 'Corpo'];
            if (!isAdminCorpo() && in_array($typeIn, $reservedTypes, true)) {
                $flash = '<div class="flash flash--err">Les types BDE, BDS et Corpo sont réservés à l\'administration Corpo.</div>';
            } else {
                $abortAdd = false;
                $nom     = trim($_POST['nom'] ?? '');
                $slugAdd = makeAssoSlug($nom, $pdo);
                $logo    = uploadLogo('assos', 'logo_file', 'logo_url');

                $ecolesEligibles = $_POST['ecoles_eligibles'] ?? [];
                $ecolesEligibles = is_array($ecolesEligibles) ? array_values(array_filter(array_map('trim', $ecolesEligibles))) : [];
                $ecolesEligiblesJson = empty($ecolesEligibles) ? null : json_encode($ecolesEligibles, JSON_UNESCAPED_UNICODE);

                $ecole = trim($_POST['ecole'] ?? '');

                if (!isAdminCorpo()) {
                    $bdeAdminId = null;
                    foreach (getMemberships() as $m) {
                        if ($m['role'] === 'admin' && $m['type'] === 'bde') {
                            $bdeAdminId = (int)$m['id'];
                            break;
                        }
                    }
                    if ($bdeAdminId) {
                        $qEc = $pdo->prepare('SELECT ecole FROM associations WHERE id = ? LIMIT 1');
                        $qEc->execute([$bdeAdminId]);
                        $forced = trim((string)$qEc->fetchColumn());
                        if ($forced !== '') {
                            $ecole = $forced;
                        }
                    } else {
                        $refEcoles = $pdo->query(
                            "SELECT DISTINCT TRIM(ecole) FROM associations
                             WHERE ecole IS NOT NULL AND TRIM(ecole) <> '' AND TRIM(ecole) <> 'Toutes'
                             ORDER BY ecole"
                        );
                        $allEcoles = $refEcoles ? array_values(array_filter(array_map('trim', $refEcoles->fetchAll(PDO::FETCH_COLUMN) ?: []))) : [];
                        $managedEcoles = getEcolesCalendrier($pdo, $allEcoles);
                        if ($ecole !== '' && $ecole !== 'Toutes' && !in_array($ecole, $managedEcoles, true)) {
                            $flash = '<div class="flash flash--err">École hors de ton périmètre (fédération).</div>';
                            $abortAdd = true;
                        }
                    }
                }

                $parentBde = empty($abortAdd)
                    ? asso_resolve_parent_bde_id($pdo, $ecole, $typeIn)
                    : null;

                if (empty($abortAdd) && $mandatColsReady) {
                    $rawDebut = trim((string)($_POST['date_debut_mandat'] ?? ''));
                    $rawFin   = trim((string)($_POST['date_fin_mandat'] ?? ''));
                    $dateDebut = $rawDebut === '' ? null : asso_normalize_mandat_date($rawDebut);
                    $dateFin   = $rawFin === '' ? null : asso_normalize_mandat_date($rawFin);
                    if ($rawDebut !== '' && $dateDebut === null) {
                        $flash = '<div class="flash flash--err">Date de début invalide — utilise le format jj/mm/aaaa.</div>';
                        $abortAdd = true;
                    } elseif ($rawFin !== '' && $dateFin === null) {
                        $flash = '<div class="flash flash--err">Date de fin invalide — utilise le format jj/mm/aaaa.</div>';
                        $abortAdd = true;
                    } elseif ($dateDebut && $dateFin && $dateDebut > $dateFin) {
                        $flash = '<div class="flash flash--err">La date de fin doit être postérieure ou égale à la date de début.</div>';
                        $abortAdd = true;
                    }
                }

                if (empty($abortAdd)) {
                    try {
                        if ($mandatColsReady) {
                            $pdo->prepare(
                                "INSERT INTO associations
                                   (slug,nom,ecole,type,campus,description,contact,instagram,ouverte_a_tous,color,logo,ecoles_eligibles,parent_bde_id,date_debut_mandat,date_fin_mandat)
                                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                            )->execute([
                                $slugAdd,
                                $nom,
                                $ecole,
                                $typeIn,
                                trim($_POST['campus']      ?? ''),
                                trim($_POST['description'] ?? ''),
                                trim($_POST['contact']     ?? ''),
                                trim($_POST['instagram']   ?? ''),
                                isset($_POST['ouverte_a_tous']) ? 1 : 0,
                                trim($_POST['color']       ?? '#5D0282'),
                                $logo,
                                $ecolesEligiblesJson,
                                $parentBde,
                                $dateDebut,
                                $dateFin,
                            ]);
                        } else {
                            $pdo->prepare(
                                "INSERT INTO associations
                                   (slug,nom,ecole,type,campus,description,contact,instagram,ouverte_a_tous,color,logo,ecoles_eligibles,parent_bde_id)
                                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
                            )->execute([
                                $slugAdd,
                                $nom,
                                $ecole,
                                $typeIn,
                                trim($_POST['campus']      ?? ''),
                                trim($_POST['description'] ?? ''),
                                trim($_POST['contact']     ?? ''),
                                trim($_POST['instagram']   ?? ''),
                                isset($_POST['ouverte_a_tous']) ? 1 : 0,
                                trim($_POST['color']       ?? '#5D0282'),
                                $logo,
                                $ecolesEligiblesJson,
                                $parentBde,
                            ]);
                        }
                        asso_sync_parents_after_structure_change($pdo, $typeIn, $ecole);
                        $flash = '<div class="flash flash--ok">Association ajoutée.</div>';
                    } catch (Throwable $e) {
                        $flash = '<div class="flash flash--err">Erreur SQL à l\'ajout : '
                               . htmlspecialchars($e->getMessage())
                               . '<br><small>→ ouvre <a href="migrate.php" style="color:inherit;text-decoration:underline">admin/migrate.php</a> pour appliquer les migrations manquantes.</small></div>';
                    }
                }
            }
        }
    }

    if ($action === 'update' && !empty($_POST['id'])) {
        $id  = (int)$_POST['id'];
        if (!canManageAsso($id, $pdo)) {
            $flash = '<div class="flash flash--err">Tu n\'as pas le droit de modifier cette association.</div>';
        } else {
            $typeIn = trim($_POST['type'] ?? '');
            $reservedTypes = ['BDE', 'BDS', 'Corpo'];
            if (!isAdminCorpo() && in_array($typeIn, $reservedTypes, true)) {
                $flash = '<div class="flash flash--err">Les types BDE, BDS et Corpo sont réservés à l\'administration Corpo.</div>';
            } else {
                $nom = trim($_POST['nom'] ?? '');

                $old  = $pdo->prepare('SELECT slug, logo, ecole, type FROM associations WHERE id=?');
                $old->execute([$id]);
                $prev = $old->fetch();
                $oldEcole = trim((string)($prev['ecole'] ?? ''));
                $ecoleUpd = trim($_POST['ecole'] ?? '');
                $parentBdeUpd = asso_resolve_parent_bde_id($pdo, $ecoleUpd, $typeIn, $id);

                $prevNomSlug = preg_replace('/[^a-z0-9]+/', '-',
                    strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nom))
                );
                $slugUpd = str_starts_with($prev['slug'] ?? '', $prevNomSlug)
                    ? ($prev['slug'] ?? makeAssoSlug($nom, $pdo, $id))
                    : makeAssoSlug($nom, $pdo, $id);

                $logo = uploadLogo('assos', 'logo_file', 'logo_url', $prev['logo'] ?? null);

                $ecolesEligibles = $_POST['ecoles_eligibles'] ?? [];
                $ecolesEligibles = is_array($ecolesEligibles) ? array_values(array_filter(array_map('trim', $ecolesEligibles))) : [];
                $ecolesEligiblesJson = empty($ecolesEligibles) ? null : json_encode($ecolesEligibles, JSON_UNESCAPED_UNICODE);

                $dateDebut = null;
                $dateFin   = null;
                if ($mandatColsReady) {
                    $rawDebut = trim((string)($_POST['date_debut_mandat'] ?? ''));
                    $rawFin   = trim((string)($_POST['date_fin_mandat'] ?? ''));
                    $dateDebut = $rawDebut === '' ? null : asso_normalize_mandat_date($rawDebut);
                    $dateFin   = $rawFin === '' ? null : asso_normalize_mandat_date($rawFin);
                }
                if ($mandatColsReady && !empty(trim((string)($_POST['date_debut_mandat'] ?? ''))) && $dateDebut === null) {
                    $flash = '<div class="flash flash--err">Date de début invalide — utilise le format jj/mm/aaaa.</div>';
                } elseif ($mandatColsReady && !empty(trim((string)($_POST['date_fin_mandat'] ?? ''))) && $dateFin === null) {
                    $flash = '<div class="flash flash--err">Date de fin invalide — utilise le format jj/mm/aaaa.</div>';
                } elseif ($dateDebut && $dateFin && $dateDebut > $dateFin) {
                    $flash = '<div class="flash flash--err">La date de fin doit être postérieure ou égale à la date de début.</div>';
                } else {
                try {
                    if ($mandatColsReady) {
                        $pdo->prepare(
                            "UPDATE associations
                             SET slug=?,nom=?,ecole=?,type=?,campus=?,description=?,contact=?,instagram=?,ouverte_a_tous=?,color=?,logo=?,ecoles_eligibles=?,parent_bde_id=?,date_debut_mandat=?,date_fin_mandat=?
                             WHERE id=?"
                        )->execute([
                            $slugUpd,
                            $nom,
                            $ecoleUpd,
                            $typeIn,
                            trim($_POST['campus']      ?? ''),
                            trim($_POST['description'] ?? ''),
                            trim($_POST['contact']     ?? ''),
                            trim($_POST['instagram']   ?? ''),
                            isset($_POST['ouverte_a_tous']) ? 1 : 0,
                            trim($_POST['color']       ?? '#5D0282'),
                            $logo,
                            $ecolesEligiblesJson,
                            $parentBdeUpd,
                            $dateDebut,
                            $dateFin,
                            $id,
                        ]);
                    } else {
                        $pdo->prepare(
                            "UPDATE associations
                             SET slug=?,nom=?,ecole=?,type=?,campus=?,description=?,contact=?,instagram=?,ouverte_a_tous=?,color=?,logo=?,ecoles_eligibles=?,parent_bde_id=?
                             WHERE id=?"
                        )->execute([
                            $slugUpd,
                            $nom,
                            $ecoleUpd,
                            $typeIn,
                            trim($_POST['campus']      ?? ''),
                            trim($_POST['description'] ?? ''),
                            trim($_POST['contact']     ?? ''),
                            trim($_POST['instagram']   ?? ''),
                            isset($_POST['ouverte_a_tous']) ? 1 : 0,
                            trim($_POST['color']       ?? '#5D0282'),
                            $logo,
                            $ecolesEligiblesJson,
                            $parentBdeUpd,
                            $id,
                        ]);
                    }
                    asso_sync_parents_after_structure_change($pdo, $typeIn, $ecoleUpd, $oldEcole);
                    $flash = '<div class="flash flash--ok">Association mise à jour.</div>';
                } catch (Throwable $e) {
                    $flash = '<div class="flash flash--err">Erreur SQL à la modification : '
                           . htmlspecialchars($e->getMessage())
                           . '<br><small>→ ouvre <a href="migrate.php" style="color:inherit;text-decoration:underline">admin/migrate.php</a> pour appliquer les migrations manquantes.</small></div>';
                }
                }
            }
        }
    }

    if ($action === 'delete' && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        if (!canManageAsso($id, $pdo)) {
            $flash = '<div class="flash flash--err">Tu n\'as pas le droit de supprimer cette association.</div>';
        } else {
            $pdo->prepare('DELETE FROM associations WHERE id=?')->execute([$id]);
            $flash = '<div class="flash flash--ok">Association supprimée.</div>';
        }
    }
}

if (isAdminCorpo()) {
    $assos = $pdo->query("SELECT * FROM associations ORDER BY type, nom")->fetchAll();
} else {
    $ids = getManagedAssoIds($pdo);
    if (empty($ids)) {
        $assos = [];
    } else {
        $pl   = implode(',', array_map('intval', $ids));
        $assos = $pdo->query("SELECT * FROM associations WHERE id IN ($pl) ORDER BY type, nom")->fetchAll();
    }
}
?>

<h1 class="admin-page-title">Associations <small style="font-size:.55em;font-weight:400;color:var(--text-muted)">(<?= count($assos) ?>)</small></h1>
<?= $flash ?>

<?php if (isAdminCorpo()): ?>
  <form method="post" style="margin-bottom:var(--s5)">
    <input type="hidden" name="action" value="sync_parents">
    <button type="submit" class="btn btn--sm" style="background:var(--surface);border-color:var(--border)"
            onclick="return confirm('Recalculer le rattachement parent de toutes les associations selon les mandats actifs ?')">
      ↻ Recalculer les rattachements
    </button>
  </form>
<?php endif; ?>

<?php if (!$mandatColsReady): ?>
  <div class="flash flash--warn" style="margin-bottom:var(--s5)">
    Les dates de mandat ne sont pas encore en base - exécute la migration
    <a href="migrate.php" style="color:inherit;text-decoration:underline">assos_mandat_dates</a>.
  </div>
<?php endif; ?>

<?php if (!isAdminCorpo() && !empty($assos)): ?>
  <div class="flash flash--warn" style="margin-bottom:var(--s5)">
    Vous ne voyez que les associations de votre périmètre de gestion.
  </div>
<?php endif; ?>

<?php if (canCreateAssociation($pdo)): ?>
<div class="admin-card">
  <h2>Ajouter une association</h2>
  <?= assoForm($pdo, 'add') ?>
</div>
<?php endif; ?>

<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead>
      <tr><th>
    </thead>
    <tbody>
      <?php foreach ($assos as $a): ?>
        <tr id="row-<?= $a['id'] ?>">
          <td><?= $a['id'] ?></td>
          <td>
            <?php if (!empty($a['logo'])): ?>
              <img src="../<?= htmlspecialchars($a['logo']) ?>" alt="" style="width:24px;height:24px;object-fit:contain;border-radius:4px;vertical-align:middle;margin-right:.4rem">
            <?php else: ?>
              <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:<?= htmlspecialchars($a['color']) ?>;margin-right:.4rem;vertical-align:middle"></span>
            <?php endif; ?>
            <strong><?= htmlspecialchars($a['nom']) ?></strong>
            <br><small style="color:var(--text-muted)"><?= htmlspecialchars($a['slug']) ?></small>
          </td>
          <td><?= htmlspecialchars($a['ecole']) ?></td>
          <td><?= htmlspecialchars($a['type']) ?></td>
          <td><?= htmlspecialchars($a['campus']) ?></td>
          <td><small><?= htmlspecialchars(asso_parent_display_name($pdo, isset($a['parent_bde_id']) ? (int)$a['parent_bde_id'] : null)) ?></small></td>
          <?php if ($mandatColsReady):
            $st = asso_mandat_status_label($a);
            $stLab = match ($st) {
                'active_life' => 'À vie',
                'active'      => 'Active',
                'upcoming'    => 'À venir',
                default       => 'Inactive',
            };
            $stClass = match ($st) {
                'active_life', 'active' => 'badge--ok',
                'upcoming'             => 'badge--pending',
                default                => '',
            };
            $period = asso_format_mandat_period($a);
          ?>
          <td>
            <span class="badge <?= $stClass ?>"><?= htmlspecialchars($stLab) ?></span>
            <?php if ($period !== ''): ?><br><small style="color:var(--text-muted)"><?= htmlspecialchars($period) ?></small><?php endif; ?>
          </td>
          <?php endif; ?>
          <td>
            <div class="actions">
              <button class="btn btn--sm" onclick="toggleEdit('asso-<?= $a['id'] ?>')"
                      style="background:var(--surface);border-color:var(--border)">✏️ Modifier</button>
              <form method="post" style="display:inline"
                    onsubmit="return confirm('Supprimer <?= htmlspecialchars(addslashes($a['nom'])) ?> ?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button class="btn btn--sm btn--danger">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
                <tr id="edit-asso-<?= $a['id'] ?>" style="display:none">
          <td colspan="<?= $mandatColsReady ? 8 : 7 ?>" style="background:rgba(255,255,255,.02);padding:var(--s5)">
            <strong style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--blue-light)">
              Modifier - <?= htmlspecialchars($a['nom']) ?>
            </strong>
            <?= assoForm($pdo, 'update', $a) ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
function toggleEdit(id) {
  const row = document.getElementById('edit-' + id);
  const isHidden = row.style.display === 'none';
  document.querySelectorAll('tr[id^="edit-"]').forEach(r => r.style.display = 'none');
  row.style.display = isHidden ? '' : 'none';
  if (isHidden) row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>

<?php require_once 'includes/admin-footer.php'; ?>

<?php

function assoForm(PDO $pdo, string $action, array $a = []): string {
    $isEdit = $action === 'update';
    $id     = $a['id'] ?? '';
    $v = fn(string $k, mixed $d = '') => htmlspecialchars($a[$k] ?? $d);

    $ecoles = ['Toutes','ECE','ESCE','HEIP','INSEEC Bachelor','INSEEC BBA','INSEEC BTS','INSEEC GE','INSEEC MSc','Sup de Pub'];
    $typesAll  = ['BDE','BDS','Corpo','Association','Fédération','Junior'];
    $typesScoped = ['Association','Junior','Fédération'];
    $types  = isAdminCorpo() ? $typesAll : $typesScoped;
    if ($isEdit && !empty($a['type']) && !in_array((string)$a['type'], $types, true)) {
        $types = array_values(array_unique(array_merge([(string)$a['type']], $types)));
    }
    $campus = ['Tous','Citroën','Citadelle'];
    $typeDefault = isAdminCorpo() ? 'BDE' : 'Association';

    $selOpt = function(array $opts, string $cur) {
        return implode('', array_map(
            fn($o) => sprintf('<option value="%s"%s>%s</option>',
                htmlspecialchars($o), $cur === $o ? ' selected' : '', htmlspecialchars($o)),
            $opts
        ));
    };

    $f  = '<form method="post" enctype="multipart/form-data" class="admin-form" style="margin-top:var(--s4)">';
    $f .= '<input type="hidden" name="action" value="' . htmlspecialchars($action) . '">';
    if ($isEdit) {
        $f .= '<input type="hidden" name="id" value="' . htmlspecialchars((string)$id) . '">';
    }

    $ecolePreview = trim((string)($a['ecole'] ?? 'Toutes'));
    $typePreview    = trim((string)($a['type'] ?? $typeDefault));
    $attachPreview  = $isEdit
        ? ['label' => asso_parent_display_name($pdo, isset($a['parent_bde_id']) ? (int)$a['parent_bde_id'] : null), 'warn' => null]
        : asso_describe_parent_attachment($pdo, $ecolePreview, $typePreview, null);
    $f .= '<div class="form-row"><div class="form-col" style="flex:1">';
    $f .= '<label>Rattachement <small style="color:var(--text-muted);font-weight:400">- automatique selon école et mandats BDE / EchoFed</small></label>';
    $f .= '<p style="margin:.35rem 0 0;font-size:.88rem">' . htmlspecialchars($attachPreview['label']) . '</p>';
    if (!empty($attachPreview['warn'])) {
        $f .= '<p style="margin:.25rem 0 0;font-size:.78rem;color:var(--amber,#e6a817)">' . htmlspecialchars($attachPreview['warn']) . '</p>';
    }
    $f .= '</div></div>';

    $f .= '<div class="form-row">';
    $f .= '<div class="form-col" style="flex:2"><label>Nom</label><input type="text" name="nom" value="' . $v('nom') . '" required></div>';
    $f .= '<div class="form-col"><label>École</label><select name="ecole">' . $selOpt($ecoles, $a['ecole'] ?? 'Toutes') . '</select></div>';
    $f .= '</div>';

    $f .= '<div class="form-row">';
    $f .= '<div class="form-col"><label>Type</label><select name="type">' . $selOpt($types, $a['type'] ?? $typeDefault) . '</select></div>';
    $f .= '<div class="form-col"><label>Campus</label><select name="campus">' . $selOpt($campus, $a['campus'] ?? 'Tous') . '</select></div>';
    $f .= '<div class="form-col"><label>Couleur</label><input type="text" name="color" value="' . $v('color', '#5D0282') . '"></div>';
    $f .= '</div>';

    global $mandatColsReady;
    if ($mandatColsReady) {
        $f .= '<div class="form-row">';
        $f .= '<div class="form-col"><label>Début de mandat <small style="color:var(--text-muted);font-weight:400">- vide = pas de limite</small></label>';
        $f .= corpo_render_date_input('date_debut_mandat', $a['date_debut_mandat'] ?? null) . '</div>';
        $f .= '<div class="form-col"><label>Fin de mandat <small style="color:var(--text-muted);font-weight:400">- vide = à vie</small></label>';
        $f .= corpo_render_date_input('date_fin_mandat', $a['date_fin_mandat'] ?? null) . '</div>';
        $f .= '</div>';
    }

    $f .= '<div class="form-row">';
    $f .= '<div class="form-col"><label>Contact (email)</label><input type="text" name="contact" value="' . $v('contact') . '"></div>';
    $f .= '<div class="form-col"><label>Instagram</label><input type="text" name="instagram" value="' . $v('instagram') . '"></div>';
    $f .= '</div>';

    $f .= '<div class="form-row"><div class="form-col">';
    $f .= '<label>Logo</label>';
    if ($isEdit && !empty($a['logo'])) {
        $f .= '<div style="display:flex;align-items:center;gap:var(--s3);margin-bottom:var(--s2)">';
        $f .= '<img src="../' . htmlspecialchars($a['logo']) . '" alt="" style="height:36px;width:36px;object-fit:contain;border-radius:6px;border:1px solid var(--border)">';
        $f .= '<span style="font-size:.72rem;color:var(--text-muted)">Logo actuel - remplacer ci-dessous si besoin</span>';
        $f .= '</div>';
    }
    $f .= '<input type="file" name="logo_file" accept="image/*" style="font-size:.8rem">';
    $f .= '<div style="margin-top:var(--s2)">';
    $f .= '<input type="text" name="logo_url" value="' . $v('logo') . '" placeholder="Ou coller une URL https://…" style="font-size:.78rem">';
    $f .= '</div>';
    $f .= '</div></div>';

    $f .= '<div class="form-row">';
    $f .= '<div class="form-col" style="flex:2"><label>Description</label><textarea name="description" rows="3">' . $v('description') . '</textarea></div>';
    $f .= '<div class="form-col" style="flex:0;min-width:140px;align-self:center"><label><input type="checkbox" name="ouverte_a_tous"' . (!empty($a['ouverte_a_tous']) ? ' checked' : '') . '> Ouverte à tous</label></div>';
    $f .= '</div>';

    $ecolesPossibles = ['ECE','ESCE','HEIP','INSEEC Bachelor','INSEEC BBA','INSEEC BTS','INSEEC GE','INSEEC MSc','Sup de Pub'];
    $current = $a['ecoles_eligibles'] ?? null;
    if (is_string($current))    $current = json_decode($current, true) ?: [];
    if (!is_array($current))    $current = [];
    $f .= '<div class="form-row"><div class="form-col" style="flex:1">';
    $f .= '<label style="margin-bottom:.4rem">Écoles autorisées à rejoindre <small style="color:var(--text-muted);font-weight:400">- vide = toutes les écoles peuvent demander à rejoindre</small></label>';
    $f .= '<div style="display:flex;flex-wrap:wrap;gap:.6rem;padding:.5rem;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:var(--r-md)">';
    foreach ($ecolesPossibles as $ec) {
        $chk = in_array($ec, $current, true) ? ' checked' : '';
        $id  = 'ec_' . md5($ec . ($a['id'] ?? 'new'));
        $f  .= '<label for="' . $id . '" style="display:inline-flex;align-items:center;gap:.35rem;font-size:.82rem;cursor:pointer;padding:.25rem .55rem;border-radius:var(--r-pill);border:1px solid var(--border);background:rgba(255,255,255,.02)">'
             . '<input type="checkbox" id="' . $id . '" name="ecoles_eligibles[]" value="' . htmlspecialchars($ec) . '"' . $chk . '> '
             . htmlspecialchars($ec)
             . '</label>';
    }
    $f .= '</div></div></div>';

    $f .= '<button type="submit" class="btn btn--primary">' . ($isEdit ? '💾 Enregistrer' : 'Ajouter →') . '</button>';
    if ($isEdit) {
        $f .= ' <button type="button" class="btn" onclick="toggleEdit(\'asso-' . $id . '\')" style="background:var(--surface);border-color:var(--border)">Annuler</button>';
    }
    $f .= '</form>';
    return $f;
}
?>
