<?php

$adminTitle = 'Journal des mails';
$adminPage  = 'mails';
require_once '../includes/db.php';
require_once '../includes/mailer.php';
require_once 'includes/admin-header.php';

if (!isSuperAdmin()) {
    echo '<div class="flash flash--err">Accès réservé au Super Administrateur.</div>';
    require_once 'includes/admin-footer.php';
    exit;
}

$logPath = __DIR__ . '/../logs/mail.log';
$flash   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_log') {
    if (is_file($logPath)) {
        if (@file_put_contents($logPath, '') === false) {
            $flash = '<div class="flash flash--err">Impossible de vider le log (permissions ?).</div>';
        } else {
            $flash = '<div class="flash flash--ok">Journal vidé.</div>';
        }
    } else {
        $flash = '<div class="flash flash--warn">Aucun journal à vider.</div>';
    }
}

function parse_mail_log_line(string $line): ?array {
    if (!preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+\[([^\]]+)\]\s*(.*)$/', $line, $m)) {
        return null;
    }
    $ts   = $m[1];
    $tag  = trim($m[2]);
    $rest = trim($m[3]);

    $cat = 'other';
    if (preg_match('/^SMTP\s+\d+$/i', $tag)) {
        $cat = 'debug';
    } elseif ($tag === 'OK') {
        $cat = 'ok';
    } elseif ($tag === 'ERR' || str_ends_with($tag, ' ERR') || str_contains($tag, 'ERR')) {
        $cat = 'err';
    } elseif ($tag === 'DEV') {
        $cat = 'dev';
    } elseif (in_array($tag, ['reset', 'PDF ERR'], true) || str_contains($tag, 'PDF')) {
        $cat = 'info';
    }

    $to      = '';
    $subject = '';
    $error   = '';
    if (preg_match('/to=([^\s]+)/', $rest, $mm)) $to = $mm[1];
    if (preg_match('/subject=(.+?)(?:\s+attachments=|$)/', $rest, $mm)) {
        $subject = trim($mm[1]);
    }
    if (preg_match('/(?:err|fatal)=(.+)$/', $rest, $mm)) {
        $error = trim($mm[1]);
    }

    return [
        'ts'       => $ts,
        'tag'      => $tag,
        'cat'      => $cat,
        'rest'     => $rest,
        'to'       => $to,
        'subject'  => $subject,
        'error'    => $error,
        'raw'      => $line,
    ];
}

$entries = [];
if (is_file($logPath)) {

    $fh = @fopen($logPath, 'r');
    if ($fh) {
        $buf = [];
        while (($ln = fgets($fh)) !== false) {
            $buf[] = rtrim($ln, "\r\n");
            if (count($buf) > 5000) array_shift($buf);
        }
        fclose($fh);
        foreach ($buf as $ln) {
            $p = parse_mail_log_line($ln);
            if ($p) $entries[] = $p;
        }
    }
}

$filterCat   = $_GET['cat']     ?? 'main';
$filterRange = $_GET['range']   ?? 'all';
$filterQ     = trim((string)($_GET['q'] ?? ''));

$now = time();
$rangeFrom = null;
if     ($filterRange === 'today') $rangeFrom = strtotime('today');
elseif ($filterRange === '7d')    $rangeFrom = $now - 7 * 86400;
elseif ($filterRange === '30d')   $rangeFrom = $now - 30 * 86400;

$rows = [];
foreach ($entries as $e) {

    if ($filterCat === 'main' && $e['cat'] === 'debug') continue;
    if ($filterCat !== 'all' && $filterCat !== 'main' && $e['cat'] !== $filterCat) continue;

    if ($rangeFrom !== null) {
        $t = strtotime($e['ts']);
        if ($t === false || $t < $rangeFrom) continue;
    }

    if ($filterQ !== '' && stripos($e['raw'], $filterQ) === false) continue;
    $rows[] = $e;
}

$rows = array_reverse($rows);

$stat = ['total' => 0, 'ok' => 0, 'err' => 0, 'dev' => 0, 'debug' => 0, 'last' => null, 'last_err' => null, 'err_24h' => 0];
$cut24h = $now - 86400;
foreach ($entries as $e) {
    $stat['total']++;
    $cat = $e['cat'];
    if (isset($stat[$cat])) $stat[$cat]++;
    $stat['last'] = $e['ts'];
    if ($cat === 'err') {
        $stat['last_err'] = $e['ts'];
        $t = strtotime($e['ts']);
        if ($t !== false && $t >= $cut24h) $stat['err_24h']++;
    }
}

