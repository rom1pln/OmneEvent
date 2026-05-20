<?php
// Gestion des rôles et permissions
// Hiérarchie : super_admin > admin_corpo > membre_corpo > user
// Les admins de BDE/BDS peuvent gérer les structures de leur école

if (is_file(__DIR__ . '/env.php')) {
    require_once __DIR__ . '/env.php';
}

// Vérifie si on est en HTTPS, y compris derrière un proxy
// sans ça le cookie de session perd le flag Secure
function is_https_request(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $xf = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['HTTP_X_FORWARDED_PROTOCOL'] ?? '';
    if (is_string($xf) && $xf !== '') {
        $first = strtolower(trim(explode(',', $xf, 2)[0]));
        if ($first === 'https') {
            return true;
        }
    }
    if (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strcasecmp((string)$_SERVER['HTTP_FRONT_END_HTTPS'], 'on') === 0) {
        return true;
    }
    return (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    $secure = is_https_request();
    $cookiePath = '/';
    $lifetimeDays = 30;
    if (function_exists('corpo_env')) {
        $p = corpo_env('SESSION_COOKIE_PATH', '');
        if (is_string($p) && $p !== '') {
            $cookiePath = $p;
        }
        $ld = corpo_env('SESSION_LIFETIME_DAYS', '');
        if (is_string($ld) && $ld !== '' && ctype_digit((string)$ld)) {
            $lifetimeDays = max(0, (int)$ld);
        }
    }
    $lifetime = $lifetimeDays * 86400;

    // la GC serveur doit avoir la même durée que le cookie sinon l'user se retrouve déco sans raison
    if ($lifetime > 0) {
        @ini_set('session.gc_maxlifetime', (string)$lifetime);
        @ini_set('session.cookie_lifetime', (string)$lifetime);
    }

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => $cookiePath,
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params($lifetime, $cookiePath, '', $secure, true);
    }
    session_start();

    // renouvelle le cookie à chaque visite pour pas que l'user soit déco s'il revient régulièrement
    if ($lifetime > 0 && !headers_sent() && isset($_COOKIE[session_name()])) {
        setcookie(session_name(), session_id(), [
            'expires'  => time() + $lifetime,
            'path'     => $cookiePath,
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

// --- session ---

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function currentRole(): ?string {
    return $_SESSION['user_role'] ?? null;
}

function currentUserId(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

// --- vérifications rôle global ---

function isSuperAdmin(): bool {
    return currentRole() === 'super_admin';
}

// admin Corpo ou super admin
function isAdminCorpo(): bool {
    return in_array(currentRole(), ['super_admin', 'admin_corpo'], true);
}

// membre Corpo ou plus
function isMembreCorpo(): bool {
    return in_array(currentRole(), ['super_admin', 'admin_corpo', 'membre_corpo'], true);
}

// accès panel admin (membre corpo ou plus)
function hasAdminAccess(): bool {
    return isMembreCorpo();
}

// ancien nom, on garde pour pas tout casser
function isAdmin(): bool {
    return isAdminCorpo();
}

// idem, ancien nom
function isBureau(): bool {
    return hasAdminAccess() || hasAnyAdminRole();
}

// est-ce qu'il est admin quelque part
function hasAnyAdminRole(): bool {
    if (!isLoggedIn()) return false;
    foreach ($_SESSION['memberships'] ?? [] as $m) {
        if ($m['role'] === 'admin') return true;
    }
    return false;
}

// a-t-il une délégation quelque part (events, partenaires, commu, tréso)
function hasAnyStructureDelegation(): bool {
    if (!isLoggedIn()) return false;
    foreach (getMemberships() as $m) {
        foreach (['evenement', 'partenariat', 'communication', 'tresorerie'] as $k) {
            if (membershipHasExplicitResponsabilite($m, $k)) {
                return true;
            }
        }
    }
    return false;
}

// est membre bureau (admin ou membre) dans au moins une structure
function hasAnyStructureBureauMemberRole(): bool {
    if (!isLoggedIn()) {
        return false;
    }
    foreach (getMemberships() as $m) {
        if (in_array((string)($m['role'] ?? ''), ['admin', 'membre'], true)) {
            return true;
        }
    }
    return false;
}

// peut se connecter au panel admin
function hasAdminPanelAccess(): bool {
    return isMembreCorpo()
        || hasAnyAdminRole()
        || hasAnyStructureDelegation()
        || hasAnyStructureBureauMemberRole();
}

function _membershipHasResponsabilite(array $m, string $respKey): bool {
    $role = (string)($m['role'] ?? '');
    if ($role === 'admin') {
        return true;
    }
    // les membres bureau peuvent gérer les events de leur structure
    if ($respKey === 'evenement' && $role === 'membre') {
        return true;
    }
    return match ($respKey) {
        'evenement'     => !empty($m['resp_evenement']),
        'partenariat'   => !empty($m['resp_partenariat']),
        'communication' => !empty($m['resp_communication']),
        'tresorerie'    => !empty($m['resp_tresorerie']),
        default         => false,
    };
}

// version sans le droit auto du bureau (pour afficher les délégations seules)
function membershipHasExplicitResponsabilite(array $m, string $respKey): bool {
    if (($m['role'] ?? '') === 'admin') {
        return false;
    }
    return match ($respKey) {
        'evenement'     => !empty($m['resp_evenement']),
        'partenariat'   => !empty($m['resp_partenariat']),
        'communication' => !empty($m['resp_communication']),
        'tresorerie'    => !empty($m['resp_tresorerie']),
        default         => false,
    };
}

// retourne les structures où l'user a une délégation explicite
function getExplicitDelegatedStructures(string $respKey): array {
    $out = [];
    foreach (getMemberships() as $m) {
        if (!membershipHasExplicitResponsabilite($m, $respKey)) {
            continue;
        }
        $out[] = ['type' => (string)$m['type'], 'id' => (int)$m['id']];
    }
    return $out;
}

// vérifie la responsabilité sur une structure précise
function memberHasStructureResponsabilite(string $structType, int $structId, string $respKey): bool {
    if (!isLoggedIn()) return false;
    foreach (getMemberships() as $m) {
        if (($m['type'] ?? '') !== $structType || (int)($m['id'] ?? 0) !== $structId) {
            continue;
        }
        return _membershipHasResponsabilite($m, $respKey);
    }
    return false;
}

// peut gérer une ressource (event, partenaire, actu, compta) pour une structure
function canManageStructureResource(PDO $pdo, string $structType, ?int $structId, string $respKey): bool {
    if (isAdminCorpo()) return true;
    if (!$structId || $structId <= 0) return false;

    if ($structType === 'sport') {
        if (canManageSport($structId, $pdo)) return true;
        return memberHasStructureResponsabilite('sport', $structId, $respKey);
    }

    if ($structType === 'asso') {
        if (canManageAsso($structId, $pdo) || canManageBDE($structId, $pdo) || canManageBDS($structId, $pdo)) {
            return true;
        }
        return memberHasStructureResponsabilite('asso', $structId, $respKey)
            || memberHasStructureResponsabilite('bde', $structId, $respKey)
            || memberHasStructureResponsabilite('bds', $structId, $respKey);
    }

    return false;
}

// peut accéder à la fiche admin de l'événement (participants, scan, modif)
function canManageEvenement(PDO $pdo, array $ev): bool {
    if (isAdminCorpo()) {
        return true;
    }
    $stType = (string)($ev['structure_type'] ?? '');
    $stId   = (int)($ev['structure_id'] ?? 0);
    if ($stId <= 0 || $stType === 'corpo') {
        return false;
    }
    $respType = $stType === 'sport' ? 'sport' : 'asso';
    return canManageStructureResource($pdo, $respType, $stId, 'evenement');
}

function hasExplicitTreasuryDelegation(): bool {
    foreach (getMemberships() as $m) {
        if (membershipHasExplicitResponsabilite($m, 'tresorerie')) {
            return true;
        }
    }
    return false;
}

// panel limité au dashboard + notes de frais (membre bureau sans délégation)
function isAdminPanelNotesFraisOnly(): bool {
    if (!isLoggedIn()) {
        return false;
    }
    if (isMembreCorpo() || hasAnyAdminRole() || hasAnyStructureDelegation()) {
        return false;
    }
    return hasAnyStructureBureauMemberRole();
}

function isAdminPanelDelegationOnly(): bool {
    if (!isLoggedIn()) {
        return false;
    }
    if (isMembreCorpo()) {
        return false;
    }
    if (hasAnyAdminRole()) {
        return false;
    }
    if (isAdminPanelNotesFraisOnly()) {
        return false;
    }
    return hasAnyStructureDelegation();
}

// extrait le nom de page depuis une URL type "evenements.php"
function adminPanelPageKeyFromHref(string $href): string {
    $base = basename(parse_url($href, PHP_URL_PATH) ?: $href);
    $base = preg_replace('/\.php$/i', '', $base) ?: '';
    if ($base === 'index') {
        return 'dashboard';
    }
    return $base !== '' ? $base : 'dashboard';
}

// vérifie si la page est accessible selon les droits de délégation
function adminPanelDelegationAllows(PDO $pdo, string $adminPage): bool {
    if ($adminPage === 'notes-frais' && !function_exists('nf_can_access_admin_notes_page')) {
        require_once __DIR__ . '/notes-frais.php';
    }
    if (isAdminPanelNotesFraisOnly()) {
        $p = $adminPage === 'evenement' ? 'evenements' : $adminPage;
        return match ($p) {
            'dashboard'   => true,
            'notes-frais' => function_exists('nf_can_access_admin_notes_page')
                && nf_can_access_admin_notes_page($pdo, (int)($_SESSION['user_id'] ?? 0)),
            default       => false,
        };
    }
    if (!isAdminPanelDelegationOnly()) {
        return true;
    }
    $p = $adminPage === 'evenement' ? 'evenements' : $adminPage;
    if ($p === 'boutique-commandes') {
        $p = 'boutique';
    }
    return match ($p) {
        'dashboard' => true,
        'evenements' => !empty(getExplicitDelegatedStructures('evenement')),
        'boutique' => !empty(getExplicitDelegatedStructures('evenement')),
        'partenaires' => !empty(getExplicitDelegatedStructures('partenariat')),
        'actualites' => !empty(getExplicitDelegatedStructures('communication')),
        'comptabilite' => hasExplicitTreasuryDelegation(),
        'notes-frais'  => function_exists('nf_can_access_admin_notes_page')
            && nf_can_access_admin_notes_page($pdo, (int)($_SESSION['user_id'] ?? 0)),
        'calendrier' => canManageCalendrier($pdo),
        default => false,
    };
}

// redirige vers le dashboard si l'accès est refusé
function requireAdminPanelDelegationRoute(PDO $pdo, string $adminPage): void {
    if (adminPanelDelegationAllows($pdo, $adminPage)) {
        return;
    }
    header('Location: index.php?denied=1');
    exit;
}

// --- appartenance structures ---

// retourne les structures de l'user stockées en session
function getMemberships(): array {
    return $_SESSION['memberships'] ?? [];
}

// est au moins membre de cette structure
function isMembreOf(string $type, int $id): bool {
    foreach (getMemberships() as $m) {
        if ($m['type'] === $type && (int)$m['id'] === $id) return true;
    }
    return false;
}

// est admin de cette structure
function isAdminOf(string $type, int $id): bool {
    foreach (getMemberships() as $m) {
        if ($m['type'] === $type && (int)$m['id'] === $id && $m['role'] === 'admin') return true;
    }
    return false;
}

// --- permissions hiérarchiques ---

// peut gérer l'asso (direct, BDE parent, fédé, ou Corpo)
function canManageAsso(int $assoId, PDO $pdo): bool {
    if (isAdminCorpo()) return true;
    if (isAdminOf('asso', $assoId)) return true;

    $row = $pdo->prepare("SELECT parent_bde_id FROM associations WHERE id = ?");
    $row->execute([$assoId]);
    $parentBdeId = $row->fetchColumn();

    if ($parentBdeId) {
        $parentId = (int)$parentBdeId;
        // admin du BDE parent
        if (isAdminOf('bde', $parentId)) return true;
        // ou admin de la fédé (ex: EchoFed pour les assos HEIP)
        if (in_array($parentId, _getAdminFederationIds($pdo), true)) return true;
    } else {
        // asso sans BDE → gérée par la Corpo directement
        if (_isAdminCorpoAsso($pdo)) return true;
    }

    return false;
}

// peut gérer ce sport (direct, BDS, BDE école, ou OMNES Sport/Corpo pour les inter-école)
function canManageSport(int $sportId, PDO $pdo): bool {
    if (isAdminCorpo()) return true;
    if (isAdminOf('sport', $sportId)) return true;

    $row = $pdo->prepare(
        "SELECT s.parent_bds_id, a.ecole AS bds_ecole
         FROM sports s LEFT JOIN associations a ON a.id = s.parent_bds_id
         WHERE s.id = ?"
    );
    $row->execute([$sportId]);
    $sport = $row->fetch();
    if (!$sport) return false;

    if ($sport['parent_bds_id']) {
        // sport d'école
        if (isAdminOf('bds', (int)$sport['parent_bds_id'])) return true;

        // ou admin BDE de la même école
        if ($sport['bds_ecole']) {
            $bdeStmt = $pdo->prepare(
                "SELECT id FROM associations WHERE type = 'BDE' AND ecole = ? LIMIT 1"
            );
            $bdeStmt->execute([$sport['bds_ecole']]);
            $bdeId = $bdeStmt->fetchColumn();
            if ($bdeId && isAdminOf('bde', (int)$bdeId)) return true;

            // ou admin d'une fédération de la même école
            $fedEcoles = _getAdminFederationEcoles($pdo);
            if (in_array((string)$sport['bds_ecole'], $fedEcoles, true)) return true;
        }
    } else {
        // sport inter-école (Omnes)
        $omnesStmt = $pdo->prepare(
            "SELECT id FROM associations WHERE slug = 'omnes-sport' LIMIT 1"
        );
        $omnesStmt->execute();
        $omnesSportId = $omnesStmt->fetchColumn();
        if ($omnesSportId && isAdminOf('bds', (int)$omnesSportId)) return true;

        // ou admin Corpo (qui chapeaute OMNES Sport)
        if (_isAdminCorpoAsso($pdo)) return true;
    }

    return false;
}

// peut gérer ce BDE (direct, Corpo, ou fédé de la même école)
function canManageBDE(int $bdeId, ?PDO $pdo = null): bool {
    if (isAdminCorpo()) return true;
    if (isAdminOf('bde', $bdeId)) return true;
    if ($pdo && _isAdminCorpoAsso($pdo)) return true;
    // ou fédé de la même école
    if ($pdo) {
        $fedEcoles = _getAdminFederationEcoles($pdo);
        if (!empty($fedEcoles)) {
            $st = $pdo->prepare("SELECT ecole FROM associations WHERE id = ? LIMIT 1");
            $st->execute([$bdeId]);
            $ecoleBde = (string)$st->fetchColumn();
            if ($ecoleBde !== '' && in_array($ecoleBde, $fedEcoles, true)) {
                return true;
            }
        }
    }
    return false;
}

// peut gérer ce BDS (idem BDE)
function canManageBDS(int $bdsId, ?PDO $pdo = null): bool {
    if (isAdminCorpo()) return true;
    if (isAdminOf('bds', $bdsId)) return true;
    if ($pdo && _isAdminCorpoAsso($pdo)) return true;
    if ($pdo) {
        $fedEcoles = _getAdminFederationEcoles($pdo);
        if (!empty($fedEcoles)) {
            $st = $pdo->prepare("SELECT ecole FROM associations WHERE id = ? LIMIT 1");
            $st->execute([$bdsId]);
            $ecoleBds = (string)$st->fetchColumn();
            if ($ecoleBds !== '' && in_array($ecoleBds, $fedEcoles, true)) {
                return true;
            }
        }
    }
    return false;
}

// est-il admin de l'asso Corpo OMNES (résultat mis en cache session)
function _isAdminCorpoAsso(PDO $pdo): bool {
    if (!isLoggedIn()) return false;
    if (isset($_SESSION['_is_admin_corpo_asso'])) {
        return (bool)$_SESSION['_is_admin_corpo_asso'];
    }
    $corpoId = getCorpoOmnesAssoId($pdo);
    $result  = $corpoId && isAdminOf('asso', (int)$corpoId);
    $_SESSION['_is_admin_corpo_asso'] = $result;
    return $result;
}

// récupère l'ID de l'asso Corpo OMNES (mis en cache session)
function getCorpoOmnesAssoId(PDO $pdo): ?int {
    if (array_key_exists('_corpo_omnes_asso_id', $_SESSION)) {
        $val = $_SESSION['_corpo_omnes_asso_id'];
        return $val ? (int)$val : null;
    }
    try {
        $stmt = $pdo->prepare("SELECT id FROM associations WHERE slug = 'corpo-omnes' LIMIT 1");
        $stmt->execute();
        $id = $stmt->fetchColumn();
        $_SESSION['_corpo_omnes_asso_id'] = $id ? (int)$id : 0;
        return $id ? (int)$id : null;
    } catch (Throwable $e) {
        return null;
    }
}

// si le type est "corpo", on le remplace par l'asso Corpo OMNES
function resolveCorpoStructure(PDO $pdo, string $structType, ?int $structId): array {
    if ($structType === 'corpo') {
        $corpoId = getCorpoOmnesAssoId($pdo);
        if ($corpoId) {
            return ['asso', $corpoId];
        }
    }
    return [$structType, $structId];
}

// IDs des fédérations dont l'user est admin (une fédé = BDE étendu pour son école)
function _getAdminFederationIds(PDO $pdo): array {
    if (!isLoggedIn()) return [];
    if (isset($_SESSION['_admin_federation_ids'])) {
        return $_SESSION['_admin_federation_ids'];
    }
    $adminAssoIds = [];
    foreach (getMemberships() as $m) {
        if (($m['role'] ?? '') === 'admin' && ($m['type'] ?? '') === 'asso') {
            $adminAssoIds[] = (int)$m['id'];
        }
    }
    if (empty($adminAssoIds)) {
        $_SESSION['_admin_federation_ids'] = [];
        return [];
    }
    $ph = implode(',', array_fill(0, count($adminAssoIds), '?'));
    $st = $pdo->prepare(
        "SELECT id, type FROM associations WHERE id IN ($ph)"
    );
    $st->execute($adminAssoIds);
    $fedIds = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (_isFederationType((string)$row['type'])) {
            $fedIds[] = (int)$row['id'];
        }
    }
    $_SESSION['_admin_federation_ids'] = $fedIds;
    return $fedIds;
}

// écoles couvertes par les fédérations de l'user (même droits que admin BDE sur ces écoles)
function _getAdminFederationEcoles(PDO $pdo): array {
    if (!isLoggedIn()) return [];
    if (isset($_SESSION['_admin_federation_ecoles'])) {
        return $_SESSION['_admin_federation_ecoles'];
    }
    $fedIds = _getAdminFederationIds($pdo);
    if (empty($fedIds)) {
        $_SESSION['_admin_federation_ecoles'] = [];
        return [];
    }
    $ph = implode(',', array_fill(0, count($fedIds), '?'));
    $st = $pdo->prepare(
        "SELECT DISTINCT ecole FROM associations WHERE id IN ($ph) AND ecole IS NOT NULL AND ecole <> ''"
    );
    $st->execute($fedIds);
    $ecoles = array_values(array_filter($st->fetchAll(PDO::FETCH_COLUMN)));
    $_SESSION['_admin_federation_ecoles'] = $ecoles;
    return $ecoles;
}

// IDs des assos que l'user peut gérer ([] = toutes si admin_corpo)
function getManagedAssoIds(PDO $pdo): array {
    if (isAdminCorpo()) return [];

    $ids = [];

    // admin Corpo OMNES → assos sans BDE parent (sauf les BDE/BDS/Corpo eux-mêmes)
    if (_isAdminCorpoAsso($pdo)) {
        $stmt = $pdo->query(
            "SELECT id FROM associations
             WHERE parent_bde_id IS NULL
               AND type NOT IN ('BDE','BDS','Corpo')"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $aid) $ids[] = (int)$aid;
    }

    foreach (getMemberships() as $m) {
        if ($m['role'] !== 'admin') continue;

        if ($m['type'] === 'asso') {
            $ids[] = (int)$m['id'];
        }
        if ($m['type'] === 'bde') {
            // toutes les assos enfants du BDE + le BDE lui-même
            $stmt = $pdo->prepare("SELECT id FROM associations WHERE parent_bde_id = ?");
            $stmt->execute([$m['id']]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $aid) $ids[] = (int)$aid;
            $ids[] = (int)$m['id'];
        }
        if ($m['type'] === 'bds') {
            $ids[] = (int)$m['id'];
        }
    }

    // fédé → ses assos enfants + les assos du BDE de la même école
    $fedIds = _getAdminFederationIds($pdo);
    if (!empty($fedIds)) {
        $ph = implode(',', array_fill(0, count($fedIds), '?'));
        $stmt = $pdo->prepare("SELECT id FROM associations WHERE parent_bde_id IN ($ph)");
        $stmt->execute($fedIds);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $aid) $ids[] = (int)$aid;

        $fedEcoles = _getAdminFederationEcoles($pdo);
        if (!empty($fedEcoles)) {
            $ph2 = implode(',', array_fill(0, count($fedEcoles), '?'));
            $stmt = $pdo->prepare(
                "SELECT id FROM associations WHERE type = 'BDE' AND ecole IN ($ph2)"
            );
            $stmt->execute($fedEcoles);
            $bdeIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            foreach ($bdeIds as $bdeId) $ids[] = $bdeId;
            if (!empty($bdeIds)) {
                $ph3 = implode(',', array_fill(0, count($bdeIds), '?'));
                $stmt = $pdo->prepare("SELECT id FROM associations WHERE parent_bde_id IN ($ph3)");
                $stmt->execute($bdeIds);
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $aid) $ids[] = (int)$aid;
            }
        }
    }

    return array_unique(array_filter($ids));
}

// IDs des sports que l'user peut gérer ([] = tous si admin_corpo)
function getManagedSportIds(PDO $pdo): array {
    if (isAdminCorpo()) return [];

    $ids = [];

    // admin Corpo OMNES → sports inter-école aussi
    if (_isAdminCorpoAsso($pdo)) {
        $stmt = $pdo->query("SELECT id FROM sports WHERE parent_bds_id IS NULL");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $sid) $ids[] = (int)$sid;
    }

    foreach (getMemberships() as $m) {
        if ($m['role'] !== 'admin') continue;

        if ($m['type'] === 'sport') {
            $ids[] = (int)$m['id'];
        }

        if ($m['type'] === 'bds') {
            $s = $pdo->prepare("SELECT id FROM sports WHERE parent_bds_id = ?");
            $s->execute([$m['id']]);
            foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $sid) $ids[] = (int)$sid;

            // OMNES Sport = accès aux sports inter-école aussi
            $slugStmt = $pdo->prepare("SELECT slug FROM associations WHERE id = ? LIMIT 1");
            $slugStmt->execute([$m['id']]);
            if ($slugStmt->fetchColumn() === 'omnes-sport') {
                $s2 = $pdo->query("SELECT id FROM sports WHERE parent_bds_id IS NULL");
                foreach ($s2->fetchAll(PDO::FETCH_COLUMN) as $sid) $ids[] = (int)$sid;
            }
        }

        if ($m['type'] === 'bde') {
            // admin BDE → sports de son école via le BDS
            $ecoleStmt = $pdo->prepare("SELECT ecole FROM associations WHERE id = ? LIMIT 1");
            $ecoleStmt->execute([$m['id']]);
            $ecole = $ecoleStmt->fetchColumn();
            if ($ecole) {
                $bdsStmt = $pdo->prepare(
                    "SELECT id FROM associations WHERE type = 'BDS' AND ecole = ? LIMIT 1"
                );
                $bdsStmt->execute([$ecole]);
                $bdsId = $bdsStmt->fetchColumn();
                if ($bdsId) {
                    $spStmt = $pdo->prepare("SELECT id FROM sports WHERE parent_bds_id = ?");
                    $spStmt->execute([$bdsId]);
                    foreach ($spStmt->fetchAll(PDO::FETCH_COLUMN) as $sid) $ids[] = (int)$sid;
                }
            }
        }
    }

    // fédé → mêmes droits sport que admin BDE de la même école
    $fedEcoles = _getAdminFederationEcoles($pdo);
    if (!empty($fedEcoles)) {
        $ph = implode(',', array_fill(0, count($fedEcoles), '?'));
        $bdsStmt = $pdo->prepare(
            "SELECT id FROM associations WHERE type = 'BDS' AND ecole IN ($ph)"
        );
        $bdsStmt->execute($fedEcoles);
        $bdsIds = array_map('intval', $bdsStmt->fetchAll(PDO::FETCH_COLUMN));
        if (!empty($bdsIds)) {
            $ph2 = implode(',', array_fill(0, count($bdsIds), '?'));
            $spStmt = $pdo->prepare("SELECT id FROM sports WHERE parent_bds_id IN ($ph2)");
            $spStmt->execute($bdsIds);
            foreach ($spStmt->fetchAll(PDO::FETCH_COLUMN) as $sid) $ids[] = (int)$sid;
        }
    }

    return array_unique($ids);
}

// remet le rôle global à 'user' si l'user n'est plus admin de rien
// (évite qu'il garde l'accès panel après avoir été retiré d'une structure)
function syncGlobalRoleAfterStructChange(PDO $pdo, int $userId): void {
    if ($userId <= 0) return;

    $st = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $st->execute([$userId]);
    $currentRole = (string)$st->fetchColumn();
    if ($currentRole !== 'membre_corpo') return;

    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM structure_membres
         WHERE user_id = ? AND role_in_struct = 'admin' AND statut = 'actif'"
    );
    $st->execute([$userId]);
    $nbAdmin = (int)$st->fetchColumn();
    if ($nbAdmin === 0) {
        $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ? AND role = 'membre_corpo'")
            ->execute([$userId]);
        // propage en session si c'est le user courant
        if ((int)($_SESSION['user_id'] ?? 0) === $userId) {
            $_SESSION['user_role'] = 'user';
        }
    }
}

