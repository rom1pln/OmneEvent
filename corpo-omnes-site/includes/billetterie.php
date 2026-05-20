<?php
// Fonctions billetterie : tokens QR, inscriptions, file d'attente

require_once __DIR__ . '/env.php';

// modes d'inscription possibles
const EVT_MODES = [
    'aucune',
    'email',
    'connexion',
    'externe',
    'billetterie_email',
    'billetterie_connexion',
];
const EVT_MODES_LABELS = [
    'aucune'                => 'Sans inscription',
    'email'                 => 'Inscription par email (sans compte)',
    'connexion'             => 'Inscription par connexion',
    'externe'               => 'Billetterie externe (lien)',
    'billetterie_email'     => 'Billetterie payante par email',
    'billetterie_connexion' => 'Billetterie payante par connexion',
];

// traduit les anciens noms (interne, billetterie) vers les nouveaux
function evt_normalize_mode(?string $mode): string {
    $mode = (string)$mode;
    return match ($mode) {
        'interne'     => 'connexion',
        'billetterie' => 'billetterie_connexion',
        default       => in_array($mode, EVT_MODES, true) ? $mode : 'aucune',
    };
}

// faut-il être connecté pour ce mode
function evt_mode_requires_login(string $mode): bool {
    return in_array(evt_normalize_mode($mode), ['connexion', 'billetterie_connexion'], true);
}
// est-ce payant
function evt_mode_is_paid(string $mode): bool {
    return in_array(evt_normalize_mode($mode), ['billetterie_email', 'billetterie_connexion'], true);
}
// est-ce qu'on collecte les coordonnées dans un formulaire
function evt_mode_collects_contact(string $mode): bool {
    return in_array(evt_normalize_mode($mode), ['email', 'billetterie_email'], true);
}
// génère un billet avec QR code
function evt_mode_emits_ticket(string $mode): bool {
    return in_array(evt_normalize_mode($mode), ['email', 'connexion', 'billetterie_email', 'billetterie_connexion'], true);
}

// sanitize l'emoji pour le stocker en base
function evt_normalize_icon(?string $icon): string
{
    $icon = trim((string)$icon);
    if ($icon === '') {
        return '🎉';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($icon, 0, 8, 'UTF-8');
    }
    return substr($icon, 0, 32);
}

