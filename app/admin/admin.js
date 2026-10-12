/* Potters School - espace de gestion : confirmations, menu mobile, lignes cliquables. */
(function () {
  'use strict';
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });
  document.querySelectorAll('tr[data-href]').forEach(function (tr) {
    tr.addEventListener('click', function (e) { if (!e.target.closest('a,button,input,select')) location.href = tr.getAttribute('data-href'); });
  });
  var b = document.getElementById('burger');
  if (b) b.addEventListener('click', function () { document.body.classList.toggle('nav-open'); });
  // Prévenir avant l'expiration de la session (30 minutes sans activité).
  if (document.querySelector('.shell')) {
    setTimeout(function () {
      var p = document.createElement('p');
      p.className = 'alert err';
      p.textContent = 'Votre session expire bientôt. Enregistrez votre travail.';
      var m = document.querySelector('.main h1');
      if (m) m.insertAdjacentElement('afterend', p);
    }, 27 * 60 * 1000);
  }
})();