// ancien nom conservé pour compat
function canManageStructure(string $type, int $structureId, ?PDO $pdo = null): bool {
    if (isAdminCorpo()) return true;
    if ($type === 'asso' && $pdo) return canManageAsso($structureId, $pdo);
    if ($type === 'sport' && $pdo) return canManageSport($structureId, $pdo);
    if ($type === 'bde')  return canManageBDE($structureId);
    if ($type === 'bds')  return canManageBDS($structureId);
    return isAdminOf($type, $structureId);
}

// --- chargement des memberships au login ---

// charge les structures de l'user en session (à appeler après login)
function loadMemberships(int $userId, PDO $pdo): void {
    $extraCols = '';
    try {
        $chk = $pdo->prepare(
            "SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'structure_membres'
                AND COLUMN_NAME = 'resp_evenement' LIMIT 1"
        );
        $chk->execute();
        if ($chk->fetchColumn()) {
            $extraCols = ', IFNULL(resp_evenement,0) AS resp_evenement, IFNULL(resp_partenariat,0) AS resp_partenariat,
                IFNULL(resp_communication,0) AS resp_communication, IFNULL(resp_tresorerie,0) AS resp_tresorerie';
        }
    } catch (Throwable $e) {
        $extraCols = '';
    }

    $stmt = $pdo->prepare(
        "SELECT structure_type AS type, structure_id AS id, role_in_struct AS role $extraCols
         FROM structure_membres
         WHERE user_id = ? AND statut = 'actif'"
    );
    $stmt->execute([$userId]);
    $_SESSION['memberships'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    unset(
        $_SESSION['_is_admin_corpo_asso'],
        $_SESSION['_admin_federation_ids'],
        $_SESSION['_admin_federation_ecoles']
    );
    $_SESSION['_last_refresh_at'] = time();
}

// rafraîchit les infos de session depuis la BDD sans se reconnecter
// throttlé à 5s pour pas spammer la BDD
function refreshUserSession(PDO $pdo, int $minIntervalSec = 5): void {
    if (!isLoggedIn()) return;
    $last = (int)($_SESSION['_last_refresh_at'] ?? 0);
    if ($minIntervalSec > 0 && (time() - $last) < $minIntervalSec) return;

    $uid = (int)$_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT role, statut, prenom, nom, username FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$uid]);
    $row = $stmt->fetch();

    if (!$row || $row['statut'] !== 'actif') {
        corpo_destroy_session();
        return;
    }

    $_SESSION['user_role']   = $row['role'];
    $_SESSION['user_prenom'] = $row['prenom'] ?? ($_SESSION['user_prenom'] ?? '');
    $_SESSION['user_nom']    = $row['nom']    ?? ($_SESSION['user_nom']    ?? '');
    $_SESSION['user_login']  = $row['username'] ?? ($_SESSION['user_login'] ?? '');

    loadMemberships($uid, $pdo);
}

