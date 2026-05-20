<?php
$adminTitle = 'Migrations DB';
$adminPage  = 'migrate';
require_once '../includes/db.php';
require_once 'includes/admin-header.php';

if (!isSuperAdmin()) {
    echo '<div class="flash flash--err">Accès réservé au Super Administrateur.</div>';
    require_once 'includes/admin-footer.php';
    exit;
}

function dbHasColumn(PDO $pdo, string $table, string $col): bool {
    try {
        $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS
                              WHERE TABLE_SCHEMA = DATABASE()
                                AND TABLE_NAME   = ?
                                AND COLUMN_NAME  = ?
                              LIMIT 1");
        $st->execute([$table, $col]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) { return false; }
}
function dbHasTable(PDO $pdo, string $table): bool {
    try {
        $st = $pdo->prepare("SELECT 1 FROM information_schema.TABLES
                              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) { return false; }
}
function dbColumnType(PDO $pdo, string $table, string $col): ?string {
    try {
        $st = $pdo->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
        $st->execute([$table, $col]);
        $v = $st->fetchColumn();
        return $v ? (string)$v : null;
    } catch (Throwable $e) { return null; }
}

$migrations = [];

if (!dbHasColumn($pdo, 'calendrier_scolaire', 'promotions')) {
    $migrations[] = [
        'id'   => 'cal_promotions',
        'desc' => 'calendrier_scolaire - ajouter colonne promotions JSON',
        'sql'  => "ALTER TABLE calendrier_scolaire
                   ADD COLUMN promotions JSON DEFAULT NULL
                   COMMENT 'Liste des promos concernées ; NULL ou [] = toutes les promos de l''école'",
    ];
}

$missingEvtCols = [];
$evtCols = [
    'email_contact'              => "ADD COLUMN email_contact VARCHAR(150) DEFAULT NULL COMMENT 'Email de réception (mode email)'",
    'inscription_message'        => "ADD COLUMN inscription_message TEXT DEFAULT NULL COMMENT 'Message d''info à l''inscription'",
    'prix'                       => "ADD COLUMN prix DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Prix unitaire en euros (0 = gratuit)'",
    'prix_membre'                => "ADD COLUMN prix_membre DECIMAL(10,2) DEFAULT NULL",
    'inscriptions_ouvertes_le'   => "ADD COLUMN inscriptions_ouvertes_le DATETIME DEFAULT NULL",
    'inscriptions_fermees_le'    => "ADD COLUMN inscriptions_fermees_le  DATETIME DEFAULT NULL",
    'max_billets_par_personne'   => "ADD COLUMN max_billets_par_personne TINYINT UNSIGNED NOT NULL DEFAULT 1",
];
foreach ($evtCols as $col => $sqlAdd) {
    if (!dbHasColumn($pdo, 'evenements', $col)) {
        $missingEvtCols[] = $sqlAdd;
    }
}
if ($missingEvtCols) {
    $migrations[] = [
        'id'   => 'evt_billetterie_cols',
        'desc' => 'evenements - ajouter ' . count($missingEvtCols) . ' colonne(s) (prix, fenêtre, etc.)',
        'sql'  => "ALTER TABLE evenements\n  " . implode(",\n  ", $missingEvtCols),
    ];
}

$iconType = dbColumnType($pdo, 'evenements', 'icon') ?: '';
if ($iconType && preg_match('/varchar\((\d+)\)/i', $iconType, $m) && (int)$m[1] < 32) {
    $migrations[] = [
        'id'   => 'evt_icon_utf8',
        'desc' => 'evenements - élargir la colonne icon pour les emojis (VARCHAR 32)',
        'sql'  => "ALTER TABLE evenements MODIFY COLUMN icon VARCHAR(32) DEFAULT NULL",
    ];
}

if (!dbHasColumn($pdo, 'evenements', 'banniere')) {
    $migrations[] = [
        'id'   => 'evt_banniere_col',
        'desc' => 'evenements - bannière image (chemin ou URL)',
        'sql'  => "ALTER TABLE evenements ADD COLUMN banniere VARCHAR(255) DEFAULT NULL COMMENT 'Image bannière (chemin relatif ou URL)' AFTER icon",
    ];
}

if (!dbHasColumn($pdo, 'evenements', 'visibilite')) {
    $migrations[] = [
        'id'   => 'evt_visibilite_col',
        'desc' => 'evenements - visibilité public | membres',
        'sql'  => "ALTER TABLE evenements ADD COLUMN visibilite ENUM('public','membres') NOT NULL DEFAULT 'public' COMMENT 'public=agenda général ; membres=réservé structure' AFTER banniere",
    ];
}

if (!dbHasColumn($pdo, 'evenements', 'inscription_membres')) {
    $migrations[] = [
        'id'   => 'evt_inscription_membres_col',
        'desc' => 'evenements - inscription réservée aux membres/adhérents de la structure',
        'sql'  => "ALTER TABLE evenements ADD COLUMN inscription_membres TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=inscription membres structure uniquement' AFTER visibilite",
    ];
}

$modeType = dbColumnType($pdo, 'evenements', 'mode_inscription') ?: '';
if (!str_contains($modeType, 'connexion') || !str_contains($modeType, 'billetterie_email')) {
    $migrations[] = [
        'id'   => 'evt_mode_enum_v2',
        'desc' => 'evenements - élargir l\'ENUM mode_inscription (aucune, email, connexion, externe, billetterie_email, billetterie_connexion + legacy)',
        'sql'  => "ALTER TABLE evenements
                   MODIFY COLUMN mode_inscription
                   ENUM('aucune','email','interne','connexion','externe','billetterie','billetterie_email','billetterie_connexion') DEFAULT 'aucune'",
    ];
}

try {
    $cntInterne = (int)$pdo->query("SELECT COUNT(*) FROM evenements WHERE mode_inscription='interne'")->fetchColumn();
    $cntBillet  = (int)$pdo->query("SELECT COUNT(*) FROM evenements WHERE mode_inscription='billetterie'")->fetchColumn();
} catch (Throwable $e) { $cntInterne = $cntBillet = 0; }
if ($cntInterne > 0 || $cntBillet > 0) {
    $migrations[] = [
        'id'   => 'evt_mode_data_migration',
        'desc' => "evenements - convertir les valeurs legacy (interne→connexion : $cntInterne, billetterie→billetterie_connexion : $cntBillet)",
        'sql'  => "UPDATE evenements SET mode_inscription='connexion'             WHERE mode_inscription='interne';\n"
                . "UPDATE evenements SET mode_inscription='billetterie_connexion' WHERE mode_inscription='billetterie';",
    ];
}

$roleType = dbColumnType($pdo, 'structure_membres', 'role_in_struct') ?: '';
if ($roleType !== '' && !str_contains($roleType, 'adherent')) {
    $migrations[] = [
        'id'   => 'struct_membres_adherent',
        'desc' => "structure_membres - ajouter le rôle « adherent » (participant à l'asso, non affiché publiquement)",
        'sql'  => "ALTER TABLE structure_membres
                   MODIFY COLUMN role_in_struct
                   ENUM('admin','membre','adherent') NOT NULL DEFAULT 'adherent'",
    ];
}

$statutType = dbColumnType($pdo, 'inscriptions_evenement', 'statut') ?: '';
if (!str_contains($statutType, 'annule') || !str_contains($statutType, 'rembourse')) {
    $migrations[] = [
        'id'   => 'insc_statut_enum',
        'desc' => 'inscriptions_evenement - élargir l\'ENUM statut (+ annule, rembourse)',
        'sql'  => "ALTER TABLE inscriptions_evenement
                   MODIFY COLUMN statut
                   ENUM('en_attente','confirme','refuse','liste_attente','annule','rembourse') DEFAULT 'confirme'",
    ];
}

$missingInsCols = [];
$insCols = [
    'qr_token'           => "ADD COLUMN qr_token VARCHAR(64) DEFAULT NULL",
    'qr_scanned_at'      => "ADD COLUMN qr_scanned_at DATETIME DEFAULT NULL",
    'qr_scanned_by'      => "ADD COLUMN qr_scanned_by INT UNSIGNED DEFAULT NULL",
    'email'              => "ADD COLUMN email VARCHAR(150) DEFAULT NULL",
    'nom'                => "ADD COLUMN nom VARCHAR(120) DEFAULT NULL",
    'prenom'             => "ADD COLUMN prenom VARCHAR(120) DEFAULT NULL",
    'prix_paye'          => "ADD COLUMN prix_paye DECIMAL(10,2) NOT NULL DEFAULT 0.00",
    'paiement_statut'    => "ADD COLUMN paiement_statut ENUM('aucun','en_attente','paye','rembourse','echec') NOT NULL DEFAULT 'aucun'",
    'paiement_provider'  => "ADD COLUMN paiement_provider VARCHAR(40) DEFAULT NULL",
    'paiement_ref'       => "ADD COLUMN paiement_ref VARCHAR(150) DEFAULT NULL",
    'waitlist_position'  => "ADD COLUMN waitlist_position INT UNSIGNED DEFAULT NULL",
];
foreach ($insCols as $col => $sqlAdd) {
    if (!dbHasColumn($pdo, 'inscriptions_evenement', $col)) {
        $missingInsCols[] = $sqlAdd;
    }
}
if ($missingInsCols) {
    $migrations[] = [
        'id'   => 'insc_billetterie_cols',
        'desc' => 'inscriptions_evenement - ajouter ' . count($missingInsCols) . ' colonne(s) (QR, paiement, file d\'attente)',
        'sql'  => "ALTER TABLE inscriptions_evenement\n  " . implode(",\n  ", $missingInsCols),
    ];
}

if (dbHasColumn($pdo, 'inscriptions_evenement', 'qr_token')) {
    try {
        $idx = $pdo->prepare("SELECT 1 FROM information_schema.STATISTICS
                               WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inscriptions_evenement'
                                 AND COLUMN_NAME='qr_token' AND NON_UNIQUE=0 LIMIT 1");
        $idx->execute();
        if (!$idx->fetchColumn()) {
            $migrations[] = [
                'id'   => 'insc_qr_unique',
                'desc' => 'inscriptions_evenement - index UNIQUE sur qr_token',
                'sql'  => "ALTER TABLE inscriptions_evenement
                           ADD UNIQUE KEY uniq_qr_token (qr_token)",
            ];
        }
    } catch (Throwable $e) {}
}

try {
    $oldUniq = $pdo->prepare("SELECT 1 FROM information_schema.STATISTICS
                               WHERE TABLE_SCHEMA=DATABASE()
                                 AND TABLE_NAME='inscriptions_evenement'
                                 AND INDEX_NAME='uniq_insc_evt' LIMIT 1");
    $oldUniq->execute();
    if ($oldUniq->fetchColumn()) {
        $migrations[] = [
            'id'   => 'insc_drop_uniq_user_evt',
            'desc' => 'inscriptions_evenement - supprimer UNIQUE(user_id, evenement_id) pour autoriser plusieurs billets / personne',
            'sql'  => "ALTER TABLE inscriptions_evenement DROP INDEX uniq_insc_evt",
        ];
    }
} catch (Throwable $e) {}

try {
    $st = $pdo->prepare("SELECT IS_NULLABLE FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inscriptions_evenement'
                            AND COLUMN_NAME='user_id' LIMIT 1");
    $st->execute();
    $isNul = $st->fetchColumn();
    if ($isNul === 'NO') {
        $migrations[] = [
            'id'   => 'insc_user_nullable',
            'desc' => 'inscriptions_evenement - rendre user_id nullable (achats invités, mode email/billetterie_email)',
            'sql'  => "ALTER TABLE inscriptions_evenement MODIFY COLUMN user_id INT UNSIGNED DEFAULT NULL",
        ];
    }
} catch (Throwable $e) {}

if (!dbHasColumn($pdo, 'evenements', 'ouvert_externes')) {
    $migrations[] = [
        'id'   => 'evt_ouvert_externes',
        'desc' => 'evenements - ajouter `ouvert_externes` (booléen)',
        'sql'  => "ALTER TABLE evenements ADD COLUMN ouvert_externes TINYINT(1) NOT NULL DEFAULT 1
                   COMMENT '1 = inscription ouverte aux personnes hors écoles invitées (modes email & billetterie_email)'",
    ];
}

if (!dbHasTable($pdo, 'evenement_tarifs')) {
    $migrations[] = [
        'id'   => 'tbl_evenement_tarifs',
        'desc' => 'Créer la table evenement_tarifs (plusieurs catégories de billets par event)',
        'sql'  => "CREATE TABLE evenement_tarifs (
                     id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                     evenement_id      INT UNSIGNED NOT NULL,
                     nom               VARCHAR(100) NOT NULL,
                     description       VARCHAR(255) DEFAULT NULL,
                     prix              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                     places_max        INT UNSIGNED DEFAULT NULL,
                     ecoles_eligibles  JSON DEFAULT NULL COMMENT 'NULL ou [\"Tous\"] = toutes; sinon liste',
                     reserve_membres   TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = uniquement utilisateurs connectés',
                     position          TINYINT NOT NULL DEFAULT 0,
                     statut            ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
                     created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                     KEY idx_tarif_evt (evenement_id),
                     FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE
                   ) ENGINE=InnoDB",
    ];
}
if (!dbHasTable($pdo, 'codes_promo')) {
    $migrations[] = [
        'id'   => 'tbl_codes_promo',
        'desc' => 'Créer la table codes_promo (réductions par code)',
        'sql'  => "CREATE TABLE codes_promo (
                     id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                     code                VARCHAR(40)  NOT NULL,
                     evenement_id        INT UNSIGNED DEFAULT NULL,
                     tarif_id            INT UNSIGNED DEFAULT NULL,
                     type                ENUM('pourcentage','fixe') NOT NULL DEFAULT 'pourcentage',
                     valeur              DECIMAL(10,2) NOT NULL,
                     utilisations_max    INT UNSIGNED DEFAULT NULL,
                     utilisations_count  INT UNSIGNED NOT NULL DEFAULT 0,
                     expire_le           DATETIME DEFAULT NULL,
                     statut              ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
                     created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                     UNIQUE KEY uniq_code_evt (code, evenement_id),
                     KEY idx_code_evt (evenement_id),
                     FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
                     FOREIGN KEY (tarif_id) REFERENCES evenement_tarifs(id) ON DELETE CASCADE
                   ) ENGINE=InnoDB",
    ];
}

if (dbHasTable($pdo, 'evenement_tarifs') && !dbHasColumn($pdo, 'evenement_tarifs', 'frais_a_charge_client')) {
    $migrations[] = [
        'id'   => 'tarif_frais_client',
        'desc' => 'evenement_tarifs - ajouter `frais_a_charge_client` (1 = frais reportés sur le client)',
        'sql'  => "ALTER TABLE evenement_tarifs
                   ADD COLUMN frais_a_charge_client TINYINT(1) NOT NULL DEFAULT 0
                   COMMENT '1 = client paie prix + frais ; 0 = frais à la charge de l''association (déduits du net)'",
    ];
}

if (dbHasTable($pdo, 'evenement_tarifs') && !dbHasColumn($pdo, 'inscriptions_evenement', 'tarif_id')) {
    $migrations[] = [
        'id'   => 'insc_tarif_id',
        'desc' => 'inscriptions_evenement - ajouter `tarif_id` (FK vers evenement_tarifs)',
        'sql'  => "ALTER TABLE inscriptions_evenement
                   ADD COLUMN tarif_id INT UNSIGNED DEFAULT NULL,
                   ADD KEY idx_insc_tarif (tarif_id),
                   ADD CONSTRAINT fk_insc_tarif FOREIGN KEY (tarif_id) REFERENCES evenement_tarifs(id) ON DELETE SET NULL",
    ];
}
if (!dbHasColumn($pdo, 'inscriptions_evenement', 'code_promo_utilise')) {
    $migrations[] = [
        'id'   => 'insc_code_promo',
        'desc' => 'inscriptions_evenement - ajouter `code_promo_utilise`',
        'sql'  => "ALTER TABLE inscriptions_evenement
                   ADD COLUMN code_promo_utilise VARCHAR(40) DEFAULT NULL",
    ];
}

if (!dbHasTable($pdo, 'demandes_renseignement_evenement')) {
    $migrations[] = [
        'id'   => 'tbl_demandes_renseignement',
        'desc' => 'Créer la table demandes_renseignement_evenement',
        'sql'  => "CREATE TABLE demandes_renseignement_evenement (
                     id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                     evenement_id  INT UNSIGNED NOT NULL,
                     email         VARCHAR(150) NOT NULL,
                     nom           VARCHAR(120) DEFAULT NULL,
                     prenom        VARCHAR(120) DEFAULT NULL,
                     ecole         VARCHAR(80)  DEFAULT NULL,
                     message       TEXT         DEFAULT NULL,
                     statut        ENUM('nouveau','traite','spam') DEFAULT 'nouveau',
                     created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                     FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE
                   ) ENGINE=InnoDB",
    ];
}
if (!dbHasTable($pdo, 'paiement_transactions')) {
    $migrations[] = [
        'id'   => 'tbl_paiement_transactions',
        'desc' => 'Créer la table paiement_transactions',
        'sql'  => "CREATE TABLE paiement_transactions (
                     id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                     evenement_id    INT UNSIGNED NOT NULL,
                     inscription_id  INT UNSIGNED DEFAULT NULL,
                     provider        VARCHAR(40)  NOT NULL DEFAULT 'mock',
                     provider_ref    VARCHAR(150) DEFAULT NULL,
                     montant         DECIMAL(10,2) NOT NULL,
                     devise          VARCHAR(8)   NOT NULL DEFAULT 'EUR',
                     statut          ENUM('init','en_attente','paye','echec','annule','rembourse') NOT NULL DEFAULT 'init',
                     email           VARCHAR(150) DEFAULT NULL,
                     user_id         INT UNSIGNED DEFAULT NULL,
                     payload         JSON         DEFAULT NULL,
                     created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                     updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                     FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE
                   ) ENGINE=InnoDB",
    ];
}

if (!dbHasColumn($pdo, 'associations', 'ecoles_eligibles')) {
    $migrations[] = [
        'id'   => 'assos_ecoles_eligibles',
        'desc' => 'associations - ajouter colonne ecoles_eligibles JSON (filtre écoles autorisées à rejoindre)',
        'sql'  => "ALTER TABLE associations
                   ADD COLUMN ecoles_eligibles JSON DEFAULT NULL
                   COMMENT 'Liste des écoles autorisées à rejoindre, NULL = toutes'",
    ];
}

if (!dbHasColumn($pdo, 'associations', 'parent_bde_id')) {
    $migrations[] = [
        'id'   => 'assos_parent_bde_id',
        'desc' => 'associations - ajouter colonne parent_bde_id (BDE parent, NULL = rattachée à la Corpo)',
        'sql'  => "ALTER TABLE associations
                   ADD COLUMN parent_bde_id INT UNSIGNED DEFAULT NULL
                   COMMENT 'BDE parent (NULL = rattaché à la Corpo)'",
    ];
}

if (dbHasColumn($pdo, 'associations', 'parent_bde_id')) {

    try {
        $nbOrphans = (int)$pdo->query(
            "SELECT COUNT(*) FROM associations a
              WHERE a.parent_bde_id IS NULL
                AND a.type NOT IN ('BDE','BDS','Corpo','Fédération')
                AND a.ecole IN ('ECE','HEIP','ESCE')
                AND EXISTS (
                    SELECT 1 FROM associations b
                     WHERE b.ecole = a.ecole
                       AND (b.type = 'BDE' OR b.slug = 'echofed')
                )"
        )->fetchColumn();
    } catch (Throwable $e) { $nbOrphans = 0; }

    if ($nbOrphans > 0) {
        $migrations[] = [
            'id'   => 'assos_rattach_orphelines',
            'desc' => 'associations - rattacher ' . $nbOrphans . ' asso(s) orpheline(s) à leur BDE/Fédération (JEECE → BDE Ginfinity, etc.)',
            'sql'  => "-- ECE : assos orphelines → BDE Ginfinity
UPDATE associations a
   JOIN associations bde ON bde.slug = 'bde-ginfinity'
   SET a.parent_bde_id = bde.id
 WHERE a.parent_bde_id IS NULL
   AND a.ecole = 'ECE'
   AND a.type NOT IN ('BDE','BDS','Corpo','Fédération');
-- HEIP : assos orphelines → EchoFed (fédération)
UPDATE associations a
   JOIN associations fed ON fed.slug = 'echofed'
   SET a.parent_bde_id = fed.id
 WHERE a.parent_bde_id IS NULL
   AND a.ecole = 'HEIP'
   AND a.type NOT IN ('BDE','BDS','Corpo','Fédération');
-- ESCE : assos orphelines → BDE Newolf
UPDATE associations a
   JOIN associations bde ON bde.slug = 'bde-newolf'
   SET a.parent_bde_id = bde.id
 WHERE a.parent_bde_id IS NULL
   AND a.ecole = 'ESCE'
   AND a.type NOT IN ('BDE','BDS','Corpo','Fédération');",
        ];
    }
}

if (dbHasColumn($pdo, 'associations', 'parent_bde_id')) {
    try {
        $pid = $pdo->query("SELECT parent_bde_id FROM associations WHERE slug = 'echofed' LIMIT 1")->fetchColumn();
        if ($pid !== null && $pid !== false && (string)$pid !== '') {
            $migrations[] = [
                'id'   => 'echofed_parent_null',
                'desc' => 'associations - EchoFed : forcer parent_bde_id = NULL (fédération autonome)',
                'sql'  => "UPDATE associations SET parent_bde_id = NULL WHERE slug = 'echofed'",
            ];
        }
    } catch (Throwable $e) {  }
}

if (dbHasTable($pdo, 'structure_membres') && !dbHasColumn($pdo, 'structure_membres', 'resp_evenement')) {
    $migrations[] = [
        'id'   => 'sm_resp_roles',
        'desc' => 'structure_membres - colonnes resp (événements, partenariats, communication, trésorerie)',
        'sql'  => "ALTER TABLE structure_membres
                   ADD COLUMN resp_evenement     TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Responsable événements',
                   ADD COLUMN resp_partenariat   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Responsable partenariats',
                   ADD COLUMN resp_communication TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Responsable communication',
                   ADD COLUMN resp_tresorerie    TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Responsable trésorerie'",
    ];
}

if (dbHasTable($pdo, 'actualites') && !dbHasColumn($pdo, 'actualites', 'visibilite')) {
    $migrations[] = [
        'id'   => 'actus_visibilite',
        'desc' => 'actualites - colonne visibilite (public | membres)',
        'sql'  => "ALTER TABLE actualites
                   ADD COLUMN visibilite ENUM('public','membres') NOT NULL DEFAULT 'public'
                   COMMENT 'membres = visible seulement aux membres de la structure, sans validation Corpo'",
    ];
}

if (!dbHasColumn($pdo, 'associations', 'date_debut_mandat')) {
    $migrations[] = [
        'id'   => 'assos_mandat_dates',
        'desc' => 'associations - dates début / fin de mandat (NULL = à vie ou sans limite)',
        'sql'  => "ALTER TABLE associations
                   ADD COLUMN date_debut_mandat DATE DEFAULT NULL
                     COMMENT 'Début du mandat (NULL = pas de limite)',
                   ADD COLUMN date_fin_mandat DATE DEFAULT NULL
                     COMMENT 'Fin du mandat (NULL = activité à vie)'",
    ];
}

if (!dbHasColumn($pdo, 'associations', 'logo')) {
    $migrations[] = [
        'id'   => 'assos_logo',
        'desc' => 'associations - ajouter colonne logo (chemin local ou URL)',
        'sql'  => "ALTER TABLE associations
                   ADD COLUMN logo VARCHAR(255) DEFAULT NULL
                   COMMENT 'Chemin relatif ou URL externe du logo'",
    ];
}

$sportCols = [
    'logo'             => "ADD COLUMN logo VARCHAR(255) DEFAULT NULL COMMENT 'Chemin ou URL du logo'",
    'lien_acces'       => "ADD COLUMN lien_acces VARCHAR(255) DEFAULT NULL COMMENT 'Lien WhatsApp ou inscription externe'",
    'infra_partenaire' => "ADD COLUMN infra_partenaire VARCHAR(200) DEFAULT NULL COMMENT 'Nom du partenaire infra (salle, piscine…)'",
    'parent_bds_id'    => "ADD COLUMN parent_bds_id INT UNSIGNED DEFAULT NULL COMMENT 'BDS responsable (OMNES Sport ou BDS école)'",
];
$missingSportCols = [];
foreach ($sportCols as $col => $sqlAdd) {
    if (!dbHasColumn($pdo, 'sports', $col)) {
        $missingSportCols[] = $sqlAdd;
    }
}
if ($missingSportCols) {
    $migrations[] = [
        'id'   => 'sports_misc_cols',
        'desc' => 'sports - ajouter ' . count($missingSportCols) . ' colonne(s) (logo, lien_acces, infra_partenaire, parent_bds_id)',
        'sql'  => "ALTER TABLE sports\n  " . implode(",\n  ", $missingSportCols),
    ];
}

if (!dbHasTable($pdo, 'compta_comptes')) {
    $migrations[] = [
        'id'   => 'tbl_compta_comptes',
        'desc' => 'Créer la table compta_comptes (comptes financiers par structure)',
        'sql'  => "CREATE TABLE compta_comptes (
                     id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                     structure_type  ENUM('asso','bde','bds','sport') NOT NULL,
                     structure_id    INT UNSIGNED NOT NULL,
                     nom             VARCHAR(120) NOT NULL,
                     type            ENUM('caisse','banque','autre') NOT NULL DEFAULT 'banque',
                     iban            VARCHAR(40)  DEFAULT NULL,
                     solde_initial   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                     archive         TINYINT(1) NOT NULL DEFAULT 0,
                     created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                     KEY idx_compta_compte_struct (structure_type, structure_id)
                   ) ENGINE=InnoDB",
    ];
}
if (!dbHasTable($pdo, 'compta_categories')) {
    $migrations[] = [
        'id'   => 'tbl_compta_categories',
        'desc' => 'Créer la table compta_categories + catégories par défaut',
        'sql'  => "CREATE TABLE compta_categories (
                     id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                     structure_type  ENUM('asso','bde','bds','sport') DEFAULT NULL,
                     structure_id    INT UNSIGNED DEFAULT NULL,
                     nom             VARCHAR(80) NOT NULL,
                     type            ENUM('recette','depense') NOT NULL,
                     couleur         VARCHAR(10) DEFAULT '#5D0282',
                     icone           VARCHAR(40) DEFAULT NULL,
                     archive         TINYINT(1) NOT NULL DEFAULT 0,
                     created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                     KEY idx_compta_cat_struct (structure_type, structure_id)
                   ) ENGINE=InnoDB;
                   INSERT INTO compta_categories (structure_type, structure_id, nom, type, couleur, icone) VALUES
                   (NULL, NULL, 'Cotisations',         'recette', '#27ae60', 'card'),
                   (NULL, NULL, 'Billetterie',         'recette', '#2980b9', 'ticket'),
                   (NULL, NULL, 'Sponsoring',          'recette', '#8e44ad', 'star'),
                   (NULL, NULL, 'Subvention',          'recette', '#16a085', 'building'),
                   (NULL, NULL, 'Buvette / Bar',       'recette', '#d35400', 'beer'),
                   (NULL, NULL, 'Autres recettes',     'recette', '#7f8c8d', 'plus'),
                   (NULL, NULL, 'Achats / Fournitures','depense', '#c0392b', 'box'),
                   (NULL, NULL, 'Location de salle',   'depense', '#e67e22', 'home'),
                   (NULL, NULL, 'Restauration',        'depense', '#f39c12', 'food'),
                   (NULL, NULL, 'Transport',           'depense', '#3498db', 'bus'),
                   (NULL, NULL, 'Communication / Goodies','depense', '#9b59b6', 'megaphone'),
                   (NULL, NULL, 'Prestataires',        'depense', '#e74c3c', 'briefcase'),
                   (NULL, NULL, 'Frais bancaires',     'depense', '#95a5a6', 'bank'),
                   (NULL, NULL, 'Autres dépenses',     'depense', '#7f8c8d', 'minus')",
    ];
}
if (!dbHasTable($pdo, 'compta_transactions')) {
    $migrations[] = [
        'id'   => 'tbl_compta_transactions',
        'desc' => 'Créer la table compta_transactions (journal des opérations)',
        'sql'  => "CREATE TABLE compta_transactions (
                     id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                     structure_type  ENUM('asso','bde','bds','sport') NOT NULL,
                     structure_id    INT UNSIGNED NOT NULL,
                     compte_id       INT UNSIGNED DEFAULT NULL,
                     categorie_id    INT UNSIGNED DEFAULT NULL,
                     evenement_id    INT UNSIGNED DEFAULT NULL,
                     type            ENUM('recette','depense') NOT NULL,
                     montant         DECIMAL(12,2) NOT NULL,
                     date_operation  DATE NOT NULL,
                     libelle         VARCHAR(200) NOT NULL,
                     notes           TEXT DEFAULT NULL,
                     reference       VARCHAR(80)  DEFAULT NULL,
                     mode_paiement   ENUM('especes','carte','virement','cheque','prelevement','autre') NOT NULL DEFAULT 'virement',
                     justificatif    VARCHAR(255) DEFAULT NULL,
                     cree_par        INT UNSIGNED DEFAULT NULL,
                     created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                     updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                     KEY idx_compta_tx_struct  (structure_type, structure_id, date_operation),
                     KEY idx_compta_tx_compte  (compte_id),
                     KEY idx_compta_tx_cat     (categorie_id),
                     KEY idx_compta_tx_evt     (evenement_id),
                     FOREIGN KEY (compte_id)    REFERENCES compta_comptes(id)    ON DELETE SET NULL,
                     FOREIGN KEY (categorie_id) REFERENCES compta_categories(id) ON DELETE SET NULL,
                     FOREIGN KEY (evenement_id) REFERENCES evenements(id)        ON DELETE SET NULL,
                     FOREIGN KEY (cree_par)     REFERENCES users(id)             ON DELETE SET NULL
                   ) ENGINE=InnoDB",
    ];
}

