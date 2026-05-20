<?php
// façade mail PHPMailer + Gmail SMTP
// si MAIL_ENABLED=0 ou pas de mot de passe SMTP → on log sans envoyer (pratique en dev)

require_once __DIR__ . '/env.php';

if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
    require_once __DIR__ . '/lib/PHPMailer/Exception.php';
    require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/lib/PHPMailer/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// log dans un fichier texte
function corpo_mail_log_path(): string {
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir . '/mail.log';
}

function corpo_mail_log(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    @file_put_contents(corpo_mail_log_path(), $line, FILE_APPEND);
}

// quota configurable via MAIL_DAILY_LIMIT dans le .env
function corpo_mail_daily_limit(): int
{
    return max(1, (int)corpo_env('MAIL_DAILY_LIMIT', 300));
}

// compte les mails envoyés depuis le log pour le quota
function corpo_mail_quota_stats(?string $logPath = null): array
{
    $limit = corpo_mail_daily_limit();
    $today = date('Y-m-d');
    $cut7  = date('Y-m-d', strtotime('-6 days'));
    $cut30 = date('Y-m-d', strtotime('-29 days'));
    $sentToday = 0;
    $sent7     = 0;
    $sent30    = 0;

    $path = $logPath ?? corpo_mail_log_path();
    if (is_file($path)) {
        $fh = @fopen($path, 'r');
        if ($fh) {
            while (($ln = fgets($fh)) !== false) {
                if (!preg_match('/^\[(\d{4}-\d{2}-\d{2})/', $ln, $m)) {
                    continue;
                }
                $day = $m[1];
                if (!str_contains($ln, '[OK]')) {
                    continue;
                }
                if ($day === $today) {
                    $sentToday++;
                }
                if ($day >= $cut7) {
                    $sent7++;
                }
                if ($day >= $cut30) {
                    $sent30++;
                }
            }
            fclose($fh);
        }
    }

    return [
        'limit'            => $limit,
        'sent_today'       => $sentToday,
        'remaining_today'  => max(0, $limit - $sentToday),
        'sent_7d'          => $sent7,
        'sent_30d'         => $sent30,
        'date_today'       => $today,
    ];
}

// on vérifie si le mail est actif et configuré
function corpo_mail_enabled(): bool {
    if ((string)corpo_env('MAIL_ENABLED', '0') !== '1') return false;
    $pass = (string)corpo_env('MAIL_SMTP_PASS', '');
    return $pass !== '';
}