// affiche l'emoji de l'événement de façon sécurisée
function evt_icon_html(?string $icon, string $class = 'evt-emoji'): string
{
    $icon = evt_normalize_icon($icon);
    return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" role="img" aria-hidden="true">'
        . htmlspecialchars($icon, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</span>';
}

// retourne l'URL de la bannière (gère les chemins relatifs et absolus)
function evt_media_url(?string $path, string $base = ''): ?string
{
    $path = trim((string)$path);
    if ($path === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

// upload ou URL de bannière
function evt_upload_banniere(?string $current = null): ?string
{
    require_once __DIR__ . '/upload-logo.php';
    return uploadLogo('evenements', 'banniere_file', 'banniere_url', $current, 5 * 1024 * 1024);
}

// convertit le format HTML datetime-local en format MySQL
function corpo_datetime_from_input(?string $raw): ?string
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }
    $raw = str_replace('T', ' ', $raw);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) {
        $raw .= ':00';
    }
    try {
        return (new DateTime($raw))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

// inverse : MySQL → input datetime-local
function corpo_datetime_to_input(?string $db): string
{
    $db = trim((string)$db);
    if ($db === '') {
        return '';
    }
    try {
        return (new DateTime($db))->format('Y-m-d\TH:i');
    } catch (Throwable $e) {
        return '';
    }
}

// calcule si les inscriptions sont ouvertes, pas encore ouvertes, ou closes
function evt_inscriptions_fenetre(array $event): array
{
    $now = new DateTime('now');
    $ouv = null;
    $fer = null;
    if (!empty($event['inscriptions_ouvertes_le'])) {
        try {
            $ouv = new DateTime($event['inscriptions_ouvertes_le']);
        } catch (Throwable $e) {
        }
    }
    if (!empty($event['inscriptions_fermees_le'])) {
        try {
            $fer = new DateTime($event['inscriptions_fermees_le']);
        } catch (Throwable $e) {
        }
    }
    if ($ouv && $now < $ouv) {
        return ['open' => false, 'status' => 'before', 'opens_at' => $ouv, 'closes_at' => $fer];
    }
    if ($fer && $now > $fer) {
        return ['open' => false, 'status' => 'after', 'opens_at' => $ouv, 'closes_at' => $fer];
    }
    return ['open' => true, 'status' => 'open', 'opens_at' => $ouv, 'closes_at' => $fer];
}

// message à afficher selon l'état des inscriptions
function evt_inscriptions_fenetre_message(array $fenetre): string
{
    if (!empty($fenetre['open'])) {
        return '';
    }
    $fmt = static function (?DateTime $d): string {
        return $d ? $d->format('d/m/Y à H:i') : '';
    };
    return match ($fenetre['status'] ?? '') {
        'before' => 'Les inscriptions ouvrent le ' . $fmt($fenetre['opens_at'] ?? null) . '.',
        'after'  => 'Les inscriptions sont closes depuis le ' . $fmt($fenetre['closes_at'] ?? null) . '.',
        default  => 'Les inscriptions ne sont pas ouvertes pour le moment.',
    };
}

// applique une demande validée (crée ou met à jour l'événement)
function evt_apply_validation_demande(PDO $pdo, array $demande, array $payload): void
{
    require_once __DIR__ . '/auth.php';
    if (!empty($payload['id'])) {
        evt_update_from_payload($pdo, (int)$payload['id'], $payload);
        return;
    }
    evt_insert_from_demande_payload($pdo, $demande, $payload);
}

// met à jour un événement depuis le payload JSON de validation
function evt_update_from_payload(PDO $pdo, int $eventId, array $p): void
{
    require_once __DIR__ . '/auth.php';
    $modeInsc = evt_normalize_mode($p['mode_inscription'] ?? 'aucune');
    $prix     = evt_mode_is_paid($modeInsc) ? (float)($p['prix'] ?? 0) : 0;
    $structType = (string)($p['structure_type'] ?? 'asso');
    $structId   = isset($p['structure_id']) ? ((int)$p['structure_id'] ?: null) : null;
    [$structType, $structId] = resolveCorpoStructure($pdo, $structType, $structId);
    $ecolesInv = $p['ecoles_invitees'] ?? ['Tous'];
    $campusInv = $p['campus_invites'] ?? ['Tous'];
    if (!is_array($ecolesInv)) {
        $ecolesInv = ['Tous'];
    }
    if (!is_array($campusInv)) {
        $campusInv = ['Tous'];
    }
    $campus = (string)($p['campus'] ?? '');
    if ($campus === '') {
        $c = in_array('Citroën', $campusInv, true);
        $d = in_array('Citadelle', $campusInv, true);
        if ($c && $d) {
            $campus = 'Tous campus';
        } elseif ($c) {
            $campus = 'Campus Citroën';
        } elseif ($d) {
            $campus = 'Campus Citadelle';
        } else {
            $campus = 'Tous campus';
        }
    }
    $pdo->prepare(
        "UPDATE evenements SET
           titre=?, date=?, date_fin=?, heure=?, heure_fin=?, lieu=?, campus=?,
           organisateur=?, structure_type=?, structure_id=?, type=?, description=?,
           mode_inscription=?, lien_billetterie=?, email_contact=?, inscription_message=?,
           places=?, prix=?, max_billets_par_personne=?, inscriptions_ouvertes_le=?, inscriptions_fermees_le=?, ouvert_externes=?,
           icon=?, banniere=?, ecoles_invitees=?, campus_invites=?, affichage_tv=?
         WHERE id=?"
    )->execute([
        trim((string)($p['titre'] ?? '')),
        $p['date'] ?? '',
        !empty($p['date_fin']) ? $p['date_fin'] : null,
        trim((string)($p['heure'] ?? '')),
        !empty($p['heure_fin']) ? trim((string)$p['heure_fin']) : null,
        trim((string)($p['lieu'] ?? '')),
        $campus,
        trim((string)($p['organisateur'] ?? '')),
        $structType,
        $structId,
        $p['type'] ?? 'Corpo',
        trim((string)($p['description'] ?? '')),
        $modeInsc,
        !empty($p['lien_billetterie']) ? trim((string)$p['lien_billetterie']) : null,
        !empty($p['email_contact']) ? trim((string)$p['email_contact']) : null,
        !empty($p['inscription_message']) ? trim((string)$p['inscription_message']) : null,
        (int)($p['places'] ?? 0),
        $prix,
        max(1, min(20, (int)($p['max_billets_par_personne'] ?? 1))),
        corpo_datetime_from_input($p['inscriptions_ouvertes_le'] ?? null),
        corpo_datetime_from_input($p['inscriptions_fermees_le'] ?? null),
        isset($p['ouvert_externes']) ? (int)(bool)$p['ouvert_externes'] : 1,
        evt_normalize_icon($p['icon'] ?? null),
        !empty($p['banniere']) ? trim((string)$p['banniere']) : null,
        json_encode($ecolesInv, JSON_UNESCAPED_UNICODE),
        json_encode($campusInv, JSON_UNESCAPED_UNICODE),
        isset($p['affichage_tv']) ? (int)(bool)$p['affichage_tv'] : 1,
        $eventId,
    ]);
}

// crée un événement depuis une demande validée par la Corpo
function evt_insert_from_demande_payload(PDO $pdo, array $demande, array $p): int
{
    require_once __DIR__ . '/auth.php';
    $titre = trim((string)($p['titre'] ?? ''));
    $slug  = preg_replace('/[^a-z0-9]+/', '-', strtolower($titre)) . '-' . time();
    $modeInsc = evt_normalize_mode($p['mode_inscription'] ?? 'aucune');
    $prix     = evt_mode_is_paid($modeInsc) ? (float)($p['prix'] ?? 0) : 0;
    $structType = (string)($demande['structure_type'] ?? $p['structure_type'] ?? 'asso');
    $structId   = (int)($demande['structure_id'] ?? $p['structure_id'] ?? 0) ?: null;
    [$structType, $structId] = resolveCorpoStructure($pdo, $structType, $structId);
    $ecolesInv = $p['ecoles_invitees'] ?? ['Tous'];
    $campusInv = $p['campus_invites'] ?? ['Tous'];
    if (!is_array($ecolesInv)) {
        $ecolesInv = ['Tous'];
    }
    if (!is_array($campusInv)) {
        $campusInv = ['Tous'];
    }
    $campus = (string)($p['campus'] ?? 'Tous campus');
    $pdo->prepare(
        "INSERT INTO evenements
           (slug, titre, date, date_fin, heure, heure_fin, lieu, campus,
            organisateur, structure_type, structure_id, type, description,
            mode_inscription, lien_billetterie, email_contact, inscription_message,
            places, prix, max_billets_par_personne, inscriptions_ouvertes_le, inscriptions_fermees_le, ouvert_externes, icon, banniere,
            ecoles_invitees, campus_invites, affichage_tv, statut, auteur_id)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'publie',?)"
    )->execute([
        $slug,
        $titre,
        $p['date'] ?? '',
        !empty($p['date_fin']) ? $p['date_fin'] : null,
        trim((string)($p['heure'] ?? '')),
        !empty($p['heure_fin']) ? trim((string)$p['heure_fin']) : null,
        trim((string)($p['lieu'] ?? '')),
        $campus,
        trim((string)($p['organisateur'] ?? 'Corpo Omnes Lyon')),
        $structType,
        $structId,
        $p['type'] ?? 'Corpo',
        trim((string)($p['description'] ?? '')),
        $modeInsc,
        !empty($p['lien_billetterie']) ? trim((string)$p['lien_billetterie']) : null,
        !empty($p['email_contact']) ? trim((string)$p['email_contact']) : null,
        !empty($p['inscription_message']) ? trim((string)$p['inscription_message']) : null,
        (int)($p['places'] ?? 0),
        $prix,
        max(1, min(20, (int)($p['max_billets_par_personne'] ?? 1))),
        corpo_datetime_from_input($p['inscriptions_ouvertes_le'] ?? null),
        corpo_datetime_from_input($p['inscriptions_fermees_le'] ?? null),
        isset($p['ouvert_externes']) ? (int)(bool)$p['ouvert_externes'] : 1,
        evt_normalize_icon($p['icon'] ?? null),
        !empty($p['banniere']) ? trim((string)$p['banniere']) : null,
        json_encode($ecolesInv, JSON_UNESCAPED_UNICODE),
        json_encode($campusInv, JSON_UNESCAPED_UNICODE),
        isset($p['affichage_tv']) ? (int)(bool)$p['affichage_tv'] : 1,
        (int)($demande['user_id'] ?? 0) ?: null,
    ]);
    return (int)$pdo->lastInsertId();
}

// vérifie si l'user peut s'inscrire à cet événement
// retourne ['ok'=>bool, 'reason'=>string|null]
function evt_user_can_register(array $event, ?array $user = null): array {
    $mode = evt_normalize_mode($event['mode_inscription'] ?? 'aucune');
    $requiresLogin = evt_mode_requires_login($mode);
    $ouvertExt = (int)($event['ouvert_externes'] ?? 1) === 1;
    $ecolesInv = [];
    if (!empty($event['ecoles_invitees'])) {
        $d = json_decode($event['ecoles_invitees'], true);
        if (is_array($d)) $ecolesInv = $d;
    }
    $restreint = !empty($ecolesInv) && !in_array('Tous', $ecolesInv, true);

    // connexion requise → faut être connecté avec la bonne école
    if ($requiresLogin) {
        if (!$user) return ['ok' => false, 'reason' => 'login_required'];
        if ($restreint) {
            $userEcole = (string)($user['ecole'] ?? '');
            if (!in_array($userEcole, $ecolesInv, true)) {
                return ['ok' => false, 'reason' => 'ecole_non_eligible'];
            }
        }
        return ['ok' => true, 'reason' => null];
    }

    // mode email : ouvert à tous ou réservé aux étudiants connectés
    if ($mode === 'email' || $mode === 'billetterie_email') {
        if ($ouvertExt) return ['ok' => true, 'reason' => null];
        if (!$user) return ['ok' => false, 'reason' => 'login_required_no_externes'];
        if ($restreint) {
            $userEcole = (string)($user['ecole'] ?? '');
            if (!in_array($userEcole, $ecolesInv, true)) {
                return ['ok' => false, 'reason' => 'ecole_non_eligible'];
            }
        }
        return ['ok' => true, 'reason' => null];
    }

    return ['ok' => true, 'reason' => null];
}

// tarifs actifs d'un événement ([] si la table n'existe pas encore)
function tarifs_pour_event(PDO $pdo, int $evtId): array {
    try {
        $st = $pdo->prepare("SELECT * FROM evenement_tarifs WHERE evenement_id=? AND statut='actif' ORDER BY position ASC, id ASC");
        $st->execute([$evtId]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) { return []; }
}

// filtre les tarifs selon l'école et le statut de connexion de l'user
function tarifs_disponibles(array $tarifs, ?array $user = null): array {
    return array_values(array_filter($tarifs, function ($t) use ($user) {
        if ((int)($t['reserve_membres'] ?? 0) === 1 && !$user) return false;
        if (!empty($t['ecoles_eligibles'])) {
            $list = json_decode($t['ecoles_eligibles'], true);
            if (is_array($list) && !empty($list) && !in_array('Tous', $list, true)) {
                $userEcole = $user['ecole'] ?? '';
                if (!in_array($userEcole, $list, true)) return false;
            }
        }
        return true;
    }));
}

// cherche un code promo applicable (lié à l'event ou global)
function code_promo_lookup(PDO $pdo, string $code, int $evtId, ?int $tarifId = null): ?array {
    try {
        $st = $pdo->prepare(
            "SELECT * FROM codes_promo
              WHERE code = ?
                AND statut = 'actif'
                AND (evenement_id IS NULL OR evenement_id = ?)
                AND (expire_le IS NULL OR expire_le >= NOW())
                AND (utilisations_max IS NULL OR utilisations_count < utilisations_max)
              ORDER BY evenement_id IS NOT NULL DESC, tarif_id IS NOT NULL DESC
              LIMIT 5"
        );
        $st->execute([strtoupper(trim($code)), $evtId]);
        $candidates = $st->fetchAll();
    } catch (Throwable $e) { return null; }

    foreach ($candidates as $c) {
        if ($c['tarif_id'] && $tarifId && (int)$c['tarif_id'] !== $tarifId) continue;
        if ($c['tarif_id'] && !$tarifId) continue; // code lié à un tarif mais pas de tarif sélectionné
        return $c;
    }
    return null;
}

// applique la réduction d'un code promo sur un prix
function code_promo_apply(array $code, float $prix): array {
    if ($code['type'] === 'pourcentage') {
        $reduc = round($prix * ((float)$code['valeur'] / 100), 2);
    } else {
        $reduc = min($prix, (float)$code['valeur']);
    }
    return ['prix_unitaire' => max(0.0, round($prix - $reduc, 2)), 'reduction' => $reduc];
}

// incrémente le compteur du code promo
function code_promo_consume(PDO $pdo, int $codeId, int $quantite = 1): void {
    try {
        $pdo->prepare("UPDATE codes_promo SET utilisations_count = utilisations_count + ? WHERE id = ?")
            ->execute([$quantite, $codeId]);
    } catch (Throwable $e) {}
}

// génère un token aléatoire lisible pour le QR (base32, 32 chars)
function billet_generate_token(): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $bytes = random_bytes(20);
    $out = '';
    for ($i = 0; $i < 32; $i++) {
        $out .= $alphabet[ord($bytes[$i % 20]) & 31];
    }
    return $out;
}

// signe le token avec le secret de l'app
function billet_sign(string $token): string {
    $secret = corpo_env('APP_SECRET', 'corpo-omnes-default-secret');
    return substr(hash_hmac('sha256', $token, $secret), 0, 16);
}

// vérifie la signature du token
function billet_verify(string $token, string $signature): bool {
    if (!preg_match('/^[A-Z0-9]{8,64}$/', $token)) return false;
    return hash_equals(billet_sign($token), $signature);
}

// payload du QR code : TOKEN.SIGNATURE
function billet_qr_payload(string $token): string {
    return $token . '.' . billet_sign($token);
}

// décode le payload du QR et vérifie la signature
function billet_qr_parse(string $payload): ?array {
    if (!str_contains($payload, '.')) return null;
    [$token, $sig] = explode('.', $payload, 2);
    if (!billet_verify($token, $sig)) return null;
    return ['token' => $token, 'sig' => $sig];
}

// nombre de billets actifs pour un event (gère l'absence de paiement_statut si pas migré)
function billet_count_actifs(PDO $pdo, int $evtId): int {
    try {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM inscriptions_evenement
              WHERE evenement_id = ?
                AND statut IN ('confirme','en_attente')
                AND (paiement_statut IN ('aucun','paye','en_attente') OR paiement_statut IS NULL)"
        );
        $st->execute([$evtId]);
        return (int)$st->fetchColumn();
    } catch (PDOException $e) {
        // fallback sans paiement_statut (avant migration)
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM inscriptions_evenement
              WHERE evenement_id = ? AND statut IN ('confirme','en_attente')"
        );
        $st->execute([$evtId]);
        return (int)$st->fetchColumn();
    }
}

