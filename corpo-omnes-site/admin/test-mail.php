<?php

$adminTitle = 'Test envoi mail';
$adminPage  = 'test-mail';
require_once '../includes/db.php';
require_once '../includes/mailer.php';
require_once 'includes/admin-header.php';

if (!isSuperAdmin()) {
    echo '<div class="flash flash--err">Accès réservé au Super Administrateur.</div>';
    require_once 'includes/admin-footer.php';
    exit;
}

$flash = '';
$sentOk = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim((string)($_POST['to'] ?? ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $flash = '<div class="flash flash--err">Adresse email invalide.</div>';
    } else {
        $html = corpo_mail_layout(
            'Test d\'envoi Corpo Omnes',
            '<p>Ce mail est un test d\'envoi déclenché depuis l\'admin Corpo Omnes.</p>'
          . '<p>Si tu le lis, c\'est que le relais SMTP est correctement configuré.</p>'
          . '<p style="color:#888;font-size:12px">Horodatage : ' . htmlspecialchars(date('Y-m-d H:i:s')) . '</p>'
        );
        $sentOk = corpo_mail_send($to, 'Test SMTP Corpo Omnes - ' . date('H:i:s'), $html);
        if ($sentOk) {
            $flash = '<div class="flash flash--ok">Envoi exécuté avec succès. Vérifie '
                   . htmlspecialchars($to) . ' (boîte de réception + spams).</div>';
        } else {
            $flash = '<div class="flash flash--err">Échec de l\'envoi. Détails ci-dessous dans le log.</div>';
        }
    }
}

$serverAddr = '';
foreach (['HTTP_X_FORWARDED_FOR', 'SERVER_ADDR'] as $key) {
    if (!empty($_SERVER[$key])) {
        $serverAddr = trim(explode(',', (string)$_SERVER[$key])[0]);
        break;
    }
}

$outboundIp = '';
$ctx = stream_context_create(['http' => ['timeout' => 3]]);
$body = @file_get_contents('https://api.ipify.org', false, $ctx);
if ($body && preg_match('/^\d+\.\d+\.\d+\.\d+$/', trim((string)$body))) {
    $outboundIp = trim((string)$body);
}

$cfg = [
    'MAIL_ENABLED'     => (string)corpo_env('MAIL_ENABLED', '0'),
    'MAIL_FROM'        => (string)corpo_env('MAIL_FROM', '(non défini)'),
    'MAIL_FROM_NAME'   => (string)corpo_env('MAIL_FROM_NAME', '(non défini)'),
    'MAIL_DEBUG'       => (string)corpo_env('MAIL_DEBUG', '0'),
    'MAIL_SMTP_HOST'   => (string)corpo_env('MAIL_SMTP_HOST', '(non défini)'),
    'MAIL_SMTP_PORT'   => (string)corpo_env('MAIL_SMTP_PORT', '(non défini)'),
    'MAIL_SMTP_SECURE' => (string)corpo_env('MAIL_SMTP_SECURE', '(non défini)'),
    'MAIL_SMTP_USER'   => (string)corpo_env('MAIL_SMTP_USER', '(non défini)'),
    'MAIL_SMTP_PASS'   => corpo_env('MAIL_SMTP_PASS', '') !== '' ? '••• défini (' . strlen((string)corpo_env('MAIL_SMTP_PASS', '')) . ' car.)' : '(VIDE - mode dev/log)',
    'IP sortante (vue par Brevo)' => $outboundIp !== '' ? $outboundIp : '(non détectée - utilise SERVER_ADDR ci-dessous)',
    'SERVER_ADDR'      => $serverAddr !== '' ? $serverAddr : '(inconnu)',
];
$enabled = corpo_mail_enabled();
?>

<h1 class="admin-page-title">🧪 Test d'envoi mail</h1>

<p style="margin-bottom:var(--s4);color:var(--text-muted)">
  <a href="mails.php">← Retour au journal des mails</a>
</p>

<div class="flash flash--info" style="margin-bottom:var(--s4)">
  Cette page est un <strong>outil de diagnostic secondaire</strong> pour vérifier la configuration SMTP.
  Pour consulter l'historique complet des envois, va sur <a href="mails.php" style="color:inherit;text-decoration:underline">Journal des mails</a>.
</div>

<?= $flash ?>

<?php if (!$enabled): ?>
  <div class="flash flash--warn">
    <strong>Mode dev actif :</strong> les mails sont écrits dans <code>logs/mail.log</code> au lieu d'être envoyés
    (parce que <code>MAIL_ENABLED ≠ 1</code> ou <code>MAIL_SMTP_PASS</code> est vide).<br>
    Configure ton compte SMTP Brevo dans <code>corpo-omnes-site/includes/.env</code> pour passer en mode envoi réel.
  </div>
