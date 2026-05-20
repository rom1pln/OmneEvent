<?php
$page = 'evenements';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/i18n.php';
require_once 'includes/billetterie.php';
$title = corpo_t('evt.meta_title');

$userId   = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
$flashEvt = '';

// inscription / désinscription via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId) {
    $evtId = (int)($_POST['evt_id'] ?? 0);
    $act   = $_POST['action'] ?? '';

    if ($evtId && $act === 'inscrire') {
        $evInfo = $pdo->prepare('SELECT mode_inscription FROM evenements WHERE id = ?');
        $evInfo->execute([$evtId]);
        $ei = $evInfo->fetch();
        if ($ei && evt_normalize_mode($ei['mode_inscription']) === 'connexion') {
            $exists = $pdo->prepare(
                "SELECT id FROM inscriptions_evenement
                  WHERE user_id = ? AND evenement_id = ?
                    AND statut IN ('confirme','liste_attente','en_attente')"
            );
            $exists->execute([$userId, $evtId]);
            if ($exists->fetchColumn()) {
                $flashEvt = corpo_t('evt.flash_already');
            } else {
                $u = $pdo->prepare('SELECT email, nom, prenom FROM users WHERE id = ?');
                $u->execute([$userId]);
                $usr = $u->fetch() ?: [];
                $newId = billet_create($pdo, $evtId, $userId, [
                    'email'  => $usr['email']  ?? '',
                    'nom'    => $usr['nom']    ?? '',
                    'prenom' => $usr['prenom'] ?? '',
                ], 0.0, 'aucun', null);
                if ($newId) {
                    $stIns = $pdo->prepare('SELECT statut FROM inscriptions_evenement WHERE id = ?');
                    $stIns->execute([$newId]);
                    $statut = (string)$stIns->fetchColumn();
                    if ($statut === 'liste_attente') {
                        $pos = billet_waitlist_position($pdo, $newId);
                        $flashEvt = $pos
                            ? sprintf(corpo_t('evt.flash_wait_pos'), $pos)
                            : corpo_t('evt.flash_wait');
                        @billet_send_mail_for_ids($pdo, [$newId]);
                    } else {
                        $flashEvt = corpo_t('evt.flash_ok');
                        @billet_send_mail_for_ids($pdo, [$newId]);
                    }
                } else {
                    $flashEvt = corpo_t('evt.flash_err');
                }
            }
        }
    }
    if ($evtId && $act === 'desinscire') {
        if (billet_cancel_for_user_event($pdo, $userId, $evtId)) {
            $flashEvt = corpo_t('evt.flash_out');
        }
    }
}

// récupère les events de l'user connecté
$mesEvts = [];
if ($userId) {
    $stmtME = $pdo->prepare("SELECT evenement_id, statut FROM inscriptions_evenement WHERE user_id = ?");
    $stmtME->execute([$userId]);
    foreach ($stmtME->fetchAll() as $row) {
        $mesEvts[$row['evenement_id']] = $row['statut'];
    }
}

require_once 'includes/header.php';

// charge tous les events publiés
$events = $pdo->query(
    "SELECT * FROM evenements WHERE statut='publie' ORDER BY date ASC, heure ASC"
)->fetchAll();

$now        = new DateTime('today');
$monthNames = corpo_month_names_full();
$weekdayL   = corpo_weekday_short_labels();

foreach ($events as &$ev) {
    $ev['_ecoles']   = json_decode($ev['ecoles_invitees'] ?? '[]', true) ?: [];
    $ev['_campusJ']  = json_decode($ev['campus_invites']  ?? '[]', true) ?: [];
    if (empty($ev['_campusJ']) && !empty($ev['campus'])) {
        $ev['_campusJ'] = [$ev['campus']];
    }
    $ev['_dateObj']  = new DateTime($ev['date']);
    $ev['_isPast']   = $ev['_dateObj'] < $now;
    $places          = (int)($ev['places'] ?? 0);
    $inscrits        = (int)($ev['inscrits'] ?? 0);
    $ev['_dispo']    = $places > 0 ? max(0, $places - $inscrits) : null;
    $ev['_statut']   = 'aucune-inscription';
    if (($ev['mode_inscription'] ?? 'aucune') !== 'aucune') {
        if ($places > 0 && $inscrits >= $places) {
            $ev['_statut'] = 'complet';
        } else {
            $ev['_statut'] = 'inscription-ouverte';
        }
    }
}
unset($ev);

