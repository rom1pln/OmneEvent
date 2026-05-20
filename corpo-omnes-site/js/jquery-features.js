

$(function () {

  const $toggle = $('#nav-toggle');
  const $menu   = $('#nav-menu');

  function closeAllDropdowns() {
    $('.nav__item--dropdown.open').removeClass('open')
      .find('.nav__dropdown-toggle').attr('aria-expanded', 'false');
  }

  let scrollYBeforeNav = 0;

  function openBurger() {
    scrollYBeforeNav = window.scrollY || 0;
    $('body').addClass('nav-open').css({
      position: 'fixed',
      top: -scrollYBeforeNav + 'px',
      left: 0,
      right: 0,
      width: '100%'
    });
    $menu.addClass('open');
    $toggle.addClass('open').attr('aria-expanded', 'true');
  }
  function closeBurger() {
    $menu.removeClass('open');
    $toggle.removeClass('open').attr('aria-expanded', 'false');
    closeAllDropdowns();
    if ($('body').hasClass('nav-open')) {
      $('body').removeClass('nav-open').css({ position: '', top: '', left: '', right: '', width: '' });
      window.scrollTo(0, scrollYBeforeNav);
    }
  }

  $toggle.on('click', function (e) {
    e.stopPropagation();
    if ($menu.hasClass('open')) {
      closeBurger();
    } else {
      openBurger();
    }
  });

  $menu.on('click', 'a', function () {
    closeBurger();
  });

  $(document).on('click', function (e) {
    if (!$toggle.length) return;
    if (!$(e.target).closest('.nav').length) {
      closeBurger();
    }
  });

  $(window).on('resize.navlock', function () {
    if (window.matchMedia('(min-width: 769px)').matches) {
      closeBurger();
    }
  });

  const $backTop = $('<button>', {
    id:           'back-top',
    class:        'back-top',
    html:         '↑',
    'aria-label': 'Retour en haut de page'
  }).appendTo('body');

  $(window).on('scroll.backtop', function () {
    $backTop.toggleClass('visible', $(this).scrollTop() > 300);
  });

  $backTop.on('click', function () {
    $('html, body').animate({ scrollTop: 0 }, 400);
  });

  $(document).on('click', '.nav__dropdown-toggle', function (e) {
    e.stopPropagation();
    const $btn    = $(this);
    const $item   = $btn.closest('.nav__item--dropdown');
    const wasOpen = $item.hasClass('open');

    $('.nav__item--dropdown').not($item).removeClass('open')
      .find('.nav__dropdown-toggle').attr('aria-expanded', 'false');

    $item.toggleClass('open', !wasOpen);
    $btn.attr('aria-expanded', String(!wasOpen));
  });

  $(document).on('click', function (e) {
    if ($(e.target).closest('.nav__item--dropdown').length) return;
    $('.nav__item--dropdown.open').removeClass('open')
      .find('.nav__dropdown-toggle').attr('aria-expanded', 'false');
  });

  $(document).on('keydown', function (e) {
    if (e.key !== 'Escape') return;
    $('.nav__item--dropdown.open').removeClass('open')
      .find('.nav__dropdown-toggle').attr('aria-expanded', 'false');
    if ($menu.hasClass('open')) closeBurger();
  });

  const $carousel = $('#campus-carousel');

  if ($carousel.length) {
    const $track  = $carousel.find('.carousel__track');
    const $slides = $carousel.find('.carousel__slide');
    const total   = $slides.length;
    let   current = 0;

    const $dots = $carousel.find('.carousel__dots');
    for (let i = 0; i < total; i++) {
      $('<button>').addClass('carousel__dot' + (i === 0 ? ' carousel__dot--active' : ''))
        .attr('aria-label', 'Slide ' + (i + 1))
        .appendTo($dots);
    }

    function goTo(idx) {

      if (idx < 0)     idx = total - 1;
      if (idx >= total) idx = 0;
      current = idx;

      const offset = -current * 100;

      $track.css('transform', 'translateX(' + offset + '%)');

      $dots.find('.carousel__dot').removeClass('carousel__dot--active')
        .eq(current).addClass('carousel__dot--active');
    }

    $carousel.find('.carousel__btn--prev').on('click', function () { goTo(current - 1); });
    $carousel.find('.carousel__btn--next').on('click', function () { goTo(current + 1); });
    $dots.on('click', '.carousel__dot', function () {
      goTo($(this).index());
    });

    let autoTimer = setInterval(function () { goTo(current + 1); }, 5000);
    $carousel.on('mouseenter', function () { clearInterval(autoTimer); });
    $carousel.on('mouseleave', function () { autoTimer = setInterval(function () { goTo(current + 1); }, 5000); });
  }

  const $lightbox = $('#lightbox');

  $(document).on('click', '.lightbox-trigger', function (e) {
    e.preventDefault();
    const bigSrc = $(this).attr('href');
    const alt    = $(this).find('img').attr('alt') || '';
    $lightbox.find('.lightbox__img').attr({ src: bigSrc, alt: alt });
    $lightbox.removeAttr('hidden').hide().fadeIn(200);
    $('body').css('overflow', 'hidden');
  });

  function closeLightbox() {
    $lightbox.fadeOut(200, function () { $(this).attr('hidden', ''); });
    $('body').css('overflow', '');
  }

  $lightbox.on('click', '.lightbox__close', closeLightbox);
  $lightbox.on('click', function (e) {
    if ($(e.target).is($lightbox)) { closeLightbox(); }
  });
  $(document).on('keydown', function (e) {
    if (e.key === 'Escape' && !$lightbox.attr('hidden')) { closeLightbox(); }
  });

  $(document).on('click', '.faq-item__trigger', function () {
    const $item  = $(this).closest('.faq-item');
    const $body  = $item.find('.faq-item__body');
    const isOpen = $item.hasClass('open');

    $('.faq-item.open').not($item).each(function () {
      $(this).removeClass('open')
             .find('.faq-item__body').slideUp(250);
      $(this).find('.faq-item__trigger').attr('aria-expanded', 'false');
    });

    if (isOpen) {
      $item.removeClass('open');
      $body.slideUp(250);
      $(this).attr('aria-expanded', 'false');
    } else {
      $item.addClass('open');
      $body.slideDown(250);
      $(this).attr('aria-expanded', 'true');
    }
  });

});