function corpo_mail_app_url(string $relative = ''): string {
    $base = rtrim((string)corpo_env('SITE_URL', ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = $scheme . '://' . $host;
    }
    $rel = ltrim($relative, '/');
    return $rel === '' ? $base : ($base . '/' . $rel);
}

// templates HTML pour les mails
function corpo_mail_layout(string $title, string $bodyHtml, ?string $ctaUrl = null, ?string $ctaLabel = null): string {
    $logoUrl = corpo_mail_app_url('images/logo-corpo-omnes.png');
    $year    = date('Y');
    $cta     = '';
    if ($ctaUrl && $ctaLabel) {
        $cta = '<p style="text-align:center;margin:32px 0 8px">'
             . '<a href="' . htmlspecialchars($ctaUrl) . '" style="display:inline-block;background:linear-gradient(135deg,#5D0282 0%,#8B2FC9 100%);color:#fff;text-decoration:none;padding:14px 32px;border-radius:999px;font-weight:700;font-size:15px;letter-spacing:.3px">'
             . htmlspecialchars($ctaLabel) . '</a></p>';
    }
    $linkFallback = '';
    if ($ctaUrl) {
        $linkFallback = '<p style="font-size:11px;color:#666;text-align:center;margin:0 0 24px;word-break:break-all">'
                      . 'Lien direct : <a href="' . htmlspecialchars($ctaUrl) . '" style="color:#5D0282">' . htmlspecialchars($ctaUrl) . '</a></p>';
    }
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f4f0fa;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;color:#1a0040">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f0fa;padding:32px 16px"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(26,0,64,.08)">'
        . '<tr><td style="background:linear-gradient(135deg,#1a0040 0%,#5D0282 60%,#8B2FC9 100%);padding:28px 32px;text-align:center">'
        . '<img src="' . htmlspecialchars($logoUrl) . '" alt="Corpo Omnes" width="56" height="56" style="display:inline-block;border:0">'
        . '<div style="color:#fff;font-weight:900;font-size:18px;letter-spacing:2px;margin-top:8px">CORPO OMNES</div>'
        . '<div style="color:rgba(255,255,255,.85);font-size:11px;letter-spacing:4px;margin-top:4px">LYON</div>'
        . '</td></tr>'
        . '<tr><td style="padding:32px;font-size:15px;line-height:1.6;color:#1a0040">'
        . '<h1 style="font-size:20px;margin:0 0 18px;color:#1a0040">' . htmlspecialchars($title) . '</h1>'
        . $bodyHtml
        . $cta
        . $linkFallback
        . '</td></tr>'
        . '<tr><td style="background:#faf7ff;padding:16px 32px;text-align:center;font-size:11px;color:#888;border-top:1px solid #e8d9f5">'
        . '© ' . $year . ' Corpo Omnes Lyon · Mail automatique, merci de ne pas y répondre directement.'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/**
 * Convertit un HTML en équivalent texte propre (pour l'AltBody).
 * - Remplace <br>/<p>/<li> par des sauts de ligne
 * - Strip les autres tags
 * - Décode les entités HTML (&#039; → ', &amp; → &, &nbsp; → espace, etc.)
 *   sinon les clients mail qui affichent l'AltBody montrent du "d&#039;hier".
 */
function corpo_mail_html_to_text(string $html): string {
    $s = preg_replace('#<\s*br\s*/?>#i', "\n", $html);
    $s = preg_replace('#</\s*(p|div|li|tr|h[1-6])\s*>#i', "\n", (string)$s);
    $s = preg_replace('#<\s*li[^>]*>#i', '• ', (string)$s);
    $s = strip_tags((string)$s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = preg_replace("/\n{3,}/", "\n\n", $s);
    return trim((string)$s);
}

// envoi réel via PHPMailer
function corpo_mail_send(
    string $to,
    string $subject,
    string $htmlBody,
    array $attachments = [],
    ?string $toName = null,
    ?string $altBody = null
): bool {
    $from     = (string)corpo_env('MAIL_FROM', 'no-reply@example.com');
    $fromName = (string)corpo_env('MAIL_FROM_NAME', 'Corpo Omnes');

    if (!corpo_mail_enabled()) {
        corpo_mail_log("[DEV] to=$to subject=" . preg_replace('/\s+/', ' ', $subject)
            . ' attachments=' . count($attachments));
        return true;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = (string)corpo_env('MAIL_SMTP_HOST', 'smtp.gmail.com');
        $mail->Port       = (int)corpo_env('MAIL_SMTP_PORT', 587);
        $mail->SMTPAuth   = true;
        $mail->Username   = (string)corpo_env('MAIL_SMTP_USER', $from);
        $mail->Password   = (string)corpo_env('MAIL_SMTP_PASS', '');
        $secure = strtolower((string)corpo_env('MAIL_SMTP_SECURE', 'tls'));
        $mail->SMTPSecure = $secure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';

        // Mode debug optionnel : MAIL_DEBUG=1 dans .env → on capture la conversation
        // SMTP complète dans logs/mail.log (utile pour diagnostiquer Brevo/Gmail).
        if ((string)corpo_env('MAIL_DEBUG', '0') === '1') {
            $mail->SMTPDebug   = SMTP::DEBUG_CONNECTION;
            $mail->Debugoutput = function ($str, $level) {
                corpo_mail_log('[SMTP ' . $level . '] ' . trim((string)$str));
            };
        }

        $mail->setFrom($from, $fromName);
        $mail->addAddress($to, $toName ?: '');
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody ?: corpo_mail_html_to_text($htmlBody);

        foreach ($attachments as $att) {
            // $att peut être :
            //  - normal : ['path'|'data', 'name', 'mime']
            //  - inline : ['path'|'data', 'name', 'mime', 'cid' => '...']
            //    → la pièce devient une image embarquée, référençable via <img src="cid:...">
            $name = (string)($att['name'] ?? 'piece-jointe');
            $mime = (string)($att['mime'] ?? 'application/octet-stream');
            $cid  = (string)($att['cid']  ?? '');

            if ($cid !== '') {
                if (!empty($att['path']) && is_file($att['path'])) {
                    $mail->addEmbeddedImage($att['path'], $cid, $name, PHPMailer::ENCODING_BASE64, $mime);
                } elseif (!empty($att['data'])) {
                    $mail->addStringEmbeddedImage($att['data'], $cid, $name, PHPMailer::ENCODING_BASE64, $mime);
                }
            } elseif (!empty($att['path']) && is_file($att['path'])) {
                $mail->addAttachment($att['path'], $name, PHPMailer::ENCODING_BASE64, $mime);
            } elseif (!empty($att['data'])) {
                $mail->addStringAttachment($att['data'], $name, PHPMailer::ENCODING_BASE64, $mime);
            }
        }

        $mail->send();
        corpo_mail_log("[OK] to=$to subject=" . preg_replace('/\s+/', ' ', $subject));
        return true;
    } catch (PHPMailerException $e) {
        corpo_mail_log('[ERR] to=' . $to . ' err=' . $e->getMessage());
        return false;
    } catch (Throwable $e) {
        corpo_mail_log('[ERR] to=' . $to . ' fatal=' . $e->getMessage());
        return false;
    }
}

// génération des tokens pour vérif email et reset mdp
function corpo_mail_make_token(): array {
    $raw  = bin2hex(random_bytes(32));      // 64 hex chars
    $hash = hash('sha256', $raw);
    return [$raw, $hash];
}

function corpo_mail_create_verification(PDO $pdo, int $userId, int $hours = 24): string {
    [$raw, $hash] = corpo_mail_make_token();
    $intervalH = max(1, min(168, (int)$hours));
    $pdo->prepare(
        "INSERT INTO email_verifications (user_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL {$intervalH} HOUR))"
    )->execute([$userId, $hash]);
    return $raw;
}

function corpo_mail_create_password_reset(PDO $pdo, int $userId, int $hours = 1): string {
    [$raw, $hash] = corpo_mail_make_token();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $intervalH = max(1, min(24, (int)$hours));
    $pdo->prepare(
        "INSERT INTO password_resets (user_id, token_hash, expires_at, ip_request)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL {$intervalH} HOUR), ?)"
    )->execute([$userId, $hash, $ip]);
    return $raw;
}

// fonctions d'envoi de mails spécifiques au site
function corpo_mail_send_verification(PDO $pdo, array $user): bool {
    $token = corpo_mail_create_verification($pdo, (int)$user['id'], 24);
    $url   = corpo_mail_app_url('verify-email.php?token=' . $token);

    $prenom = htmlspecialchars((string)($user['prenom'] ?? ''));
    $body = '<p>Bonjour ' . ($prenom ?: 'à toi') . ',</p>'
          . '<p>Bienvenue sur la plateforme <strong>Corpo Omnes Lyon</strong>. Ton compte a été créé '
          . 'mais il faut encore <strong>confirmer ton adresse email</strong> avant de pouvoir te connecter.</p>'
          . '<p>Clique sur le bouton ci-dessous pour activer ton compte (le lien expire dans 24 heures).</p>';

    return corpo_mail_send(
        (string)$user['email'],
        'Confirme ton adresse email - Corpo Omnes Lyon',
        corpo_mail_layout('Confirme ton adresse email', $body, $url, 'Activer mon compte'),
        [],
        trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''))
    );
}

function corpo_mail_send_password_reset(PDO $pdo, array $user): bool {
    $token = corpo_mail_create_password_reset($pdo, (int)$user['id'], 1);
    $url   = corpo_mail_app_url('reset-password.php?token=' . rawurlencode($token));

    $prenom = htmlspecialchars((string)($user['prenom'] ?? ''));
    $body = '<p>Bonjour ' . ($prenom ?: 'à toi') . ',</p>'
          . '<p>Une demande de réinitialisation de mot de passe a été déposée pour ton compte Corpo Omnes Lyon. '
          . 'Si tu n\'es pas à l\'origine de cette demande, ignore simplement ce mail - rien ne sera changé.</p>'
          . '<p>Sinon, clique sur le bouton ci-dessous pour choisir un nouveau mot de passe. '
          . '<strong>Le lien expire dans 1 heure.</strong></p>';

    return corpo_mail_send(
        (string)$user['email'],
        'Réinitialisation de ton mot de passe - Corpo Omnes Lyon',
        corpo_mail_layout('Réinitialiser ton mot de passe', $body, $url, 'Choisir un nouveau mot de passe'),
        [],
        trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''))
    );
}

