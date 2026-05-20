# Corpo Omnes - Site Web

Plateforme PHP/MySQL de gestion de vie associative: actualites, evenements, billetterie, boutique, membres, partenariats et administration multi-structures.

## Fonctionnalites

- Front public: accueil, actualites, associations, sports, agenda d'evenements, pages legales.
- Espace membre: profil, inscriptions, billets, commandes, suivi personnel.
- Admin: gestion utilisateurs, structures, evenements, boutique, comptabilite, notes de frais, calendrier.
- Paiements: routage SumUp/Stripe selon montant, mode mock si cles absentes.
- Billetterie: generation QR + PDF, webhooks de confirmation paiement.
- Emails: PHPMailer (SMTP) ou mode log local en developpement.
- i18n: FR/EN.

## Stack technique

- PHP 8.0+ (usage de `match`, `str_contains`, etc.).
- MySQL / MariaDB.
- Apache recommande (fichier `.htaccess` fourni).
- Bibliotheques embarquees dans le repo:
  - PHPMailer (`includes/lib/PHPMailer`)
  - FPDF (`includes/lib/fpdf.php`)
  - QRCode (`includes/lib/qrcode.php`)

## Prerequis PHP

Extensions recommandees:

- `pdo_mysql`
- `curl`
- `gd` (QR PNG)
- `mbstring`
- `openssl`
- `json`
- `zip` (utile si Apple Wallet active)

## Installation locale

1. Cloner/copier le projet dans votre racine web (ex: XAMPP `htdocs/corpo-omnes-site`).
2. Creer une base MySQL, puis importer `database.sql`.
3. Copier `.env.example` vers `.env`, puis adapter vos valeurs.
4. Verifier la config DB:
   - soit via constantes dans `includes/db.php`
   - soit en adaptant votre environnement selon votre hebergement.
5. Ouvrir l'application: `http://localhost/corpo-omnes-site`.

## Configuration `.env`

Variables principales:

- `SITE_URL`
- `APP_TIMEZONE`
- `APP_SECRET`
- `SESSION_COOKIE_PATH`
- `SESSION_LIFETIME_DAYS`

Paiements:

- `SUMUP_*`
- `STRIPE_*`
- `PAYMENT_PROVIDER_THRESHOLD`
- `SUMUP_FEE_PERCENT`
- `STRIPE_FEE_PERCENT`
- `STRIPE_FEE_FIXED`

Emails:

- `MAIL_ENABLED`
- `MAIL_FROM`, `MAIL_FROM_NAME`
- `MAIL_SMTP_HOST`, `MAIL_SMTP_PORT`, `MAIL_SMTP_SECURE`
- `MAIL_SMTP_USER`, `MAIL_SMTP_PASS`
- `MAIL_DEBUG`

Important:

- Si `SUMUP_API_KEY` est vide, SumUp fonctionne en mock.
- Si `STRIPE_SECRET_KEY` est vide, Stripe fonctionne en mock.
- Si `MAIL_ENABLED != 1` ou `MAIL_SMTP_PASS` vide, les emails sont ecrits dans `logs/mail.log`.

## Comptes initiaux

Le dump `database.sql` cree:

- `superadmin` (`superadmin@corpo-omnes.fr`)
- `admincorpo` (`admin@corpo-omnes.fr`)

Le mot de passe est `SETUP_REQUIRED` tant que vous n'avez pas lance:

- `admin/setup-password.php`

Apres execution, supprimez immediatement ce fichier de setup.

## Migrations

Une page de migrations idempotentes est disponible:

- `admin/migrate.php` (acces super admin)

Elle ajoute automatiquement colonnes/tables manquantes sans reimport complet.

## Webhooks paiement

Endpoints:

- `api/sumup-webhook.php`
- `api/stripe-webhook.php`

Configurer ces URLs dans vos dashboards SumUp/Stripe pour finaliser automatiquement billets et transactions.

## Arborescence utile

- `index.php` : entree publique.
- `admin/` : back-office.
- `api/` : endpoints JSON (recherche, QR, exports, webhooks).
- `includes/` : coeur metier (auth, db, i18n, paiements, mails, PDF).
- `database.sql` : schema + donnees initiales.
- `css/`, `js/`, `images/` : assets front.

## Securite

- `.env` et `includes/.env` sont ignores par git.
- `.htaccess` bloque acces a `.env`, `.sql`, `.log`, etc.
- `images/justificatifs/.htaccess` protege les uploads sensibles.
- Penser a forcer HTTPS en production.

## Notes developpement

- Pas de `composer.json`/`package.json`: dependances deja committees.
- Dossier `logs/` cree automatiquement si necessaire.
- Dossiers `scripts/` et `tools/` presents pour outillage projet.

## Licence

A definir (ajouter un fichier `LICENSE` si necessaire).