// déconnexion propre (vide session + invalide le cookie)
function corpo_destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        if (PHP_VERSION_ID >= 70300) {
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'] ?? '/',
                'domain'   => $p['domain'] ?? '',
                'secure'   => (bool)($p['secure'] ?? false),
                'httponly' => (bool)($p['httponly'] ?? true),
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        } else {
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'] ?? '/',
                $p['domain'] ?? '',
                (bool)($p['secure'] ?? false),
                (bool)($p['httponly'] ?? true)
            );
        }
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

// --- accès panel admin ---

// vérifie si le type d'asso est une fédération (insensible accents/casse)
function _isFederationType(?string $type): bool {
    if (!$type) return false;
    $norm = strtolower(trim($type));
    return $norm === 'fédération' || $norm === 'federation' || $norm === 'fed' || $norm === 'féderation';
}

// peut modifier le calendrier scolaire (admin_corpo, BDE, fédé)
function canManageCalendrier(PDO $pdo): bool {
    if (isAdminCorpo()) return true;
    if (!isLoggedIn()) return false;

    foreach (getMemberships() as $m) {
        if ($m['role'] !== 'admin') continue;
        if ($m['type'] === 'bde') return true;
        if ($m['type'] === 'asso') {
            $st = $pdo->prepare("SELECT type FROM associations WHERE id = ? LIMIT 1");
            $st->execute([$m['id']]);
            if (_isFederationType((string)$st->fetchColumn())) return true;
        }
    }
    return false;
}

