/**
 * @file
 * SCDC theme global JavaScript behaviours.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Global theme initialisation.
   */
  Drupal.behaviors.scdcGlobal = {
    attach: function (context) {
      // Skip link focus fix.
      once('scdc-skip-link', '.skip-link', context).forEach(function (el) {
        el.addEventListener('click', function (e) {
          var target = document.getElementById('main-content-anchor');
          if (target) {
            target.focus();
          }
        });
      });
    }
  };

})(Drupal, once);
