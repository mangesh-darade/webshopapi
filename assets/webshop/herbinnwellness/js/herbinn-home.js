/**
 * Herbinn marketing site — navbar scroll + hero slider (matches herbinnwellness.com).
 */
(function () {
  'use strict';

  var nav = document.getElementById('mainNav');
  if (nav) {
    window.addEventListener('scroll', function () {
      nav.classList.toggle('scrolled', window.scrollY > 50);
    }, { passive: true });

    var toggle = document.getElementById('navToggle');
    var links = document.getElementById('navLinks');
    if (toggle && links) {
      toggle.addEventListener('click', function () {
        toggle.classList.toggle('open');
        links.classList.toggle('open');
      });
      links.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () {
          toggle.classList.remove('open');
          links.classList.remove('open');
        });
      });
    }
  }

  var slider = document.getElementById('homeHero');
  if (!slider) {
    return;
  }

  var slides = slider.querySelectorAll('.hero-slide');
  if (!slides.length) {
    return;
  }

  var current = 0;
  var intervalId;
  var dotsContainer = document.getElementById('sliderDots');
  var dots = [];

  slides.forEach(function (_, i) {
    if (!dotsContainer) {
      return;
    }
    var dot = document.createElement('div');
    dot.className = 'dot' + (i === 0 ? ' active' : '');
    dot.addEventListener('click', function () {
      current = i;
      showSlide(current);
      resetInterval();
    });
    dotsContainer.appendChild(dot);
    dots.push(dot);
  });

  function showSlide(index) {
    slides.forEach(function (slide, i) {
      slide.classList.toggle('active', i === index);
    });
    dots.forEach(function (dot, i) {
      dot.classList.toggle('active', i === index);
    });
  }

  function nextSlide() {
    current = (current + 1) % slides.length;
    showSlide(current);
  }

  function resetInterval() {
    clearInterval(intervalId);
    intervalId = setInterval(nextSlide, 8000);
  }

  showSlide(0);
  resetInterval();

  var touchStartX = 0;
  slider.addEventListener('touchstart', function (e) {
    touchStartX = e.changedTouches[0].screenX;
  }, { passive: true });

  slider.addEventListener('touchend', function (e) {
    var touchEndX = e.changedTouches[0].screenX;
    if (touchEndX < touchStartX - 40) {
      nextSlide();
      resetInterval();
    } else if (touchEndX > touchStartX + 40) {
      current = (current - 1 + slides.length) % slides.length;
      showSlide(current);
      resetInterval();
    }
  }, { passive: true });
})();
