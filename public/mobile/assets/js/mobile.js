/**
 * Diárias Village Mobile – Navigation & PWA helpers
 */
(function () {
  'use strict';

  function navigate(page) {
    window.location.href = '/mobile/?page=' + encodeURIComponent(page);
  }

  document.addEventListener('click', function (e) {
    var target = e.target.closest('[data-go]');
    if (!target) return;

    e.preventDefault();
    var page = target.getAttribute('data-go');
    if (page) navigate(page);
  });

  // Register Service Worker
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/mobile/sw.js', { scope: '/mobile/' })
        .catch(function () { /* SW registration failed – offline won't work */ });
    });
  }
})();