// calcule les places dispo en temps réel (pas le compteur cache)
function billet_event_places_state(PDO $pdo, int $evtId): array
{
    $st = $pdo->prepare('SELECT places FROM evenements WHERE id = ?');
    $st->execute([$evtId]);
    $places = (int)$st->fetchColumn();
    $actifs = billet_count_actifs($pdo, $evtId);
    if ($places <= 0) {
        return ['places' => 0, 'actifs' => $actifs, 'dispo' => null, 'complet' => false];
    }
    return [
        'places'  => $places,
        'actifs'  => $actifs,
        'dispo'   => max(0, $places - $actifs),
        'complet' => $actifs >= $places,
    ];
}

// position dans la file d'attente (null si pas en liste d'attente)
function billet_waitlist_position(PDO $pdo, int $inscriptionId): ?int
{
    $st = $pdo->prepare(
        'SELECT waitlist_position FROM inscriptions_evenement
          WHERE id = ? AND statut = \'liste_attente\''
    );
    $st->execute([$inscriptionId]);
    $pos = $st->fetchColumn();
    return $pos !== false && $pos !== null ? (int)$pos : null;
}

// confirme ou met en liste d'attente selon les places restantes
function billet_compute_statut(PDO $pdo, int $evtId): string {
    $ev = $pdo->prepare("SELECT places FROM evenements WHERE id=?");
    $ev->execute([$evtId]);
    $places = (int)$ev->fetchColumn();
    if ($places <= 0) return 'confirme'; // pas de limite de places
    return billet_count_actifs($pdo, $evtId) >= $places ? 'liste_attente' : 'confirme';
}

