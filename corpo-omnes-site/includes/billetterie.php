<?php

require_once __DIR__ . '/env.php';

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

function evt_normalize_mode(?string $mode): string {
    $mode = (string)$mode;
    return match ($mode) {
        'interne'     => 'connexion',
        'billetterie' => 'billetterie_connexion',
        default       => in_array($mode, EVT_MODES, true) ? $mode : 'aucune',
    };
}

function evt_mode_requires_login(string $mode): bool {
    return in_array(evt_normalize_mode($mode), ['connexion', 'billetterie_connexion'], true);
}

function evt_mode_is_paid(string $mode): bool {
    return in_array(evt_normalize_mode($mode), ['billetterie_email', 'billetterie_connexion'], true);
}

function evt_mode_collects_contact(string $mode): bool {
    return in_array(evt_normalize_mode($mode), ['email', 'billetterie_email'], true);
}

function evt_mode_emits_ticket(string $mode): bool {
    return in_array(evt_normalize_mode($mode), ['email', 'connexion', 'billetterie_email', 'billetterie_connexion'], true);
}

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

function evt_sanitize_icon(?string $raw, string $default = '🎉'): string
{
    $raw = trim(strip_tags((string)$raw));
    if ($raw === '') {
        return $default;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($raw, 0, 8, 'UTF-8');
    }
    return substr($raw, 0, 32);
}

function evt_render_icon(?string $icon, string $default = '🎉'): string
{
    $icon = evt_sanitize_icon($icon, $default);
    return '<span class="evt-icon" role="img" aria-hidden="true">' . $icon . '</span>';
}

function corpo_evt_has_visibilite_column(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenements' AND COLUMN_NAME = 'visibilite' LIMIT 1"
        );
        $st->execute();
        $cache = (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function corpo_evt_has_inscription_membres_column(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evenements' AND COLUMN_NAME = 'inscription_membres' LIMIT 1"
        );
        $st->execute();
        $cache = (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function evt_normalize_visibilite(?string $raw): string
{
    return ($raw ?? '') === 'membres' ? 'membres' : 'public';
}

function evt_parse_visibilite_from_post(array $post, string $structType, ?int $structId): string
{
    if ($structType === 'corpo' || !$structId) {
        return 'public';
    }
    return (($post['visibilite'] ?? '') === 'membres') ? 'membres' : 'public';
}

function evt_parse_inscription_membres_from_post(array $post, string $structType, ?int $structId): int
{
    if ($structType === 'corpo' || !$structId) {
        return 0;
    }
    return isset($post['inscription_membres']) ? 1 : 0;
}

function evt_user_is_structure_member(array $event, ?int $userId, ?PDO $pdo = null): bool
{
    if (!$userId) {
        return false;
    }
    $stType = (string)($event['structure_type'] ?? 'corpo');
    $stId   = (int)($event['structure_id'] ?? 0);
    if (!$stId || $stType === 'corpo') {
        return false;
    }
    $checkType = $stType === 'sport' ? 'sport' : 'asso';
    if (isLoggedIn() && (int)($_SESSION['user_id'] ?? 0) === $userId && isMembreOf($checkType, $stId)) {
        return true;
    }
    if ($pdo instanceof PDO) {
        try {
            $st = $pdo->prepare(
                "SELECT 1 FROM structure_membres
                  WHERE user_id = ? AND structure_type = ? AND structure_id = ? AND statut = 'actif'
                  LIMIT 1"
            );
            $st->execute([$userId, $checkType, $stId]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
    return false;
}

function evt_user_can_see_event(PDO $pdo, array $event, ?int $userId): bool
{
    if (!corpo_evt_has_visibilite_column($pdo)) {
        return true;
    }
    if (evt_normalize_visibilite($event['visibilite'] ?? 'public') === 'public') {
        return true;
    }
    return evt_user_is_structure_member($event, $userId, $pdo);
}

function evt_visibilite_message(array $event): string
{
    return 'Cet événement est réservé aux membres et adhérents de '
        . trim((string)($event['organisateur'] ?? 'cette structure')) . '.';
}

function evt_inscription_membres_message(array $event): string
{
    return 'L\'inscription est réservée aux membres et adhérents de '
        . trim((string)($event['organisateur'] ?? 'cette structure')) . '.';
}

function evt_banniere_src(?string $path): ?string
{
    $path = trim((string)$path);
    if ($path === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return $path;
}

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

function evt_apply_validation_demande(PDO $pdo, array $demande, array $payload): void
{
    require_once __DIR__ . '/auth.php';
    if (!empty($payload['id'])) {
        evt_update_from_payload($pdo, (int)$payload['id'], $payload);
        return;
    }
    evt_insert_from_demande_payload($pdo, $demande, $payload);
}

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
           icon=?, banniere=?, visibilite=?, inscription_membres=?, ecoles_invitees=?, campus_invites=?, affichage_tv=?
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
        evt_sanitize_icon($p['icon'] ?? null),
        !empty($p['banniere']) ? trim((string)$p['banniere']) : null,
        evt_normalize_visibilite($p['visibilite'] ?? 'public'),
        (int)(bool)($p['inscription_membres'] ?? 0),
        json_encode($ecolesInv, JSON_UNESCAPED_UNICODE),
        json_encode($campusInv, JSON_UNESCAPED_UNICODE),
        isset($p['affichage_tv']) ? (int)(bool)$p['affichage_tv'] : 1,
        $eventId,
    ]);
}

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
            visibilite, inscription_membres, ecoles_invitees, campus_invites, affichage_tv, statut, auteur_id)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'publie',?)"
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
        evt_sanitize_icon($p['icon'] ?? null),
        !empty($p['banniere']) ? trim((string)$p['banniere']) : null,
        evt_normalize_visibilite($p['visibilite'] ?? 'public'),
        (int)(bool)($p['inscription_membres'] ?? 0),
        json_encode($ecolesInv, JSON_UNESCAPED_UNICODE),
        json_encode($campusInv, JSON_UNESCAPED_UNICODE),
        isset($p['affichage_tv']) ? (int)(bool)$p['affichage_tv'] : 1,
        (int)($demande['user_id'] ?? 0) ?: null,
    ]);
    return (int)$pdo->lastInsertId();
}

