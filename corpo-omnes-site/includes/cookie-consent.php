<?php
/**
 * includes/cookie-consent.php
 * Bannière + modale de consentement cookies RGPD.
 * À inclure depuis le footer (juste avant les scripts).
 *
 * Variables attendues (auto-définies si absentes) :
 *   $base       string  Préfixe URL ('' ou '../')
 */
if (!function_exists('corpo_t')) {
    require_once __DIR__ . '/i18n.php';
}
$base = $base ?? '';
?>
<!-- ╔═══════════════════════════════════════════════════════╗
     ║  COOKIE CONSENT - RGPD / CNIL                         ║
     ╚═══════════════════════════════════════════════════════╝ -->
<style>
/* Fallback critique : si style.css n'a pas encore été appliqué (cache,
   chargement asynchrone…), on garde la bannière correctement positionnée
   et masquée par défaut. */
.cc-root[hidden]{display:none!important}
.cc-banner,.cc-modal,.cc-overlay{position:fixed!important;box-sizing:border-box}
.cc-banner{left:16px;right:16px;bottom:16px;max-width:980px;margin-left:auto;margin-right:auto;z-index:9000;opacity:0;transform:translateY(20px);pointer-events:none;transition:opacity .35s ease,transform .35s ease}
.cc-banner.is-open{opacity:1;transform:translateY(0);pointer-events:auto}
.cc-overlay{inset:0;z-index:9100;opacity:0;transition:opacity .25s ease}
.cc-overlay.is-open{opacity:1}
.cc-modal{top:50%;left:50%;transform:translate(-50%,calc(-50% + 16px));opacity:0;z-index:9200;width:min(640px,calc(100vw - 32px));max-height:calc(100vh - 32px);overflow:hidden;transition:opacity .25s ease,transform .25s ease}
.cc-modal.is-open{opacity:1;transform:translate(-50%,-50%)}
.cc-overlay[hidden],.cc-modal[hidden]{display:none!important}
</style>
<div class="cc-root" data-cookie-root hidden>
  <!-- Bannière initiale -->
  <div class="cc-banner" data-cc-banner role="dialog" aria-labelledby="cc-banner-title" aria-describedby="cc-banner-desc" aria-modal="false">
    <div class="cc-banner__icon" aria-hidden="true">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 2a10 10 0 1 0 10 10c0-.46-.04-.92-.1-1.36a4 4 0 0 1-5.06-5.06A4 4 0 0 1 11.36 2.1 10 10 0 0 0 12 2z"></path>
        <circle cx="8.5" cy="10.5" r="1"></circle>
        <circle cx="15" cy="14" r="1"></circle>
        <circle cx="10" cy="15" r="1"></circle>
      </svg>
    </div>

    <div class="cc-banner__body">
      <h2 class="cc-banner__title" id="cc-banner-title"><?= htmlspecialchars(corpo_t('cookies.banner.title')) ?></h2>
      <p class="cc-banner__text" id="cc-banner-desc">
        <?= htmlspecialchars(corpo_t('cookies.banner.text')) ?>
        <a href="<?= $base ?>politique-cookies.php" class="cc-banner__link"><?= htmlspecialchars(corpo_t('cookies.link_policy')) ?></a>
      </p>
    </div>

    <div class="cc-banner__actions">
      <button type="button" class="cc-btn cc-btn--ghost" data-cc-action="refuse"><?= htmlspecialchars(corpo_t('cookies.banner.refuse_all')) ?></button>
      <button type="button" class="cc-btn cc-btn--ghost" data-cc-action="open"><?= htmlspecialchars(corpo_t('cookies.banner.customize')) ?></button>
      <button type="button" class="cc-btn cc-btn--primary" data-cc-action="accept"><?= htmlspecialchars(corpo_t('cookies.banner.accept_all')) ?></button>
    </div>
  </div>

  <!-- Modale détaillée -->
  <div class="cc-overlay" data-cc-overlay hidden></div>
  <div class="cc-modal" data-cc-modal role="dialog" aria-labelledby="cc-modal-title" aria-modal="true" hidden>
    <button type="button" class="cc-modal__close" data-cc-action="close" aria-label="<?= htmlspecialchars(corpo_t('cookies.modal.close_aria')) ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
      </svg>
    </button>

    <div class="cc-modal__head">
      <h2 class="cc-modal__title" id="cc-modal-title"><?= htmlspecialchars(corpo_t('cookies.modal.title')) ?></h2>
      <p class="cc-modal__intro"><?= htmlspecialchars(corpo_t('cookies.modal.intro')) ?></p>
    </div>

    <div class="cc-modal__list">
      <!-- Essentiel : forcé, non désactivable -->
      <article class="cc-cat">
        <header class="cc-cat__head">
          <div>
            <h3 class="cc-cat__title"><?= htmlspecialchars(corpo_t('cookies.cat.essential.title')) ?></h3>
            <p class="cc-cat__desc"><?= htmlspecialchars(corpo_t('cookies.cat.essential.desc')) ?></p>
          </div>
          <span class="cc-toggle cc-toggle--locked" role="img" aria-label="<?= htmlspecialchars(corpo_t('cookies.modal.always_on')) ?>">
            <span class="cc-toggle__pill"></span>
            <span class="cc-toggle__label"><?= htmlspecialchars(corpo_t('cookies.modal.always_on')) ?></span>
          </span>
        </header>
      </article>

      <!-- Préférences -->
      <article class="cc-cat">
        <header class="cc-cat__head">
          <div>
            <h3 class="cc-cat__title"><?= htmlspecialchars(corpo_t('cookies.cat.preferences.title')) ?></h3>
            <p class="cc-cat__desc"><?= htmlspecialchars(corpo_t('cookies.cat.preferences.desc')) ?></p>
          </div>
          <label class="cc-toggle">
            <input type="checkbox" data-cc-cat="preferences" class="cc-toggle__input">
            <span class="cc-toggle__pill"></span>
          </label>
        </header>
      </article>

      <!-- Audience / Analytics -->
      <article class="cc-cat">
        <header class="cc-cat__head">
          <div>
            <h3 class="cc-cat__title"><?= htmlspecialchars(corpo_t('cookies.cat.analytics.title')) ?></h3>
            <p class="cc-cat__desc"><?= htmlspecialchars(corpo_t('cookies.cat.analytics.desc')) ?></p>
          </div>
          <label class="cc-toggle">
            <input type="checkbox" data-cc-cat="analytics" class="cc-toggle__input">
            <span class="cc-toggle__pill"></span>
          </label>
        </header>
      </article>

      <!-- Marketing -->
      <article class="cc-cat">
        <header class="cc-cat__head">
          <div>
            <h3 class="cc-cat__title"><?= htmlspecialchars(corpo_t('cookies.cat.marketing.title')) ?></h3>
            <p class="cc-cat__desc"><?= htmlspecialchars(corpo_t('cookies.cat.marketing.desc')) ?></p>
          </div>
          <label class="cc-toggle">
            <input type="checkbox" data-cc-cat="marketing" class="cc-toggle__input">
            <span class="cc-toggle__pill"></span>
          </label>
        </header>
      </article>
    </div>

    <footer class="cc-modal__footer">
      <a href="<?= $base ?>politique-cookies.php" class="cc-modal__link"><?= htmlspecialchars(corpo_t('cookies.link_policy')) ?></a>
      <div class="cc-modal__actions">
        <button type="button" class="cc-btn cc-btn--ghost" data-cc-action="refuse"><?= htmlspecialchars(corpo_t('cookies.banner.refuse_all')) ?></button>
        <button type="button" class="cc-btn cc-btn--primary" data-cc-action="save"><?= htmlspecialchars(corpo_t('cookies.modal.save')) ?></button>
      </div>
    </footer>
  </div>
</div>
