/**
 * @file
 * SCDC theme global JavaScript behaviours.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Skip link focus fix.
   */
  Drupal.behaviors.scdcGlobal = {
    attach: function (context) {
      once('scdc-skip-link', '.skip-link', context).forEach(function (el) {
        el.addEventListener('click', function () {
          var target = document.getElementById('main-content-anchor');
          if (target) {
            target.focus();
          }
        });
      });
    }
  };

  /**
   * Mega-menu navigation.
   */
  Drupal.behaviors.scdcMegaMenu = {
    attach: function (context) {
      once('scdc-mega-menu', '.main-nav__item--has-children', context).forEach(function (item) {
        var button = item.querySelector('.main-nav__link');
        var dropdown = item.querySelector('.main-nav__dropdown');

        if (!button || !dropdown) return;

        // Toggle dropdown
        button.addEventListener('click', function () {
          var expanded = button.getAttribute('aria-expanded') === 'true';

          // Close all other dropdowns
          document.querySelectorAll('.main-nav__item--has-children').forEach(function (otherItem) {
            if (otherItem !== item) {
              var otherBtn = otherItem.querySelector('.main-nav__link');
              var otherDrop = otherItem.querySelector('.main-nav__dropdown');
              if (otherBtn) otherBtn.setAttribute('aria-expanded', 'false');
              if (otherDrop) otherDrop.hidden = true;
            }
          });

          // Toggle current
          button.setAttribute('aria-expanded', !expanded);
          dropdown.hidden = expanded;
        });
      });

      // Close on Escape
      once('scdc-mega-menu-escape', 'body', context).forEach(function () {
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape') {
            document.querySelectorAll('.main-nav__item--has-children').forEach(function (item) {
              var btn = item.querySelector('.main-nav__link');
              var drop = item.querySelector('.main-nav__dropdown');
              if (btn && btn.getAttribute('aria-expanded') === 'true') {
                btn.setAttribute('aria-expanded', 'false');
                if (drop) drop.hidden = true;
                btn.focus();
              }
            });
          }
        });
      });

      // Close when clicking outside
      once('scdc-mega-menu-outside', 'body', context).forEach(function () {
        document.addEventListener('click', function (e) {
          if (!e.target.closest('.main-nav')) {
            document.querySelectorAll('.main-nav__item--has-children').forEach(function (item) {
              var btn = item.querySelector('.main-nav__link');
              var drop = item.querySelector('.main-nav__dropdown');
              if (btn) btn.setAttribute('aria-expanded', 'false');
              if (drop) drop.hidden = true;
            });
          }
        });
      });
    }
  };

  /**
   * Mobile menu toggle.
   */
  Drupal.behaviors.scdcMobileMenu = {
    attach: function (context) {
      once('scdc-mobile-toggle', '.site-header__menu-toggle', context).forEach(function (toggle) {
        var nav = document.getElementById('main-nav');
        if (!nav) return;

        toggle.addEventListener('click', function () {
          var expanded = toggle.getAttribute('aria-expanded') === 'true';
          toggle.setAttribute('aria-expanded', !expanded);
          nav.classList.toggle('is-open');
        });
      });
    }
  };

})(Drupal, once);
