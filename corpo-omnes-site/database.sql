
CREATE DATABASE IF NOT EXISTS corpo_omnes
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE corpo_omnes;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(80)  NOT NULL UNIQUE,
  -- email est l'adresse principale (mail école si dispo)
  email         VARCHAR(255) NOT NULL UNIQUE,
  email_perso   VARCHAR(255) DEFAULT NULL COMMENT 'Email personnel (hors école)',
  password_hash VARCHAR(255) NOT NULL,
  nom           VARCHAR(100) DEFAULT NULL,
  prenom        VARCHAR(100) DEFAULT NULL,
  ecole         ENUM('ECE','ESCE','HEIP','INSEEC Bachelor','INSEEC BBA','INSEEC BTS','INSEEC GE','INSEEC MSc','Sup de Pub','Autre') DEFAULT NULL,
  programme     VARCHAR(100) DEFAULT NULL COMMENT 'ex: Bachelor, Ingénieur, MBA, MSc…',
  promotion     VARCHAR(20)  DEFAULT NULL COMMENT 'ex: 2026, B3 2027…',
  role          ENUM('super_admin','admin_corpo','membre_corpo','user') DEFAULT 'user',
  statut        ENUM('actif','en_attente','suspendu') DEFAULT 'en_attente',
  email_verified_at DATETIME DEFAULT NULL COMMENT 'Date de validation de l''email (NULL = pas encore validée)',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO users (username, email, password_hash, role, statut) VALUES
('superadmin', 'superadmin@corpo-omnes.fr', 'SETUP_REQUIRED', 'super_admin', 'actif'),
('admincorpo', 'admin@corpo-omnes.fr',      'SETUP_REQUIRED', 'admin_corpo', 'actif');

