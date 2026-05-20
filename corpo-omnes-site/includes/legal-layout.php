<?php
/**
 * includes/legal-layout.php
 * Layout commun à toutes les pages légales (mentions, confidentialité, cookies, CGV, CGU).
 *
 * Variables attendues avant l'inclusion :
 *   $legalKey        string  Clé i18n racine : 'legal.mentions' | 'legal.confid' | 'legal.cookies' | 'legal.cgv' | 'legal.cgu'
 *   $legalPage       string  Slug page actuelle pour $page (ex: 'mentions-legales')
 *   $legalUpdated    string  Date de dernière mise à jour (format affiché)
 *   $legalToc        array   [ ['id' => 'sec-1', 'label' => 'Titre 1'], ... ]
 *   $legalContent    string  HTML du contenu (sections <section id="sec-1"><h2>…</h2><p>…</p></section>)
 *   $legalRelated    array?  [ ['href' => 'cgu.php', 'label' => 'CGU'], ... ] - optionnel
 */
require_once __DIR__ . '/i18n.php';

$title = corpo_t($legalKey . '.meta_title');
$page  = $legalPage ?? 'legal';

require_once __DIR__ . '/header.php';
?>

<main>
  <section class="page-hero legal-hero">
    <div class="container">
      <nav class="breadcrumb" aria-label="<?= htmlspecialchars(corpo_t('apr.breadcrumb_aria')) ?>">
        <a href="<?= $base ?>index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a>
        <span aria-hidden="true">›</span>
        <span><?= htmlspecialchars(corpo_t('legal.crumb')) ?></span>
        <span aria-hidden="true">›</span>
        <span><?= htmlspecialchars(corpo_t($legalKey . '.meta_title')) ?></span>
      </nav>
      <span class="legal-eyebrow"><?= htmlspecialchars(corpo_t('legal.crumb')) ?></span>
      <h1><?= htmlspecialchars(corpo_t($legalKey . '.meta_title')) ?></h1>
      <p class="page-hero__sub"><?= htmlspecialchars(corpo_t($legalKey . '.hero_sub')) ?></p>
      <?php if (!empty($legalUpdated)): ?>
        <p class="legal-updated">
          <span class="legal-updated__label"><?= htmlspecialchars(corpo_t('legal.updated_on')) ?></span>
          <span class="legal-updated__value"><?= htmlspecialchars($legalUpdated) ?></span>
        </p>
      <?php endif; ?>
    </div>
  </section>

  <section class="legal-section">
    <div class="container legal-layout">

      <?php if (!empty($legalToc)): ?>
        <aside class="legal-toc" aria-label="<?= htmlspecialchars(corpo_t('legal.toc_title')) ?>">
          <h2 class="legal-toc__title"><?= htmlspecialchars(corpo_t('legal.toc_title')) ?></h2>
          <ol class="legal-toc__list">
            <?php foreach ($legalToc as $item): ?>
              <li><a href="#<?= htmlspecialchars($item['id']) ?>"><?= htmlspecialchars($item['label']) ?></a></li>
            <?php endforeach; ?>
          </ol>

          <?php if (!empty($legalRelated)): ?>
            <div class="legal-toc__related">
              <h3 class="legal-toc__related-title"><?= htmlspecialchars(corpo_t('legal.related_title')) ?></h3>
              <ul>
                <?php foreach ($legalRelated as $r): ?>
                  <li><a href="<?= htmlspecialchars($r['href']) ?>"><?= htmlspecialchars($r['label']) ?></a></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <div class="legal-toc__pref">
            <button type="button" class="btn btn--ghost btn--sm" data-cookie-pref>
              <?= htmlspecialchars(corpo_t('legal.cookie_pref_label')) ?>
            </button>
          </div>
        </aside>
      <?php endif; ?>

      <article class="legal-prose">
        <?= $legalContent ?? '' ?>

        <div class="legal-contact-block">
          <h2><?= htmlspecialchars(corpo_t('legal.contact_block_title')) ?></h2>
          <p>
            <?= htmlspecialchars(corpo_t('legal.contact_block_text')) ?>
            <a href="mailto:corpoomnes@gmail.com">corpoomnes@gmail.com</a>.
          </p>
        </div>
      </article>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>