// écoles accessibles dans le calendrier (toutes si admin_corpo, sinon ses BDE/fédés)
function getEcolesCalendrier(PDO $pdo, array $ecolesAll): array {
    if (isAdminCorpo()) return $ecolesAll;
    $ecoles = [];
    foreach (getMemberships() as $m) {
        if ($m['role'] !== 'admin') continue;
        if (!in_array($m['type'], ['bde','asso'], true)) continue;
        $st = $pdo->prepare("SELECT ecole, type FROM associations WHERE id = ? LIMIT 1");
        $st->execute([$m['id']]);
        $row = $st->fetch();
        if (!$row) continue;
        if ($m['type'] === 'asso' && !_isFederationType((string)$row['type'])) continue;
        $e = (string)$row['ecole'];
        if ($e !== '' && in_array($e, $ecolesAll, true)) $ecoles[] = $e;
    }
    return array_values(array_unique($ecoles));
}

// peut voir la section sport dans le panel admin
function canAccessSportAdmin(PDO $pdo): bool {
    if (isAdminCorpo()) return true;
    if (!isLoggedIn()) return false;
    foreach (getMemberships() as $m) {
        if ($m['role'] !== 'admin') continue;
        if (in_array($m['type'], ['sport','bds','bde'], true)) return true;
        if ($m['type'] === 'asso') {
            $st = $pdo->prepare("SELECT type FROM associations WHERE id = ? LIMIT 1");
            $st->execute([$m['id']]);
            if (_isFederationType((string)$st->fetchColumn())) return true;
        }
    }
    return false;
}