CREATE TABLE IF NOT EXISTS associations (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug           VARCHAR(120) NOT NULL UNIQUE,
  nom            VARCHAR(150) NOT NULL,
  ecole          VARCHAR(80)  NOT NULL,
  type           VARCHAR(50)  NOT NULL,
  campus         VARCHAR(80)  NOT NULL,
  description    TEXT,
  membres        INT          DEFAULT 0,
  contact        VARCHAR(150),
  instagram      VARCHAR(100),
  ouverte_a_tous TINYINT(1)   DEFAULT 0,
  color          VARCHAR(10)  DEFAULT '#5D0282',
  logo           VARCHAR(255) DEFAULT NULL COMMENT 'Chemin vers le logo (images/assos/xxx.png)',
  parent_bde_id  INT UNSIGNED DEFAULT NULL COMMENT 'BDE parent (NULL = rattaché à la Corpo)',
  ecoles_eligibles JSON DEFAULT NULL COMMENT 'Liste des écoles autorisées à rejoindre, NULL = toutes',
  date_debut_mandat DATE DEFAULT NULL COMMENT 'Début du mandat / période d''activité (NULL = pas de limite)',
  date_fin_mandat   DATE DEFAULT NULL COMMENT 'Fin du mandat (NULL = activité à vie si début vide aussi)',
  FOREIGN KEY (parent_bde_id) REFERENCES associations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO associations (slug, nom, ecole, type, campus, description, membres, contact, instagram, ouverte_a_tous, color, parent_bde_id) VALUES
('corpo-omnes',      'Corpo OMNES',          'Toutes',    'Corpo',       'Tous',       "L'association fédératrice inter-écoles qui coordonne les BDE, accompagne les associations et mutualise les ressources pour une vie étudiante riche sur les deux campus lyonnais.", 15, 'corpoomnes@gmail.com',    'copro_omnes',    1, '#5D0282', NULL),
('omnes-sport',      'OMNES Sport',          'Toutes',    'BDS',         'Tous',       "Le Bureau des Sports inter-écoles coordonne toutes les activités sportives des deux campus. Tournois, entraînements hebdomadaires et compétitions inter-établissements sur 8 disciplines.", 30, 'sport.omnes.lyon@gmail.com',    'omnes_sport_lyon',    1, '#8B2FC9', NULL),
('bde-shot',         'BDE Shot',             'Sup de Pub','BDE',         'Citadelle',  "Le BDE de Sup de Pub anime la communauté des futurs créatifs et communicants. Ateliers créatifs, soirées à thème, portfolio day et projets de communication.",                         16, 'bde.shot.lyon@gmail.com',       'bde_shot_lyon',       0, '#FF5B05', NULL),
('bde-ginfinity',    'BDE Ginfinity',        'ECE',       'BDE',         'Citadelle',  "Le Bureau des Étudiants de l'ECE, Ginfinity, anime la vie étudiante des futurs ingénieurs. Soirées, intégration, voyages et projets associatifs.",                                      18, 'bde.ginfinity.lyon@gmail.com', 'bde_ginfinity',       0, '#007179', NULL),
('bde-hyperion',     'BDE Hyperion',         'HEIP',      'BDE',         'Citadelle',  "Le BDE de l'HEIP, Hyperion, représente les étudiants en sciences politiques. Débats, conférences, voyages diplomatiques et événements culturels.",                                      14, 'bde.hyperion.lyon@gmail.com',  'bde_hyperion_heip',   0, '#E52521', NULL),
('bde-newolf',       'BDE Newolf',           'ESCE',      'BDE',         'Citroën',    "Le BDE de l'ESCE, Newolf, porte la vie étudiante des futurs managers internationaux. Événements, networking, soirées et actions solidaires.",                                           22, 'bde.newolf.lyon@gmail.com',    'bde_newolf_esce',     0, '#002D74', NULL),
('bde-insolute',     "BDE In'Solute",        'INSEEC GE',       'BDE',  'Citroën',    "Le BDE Grande École de l'INSEEC, In'Solute, représente les étudiants du programme GE. Événements festifs, projets associatifs et gestion de la vie de campus.", 20, 'bde.insolute.lyon@gmail.com',  'bde_insolute',        0, '#003DA5', NULL),
('bde-instables',    "BDE In'Stables",       'INSEEC BBA',      'BDE',  'Citroën',    "Le BDE BBA de l'INSEEC, In'Stables, anime la vie étudiante du Bachelor in Business Administration. Soirées, voyages et événements.",                                                    18, 'bde.instables.lyon@gmail.com', 'bde_instables',       0, '#003DA5', NULL),
('bde-the-hangover', 'BDE The Hangover',     'INSEEC Bachelor', 'BDE',  'Citroën',    "Le BDE Bachelor de l'INSEEC, The Hangover, représente les étudiants du cursus Bachelor. Soirées mémorables, intégration des nouveaux et animation du campus Citroën.",                  16, 'bde.hangover.lyon@gmail.com',  'bde_thehangover',     0, '#003DA5', NULL),
('bde-paradise',     'BDE Paradise',         'INSEEC MSc',      'BDE',  'Citroën',    "Le BDE MSc de l'INSEEC, Paradise, anime la communauté des étudiants en Master of Science. Networking professionnel, conférences et événements.",                                        14, 'bde.paradise.lyon@gmail.com',  'bde_paradise_msc',    0, '#003DA5', NULL),
('echofed',          'EchoFed',              'HEIP',      'Fédération',  'Citadelle',  "La fédération des associations de l'HEIP. EchoFed coordonne et représente l'ensemble des clubs et associations de l'école au sein du campus Citadelle.",                                10, 'echofed.heip@gmail.com',       'echofed_heip',        0, '#E52521', NULL),
('agora-nostra',     'Agora Nostra',         'HEIP',      'Association', 'Citadelle',  "Association de diplomatie et politique de l'HEIP. Simulations de négociations, conférences d'élus, modèles ONU et débats sur les grands enjeux géopolitiques.",                         25, 'agora.nostra.heip@gmail.com',  'agora_nostra_heip',   0, '#E52521', NULL),
('terra-vitaia',     'Terra Vitaia',         'HEIP',      'Association', 'Citadelle',  "Association humanitaire de l'HEIP. Projets de terrain, collectes solidaires et sensibilisation aux causes humanitaires internationales.",                                                30, 'terra.vitaia.heip@gmail.com',  'terra_vitaia',        0, '#E52521', NULL),
('aequalis',         'Aequalis',             'HEIP',      'Association', 'Citadelle',  "Association pour le droit des femmes de l'HEIP. Sensibilisation aux inégalités de genre, conférences féministes et actions de plaidoyer pour l'égalité.",                              20, 'aequalis.heip@gmail.com',      'aequalis_heip',       0, '#E52521', NULL),
('invino-veritas',   'InVino Veritas',       'HEIP',      'Association', 'Citadelle',  "Club œnologie de l'HEIP. Dégustations commentées, visites de domaines viticoles en Bourgogne et Rhône, soirées vins du monde.",                                                         18, 'invino.heip@gmail.com',        'invino_veritas_heip', 0, '#E52521', NULL),
('oratores',         'Oratores',             'HEIP',      'Association', 'Citadelle',  "Club d'éloquence de l'HEIP. Art oratoire, débats contradictoires, joutes verbales et préparation aux concours de plaidoirie.",                                                           22, 'oratores.heip@gmail.com',      'oratores_heip',       0, '#E52521', NULL),
('definseec',        "Def'Inseec",           'HEIP',      'Association', 'Citadelle',  "Association défense et sécurité nationale de l'HEIP. Conférences avec des acteurs de la défense, visites d'institutions militaires.",                                                    15, 'definseec.heip@gmail.com',     'definseec',           0, '#E52521', NULL),
('cine-club-esce',   'Ciné Club',            'ESCE',      'Association', 'Citroën',    "Le club cinéma de l'ESCE. Projections en avant-première, soirées ciné-débat, analyse filmique et sorties au cinéma d'art et essai à Lyon.",                                            20, 'cineclub.esce.lyon@gmail.com', 'cineclub_esce',       0, '#002D74', NULL),
('assocuisto',       'AssoCuisto',           'ESCE',      'Association', 'Citroën',    "L'association cuisine de l'ESCE. Cours de cuisine animés par des étudiants ou des chefs, dîners thématiques, soirées gastronomiques.",                                                  25, 'assocuisto.esce@gmail.com',    'assocuisto_esce',     0, '#002D74', NULL),
('promesce',         'PromESCE',             'ESCE',      'Association', 'Citroën',    "L'association de promotion de l'ESCE. Cohésion de promo, création du trombinoscope, organisation des souvenirs de fin d'études.",                                                       12, 'promesce.lyon@gmail.com',      'promesce_lyon',       0, '#002D74', NULL),
('bds-esce',         'BDS ESCE',             'ESCE',      'BDS',         'Citroën',    "Le Bureau des Sports de l'ESCE organise les activités sportives pour les étudiants du campus Citroën. Tournois internes, inscriptions et équipes ESCE.",                                16, 'bds.esce.lyon@gmail.com',      'bds_esce',            0, '#002D74', NULL),
('bds-ece',          'BDS ECE',              'ECE',       'BDS',         'Citadelle',  "Le Bureau des Sports de l'ECE coordonne les activités sportives pour les étudiants ingénieurs. Tournois internes, équipes ECE et représentation sportive.",                             20, 'bds.ece.lyon@gmail.com',       'bds_ece_lyon',        0, '#007179', NULL),
('jeece',            'JEECE',                'ECE',       'Junior',      'Citadelle',  "La Junior-Entreprise de l'ECE Lyon réalise des missions de conseil, développement logiciel et ingénierie pour des clients réels. Formation professionnelle et réseau alumni.",           22, 'jeece.lyon@gmail.com',         'jeece_lyon',          0, '#007179', NULL),
('arece',            'ARECE',                'ECE',       'Association', 'Citadelle',  "Association de robotique et de courses autonomes de l'ECE Lyon. Conception et pilotage de véhicules autonomes, participation à des compétitions nationales.",                            18, 'arece.lyon@gmail.com',         'arece_lyon',          0, '#007179', NULL),
('ece-automobile-club','ECE Automobile Club','ECE',       'Association', 'Citadelle',  "Le club automobile de l'ECE rassemble les passionnés d'automobile et de mécanique. Sorties, rallyes étudiants, conférences constructeurs et visites d'usines.",                        15, 'ece.autoclub@gmail.com',       'ece_auto_club',       0, '#007179', NULL),
('hello-tech-girl',  'Hello Tech Girl',      'ECE',       'Association', 'Citadelle',  "Association de promotion des femmes dans les sciences et les technologies à l'ECE. Mentorat, conférences inspirantes et sensibilisation aux carrières STEM.",                           28, 'hellotechgirl.ece@gmail.com',  'hellotechgirl_ece',   0, '#007179', NULL),
('loophole',         'LoopHole',             'ECE',       'Association', 'Citadelle',  "Le club musique de l'ECE. Concerts live, ateliers musicaux, studio d'enregistrement et jams sessions. Tous les styles, tous les instruments.",                                          24, 'loophole.ece@gmail.com',       'loophole_ece',        0, '#007179', NULL),
('lyontech',         'LyonTech',             'ECE',       'Association', 'Citadelle',  "Association technique et innovation de l'ECE Lyon. Hackathons, projets IoT, intelligence artificielle, développement logiciel et rencontres avec l'écosystème tech lyonnais.",          32, 'lyontech.ece@gmail.com',       'lyontech_ece',        0, '#007179', NULL),
('ece-finance',      'ECE Finance',          'ECE',       'Association', 'Citadelle',  "Club finance et marchés financiers de l'ECE. Simulations boursières, analyse de marchés, conférences avec des professionnels.",                                                         20, 'ece.finance.lyon@gmail.com',   'ece_finance',         0, '#007179', NULL),
('tutorat-ece',      'Tutorat ECE',          'ECE',       'Association', 'Citadelle',  "Association de tutorat de l'ECE. Étudiants avancés accompagnent les plus juniors en maths, physique, informatique et autres matières.",                                                  35, 'tutorat.ece.lyon@gmail.com',   'tutorat_ece',         0, '#007179', NULL),
('sdi-ece',          'SDI',                  'ECE',       'Association', 'Citadelle',  "Le Séminaire d'Intégration de l'ECE organise l'accueil des nouveaux étudiants. Week-end d'intégration, parrainage des premières années.",                                               16, 'sdi.ece.lyon@gmail.com',       'sdi_ece_lyon',        0, '#007179', NULL);

-- c.-à-d. Association, Junior, Club, etc. (ex: JEECE = type 'Junior' à l'ECE).
UPDATE associations SET parent_bde_id = (SELECT id FROM (SELECT id FROM associations WHERE slug='bde-ginfinity') t)
WHERE ecole='ECE' AND type NOT IN ('BDE','BDS','Corpo','Fédération');
UPDATE associations SET parent_bde_id = (SELECT id FROM (SELECT id FROM associations WHERE slug='echofed') t)
WHERE ecole='HEIP' AND type NOT IN ('BDE','BDS','Corpo','Fédération');
UPDATE associations SET parent_bde_id = (SELECT id FROM (SELECT id FROM associations WHERE slug='bde-newolf') t)
WHERE ecole='ESCE' AND type NOT IN ('BDE','BDS','Corpo','Fédération');

-- parent_bds_id est OBLIGATOIRE - jamais NULL en pratique.
CREATE TABLE IF NOT EXISTS sports (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug              VARCHAR(80)  NOT NULL UNIQUE,
  nom               VARCHAR(100) NOT NULL,
  icon              VARCHAR(10),
  couleur           VARCHAR(10)  DEFAULT '#5D0282',
  categorie         VARCHAR(30)  DEFAULT 'club',
  description       TEXT,
  campus            VARCHAR(80),
  places            INT          DEFAULT 0,
  inscrits          INT          DEFAULT 0,
  lien_acces        VARCHAR(255) DEFAULT NULL COMMENT 'Lien WhatsApp ou inscription externe',
  infra_partenaire  VARCHAR(200) DEFAULT NULL COMMENT 'Nom du partenaire infra (salle, piscine…)',
  logo              VARCHAR(255) DEFAULT NULL COMMENT 'Chemin relatif ou URL du logo (images/sports/…)',
  parent_bds_id     INT UNSIGNED NOT NULL     COMMENT 'BDS responsable (OMNES Sport ou BDS école)',
  FOREIGN KEY (parent_bds_id) REFERENCES associations(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Insertion des sports clubs - parent_bds_id = OMNES Sport (récupéré via sous-requête)
INSERT INTO sports (slug, nom, icon, couleur, categorie, description, campus, places, inscrits, parent_bds_id)
SELECT 'basket', 'Basketball', '🏀', '#FF9500', 'club',
  "Deux séances par semaine au gymnase Citroën. On joue en championnat universitaire lyonnais et on organise un tournoi 3×3 à l'automne. Tous niveaux bienvenus.",
  'Citroën', 15, 10, id FROM associations WHERE slug='omnes-sport'
UNION ALL
SELECT 'foot', 'Football', '⚽', '#27AE60', 'club',
  "Mardi et jeudi soir à Villeurbanne. L'équipe dispute le championnat universitaire avec les autres grandes écoles lyonnaises. Quelques places encore dispo pour la saison.",
  'Tous', 20, 14, id FROM associations WHERE slug='omnes-sport'
UNION ALL
SELECT 'rugby', 'Rugby', '🏉', '#E52521', 'club',
  "Le club le plus engagé du campus. Mercredi soir et samedi matin au stade de Villeurbanne. On joue en compétitions régionales - pas besoin d'avoir déjà joué.",
  'Tous', 22, 16, id FROM associations WHERE slug='omnes-sport'
UNION ALL
SELECT 'cheerleading', 'Cheerleading', '📣', '#8B2FC9', 'club',
  "Zéro prérequis, zéro prise de tête. On répète mardi soir et jeudi midi à Citadelle. L'équipe prépare un showcase pour décembre - les nouvelles têtes sont les bienvenues.",
  'Citadelle', 20, 17, id FROM associations WHERE slug='omnes-sport';

CREATE TABLE IF NOT EXISTS sport_referents (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sport_id  INT UNSIGNED NOT NULL,
  initiales VARCHAR(5),
  nom       VARCHAR(100),
  role      VARCHAR(80),
  email     VARCHAR(150),
  FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO sport_referents (sport_id, initiales, nom, role, email) VALUES
(1, 'MB', 'Marc Bonnet',     'Capitaine', 'basket.omnes.lyon@gmail.com'),
(2, 'TL', 'Thomas Leroy',    'Capitaine', 'foot.omnes.lyon@gmail.com'),
(3, 'AL', 'Alexandre Lopes', 'Capitaine', 'rugby.omnes.lyon@gmail.com'),
(4, 'CL', 'Camille Laurent', 'Capitaine', 'cheer.omnes.lyon@gmail.com');

CREATE TABLE IF NOT EXISTS sport_entrainements (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sport_id  INT UNSIGNED NOT NULL,
  jour      VARCHAR(20),
  heure     VARCHAR(40),
  lieu      VARCHAR(200),
  FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO sport_entrainements (sport_id, jour, heure, lieu) VALUES
(1, 'Lundi',    '19h00 – 21h00', 'Gymnase Campus Citroën'),
(1, 'Vendredi', '17h00 – 19h00', 'Gymnase Campus Citroën'),
(2, 'Mardi',    '18h00 – 20h00', 'Stade Municipal Villeurbanne'),
(2, 'Jeudi',    '18h00 – 20h00', 'Stade Municipal Villeurbanne'),
(3, 'Mercredi', '17h30 – 19h30', 'Stade Municipal Villeurbanne'),
(3, 'Samedi',   '10h00 – 12h00', 'Stade Municipal Villeurbanne'),
(4, 'Mardi',    '18h30 – 20h00', 'Gymnase Campus Citadelle'),
(4, 'Jeudi',    '12h30 – 14h00', 'Gymnase Campus Citadelle');

CREATE TABLE IF NOT EXISTS sport_evenements (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sport_id  INT UNSIGNED NOT NULL,
  titre     VARCHAR(200),
  date      DATE,
  lieu      VARCHAR(200),
  FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO sport_evenements (sport_id, titre, date, lieu) VALUES
(1, 'Tournoi 3×3 inter-écoles',           '2026-10-17', 'Gymnase Campus Citroën'),
(2, 'Tournoi inter-écoles Omnes',          '2026-10-03', 'Stade Municipal Villeurbanne'),
(3, 'Challenge Universitaire Rhône-Alpes', '2026-11-14', 'Stade de la Plaine - Lyon'),
(4, 'Showcase inter-écoles',               '2026-12-05', 'Campus Citadelle - Grand Hall');

CREATE TABLE IF NOT EXISTS sport_resultats (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sport_id   INT UNSIGNED NOT NULL,
  adversaire VARCHAR(150),
  score      VARCHAR(40),
  date       DATE,
  victoire   TINYINT(1) DEFAULT NULL,
  FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO sport_resultats (sport_id, adversaire, score, date, victoire) VALUES
(1, 'INSA Lyon',        '72 – 58',           '2026-03-15', 1),
(1, 'Sciences Po Lyon', '61 – 65',           '2026-02-28', 0),
(2, 'EM Lyon',          '3 – 1',             '2026-03-22', 1),
(2, 'Centrale Lyon',    '2 – 2',             '2026-03-08', NULL),
(3, 'Grenoble INP',     '18 – 22',           '2026-03-19', 0),
(3, 'IEP Lyon',         '31 – 12',           '2026-03-05', 1),
(4, 'Showcase rentrée', "Médaille d'argent", '2026-09-20', 1);

-- (interne / billetterie = valeurs legacy, conservées dans l'ENUM pour rétrocompat)
CREATE TABLE IF NOT EXISTS evenements (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug                     VARCHAR(120) NOT NULL UNIQUE,
  titre                    VARCHAR(200) NOT NULL,
  date                     DATE         NOT NULL,
  date_fin                 DATE         DEFAULT NULL COMMENT 'Date de fin (optionnelle)',
  heure                    VARCHAR(20),
  heure_fin                VARCHAR(20)  DEFAULT NULL COMMENT 'Heure de fin (optionnelle)',
  lieu                     VARCHAR(200),
  campus                   VARCHAR(80)  COMMENT 'Valeur legacy déduite de campus_invites',
  organisateur             VARCHAR(150),
  structure_type           ENUM('corpo','asso','sport') DEFAULT 'corpo',
  structure_id             INT UNSIGNED DEFAULT NULL,
  type                     VARCHAR(50),
  description              TEXT,
  mode_inscription         ENUM('aucune','email','interne','connexion','externe','billetterie','billetterie_email','billetterie_connexion') DEFAULT 'aucune',
  lien_billetterie         VARCHAR(255) DEFAULT NULL,
  email_contact            VARCHAR(150) DEFAULT NULL COMMENT 'Adresse de réception des inscriptions par mail',
  inscription_message      TEXT         DEFAULT NULL COMMENT 'Message d''information affiché aux participants',
  places                   INT          DEFAULT 0,
  inscrits                 INT          DEFAULT 0  COMMENT 'Compteur mis à jour auto',
  prix                     DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Prix de base (utilisé si aucun tarif défini dans evenement_tarifs)',
  prix_membre              DECIMAL(10,2) DEFAULT NULL          COMMENT 'Prix membre (NULL = même prix)',
  max_billets_par_personne TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Nombre max de billets par personne',
  inscriptions_ouvertes_le DATETIME     DEFAULT NULL,
  inscriptions_fermees_le  DATETIME     DEFAULT NULL,
  ouvert_externes          TINYINT(1)   NOT NULL DEFAULT 1     COMMENT '1 = inscription ouverte aux personnes hors écoles invitées (modes email & billetterie_email)',
  icon                     VARCHAR(32)  DEFAULT NULL COMMENT 'Emoji ou pictogramme (UTF-8)',
  banniere                 VARCHAR(255) DEFAULT NULL COMMENT 'Image bannière (chemin relatif ou URL)',
  -- public = agenda général ; membres = visible seulement par les adhérents/membres de la structure (+ page asso / mes assos)
  visibilite               ENUM('public','membres') NOT NULL DEFAULT 'public',
  inscription_membres      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = inscription réservée aux adhérents/membres de la structure',
  ecoles_invitees          JSON         DEFAULT NULL COMMENT 'Ex: ["ECE","HEIP"] ou ["Tous"]',
  campus_invites           JSON         DEFAULT NULL COMMENT 'Ex: ["Citroën"] ou ["Tous"]',
  affichage_tv             TINYINT(1)   DEFAULT 1   COMMENT '1 = visible sur les écrans TV campus',
  auteur_id                INT UNSIGNED DEFAULT NULL,
  statut                   ENUM('en_attente','publie','refuse') DEFAULT 'publie',
  FOREIGN KEY (auteur_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO evenements (slug, titre, date, heure, lieu, campus, organisateur, structure_type, type, description, mode_inscription, places, icon, ecoles_invitees, campus_invites, affichage_tv, statut) VALUES
('soiree-integration-2026', "Soirée d'intégration", '2026-09-30', '20h00', 'Campus Citroën, Lyon', 'Tous campus', 'Corpo Omnes Lyon', 'corpo', 'Corpo',
 "La grande soirée d'intégration inter-écoles pour accueillir les nouveaux étudiants des cinq écoles Omnes Lyon. Une nuit pour se rencontrer, créer des liens et démarrer l'année ensemble.",
 'connexion', 300, '🎉', '["Tous"]', '["Citroën","Citadelle"]', 1, 'publie');

CREATE TABLE IF NOT EXISTS partenaires (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom             VARCHAR(150) NOT NULL,
  type            VARCHAR(60),
  logo            VARCHAR(255) DEFAULT 'images/partner-placeholder.png',
  offre           VARCHAR(255),
  code            VARCHAR(50),
  campus          VARCHAR(80),
  lien            VARCHAR(255) DEFAULT '#',
  description     TEXT,
  structure_type  ENUM('corpo','asso','sport') DEFAULT 'corpo',
  structure_id    INT UNSIGNED DEFAULT NULL,
  auteur_id       INT UNSIGNED DEFAULT NULL,
  statut          ENUM('en_attente','publie','refuse') DEFAULT 'publie',
  FOREIGN KEY (auteur_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO partenaires (nom, type, logo, offre, code, campus, lien, description, structure_type, statut) VALUES
('Urban Gym Lyon',            'Sport',        'images/partner-placeholder.png', '−20% sur l\'abonnement mensuel', 'OMNES20',      'Tous',      '#', 'Accès à 3 salles de sport à Lyon 7, Lyon 3 et Villeurbanne. Cours collectifs inclus avec le code promo.', 'corpo', 'publie'),
('Café Confluences',          'Restauration', 'images/partner-placeholder.png', 'Café offert pour tout achat',     'CORPO2026',    'Citadelle', '#', 'Bar-restaurant à deux pas du campus Citadelle. Menu étudiant à 9€ le midi, du lundi au vendredi.', 'corpo', 'publie'),
('La Bibliothèque du Cinéma', 'Culture',      'images/partner-placeholder.png', 'Tarif étudiant −30%',             'OMNESCULTURE', 'Tous',      '#', 'Séances de cinéma d\'art et d\'essai à Lyon. Accès à la médiathèque cinématographique sur présentation de la carte étudiante.', 'corpo', 'publie'),
('Maison de la Danse',        'Culture',      'images/partner-placeholder.png', 'Places à 7€ (prix normal 22€)',   'BDEOMNES',     'Tous',      '#', 'Réductions sur les spectacles de danse contemporaine à Lyon. Quota de places réservées aux étudiants Omnes chaque saison.', 'corpo', 'publie'),
('Co-Working Station Lyon',   'Travail',      'images/partner-placeholder.png', '1 journée gratuite / mois',       'CORPORWORK',   'Tous',      '#', 'Espace de co-working au cœur de Lyon Confluence. Idéal pour les projets associatifs, les juniors entreprises et les startups étudiantes.', 'corpo', 'publie'),
('Association solidaire du Rhône', 'RSE',     'images/partner-placeholder.png', 'Opportunités de bénévolat',       NULL,           'Tous',      '#', 'Partenariat RSE : les étudiants peuvent rejoindre les actions de terrain (maraudes, ateliers) et valider des heures de bénévolat.', 'corpo', 'publie');

CREATE TABLE IF NOT EXISTS actualites (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titre          VARCHAR(255) NOT NULL,
  contenu        TEXT         NOT NULL,
  structure_type ENUM('corpo','asso','sport') DEFAULT 'corpo',
  structure_id   INT UNSIGNED DEFAULT NULL,
  auteur_id      INT UNSIGNED NOT NULL,
  statut         ENUM('en_attente','publie','refuse') DEFAULT 'en_attente',
  -- public = flux général (validation Corpo si hors admin corpo) ; membres = réservé aux adhérents de la structure, sans validation Corpo
  visibilite     ENUM('public','membres') NOT NULL DEFAULT 'public',
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (auteur_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- structure_type : asso / bde / sport / bds
-- role_in_struct : admin (gère) / membre (consulte)
CREATE TABLE IF NOT EXISTS structure_membres (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  structure_type  ENUM('asso','bde','sport','bds') NOT NULL,
  structure_id    INT UNSIGNED NOT NULL,
  -- 3 niveaux :
  role_in_struct  ENUM('admin','membre','adherent') DEFAULT 'adherent',
  resp_evenement      TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Responsable événements',
  resp_partenariat    TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Responsable partenariats',
  resp_communication  TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Responsable communication (actus)',
  resp_tresorerie     TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Responsable trésorerie (compta)',
  statut          ENUM('actif','en_attente','refuse') DEFAULT 'en_attente',
  invited_by      INT UNSIGNED DEFAULT NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY unique_membre (user_id, structure_type, structure_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inscriptions_sport (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  sport_id   INT UNSIGNED NOT NULL,
  statut     ENUM('en_attente','confirme','refuse','liste_attente') DEFAULT 'confirme',
  message    TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_insc_sport (user_id, sport_id),
  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- (achat multi-billets, ou achat invité sans compte).
-- user_id est nullable pour permettre les achats sans compte (modes "email"
-- et "billetterie_email"). On identifie alors le participant via email + nom + prenom.
CREATE TABLE IF NOT EXISTS inscriptions_evenement (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id            INT UNSIGNED DEFAULT NULL,
  evenement_id       INT UNSIGNED NOT NULL,
  tarif_id           INT UNSIGNED DEFAULT NULL    COMMENT 'NULL si pas de tarif catégorisé (mode email gratuit, etc.)',
  statut             ENUM('en_attente','confirme','refuse','liste_attente','annule','rembourse') DEFAULT 'confirme',
  email              VARCHAR(150) DEFAULT NULL,
  nom                VARCHAR(120) DEFAULT NULL,
  prenom             VARCHAR(120) DEFAULT NULL,
  qr_token           VARCHAR(64)  DEFAULT NULL UNIQUE COMMENT 'Token aléatoire encodé dans le QR',
  qr_scanned_at      DATETIME     DEFAULT NULL        COMMENT 'Horodatage du scan d''entrée',
  qr_scanned_by      INT UNSIGNED DEFAULT NULL        COMMENT 'User_id du scanneur',
  prix_paye          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  code_promo_utilise VARCHAR(40)  DEFAULT NULL    COMMENT 'Code promo appliqué à cette inscription',
  paiement_statut    ENUM('aucun','en_attente','paye','rembourse','echec') NOT NULL DEFAULT 'aucun',
  paiement_provider  VARCHAR(40)  DEFAULT NULL COMMENT 'sumup | stripe | manuel | mock',
  paiement_ref       VARCHAR(150) DEFAULT NULL COMMENT 'ID de la transaction côté provider',
  waitlist_position  INT UNSIGNED DEFAULT NULL COMMENT 'Position dans la file (1 = premier promu)',
  created_at         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  KEY idx_insc_evt (evenement_id),
  KEY idx_insc_user_evt (user_id, evenement_id),
  KEY idx_insc_email_evt (evenement_id, email),
  KEY idx_insc_tarif (tarif_id),
  FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE SET NULL,
  FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
  FOREIGN KEY (qr_scanned_by) REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evenement_tarifs (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  evenement_id      INT UNSIGNED NOT NULL,
  nom               VARCHAR(100) NOT NULL,
  description       VARCHAR(255) DEFAULT NULL,
  prix              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  places_max        INT UNSIGNED DEFAULT NULL   COMMENT 'NULL = limité par places totales de l''event',
  ecoles_eligibles  JSON DEFAULT NULL           COMMENT 'NULL ou ["Tous"] = toutes; sinon liste d''écoles',
  reserve_membres   TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = uniquement utilisateurs connectés',
  frais_a_charge_client TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = client paie prix + frais ; 0 = frais à la charge de l''association',
  position          TINYINT NOT NULL DEFAULT 0,
  statut            ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tarif_evt (evenement_id),
  FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS codes_promo (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code                VARCHAR(40)  NOT NULL,
  evenement_id        INT UNSIGNED DEFAULT NULL   COMMENT 'NULL = global tous events',
  tarif_id            INT UNSIGNED DEFAULT NULL   COMMENT 'NULL = applicable à tous les tarifs',
  type                ENUM('pourcentage','fixe') NOT NULL DEFAULT 'pourcentage',
  valeur              DECIMAL(10,2) NOT NULL      COMMENT '% (0-100) ou montant en €',
  utilisations_max    INT UNSIGNED DEFAULT NULL   COMMENT 'NULL = illimité',
  utilisations_count  INT UNSIGNED NOT NULL DEFAULT 0,
  expire_le           DATETIME DEFAULT NULL,
  statut              ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_code_evt (code, evenement_id),
  KEY idx_code_evt (evenement_id),
  FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
  FOREIGN KEY (tarif_id) REFERENCES evenement_tarifs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS demandes_validation (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  -- 'sport' = création/modification d'un sport (hors scores)
  -- 'nouvelle_asso' = proposition de création d'une nouvelle association
  type            ENUM('evenement','partenaire','offre_partenaire','actualite','contenu','sport','nouvelle_asso') NOT NULL,
  structure_type  ENUM('corpo','asso','sport','bde','bds') NOT NULL,
  structure_id    INT UNSIGNED DEFAULT NULL,
  payload         JSON         NOT NULL,
  statut          ENUM('en_attente','valide','refuse') DEFAULT 'en_attente',
  validated_by    INT UNSIGNED DEFAULT NULL,
  validated_at    DATETIME     DEFAULT NULL,
  message_refus   TEXT         DEFAULT NULL,
  created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- structure_type élargi : asso / bde / sport / bds
CREATE TABLE IF NOT EXISTS demandes_adhesion (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  structure_type  ENUM('asso','bde','sport','bds') NOT NULL,
  structure_id    INT UNSIGNED NOT NULL,
  message         TEXT         DEFAULT NULL,
  statut          ENUM('en_attente','accepte','refuse') DEFAULT 'en_attente',
  traite_par      INT UNSIGNED DEFAULT NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (traite_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS demandes_partenariat (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom_contact  VARCHAR(150),
  email        VARCHAR(150),
  organisation VARCHAR(200),
  telephone    VARCHAR(30),
  type_offre   VARCHAR(100),
  message      TEXT,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calendrier_scolaire (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ecole       VARCHAR(80) NOT NULL COMMENT 'ECE | ESCE | HEIP | INSEEC Bachelor | … | Tous',
  type        ENUM('vacances','examens','rattrapages','rentree','evenement_academique','autre') NOT NULL,
  titre       VARCHAR(200) NOT NULL,
  date_debut  DATE NOT NULL,
  date_fin    DATE DEFAULT NULL,
  notes       TEXT DEFAULT NULL,
  promotions  JSON DEFAULT NULL COMMENT 'Liste des promos concernées ; NULL ou [] = toutes les promos de l''école',
  auteur_id   INT UNSIGNED DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (auteur_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS demandes_renseignement_evenement (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS paiement_transactions (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  evenement_id    INT UNSIGNED NOT NULL,
  inscription_id  INT UNSIGNED DEFAULT NULL,
  provider        VARCHAR(40)  NOT NULL DEFAULT 'mock' COMMENT 'sumup | stripe | mock | manuel',
  provider_ref    VARCHAR(150) DEFAULT NULL            COMMENT 'checkout_id côté provider',
  montant         DECIMAL(10,2) NOT NULL,
  devise          VARCHAR(8)   NOT NULL DEFAULT 'EUR',
  statut          ENUM('init','en_attente','paye','echec','annule','rembourse') NOT NULL DEFAULT 'init',
  email           VARCHAR(150) DEFAULT NULL,
  user_id         INT UNSIGNED DEFAULT NULL,
  payload         JSON         DEFAULT NULL,
  created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS boutique_produits (
  id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  structure_type          ENUM('asso','bde','bds','sport') NOT NULL DEFAULT 'asso',
  structure_id            INT UNSIGNED NOT NULL COMMENT 'Référence associations.id',
  titre                   VARCHAR(200) NOT NULL,
  description             TEXT,
  categorie               VARCHAR(80) DEFAULT NULL COMMENT 'Type d’article (goodies, textile…)',
  taille                  VARCHAR(40) DEFAULT NULL COMMENT 'Taille textile ; NULL si non applicable',
  image                   VARCHAR(500) DEFAULT NULL,
  prix                    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  stock                   INT NOT NULL DEFAULT 0,
  frais_a_charge_client   TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = frais prestataire refacturés au client (comme billetterie)',
  statut                  ENUM('brouillon','publie','archive') NOT NULL DEFAULT 'brouillon',
  created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_boutique_prod_struct (structure_type, structure_id),
  KEY idx_boutique_prod_statut (statut),
  CONSTRAINT fk_boutique_prod_struct FOREIGN KEY (structure_id) REFERENCES associations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS boutique_commandes (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email           VARCHAR(150) NOT NULL,
  nom             VARCHAR(120) NOT NULL,
  prenom          VARCHAR(120) NOT NULL,
  user_id         INT UNSIGNED DEFAULT NULL,
  montant_total   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  provider        VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'sumup|stripe|mock|mock_stripe|free',
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

CREATE TABLE IF NOT EXISTS boutique_commande_lignes (
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
) ENGINE=InnoDB;

-- éventuellement liées à un événement.
CREATE TABLE IF NOT EXISTS compta_comptes (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  structure_type  ENUM('asso','bde','bds','sport') NOT NULL,
  structure_id    INT UNSIGNED NOT NULL,
  nom             VARCHAR(120) NOT NULL,
  type            ENUM('caisse','banque','autre') NOT NULL DEFAULT 'banque',
  iban            VARCHAR(40)  DEFAULT NULL,
  solde_initial   DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Solde de départ ajouté aux transactions',
  archive         TINYINT(1) NOT NULL DEFAULT 0,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_compta_compte_struct (structure_type, structure_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS compta_categories (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  structure_type  ENUM('asso','bde','bds','sport') DEFAULT NULL COMMENT 'NULL = catégorie globale (modèle Corpo)',
  structure_id    INT UNSIGNED DEFAULT NULL,
  nom             VARCHAR(80) NOT NULL,
  type            ENUM('recette','depense') NOT NULL,
  couleur         VARCHAR(10) DEFAULT '#5D0282',
  icone           VARCHAR(40) DEFAULT NULL,
  archive         TINYINT(1) NOT NULL DEFAULT 0,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_compta_cat_struct (structure_type, structure_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS compta_transactions (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  structure_type  ENUM('asso','bde','bds','sport') NOT NULL,
  structure_id    INT UNSIGNED NOT NULL,
  compte_id       INT UNSIGNED DEFAULT NULL,
  categorie_id    INT UNSIGNED DEFAULT NULL,
  evenement_id    INT UNSIGNED DEFAULT NULL,
  source_type     ENUM('manuel','billetterie','boutique') NOT NULL DEFAULT 'manuel' COMMENT 'Origine de l''écriture',
  source_id       INT UNSIGNED DEFAULT NULL COMMENT 'ID paiement_transactions ou boutique_commande_lignes',
  type            ENUM('recette','depense') NOT NULL,
  montant         DECIMAL(12,2) NOT NULL COMMENT 'Toujours positif. Le signe est porté par `type`.',
  date_operation  DATE NOT NULL,
  libelle         VARCHAR(200) NOT NULL,
  notes           TEXT DEFAULT NULL,
  reference       VARCHAR(80)  DEFAULT NULL COMMENT 'N° facture / pièce',
  mode_paiement   ENUM('especes','carte','virement','cheque','prelevement','autre') NOT NULL DEFAULT 'virement',
  justificatif    VARCHAR(255) DEFAULT NULL COMMENT 'Chemin du PDF/JPG uploadé',
  cree_par        INT UNSIGNED DEFAULT NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_compta_tx_struct  (structure_type, structure_id, date_operation),
  KEY idx_compta_tx_compte  (compte_id),
  KEY idx_compta_tx_cat     (categorie_id),
  KEY idx_compta_tx_evt     (evenement_id),
  KEY idx_compta_tx_source  (source_type, source_id),
  UNIQUE KEY uniq_compta_tx_source (source_type, source_id),
  FOREIGN KEY (compte_id)    REFERENCES compta_comptes(id)    ON DELETE SET NULL,
  FOREIGN KEY (categorie_id) REFERENCES compta_categories(id) ON DELETE SET NULL,
  FOREIGN KEY (evenement_id) REFERENCES evenements(id)        ON DELETE SET NULL,
  FOREIGN KEY (cree_par)     REFERENCES users(id)             ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tokens jetables, hachés côté serveur (sha256) ; un token clair n'existe que
-- dans l'URL envoyée par mail. Expiration : 24 h pour vérif compte, 1 h pour
-- reset mot de passe.
CREATE TABLE IF NOT EXISTS email_verifications (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64) NOT NULL COMMENT 'sha256(token brut)',
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_email_verif_token (token_hash),
  KEY idx_email_verif_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64) NOT NULL COMMENT 'sha256(token brut)',
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME DEFAULT NULL,
  ip_request  VARCHAR(45) DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_pwd_reset_token (token_hash),
  KEY idx_pwd_reset_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO compta_categories (structure_type, structure_id, nom, type, couleur, icone) VALUES
(NULL, NULL, 'Cotisations',       'recette', '#27ae60', 'card'),
(NULL, NULL, 'Billetterie',       'recette', '#2980b9', 'ticket'),
(NULL, NULL, 'Boutique',          'recette', '#9b59b6', 'bag'),
(NULL, NULL, 'Sponsoring',        'recette', '#8e44ad', 'star'),
(NULL, NULL, 'Subvention',        'recette', '#16a085', 'building'),
(NULL, NULL, 'Buvette / Bar',     'recette', '#d35400', 'beer'),
(NULL, NULL, 'Autres recettes',   'recette', '#7f8c8d', 'plus'),
(NULL, NULL, 'Achats / Fournitures','depense', '#c0392b', 'box'),
(NULL, NULL, 'Location de salle', 'depense', '#e67e22', 'home'),
(NULL, NULL, 'Restauration',      'depense', '#f39c12', 'food'),
(NULL, NULL, 'Transport',         'depense', '#3498db', 'bus'),
(NULL, NULL, 'Communication / Goodies','depense', '#9b59b6', 'megaphone'),
(NULL, NULL, 'Prestataires',      'depense', '#e74c3c', 'briefcase'),
(NULL, NULL, 'Frais bancaires',   'depense', '#95a5a6', 'bank'),
(NULL, NULL, 'Autres dépenses',   'depense', '#7f8c8d', 'minus');

-- `admin/migrate.php` applique les ALTER manquants de façon idempotente.
--   • inscriptions_evenement : QR, paiement, tarif_id, code_promo_utilise, user_id nullable, DROP uniq user+event
--   • compta_comptes, compta_categories, compta_transactions + INSERT catégories par défaut
-- ALTER TABLE demandes_validation
-- ALTER TABLE evenements
-- UPDATE evenements SET mode_inscription='connexion'             WHERE mode_inscription='interne';
-- UPDATE evenements SET mode_inscription='billetterie_connexion' WHERE mode_inscription='billetterie';