function evt_user_can_register(array $event, ?array $user = null, ?PDO $pdo = null): array {
    $userId = $user ? (int)($user['id'] ?? 0) : 0;
    if (!$userId && isLoggedIn()) {
        $userId = (int)($_SESSION['user_id'] ?? 0);
    }
    if (
        ($pdo instanceof PDO && corpo_evt_has_inscription_membres_column($pdo))
        || (!$pdo instanceof PDO && array_key_exists('inscription_membres', $event))
    ) {
        if ((int)($event['inscription_membres'] ?? 0) === 1) {
            if (!evt_user_is_structure_member($event, $userId ?: null, $pdo)) {
                return ['ok' => false, 'reason' => 'membres_structure_required'];
            }
        }
    }

    $mode = evt_normalize_mode($event['mode_inscription'] ?? 'aucune');
    $requiresLogin = evt_mode_requires_login($mode);
    $ouvertExt = (int)($event['ouvert_externes'] ?? 1) === 1;
    $ecolesInv = [];
    if (!empty($event['ecoles_invitees'])) {
        $d = json_decode($event['ecoles_invitees'], true);
        if (is_array($d)) $ecolesInv = $d;
    }
    $restreint = !empty($ecolesInv) && !in_array('Tous', $ecolesInv, true);

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

function tarifs_pour_event(PDO $pdo, int $evtId): array {
    try {
        $st = $pdo->prepare("SELECT * FROM evenement_tarifs WHERE evenement_id=? AND statut='actif' ORDER BY position ASC, id ASC");
        $st->execute([$evtId]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) { return []; }
}

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
        if ($c['tarif_id'] && !$tarifId) continue;
        return $c;
    }
    return null;
}

