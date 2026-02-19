/**
 * Diárias Village Mobile – Navigation & PWA helpers
 */
(function () {
  'use strict';

  // Maps data-go values to /mobile/?r= routes (stays inside /mobile)
  var APP_ROUTES = {
    tela_login:     '/mobile/?r=login',
    tela_cadastro:  '/mobile/?r=primeiro-acesso',
    grade_oficinas: '/mobile/?r=grade',
    resumo_pedido:  '/mobile/?r=resumo'
  };

  function navigate(page) {
    if (APP_ROUTES[page]) {
      window.location.href = APP_ROUTES[page];
      return;
    }
    // Fallback: legacy pages
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