// peut créer une association (Corpo, admin BDE ou fédé)
function canCreateAssociation(PDO $pdo): bool {
    if (isAdminCorpo()) {
        return true;
    }
    if (!isLoggedIn()) {
        return false;
    }
    foreach (getMemberships() as $m) {
        if ($m['role'] !== 'admin') {
            continue;
        }
        if ($m['type'] === 'bde') {
            return true;
        }
        if ($m['type'] === 'asso') {
            $st = $pdo->prepare("SELECT type FROM associations WHERE id = ? LIMIT 1");
            $st->execute([$m['id']]);
            if (_isFederationType((string)$st->fetchColumn())) {
                return true;
            }
        }
    }
    return false;
}

// peut ajouter un sport sous ce BDS
function canCreateSportUnderBds(PDO $pdo, int $bdsId): bool {
    if (isAdminCorpo()) {
        return true;
    }
    if ($bdsId <= 0 || !isLoggedIn()) {
        return false;
    }
    if (isAdminOf('bds', $bdsId)) {
        return true;
    }

    $st = $pdo->prepare("SELECT ecole FROM associations WHERE id = ? AND type = 'BDS' LIMIT 1");
    $st->execute([$bdsId]);
    $bdsEcole = trim((string)$st->fetchColumn());
    if ($bdsEcole === '' || strcasecmp($bdsEcole, 'toutes') === 0) {
        return false;
    }

    foreach (getMemberships() as $m) {
        if ($m['role'] !== 'admin' || $m['type'] !== 'bde') {
            continue;
        }
        $q = $pdo->prepare("SELECT ecole FROM associations WHERE id = ? LIMIT 1");
        $q->execute([(int)$m['id']]);
        $bdeEcole = trim((string)$q->fetchColumn());
        if ($bdeEcole !== '' && strcasecmp($bdeEcole, $bdsEcole) === 0) {
            return true;
        }
    }

    $refEcoles = $pdo->query(
        "SELECT DISTINCT TRIM(ecole) FROM associations
         WHERE ecole IS NOT NULL AND TRIM(ecole) <> '' AND TRIM(ecole) <> 'Toutes'
         ORDER BY ecole"
    );
    $allEcoles = $refEcoles ? array_values(array_filter(array_map('trim', $refEcoles->fetchAll(PDO::FETCH_COLUMN) ?: []))) : [];
    $managedEcoles = getEcolesCalendrier($pdo, $allEcoles);
    foreach (getMemberships() as $m) {
        if ($m['role'] !== 'admin' || $m['type'] !== 'asso') {
            continue;
        }
        $ty = $pdo->prepare("SELECT type FROM associations WHERE id = ? LIMIT 1");
        $ty->execute([(int)$m['id']]);
        if (!_isFederationType((string)$ty->fetchColumn())) {
            continue;
        }
        if (in_array($bdsEcole, $managedEcoles, true)) {
            return true;
        }
    }

    return false;
}

