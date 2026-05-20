<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/i18n.php';

$title = corpo_t('contact.meta_title');
$page  = 'contact';
require_once __DIR__ . '/includes/header.php';
?>

<main class="contact-page">
  <section class="page-hero">
    <div class="container">
      <nav class="breadcrumb" aria-label="<?= htmlspecialchars(corpo_t('apr.breadcrumb_aria')) ?>">
        <a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a>
        <span aria-hidden="true">›</span>
        <span><?= htmlspecialchars(corpo_t('contact.crumb')) ?></span>
      </nav>
      <h1><?= htmlspecialchars(corpo_t('contact.h1')) ?></h1>
      <p class="page-hero__sub"><?= htmlspecialchars(corpo_t('contact.sub')) ?></p>
    </div>
  </section>

  <section class="section">
    <div class="container contact-quick">
      <article class="pillar-card contact-quick__card">
        <div class="pillar-card__icon" aria-hidden="true">✉️</div>
        <h2><?= htmlspecialchars(corpo_t('contact.card_email_t')) ?></h2>
        <p><?= htmlspecialchars(corpo_t('contact.card_email_p')) ?></p>
        <p><a href="mailto:corpoomnes@gmail.com" class="btn btn--primary btn--sm">corpoomnes@gmail.com</a></p>
      </article>
      <article class="pillar-card contact-quick__card">
        <div class="pillar-card__icon" aria-hidden="true">📱</div>
        <h2><?= htmlspecialchars(corpo_t('contact.card_social_t')) ?></h2>
        <p><?= htmlspecialchars(corpo_t('contact.card_social_p')) ?></p>
        <p><a href="https://instagram.com/copro_omnes" target="_blank" rel="noopener" class="btn btn--ghost btn--sm">@copro_omnes</a></p>
      </article>
      <article class="pillar-card contact-quick__card">
        <div class="pillar-card__icon" aria-hidden="true">🏫</div>
        <h2><?= htmlspecialchars(corpo_t('contact.card_campus_t')) ?></h2>
        <p><?= htmlspecialchars(corpo_t('contact.card_campus_p')) ?></p>
        <ul class="contact-list">
          <li><strong>Citroën</strong> - <?= htmlspecialchars(corpo_t('footer.campus_citroen')) ?></li>
          <li><strong>Citadelle</strong> - <?= htmlspecialchars(corpo_t('footer.campus_citadelle')) ?></li>
        </ul>
      </article>
      <article class="pillar-card contact-quick__card">
        <div class="pillar-card__icon" aria-hidden="true">⏱️</div>
        <h2><?= htmlspecialchars(corpo_t('contact.card_delay_t')) ?></h2>
        <p><?= htmlspecialchars(corpo_t('contact.card_delay_p')) ?></p>
      </article>
    </div>
  </section>

  <section class="section section--alt">
    <div class="container">
      <h2 class="section-title"><?= htmlspecialchars(corpo_t('contact.links_title')) ?></h2>
      <p class="section-intro"><?= htmlspecialchars(corpo_t('contact.links_sub')) ?></p>
      <div class="contact-links-grid">
        <a href="guide-site.php" class="contact-link-card"><span>📖</span><strong><?= htmlspecialchars(corpo_t('nav.corpo_site_guide')) ?></strong><small><?= htmlspecialchars(corpo_t('contact.link_guide')) ?></small></a>
        <a href="evenements.php" class="contact-link-card"><span>📅</span><strong><?= htmlspecialchars(corpo_t('nav.events')) ?></strong><small><?= htmlspecialchars(corpo_t('contact.link_events')) ?></small></a>
        <a href="associations.php" class="contact-link-card"><span>🤝</span><strong><?= htmlspecialchars(corpo_t('nav.assos')) ?></strong><small><?= htmlspecialchars(corpo_t('contact.link_assos')) ?></small></a>
        <a href="boutique.php" class="contact-link-card"><span>🛍</span><strong><?= htmlspecialchars(corpo_t('nav.shop')) ?></strong><small><?= htmlspecialchars(corpo_t('contact.link_shop')) ?></small></a>
        <a href="admin/login.php" class="contact-link-card"><span>🔐</span><strong><?= htmlspecialchars(corpo_t('account.login')) ?></strong><small><?= htmlspecialchars(corpo_t('contact.link_login')) ?></small></a>
        <a href="register.php" class="contact-link-card"><span>✨</span><strong><?= htmlspecialchars(corpo_t('account.register')) ?></strong><small><?= htmlspecialchars(corpo_t('contact.link_register')) ?></small></a>
        <a href="forgot-password.php" class="contact-link-card"><span>🔑</span><strong><?= htmlspecialchars(corpo_t('contact.link_forgot')) ?></strong><small><?= htmlspecialchars(corpo_t('contact.link_forgot_sub')) ?></small></a>
        <a href="demande-partenariat.php" class="contact-link-card"><span>💼</span><strong><?= htmlspecialchars(corpo_t('nav.become_partner')) ?></strong><small><?= htmlspecialchars(corpo_t('contact.link_partner')) ?></small></a>
        <a href="proposer-asso.php" class="contact-link-card"><span>➕</span><strong><?= htmlspecialchars(corpo_t('contact.link_propose_asso')) ?></strong><small><?= htmlspecialchars(corpo_t('contact.link_propose_asso_sub')) ?></small></a>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container contact-faq">
      <h2 class="section-title section-title--center"><?= htmlspecialchars(corpo_t('contact.faq_title')) ?></h2>
      <p class="section-intro section-intro--center"><?= htmlspecialchars(corpo_t('contact.faq_sub')) ?></p>

      <div class="faq-accordion" data-faq-accordion>
        <?php
        $faqBlocks = [
            ['contact.faq_account_t', [
                'contact.faq_account_q1', 'contact.faq_account_a1',
                'contact.faq_account_q2', 'contact.faq_account_a2',
                'contact.faq_account_q3', 'contact.faq_account_a3',
            ]],
            ['contact.faq_evt_t', [
                'contact.faq_evt_q1', 'contact.faq_evt_a1',
                'contact.faq_evt_q2', 'contact.faq_evt_a2',
                'contact.faq_evt_q3', 'contact.faq_evt_a3',
                'contact.faq_evt_q4', 'contact.faq_evt_a4',
            ]],
            ['contact.faq_shop_t', [
                'contact.faq_shop_q1', 'contact.faq_shop_a1',
                'contact.faq_shop_q2', 'contact.faq_shop_a2',
            ]],
            ['contact.faq_asso_t', [
                'contact.faq_asso_q1', 'contact.faq_asso_a1',
                'contact.faq_asso_q2', 'contact.faq_asso_a2',
            ]],
            ['contact.faq_data_t', [
                'contact.faq_data_q1', 'contact.faq_data_a1',
                'contact.faq_data_q2', 'contact.faq_data_a2',
            ]],
            ['contact.faq_struct_t', [
                'contact.faq_struct_q1', 'contact.faq_struct_a1',
                'contact.faq_struct_q2', 'contact.faq_struct_a2',
            ]],
        ];
        foreach ($faqBlocks as [$blockTitle, $pairs]):
        ?>
        <div class="faq-block">
          <h3 class="faq-block__title"><?= htmlspecialchars(corpo_t($blockTitle)) ?></h3>
          <?php for ($i = 0; $i < count($pairs); $i += 2): ?>
            <details class="faq-item">
              <summary><?= htmlspecialchars(corpo_t($pairs[$i])) ?></summary>
              <div class="faq-item__body"><?= nl2br(htmlspecialchars(corpo_t($pairs[$i + 1]))) ?></div>
            </details>
          <?php endfor; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="admin-card" style="margin-top:var(--s8);text-align:center">
        <h3 style="margin:0 0 var(--s3)"><?= htmlspecialchars(corpo_t('contact.still_title')) ?></h3>
        <p style="color:var(--text-muted);margin-bottom:var(--s4)"><?= htmlspecialchars(corpo_t('contact.still_p')) ?></p>
        <a href="mailto:corpoomnes@gmail.com?subject=<?= rawurlencode(corpo_t('contact.mail_subject')) ?>" class="btn btn--primary"><?= htmlspecialchars(corpo_t('contact.still_btn')) ?></a>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