if (dbHasTable($pdo, 'compta_transactions') && !dbHasColumn($pdo, 'compta_transactions', 'source_type')) {
    $migrations[] = [
        'id'   => 'compta_tx_source_link',
        'desc' => 'compta_transactions - lien billetterie / boutique (source_type, source_id)',
        'sql'  => "ALTER TABLE compta_transactions
                   ADD COLUMN source_type ENUM('manuel','billetterie','boutique') NOT NULL DEFAULT 'manuel'
                     COMMENT 'Origine de l\'écriture' AFTER evenement_id,
                   ADD COLUMN source_id INT UNSIGNED DEFAULT NULL
                     COMMENT 'ID paiement_transactions ou boutique_commande_lignes' AFTER source_type,
                   ADD KEY idx_compta_tx_source (source_type, source_id),
                   ADD UNIQUE KEY uniq_compta_tx_source (source_type, source_id)",
    ];
}

if (dbHasTable($pdo, 'compta_categories')) {
    try {
        $chk = $pdo->query("SELECT 1 FROM compta_categories WHERE structure_type IS NULL AND nom = 'Boutique' LIMIT 1");
        if (!$chk || !$chk->fetchColumn()) {
            $migrations[] = [
                'id'   => 'compta_cat_boutique',
                'desc' => 'Catégorie compta par défaut - Boutique',
                'sql'  => "INSERT INTO compta_categories (structure_type, structure_id, nom, type, couleur, icone)
                           VALUES (NULL, NULL, 'Boutique', 'recette', '#9b59b6', 'bag')",
            ];
        }
    } catch (Throwable $e) {
    }
}

