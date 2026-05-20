<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/i18n.php';
$title = corpo_t('guide.meta_title');
$page  = 'guide-site';
$pageStyles = ['css/guide-page.css'];
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/header.php';

function guide_icon_svg(string $name): string {
    $a = 'width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"';
    return match ($name) {
        'sparkles' => "<svg $a aria-hidden=\"true\"><path d=\"M12 3v2M12 19v2M3 12h2M19 12h2\"/><path d=\"m18.364 5.636-1.414 1.414M7.05 16.95l-1.414 1.414M5.636 5.636l1.414 1.414M16.95 16.95l1.414 1.414\"/><circle cx=\"12\" cy=\"12\" r=\"4\"/></svg>",
        'calendar' => "<svg $a aria-hidden=\"true\"><rect x=\"3\" y=\"4\" width=\"18\" height=\"18\" rx=\"2\" ry=\"2\"/><line x1=\"16\" y1=\"2\" x2=\"16\" y2=\"6\"/><line x1=\"8\" y1=\"2\" x2=\"8\" y2=\"6\"/><line x1=\"3\" y1=\"10\" x2=\"21\" y2=\"10\"/></svg>",
        'users' => "<svg $a aria-hidden=\"true\"><path d=\"M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2\"/><circle cx=\"9\" cy=\"7\" r=\"4\"/><path d=\"M23 21v-2a4 4 0 0 0-3-3.87\"/><path d=\"M16 3.13a4 4 0 0 1 0 7.75\"/></svg>",
        'trophy' => "<svg $a aria-hidden=\"true\"><circle cx=\"12\" cy=\"8\" r=\"6\"/><path d=\"M15.477 12.89 17 22l-5-3-5 3 1.523-9.11\"/></svg>",
        'briefcase' => "<svg $a aria-hidden=\"true\"><rect x=\"2\" y=\"7\" width=\"20\" height=\"14\" rx=\"2\" ry=\"2\"/><path d=\"M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16\"/></svg>",
        'bag' => "<svg $a aria-hidden=\"true\"><path d=\"M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z\"/><line x1=\"3\" y1=\"6\" x2=\"21\" y2=\"6\"/><path d=\"M16 10a4 4 0 0 1-8 0\"/></svg>",
        'user' => "<svg $a aria-hidden=\"true\"><path d=\"M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2\"/><circle cx=\"12\" cy=\"7\" r=\"4\"/></svg>",
        'sliders' => "<svg $a aria-hidden=\"true\"><line x1=\"4\" y1=\"21\" x2=\"4\" y2=\"14\"/><line x1=\"4\" y1=\"10\" x2=\"4\" y2=\"3\"/><line x1=\"12\" y1=\"21\" x2=\"12\" y2=\"12\"/><line x1=\"12\" y1=\"8\" x2=\"12\" y2=\"3\"/><line x1=\"20\" y1=\"21\" x2=\"20\" y2=\"16\"/><line x1=\"20\" y1=\"12\" x2=\"20\" y2=\"3\"/><line x1=\"1\" y1=\"14\" x2=\"7\" y2=\"14\"/><line x1=\"9\" y1=\"8\" x2=\"15\" y2=\"8\"/><line x1=\"17\" y1=\"16\" x2=\"23\" y2=\"16\"/></svg>",
        'scale' => "<svg $a aria-hidden=\"true\"><path d=\"m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z\"/><path d=\"m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z\"/><path d=\"M7 21h10\"/><path d=\"M12 3v18\"/><path d=\"M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2\"/></svg>",
        default => "<svg $a aria-hidden=\"true\"><circle cx=\"12\" cy=\"12\" r=\"10\"/></svg>",
    };
}

$guideSections = [
    ['id' => 'portail', 'toc' => 'guide.toc_portail', 'icon' => 'sparkles', 't' => 'guide.intro_t', 'p' => 'guide.intro_p', 'layout' => 'hero'],
    ['id' => 'evenements', 'toc' => 'guide.toc_evt', 'icon' => 'calendar', 't' => 'guide.evt_t', 'p' => 'guide.evt_p', 'layout' => 'half'],
    ['id' => 'associations', 'toc' => 'guide.toc_asso', 'icon' => 'users', 't' => 'guide.asso_t', 'p' => 'guide.asso_p', 'layout' => 'half'],
    ['id' => 'sport', 'toc' => 'guide.toc_sport', 'icon' => 'trophy', 't' => 'guide.sport_t', 'p' => 'guide.sport_p', 'layout' => 'half'],
    ['id' => 'partenaires', 'toc' => 'guide.toc_pt', 'icon' => 'briefcase', 't' => 'guide.pt_t', 'p' => 'guide.pt_p', 'layout' => 'half'],
    ['id' => 'boutique', 'toc' => 'guide.toc_shop', 'icon' => 'bag', 't' => 'guide.shop_t', 'p' => 'guide.shop_p', 'layout' => 'half'],
    ['id' => 'compte', 'toc' => 'guide.toc_account', 'icon' => 'user', 't' => 'guide.account_t', 'p' => 'guide.account_p', 'layout' => 'half'],
    ['id' => 'admin', 'toc' => 'guide.toc_admin', 'icon' => 'sliders', 't' => 'guide.admin_t', 'p' => 'guide.admin_p', 'layout' => 'admin'],
    ['id' => 'legal', 'toc' => 'guide.toc_legal', 'icon' => 'scale', 't' => 'guide.legal_t', 'p' => 'guide.legal_p', 'layout' => 'legal'],
];