$countByCat = ['main' => 0, 'ok' => 0, 'err' => 0, 'dev' => 0, 'debug' => 0, 'all' => count($entries)];
foreach ($entries as $e) {
    if ($e['cat'] !== 'debug') $countByCat['main']++;
    if (isset($countByCat[$e['cat']])) $countByCat[$e['cat']]++;
}

function mail_log_badge(string $cat): string {
    $map = [
        'ok'    => ['ok',    'Envoyé'],
        'err'   => ['ko',    'Erreur'],
        'dev'   => ['pending','Dev/log'],
        'debug' => ['pending','SMTP debug'],
        'info'  => ['pending','Info'],
        'other' => ['pending','Autre'],
    ];
    [$cls, $label] = $map[$cat] ?? ['pending', 'Autre'];
    return '<span class="badge badge--' . $cls . '">' . htmlspecialchars($label) . '</span>';
}
function mail_log_link(string $cat, string $range, string $q): string {
    $params = array_filter(['cat' => $cat, 'range' => $range, 'q' => $q], fn($v) => $v !== '' && $v !== null);
    return 'mails.php?' . http_build_query($params);
}
?>

<h1 class="admin-page-title">📧 Journal des mails</h1>

<?= $flash ?>

<div class="admin-card" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:var(--s4);padding:var(--s5)">
  <div>
    <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.1em;font-weight:700">Total entrées</div>
    <div style="font-size:1.8rem;font-weight:700;margin-top:.2rem"><?= number_format($stat['total'], 0, ',', ' ') ?></div>
  </div>
  <div>
    <div style="font-size:.7rem;color:#86efac;text-transform:uppercase;letter-spacing:.1em;font-weight:700">Envoyés OK</div>
    <div style="font-size:1.8rem;font-weight:700;margin-top:.2rem;color:#86efac"><?= $stat['ok'] ?></div>
  </div>
  <div>
    <div style="font-size:.7rem;color:#fca5a5;text-transform:uppercase;letter-spacing:.1em;font-weight:700">Erreurs (total)</div>
    <div style="font-size:1.8rem;font-weight:700;margin-top:.2rem;color:#fca5a5"><?= $stat['err'] ?></div>
  </div>
  <div>
    <div style="font-size:.7rem;color:#fca5a5;text-transform:uppercase;letter-spacing:.1em;font-weight:700">Erreurs (24 h)</div>
    <div style="font-size:1.8rem;font-weight:700;margin-top:.2rem;color:<?= $stat['err_24h'] > 0 ? '#fca5a5' : '#86efac' ?>"><?= $stat['err_24h'] ?></div>
  </div>
  <div>
    <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.1em;font-weight:700">Dernier envoi</div>
    <div style="font-size:.95rem;font-weight:600;margin-top:.4rem"><?= htmlspecialchars($stat['last'] ?? '-') ?></div>
  </div>
</div>

<div style="display:flex;gap:var(--s2);flex-wrap:wrap;margin-bottom:var(--s4)">
  <a href="test-mail.php" class="btn">🧪 Envoyer un mail de test</a>
  <?php if (is_file($logPath)): ?>
    <form method="post" style="display:inline" onsubmit="return confirm('Vider entièrement le journal des mails ? Cette action est irréversible.')">
      <input type="hidden" name="action" value="clear_log">
      <button type="submit" class="btn btn--danger">🗑 Vider le journal</button>
    </form>
  <?php endif; ?>
  <a href="<?= htmlspecialchars(mail_log_link($filterCat, $filterRange, $filterQ)) ?>" class="btn" style="margin-left:auto">🔄 Rafraîchir</a>
</div>