/**
 * Envoie un mail "billet" avec :
 *   - corps HTML stylé (QR inline)
 *   - PDF en pièce jointe
 *
 * @param array $billets  Liste d'inscriptions (rows DB) à envoyer dans le même mail.
 * @param array $event    Ligne `evenements`.
 * @param string $to      Email destinataire.
 */
function corpo_mail_send_tickets(array $billets, array $event, string $to, ?string $toName = null): bool {
    $billets = array_values(array_filter($billets, static function (array $b): bool {
        return ($b['statut'] ?? '') === 'confirme' && !empty($b['qr_token']);
    }));
    if (empty($billets)) {
        return false;
    }
    require_once __DIR__ . '/ticket-pdf.php';
    require_once __DIR__ . '/billetterie.php';

    $nbBillets = count($billets);
    $title = htmlspecialchars((string)$event['titre']);
    $dateFmt = function_exists('corpo_format_date_long')
        ? corpo_format_date_long((string)($event['date'] ?? ''), true)
        : (string)($event['date'] ?? '');
    $heure = htmlspecialchars((string)($event['heure'] ?? ''));
    $lieu  = htmlspecialchars((string)($event['lieu']  ?? ''));

    $ticketsHtml = '';
    $attachments = [];
    foreach ($billets as $b) {
        $bid   = (int)$b['id'];
        $stat  = (string)($b['statut'] ?? '');
        $statLabel = [
            'confirme'      => '✓ Confirmé',
            'liste_attente' => '⏳ Liste d\'attente',
            'en_attente'    => '⏳ En attente',
        ][$stat] ?? $stat;

        // QR : on génère un PNG inline (cid) car Gmail/Outlook/Apple Mail bloquent
        // les SVG distants et la plupart des images data:.
        $qrImgTag = '';
        if (!empty($b['qr_token'])) {
            $qrData = function_exists('billet_qr_payload')
                ? billet_qr_payload((string)$b['qr_token'])
                : (string)$b['qr_token'];
            $png = corpo_ticket_pdf_qr_png($qrData, 480);
            if ($png !== null) {
                $cid = 'qr-' . $bid . '@corpoomnes';
                $attachments[] = [
                    'data' => $png,
                    'name' => 'qr-' . $bid . '.png',
                    'mime' => 'image/png',
                    'cid'  => $cid,
                ];
                $qrImgTag = '<img src="cid:' . htmlspecialchars($cid) . '" alt="QR code billet ' . $bid
                          . '" width="120" height="120" style="display:block;margin:0 auto;border:4px solid #5D0282;border-radius:10px;background:#fff;padding:8px">';
            }
        }
        $codeShort = !empty($b['qr_token']) ? strtoupper(substr((string)$b['qr_token'], 0, 8)) : '';

        $ticketsHtml .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
            . 'style="background:#faf7ff;border:1px solid #e8d9f5;border-radius:12px;margin:14px 0">'
            . '<tr>'
            . '<td style="padding:18px 8px;text-align:center;width:140px">'
            . $qrImgTag
            . '</td>'
            . '<td style="padding:18px;vertical-align:top">'
            . '<div style="font-weight:700;color:#5D0282;font-size:11px;letter-spacing:1px;text-transform:uppercase;margin-bottom:6px">Billet n° ' . $bid . '</div>'
            . '<div style="font-weight:700;font-size:15px;color:#1a0040;margin-bottom:4px">' . $title . '</div>'
            . '<div style="color:#5D0282;font-size:13px">' . htmlspecialchars(ucfirst($dateFmt)) . ($heure ? ' • ' . $heure : '') . '</div>'
            . ($lieu ? '<div style="color:#666;font-size:12px;margin-top:4px">📍 ' . $lieu . '</div>' : '')
            . '<div style="margin-top:8px;display:inline-block;padding:3px 10px;background:rgba(93,2,130,.1);border-radius:999px;font-size:11px;color:#5D0282;font-weight:600">' . htmlspecialchars($statLabel) . '</div>'
            . ($codeShort ? '<div style="font-size:10px;color:#888;margin-top:6px;font-family:monospace">Code : ' . $codeShort . '…</div>' : '')
            . '</td></tr></table>';

        try {
            $pdfData = corpo_ticket_pdf_data($b, $event);
            if ($pdfData !== null) {
                $attachments[] = [
                    'data' => $pdfData,
                    'name' => 'billet-' . $bid . '.pdf',
                    'mime' => 'application/pdf',
                ];
            }
        } catch (Throwable $e) {
            corpo_mail_log('[PDF ERR] ' . $e->getMessage());
        }
    }

    $intro = $nbBillets === 1
        ? '<p>Voici ton billet pour <strong>' . $title . '</strong>. Présente le QR code à l\'entrée le jour de l\'événement.</p>'
        : '<p>Voici tes <strong>' . $nbBillets . ' billets</strong> pour <strong>' . $title . '</strong>. Présente chaque QR code à l\'entrée.</p>';
    $intro .= '<p style="font-size:13px;color:#666">Tu peux aussi retrouver tes billets en te connectant à ton compte sur '
            . '<a href="' . htmlspecialchars(corpo_mail_app_url('mes-evenements.php')) . '" style="color:#5D0282">Mes événements</a>.</p>';

    $html = corpo_mail_layout(
        $nbBillets === 1 ? 'Ton billet pour ' . $title : 'Tes billets pour ' . $title,
        $intro . $ticketsHtml,
        corpo_mail_app_url('evenement.php?id=' . (int)$event['id']),
        'Voir l\'événement en ligne'
    );

    return corpo_mail_send(
        $to,
        ($nbBillets === 1 ? 'Ton billet' : "Tes $nbBillets billets") . ' - ' . (string)$event['titre'],
        $html,
        $attachments,
        $toName
    );
}