<?php else: ?>
  <div class="flash flash--info">
    <strong>Mode envoi réel actif</strong> via <code><?= htmlspecialchars($cfg['MAIL_SMTP_HOST']) ?></code>.
    Tu peux envoyer un mail de test ci-dessous.
  </div>
<?php endif; ?>

<div class="admin-card">
  <h2>Envoyer un mail de test</h2>
  <form method="post">
    <div class="form-group">
      <label for="to">Adresse destinataire</label>
      <input type="email" id="to" name="to" required autofocus
             placeholder="ton.email@exemple.com"
             value="<?= htmlspecialchars($_POST['to'] ?? '') ?>"
             style="width:100%;max-width:400px">
    </div>
    <button type="submit" class="btn btn--primary">Envoyer →</button>
  </form>
</div>

<div class="admin-card" style="margin-top:var(--s4)">
  <h2>Configuration actuelle</h2>
  <table class="admin-table" style="font-family:monospace;font-size:.85rem">
    <?php foreach ($cfg as $k => $v): ?>
      <tr>
        <td style="width:200px;color:var(--purple)"><?= htmlspecialchars($k) ?></td>
        <td><?= htmlspecialchars($v) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <p style="margin-top:var(--s3);font-size:.85rem;color:var(--text-muted)">
    Pour activer le mode debug détaillé (capture de la conversation SMTP), passe
    <code>MAIL_DEBUG=1</code> dans <code>includes/.env</code> puis relance un envoi de test.
  </p>
</div>

<div class="admin-card" style="margin-top:var(--s4);background:#fff4e6;border-left:4px solid #f59e0b">
  <h2>⚠ Erreur fréquente Brevo - « 525 Unauthorized IP address »</h2>
  <p>Brevo bloque l'envoi quand l'IP du serveur n'est pas dans la liste des « Authorized IPs ».
  Sur un hébergement mutualisé comme 42web.io, l'IP change régulièrement → la seule solution viable
  est de <strong>désactiver</strong> cette restriction.</p>
  <ol style="line-height:1.8">
    <li>Va sur <a href="https://app.brevo.com/security/authorised_ips" target="_blank" rel="noopener">app.brevo.com/security/authorised_ips</a>.</li>
    <li>Si tu vois un toggle <em>« Limit access to authorized IPs »</em> → désactive-le.</li>
    <li>Sinon, supprime toutes les IPs de la liste (liste vide = autoriser toutes).</li>
    <li>Reviens ici et renvoie un mail de test.</li>
  </ol>
  <?php if ($outboundIp !== ''): ?>
    <p style="margin-top:.6rem;font-size:.9rem;color:var(--text-muted)">
      Si tu choisis quand même d'ajouter une IP spécifique, l'IP sortante actuelle du serveur est :
      <code style="background:#fff;padding:2px 6px;border-radius:4px;color:#d97706"><?= htmlspecialchars($outboundIp) ?></code>
      (peut changer, c'est pour ça qu'on recommande de désactiver la restriction).
    </p>
  <?php endif; ?>
</div>

<div class="admin-card" style="margin-top:var(--s4);background:#faf7ff">
  <h2>Procédure Brevo (relais SMTP recommandé)</h2>
  <ol style="line-height:1.8">
    <li>Crée un compte gratuit sur <a href="https://www.brevo.com" target="_blank" rel="noopener">brevo.com</a> (300 mails/jour).</li>
    <li>Onglet <strong>Senders &amp; IP</strong> → <strong>Senders</strong> → <em>Add a Sender</em> :
      saisis <code>corpoomnes@gmail.com</code>, Brevo t'envoie un code par mail, valide-le.</li>
    <li>Onglet <strong>SMTP &amp; API</strong> → <strong>SMTP</strong> : note ton <em>login</em> (souvent ton email Brevo)
      et clique <em>Generate a new SMTP key</em>.</li>
    <li>Édite <code>corpo-omnes-site/includes/.env</code> :
      <ul>
        <li><code>MAIL_SMTP_USER=&lt;login Brevo&gt;</code></li>
        <li><code>MAIL_SMTP_PASS=&lt;clé SMTP générée&gt;</code></li>
      </ul>
    </li>
    <li>Reviens ici et envoie un mail de test.</li>
  </ol>
  <p style="color:var(--text-muted);font-size:.85rem">
    💡 Astuce : pour une délivrabilité maximale, prends un nom de domaine perso
    (~10 €/an) puis ajoute les enregistrements SPF/DKIM fournis par Brevo. Les mails
    en <code>From: corpoomnes@gmail.com</code> fonctionneront, mais auront un peu plus
    de chance d'aller en spam que <code>noreply@corpoomnes.fr</code>.
  </p>
</div>

<?php require_once 'includes/admin-footer.php'; ?>
