(function () {
  'use strict';

  var toggle = document.getElementById('mobile-menu-toggle');
  var menu = document.getElementById('mobile-menu');
  var iconOpen = document.getElementById('menu-icon-open');
  var iconClose = document.getElementById('menu-icon-close');
  var yearEl = document.getElementById('current-year');

  if (yearEl) {
    yearEl.textContent = String(new Date().getFullYear());
  }

  if (!toggle || !menu) {
    return;
  }

  function setMenuOpen(isOpen) {
    menu.classList.toggle('hidden', !isOpen);
    if (iconOpen) iconOpen.classList.toggle('hidden', isOpen);
    if (iconClose) iconClose.classList.toggle('hidden', !isOpen);
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    document.body.classList.toggle('overflow-hidden', isOpen);
  }

  toggle.addEventListener('click', function () {
    setMenuOpen(menu.classList.contains('hidden'));
  });

  menu.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', function () {
      setMenuOpen(false);
    });
  });
})();
