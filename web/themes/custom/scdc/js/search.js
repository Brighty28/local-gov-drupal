/**
 * @file
 * SCDC search enhancements.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.scdcSearch = {
    attach: function (context) {
      once('scdc-search', '.search-form__input', context).forEach(function (input) {
        // Auto-focus search input when visible
        if (input.offsetParent !== null) {
          input.focus();
        }
      });
    }
  };

})(Drupal, once);