// remet à jour le compteur d'inscrits sur l'événement
function billet_recompute_compteur(PDO $pdo, int $evtId): void {
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM inscriptions_evenement
          WHERE evenement_id = ? AND statut = 'confirme'"
    );
    $st->execute([$evtId]);
    $n = (int)$st->fetchColumn();
    $pdo->prepare("UPDATE evenements SET inscrits = ? WHERE id = ?")->execute([$n, $evtId]);
}

/**
 * Recalcule la position dans la file d'attente pour un événement.
 */
function billet_recompute_waitlist(PDO $pdo, int $evtId): void {
    $list = $pdo->prepare(
        "SELECT id FROM inscriptions_evenement
          WHERE evenement_id = ? AND statut = 'liste_attente'
          ORDER BY created_at ASC, id ASC"
    );
    $list->execute([$evtId]);
    $rows = $list->fetchAll(PDO::FETCH_COLUMN);
    $upd = $pdo->prepare("UPDATE inscriptions_evenement SET waitlist_position = ? WHERE id = ?");
    foreach ($rows as $i => $id) {
        $upd->execute([$i + 1, $id]);
    }
}

// génère le token QR s'il est pas encore créé
function billet_ensure_qr_token(PDO $pdo, int $inscriptionId): void
{
    $st = $pdo->prepare('SELECT qr_token FROM inscriptions_evenement WHERE id = ?');
    $st->execute([$inscriptionId]);
    $existing = $st->fetchColumn();
    if ($existing !== false && $existing !== null && $existing !== '') {
        return;
    }
    for ($i = 0; $i < 5; $i++) {
        $token = billet_generate_token();
        try {
            $pdo->prepare(
                'UPDATE inscriptions_evenement SET qr_token = ? WHERE id = ? AND (qr_token IS NULL OR qr_token = \'\')'
            )->execute([$token, $inscriptionId]);
            return;
        } catch (PDOException $e) {
            if (!str_contains($e->getMessage(), 'qr_token')) {
                throw $e;
            }
        }
    }
}