$upcoming = array_values(array_filter($events, fn($e) => !$e['_isPast']));
$past     = array_reverse(array_values(array_filter($events, fn($e) => $e['_isPast'])));

// Distincts pour les chips
$typesEvt = array_values(array_unique(array_filter(
    array_column($events, 'type'), fn($t) => !empty($t)
)));
sort($typesEvt);

$ecolesEvt = [];
foreach ($events as $ev) foreach ($ev['_ecoles'] as $e) {
    if ($e && $e !== 'Tous' && $e !== 'Toutes') $ecolesEvt[] = $e;
}
$ecolesEvt = array_values(array_unique($ecolesEvt));
$ecoleOrder = ['ECE','ESCE','HEIP','Sup de Pub','INSEEC Bachelor','INSEEC BBA','INSEEC BTS','INSEEC GE','INSEEC MSc'];
usort($ecolesEvt, function($a, $b) use ($ecoleOrder) {
    $ia = array_search($a, $ecoleOrder); $ib = array_search($b, $ecoleOrder);
    return (($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib));
});

$campusEvt = [];
foreach ($events as $ev) foreach ($ev['_campusJ'] as $c) {
    if ($c && $c !== 'Tous' && $c !== 'Tous campus') $campusEvt[] = $c;
}
$campusEvt = array_values(array_unique($campusEvt));
sort($campusEvt);

// Stats hero
$totalUpcoming   = count($upcoming);
$nbCetteSemaine  = 0;
$nbInscriptions  = 0;
$endOfWeek = (clone $now)->modify('+' . (7 - (int)$now->format('N')) . ' days');
foreach ($upcoming as $ev) {
    if ($ev['_dateObj'] <= $endOfWeek) $nbCetteSemaine++;
    if (($ev['mode_inscription'] ?? 'aucune') !== 'aucune') $nbInscriptions++;
}

// Calendrier (PHP - mois affiché)
$dt0 = !empty($upcoming) ? clone $upcoming[0]['_dateObj'] : new DateTime();
$reqYear  = (int)($_GET['y'] ?? $dt0->format('Y'));
$reqMonth = (int)($_GET['m'] ?? $dt0->format('n'));
$reqYear  = max(2024, min(2030, $reqYear));
$reqMonth = max(1, min(12, $reqMonth));

$prevDt = (new DateTime("$reqYear-$reqMonth-01"))->modify('-1 month');
$nextDt = (new DateTime("$reqYear-$reqMonth-01"))->modify('+1 month');

$calEvents = [];
foreach ($events as $ev) {
    $d = $ev['_dateObj'];
    if ((int)$d->format('n') === $reqMonth && (int)$d->format('Y') === $reqYear) {
        $calEvents[(int)$d->format('j')][] = $ev;
    }
}
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $reqMonth, $reqYear);
$firstDow    = (int)(new DateTime("$reqYear-$reqMonth-01"))->format('N');
$lastDow     = (int)(new DateTime("$reqYear-$reqMonth-$daysInMonth"))->format('N');
?>