// --- redirections ---

function requireAdmin(): void {
    if (!isAdminCorpo()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        $loginPath = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'login.php' : 'admin/login.php';
        header('Location: ' . $loginPath);
        exit;
    }
}

function requireMembreCorpo(): void {
    if (!hasAdminPanelAccess()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        $loginPath = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'login.php' : 'admin/login.php';
        header('Location: ' . $loginPath);
        exit;
    }
}

// ancien nom, redirige vers requireMembreCorpo
function requireBureau(): void {
    requireMembreCorpo();
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: /corpo-omnes-site/admin/login.php');
        exit;
    }
}

// --- affichage ---

function roleLabel(string $role): string {
    return match($role) {
        'super_admin'  => 'Super Administrateur',
        'admin_corpo'  => 'Admin Corpo',
        'membre_corpo' => 'Membre Corpo',
        'user'         => 'Étudiant',
        default        => $role,
    };
}

function roleBadge(string $role): string {
    $map = [
        'super_admin'  => ['Super Admin',    '#5D0282'],
        'admin_corpo'  => ['Admin Corpo',    '#007179'],
        'membre_corpo' => ['Membre Corpo',   '#8B2FC9'],
        'user'         => ['Étudiant',       '#555'],
    ];
    [$label, $color] = $map[$role] ?? [$role, '#999'];
    return sprintf(
        '<span style="background:%s;color:#fff;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">%s</span>',
        htmlspecialchars($color), htmlspecialchars($label)
    );
}

function structRoleBadge(string $role): string {
    if ($role === 'admin') {
        return '<span style="background:#FF9500;color:#000;padding:2px 8px;border-radius:999px;font-size:.7rem;font-weight:700">Bureau</span>';
    }
    if ($role === 'membre') {
        return '<span style="background:rgba(124,224,176,.18);color:#7ce0b0;padding:2px 8px;border-radius:999px;font-size:.7rem;font-weight:700">Membre</span>';
    }
    return '<span style="background:rgba(255,255,255,.06);color:#aaa;padding:2px 8px;border-radius:999px;font-size:.7rem">Adhérent</span>';
}

// --- CSRF ---

function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_validate(?string $token): bool {
    return isset($_SESSION['_csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['_csrf_token'], $token);
}

function csrf_rotate(): void {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}