/**
 * Mail envoyé quand une place se libère et que le participant passe de la file à « confirmé ».
 * Billetterie payante : lien pour finaliser le paiement. Gratuit : envoi du billet QR.
 */
function corpo_mail_send_waitlist_promoted(PDO $pdo, int $inscriptionId): bool
{
    require_once __DIR__ . '/billetterie.php';

    $st = $pdo->prepare(
        'SELECT i.*, e.titre, e.date, e.heure, e.lieu, e.mode_inscription, e.prix, e.slug
           FROM inscriptions_evenement i
           JOIN evenements e ON e.id = i.evenement_id
          WHERE i.id = ?'
    );
    $st->execute([$inscriptionId]);
    $ins = $st->fetch();
    if (!$ins || ($ins['statut'] ?? '') !== 'confirme') {
        return false;
    }

    $event = [
        'id'               => (int)$ins['evenement_id'],
        'titre'            => $ins['titre'],
        'date'             => $ins['date'],
        'heure'            => $ins['heure'],
        'lieu'             => $ins['lieu'],
        'mode_inscription' => $ins['mode_inscription'],
        'prix'             => $ins['prix'],
        'slug'             => $ins['slug'] ?? '',
    ];

    $to = trim((string)($ins['email'] ?? ''));
    if ($to === '' && !empty($ins['user_id'])) {
        $u = $pdo->prepare('SELECT email, prenom, nom FROM users WHERE id = ?');
        $u->execute([(int)$ins['user_id']]);
        $row = $u->fetch();
        if ($row) {
            $to = (string)$row['email'];
            $ins['prenom'] = $ins['prenom'] ?: ($row['prenom'] ?? '');
            $ins['nom']    = $ins['nom']    ?: ($row['nom']    ?? '');
        }
    }
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $name = trim(($ins['prenom'] ?? '') . ' ' . ($ins['nom'] ?? ''));
    $mode = function_exists('evt_normalize_mode')
        ? evt_normalize_mode($ins['mode_inscription'] ?? '')
        : (string)($ins['mode_inscription'] ?? '');
    $isPaid = function_exists('evt_mode_is_paid') && evt_mode_is_paid($mode);
    $paiement = (string)($ins['paiement_statut'] ?? 'aucun');
    $needsPayment = $isPaid && (float)($ins['prix_paye'] ?? 0) <= 0
        && $paiement !== 'paye' && (float)($event['prix'] ?? 0) > 0;

    if ($needsPayment) {
        $title = htmlspecialchars((string)$event['titre']);
        $evtUrl = corpo_mail_app_url('evenement.php?id=' . (int)$event['id']);
        $body = '<p>Bonne nouvelle : une place s\'est libérée pour <strong>' . $title . '</strong>.</p>'
              . '<p>Tu es maintenant <strong>inscrit·e</strong>. Pour recevoir ton billet (QR code), '
              . 'finalise le paiement sur la page de l\'événement dans les meilleurs délais — '
              . 'tant que des places restent disponibles.</p>';
        $html = corpo_mail_layout(
            'Une place pour ' . (string)$event['titre'],
            $body,
            $evtUrl,
            'Finaliser mon inscription'
        );
        return corpo_mail_send(
            $to,
            'Place libérée — finalise ton inscription : ' . (string)$event['titre'],
            $html,
            [],
            $name ?: null
        );
    }

    return billet_send_mail_for_ids($pdo, [$inscriptionId]) > 0;
}