function code_promo_apply(array $code, float $prix): array {
    if ($code['type'] === 'pourcentage') {
        $reduc = round($prix * ((float)$code['valeur'] / 100), 2);
    } else {
        $reduc = min($prix, (float)$code['valeur']);
    }
    return ['prix_unitaire' => max(0.0, round($prix - $reduc, 2)), 'reduction' => $reduc];
}

function code_promo_consume(PDO $pdo, int $codeId, int $quantite = 1): void {
    try {
        $pdo->prepare("UPDATE codes_promo SET utilisations_count = utilisations_count + ? WHERE id = ?")
            ->execute([$quantite, $codeId]);
    } catch (Throwable $e) {}
}

function billet_generate_token(): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $bytes = random_bytes(20);
    $out = '';
    for ($i = 0; $i < 32; $i++) {
        $out .= $alphabet[ord($bytes[$i % 20]) & 31];
    }
    return $out;
}

function billet_sign(string $token): string {
    $secret = corpo_env('APP_SECRET', 'corpo-omnes-default-secret');
    return substr(hash_hmac('sha256', $token, $secret), 0, 16);
}

function billet_verify(string $token, string $signature): bool {
    if (!preg_match('/^[A-Z0-9]{8,64}$/', $token)) return false;
    return hash_equals(billet_sign($token), $signature);
}

function billet_qr_payload(string $token): string {
    return $token . '.' . billet_sign($token);
}

function billet_qr_parse(string $payload): ?array {
    if (!str_contains($payload, '.')) return null;
    [$token, $sig] = explode('.', $payload, 2);
    if (!billet_verify($token, $sig)) return null;
    return ['token' => $token, 'sig' => $sig];
}

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

        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM inscriptions_evenement
              WHERE evenement_id = ? AND statut IN ('confirme','en_attente')"
        );
        $st->execute([$evtId]);
        return (int)$st->fetchColumn();
    }
}

function billet_compute_statut(PDO $pdo, int $evtId): string {
    $ev = $pdo->prepare("SELECT places FROM evenements WHERE id=?");
    $ev->execute([$evtId]);
    $places = (int)$ev->fetchColumn();
    if ($places <= 0) return 'confirme';
    return billet_count_actifs($pdo, $evtId) >= $places ? 'liste_attente' : 'confirme';
}

function billet_recompute_compteur(PDO $pdo, int $evtId): void {
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM inscriptions_evenement
          WHERE evenement_id = ? AND statut = 'confirme'"
    );
    $st->execute([$evtId]);
    $n = (int)$st->fetchColumn();
    $pdo->prepare("UPDATE evenements SET inscrits = ? WHERE id = ?")->execute([$n, $evtId]);
}

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

function billet_promote_first_waitlist(PDO $pdo, int $evtId): ?int {
    $st = $pdo->prepare(
        "SELECT id FROM inscriptions_evenement
          WHERE evenement_id = ? AND statut = 'liste_attente'
          ORDER BY waitlist_position ASC, created_at ASC, id ASC
          LIMIT 1"
    );
    $st->execute([$evtId]);
    $id = $st->fetchColumn();
    if (!$id) return null;
    $pdo->prepare(
        "UPDATE inscriptions_evenement SET statut='confirme', waitlist_position=NULL WHERE id=?"
    )->execute([$id]);
    billet_recompute_compteur($pdo, $evtId);
    billet_recompute_waitlist($pdo, $evtId);
    return (int)$id;
}