<main>

  <!-- hero -->
  <section class="page-hero">
    <div class="container">
      <nav class="breadcrumb"><a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a><span>›</span><span><?= htmlspecialchars(corpo_t('evt.crumb')) ?></span></nav>
      <div class="evt-hero-grid">
        <div>
          <h1><?= htmlspecialchars(corpo_t('evt.meta_title')) ?></h1>
      <p class="page-hero__sub">
            <?= corpo_t('evt.hero_sub') ?>
      </p>
      <?php if ($flashEvt): ?>
            <div class="evt-flash"><?= htmlspecialchars($flashEvt) ?></div>
          <?php endif; ?>
        </div>
        <div class="evt-hero-stats">
          <div class="evt-stat"><strong><?= $totalUpcoming ?></strong><span><?= htmlspecialchars(corpo_t('evt.stat_upcoming')) ?></span></div>
          <div class="evt-stat"><strong><?= $nbCetteSemaine ?></strong><span><?= htmlspecialchars(corpo_t('evt.stat_week')) ?></span></div>
          <div class="evt-stat"><strong><?= $nbInscriptions ?></strong><span><?= htmlspecialchars(corpo_t('evt.stat_reg')) ?></span></div>
        </div>
      </div>
    </div>
  </section>

  <!-- filtres -->
  <div class="evt-filterbar">
    <div class="container">

      <!-- Ligne 1 : recherche + presets dates + vue -->
      <div class="evt-filterbar__row evt-filterbar__row--top">
        <div class="evt-search-wrap">
          <svg viewBox="0 0 20 20" fill="none" aria-hidden="true" class="evt-search-icon">
            <circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.6"/>
            <path d="M13 13l3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
          <input type="search" id="evt-search" class="evt-search-input"
                 placeholder="<?= htmlspecialchars(corpo_t('evt.search_ph')) ?>"
                 autocomplete="off" aria-label="<?= htmlspecialchars(corpo_t('evt.search_aria')) ?>">
          <button id="evt-search-clear" class="evt-search-clear" aria-label="<?= htmlspecialchars(corpo_t('evt.search_clear')) ?>" hidden>✕</button>
        </div>

        <div class="evt-presets" role="group" aria-label="<?= htmlspecialchars(corpo_t('evt.preset_group')) ?>">
          <button class="evt-preset active" data-preset="all"><?= htmlspecialchars(corpo_t('evt.preset_all')) ?></button>
          <button class="evt-preset" data-preset="today"><?= htmlspecialchars(corpo_t('evt.preset_today')) ?></button>
          <button class="evt-preset" data-preset="week"><?= htmlspecialchars(corpo_t('evt.preset_week')) ?></button>
          <button class="evt-preset" data-preset="weekend"><?= htmlspecialchars(corpo_t('evt.preset_weekend')) ?></button>
          <button class="evt-preset" data-preset="month"><?= htmlspecialchars(corpo_t('evt.preset_month')) ?></button>
        </div>

        <div class="evt-view-toggle" role="group" aria-label="<?= htmlspecialchars(corpo_t('evt.view_group')) ?>">
          <button class="evt-view-btn active" data-view="list" aria-label="<?= htmlspecialchars(corpo_t('evt.view_list_aria')) ?>">
            <svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor" aria-hidden="true">
              <rect x="2" y="3" width="12" height="2" rx="1"/><rect x="2" y="7" width="12" height="2" rx="1"/><rect x="2" y="11" width="12" height="2" rx="1"/>
            </svg>
            <?= htmlspecialchars(corpo_t('evt.view_list')) ?>
          </button>
          <button class="evt-view-btn" data-view="calendar" aria-label="<?= htmlspecialchars(corpo_t('evt.view_cal_aria')) ?>">
            <svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor" aria-hidden="true">
              <path d="M3 3h10a1 1 0 011 1v9a1 1 0 01-1 1H3a1 1 0 01-1-1V4a1 1 0 011-1zm0 4v6h10V7H3zm2-3v2h2V4H5zm4 0v2h2V4H9z"/>
            </svg>
            <?= htmlspecialchars(corpo_t('evt.view_cal')) ?>
          </button>
        </div>
      </div>

      <!-- Ligne 2 : chips type / école / campus / statut + tri -->
      <div class="evt-filterbar__row evt-filterbar__row--filters">

        <div class="evt-filter-group">
          <span class="evt-filter-label"><?= htmlspecialchars(corpo_t('evt.label_type')) ?></span>
          <div class="evt-chips">
            <button class="evt-chip active" data-filter="type" data-value=""><?= htmlspecialchars(corpo_t('evt.chip_all_m')) ?></button>
          <?php foreach ($typesEvt as $t): ?>
              <button class="evt-chip" data-filter="type" data-value="<?= htmlspecialchars($t) ?>">
              <?= htmlspecialchars($t) ?>
            </button>
          <?php endforeach; ?>
          </div>
        </div>

        <?php if (!empty($ecolesEvt)): ?>
        <div class="evt-filter-group">
          <span class="evt-filter-label"><?= htmlspecialchars(corpo_t('evt.label_school')) ?></span>
          <div class="evt-chips">
            <button class="evt-chip active" data-filter="ecole" data-value=""><?= htmlspecialchars(corpo_t('evt.chip_all_f')) ?></button>
            <?php foreach ($ecolesEvt as $e): ?>
              <button class="evt-chip" data-filter="ecole" data-value="<?= htmlspecialchars($e) ?>">
                <?= htmlspecialchars($e) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="evt-filter-group">
          <span class="evt-filter-label"><?= htmlspecialchars(corpo_t('evt.label_campus')) ?></span>
          <div class="evt-chips">
            <button class="evt-chip active" data-filter="campus" data-value=""><?= htmlspecialchars(corpo_t('evt.chip_all_m')) ?></button>
            <?php foreach ($campusEvt as $c): ?>
              <button class="evt-chip" data-filter="campus" data-value="<?= htmlspecialchars($c) ?>">
                <?= htmlspecialchars($c) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="evt-filter-group">
          <span class="evt-filter-label"><?= htmlspecialchars(corpo_t('evt.label_status')) ?></span>
          <div class="evt-chips">
            <button class="evt-chip active" data-filter="statut" data-value=""><?= htmlspecialchars(corpo_t('evt.chip_all_m')) ?></button>
            <button class="evt-chip" data-filter="statut" data-value="inscription-ouverte"><?= htmlspecialchars(corpo_t('evt.status_open')) ?></button>
            <button class="evt-chip" data-filter="statut" data-value="complet"><?= htmlspecialchars(corpo_t('evt.status_full')) ?></button>
          </div>
        </div>

        <div class="evt-filter-group evt-filter-group--end">
          <select id="evt-sort" class="evt-select" aria-label="<?= htmlspecialchars(corpo_t('evt.sort_aria')) ?>">
            <option value="date-asc"><?= htmlspecialchars(corpo_t('evt.sort_date_asc')) ?></option>
            <option value="date-desc"><?= htmlspecialchars(corpo_t('evt.sort_date_desc')) ?></option>
            <option value="popular"><?= htmlspecialchars(corpo_t('evt.sort_pop')) ?></option>
        </select>
          <button id="evt-reset" class="evt-reset-btn"><?= htmlspecialchars(corpo_t('evt.reset')) ?></button>
        </div>
      </div>

      <!-- Filtres actifs -->
      <div id="evt-active-filters" class="evt-active-filters" hidden></div>
    </div>
  </div>

  <!-- contenu -->
  <section class="section">
    <div class="container">

      <!-- vue liste -->
      <div id="evt-view-list" class="evt-view evt-view--active">
        <div class="evt-list-header">
          <span class="evt-list-count" id="evt-list-count"><?= $totalUpcoming ?></span>
          <?= $totalUpcoming !== 1 ? htmlspecialchars(corpo_t('evt.list_found_many')) : htmlspecialchars(corpo_t('evt.list_found_one')) ?>
        </div>

        <!-- À VENIR -->
        <?php if (!empty($upcoming)): ?>
          <div id="evt-upcoming-wrapper">
            <?php
            $groups = [];
            foreach ($upcoming as $ev) {
                $key = $ev['_dateObj']->format('Y-n');
                $groups[$key][] = $ev;
            }
            ?>
            <?php foreach ($groups as $key => $evs):
              [$y, $m] = explode('-', $key);
              $titre = htmlspecialchars($monthNames[(int)$m] . ' ' . $y);
            ?>
              <div class="evt-month-group" data-month-group="<?= htmlspecialchars($key) ?>">
                <h2 class="evt-month-title">
                  <span><?= $titre ?></span>
                  <span class="evt-month-count"><?= count($evs) ?></span>
                </h2>

                <div class="evt-cards">
                  <?php foreach ($evs as $ev):
                    $d        = $ev['_dateObj'];
                    $n        = (int)$d->format('N');
                    $jour     = $weekdayL[$n - 1] ?? $d->format('D');
                    $isToday  = $d == $now;
                    $places   = (int)($ev['places']   ?? 0);
                    $inscrits = (int)($ev['inscrits'] ?? 0);
                    $dispo    = $ev['_dispo'];
                    $mode     = evt_normalize_mode($ev['mode_inscription'] ?? 'aucune');
                    $statut   = $ev['_statut'];
                    $ecoleStr = implode(',', $ev['_ecoles']);
                    $campStr  = implode(',', $ev['_campusJ']);
                  ?>
                    <?php
                      $evtBanUrl = evt_media_url($ev['banniere'] ?? null, $base ?? '');
                      $evtUrl    = 'evenement.php?id=' . (int)$ev['id'];
                    ?>
                    <article class="evt-list-card evt-filterable<?= $evtBanUrl ? ' evt-list-card--cover' : '' ?>"
                             data-evt-type="<?= htmlspecialchars($ev['type'] ?? '') ?>"
                             data-evt-nom="<?= htmlspecialchars(mb_strtolower($ev['titre'])) ?>"
                             data-evt-search="<?= htmlspecialchars(mb_strtolower(($ev['titre'] ?? '').' '.($ev['lieu'] ?? '').' '.($ev['organisateur'] ?? '').' '.($ev['description'] ?? ''))) ?>"
                             data-evt-ecole="<?= htmlspecialchars($ecoleStr) ?>"
                             data-evt-campus="<?= htmlspecialchars($campStr) ?>"
                             data-evt-statut="<?= htmlspecialchars($statut) ?>"
                             data-evt-date="<?= $d->format('Y-m-d') ?>"
                             data-evt-day="<?= (int)$d->format('j') ?>"
                             data-evt-month="<?= (int)$d->format('n') ?>"
                             data-evt-year="<?= (int)$d->format('Y') ?>"
                             data-evt-popularity="<?= $inscrits ?>"
                             id="ev-<?= $ev['id'] ?>">

                      <?php if ($evtBanUrl): ?>
                      <a href="<?= htmlspecialchars($evtUrl) ?>" class="evt-list-card__cover" tabindex="-1" aria-hidden="true">
                        <img src="<?= htmlspecialchars($evtBanUrl) ?>" alt="" loading="lazy" decoding="async">
                      </a>
                      <?php endif; ?>

                      <div class="evt-list-card__body">
                        <div class="evt-list-card__head">
                          <time class="evt-list-card__when" datetime="<?= $d->format('Y-m-d') ?>">
                            <span class="evt-list-card__when-day"><?= $d->format('d') ?></span>
                            <span class="evt-list-card__when-meta">
                              <?= htmlspecialchars(corpo_month_abbr((int)$d->format('n'))) ?>
                              <span aria-hidden="true">·</span>
                              <?= htmlspecialchars($jour) ?>
                            </span>
                          </time>
                          <?php if ($isToday): ?>
                            <span class="evt-list-card__pill evt-list-card__pill--today"><?= htmlspecialchars(corpo_t('evt.today')) ?></span>
                          <?php endif; ?>
                          <?php if (!empty($ev['type'])): ?>
                            <span class="evt-list-card__pill evt-list-card__pill--type"><?= htmlspecialchars($ev['type']) ?></span>
                          <?php endif; ?>
                          <?php if ($statut === 'complet'): ?>
                            <span class="evt-list-card__pill evt-list-card__pill--full"><?= htmlspecialchars(corpo_t('evt.status_full')) ?></span>
                          <?php elseif ($dispo !== null && $dispo > 0 && $dispo <= 5): ?>
                            <span class="evt-list-card__pill evt-list-card__pill--low"><?= htmlspecialchars(sprintf(corpo_t('evt.low_places'), $dispo)) ?></span>
                          <?php elseif ($statut === 'inscription-ouverte'): ?>
                            <span class="evt-list-card__pill evt-list-card__pill--open"><?= htmlspecialchars(corpo_t('evt.status_open')) ?></span>
                          <?php endif; ?>
                        </div>

                        <h3 class="evt-list-card__title">
                          <a href="<?= htmlspecialchars($evtUrl) ?>">
                            <span class="evt-list-card__ico" aria-hidden="true"><?= htmlspecialchars(evt_normalize_icon($ev['icon'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                            <span><?= htmlspecialchars($ev['titre']) ?></span>
                          </a>
                        </h3>

                        <p class="evt-list-card__orga">
                          <?= htmlspecialchars(corpo_t('evt.by')) ?>
                          <strong><?= htmlspecialchars($ev['organisateur'] ?? 'Corpo') ?></strong>
                        </p>

                        <?php if (!empty($ev['description'])): ?>
                          <p class="evt-list-card__excerpt"><?= htmlspecialchars(mb_substr($ev['description'], 0, 160)) ?><?= mb_strlen($ev['description']) > 160 ? '…' : '' ?></p>
                        <?php endif; ?>

                        <ul class="evt-list-card__facts">
                          <?php if ($ev['heure']): ?>
                            <li><?= htmlspecialchars($ev['heure']) ?><?= $ev['heure_fin'] ? ' – ' . htmlspecialchars($ev['heure_fin']) : '' ?></li>
                          <?php endif; ?>
                          <?php if ($ev['lieu']): ?>
                            <li><?= htmlspecialchars($ev['lieu']) ?></li>
                          <?php endif; ?>
                          <?php foreach ($ev['_campusJ'] as $c): if ($c && $c !== 'Tous'): ?>
                            <li><?= htmlspecialchars($c) ?></li>
                          <?php endif; endforeach; ?>
                          <?php if ($places > 0): ?>
                            <li class="<?= $statut === 'complet' ? 'is-full' : ($dispo <= 5 ? 'is-low' : '') ?>">
                              <?php if ($statut === 'complet'): ?>
                                <?= htmlspecialchars(corpo_t('evt.status_full')) ?>
                              <?php else: ?>
                                <?= htmlspecialchars(sprintf(corpo_t('evt.places_of'), $dispo, $places)) ?>
                              <?php endif; ?>
                            </li>
                          <?php endif; ?>
                        </ul>

                        <div class="evt-list-card__foot">
                          <?php if ($mode === 'externe' && !empty($ev['lien_billetterie'])): ?>
                            <a href="<?= htmlspecialchars($ev['lien_billetterie']) ?>" target="_blank" rel="noopener" class="btn btn--primary btn--sm">
                              <?= htmlspecialchars(corpo_t('evt.btn_ticketing')) ?>
                            </a>
                          <?php elseif (in_array($mode, ['email','connexion','billetterie_email','billetterie_connexion'], true)): ?>
                            <a href="<?= htmlspecialchars($evtUrl) ?>" class="btn btn--primary btn--sm">
                              <?php
                              if ($mode === 'billetterie_email' || $mode === 'billetterie_connexion') {
                                  if ($statut === 'complet') {
                                      echo htmlspecialchars(corpo_t('evt.btn_join_waitlist'));
                                  } else {
                                      echo (float)($ev['prix'] ?? 0) > 0
                                          ? 'Acheter · ' . number_format((float)$ev['prix'], 2, ',', ' ') . ' €'
                                          : htmlspecialchars(corpo_t('evt.btn_register'));
                                  }
                              } else {
                                  echo $statut === 'complet'
                                      ? htmlspecialchars(corpo_t('evt.btn_join_waitlist'))
                                      : htmlspecialchars(corpo_t('evt.btn_register'));
                              }
                              ?>
                            </a>
                          <?php else: ?>
                            <a href="<?= htmlspecialchars($evtUrl) ?>" class="evt-list-card__more"><?= htmlspecialchars(corpo_t('mes_evt.btn_view')) ?></a>
                          <?php endif; ?>
                        </div>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state"><?= htmlspecialchars(corpo_t('evt.empty_upcoming')) ?></div>
        <?php endif; ?>

        <!-- Message vide après filtrage -->
        <p id="evt-empty" class="empty-state" hidden>
          <?= htmlspecialchars(corpo_t('evt.empty_filter')) ?>
          <button type="button" class="evt-empty-reset" data-evt-reset><?= htmlspecialchars(corpo_t('evt.empty_reset')) ?></button>
        </p>

        <!-- ÉVÉNEMENTS PASSÉS -->
        <?php if (!empty($past)): ?>
          <details class="evt-past-toggle">
            <summary>
              <span><?= htmlspecialchars(corpo_t('evt.past_title')) ?></span>
              <span class="evt-past-count"><?= count($past) ?></span>
            </summary>
            <div class="evt-cards">
              <?php foreach ($past as $ev):
                $d = $ev['_dateObj'];
                $ecoleStr = implode(',', $ev['_ecoles']);
                $campStr  = implode(',', $ev['_campusJ']);
              ?>
                <article class="evt-card evt-card--past evt-filterable"
                         data-evt-type="<?= htmlspecialchars($ev['type'] ?? '') ?>"
                         data-evt-nom="<?= htmlspecialchars(mb_strtolower($ev['titre'])) ?>"
                         data-evt-search="<?= htmlspecialchars(mb_strtolower(($ev['titre'] ?? '').' '.($ev['lieu'] ?? '').' '.($ev['organisateur'] ?? '').' '.($ev['description'] ?? ''))) ?>"
                         data-evt-ecole="<?= htmlspecialchars($ecoleStr) ?>"
                         data-evt-campus="<?= htmlspecialchars($campStr) ?>"
                         data-evt-statut="passe"
                         data-evt-date="<?= $d->format('Y-m-d') ?>"
                         data-evt-day="<?= (int)$d->format('j') ?>"
                         data-evt-month="<?= (int)$d->format('n') ?>"
                         data-evt-year="<?= (int)$d->format('Y') ?>">
                  <div class="evt-card__date">
                    <span class="evt-card__day"><?= $d->format('d') ?></span>
                    <span class="evt-card__month-sm"><?= htmlspecialchars(corpo_month_abbr((int)$d->format('n'))) ?></span>
                  </div>
                  <div class="evt-card__body">
                    <h3 class="evt-card__title"><?= htmlspecialchars($ev['titre']) ?></h3>
                    <div class="evt-card__organiser">
                      <?= htmlspecialchars(corpo_t('evt.by')) ?> <strong><?= htmlspecialchars($ev['organisateur'] ?? 'Corpo') ?></strong>
                      · <?= $d->format('d') ?> <?= htmlspecialchars($monthNames[(int)$d->format('n')]) ?> <?= $d->format('Y') ?>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endif; ?>
      </div>

      <!-- vue calendrier -->
      <div id="evt-view-calendar" class="evt-view">
        <div class="evt-cal-wrapper">
          <div class="evt-cal-header">
            <a href="?m=<?= $prevDt->format('n') ?>&y=<?= $prevDt->format('Y') ?>#evt-view-calendar"
               class="evt-cal-nav" aria-label="<?= htmlspecialchars(corpo_t('evt.cal_prev')) ?>">‹</a>
            <h2 class="evt-cal-month">
              <?= htmlspecialchars($monthNames[$reqMonth]) ?> <span><?= $reqYear ?></span>
            </h2>
            <a href="?m=<?= $nextDt->format('n') ?>&y=<?= $nextDt->format('Y') ?>#evt-view-calendar"
               class="evt-cal-nav" aria-label="<?= htmlspecialchars(corpo_t('evt.cal_next')) ?>">›</a>
          </div>

          <div class="evt-cal-grid">
            <?php foreach ($weekdayL as $n): ?>
              <div class="evt-cal-dayname"><?= htmlspecialchars($n) ?></div>
            <?php endforeach; ?>

            <?php for ($i = 1; $i < $firstDow; $i++): ?>
              <div class="evt-cal-cell evt-cal-cell--empty"></div>
            <?php endfor; ?>

            <?php for ($day = 1; $day <= $daysInMonth; $day++):
              $hasEv   = isset($calEvents[$day]);
              $isToday = $day === (int)(new DateTime())->format('j')
                      && $reqMonth === (int)(new DateTime())->format('n')
                      && $reqYear  === (int)(new DateTime())->format('Y');
              $cls = 'evt-cal-cell' . ($hasEv ? ' evt-cal-cell--has' : '') . ($isToday ? ' evt-cal-cell--today' : '');
            ?>
              <div class="<?= $cls ?>">
                <span class="evt-cal-num"><?= $day ?></span>
                <?php if ($hasEv): ?>
                  <div class="evt-cal-events">
                    <?php foreach (array_slice($calEvents[$day], 0, 3) as $ev): ?>
                      <a href="evenement.php?id=<?= (int)$ev['id'] ?>" class="evt-cal-pill"
                         style="--evt-color:<?= htmlspecialchars($ev['color'] ?? '#5D0282') ?>"
                         title="<?= htmlspecialchars($ev['titre']) ?>">
                        <span class="evt-cal-pill__ico" aria-hidden="true"><?= htmlspecialchars(evt_normalize_icon($ev['icon'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="evt-cal-pill__txt"><?= htmlspecialchars($ev['titre']) ?></span>
                      </a>
                    <?php endforeach; ?>
                    <?php if (count($calEvents[$day]) > 3): ?>
                      <span class="evt-cal-more">+ <?= count($calEvents[$day]) - 3 ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endfor; ?>

            <?php for ($i = $lastDow; $i < 7; $i++): ?>
              <div class="evt-cal-cell evt-cal-cell--empty"></div>
            <?php endfor; ?>
          </div>

          <!-- Mois rapides -->
          <?php
          $byMonth = [];
          foreach ($events as $ev) {
              $d = $ev['_dateObj'];
              $byMonth[$d->format('Y-n')] = ($byMonth[$d->format('Y-n')] ?? 0) + 1;
          }
          if (count($byMonth) > 1):
          ?>
            <div class="evt-cal-monthnav">
              <?php foreach ($byMonth as $key => $count):
                [$y, $m] = explode('-', $key);
                $isActive = (int)$m === $reqMonth && (int)$y === $reqYear;
              ?>
                <a href="?m=<?= $m ?>&y=<?= $y ?>#evt-view-calendar"
                   class="evt-cal-month-pill<?= $isActive ? ' active' : '' ?>">
                  <?= $monthNames[(int)$m] ?> <?= substr($y, 2) ?>
                  <span><?= $count ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
                      </div>
                    </div>

    </div>
  </section>

  <!-- CTA bas de page -->
  <section class="cta-section">
    <div class="container">
      <h2 class="cta-section__title"><?= htmlspecialchars(corpo_t('evt.cta_title')) ?></h2>
      <p class="cta-section__sub"><?= htmlspecialchars(corpo_t('evt.cta_sub')) ?></p>
      <div class="cta-section__actions">
        <?php if (!$userId): ?>
          <a href="register.php" class="btn btn--primary btn--lg"><?= htmlspecialchars(corpo_t('evt.cta_register')) ?></a>
        <?php else: ?>
          <a href="admin/index.php" class="btn btn--primary btn--lg"><?= htmlspecialchars(corpo_t('evt.cta_admin')) ?></a>
        <?php endif; ?>
        <a href="associations.php" class="btn btn--ghost btn--lg"><?= htmlspecialchars(corpo_t('evt.cta_assos')) ?></a>
      </div>
    </div>
  </section>

</main>

<script type="application/json" id="evt-i18n"><?= json_encode([
    'remove'       => corpo_t('evt.js.remove'),
    'clear_all'    => corpo_t('evt.js.clear_all'),
    'type'         => corpo_t('evt.js.type'),
    'school'       => corpo_t('evt.js.school'),
    'campus'       => corpo_t('evt.js.campus'),
    'status'       => corpo_t('evt.js.status'),
    'preset_today' => corpo_t('evt.js.preset_today'),
    'preset_week'  => corpo_t('evt.js.preset_week'),
    'preset_weekend' => corpo_t('evt.js.preset_weekend'),
    'preset_month' => corpo_t('evt.js.preset_month'),
    'status_open'  => corpo_t('evt.js.status_open'),
    'status_full'  => corpo_t('evt.js.status_full'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="js/evenements.js" defer></script>

<?php require_once 'includes/footer.php'; ?>