/**
 * Mail de confirmation d'inscription en liste d'attente (sans QR / PDF).
 */
function corpo_mail_send_waitlist_joined(
    array $inscription,
    array $event,
    string $to,
    ?string $toName = null,
    ?PDO $pdo = null
): bool {
    if (($inscription['statut'] ?? '') !== 'liste_attente') {
        return false;
    }
    if (!function_exists('billet_waitlist_position')) {
        require_once __DIR__ . '/billetterie.php';
    }
    $title = htmlspecialchars((string)$event['titre']);
    $dateFmt = function_exists('corpo_format_date_long')
        ? corpo_format_date_long((string)($event['date'] ?? ''), true)
        : htmlspecialchars((string)($event['date'] ?? ''));
    $pos = '';
    if ($pdo instanceof PDO && !empty($inscription['id'])) {
        $p = billet_waitlist_position($pdo, (int)$inscription['id']);
        if ($p) {
            $pos = ' (position #' . (int)$p . ' dans la file)';
        }
    }
    $body = '<p>Tu es inscrit·e en <strong>liste d\'attente</strong> pour <strong>' . $title . '</strong>'
          . htmlspecialchars($pos) . '.</p>'
          . '<p>Date : <strong>' . $dateFmt . '</strong></p>'
          . '<p>Tu recevras un email avec ton billet (QR code) dès qu\'une place se libère.</p>';
    $html = corpo_mail_layout(
        'Liste d\'attente — ' . (string)$event['titre'],
        $body,
        corpo_mail_app_url('evenement.php?id=' . (int)$event['id']),
        'Voir l\'événement'
    );
    return corpo_mail_send(
        $to,
        'Liste d\'attente — ' . (string)$event['titre'],
        $html,
        [],
        $toName
    );
}