// passe une inscription de la file d'attente à confirmée et envoie un mail
function billet_promote_waitlist(PDO $pdo, int $inscriptionId): bool
{
    $st = $pdo->prepare(
        'SELECT id, evenement_id, statut FROM inscriptions_evenement WHERE id = ?'
    );
    $st->execute([$inscriptionId]);
    $row = $st->fetch();
    if (!$row || ($row['statut'] ?? '') !== 'liste_attente') {
        return false;
    }
    $evtId = (int)$row['evenement_id'];
    $pdo->prepare(
        "UPDATE inscriptions_evenement SET statut = 'confirme', waitlist_position = NULL WHERE id = ?"
    )->execute([$inscriptionId]);
    billet_ensure_qr_token($pdo, $inscriptionId);
    billet_recompute_compteur($pdo, $evtId);
    billet_recompute_waitlist($pdo, $evtId);
    billet_notify_waitlist_promoted($pdo, $inscriptionId);
    return true;
}

/**
 * Promeut le premier de la file d'attente (désistement / annulation).
 * Retourne l'id de l'inscription promue ou null.
 */
function billet_promote_first_waitlist(PDO $pdo, int $evtId): ?int
{
    $st = $pdo->prepare(
        "SELECT id FROM inscriptions_evenement
          WHERE evenement_id = ? AND statut = 'liste_attente'
          ORDER BY waitlist_position ASC, created_at ASC, id ASC
          LIMIT 1"
    );
    $st->execute([$evtId]);
    $id = $st->fetchColumn();
    if (!$id) {
        return null;
    }
    return billet_promote_waitlist($pdo, (int)$id) ? (int)$id : null;
}

/** Notifie après promotion (email billet ou lien paiement). */
function billet_notify_waitlist_promoted(PDO $pdo, int $inscriptionId): void
{
    if (!function_exists('corpo_mail_send_waitlist_promoted')) {
        require_once __DIR__ . '/mailer.php';
    }
    try {
        corpo_mail_send_waitlist_promoted($pdo, $inscriptionId);
    } catch (Throwable $e) {
        corpo_mail_log('[waitlist promote mail ERR] ins=' . $inscriptionId . ' ' . $e->getMessage());
    }
}

/**
 * Annule l'inscription d'un utilisateur à un événement (avec promotion auto de la file).
 */
function billet_cancel_for_user_event(PDO $pdo, int $userId, int $evtId): bool
{
    $st = $pdo->prepare(
        "SELECT id FROM inscriptions_evenement
          WHERE user_id = ? AND evenement_id = ?
            AND statut IN ('confirme','liste_attente','en_attente')
          ORDER BY FIELD(statut, 'confirme', 'en_attente', 'liste_attente'), id DESC
          LIMIT 1"
    );
    $st->execute([$userId, $evtId]);
    $insId = $st->fetchColumn();
    return $insId ? billet_cancel($pdo, (int)$insId) : false;
}

/**
 * Finalise le paiement d'une inscription déjà confirmée (ex. promue depuis la file d'attente).
 */
function billet_finalize_paid(
    PDO $pdo,
    int $inscriptionId,
    float $prixPaye,
    string $paiementProvider,
    ?int $tarifId = null,
    ?string $codePromo = null
): bool {
    $st = $pdo->prepare('SELECT * FROM inscriptions_evenement WHERE id = ?');
    $st->execute([$inscriptionId]);
    $row = $st->fetch();
    if (!$row || ($row['statut'] ?? '') !== 'confirme') {
        return false;
    }

    $token = $row['qr_token'] ?? null;
    if ($token === null || $token === '') {
        for ($i = 0; $i < 5; $i++) {
            $token = billet_generate_token();
            try {
                $pdo->prepare('UPDATE inscriptions_evenement SET qr_token = ? WHERE id = ? AND (qr_token IS NULL OR qr_token = \'\')')
                    ->execute([$token, $inscriptionId]);
                break;
            } catch (PDOException $e) {
                if (!str_contains($e->getMessage(), 'qr_token')) {
                    throw $e;
                }
            }
        }
    }

    static $extColsPaid = null;
    if ($extColsPaid === null) {
        $extColsPaid = ['tarif' => false, 'promo' => false];
        try {
            $check = $pdo->query("SHOW COLUMNS FROM inscriptions_evenement LIKE 'tarif_id'");
            $extColsPaid['tarif'] = $check && $check->fetchColumn() !== false;
            $check = $pdo->query("SHOW COLUMNS FROM inscriptions_evenement LIKE 'code_promo_utilise'");
            $extColsPaid['promo'] = $check && $check->fetchColumn() !== false;
        } catch (Throwable $e) {
        }
    }

    $sql = 'UPDATE inscriptions_evenement SET prix_paye = ?, paiement_statut = \'paye\', paiement_provider = ?';
    $params = [$prixPaye, $paiementProvider];
    if ($extColsPaid['tarif'] && $tarifId) {
        $sql .= ', tarif_id = ?';
        $params[] = $tarifId;
    }
    if ($extColsPaid['promo'] && $codePromo) {
        $sql .= ', code_promo_utilise = ?';
        $params[] = $codePromo;
    }
    $sql .= ' WHERE id = ?';
    $params[] = $inscriptionId;
    $pdo->prepare($sql)->execute($params);
    billet_recompute_compteur($pdo, (int)$row['evenement_id']);
    return true;
}

/**
 * Trouve une inscription confirmée en attente de paiement (promotion file d'attente).
 */
function billet_find_unpaid_confirmed(PDO $pdo, int $evtId, ?int $userId, ?string $email = null): ?int
{
    if ($userId) {
        $st = $pdo->prepare(
            "SELECT id FROM inscriptions_evenement
              WHERE evenement_id = ? AND user_id = ? AND statut = 'confirme'
                AND (paiement_statut IS NULL OR paiement_statut = 'aucun')
                AND COALESCE(prix_paye, 0) = 0
              ORDER BY id DESC LIMIT 1"
        );
        $st->execute([$evtId, $userId]);
    } elseif ($email !== null && $email !== '') {
        $st = $pdo->prepare(
            "SELECT id FROM inscriptions_evenement
              WHERE evenement_id = ? AND email = ? AND statut = 'confirme'
                AND (paiement_statut IS NULL OR paiement_statut = 'aucun')
                AND COALESCE(prix_paye, 0) = 0
              ORDER BY id DESC LIMIT 1"
        );
        $st->execute([$evtId, $email]);
    } else {
        return null;
    }
    $id = $st->fetchColumn();
    return $id ? (int)$id : null;
}