if (!dbHasTable($pdo, 'compta_notes_frais')) {
    $migrations[] = [
        'id'   => 'tbl_compta_notes_frais',
        'desc' => 'Créer compta_notes_frais (demandes de remboursement avec PDF unique)',
        'sql'  => "CREATE TABLE compta_notes_frais (
                     id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                     structure_type        ENUM('asso','bde','bds','sport') NOT NULL,
                     structure_id          INT UNSIGNED NOT NULL,
                     user_id               INT UNSIGNED NOT NULL,
                     montant               DECIMAL(12,2) NOT NULL,
                     date_depense          DATE NOT NULL,
                     libelle               VARCHAR(200) NOT NULL,
                     justificatif_pdf      VARCHAR(255) NOT NULL COMMENT 'Chemin vers un PDF unique',
                     commentaire_membre    TEXT DEFAULT NULL,
                     commentaire_tresorier TEXT DEFAULT NULL,
                     statut                ENUM('soumise','approuvee','refusee','remboursee') NOT NULL DEFAULT 'soumise',
                     traite_par            INT UNSIGNED DEFAULT NULL,
                     traite_le             DATETIME DEFAULT NULL,
                     compta_transaction_id INT UNSIGNED DEFAULT NULL,
                     created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                     updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                     KEY idx_nf_struct_stat (structure_type, structure_id, statut),
                     KEY idx_nf_user (user_id),
                     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                     FOREIGN KEY (traite_par) REFERENCES users(id) ON DELETE SET NULL,
                     FOREIGN KEY (compta_transaction_id) REFERENCES compta_transactions(id) ON DELETE SET NULL
                   ) ENGINE=InnoDB",
    ];
}
if (dbHasTable($pdo, 'compta_transactions') && dbHasColumn($pdo, 'compta_transactions', 'source_type')) {
    $needsNoteFraisSource = true;
    try {
        $ct = $pdo->query(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compta_transactions' AND COLUMN_NAME = 'source_type'"
        )->fetchColumn();
        $needsNoteFraisSource = !str_contains((string)$ct, 'note_frais');
    } catch (Throwable $e) {
        $needsNoteFraisSource = true;
    }
    if ($needsNoteFraisSource) {
    $migrations[] = [
        'id'   => 'compta_tx_source_note_frais',
        'desc' => 'compta_transactions — source_type inclut note_frais',
        'sql'  => "ALTER TABLE compta_transactions
                   MODIFY COLUMN source_type ENUM('manuel','billetterie','boutique','note_frais') NOT NULL DEFAULT 'manuel'",
        ];
    }
}
if (dbHasTable($pdo, 'compta_categories')) {
    try {
        $chk = $pdo->query("SELECT 1 FROM compta_categories WHERE structure_type IS NULL AND nom = 'Notes de frais' LIMIT 1");
        if (!$chk || !$chk->fetchColumn()) {
            $migrations[] = [
                'id'   => 'compta_cat_notes_frais',
                'desc' => 'Catégorie compta — Notes de frais (dépense)',
                'sql'  => "INSERT INTO compta_categories (structure_type, structure_id, nom, type, couleur, icone)
                           VALUES (NULL, NULL, 'Notes de frais', 'depense', '#c0392b', 'receipt')",
            ];
        }
    } catch (Throwable $e) {
    }
}

