/**
 * @file
 * SCDC accordion component.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.scdcAccordion = {
    attach: function (context) {
      once('scdc-accordion', '.accordion__header', context).forEach(function (header) {
        header.addEventListener('click', function () {
          var expanded = header.getAttribute('aria-expanded') === 'true';
          var content = document.getElementById(header.getAttribute('aria-controls'));

          header.setAttribute('aria-expanded', !expanded);

          if (content) {
            content.hidden = expanded;
          }
        });
      });
    }
  };

})(Drupal, once);