/**
 * Crée ou finalise les billets après un paiement réussi (webhook / retour checkout).
 *
 * @return int[] IDs d'inscriptions concernées
 */
function billet_fulfill_from_transaction(PDO $pdo, array $transaction, array $payload): array
{
    $evtId = (int)$transaction['evenement_id'];
    $qte = max(1, (int)($payload['quantite'] ?? 1));
    $unit = isset($payload['prix_unitaire_billet'])
        ? (float)$payload['prix_unitaire_billet']
        : (float)$transaction['montant'] / $qte;
    $tarifId = !empty($payload['tarif_id']) ? (int)$payload['tarif_id'] : null;
    $codePromo = $payload['code_promo'] ?? null;
    $provider = (string)($transaction['provider'] ?? 'sumup');
    $insId = !empty($payload['inscription_id']) ? (int)$payload['inscription_id'] : 0;

    $createdIds = [];
    if ($insId > 0) {
        if (billet_finalize_paid($pdo, $insId, $unit, $provider, $tarifId, $codePromo)) {
            $createdIds[] = $insId;
        }
        return $createdIds;
    }

    for ($i = 0; $i < $qte; $i++) {
        $bid = billet_create(
            $pdo,
            $evtId,
            $transaction['user_id'] ? (int)$transaction['user_id'] : null,
            [
                'email'  => $transaction['email'],
                'nom'    => $payload['nom']    ?? '',
                'prenom' => $payload['prenom'] ?? '',
            ],
            $unit,
            'paye',
            $provider,
            $tarifId,
            $codePromo
        );
        if ($bid) {
            $createdIds[] = $bid;
        }
    }
    return $createdIds;
}

/**
 * Crée un billet (inscription) après contrôles. Retourne l'id ou null.
 * Pour mode `interne` ou `billetterie`.
 *
 * @param array $contact ['email'=>..., 'nom'=>..., 'prenom'=>...]
 * @param float $prixPaye Prix payé (0 pour gratuit)
 */
function billet_create(PDO $pdo, int $evtId, ?int $userId, array $contact, float $prixPaye = 0.0, string $paiementStatut = 'aucun', ?string $paiementProvider = null, ?int $tarifId = null, ?string $codePromo = null): ?int {
    // Vérifie l'événement
    $ev = $pdo->prepare("SELECT * FROM evenements WHERE id=?");
    $ev->execute([$evtId]);
    $event = $ev->fetch();
    if (!$event) return null;

    if (!evt_inscriptions_fenetre($event)['open']) {
        return null;
    }

    // Statut selon places
    $statut = billet_compute_statut($pdo, $evtId);

    // Détecte la présence des colonnes optionnelles (tarif_id, code_promo_utilise)
    static $extCols = null;
    if ($extCols === null) {
        $extCols = ['tarif' => false, 'promo' => false];
        try {
            $check = $pdo->query("SHOW COLUMNS FROM inscriptions_evenement LIKE 'tarif_id'");
            $extCols['tarif'] = $check && $check->fetchColumn() !== false;
            $check = $pdo->query("SHOW COLUMNS FROM inscriptions_evenement LIKE 'code_promo_utilise'");
            $extCols['promo'] = $check && $check->fetchColumn() !== false;
        } catch (Throwable $e) {}
    }

    // Pas de QR ni de « billet » tant que l'inscription n'est pas confirmée
    $withQr = ($statut === 'confirme');

    for ($i = 0; $i < 5; $i++) {
        $token = $withQr ? billet_generate_token() : null;
        try {
            $cols = 'user_id, evenement_id, statut, email, nom, prenom,
                     prix_paye, paiement_statut, paiement_provider';
            $vals = '?,?,?,?,?,?,?,?,?';
            $params = [
                $userId ?: null,
                $evtId,
                $statut,
            ];
            if ($withQr) {
                $cols = 'user_id, evenement_id, statut, qr_token, email, nom, prenom,
                         prix_paye, paiement_statut, paiement_provider';
                $vals = '?,?,?,?,?,?,?,?,?,?';
                $params[] = $token;
            }
            $params = array_merge($params, [
                $contact['email'] ?? null,
                $contact['nom']   ?? null,
                $contact['prenom'] ?? null,
                $prixPaye,
                $paiementStatut,
                $paiementProvider,
            ]);
            if ($extCols['tarif']) { $cols .= ", tarif_id"; $vals .= ",?"; $params[] = $tarifId; }
            if ($extCols['promo']) { $cols .= ", code_promo_utilise"; $vals .= ",?"; $params[] = $codePromo; }

            $pdo->prepare("INSERT INTO inscriptions_evenement ($cols) VALUES ($vals)")->execute($params);
            $id = (int)$pdo->lastInsertId();
            if ($statut === 'liste_attente') billet_recompute_waitlist($pdo, $evtId);
            billet_recompute_compteur($pdo, $evtId);
            return $id;
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            // Collision token → retry
            if (str_contains($msg, 'qr_token')) continue;

            // Ancienne contrainte UNIQUE(user_id, evenement_id) toujours présente → on la drop puis on retente
            if (str_contains($msg, 'uniq_insc_evt') || str_contains($msg, "for key 'inscriptions_evenement.uniq_insc_evt'") || str_contains($msg, "clef 'inscriptions_evenement.uniq_insc_evt'")) {
                try {
                    $pdo->exec("ALTER TABLE inscriptions_evenement DROP INDEX uniq_insc_evt");
                    // retry l'INSERT immédiatement
                    continue;
                } catch (Throwable $eAlt) {
                    throw new PDOException(
                        "Une ancienne contrainte UNIQUE empêche les multi-billets. "
                      . "Va sur admin/migrate.php et clique « ⚡ Tout appliquer » pour la supprimer. "
                      . "(détail : " . $eAlt->getMessage() . ")",
                        (int)$e->getCode()
                    );
                }
            }

            // Colonnes manquantes (migration billetterie non exécutée) → fallback minimal
            if (str_contains($msg, "Unknown column") || (str_contains($msg, "Champ ") && str_contains($msg, "inconnu"))) {
                try {
                    $pdo->prepare(
                        "INSERT INTO inscriptions_evenement (user_id, evenement_id, statut) VALUES (?,?,?)"
                    )->execute([$userId ?: null, $evtId, $statut === 'liste_attente' ? 'liste_attente' : 'confirme']);
                    $id = (int)$pdo->lastInsertId();
                    if ($statut === 'liste_attente') billet_recompute_waitlist($pdo, $evtId);
                    billet_recompute_compteur($pdo, $evtId);
                    return $id;
                } catch (PDOException $e2) {
                    throw $e2;
                }
            }
            throw $e;
        }
    }
    return null;
}

