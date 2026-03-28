/**
 * @file
 * LocalGov Localisation - AJAX postcode search.
 */

(function (Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.localgovLocalisation = {
    attach: function (context) {
      var ajaxUrl = drupalSettings.localgov_localisation
        ? drupalSettings.localgov_localisation.ajax_url
        : '/api/localisation/search/';

      once('localgov-localisation', '[data-localisation-form]', context).forEach(function (form) {
        var resultsContainer = form.closest('.localisation-search')
          ? form.closest('.localisation-search').querySelector('[data-localisation-results]')
          : null;

        if (!resultsContainer) return;

        form.addEventListener('submit', function (e) {
          var postcode = form.querySelector('input[name="postcode"]').value.trim();

          // Only intercept if we have a results container for AJAX
          if (!postcode) return;

          e.preventDefault();

          // Show loading state
          form.closest('.localisation-search').classList.add('localisation-search--loading');
          resultsContainer.innerHTML = '<div class="localisation-results__loading">' +
            Drupal.t('Searching...') + '</div>';

          // Fetch results via AJAX
          fetch(ajaxUrl + encodeURIComponent(postcode), {
            headers: { 'Accept': 'application/json' },
          })
            .then(function (response) {
              if (!response.ok) {
                throw new Error('API request failed');
              }
              return response.json();
            })
            .then(function (data) {
              // Update browser URL without reload
              var newUrl = form.action + '?postcode=' + encodeURIComponent(postcode);
              window.history.pushState({}, '', newUrl);

              // Render results via Drupal AJAX or fallback
              renderResults(resultsContainer, data, postcode);
            })
            .catch(function () {
              resultsContainer.innerHTML =
                '<div class="messages messages--error">' +
                Drupal.t('Sorry, we could not retrieve information for that postcode. Please try again.') +
                '</div>';
            })
            .finally(function () {
              form.closest('.localisation-search').classList.remove('localisation-search--loading');
            });
        });
      });

      /**
       * Render API results into the results container.
       */
      function renderResults(container, data, postcode) {
        var html = '<div class="localisation-results">';
        html += '<h2 class="localisation-results__title">' +
          Drupal.t('Results for @postcode', { '@postcode': postcode }) + '</h2>';

        // Councillors
        if (data.councillors && data.councillors.length > 0) {
          html += '<section class="localisation-results__section">';
          html += '<h3 class="localisation-results__heading">' + Drupal.t('Your Councillors') + '</h3>';
          html += '<div class="localisation-results__cards">';
          data.councillors.forEach(function (c) {
            html += '<div class="councillor-card">';
            if (c.photoUrl) {
              html += '<img src="' + Drupal.checkPlain(c.photoUrl) + '" alt="' +
                Drupal.checkPlain(c.name) + '" class="councillor-card__photo" loading="lazy">';
            }
            html += '<div class="councillor-card__info">';
            html += '<h4 class="councillor-card__name">' + Drupal.checkPlain(c.name) + '</h4>';
            if (c.party) html += '<p class="councillor-card__party">' + Drupal.checkPlain(c.party) + '</p>';
            if (c.ward) html += '<p class="councillor-card__ward">' + Drupal.checkPlain(c.ward) + '</p>';
            if (c.email) html += '<a href="mailto:' + Drupal.checkPlain(c.email) +
              '" class="councillor-card__email">' + Drupal.checkPlain(c.email) + '</a>';
            html += '</div></div>';
          });
          html += '</div></section>';
        }

        // Bin collections
        if (data.collections && data.collections.length > 0) {
          html += '<section class="localisation-results__section">';
          html += '<h3 class="localisation-results__heading">' + Drupal.t('Bin Collections') + '</h3>';
          data.collections.forEach(function (c) {
            html += '<div class="collection-item">';
            html += '<div class="collection-item__type">' + Drupal.checkPlain(c.type) + '</div>';
            html += '<div class="collection-item__date">' +
              Drupal.t('Next collection: @date', { '@date': c.nextDate || '' }) + '</div>';
            html += '</div>';
          });
          html += '</section>';
        }

        // Planning
        if (data.planning && data.planning.length > 0) {
          html += '<section class="localisation-results__section">';
          html += '<h3 class="localisation-results__heading">' + Drupal.t('Planning Applications Nearby') + '</h3>';
          data.planning.forEach(function (p) {
            html += '<div class="planning-item">';
            html += '<h4 class="planning-item__ref">';
            if (p.url) {
              html += '<a href="' + Drupal.checkPlain(p.url) + '" target="_blank" rel="noopener">' +
                Drupal.checkPlain(p.reference) + '</a>';
            } else {
              html += Drupal.checkPlain(p.reference);
            }
            html += '</h4>';
            if (p.description) html += '<p class="planning-item__desc">' + Drupal.checkPlain(p.description) + '</p>';
            if (p.address) html += '<p class="planning-item__address">' + Drupal.checkPlain(p.address) + '</p>';
            if (p.status) html += '<span class="planning-item__status">' + Drupal.checkPlain(p.status) + '</span>';
            html += '</div>';
          });
          html += '</section>';
        }

        // Events
        if (data.events && data.events.length > 0) {
          html += '<section class="localisation-results__section">';
          html += '<h3 class="localisation-results__heading">' + Drupal.t('Local Events') + '</h3>';
          data.events.forEach(function (ev) {
            html += '<div class="event-result">';
            html += '<h4 class="event-result__title">';
            if (ev.url) {
              html += '<a href="' + Drupal.checkPlain(ev.url) + '" target="_blank" rel="noopener">' +
                Drupal.checkPlain(ev.title) + '</a>';
            } else {
              html += Drupal.checkPlain(ev.title);
            }
            html += '</h4>';
            if (ev.date) html += '<p class="event-result__date">' + Drupal.checkPlain(ev.date) + '</p>';
            if (ev.venue) html += '<p class="event-result__venue">' + Drupal.checkPlain(ev.venue) + '</p>';
            html += '</div>';
          });
          html += '</section>';
        }

        html += '</div>';
        container.innerHTML = html;
      }
    }
  };

})(Drupal, drupalSettings, once);