function billet_create(PDO $pdo, int $evtId, ?int $userId, array $contact, float $prixPaye = 0.0, string $paiementStatut = 'aucun', ?string $paiementProvider = null, ?int $tarifId = null, ?string $codePromo = null): ?int {

    $ev = $pdo->prepare("SELECT * FROM evenements WHERE id=?");
    $ev->execute([$evtId]);
    $event = $ev->fetch();
    if (!$event) return null;

    if (!evt_inscriptions_fenetre($event)['open']) {
        return null;
    }

    $statut = billet_compute_statut($pdo, $evtId);

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

    for ($i = 0; $i < 5; $i++) {
        $token = billet_generate_token();
        try {
            $cols = "user_id, evenement_id, statut, qr_token, email, nom, prenom,
                     prix_paye, paiement_statut, paiement_provider";
            $vals = "?,?,?,?,?,?,?,?,?,?";
            $params = [
                $userId ?: null,
                $evtId, $statut, $token,
                $contact['email'] ?? null,
                $contact['nom']   ?? null,
                $contact['prenom']?? null,
                $prixPaye,
                $paiementStatut,
                $paiementProvider,
            ];
            if ($extCols['tarif']) { $cols .= ", tarif_id"; $vals .= ",?"; $params[] = $tarifId; }
            if ($extCols['promo']) { $cols .= ", code_promo_utilise"; $vals .= ",?"; $params[] = $codePromo; }

            $pdo->prepare("INSERT INTO inscriptions_evenement ($cols) VALUES ($vals)")->execute($params);
            $id = (int)$pdo->lastInsertId();
            if ($statut === 'liste_attente') billet_recompute_waitlist($pdo, $evtId);
            billet_recompute_compteur($pdo, $evtId);
            return $id;
        } catch (PDOException $e) {
            $msg = $e->getMessage();

            if (str_contains($msg, 'qr_token')) continue;

            if (str_contains($msg, 'uniq_insc_evt') || str_contains($msg, "for key 'inscriptions_evenement.uniq_insc_evt'") || str_contains($msg, "clef 'inscriptions_evenement.uniq_insc_evt'")) {
                try {
                    $pdo->exec("ALTER TABLE inscriptions_evenement DROP INDEX uniq_insc_evt");

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

function billet_cancel(PDO $pdo, int $inscriptionId): bool {
    $row = $pdo->prepare("SELECT evenement_id, statut FROM inscriptions_evenement WHERE id=?");
    $row->execute([$inscriptionId]);
    $r = $row->fetch();
    if (!$r) return false;

    $pdo->prepare(
        "UPDATE inscriptions_evenement SET statut='annule', waitlist_position=NULL WHERE id=?"
    )->execute([$inscriptionId]);

    billet_recompute_compteur($pdo, (int)$r['evenement_id']);

    if ($r['statut'] === 'confirme') {
        billet_promote_first_waitlist($pdo, (int)$r['evenement_id']);
    } else {
        billet_recompute_waitlist($pdo, (int)$r['evenement_id']);
    }
    return true;
}

function billet_qr_image_url(string $token, int $size = 280): string {
    $payload = billet_qr_payload($token);

    return '/api/qr-image.php?p=' . urlencode($payload) . '&s=' . (int)$size;
}

function billet_scan_lookup(PDO $pdo, string $qrPayload, ?int $evtId = null): array {
    $raw = strtoupper(trim($qrPayload));
    $parsed = billet_qr_parse($qrPayload);
    if (!$parsed) {

        if (str_contains($raw, '.')) {
            $tok = strtok($raw, '.');
            if ($tok && preg_match('/^[A-Z0-9]{8,64}$/', $tok)) {
                $parsed = ['token' => $tok, 'sig' => ''];
            }
        }

        if (!$parsed && preg_match('/^[A-Z0-9]{8,64}$/', $raw)) {
            $parsed = ['token' => $raw, 'sig' => ''];
        }

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

    $st = $pdo->prepare(
        "SELECT i.*, e.titre, e.date, e.lieu
           FROM inscriptions_evenement i
           JOIN evenements e ON e.id = i.evenement_id
          WHERE i.qr_token = ?"
    );
    $st->execute([$parsed['token']]);
    $row = $st->fetch();
    if ($row) return ['ok' => true, 'msg' => 'OK', 'inscription' => $row];

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

function billet_send_mail_for_ids(PDO $pdo, array $billetIds, ?int $txId = null): int {
    $billetIds = array_values(array_unique(array_filter(array_map('intval', $billetIds))));
    if (empty($billetIds)) return 0;

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
        if (corpo_mail_send_tickets($bs, $event, $email, $name ?: null)) {
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
        } catch (Throwable $e) {  }
    }
    return $sent;
}