/**
 * Annule un billet (statut → annule) et promeut éventuellement la file d'attente.
 */
function billet_cancel(PDO $pdo, int $inscriptionId): bool {
    $row = $pdo->prepare("SELECT evenement_id, statut FROM inscriptions_evenement WHERE id=?");
    $row->execute([$inscriptionId]);
    $r = $row->fetch();
    if (!$r) return false;

    $pdo->prepare(
        "UPDATE inscriptions_evenement SET statut='annule', waitlist_position=NULL WHERE id=?"
    )->execute([$inscriptionId]);

    $evtId = (int)$r['evenement_id'];
    billet_recompute_compteur($pdo, $evtId);

    if (in_array($r['statut'], ['confirme', 'en_attente'], true)) {
        billet_promote_first_waitlist($pdo, $evtId);
    } else {
        billet_recompute_waitlist($pdo, $evtId);
    }
    return true;
}

/**
 * URL absolue de l'image QR (utilise un service externe pour la génération).
 * Le QR encode TOKEN.SIGNATURE pour validation hors-ligne possible.
 */
function billet_qr_image_url(string $token, int $size = 280): string {
    $payload = billet_qr_payload($token);
    // Endpoint local : aucun appel externe (pas de Tracking Prevention, pas de blocage CDN).
    // Le chemin est relatif à la racine du site → fonctionne depuis n'importe quelle page.
    return '/api/qr-image.php?p=' . urlencode($payload) . '&s=' . (int)$size;
}

/**
 * Vérifie l'éligibilité au scan : retourne ['ok'=>bool, 'msg'=>string, 'inscription'=>row|null].
 */
function billet_scan_lookup(PDO $pdo, string $qrPayload, ?int $evtId = null): array {
    $raw = strtoupper(trim($qrPayload));
    $parsed = billet_qr_parse($qrPayload);
    if (!$parsed) {
        // Fallback 1 : payload du format "TOKEN.SIG" mais la signature ne valide pas
        // (APP_SECRET changé, billet généré avant migration, etc.) - on extrait le token avant le point
        if (str_contains($raw, '.')) {
            $tok = strtok($raw, '.');
            if ($tok && preg_match('/^[A-Z0-9]{8,64}$/', $tok)) {
                $parsed = ['token' => $tok, 'sig' => ''];
            }
        }
        // Fallback 2 : payload = token nu
        if (!$parsed && preg_match('/^[A-Z0-9]{8,64}$/', $raw)) {
            $parsed = ['token' => $raw, 'sig' => ''];
        }
        // Fallback 3 : URL contenant un token en query string (ex: ?qr=ABC123)
        if (!$parsed && preg_match('/[?&](qr|token)=([A-Z0-9.]{8,128})/i', $qrPayload, $m)) {
            $tok = strtok(strtoupper($m[2]), '.');
            if ($tok && preg_match('/^[A-Z0-9]{8,64}$/', $tok)) {
                $parsed = ['token' => $tok, 'sig' => ''];
            }
        }
        if (!$parsed) {
            return ['ok' => false, 'msg' => 'QR code non reconnu (payload : ' . substr($raw, 0, 30) . '…)', 'inscription' => null];
        }
    }

    // 1) Match exact (cas normal : QR complet ou token nu)
    $st = $pdo->prepare(
        "SELECT i.*, e.titre, e.date, e.lieu
           FROM inscriptions_evenement i
           JOIN evenements e ON e.id = i.evenement_id
          WHERE i.qr_token = ?"
    );
    $st->execute([$parsed['token']]);
    $row = $st->fetch();
    if ($row) return ['ok' => true, 'msg' => 'OK', 'inscription' => $row];

    // 2) Match par préfixe (saisie manuelle des 8 premiers caractères affichés sur le billet)
    if (strlen($parsed['token']) >= 6 && strlen($parsed['token']) < 64) {
        $like = $parsed['token'] . '%';
        $sql = "SELECT i.*, e.titre, e.date, e.lieu
                  FROM inscriptions_evenement i
                  JOIN evenements e ON e.id = i.evenement_id
                 WHERE i.qr_token LIKE ?";
        $params = [$like];
        if ($evtId) { $sql .= " AND i.evenement_id = ?"; $params[] = $evtId; }
        $sql .= " LIMIT 2";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        if (count($rows) === 1) return ['ok' => true, 'msg' => 'OK (préfixe)', 'inscription' => $rows[0]];
        if (count($rows) > 1) {
            return ['ok' => false, 'msg' => 'Code ambigu : plusieurs billets correspondent. Saisis plus de caractères.', 'inscription' => null];
        }
    }

    return ['ok' => false, 'msg' => 'Billet introuvable.', 'inscription' => null];
}

