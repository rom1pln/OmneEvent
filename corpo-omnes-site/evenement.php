<?php
$page = 'evenements';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/i18n.php';
require_once 'includes/billetterie.php';
require_once 'includes/sumup.php';
require_once 'includes/paiements.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: evenements.php'); exit; }

// charge l'event depuis la BDD
$st = $pdo->prepare("SELECT * FROM evenements WHERE id=? AND statut='publie'");
$st->execute([$id]);
$ev = $st->fetch();
if (!$ev) {
    require_once 'includes/header.php';
    echo '<main class="container" style="padding:6rem 0;text-align:center"><h1>Événement introuvable</h1><p><a href="evenements.php" class="btn">Retour aux événements</a></p></main>';
    require_once 'includes/footer.php';
    exit;
}

$title       = $ev['titre'];
$userId      = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
$flash       = '';
$mode        = evt_normalize_mode($ev['mode_inscription'] ?? 'aucune');
$prix        = (float)($ev['prix'] ?? 0);
$places      = (int)($ev['places'] ?? 0);
$inscrits    = (int)($ev['inscrits'] ?? 0);
$placesState = billet_event_places_state($pdo, $id);
$dispoSlots  = $placesState['dispo'];
$complet     = $placesState['complet'];
$inscrits    = $placesState['actifs'];
$requireLogin = evt_mode_requires_login($mode);
$isPaid       = evt_mode_is_paid($mode);
$collectsContact = evt_mode_collects_contact($mode);

// si connecté, on récupère les infos de l'user pour vérifier l'éligibilité
$currentUser = null;
if ($userId) {
    $u = $pdo->prepare("SELECT id, email, nom, prenom, ecole, promotion FROM users WHERE id=?");
    $u->execute([$userId]);
    $currentUser = $u->fetch() ?: null;
}

// vérifie si l'user peut s'inscrire (école, externes…)
$eligibilite = evt_user_can_register($ev, $currentUser);
$fenetreInsc = evt_inscriptions_fenetre($ev);

// tarifs et codes promo pour les modes billetterie
$tarifsAll = $isPaid ? tarifs_pour_event($pdo, $id) : [];
$tarifsDispo = tarifs_disponibles($tarifsAll, $currentUser);

/* Code promo pré-rempli via URL */
$promoFromUrl = trim((string)($_GET['promo'] ?? ''));

// mes billets pour cet event
$mesBillets = [];
if ($userId) {
    $st = $pdo->prepare(
        "SELECT * FROM inscriptions_evenement
          WHERE evenement_id = ? AND user_id = ? AND statut IN ('confirme','liste_attente','en_attente')
          ORDER BY id DESC"
    );
    $st->execute([$id, $userId]);
    $mesBillets = $st->fetchAll();
}
// Billets invités affichés après paiement (récupérés via param tx)
$billetsInvite = [];

// crée 1 ou N billets gratuits
function evt_create_free_tickets(PDO $pdo, int $eventId, ?int $userId, array $contact, int $qte = 1): array {
    $ids = [];
    for ($i = 0; $i < max(1, $qte); $i++) {
        $bid = billet_create($pdo, $eventId, $userId, $contact, 0.0, 'aucun', null);
        if ($bid) $ids[] = $bid;
    }
    return $ids;
}