if (dbHasTable($pdo, 'compta_notes_frais') && !dbHasColumn($pdo, 'compta_notes_frais', 'valide_bureau_par')) {
    $migrations[] = [
        'id'   => 'nf_dual_validation_cols',
        'desc' => 'compta_notes_frais — validation bureau + trésorerie (deux personnes distinctes)',
        'sql'  => "ALTER TABLE compta_notes_frais
                   ADD COLUMN valide_bureau_par INT UNSIGNED DEFAULT NULL AFTER commentaire_membre,
                   ADD COLUMN valide_bureau_le DATETIME DEFAULT NULL,
                   ADD COLUMN commentaire_bureau TEXT DEFAULT NULL,
                   ADD COLUMN valide_treso_par INT UNSIGNED DEFAULT NULL,
                   ADD COLUMN valide_treso_le DATETIME DEFAULT NULL,
                   ADD KEY idx_nf_bureau (valide_bureau_par),
                   ADD KEY idx_nf_treso (valide_treso_par),
                   ADD CONSTRAINT fk_nf_bureau FOREIGN KEY (valide_bureau_par) REFERENCES users(id) ON DELETE SET NULL,
                   ADD CONSTRAINT fk_nf_treso FOREIGN KEY (valide_treso_par) REFERENCES users(id) ON DELETE SET NULL",
    ];
    $migrations[] = [
        'id'   => 'nf_dual_validation_statut',
        'desc' => 'compta_notes_frais — statuts soumise → approuvee_bureau → remboursee',
        'sql'  => "UPDATE compta_notes_frais SET statut = 'approuvee_bureau' WHERE statut = 'approuvee';
                   UPDATE compta_notes_frais SET valide_treso_par = traite_par, valide_treso_le = traite_le
                     WHERE statut = 'remboursee' AND valide_treso_par IS NULL AND traite_par IS NOT NULL;
                   ALTER TABLE compta_notes_frais
                   MODIFY COLUMN statut ENUM('soumise','approuvee_bureau','remboursee','refusee') NOT NULL DEFAULT 'soumise'",
    ];
}

