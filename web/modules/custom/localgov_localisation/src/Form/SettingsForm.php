<?php

namespace Drupal\localgov_localisation\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for LocalGov Localisation settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['localgov_localisation.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'localgov_localisation_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('localgov_localisation.settings');

    // --- API Connection ---
    $form['api'] = [
      '#type' => 'details',
      '#title' => $this->t('API Connection'),
      '#open' => TRUE,
    ];

    $form['api']['api_base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('API Base URL'),
      '#description' => $this->t('The base URL of the Localisation API (e.g. https://localisationapi.azurewebsites.net). Do not include a trailing slash.'),
      '#default_value' => $config->get('api_base_url'),
      '#required' => TRUE,
    ];

    $form['api']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('API key for authentication. Leave blank if not required.'),
      '#default_value' => $config->get('api_key'),
    ];

    // --- Authority ---
    $form['authority'] = [
      '#type' => 'details',
      '#title' => $this->t('Authority Settings'),
      '#open' => TRUE,
    ];

    $form['authority']['authority_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Authority Source'),
      '#description' => $this->t('Which authority to query for ModernGov data (councillors, meetings). Maps to the AuthoritySource enum in the API.'),
      '#options' => [
        'SCDC' => $this->t('South Cambridgeshire District Council (SCDC)'),
        'CCC' => $this->t('Cambridgeshire County Council (CCC)'),
        'All' => $this->t('All authorities'),
      ],
      '#default_value' => $config->get('authority_source'),
    ];

    $form['authority']['address_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Address Data Source'),
      '#description' => $this->t('Which address lookup provider to use. Maps to the AddressSource enum in the API.'),
      '#options' => [
        'Alloy' => $this->t('Alloy'),
        'OSData' => $this->t('OS Data Hub'),
      ],
      '#default_value' => $config->get('address_source'),
    ];

    // --- Caching ---
    $form['cache'] = [
      '#type' => 'details',
      '#title' => $this->t('Caching'),
      '#open' => FALSE,
    ];

    $form['cache']['cache_lifetime'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache Lifetime (seconds)'),
      '#description' => $this->t('How long to cache API responses. Set to 0 to disable caching. Default: 3600 (1 hour).'),
      '#default_value' => $config->get('cache_lifetime'),
      '#min' => 0,
      '#max' => 86400,
    ];

    // --- Search Defaults ---
    $form['search'] = [
      '#type' => 'details',
      '#title' => $this->t('Search Defaults'),
      '#open' => FALSE,
    ];

    $form['search']['default_radius_events'] = [
      '#type' => 'number',
      '#title' => $this->t('Default Events Radius (km)'),
      '#description' => $this->t('Default search radius for the Events endpoint. Maps to RadiusEvents parameter.'),
      '#default_value' => $config->get('default_radius_events'),
      '#min' => 1,
      '#max' => 50,
      '#step' => 0.5,
    ];

    $form['search']['default_radius_planning'] = [
      '#type' => 'number',
      '#title' => $this->t('Default Planning Radius (km)'),
      '#description' => $this->t('Default search radius for Planning and Localisations endpoints. Maps to RadiusPlanning and radius parameters.'),
      '#default_value' => $config->get('default_radius_planning'),
      '#min' => 0.5,
      '#max' => 10,
      '#step' => 0.5,
    ];

    // --- Enabled Services ---
    $form['services'] = [
      '#type' => 'details',
      '#title' => $this->t('Enabled Services'),
      '#description' => $this->t('Toggle which API services are available. Disabled services will not appear in the "In My Area" results.'),
      '#open' => TRUE,
    ];

    $enabled = $config->get('enabled_services') ?? [];

    $service_options = [
      'addresses' => [
        'label' => $this->t('Address Lookup'),
        'desc' => $this->t('Addresses/PostcodeSearch and Addresses/UprnSearch endpoints'),
      ],
      'collections' => [
        'label' => $this->t('Bin Collections'),
        'desc' => $this->t('Collections/Search endpoint (requires premise ID from address lookup)'),
      ],
      'councillors' => [
        'label' => $this->t('Councillors (ModernGov)'),
        'desc' => $this->t('ModernGov/GetCouncillors and GetWardCouncillors endpoints'),
      ],
      'meetings' => [
        'label' => $this->t('Council Meetings (ModernGov)'),
        'desc' => $this->t('ModernGov/GetEvents endpoint'),
      ],
      'planning' => [
        'label' => $this->t('Planning Applications'),
        'desc' => $this->t('Planning/ApplicationByPostcode and ApplicationByUPRN endpoints'),
      ],
      'planning_constraints' => [
        'label' => $this->t('Planning Constraints'),
        'desc' => $this->t('Listed buildings, TPOs, conservation areas, flood zones'),
      ],
      'events' => [
        'label' => $this->t('Local Events'),
        'desc' => $this->t('Events endpoint (events near a postcode)'),
      ],
      'democracy' => [
        'label' => $this->t('Democracy Club (Elections)'),
        'desc' => $this->t('DemocracyClubs/Postcode endpoint'),
      ],
      'localisation' => [
        'label' => $this->t('Combined Localisation'),
        'desc' => $this->t('Localisations endpoint (all-in-one postcode lookup)'),
      ],
    ];

    foreach ($service_options as $key => $info) {
      $form['services']['service_' . $key] = [
        '#type' => 'checkbox',
        '#title' => $info['label'],
        '#description' => $info['desc'],
        '#default_value' => $enabled[$key] ?? FALSE,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $enabled_services = [];
    $service_keys = [
      'addresses', 'collections', 'councillors', 'meetings', 'planning',
      'planning_constraints', 'events', 'democracy', 'localisation',
    ];

    foreach ($service_keys as $key) {
      $enabled_services[$key] = (bool) $form_state->getValue('service_' . $key);
    }

    $this->config('localgov_localisation.settings')
      ->set('api_base_url', $form_state->getValue('api_base_url'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('authority_source', $form_state->getValue('authority_source'))
      ->set('address_source', $form_state->getValue('address_source'))
      ->set('cache_lifetime', $form_state->getValue('cache_lifetime'))
      ->set('default_radius_events', $form_state->getValue('default_radius_events'))
      ->set('default_radius_planning', $form_state->getValue('default_radius_planning'))
      ->set('enabled_services', $enabled_services)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