$idx = 0;
?>

<main class="guide-page">
  <section class="page-hero">
    <div class="container">
      <nav class="breadcrumb" aria-label="<?= htmlspecialchars(corpo_t('apr.breadcrumb_aria')) ?>">
        <a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a>
        <span aria-hidden="true">›</span>
        <a href="apropos.php"><?= htmlspecialchars(corpo_t('nav.corpo')) ?></a>
        <span aria-hidden="true">›</span>
        <span><?= htmlspecialchars(corpo_t('guide.crumb')) ?></span>
      </nav>

      <div class="guide-page__hero-grid">
        <div>
          <span class="section-label"><?= htmlspecialchars(corpo_t('guide.hero_eyebrow')) ?></span>
          <h1><?= htmlspecialchars(corpo_t('guide.hero_h1')) ?></h1>
          <p class="page-hero__sub"><?= htmlspecialchars(corpo_t('guide.hero_sub')) ?></p>
          <div class="guide-page__tags" role="list">
            <span class="tag tag--corpo" role="listitem"><?= htmlspecialchars(corpo_t('guide.chip_schools')) ?></span>
            <span class="tag tag--bde" role="listitem"><?= htmlspecialchars(corpo_t('guide.chip_campus')) ?></span>
            <span class="evt-badge evt-badge--campus" role="listitem"><?= htmlspecialchars(corpo_t('guide.chip_langs')) ?></span>
          </div>
        </div>
        <div class="guide-page__mockups" aria-hidden="true">
          <div class="guide-page__mock-card">
            <div class="guide-page__mock-bar"></div>
            <div class="guide-page__mock-lines"><span></span><span></span><span></span></div>
          </div>
          <div class="guide-page__mock-card">
            <div class="guide-page__mock-bar" style="max-width:45%"></div>
            <div class="guide-page__mock-lines"><span></span><span></span></div>
          </div>
          <div class="guide-page__mock-card">
            <div class="guide-page__mock-bar" style="max-width:55%"></div>
            <div class="guide-page__mock-lines"><span></span><span></span><span></span></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <nav class="evt-filterbar guide-page__toc" aria-label="<?= htmlspecialchars(corpo_t('guide.toc_aria')) ?>">
    <div class="container evt-filterbar__row">
      <span class="evt-filter-label"><?= htmlspecialchars(corpo_t('guide.toc_title')) ?></span>
      <div class="evt-chips">
        <?php foreach ($guideSections as $s): ?>
          <a class="evt-chip" href="#guide-<?= htmlspecialchars($s['id']) ?>"><?= htmlspecialchars(corpo_t($s['toc'])) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </nav>

  <section class="section">
    <div class="container">
      <div class="guide-bento">
        <?php foreach ($guideSections as $s):
            $idx++;
            $layout = $s['layout'];
            $cardClass = 'pillar-card guide-page__card';
            if ($layout === 'hero') {
                $cardClass .= ' guide-page__card--full';
            } elseif ($layout === 'half') {
                $cardClass .= ' guide-page__card--half';
            } elseif ($layout === 'admin') {
                $cardClass .= ' guide-page__card--full guide-page__card--admin';
            } elseif ($layout === 'legal') {
                $cardClass .= ' guide-page__card--full guide-page__card--legal';
            }
            $num = str_pad((string)$idx, 2, '0', STR_PAD_LEFT);
            ?>
        <article id="guide-<?= htmlspecialchars($s['id']) ?>" class="<?= htmlspecialchars($cardClass) ?>">
          <span class="guide-page__num" aria-hidden="true"><?= $num ?></span>
          <div class="pillar-card__icon"><?= guide_icon_svg($s['icon']) ?></div>
          <h3><?= htmlspecialchars(corpo_t($s['t'])) ?></h3>
          <p><?= htmlspecialchars(corpo_t($s['p'])) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section section--alt">
    <div class="container guide-page__cta-inner">
      <h2 class="section-title section-title--center" id="guide-cta-title"><?= htmlspecialchars(corpo_t('guide.cta_title')) ?></h2>
      <p class="section-intro"><?= htmlspecialchars(corpo_t('guide.cta_sub')) ?></p>
      <div class="hero__actions">
        <a href="evenements.php" class="btn btn--primary"><?= htmlspecialchars(corpo_t('guide.cta_btn_evt')) ?></a>
        <a href="boutique.php" class="btn btn--ghost"><?= htmlspecialchars(corpo_t('guide.cta_btn_shop')) ?></a>
        <a href="apropos.php" class="btn btn--outline"><?= htmlspecialchars(corpo_t('guide.cta_btn_corpo')) ?></a>
      </div>
      <p style="margin-top:var(--s8)">
        <a href="apropos.php" class="btn btn--ghost btn--sm"><?= htmlspecialchars(corpo_t('nav.corpo_mission')) ?></a>
      </p>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