if (!dbHasColumn($pdo, 'users', 'email_verified_at')) {
    $migrations[] = [
        'id'   => 'users_email_verified_at',
        'desc' => 'users - ajouter colonne email_verified_at (date de validation de l\'email)',
        'sql'  => "ALTER TABLE users
                   ADD COLUMN email_verified_at DATETIME DEFAULT NULL
                   COMMENT 'Date de validation de l\'adresse email par lien (NULL = pas encore validée)'",
    ];
}

if (!dbHasTable($pdo, 'email_verifications')) {
    $migrations[] = [
        'id'   => 'tbl_email_verifications',
        'desc' => 'Créer la table email_verifications (tokens de validation des nouveaux comptes)',
        'sql'  => "CREATE TABLE email_verifications (
                     id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                     user_id     INT UNSIGNED NOT NULL,
                     token_hash  CHAR(64) NOT NULL COMMENT 'sha256(token brut envoyé par mail)',
                     expires_at  DATETIME NOT NULL,
                     used_at     DATETIME DEFAULT NULL,
                     created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                     UNIQUE KEY uniq_email_verif_token (token_hash),
                     KEY idx_email_verif_user (user_id),
                     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                   ) ENGINE=InnoDB",
    ];
}

if (!dbHasTable($pdo, 'password_resets')) {
    $migrations[] = [
        'id'   => 'tbl_password_resets',
        'desc' => 'Créer la table password_resets (tokens de réinitialisation, expirent en 1 h)',
        'sql'  => "CREATE TABLE password_resets (
                     id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                     user_id     INT UNSIGNED NOT NULL,
                     token_hash  CHAR(64) NOT NULL COMMENT 'sha256(token brut envoyé par mail)',
                     expires_at  DATETIME NOT NULL,
                     used_at     DATETIME DEFAULT NULL,
                     ip_request  VARCHAR(45) DEFAULT NULL,
                     created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                     UNIQUE KEY uniq_pwd_reset_token (token_hash),
                     KEY idx_pwd_reset_user (user_id),
                     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                   ) ENGINE=InnoDB",
    ];
}