// gestion des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    // inscription gratuite par email
    if ($act === 'inscrire_email' && $mode === 'email') {
        $email   = trim($_POST['email']  ?? '');
        $nom     = trim($_POST['nom']    ?? '');
        $prenom  = trim($_POST['prenom'] ?? '');
        if (!$fenetreInsc['open']) {
            $flash = '<div class="flash flash--err">' . htmlspecialchars(evt_inscriptions_fenetre_message($fenetreInsc)) . '</div>';
        } elseif (!$eligibilite['ok']) {
            $flash = '<div class="flash flash--err">Cet événement est réservé aux écoles invitées - l\'inscription externe n\'est pas autorisée.</div>';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flash = '<div class="flash flash--err">Email invalide.</div>';
        } elseif ($nom === '' || $prenom === '') {
            $flash = '<div class="flash flash--err">Merci de renseigner ton nom et ton prénom.</div>';
        } else {
            // Bloque si déjà inscrit avec le même email
            $exists = $pdo->prepare("SELECT id FROM inscriptions_evenement WHERE evenement_id=? AND email=? AND statut IN ('confirme','liste_attente','en_attente')");
            $exists->execute([$id, $email]);
            if ($exists->fetchColumn()) {
                $flash = '<div class="flash flash--warn">Cet email est déjà inscrit·e à cet événement.</div>';
            } else {
                $ids = evt_create_free_tickets($pdo, $id, $userId ?: null,
                    ['email' => $email, 'nom' => $nom, 'prenom' => $prenom], 1);
                if (!empty($ids)) {
                    $ph = implode(',', array_map('intval', $ids));
                    $billetsInvite = $pdo->query("SELECT * FROM inscriptions_evenement WHERE id IN ($ph)")->fetchAll();
                    @billet_send_mail_for_ids($pdo, $ids);
                    $first = $billetsInvite[0] ?? [];
                    if (($first['statut'] ?? '') === 'liste_attente') {
                        $pos = billet_waitlist_position($pdo, (int)$first['id']);
                        $posTxt = $pos ? ' (position #' . $pos . ')' : '';
                        $flash = '<div class="flash flash--ok">Tu es en liste d\'attente' . htmlspecialchars($posTxt) . '. '
                               . 'Tu seras inscrit·e automatiquement si une place se libère. Un email de confirmation t\'a été envoyé (sans billet tant qu\'une place n\'est pas libérée).</div>';
                    } else {
                        $flash = '<div class="flash flash--ok">Inscription confirmée. Ton billet a été envoyé à <strong>' . htmlspecialchars($email) . '</strong> et reste disponible ci-dessous.</div>';
                    }
                } else {
                    $flash = '<div class="flash flash--err">' . htmlspecialchars(
                        evt_inscriptions_fenetre_message($fenetreInsc) ?: 'Impossible de finaliser l\'inscription.'
                    ) . '</div>';
                }
            }
        }
    }

    // inscription gratuite via connexion
    if ($act === 'inscrire_connexion' && $mode === 'connexion' && $userId) {
        if (!$fenetreInsc['open']) {
            $flash = '<div class="flash flash--err">' . htmlspecialchars(evt_inscriptions_fenetre_message($fenetreInsc)) . '</div>';
        } elseif (!$eligibilite['ok']) {
            $flash = '<div class="flash flash--err">Cet événement est réservé aux écoles invitées : ton école n\'est pas éligible.</div>';
        } else {
        $exists = $pdo->prepare("SELECT id FROM inscriptions_evenement WHERE user_id=? AND evenement_id=? AND statut IN ('confirme','liste_attente','en_attente')");
        $exists->execute([$userId, $id]);
        if ($exists->fetchColumn()) {
            $flash = '<div class="flash flash--warn">Tu es déjà inscrit·e à cet événement.</div>';
        } else {
            $u = $pdo->prepare("SELECT email, nom, prenom FROM users WHERE id=?");
            $u->execute([$userId]);
            $usr = $u->fetch() ?: [];
            $newId = billet_create($pdo, $id, $userId, [
                'email'  => $usr['email']  ?? '',
                'nom'    => $usr['nom']    ?? '',
                'prenom' => $usr['prenom'] ?? '',
            ], 0.0, 'aucun', null);
            if ($newId) {
                @billet_send_mail_for_ids($pdo, [$newId]);
                $stIns = $pdo->prepare('SELECT statut FROM inscriptions_evenement WHERE id = ?');
                $stIns->execute([$newId]);
                if ((string)$stIns->fetchColumn() === 'liste_attente') {
                    $_SESSION['evt_flash_wait'] = billet_waitlist_position($pdo, $newId);
                }
                header('Location: evenement.php?id=' . $id . '#mes-billets'); exit;
            }
            $flash = '<div class="flash flash--err">' . htmlspecialchars(
                evt_inscriptions_fenetre_message($fenetreInsc) ?: 'Impossible de finaliser l\'inscription.'
            ) . '</div>';
        }
        }  /* fin else $eligibilite['ok'] */
    }

    // achat - modes billetterie
    if ($act === 'acheter' && $isPaid) {
        // Mode "billetterie_connexion" exige une session
        if ($mode === 'billetterie_connexion' && !$userId) {
            $flash = '<div class="flash flash--err">Tu dois être connecté·e pour acheter ce billet.</div>';
        } elseif (!$fenetreInsc['open']) {
            $flash = '<div class="flash flash--err">' . htmlspecialchars(evt_inscriptions_fenetre_message($fenetreInsc)) . '</div>';
        } elseif (!$eligibilite['ok']) {
            $flash = '<div class="flash flash--err">Cet événement est réservé aux écoles invitées : achat non autorisé.</div>';
        } else {
            // Données contact : si connecté → session ; sinon → form
            if ($userId) {
                $u = $pdo->prepare("SELECT email, nom, prenom FROM users WHERE id=?");
                $u->execute([$userId]);
                $usr = $u->fetch() ?: [];
                $email  = $usr['email']  ?? '';
                $nom    = $usr['nom']    ?? '';
                $prenom = $usr['prenom'] ?? '';
            } else {
                $email  = trim($_POST['email']  ?? '');
                $nom    = trim($_POST['nom']    ?? '');
                $prenom = trim($_POST['prenom'] ?? '');
            }
            $qte = max(1, min((int)($ev['max_billets_par_personne'] ?: 1), (int)($_POST['quantite'] ?? 1)));

            // tarif sélectionné
            $tarifChoisi = null;
            $prixUnitaire = $prix;
            if (!empty($tarifsDispo)) {
                $tarifId = (int)($_POST['tarif_id'] ?? 0);
                foreach ($tarifsDispo as $t) {
                    if ((int)$t['id'] === $tarifId) { $tarifChoisi = $t; break; }
                }
                if (!$tarifChoisi) {
                    $flash = '<div class="flash flash--err">Merci de choisir un tarif.</div>';
                } else {
                    $prixUnitaire = (float)$tarifChoisi['prix'];
                }
            }

            // code promo
            $codePromoEntered = strtoupper(trim($_POST['code_promo'] ?? ''));
            $codeAppliqued = null;
            $reductionTotale = 0.0;
            if ($codePromoEntered && empty($flash)) {
                $codeAppliqued = code_promo_lookup($pdo, $codePromoEntered, $id, $tarifChoisi ? (int)$tarifChoisi['id'] : null);
                if (!$codeAppliqued) {
                    $flash = '<div class="flash flash--warn">Code promo invalide ou expiré.</div>';
                } else {
                    $r = code_promo_apply($codeAppliqued, $prixUnitaire);
                    $prixUnitaire = $r['prix_unitaire'];
                    $reductionTotale = $r['reduction'] * $qte;
                }
            }

            if (!empty($flash)) {
                // erreur déjà signalée
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $flash = '<div class="flash flash--err">Email invalide.</div>';
            } elseif ($mode === 'billetterie_email' && ($nom === '' || $prenom === '')) {
                $flash = '<div class="flash flash--err">Merci de renseigner ton nom et ton prénom.</div>';
            } elseif ($complet && !billet_find_unpaid_confirmed($pdo, $id, $userId ?: null, $email)) {
                $flash = '<div class="flash flash--warn">Événement complet. Rejoins la <a href="#inscription">liste d\'attente</a> ci-dessous.</div>';
            } else {
                $existingInsId = billet_find_unpaid_confirmed($pdo, $id, $userId ?: null, $email);
                if ($existingInsId) {
                    $qte = 1;
                }
                $total = $prixUnitaire * $qte;

                // frais de paiement si à charge client
                //    facturé au client. Sinon, frais déduits du net côté association.
                $fraisAuClient = $tarifChoisi ? ((int)($tarifChoisi['frais_a_charge_client'] ?? 0) === 1) : false;
                $totalAvecFrais = $total;
                $feeInfo = paiement_calcule_frais($total);
                if ($fraisAuClient && $total > 0) {
                    $totalAvecFrais = $feeInfo['client_total'];
                    // Recalcule les frais avec ce nouveau total (le provider peut changer si on franchit le seuil)
                    $feeInfo = paiement_calcule_frais($totalAvecFrais);
                }
                $providerChoisi = $feeInfo['provider']; // 'sumup' | 'stripe'

                // Transaction "init"
                $pdo->prepare(
                    "INSERT INTO paiement_transactions
                       (evenement_id, provider, montant, devise, statut, email, user_id, payload)
                     VALUES (?,?,?,?,?,?,?,?)"
                )->execute([
                    $id,
                    paiement_is_mock($providerChoisi) ? ($providerChoisi === 'stripe' ? 'mock_stripe' : 'mock') : $providerChoisi,
                    $totalAvecFrais, 'EUR', 'init', $email, $userId ?: null,
                    json_encode(array_filter([
                        'nom'=>$nom, 'prenom'=>$prenom, 'quantite'=>$qte,
                        'tarif_id'=>$tarifChoisi ? (int)$tarifChoisi['id'] : null,
                        'code_promo'=>$codeAppliqued ? $codeAppliqued['code'] : null,
                        'reduction_total'=>$reductionTotale,
                        'prix_unitaire_billet'=>round($prixUnitaire, 2),
                        'total_billets'=>round($total, 2),
                        'frais'=>$feeInfo['frais'],
                        'frais_a_charge_client'=>$fraisAuClient,
                        'provider'=>$providerChoisi,
                        'inscription_id'=>$existingInsId ?? null,
                    ], fn($v) => $v !== null), JSON_UNESCAPED_UNICODE),
                ]);
                $txId = (int)$pdo->lastInsertId();
                $reference = 'evt' . $id . '-tx' . $txId;

                // URL de retour SumUp
                // On préfère SITE_URL (.env) pour avoir une URL fiable et HTTPS, sinon
                // on reconstruit en tenant compte des proxys (Cloudflare, 42web.io…).
                $siteUrl = trim((string)corpo_env('SITE_URL', ''));
                if ($siteUrl !== '') {
                    $returnUrl = rtrim($siteUrl, '/') . '/evenement.php?id=' . $id . '&tx=' . $txId;
                } else {
                    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
                            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
                            || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
                            || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
                    $scheme = $isHttps ? 'https' : 'http';
                    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $self   = $_SERVER['SCRIPT_NAME'] ?? '/evenement.php';
                    $returnUrl = $scheme . '://' . $host . $self . '?id=' . $id . '&tx=' . $txId;
                }

                try {
                    $checkout = paiement_create_checkout(
                        $totalAvecFrais, $reference, $email,
                        'Billet : ' . $ev['titre'], $returnUrl,
                        $providerChoisi
                    );
                    $pdo->prepare("UPDATE paiement_transactions SET provider=?, provider_ref=?, statut='en_attente' WHERE id=?")
                        ->execute([$checkout['provider'], $checkout['checkout_id'], $txId]);
                    header('Location: ' . $checkout['redirect_url']); exit;
                } catch (Throwable $e) {
                    $pdo->prepare("UPDATE paiement_transactions SET statut='echec' WHERE id=?")->execute([$txId]);
                    $flash = '<div class="flash flash--err">Échec création paiement : ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }

    // file d'attente (event complet)
    if ($act === 'rejoindre_file_attente' && in_array($mode, ['email', 'connexion', 'billetterie_email', 'billetterie_connexion'], true)) {
        if ($mode === 'billetterie_connexion' && !$userId) {
            $flash = '<div class="flash flash--err">Tu dois être connecté·e pour rejoindre la liste d\'attente.</div>';
        } elseif (!$fenetreInsc['open']) {
            $flash = '<div class="flash flash--err">' . htmlspecialchars(evt_inscriptions_fenetre_message($fenetreInsc)) . '</div>';
        } elseif (!$eligibilite['ok']) {
            $flash = '<div class="flash flash--err">Inscription non autorisée pour ton profil.</div>';
        } elseif (!$complet) {
            $flash = '<div class="flash flash--warn">Des places sont encore disponibles — inscris-toi normalement.</div>';
        } else {
            if ($userId) {
                $u = $pdo->prepare('SELECT email, nom, prenom FROM users WHERE id=?');
                $u->execute([$userId]);
                $usr = $u->fetch() ?: [];
                $email  = $usr['email']  ?? '';
                $nom    = $usr['nom']    ?? '';
                $prenom = $usr['prenom'] ?? '';
            } else {
                $email  = trim($_POST['email']  ?? '');
                $nom    = trim($_POST['nom']    ?? '');
                $prenom = trim($_POST['prenom'] ?? '');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $flash = '<div class="flash flash--err">Email invalide.</div>';
            } elseif ($mode === 'billetterie_email' && ($nom === '' || $prenom === '')) {
                $flash = '<div class="flash flash--err">Merci de renseigner ton nom et ton prénom.</div>';
            } else {
                $existsSql = $userId
                    ? 'SELECT id FROM inscriptions_evenement WHERE evenement_id=? AND user_id=? AND statut IN (\'confirme\',\'liste_attente\',\'en_attente\')'
                    : 'SELECT id FROM inscriptions_evenement WHERE evenement_id=? AND email=? AND statut IN (\'confirme\',\'liste_attente\',\'en_attente\')';
                $exists = $pdo->prepare($existsSql);
                $exists->execute($userId ? [$id, $userId] : [$id, $email]);
                if ($exists->fetchColumn()) {
                    $flash = '<div class="flash flash--warn">Tu es déjà inscrit·e ou déjà en liste d\'attente pour cet événement.</div>';
                } else {
                    $newId = billet_create($pdo, $id, $userId ?: null, [
                        'email' => $email, 'nom' => $nom, 'prenom' => $prenom,
                    ], 0.0, 'aucun', null);
                    if ($newId) {
                        @billet_send_mail_for_ids($pdo, [$newId]);
                        $pos = billet_waitlist_position($pdo, $newId);
                        $posTxt = $pos ? ' (position #' . (int)$pos . ')' : '';
                        $flash = '<div class="flash flash--ok">Tu es en liste d\'attente' . htmlspecialchars($posTxt) . '. '
                               . 'Tu seras inscrit·e automatiquement dès qu\'une place se libère.</div>';
                        if ($userId) {
                            $st = $pdo->prepare(
                                "SELECT * FROM inscriptions_evenement
                                  WHERE evenement_id=? AND user_id=?
                                    AND statut IN ('confirme','liste_attente','en_attente')
                                  ORDER BY id DESC"
                            );
                            $st->execute([$id, $userId]);
                            $mesBillets = $st->fetchAll();
                        } else {
                            $stInv = $pdo->prepare('SELECT * FROM inscriptions_evenement WHERE id = ?');
                            $stInv->execute([$newId]);
                            $rowInv = $stInv->fetch();
                            if ($rowInv) {
                                $billetsInvite = [$rowInv];
                            }
                        }
                        $placesState = billet_event_places_state($pdo, $id);
                        $dispoSlots  = $placesState['dispo'];
                        $complet     = $placesState['complet'];
                    } else {
                        $flash = '<div class="flash flash--err">Impossible de rejoindre la liste d\'attente.</div>';
                    }
                }
            }
        }
    }

    // annulation d'un billet
    if ($act === 'annuler_billet' && $userId) {
        $insId = (int)($_POST['inscription_id'] ?? 0);
        $check = $pdo->prepare("SELECT id FROM inscriptions_evenement WHERE id=? AND user_id=?");
        $check->execute([$insId, $userId]);
        if ($check->fetchColumn()) {
            billet_cancel($pdo, $insId);
            $flash = '<div class="flash flash--ok">Inscription annulée.</div>';
            $st = $pdo->prepare("SELECT * FROM inscriptions_evenement WHERE evenement_id=? AND user_id=? AND statut IN ('confirme','liste_attente','en_attente') ORDER BY id DESC");
            $st->execute([$id, $userId]);
            $mesBillets = $st->fetchAll();
        }
    }
}

// callback paiement (SumUp ou Stripe selon le montant)
if (!empty($_GET['tx'])) {
    $txId = (int)$_GET['tx'];
    $tx = $pdo->prepare("SELECT * FROM paiement_transactions WHERE id=? AND evenement_id=?");
    $tx->execute([$txId, $id]);
    $transaction = $tx->fetch();
    if ($transaction && $transaction['statut'] === 'en_attente') {
        $txProvider = (string)($transaction['provider'] ?? 'sumup');
        $txRef      = (string)($transaction['provider_ref'] ?? '');
        $status     = paiement_get_status($txProvider, $txRef);
        if (!empty($_GET['mock']) && paiement_is_mock($txProvider)) $status = 'paid';

        // SumUp/Stripe peuvent être encore en traitement quand le client revient.
        // On retente une seconde fois avant d'afficher un message d'attente.
        if ($status === 'pending' || $status === 'unknown') {
            usleep(800000); // 0.8 s
            $status = paiement_get_status($txProvider, $txRef);
        }

        if ($status === 'paid') {
            $pdo->prepare("UPDATE paiement_transactions SET statut='paye' WHERE id=?")->execute([$txId]);
            $payload = json_decode($transaction['payload'] ?? '{}', true) ?: [];
            $codePromo = $payload['code_promo'] ?? null;
            $createdIds = billet_fulfill_from_transaction($pdo, $transaction, $payload);
            // Incrémente le compteur du code promo
            if ($codePromo && !empty($createdIds)) {
                try {
                    $c = $pdo->prepare("SELECT id FROM codes_promo WHERE code=? AND (evenement_id=? OR evenement_id IS NULL) LIMIT 1");
                    $c->execute([$codePromo, $id]);
                    $cid = (int)$c->fetchColumn();
                    if ($cid) code_promo_consume($pdo, $cid, count($createdIds));
                } catch (Throwable $e) {}
            }
            if (!empty($createdIds)) {
                $pdo->prepare("UPDATE paiement_transactions SET inscription_id=? WHERE id=?")
                    ->execute([$createdIds[0], $txId]);
                @billet_send_mail_for_ids($pdo, $createdIds, $txId);
            }
            $flash = '<div class="flash flash--ok">Paiement confirmé. Tes billets sont prêts ci-dessous (un mail t\'a aussi été envoyé).</div>';
            if ($userId) {
                $st = $pdo->prepare("SELECT * FROM inscriptions_evenement WHERE evenement_id=? AND user_id=? AND statut IN ('confirme','liste_attente','en_attente') ORDER BY id DESC");
                $st->execute([$id, $userId]);
                $mesBillets = $st->fetchAll();
            } elseif (!empty($createdIds)) {
                $ph = implode(',', array_map('intval', $createdIds));
                $billetsInvite = $pdo->query("SELECT * FROM inscriptions_evenement WHERE id IN ($ph)")->fetchAll();
            }
        } elseif ($status === 'failed') {
            $pdo->prepare("UPDATE paiement_transactions SET statut='echec' WHERE id=?")->execute([$txId]);
            $flash = '<div class="flash flash--err">Le paiement n\'a pas abouti.</div>';
        } else {
            // Encore en cours ou statut inconnu côté SumUp
            // → on affiche un message + on relance la page dans quelques secondes.
            $flash = '<div class="flash flash--warn">Paiement en cours de validation… La page va se rafraîchir automatiquement.</div>'
                   . '<meta http-equiv="refresh" content="4">';
        }
    } elseif ($transaction && $transaction['statut'] === 'paye') {
        // L'utilisateur revient sur la page après un paiement déjà confirmé
        // (probablement via le webhook ou un précédent callback). On lui montre
        // ses billets et un message de confirmation.
        $flash = '<div class="flash flash--ok">Paiement confirmé ✓ Tes billets sont disponibles ci-dessous.</div>';
        if ($userId) {
            $st = $pdo->prepare("SELECT * FROM inscriptions_evenement WHERE evenement_id=? AND user_id=? AND statut IN ('confirme','liste_attente','en_attente') ORDER BY id DESC");
            $st->execute([$id, $userId]);
            $mesBillets = $st->fetchAll();
        } elseif (!empty($transaction['inscription_id'])) {
            $st = $pdo->prepare("SELECT * FROM inscriptions_evenement WHERE id=? OR evenement_id=? AND email=? ORDER BY id DESC");
            $st->execute([(int)$transaction['inscription_id'], $id, (string)$transaction['email']]);
            $billetsInvite = $st->fetchAll();
        }
    }
}

// détecte une transaction en_attente sans billet créé
//    sans paramètre ?tx=… dans l'URL (l'utilisateur est revenu directement,
//    typiquement après la page blanche de SumUp). On lui propose de vérifier.
if (empty($_GET['tx']) && $userId) {
    try {
        $orphan = $pdo->prepare(
            "SELECT id FROM paiement_transactions
              WHERE evenement_id = ? AND user_id = ? AND statut = 'en_attente'
              ORDER BY id DESC LIMIT 1"
        );
        $orphan->execute([$id, $userId]);
        $orphanTxId = (int)$orphan->fetchColumn();
        if ($orphanTxId) {
            $flash = '<div class="flash flash--warn">Tu as un paiement en cours pour cet événement. '
                   . '<a href="evenement.php?id=' . $id . '&tx=' . $orphanTxId . '" style="color:inherit;text-decoration:underline">Vérifier maintenant</a>.</div>';
        }
    } catch (Throwable $e) { /* ignore */ }
}

if (!empty($_SESSION['evt_flash_wait'])) {
    $pos = (int)$_SESSION['evt_flash_wait'];
    unset($_SESSION['evt_flash_wait']);
    $flash = '<div class="flash flash--ok">Tu es en liste d\'attente (position #' . $pos . '). '
           . 'Tu seras inscrit·e automatiquement si une place se libère.</div>';
}

$onWaitlist = false;
$needsPayAfterPromo = false;
foreach ($mesBillets as $b) {
    if (($b['statut'] ?? '') === 'liste_attente') {
        $onWaitlist = true;
    }
    if (($b['statut'] ?? '') === 'confirme' && $isPaid && $prix > 0
        && ($b['paiement_statut'] ?? 'aucun') !== 'paye' && (float)($b['prix_paye'] ?? 0) <= 0) {
        $needsPayAfterPromo = true;
    }
}

require_once 'includes/header.php';

$ecolesInv  = json_decode($ev['ecoles_invitees'] ?? '[]', true) ?: [];
$dateFmt = !empty($ev['date']) ? corpo_format_date_long((string)$ev['date'], true) : '-';
$dateFinFmt = !empty($ev['date_fin']) ? corpo_format_date_long((string)$ev['date_fin'], true) : null;

// carte liste d'attente (pas de QR)
function render_waitlist_card(array $b, array $ev, ?int $position = null): string
{
    $name = trim(($b['prenom'] ?? '') . ' ' . ($b['nom'] ?? '')) ?: ($b['email'] ?? '');
    $dateFmt = !empty($ev['date']) ? corpo_format_date_long((string)$ev['date'], false) : '';
    $html = '<div class="ticket ticket--waitlist" id="waitlist-' . (int)$b['id'] . '">';
    $html .= '<div class="ticket__body" style="width:100%">';
    $html .= '<span class="ticket__statut ticket__statut--liste_attente">' . htmlspecialchars(corpo_t('evt.waitlist_badge')) . '</span>';
    if ($position) {
        $html .= '<p style="margin:.5rem 0 0;font-weight:700">#' . (int)$position . '</p>';
    }
    $html .= '<strong style="display:block;margin-top:var(--s2)">' . htmlspecialchars($ev['titre']) . '</strong>';
    if ($dateFmt) {
        $html .= '<p>' . htmlspecialchars($dateFmt) . '</p>';
    }
    if ($name) {
        $html .= '<p style="color:var(--text-muted);font-size:.85rem">' . htmlspecialchars($name) . '</p>';
    }
    $html .= '<p class="evt-waitlist-hint" style="margin-top:var(--s3)">' . htmlspecialchars(corpo_t('evt.waitlist_auto')) . '</p>';
    $html .= '</div></div>';
    return $html;
}

// affiche un billet (carte) avec son QR
function render_ticket(array $b, array $ev): string {
    if (($b['statut'] ?? '') === 'liste_attente') {
        return '';
    }
    $stat = $b['statut'];
    $qrUrl = !empty($b['qr_token']) ? billet_qr_image_url($b['qr_token'], 220) : null;
    $qrBig = !empty($b['qr_token']) ? billet_qr_image_url($b['qr_token'], 600) : null;
    $statLabel = [
        'confirme'      => '✓ Confirmé',
        'liste_attente' => '⏳ Liste d\'attente',
        'en_attente'    => '⏳ En attente',
    ][$stat] ?? $stat;
    $dateFmt = !empty($ev['date']) ? corpo_format_date_long((string)$ev['date'], false) : '';
    $name = trim(($b['prenom'] ?? '') . ' ' . ($b['nom'] ?? '')) ?: ($b['email'] ?? '');
    $bid  = (int)$b['id'];
    $codeShort = !empty($b['qr_token']) ? strtoupper(substr($b['qr_token'], 0, 8)) : '';
    $lieu = $ev['lieu'] ?? '';
    $heure = $ev['heure'] ?? '';

    $html  = '<div class="ticket" id="ticket-' . $bid . '" data-ticket-id="' . $bid . '" data-qr-token="' . htmlspecialchars($b['qr_token'] ?? '') . '">';

    // header visible seulement à l'impression
    $html .= '<div class="ticket__print-header">';
    $html .= '<div class="ticket__print-brand">';
    $html .= '<span class="ticket__print-brand-name">CORPO OMNES</span>';
    $html .= '<span class="ticket__print-brand-sub">LYON · BILLET ÉLECTRONIQUE</span>';
    $html .= '</div>';
    $html .= '<div class="ticket__print-id">N° ' . $bid . '</div>';
    $html .= '</div>';

    $html .= '<div class="ticket__left">';
    if ($qrUrl && $qrBig) {
        $jsArgs = "'" . addslashes($qrBig) . "','" . addslashes($ev['titre']) . "','" . addslashes($name) . "'";
        $html .= '<button type="button" class="ticket__qr-btn"'
              .  ' onclick="openQrModal(' . $jsArgs . ');return false;"'
              .  ' data-qr-big="' . htmlspecialchars($qrBig) . '"'
              .  ' data-qr-name="' . htmlspecialchars($name) . '"'
              .  ' data-qr-event="' . htmlspecialchars($ev['titre']) . '"'
              .  ' aria-label="Agrandir le QR code">'
              .  '<img src="' . htmlspecialchars($qrUrl) . '" alt="QR Code" class="ticket__qr" loading="lazy">'
              .  '<span class="ticket__qr-hint">🔍 Agrandir</span>'
              .  '</button>';
    }
    $html .= '<span class="ticket__statut ticket__statut--' . htmlspecialchars($stat) . '">' . $statLabel . '</span>';
    $html .= '</div>';
    $html .= '<div class="ticket__body">';
    $html .= '<strong>' . htmlspecialchars($ev['titre']) . '</strong>';
    $html .= '<p>' . ucfirst($dateFmt) . (!empty($heure) ? ' • ' . htmlspecialchars($heure) : '') . '</p>';
    if (!empty($lieu))      $html .= '<p>📍 ' . htmlspecialchars($lieu) . '</p>';
    if ($name) {
        $html .= '<p style="color:var(--text-muted);font-size:.78rem">' . htmlspecialchars($name) . '</p>';
    }
    if (!empty($b['prix_paye']) && (float)$b['prix_paye'] > 0) {
        $html .= '<p class="ticket__price">' . number_format($b['prix_paye'], 2, ',', ' ') . ' € payés</p>';
    }
    if (!empty($b['qr_token'])) {
        $html .= '<small style="color:var(--text-muted);font-size:.7rem;display:block;margin-top:6px">Code : ' . htmlspecialchars($codeShort) . '…</small>';
    }

    if (!empty($b['qr_token']) && $stat !== 'liste_attente' && $stat !== 'en_attente') {
        $html .= '<div class="ticket__actions">';
        $html .= '<button type="button" class="ticket__action-btn" onclick="printTicket(' . $bid . ');return false;">🖨 Imprimer / PDF</button>';

        $appleCertOk = function_exists('openssl_pkcs12_read')
                       && getenv('APPLE_WALLET_CERT_PATH')
                       && @file_exists(getenv('APPLE_WALLET_CERT_PATH'))
                       && getenv('APPLE_WALLET_WWDR_PATH')
                       && @file_exists(getenv('APPLE_WALLET_WWDR_PATH'));
        if ($appleCertOk) {
            $pkpassUrl = 'api/event-pkpass.php?ins=' . $bid . '&t=' . urlencode($b['qr_token']);
            $html .= '<a href="' . htmlspecialchars($pkpassUrl) . '" class="ticket__action-btn ticket__action-btn--apple" download>'
                  .  '<span aria-hidden="true">🍎</span>&nbsp;Apple Wallet</a>';
        } else {
            $html .= '<button type="button" class="ticket__action-btn ticket__action-btn--apple is-disabled" onclick="walletInfo(\'apple\');return false;" aria-label="Apple Wallet (non configuré)">'
                  .  '<span aria-hidden="true">🍎</span>&nbsp;Apple Wallet</button>';
        }

        // Google Wallet : n'activer le lien que si la clé + flag explicite (l'API n'est pas encore branchée par défaut).
        $googleOk = (getenv('GOOGLE_WALLET_API_READY') === '1')
                    && (bool)getenv('GOOGLE_WALLET_ISSUER_ID')
                    && (bool)getenv('GOOGLE_WALLET_SERVICE_KEY_PATH')
                    && @file_exists((string)getenv('GOOGLE_WALLET_SERVICE_KEY_PATH'));
        if ($googleOk) {
            $gwUrl = 'api/event-gpay.php?ins=' . $bid . '&t=' . urlencode($b['qr_token']);
            $html .= '<a href="' . htmlspecialchars($gwUrl) . '" target="_blank" rel="noopener" class="ticket__action-btn ticket__action-btn--google">'
                  .  '<span aria-hidden="true">🅖</span>&nbsp;Google Wallet</a>';
        } else {
            $html .= '<button type="button" class="ticket__action-btn ticket__action-btn--google is-disabled" onclick="walletInfo(\'google\');return false;" aria-label="Google Wallet (non configuré)">'
                  .  '<span aria-hidden="true">🅖</span>&nbsp;Google Wallet</button>';
        }
        $html .= '</div>';
    }

    $html .= '</div>'; /* fin .ticket__body */

    // infos visibles à l'impression (hors .ticket__body)
    $html .= '<dl class="ticket__print-info">';
    $html .= '<div><dt>Titulaire</dt><dd>' . htmlspecialchars($name ?: '-') . '</dd></div>';
    $html .= '<div><dt>Événement</dt><dd>' . htmlspecialchars($ev['titre']) . '</dd></div>';
    $html .= '<div><dt>Date</dt><dd>' . htmlspecialchars(ucfirst($dateFmt)) . (!empty($heure) ? ' à ' . htmlspecialchars($heure) : '') . '</dd></div>';
    if ($lieu) $html .= '<div><dt>Lieu</dt><dd>' . htmlspecialchars($lieu) . '</dd></div>';
    if (!empty($ev['organisateur'])) $html .= '<div><dt>Organisateur</dt><dd>' . htmlspecialchars($ev['organisateur']) . '</dd></div>';
    if (!empty($b['prix_paye']) && (float)$b['prix_paye'] > 0) {
        $html .= '<div><dt>Tarif réglé</dt><dd>' . number_format($b['prix_paye'], 2, ',', ' ') . ' €</dd></div>';
    }
    $html .= '<div><dt>Référence</dt><dd>' . htmlspecialchars($codeShort) . '</dd></div>';
    $html .= '</dl>';

    $html .= '<div class="ticket__print-footer">';
    $html .= '<p class="ticket__print-instructions">Présente ce billet (ou son QR code) à l\'entrée. Il est nominatif et ne peut être transmis sans accord de l\'organisateur.</p>';
    $html .= '<p class="ticket__print-meta">Imprimé le ' . date('d/m/Y à H:i') . ' · corpo-omnes.fr</p>';
    $html .= '</div>';

    $html .= '</div>'; /* fin .ticket */
    return $html;
}
?>

<?php
  // URLs "ajouter à l'agenda" (Google / Apple / Outlook / ICS) - try/catch si date mal formée
  $addcalAvailable = false;
  $gcalUrl = $outlookUrl = $icsUrl = '#';
  try {
      $tz       = new DateTimeZone('Europe/Paris');
      $heureD   = !empty($ev['heure']) ? $ev['heure'] : '00:00';
      $dateD    = !empty($ev['date'])  ? $ev['date']  : date('Y-m-d');
      $dateF    = !empty($ev['date_fin']) ? $ev['date_fin'] : $dateD;
      $heureF   = !empty($ev['heure_fin'])
                  ? $ev['heure_fin']
                  : (!empty($ev['heure']) ? date('H:i', strtotime($ev['heure'] . ' +2 hours')) : '23:59');
      $dtStart  = new DateTime($dateD . ' ' . $heureD, $tz);
      $dtEnd    = new DateTime($dateF . ' ' . $heureF, $tz);
      $utcStart = (clone $dtStart)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
      $utcEnd   = (clone $dtEnd)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
      $details  = trim(($ev['description'] ?? '') . "\n\nOrganisé par : " . ($ev['organisateur'] ?? ''));
      $location = ($ev['lieu'] ?? '') . (!empty($ev['campus']) ? ' - ' . $ev['campus'] : '');
      $gcalUrl  = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
                . '&text='     . rawurlencode($ev['titre'] ?? '')
                . '&dates='    . $utcStart . '/' . $utcEnd
                . '&details='  . rawurlencode($details)
                . '&location=' . rawurlencode($location);
      $outlookUrl = 'https://outlook.live.com/calendar/0/deeplink/compose?'
                  . 'subject='   . rawurlencode($ev['titre'] ?? '')
                  . '&body='     . rawurlencode($details)
                  . '&location=' . rawurlencode($location)
                  . '&startdt='  . rawurlencode($dtStart->format('c'))
                  . '&enddt='    . rawurlencode($dtEnd->format('c'))
                  . '&path=/calendar/action/compose&rru=addevent';
      $icsUrl = 'api/event-ics.php?id=' . $id;
      $addcalAvailable = true;
  } catch (Throwable $e) {
      $addcalAvailable = false;
  }
?>
<?php
  // Libellés multilingues avec fallback explicite (corpo_t renvoie la clé si manquante)
  $isEN = (corpo_current_lang() === 'en');
  $lblBack    = $isEN ? '← Back to events' : '← Retour aux événements';
  $lblAddCal  = $isEN ? 'Add to my calendar' : 'Ajouter à mon agenda';
  $lblAddSub  = $isEN ? 'Get a reminder before the event' : 'Reçois un rappel avant l\'événement';
?>
<main class="evt-detail">
  <div class="container">
    <a href="evenements.php" class="evt-back"><?= htmlspecialchars($lblBack) ?></a>

    <?= $flash ?>

    <?php $evtBanniereUrl = evt_media_url($ev['banniere'] ?? null, $base ?? ''); ?>

    <?php if ($evtBanniereUrl): ?>
    <header class="evt-detail-cover">
      <div class="evt-detail-cover__media">
        <img src="<?= htmlspecialchars($evtBanniereUrl) ?>" alt="" loading="eager" decoding="async">
      </div>
      <div class="evt-detail-cover__shade" aria-hidden="true"></div>
      <div class="evt-detail-cover__content">
        <div class="evt-detail-cover__meta">
          <?= evt_icon_html($ev['icon'] ?? null, 'evt-emoji evt-emoji--lg') ?>
          <?php if (!empty($ev['type'])): ?>
            <span class="evt-detail-cover__type"><?= htmlspecialchars($ev['type']) ?></span>
          <?php endif; ?>
        </div>
        <h1 class="evt-detail-cover__title"><?= htmlspecialchars($ev['titre']) ?></h1>
        <p class="evt-detail-cover__orga">par <strong><?= htmlspecialchars($ev['organisateur']) ?></strong></p>
      </div>
    </header>
    <?php endif; ?>

    <?php if ($addcalAvailable): ?>
    <!-- bandeau "ajouter à l'agenda" -->
    <section class="evt-addcal-banner" aria-label="<?= htmlspecialchars($lblAddCal) ?>">
      <div class="evt-addcal-banner__head">
        <span class="evt-addcal-banner__icon" aria-hidden="true">📅</span>
        <div>
          <strong class="evt-addcal-banner__title"><?= htmlspecialchars($lblAddCal) ?></strong>
          <span class="evt-addcal-banner__sub"><?= htmlspecialchars($lblAddSub) ?></span>
        </div>
      </div>
      <div class="evt-addcal-banner__btns">
        <a href="<?= htmlspecialchars($gcalUrl) ?>" target="_blank" rel="noopener" class="evt-addcal__btn">
          <span aria-hidden="true">🅖</span> Google
        </a>
        <a href="<?= htmlspecialchars($icsUrl) ?>" class="evt-addcal__btn" download="evenement-<?= (int)$id ?>.ics">
          <span aria-hidden="true">⤓</span> <?= htmlspecialchars(corpo_t('mes_evt.btn_ics')) ?>
        </a>
        <a href="<?= htmlspecialchars($outlookUrl) ?>" target="_blank" rel="noopener" class="evt-addcal__btn">
          <span aria-hidden="true">📧</span> Outlook
        </a>
      </div>
    </section>
    <?php endif; ?>

    <div class="evt-detail-grid">
      <!-- Colonne gauche : informations -->
      <article class="evt-detail-main">
        <?php if (!$evtBanniereUrl): ?>
        <div class="evt-detail-hero">
          <span class="evt-detail-icon"><?= evt_icon_html($ev['icon'] ?? null) ?></span>
          <?php if (!empty($ev['type'])): ?>
            <span class="evt-detail-type"><?= htmlspecialchars($ev['type']) ?></span>
          <?php endif; ?>
        </div>
        <h1 class="evt-detail-title"><?= htmlspecialchars($ev['titre']) ?></h1>
        <p class="evt-detail-orga">par <strong><?= htmlspecialchars($ev['organisateur']) ?></strong></p>
        <?php endif; ?>

        <dl class="evt-detail-meta">
          <div><dt>📅 Date</dt><dd><?= ucfirst($dateFmt) ?><?= $dateFinFmt ? ' → ' . ucfirst($dateFinFmt) : '' ?></dd></div>
          <?php if (!empty($ev['heure'])): ?>
          <div><dt>🕒 Horaire</dt><dd><?= htmlspecialchars($ev['heure']) ?><?= !empty($ev['heure_fin']) ? ' → ' . htmlspecialchars($ev['heure_fin']) : '' ?></dd></div>
          <?php endif; ?>
          <?php if (!empty($ev['lieu'])): ?>
          <div><dt>📍 Lieu</dt><dd><?= htmlspecialchars($ev['lieu']) ?></dd></div>
          <?php endif; ?>
          <?php if (!empty($ecolesInv) && $ecolesInv !== ['Tous']): ?>
          <div><dt>🎓 Écoles invitées</dt><dd><?= htmlspecialchars(implode(', ', $ecolesInv)) ?></dd></div>
          <?php endif; ?>
        </dl>

        <?php if (!empty($ev['description'])): ?>
          <h2 class="evt-detail-section">À propos</h2>
          <p class="evt-detail-desc"><?= nl2br(htmlspecialchars($ev['description'])) ?></p>
        <?php endif; ?>

        <!-- Carte interactive -->
        <?php if (!empty($ev['lieu'])): ?>
          <h2 class="evt-detail-section">Comment s'y rendre</h2>
          <div id="evt-map" class="evt-map" data-lieu="<?= htmlspecialchars($ev['lieu']) ?>" aria-label="Carte de l'événement">
            <div class="evt-map__placeholder">
              <span>🗺️</span>
              <p>Chargement de la carte…</p>
            </div>
          </div>
          <p class="evt-map__links">
            <a target="_blank" rel="noopener" id="evt-map-osm" href="https://www.openstreetmap.org/search?query=<?= urlencode($ev['lieu']) ?>">Ouvrir dans OpenStreetMap ↗</a>
            <a target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($ev['lieu']) ?>">Google Maps ↗</a>
          </p>
        <?php endif; ?>
      </article>

      <!-- Colonne droite : inscription / billetterie -->
      <aside class="evt-detail-aside" id="inscription">
        <div class="evt-detail-card">
          <?php /* bandeau d'éligibilité */ ?>
          <?php if ($mode !== 'aucune' && $mode !== 'externe' && !$eligibilite['ok']): ?>
            <?php
              $msg = match($eligibilite['reason']) {
                'login_required'             => "Cet événement est réservé aux membres connectés.",
                'login_required_no_externes' => "Cet événement est réservé aux étudiants des écoles invitées - connecte-toi pour vérifier ton accès.",
                'ecole_non_eligible'         => "Désolé, cet événement est réservé aux écoles invitées et la tienne n'en fait pas partie.",
                default                      => "Inscription non disponible.",
              };
            ?>
            <div class="flash flash--warn" style="margin:0 0 var(--s3)"><?= $msg ?></div>
            <?php if (in_array($eligibilite['reason'], ['login_required', 'login_required_no_externes'], true)): ?>
              <a href="login.php?next=<?= urlencode('evenement.php?id=' . $id) ?>" class="btn btn--primary btn--full">Se connecter</a>
              <p class="evt-detail-help"><a href="register.php">Pas encore de compte ?</a></p>
            <?php endif; ?>
          <?php else: ?>
          <?php if ($mode !== 'aucune' && $mode !== 'externe' && !$fenetreInsc['open']): ?>
            <div class="evt-detail-mode-badge"><?= $fenetreInsc['status'] === 'before' ? 'Inscriptions à venir' : 'Inscriptions closes' ?></div>
            <div class="flash flash--warn" style="margin:0"><?= htmlspecialchars(evt_inscriptions_fenetre_message($fenetreInsc)) ?></div>
          <?php /* mode sans inscription */ ?>
          <?php elseif ($mode === 'aucune'): ?>
            <div class="evt-detail-mode-badge">Événement ouvert</div>
            <p class="evt-detail-help">Pas d'inscription requise - viens simplement le jour J.</p>

          <?php /* mode email - gratuit, sans compte */ ?>
          <?php elseif ($mode === 'email'): ?>
            <div class="evt-detail-mode-badge">Inscription par email</div>
            <?php if (!empty($ev['inscription_message'])): ?>
              <p class="evt-detail-msg"><?= nl2br(htmlspecialchars($ev['inscription_message'])) ?></p>
            <?php endif; ?>
            <?php if ($places > 0): ?>
              <p class="evt-detail-stock">
                <strong><?= $dispoSlots ?></strong> place<?= $dispoSlots > 1 ? 's' : '' ?> sur <?= $places ?>
                <?php if ($complet): ?><span class="evt-detail-stock--full">- Complet (liste d'attente)</span><?php endif; ?>
              </p>
            <?php endif; ?>
            <form method="post" class="evt-detail-form">
              <input type="hidden" name="action" value="inscrire_email">
              <div class="evt-detail-row">
                <label>Prénom *<input type="text" name="prenom" required value="<?= htmlspecialchars($_SESSION['prenom'] ?? '') ?>"></label>
                <label>Nom *<input type="text" name="nom" required value="<?= htmlspecialchars($_SESSION['nom'] ?? '') ?>"></label>
              </div>
              <label>Email *<input type="email" name="email" required value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>"></label>
              <button type="submit" class="btn btn--primary btn--full">
                <?= $complet ? "Rejoindre la liste d'attente" : "Obtenir mon billet →" ?>
              </button>
              <p class="evt-detail-help">Un billet (QR code) te sera remis immédiatement.</p>
            </form>

          <?php /* mode connexion - gratuit, compte requis */ ?>
          <?php elseif ($mode === 'connexion'): ?>
            <div class="evt-detail-mode-badge">Inscription par connexion</div>
            <?php if (!empty($ev['inscription_message'])): ?>
              <p class="evt-detail-msg"><?= nl2br(htmlspecialchars($ev['inscription_message'])) ?></p>
            <?php endif; ?>
            <?php if ($places > 0): ?>
              <p class="evt-detail-stock">
                <strong><?= $dispoSlots ?></strong> place<?= $dispoSlots > 1 ? 's' : '' ?> sur <?= $places ?>
                <?php if ($complet): ?><span class="evt-detail-stock--full">- Complet (liste d'attente)</span><?php endif; ?>
              </p>
            <?php endif; ?>
            <?php if (!$userId): ?>
              <a href="login.php?next=<?= urlencode('evenement.php?id=' . $id) ?>" class="btn btn--primary btn--full">Se connecter pour s'inscrire</a>
              <p class="evt-detail-help"><a href="register.php">Pas encore de compte ?</a></p>
            <?php elseif (!empty($mesBillets)): ?>
              <?php
                $mb0 = $mesBillets[0];
                $mbPos = ($mb0['statut'] ?? '') === 'liste_attente'
                    ? billet_waitlist_position($pdo, (int)$mb0['id'])
                    : null;
              ?>
              <p class="flash flash--ok" style="margin:0">
                <?php if (($mb0['statut'] ?? '') === 'liste_attente'): ?>
                  ✓ <?= htmlspecialchars(corpo_t('evt.waitlist_badge')) ?><?= $mbPos ? ' — #' . (int)$mbPos : '' ?>.
                  <span class="evt-waitlist-hint"><?= htmlspecialchars(corpo_t('evt.waitlist_auto')) ?></span>
                <?php else: ?>
                  ✓ Inscrit·e.
                <?php endif; ?>
              </p>
            <?php else: ?>
              <form method="post">
                <input type="hidden" name="action" value="inscrire_connexion">
                <button type="submit" class="btn btn--primary btn--full">
                  <?= $complet ? "Rejoindre la liste d'attente" : "M'inscrire →" ?>
                </button>
              </form>
            <?php endif; ?>

          <?php /* mode billetterie externe */ ?>
          <?php elseif ($mode === 'externe'): ?>
            <div class="evt-detail-mode-badge">Billetterie externe</div>
            <?php if (!empty($ev['lien_billetterie'])): ?>
              <a href="<?= htmlspecialchars($ev['lien_billetterie']) ?>" target="_blank" rel="noopener" class="btn btn--primary btn--full">Acheter mon billet →</a>
              <p class="evt-detail-help">Tu seras redirigé·e vers la billetterie de l'organisateur.</p>
            <?php endif; ?>

          <?php /* mode billetterie email - payant, sans compte */ ?>
          <?php elseif ($mode === 'billetterie_email'): ?>
            <div class="evt-detail-mode-badge">Billetterie par email</div>
            <div class="evt-detail-price">
              <?php if ($prix > 0): ?>
                <span class="evt-detail-price__amount"><?= number_format($prix, 2, ',', ' ') ?> €</span>
                <span class="evt-detail-price__unit">/ billet</span>
              <?php else: ?>
                <span class="evt-detail-price__amount">Gratuit</span>
              <?php endif; ?>
            </div>
            <?php if ($places > 0): ?>
              <p class="evt-detail-stock">
                <strong><?= $dispoSlots ?></strong> place<?= $dispoSlots > 1 ? 's' : '' ?> sur <?= $places ?>
                <?php if ($complet): ?><span class="evt-detail-stock--full">- Complet (liste d'attente)</span><?php endif; ?>
              </p>
            <?php endif; ?>
            <?php if (!empty($ev['inscription_message'])): ?>
              <p class="evt-detail-msg"><?= nl2br(htmlspecialchars($ev['inscription_message'])) ?></p>
            <?php endif; ?>
            <?php if ($onWaitlist || !empty($billetsInvite)): ?>
              <?php
                $bw = $mesBillets[0] ?? $billetsInvite[0] ?? null;
                $bwPos = $bw ? billet_waitlist_position($pdo, (int)$bw['id']) : null;
              ?>
              <p class="flash flash--ok" style="margin:0">
                <?= htmlspecialchars(corpo_t('evt.waitlist_badge')) ?><?= $bwPos ? ' #' . (int)$bwPos : '' ?> —
                <?= htmlspecialchars(corpo_t('evt.waitlist_auto')) ?>
              </p>
            <?php elseif ($complet): ?>
              <form method="post" class="evt-detail-form">
                <input type="hidden" name="action" value="rejoindre_file_attente">
                <div class="evt-detail-row">
                  <label>Prénom *<input type="text" name="prenom" required value="<?= htmlspecialchars($_SESSION['prenom'] ?? '') ?>"></label>
                  <label>Nom *<input type="text" name="nom" required value="<?= htmlspecialchars($_SESSION['nom'] ?? '') ?>"></label>
                </div>
                <label>Email *<input type="email" name="email" required value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>"></label>
                <button type="submit" class="btn btn--primary btn--full"><?= htmlspecialchars(corpo_t('evt.btn_join_waitlist')) ?></button>
                <p class="evt-detail-help"><?= htmlspecialchars(corpo_t('evt.waitlist_help')) ?></p>
              </form>
            <?php else: ?>
              <form method="post" class="evt-detail-form">
                <input type="hidden" name="action" value="acheter">
                <div class="evt-detail-row">
                  <label>Prénom *<input type="text" name="prenom" required value="<?= htmlspecialchars($_SESSION['prenom'] ?? '') ?>"></label>
                  <label>Nom *<input type="text" name="nom" required value="<?= htmlspecialchars($_SESSION['nom'] ?? '') ?>"></label>
                </div>
                <label>Email *<input type="email" name="email" required value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>"></label>
                <?php if (!empty($tarifsDispo)): ?>
                  <label>Tarif *
                    <select name="tarif_id" required>
                      <?php foreach ($tarifsDispo as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nom']) ?> - <?= number_format($t['prix'], 2, ',', ' ') ?> €</option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                <?php endif; ?>
                <?php $maxQ = max(1, (int)($ev['max_billets_par_personne'] ?? 1)); ?>
                <?php if ($maxQ > 1): ?>
                <label>Quantité
                  <select name="quantite">
                    <?php for ($q = 1; $q <= $maxQ; $q++): ?>
                      <option value="<?= $q ?>"><?= $q ?> billet<?= $q > 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
                  </select>
                </label>
                <?php else: ?>
                  <input type="hidden" name="quantite" value="1">
                <?php endif; ?>
                <label>Code promo <small style="color:var(--text-muted)">(optionnel)</small>
                  <input type="text" name="code_promo" value="<?= htmlspecialchars($promoFromUrl) ?>" placeholder="Ex: EARLY20" style="text-transform:uppercase">
                </label>
                <?php
                  $_payProvider = paiement_provider_for($prix);
                  $_payIsMock   = paiement_is_mock($_payProvider);
                  $_payLabel    = $_payProvider === 'stripe' ? 'Stripe' : 'SumUp';
                ?>
                <button type="submit" class="btn btn--primary btn--full">
                  Payer →
                </button>
                <?php if ($_payIsMock): ?>
                  <p class="evt-detail-help" style="color:var(--orange);font-size:.72rem">
                    ⚠ Paiement en mode test (mock <?= htmlspecialchars($_payLabel) ?>) - aucune carte ne sera débitée.
                  </p>
                <?php else: ?>
                  <p class="evt-detail-help" style="font-size:.72rem">
                    🔒 Paiement sécurisé via <?= htmlspecialchars($_payLabel) ?>
                  </p>
                <?php endif; ?>
              </form>
            <?php endif; ?>

          <?php /* mode billetterie connexion - payant, compte requis */ ?>
          <?php elseif ($mode === 'billetterie_connexion'): ?>
            <div class="evt-detail-mode-badge">Billetterie par connexion</div>
            <div class="evt-detail-price">
              <?php if ($prix > 0): ?>
                <span class="evt-detail-price__amount"><?= number_format($prix, 2, ',', ' ') ?> €</span>
                <span class="evt-detail-price__unit">/ billet</span>
              <?php else: ?>
                <span class="evt-detail-price__amount">Gratuit</span>
              <?php endif; ?>
            </div>
            <?php if ($places > 0): ?>
              <p class="evt-detail-stock">
                <strong><?= $dispoSlots ?></strong> place<?= $dispoSlots > 1 ? 's' : '' ?> sur <?= $places ?>
                <?php if ($complet): ?><span class="evt-detail-stock--full">- Complet (liste d'attente)</span><?php endif; ?>
              </p>
            <?php endif; ?>
            <?php if (!empty($ev['inscription_message'])): ?>
              <p class="evt-detail-msg"><?= nl2br(htmlspecialchars($ev['inscription_message'])) ?></p>
            <?php endif; ?>

            <?php if (!$userId): ?>
              <a href="login.php?next=<?= urlencode('evenement.php?id=' . $id) ?>" class="btn btn--primary btn--full">Se connecter pour acheter</a>
              <p class="evt-detail-help"><a href="register.php">Pas encore de compte ?</a></p>
            <?php elseif ($onWaitlist): ?>
              <?php $bwPos = billet_waitlist_position($pdo, (int)($mesBillets[0]['id'] ?? 0)); ?>
              <p class="flash flash--ok" style="margin:0">
                <?= htmlspecialchars(corpo_t('evt.waitlist_badge')) ?><?= $bwPos ? ' #' . (int)$bwPos : '' ?> —
                <?= htmlspecialchars(corpo_t('evt.waitlist_auto')) ?>
              </p>
            <?php elseif ($needsPayAfterPromo): ?>
              <div class="flash flash--ok" style="margin:0 0 var(--s3)">
                <?= htmlspecialchars(corpo_t('evt.waitlist_promoted_pay')) ?>
              </div>
              <form method="post" class="evt-detail-form">
                <input type="hidden" name="action" value="acheter">
                <?php if (!empty($tarifsDispo)): ?>
                  <label>Tarif *
                    <select name="tarif_id" required>
                      <?php foreach ($tarifsDispo as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nom']) ?> - <?= number_format($t['prix'], 2, ',', ' ') ?> €</option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                <?php endif; ?>
                <input type="hidden" name="quantite" value="1">
                <label>Code promo <small style="color:var(--text-muted)">(optionnel)</small>
                  <input type="text" name="code_promo" value="<?= htmlspecialchars($promoFromUrl) ?>" placeholder="Ex: EARLY20" style="text-transform:uppercase">
                </label>
                <button type="submit" class="btn btn--primary btn--full">Finaliser le paiement →</button>
              </form>
            <?php elseif ($complet): ?>
              <form method="post">
                <input type="hidden" name="action" value="rejoindre_file_attente">
                <button type="submit" class="btn btn--primary btn--full"><?= htmlspecialchars(corpo_t('evt.btn_join_waitlist')) ?></button>
                <p class="evt-detail-help"><?= htmlspecialchars(corpo_t('evt.waitlist_help')) ?></p>
              </form>
            <?php else: ?>
              <form method="post" class="evt-detail-form">
                <input type="hidden" name="action" value="acheter">
                <?php if (!empty($tarifsDispo)): ?>
                  <label>Tarif *
                    <select name="tarif_id" required>
                      <?php foreach ($tarifsDispo as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nom']) ?> - <?= number_format($t['prix'], 2, ',', ' ') ?> €</option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                <?php endif; ?>
                <?php $maxQ = max(1, (int)($ev['max_billets_par_personne'] ?? 1)); ?>
                <?php if ($maxQ > 1): ?>
                  <label>Quantité
                    <select name="quantite">
                      <?php for ($q = 1; $q <= $maxQ; $q++): ?>
                        <option value="<?= $q ?>"><?= $q ?> billet<?= $q > 1 ? 's' : '' ?></option>
                      <?php endfor; ?>
                    </select>
                  </label>
                <?php else: ?>
                  <input type="hidden" name="quantite" value="1">
                <?php endif; ?>
                <label>Code promo <small style="color:var(--text-muted)">(optionnel)</small>
                  <input type="text" name="code_promo" value="<?= htmlspecialchars($promoFromUrl) ?>" placeholder="Ex: ETUDIANT20" style="text-transform:uppercase">
                </label>
                <?php
                  $_payProvider2 = paiement_provider_for($prix);
                  $_payIsMock2   = paiement_is_mock($_payProvider2);
                  $_payLabel2    = $_payProvider2 === 'stripe' ? 'Stripe' : 'SumUp';
                ?>
                <button type="submit" class="btn btn--primary btn--full">Payer →</button>
                <?php if ($_payIsMock2): ?>
                  <p class="evt-detail-help" style="color:var(--orange);font-size:.72rem">
                    ⚠ Paiement en mode test (mock <?= htmlspecialchars($_payLabel2) ?>).
                  </p>
                <?php else: ?>
                  <p class="evt-detail-help" style="font-size:.72rem">
                    🔒 Paiement sécurisé via <?= htmlspecialchars($_payLabel2) ?>
                  </p>
                <?php endif; ?>
              </form>
            <?php endif; ?>
          <?php endif; ?>
          <?php endif; /* fin éligibilité OK */ ?>
        </div>

        <!-- Billets visiteur (mode email, après inscription) -->
        <?php if (!empty($billetsInvite)): ?>
        <div class="evt-detail-card evt-mes-billets" id="mes-billets">
          <h3>🎟 Ton billet</h3>
          <?php foreach ($billetsInvite as $b): ?>
            <?php if (($b['statut'] ?? '') === 'liste_attente'): ?>
              <?= render_waitlist_card($b, $ev, billet_waitlist_position($pdo, (int)$b['id'])) ?>
            <?php else: ?>
              <?= render_ticket($b, $ev) ?>
            <?php endif; ?>
          <?php endforeach; ?>
          <p class="evt-detail-help">Conserve cette page (ou imprime-la) pour présenter ton QR à l'entrée.</p>
        </div>
        <?php endif; ?>

        <!-- Billets utilisateur connecté -->
        <?php if (!empty($mesBillets)): ?>
        <div class="evt-detail-card evt-mes-billets" id="mes-billets">
          <h3>🎟 Mes billets</h3>
          <?php foreach ($mesBillets as $b): ?>
            <?php if (($b['statut'] ?? '') === 'liste_attente'): ?>
              <?= render_waitlist_card($b, $ev, billet_waitlist_position($pdo, (int)$b['id'])) ?>
            <?php else: ?>
              <?= render_ticket($b, $ev) ?>
            <?php endif; ?>
            <?php if ($b['statut'] !== 'en_attente'): ?>
              <form method="post" onsubmit="return confirm('Annuler ce billet ?')" style="margin:.4rem 0 1rem">
                <input type="hidden" name="action" value="annuler_billet">
                <input type="hidden" name="inscription_id" value="<?= $b['id'] ?>">
                <button class="btn btn--sm btn--danger">Annuler ce billet</button>
              </form>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      </aside>
    </div>
  </div>
</main>

<?php if (!empty($ev['lieu'])): ?>
<!-- Leaflet - carte interactive (OpenStreetMap, sans clé API) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
  const mapEl = document.getElementById('evt-map');
  if (!mapEl || typeof L === 'undefined') return;
  const lieu = mapEl.dataset.lieu || '';
  if (!lieu) return;

  // Géocodage Nominatim (OpenStreetMap) - gratuit, sans clé API
  // Politique d'usage : 1 requête/seconde max, identifier avec un User-Agent
  // Cache localStorage par lieu pour limiter les appels
  const cacheKey = 'evt-geocode:' + lieu.toLowerCase();
  function placeMap(lat, lon) {
    mapEl.innerHTML = ''; // retire le placeholder
    const map = L.map(mapEl, { scrollWheelZoom: false }).setView([lat, lon], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);
    L.marker([lat, lon]).addTo(map).bindPopup(lieu).openPopup();
    // Lien OSM ciblé sur la position trouvée
    const osm = document.getElementById('evt-map-osm');
    if (osm) osm.href = `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lon}#map=17/${lat}/${lon}`;
  }
  function fail(msg) {
    mapEl.innerHTML = '<div class="evt-map__placeholder"><span>🗺️</span><p>' + (msg || 'Carte indisponible') + '</p></div>';
  }

  const cached = sessionStorage.getItem(cacheKey);
  if (cached) {
    try { const c = JSON.parse(cached); placeMap(c.lat, c.lon); return; } catch (e) { }
  }
  fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(lieu), {
    headers: { 'Accept': 'application/json' }
  })
    .then(r => r.ok ? r.json() : Promise.reject('http'))
    .then(arr => {
      if (!Array.isArray(arr) || arr.length === 0) return fail('Lieu introuvable sur la carte');
      const lat = parseFloat(arr[0].lat), lon = parseFloat(arr[0].lon);
      sessionStorage.setItem(cacheKey, JSON.stringify({ lat, lon }));
      placeMap(lat, lon);
    })
    .catch(() => fail());
})();
</script>
<?php endif; ?>

<!-- modale QR plein écran -->
<div id="qr-modal" class="qr-modal" hidden role="dialog" aria-modal="true" aria-labelledby="qr-modal-title">
  <div class="qr-modal__backdrop" data-qr-close></div>
  <div class="qr-modal__inner">
    <button type="button" class="qr-modal__close" data-qr-close aria-label="Fermer">✕</button>
    <h3 id="qr-modal-title" class="qr-modal__title"></h3>
    <p class="qr-modal__sub" id="qr-modal-sub"></p>
    <img id="qr-modal-img" class="qr-modal__img" alt="QR code agrandi">
    <p class="qr-modal__hint">Présente ce QR à l'entrée pour validation</p>
  </div>
</div>

<!-- modale info Wallet -->
<div id="wallet-info-modal" class="qr-modal" hidden role="dialog" aria-modal="true" aria-labelledby="wallet-info-title">
  <div class="qr-modal__backdrop" onclick="closeWalletInfo()"></div>
  <div class="qr-modal__inner" style="max-width:480px;text-align:left">
    <button type="button" class="qr-modal__close" onclick="closeWalletInfo()" aria-label="Fermer">✕</button>
    <h3 id="wallet-info-title" class="qr-modal__title" style="text-align:center"></h3>
    <div id="wallet-info-body" style="color:#333;font-size:.92rem;line-height:1.55"></div>
  </div>
</div>

<script>
// fonctions QR et impression (appelées via onclick)
function openQrModal(src, evt, name) {
  var modal = document.getElementById('qr-modal');
  if (!modal) { alert('Erreur : modale introuvable.'); return; }
  var img   = document.getElementById('qr-modal-img');
  var title = document.getElementById('qr-modal-title');
  var sub   = document.getElementById('qr-modal-sub');
  if (img)   img.src = src;
  if (title) title.textContent = evt || '';
  if (sub)   sub.textContent  = name || '';
  modal.hidden = false;
  modal.classList.add('is-open');
  document.body.style.overflow = 'hidden';
}
function closeQrModal() {
  var modal = document.getElementById('qr-modal');
  if (!modal) return;
  modal.hidden = true;
  modal.classList.remove('is-open');
  var img = document.getElementById('qr-modal-img');
  if (img) img.src = '';
  document.body.style.overflow = '';
}
function printTicket(id) {
  var ticket = document.getElementById('ticket-' + id);
  if (!ticket) { alert('Billet introuvable.'); return; }

  // Clone profond pour ne pas modifier le DOM visible.
  var clone = ticket.cloneNode(true);
  clone.classList.add('is-printing');
  // Supprime les éléments inutiles à l'impression (boutons d'action, hints).
  clone.querySelectorAll('.ticket__actions, .ticket__qr-hint, .ticket__statut, .ticket__body')
       .forEach(function (el) { el.remove(); });

  var styles =
      'html,body{margin:0;padding:0;background:#fff;color:#1a0040;font-family:Inter,system-ui,-apple-system,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact;color-adjust:exact;}'
    + '.ticket{display:flex;flex-direction:column;width:100%;background:#fff;page-break-inside:avoid;break-inside:avoid;}'
    + '.ticket__print-header{display:flex;align-items:center;justify-content:space-between;padding:28px 40px;background:linear-gradient(135deg,#1a0040 0%,#5D0282 50%,#8B2FC9 100%) !important;color:#fff;border-bottom:4px solid #8B2FC9;}'
    + '.ticket__print-brand{display:flex;flex-direction:column;}'
    + '.ticket__print-brand-name{font-size:24pt;font-weight:900;color:#fff;letter-spacing:2px;text-transform:uppercase;line-height:1;}'
    + '.ticket__print-brand-sub{font-size:9pt;color:rgba(255,255,255,.85);letter-spacing:4px;margin-top:6px;font-weight:600;}'
    + '.ticket__print-id{font-size:11pt;color:rgba(255,255,255,.9);padding:6px 14px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.35);border-radius:8px;letter-spacing:1px;font-weight:600;}'
    + '.ticket__left{display:flex;flex-direction:column;align-items:center;padding:36px 40px 16px;background:#fff;}'
    + '.ticket__qr-btn{display:inline-block;border:4px solid #5D0282;background:#fff;padding:14px;border-radius:16px;line-height:0;box-shadow:0 0 0 1px rgba(93,2,130,.15);}'
    + '.ticket__qr{display:block;width:260px;height:260px;background:#fff;object-fit:contain;}'
    + '.ticket__print-info{display:grid;grid-template-columns:1fr 1fr;gap:14px 32px;padding:20px 40px 24px;margin:0;background:#fff;border-top:2px dashed #c4b5fd;}'
    + '.ticket__print-info > div{display:flex;flex-direction:column;gap:2px;}'
    + '.ticket__print-info dt{font-size:8pt;color:#5D0282;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin:0;}'
    + '.ticket__print-info dd{font-size:11.5pt;color:#1a0040;font-weight:600;margin:0;line-height:1.35;}'
    + '.ticket__print-footer{display:block;padding:16px 40px 22px;background:#faf7ff;border-top:1px solid #e8d9f5;text-align:center;}'
    + '.ticket__print-instructions{font-size:9.5pt;color:#5D0282;margin:0 0 6px;font-weight:600;line-height:1.5;}'
    + '.ticket__print-meta{font-size:8pt;color:#888;margin:0;letter-spacing:.5px;}'
    + '@page{margin:0;size:A4 portrait;}';

  // Détecte mobile : iOS Safari ne supporte pas iframe.contentWindow.print(),
  // il faut ouvrir un nouvel onglet sinon le ticket ne s'imprime pas.
  var isMobile = /iPhone|iPad|iPod|Android|Mobi/i.test(navigator.userAgent || '');

  // Script auto-print injecté dans le document généré : attend que les images
  // (QR distant) soient chargées puis ouvre la boîte d'impression du navigateur.
  var autoPrintScript =
      '(function(){function go(){try{window.focus();window.print();}catch(e){console.error(e);}}'
    + 'var imgs=document.images;var t0=Date.now();'
    + 'function ready(){var ok=true;for(var i=0;i<imgs.length;i++){if(!imgs[i].complete||imgs[i].naturalWidth===0){ok=false;break;}}return ok;}'
    + 'var iv=setInterval(function(){if(ready()||Date.now()-t0>5000){clearInterval(iv);setTimeout(go,250);}},120);'
    + '})();';

  var html =
      '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
    + '<meta name="viewport" content="width=device-width,initial-scale=1">'
    + '<title>Billet ' + id + '</title>'
    + '<base href="' + window.location.origin + '/">'
    + '<style>' + styles + '</style></head><body>'
    + clone.outerHTML
    + '<scr' + 'ipt>' + autoPrintScript + '</scr' + 'ipt>'
    + '</body></html>';

  if (isMobile) {
    // Mobile (iOS Safari notamment) : nouvel onglet. Le navigateur autorise
    // window.open() car l'appel découle d'un clic utilisateur direct.
    var win = window.open('', '_blank');
    if (!win) {
      alert('Le navigateur a bloqué l\'ouverture du billet. '
        + 'Autorise les fenêtres pop-up pour ce site, puis réessaie.');
      return;
    }
    try {
      win.document.open();
      win.document.write(html);
      win.document.close();
    } catch (e) {
      console.error('[print mobile]', e);
    }
    return;
  }

  // Desktop : iframe cachée - seul son contenu est imprimé → 1 page A4 = 1 billet.
  var iframe = document.createElement('iframe');
  iframe.setAttribute('aria-hidden', 'true');
  iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
  document.body.appendChild(iframe);

  var doc = iframe.contentDocument || iframe.contentWindow.document;
  doc.open();
  doc.write(html);
  doc.close();

  setTimeout(function () {
    if (iframe && iframe.parentNode) iframe.parentNode.removeChild(iframe);
  }, 8000);
}
function walletInfo(provider) {
  var modal = document.getElementById('wallet-info-modal');
  var title = document.getElementById('wallet-info-title');
  var body  = document.getElementById('wallet-info-body');
  if (!modal || !title || !body) {
    alert('Cette fonctionnalité nécessite une configuration côté serveur.');
    return;
  }
  if (provider === 'apple') {
    title.textContent = '🍎 Apple Wallet - bientôt disponible';
    body.innerHTML =
      '<p>L\'ajout à Apple Wallet nécessite un <strong>certificat Pass Type ID</strong> ' +
      'du programme Apple Developer (99 €/an), encore en cours de configuration.</p>' +
      '<p><strong>En attendant :</strong></p>' +
      '<ul>' +
      '<li>🖨 Imprime ton billet ou enregistre-le en PDF</li>' +
      '<li>📅 Ajoute l\'événement à ton agenda Apple</li>' +
      '<li>📸 Garde une capture d\'écran de ton QR</li>' +
      '</ul>' +
      '<p style="color:var(--text-muted);font-size:.8rem;margin-top:1rem">Le QR scanné à l\'entrée fonctionne dans tous les cas.</p>';
  } else {
    title.textContent = '🅖 Google Wallet - bientôt disponible';
    body.innerHTML =
      '<p>L\'ajout à Google Wallet nécessite un <strong>compte Google Cloud</strong> ' +
      'avec Issuer ID et clé de service, encore en cours de configuration.</p>' +
      '<p><strong>En attendant :</strong></p>' +
      '<ul>' +
      '<li>🖨 Imprime ton billet ou enregistre-le en PDF</li>' +
      '<li>📅 Ajoute l\'événement à ton agenda Google</li>' +
      '<li>📸 Garde une capture d\'écran de ton QR</li>' +
      '</ul>' +
      '<p style="color:var(--text-muted);font-size:.8rem;margin-top:1rem">Le QR scanné à l\'entrée fonctionne dans tous les cas.</p>';
  }
  modal.hidden = false;
  document.body.style.overflow = 'hidden';
}
function closeWalletInfo() {
  var modal = document.getElementById('wallet-info-modal');
  if (!modal) return;
  modal.hidden = true;
  document.body.style.overflow = '';
}
/* Fermeture modale : backdrop, croix, Escape */
document.addEventListener('click', function (e) {
  if (e.target.closest('[data-qr-close]')) closeQrModal();
});
document.addEventListener('keydown', function (e) {
  if (e.key !== 'Escape') return;
  closeQrModal();
  closeWalletInfo();
});
window.addEventListener('afterprint', function () {
  document.querySelectorAll('.ticket.is-printing').forEach(function (el) { el.classList.remove('is-printing'); });
});
/* S'assure que la modale est cachée au chargement (au cas où CSS override) */
(function () {
  var m = document.getElementById('qr-modal');
  if (m) { m.hidden = true; m.classList.remove('is-open'); }
})();
</script>

<?php require_once 'includes/footer.php'; ?>