<div class="admin-card" style="padding:var(--s4)">
    <div style="display:flex;gap:var(--s2);flex-wrap:wrap;margin-bottom:var(--s4)">
    <?php
      $tabs = [
        'main'  => ['Métier',     $countByCat['main']],
        'ok'    => ['Envoyés',    $countByCat['ok']],
        'err'   => ['Erreurs',    $countByCat['err']],
        'dev'   => ['Mode dev',   $countByCat['dev']],
        'debug' => ['SMTP debug', $countByCat['debug']],
        'all'   => ['Tout',       $countByCat['all']],
      ];
      foreach ($tabs as $key => [$lbl, $cnt]):
        $active = $filterCat === $key;
    ?>
      <a href="<?= htmlspecialchars(mail_log_link($key, $filterRange, $filterQ)) ?>"
         class="btn<?= $active ? ' btn--primary' : '' ?>"
         style="<?= $active ? '' : 'background:var(--surface);border:1px solid var(--border);color:var(--blue-light)' ?>">
        <?= htmlspecialchars($lbl) ?> <span style="opacity:.7;font-weight:500">(<?= $cnt ?>)</span>
      </a>
    <?php endforeach; ?>
  </div>

    <form method="get" class="admin-form" style="display:flex;gap:var(--s3);flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="cat" value="<?= htmlspecialchars($filterCat) ?>">
    <div class="form-col" style="min-width:160px;flex:0 1 200px">
      <label>Période</label>
      <select name="range">
        <option value="all"   <?= $filterRange === 'all'   ? 'selected' : '' ?>>Toutes</option>
        <option value="today" <?= $filterRange === 'today' ? 'selected' : '' ?>>Aujourd'hui</option>
        <option value="7d"    <?= $filterRange === '7d'    ? 'selected' : '' ?>>7 derniers jours</option>
        <option value="30d"   <?= $filterRange === '30d'   ? 'selected' : '' ?>>30 derniers jours</option>
      </select>
    </div>
    <div class="form-col" style="flex:1;min-width:200px">
      <label>Recherche (destinataire, sujet, message d'erreur…)</label>
      <input type="text" name="q" value="<?= htmlspecialchars($filterQ) ?>" placeholder="ex: romain@edu.ece.fr ou « Could not authenticate »">
    </div>
    <div class="form-col" style="flex:0">
      <button type="submit" class="btn btn--primary">Filtrer</button>
    </div>
    <?php if ($filterRange !== 'all' || $filterQ !== ''): ?>
      <div class="form-col" style="flex:0">
        <a href="<?= htmlspecialchars(mail_log_link($filterCat, 'all', '')) ?>" class="btn">Réinitialiser</a>
      </div>
    <?php endif; ?>
  </form>
</div>

<?php if (empty($rows)): ?>
  <div class="admin-card" style="text-align:center;padding:var(--s8)">
    <p style="font-size:1.8rem;margin-bottom:var(--s3)">📭</p>
    <h2 style="margin-bottom:var(--s2)">Aucune entrée pour ces filtres</h2>
    <p style="color:var(--text-muted)">
      <?php if (empty($entries)): ?>
        Le journal est vide. Envoie un mail de test pour générer la première ligne.
      <?php else: ?>
        Modifie les filtres pour voir d'autres entrées.
      <?php endif; ?>
    </p>
  </div>
<?php else: ?>
  <div class="admin-card" style="padding:0;overflow:hidden">
    <div style="padding:var(--s3) var(--s5);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <span style="font-size:.85rem;color:var(--text-muted)">
        <?= count($rows) ?> entrée<?= count($rows) > 1 ? 's' : '' ?> trouvée<?= count($rows) > 1 ? 's' : '' ?>
        <?php if (count($rows) >= 500): ?>
          <em style="margin-left:.5rem">- affichage limité aux 500 plus récentes</em>
        <?php endif; ?>
      </span>
      <span style="font-size:.75rem;color:var(--text-muted)">trié du plus récent au plus ancien</span>
    </div>
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width:155px">Date</th>
          <th style="width:115px">Statut</th>
          <th style="width:220px">Destinataire</th>
          <th>Sujet / détails</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (array_slice($rows, 0, 500) as $r):
        $tooltip = htmlspecialchars($r['raw']);
        $detail = '';
        if ($r['cat'] === 'err') {
            $detail = '<span style="color:#fca5a5">' . htmlspecialchars($r['error'] ?: $r['rest']) . '</span>';
        } elseif ($r['subject'] !== '') {
            $detail = htmlspecialchars($r['subject']);
        } else {
            $detail = '<span style="color:var(--text-muted)">' . htmlspecialchars(mb_strimwidth($r['rest'], 0, 160, '…')) . '</span>';
        }
      ?>
        <tr title="<?= $tooltip ?>">
          <td data-label="Date" style="font-family:monospace;font-size:.78rem;color:var(--text-muted)">
            <?= htmlspecialchars($r['ts']) ?>
          </td>
          <td data-label="Statut"><?= mail_log_badge($r['cat']) ?></td>
          <td data-label="Destinataire" style="font-family:monospace;font-size:.78rem"><?= htmlspecialchars($r['to'] ?: '-') ?></td>
          <td data-label="Détails"><?= $detail ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require_once 'includes/admin-footer.php'; ?>