if (!dbHasTable($pdo, 'boutique_produits')) {
    $migrations[] = [
        'id'   => 'tbl_boutique',
        'desc' => 'Créer les tables boutique (produits, commandes, lignes) - paiement SumUp / Stripe',
        'sql'  => "CREATE TABLE boutique_produits (
                     id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                     structure_type          ENUM('asso','bde','bds','sport') NOT NULL DEFAULT 'asso',
                     structure_id            INT UNSIGNED NOT NULL COMMENT 'Référence associations.id',
                     titre                   VARCHAR(200) NOT NULL,
                     description             TEXT,
                     categorie               VARCHAR(80) DEFAULT NULL,
                     taille                  VARCHAR(40) DEFAULT NULL,
                     image                   VARCHAR(500) DEFAULT NULL,
                     prix                    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                     stock                   INT NOT NULL DEFAULT 0,
                     frais_a_charge_client   TINYINT(1) NOT NULL DEFAULT 0,
                     statut                  ENUM('brouillon','publie','archive') NOT NULL DEFAULT 'brouillon',
                     created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                     updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                     KEY idx_boutique_prod_struct (structure_type, structure_id),
                     KEY idx_boutique_prod_statut (statut),
                     CONSTRAINT fk_boutique_prod_struct FOREIGN KEY (structure_id) REFERENCES associations(id) ON DELETE CASCADE
                   ) ENGINE=InnoDB;
                   CREATE TABLE boutique_commandes (
                     id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                     email           VARCHAR(150) NOT NULL,
                     nom             VARCHAR(120) NOT NULL,
                     prenom          VARCHAR(120) NOT NULL,
                     user_id         INT UNSIGNED DEFAULT NULL,
                     montant_total   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                     provider        VARCHAR(40) NOT NULL DEFAULT '',
                     provider_ref    VARCHAR(191) DEFAULT NULL,
                     statut          ENUM('init','en_attente','paye','echec','annule') NOT NULL DEFAULT 'init',
                     payload         JSON DEFAULT NULL,
                     created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                     updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                     UNIQUE KEY uniq_boutique_cmd_provider_ref (provider_ref),
                     KEY idx_boutique_cmd_user (user_id),
                     KEY idx_boutique_cmd_statut (statut),
                     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                   ) ENGINE=InnoDB;
                   CREATE TABLE boutique_commande_lignes (
                     id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                     commande_id     INT UNSIGNED NOT NULL,
                     produit_id      INT UNSIGNED NOT NULL,
                     structure_type  ENUM('asso','bde','bds','sport') NOT NULL DEFAULT 'asso',
                     structure_id    INT UNSIGNED NOT NULL,
                     titre_snapshot  VARCHAR(200) NOT NULL,
                     prix_unitaire   DECIMAL(10,2) NOT NULL,
                     quantite        INT UNSIGNED NOT NULL DEFAULT 1,
                     KEY idx_boutique_ligne_cmd (commande_id),
                     CONSTRAINT fk_boutique_ligne_cmd FOREIGN KEY (commande_id) REFERENCES boutique_commandes(id) ON DELETE CASCADE,
                     CONSTRAINT fk_boutique_ligne_prod FOREIGN KEY (produit_id) REFERENCES boutique_produits(id) ON DELETE RESTRICT
                   ) ENGINE=InnoDB",
    ];
}

