/**
 * @file
 * SCDC mobile navigation toggle.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.scdcNavigation = {
    attach: function (context) {
      once('scdc-nav-toggle', '.primary-nav__toggle', context).forEach(function (toggle) {
        var nav = toggle.closest('.primary-nav');

        toggle.addEventListener('click', function () {
          var expanded = toggle.getAttribute('aria-expanded') === 'true';
          toggle.setAttribute('aria-expanded', !expanded);
          nav.classList.toggle('is-open');
        });

        // Close menu on escape key
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && nav.classList.contains('is-open')) {
            nav.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
          }
        });
      });
    }
  };

})(Drupal, once);