/**
 * Marque un billet comme scanné (utilisé) si pas déjà fait.
 * Retourne ['ok'=>bool, 'msg'=>string, 'already'=>bool, 'inscription'=>row].
 */
function billet_scan_mark(PDO $pdo, int $inscriptionId, int $scannerUserId): array {
    $st = $pdo->prepare(
        "SELECT i.*, e.titre, e.date FROM inscriptions_evenement i
           JOIN evenements e ON e.id = i.evenement_id
          WHERE i.id = ?"
    );
    $st->execute([$inscriptionId]);
    $row = $st->fetch();
    if (!$row) return ['ok'=>false,'msg'=>'Billet introuvable','already'=>false,'inscription'=>null];

    if ($row['statut'] === 'annule' || $row['statut'] === 'refuse') {
        return ['ok'=>false,'msg'=>'Billet annulé/refusé.','already'=>false,'inscription'=>$row];
    }
    if ($row['statut'] === 'liste_attente') {
        return ['ok'=>false,'msg'=>'Billet en liste d\'attente - non encore validé.','already'=>false,'inscription'=>$row];
    }
    if (!empty($row['qr_scanned_at'])) {
        return ['ok'=>false,'msg'=>'Billet déjà utilisé le ' . date('d/m/Y H:i', strtotime($row['qr_scanned_at'])),
                'already'=>true,'inscription'=>$row];
    }
    $pdo->prepare(
        "UPDATE inscriptions_evenement SET qr_scanned_at = NOW(), qr_scanned_by = ? WHERE id = ?"
    )->execute([$scannerUserId, $inscriptionId]);
    $row['qr_scanned_at'] = date('Y-m-d H:i:s');
    return ['ok'=>true,'msg'=>'Billet validé ✓','already'=>false,'inscription'=>$row];
}

// envoi des mails de billets (HTML + PDF en pièce jointe)

/**
 * Envoie un (ou plusieurs) billet(s) par mail à partir de leurs IDs.
 * - Charge les inscriptions + l'événement
 * - Regroupe les destinataires par email (un mail par adresse)
 * - Si $txId est fourni, marque la transaction `mail_sent=true` dans son payload
 *   pour éviter un double envoi (webhook + retour callback).
 *
 * @return int  Nombre d'emails envoyés.
 */
function billet_send_mail_for_ids(PDO $pdo, array $billetIds, ?int $txId = null): int {
    $billetIds = array_values(array_unique(array_filter(array_map('intval', $billetIds))));
    if (empty($billetIds)) {
        return 0;
    }

    // Garde anti-doublon basée sur paiement_transactions.payload['mail_sent']
    if ($txId !== null && $txId > 0) {
        $stTx = $pdo->prepare("SELECT payload FROM paiement_transactions WHERE id = ? LIMIT 1");
        $stTx->execute([$txId]);
        $row = $stTx->fetch();
        if ($row) {
            $pl = json_decode($row['payload'] ?? '{}', true) ?: [];
            if (!empty($pl['mail_sent'])) return 0;
        }
    }

    $ph = implode(',', array_map('intval', $billetIds));
    $st = $pdo->query("SELECT * FROM inscriptions_evenement WHERE id IN ($ph) ORDER BY id");
    $billets = $st ? $st->fetchAll() : [];
    if (empty($billets)) return 0;

    $evId = (int)$billets[0]['evenement_id'];
    $stE = $pdo->prepare("SELECT * FROM evenements WHERE id = ? LIMIT 1");
    $stE->execute([$evId]);
    $event = $stE->fetch();
    if (!$event) return 0;

    require_once __DIR__ . '/mailer.php';

    // Groupe par email destinataire (pour mode invité : tous au même email
    // d'achat ; pour mode connecté : email du compte).
    $byEmail = [];
    foreach ($billets as $b) {
        $email = trim((string)($b['email'] ?? ''));
        if ($email === '' && !empty($b['user_id'])) {
            $u = $pdo->prepare("SELECT email, prenom, nom FROM users WHERE id = ?");
            $u->execute([(int)$b['user_id']]);
            $row = $u->fetch();
            if ($row) {
                $email      = (string)$row['email'];
                $b['email']  = $email;
                $b['prenom'] = $b['prenom'] ?: ($row['prenom'] ?? '');
                $b['nom']    = $b['nom']    ?: ($row['nom']    ?? '');
            }
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
        $byEmail[$email][] = $b;
    }

    $sent = 0;
    foreach ($byEmail as $email => $bs) {
        $name = trim(($bs[0]['prenom'] ?? '') . ' ' . ($bs[0]['nom'] ?? ''));
        $waitlist = [];
        $tickets = [];
        foreach ($bs as $b) {
            $st = (string)($b['statut'] ?? '');
            if ($st === 'liste_attente') {
                $waitlist[] = $b;
            } elseif ($st === 'confirme' && !empty($b['qr_token'])) {
                $tickets[] = $b;
            }
        }
        foreach ($waitlist as $w) {
            if (corpo_mail_send_waitlist_joined($w, $event, $email, $name ?: null, $pdo)) {
                $sent++;
            }
        }
        if (!empty($tickets) && corpo_mail_send_tickets($tickets, $event, $email, $name ?: null)) {
            $sent++;
        }
    }

    if ($txId !== null && $txId > 0 && $sent > 0) {
        try {
            $stTx = $pdo->prepare("SELECT payload FROM paiement_transactions WHERE id = ?");
            $stTx->execute([$txId]);
            $pl = json_decode($stTx->fetchColumn() ?: '{}', true) ?: [];
            $pl['mail_sent']    = true;
            $pl['mail_sent_at'] = date('c');
            $pdo->prepare("UPDATE paiement_transactions SET payload = ? WHERE id = ?")
                ->execute([json_encode($pl, JSON_UNESCAPED_UNICODE), $txId]);
        } catch (Throwable $e) { /* non bloquant */ }
    }
    return $sent;
}