$results = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toApply = $_POST['migrate_ids'] ?? [];
    if ($_POST['action'] === 'apply_all') $toApply = array_column($migrations, 'id');

    foreach ($migrations as $mig) {
        if (!in_array($mig['id'], (array)$toApply, true)) continue;
        try {

            $stmts = array_filter(array_map('trim', explode(';', $mig['sql'])));
            foreach ($stmts as $stmt) {
                if ($stmt === '') continue;
                $pdo->exec($stmt);
            }
            $results[$mig['id']] = ['ok' => true, 'msg' => 'Appliquée'];
        } catch (Throwable $e) {
            $results[$mig['id']] = ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    if (!empty($results)) {
        header('Location: migrate.php?done=1');
        exit;
    }
}
?>

<h1 class="admin-page-title">🛠 Migrations de la base de données</h1>

<?php if (!empty($_GET['done'])): ?>
  <div class="flash flash--ok">Migrations appliquées. La page a été rafraîchie pour détecter le nouvel état.</div>
<?php endif; ?>

<div class="flash flash--info">
  <strong>Comment ça marche ?</strong> Cet écran détecte automatiquement les colonnes/tables manquantes
  dans ta base. Tu peux appliquer les migrations en un clic - elles sont <em>idempotentes</em>
  (n'apparaissent que si nécessaires) et n'effacent jamais de données.
</div>

<?php if (empty($migrations)): ?>
  <div class="admin-card" style="text-align:center;padding:var(--s6)">
    <p style="font-size:2rem;margin-bottom:var(--s3)">✓</p>
    <h2 style="margin-bottom:var(--s2)">Base de données à jour</h2>
    <p style="color:var(--text-muted)">Toutes les migrations sont déjà appliquées.</p>
  </div>
<?php else: ?>
  <form method="post">
    <input type="hidden" name="action" value="apply_selected">
    <div class="admin-card" style="padding:0;overflow:hidden">
      <table class="admin-table">
        <thead>
          <tr><th style="width:40px"><input type="checkbox" id="check-all"></th><th>Description</th><th>SQL</th><th>Statut</th></tr>
        </thead>
        <tbody>
        <?php foreach ($migrations as $mig):
          $r = $results[$mig['id']] ?? null;
        ?>
          <tr>
            <td><input type="checkbox" name="migrate_ids[]" value="<?= htmlspecialchars($mig['id']) ?>" class="mig-check" checked></td>
            <td>
              <strong><?= htmlspecialchars($mig['desc']) ?></strong>
              <br><small style="color:var(--text-muted);font-family:monospace"><?= htmlspecialchars($mig['id']) ?></small>
            </td>
            <td><pre style="font-size:.72rem;margin:0;white-space:pre-wrap;color:var(--text-muted);max-width:480px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($mig['sql']) ?></pre></td>
            <td>
              <?php if ($r): ?>
                <?php if ($r['ok']): ?>
                  <span class="badge badge--ok">✓ <?= htmlspecialchars($r['msg']) ?></span>
                <?php else: ?>
                  <span class="badge badge--ko" title="<?= htmlspecialchars($r['msg']) ?>">✗ Erreur</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge badge--pending">En attente</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top:var(--s4);display:flex;gap:var(--s2);flex-wrap:wrap">
      <button type="submit" class="btn btn--primary">Appliquer la sélection →</button>
      <button type="submit" name="action" value="apply_all" class="btn btn--success">⚡ Tout appliquer</button>
      <a href="migrate.php" class="btn" style="background:var(--surface);border-color:var(--border)">Réinitialiser</a>
    </div>
  </form>
<?php endif; ?>

<?php if (!empty($results)): ?>
  <div class="admin-card" style="margin-top:var(--s4)">
    <h2>Résultats détaillés</h2>
    <?php foreach ($results as $id => $r): ?>
      <div style="padding:var(--s3) 0;border-bottom:1px solid var(--border)">
        <strong><?= htmlspecialchars($id) ?></strong> :
        <?php if ($r['ok']): ?>
          <span style="color:var(--green,#3ECF8E)">✓ <?= htmlspecialchars($r['msg']) ?></span>
        <?php else: ?>
          <span style="color:#ff6b6b">✗ <?= htmlspecialchars($r['msg']) ?></span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
document.getElementById('check-all')?.addEventListener('change', function () {
  document.querySelectorAll('.mig-check').forEach(cb => cb.checked = this.checked);
});
</script>

<?php require_once 'includes/admin-footer.php'; ?>
