<?php
/* includes/footer.php - Pied de page commun + scripts JS */
$base = $base ?? '';
if (!function_exists('corpo_current_lang')) {
    require_once __DIR__ . '/i18n.php';
}
$hereLang = $_SERVER['REQUEST_URI'] ?? '/';
$langFrUrl = $base . 'set-lang.php?lang=fr&redirect=' . urlencode($hereLang);
$langEnUrl = $base . 'set-lang.php?lang=en&redirect=' . urlencode($hereLang);
$curLang = corpo_current_lang();
?>

  <footer class="footer">
    <div class="container">
      <div class="footer__grid">
        <div class="footer__col">
          <img src="<?= $base ?>images/logo-corpo-omnes.png" alt="" class="footer__logo" width="42" height="42">
          <p class="footer__text"><?= htmlspecialchars(corpo_t('footer.tagline')) ?></p>
        </div>
        <div class="footer__col">
          <h3 class="footer__heading"><?= htmlspecialchars(corpo_t('footer.campus')) ?></h3>
          <ul class="footer__list">
            <li><?= htmlspecialchars(corpo_t('footer.campus_citroen')) ?></li>
            <li><?= htmlspecialchars(corpo_t('footer.campus_citadelle')) ?></li>
          </ul>
        </div>
        <div class="footer__col">
          <h3 class="footer__heading"><?= htmlspecialchars(corpo_t('footer.nav')) ?></h3>
          <ul class="footer__list">
            <li><a href="<?= $base ?>index.php"><?= htmlspecialchars(corpo_t('nav.home')) ?></a></li>
            <li><a href="<?= $base ?>apropos.php"><?= htmlspecialchars(corpo_t('nav.corpo_mission')) ?></a></li>
            <li><a href="<?= $base ?>guide-site.php"><?= htmlspecialchars(corpo_t('nav.corpo_site_guide')) ?></a></li>
            <li><a href="<?= $base ?>associations.php"><?= htmlspecialchars(corpo_t('nav.assos')) ?></a></li>
            <li><a href="<?= $base ?>evenements.php"><?= htmlspecialchars(corpo_t('nav.events')) ?></a></li>
            <li><a href="<?= $base ?>sport.php"><?= htmlspecialchars(corpo_t('nav.sport')) ?></a></li>
            <li><a href="<?= $base ?>partenaires.php"><?= htmlspecialchars(corpo_t('nav.partners')) ?></a></li>
          </ul>
        </div>
        <div class="footer__col">
          <h3 class="footer__heading"><?= htmlspecialchars(corpo_t('footer.contact')) ?></h3>
          <ul class="footer__list">
            <li><a href="<?= $base ?>contact.php"><?= htmlspecialchars(corpo_t('footer.help_contact')) ?></a></li>
            <li><a href="mailto:corpoomnes@gmail.com">corpoomnes@gmail.com</a></li>
            <li><a href="https://instagram.com/copro_omnes" target="_blank" rel="noopener">@copro_omnes</a></li>
          </ul>
        </div>
        <div class="footer__col">
          <h3 class="footer__heading"><?= htmlspecialchars(corpo_t('footer.legal_heading')) ?></h3>
          <ul class="footer__list">
            <li><a href="<?= $base ?>mentions-legales.php"><?= htmlspecialchars(corpo_t('footer.legal_mentions')) ?></a></li>
            <li><a href="<?= $base ?>politique-confidentialite.php"><?= htmlspecialchars(corpo_t('footer.legal_confid')) ?></a></li>
            <li><a href="<?= $base ?>politique-cookies.php"><?= htmlspecialchars(corpo_t('footer.legal_cookies')) ?></a></li>
            <li><a href="<?= $base ?>cgv.php"><?= htmlspecialchars(corpo_t('footer.legal_cgv')) ?></a></li>
            <li><a href="<?= $base ?>cgu.php"><?= htmlspecialchars(corpo_t('footer.legal_cgu')) ?></a></li>
            <li><button type="button" class="footer__link-btn" data-cookie-pref><?= htmlspecialchars(corpo_t('legal.cookie_pref_label')) ?></button></li>
          </ul>
        </div>
      </div>
      <div class="footer__bottom">
        <div class="footer__bottom-left">
          <span>© <?= date('Y') ?> <?= htmlspecialchars(corpo_t('footer.copyright')) ?></span>
          <div class="footer__lang" role="group" aria-label="<?= htmlspecialchars(corpo_t('footer.lang')) ?>">
            <span class="footer__lang-label"><?= htmlspecialchars(corpo_t('footer.lang')) ?></span>
            <a href="<?= htmlspecialchars($langFrUrl) ?>" class="footer__lang-link<?= $curLang === 'fr' ? ' is-active' : '' ?>" hreflang="fr" lang="fr">FR</a>
            <span class="footer__lang-sep" aria-hidden="true">·</span>
            <a href="<?= htmlspecialchars($langEnUrl) ?>" class="footer__lang-link<?= $curLang === 'en' ? ' is-active' : '' ?>" hreflang="en" lang="en">EN</a>
          </div>
        </div>
        <div class="footer__schools">
          <span class="tag tag--ece">ECE</span>
          <span class="tag tag--esce">ESCE</span>
          <span class="tag tag--heip">HEIP</span>
          <span class="tag tag--inseec">INSEEC</span>
          <span class="tag tag--sup">Sup de Pub</span>
        </div>
      </div>
    </div>
  </footer>

  <?php
    // Bannière / modale de consentement cookies (RGPD)
    if (!isset($disableCookieConsent) || !$disableCookieConsent) {
        require_once __DIR__ . '/cookie-consent.php';
    }
  ?>

  <?php
    $jsCookiePath = __DIR__ . '/../js/cookie-consent.js';
    $jsCookieVer  = file_exists($jsCookiePath) ? filemtime($jsCookiePath) : '1';
  ?>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="<?= $base ?>js/jquery-features.js"></script>
  <script src="<?= $base ?>js/cookie-consent.js?v=<?= $jsCookieVer ?>"></script>
  <script src="<?= $base ?>js/app.js"></script>
  <?php
    $jsSearchPath = __DIR__ . '/../js/global-search.js';
    $jsSearchVer  = file_exists($jsSearchPath) ? filemtime($jsSearchPath) : '1';
  ?>
  <script src="<?= $base ?>js/global-search.js?v=<?= $jsSearchVer ?>"></script>
  <?php if (!empty($extraScripts)): ?>
    <?php foreach ($extraScripts as $s): ?>
      <script src="<?= $base . htmlspecialchars($s) ?>"></script>
    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>
