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

    $form['api'] = [
      '#type' => 'details',
      '#title' => $this->t('API Connection'),
      '#open' => TRUE,
    ];

    $form['api']['api_base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('API Base URL'),
      '#description' => $this->t('The base URL of the Localisation API (e.g. https://localisationapi.azurewebsites.net).'),
      '#default_value' => $config->get('api_base_url'),
      '#required' => TRUE,
    ];

    $form['api']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('API key for authentication. Leave blank if not required.'),
      '#default_value' => $config->get('api_key'),
    ];

    $form['api']['authority_source'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authority Identifier'),
      '#description' => $this->t('Short code identifying this authority (e.g. SCDC, CCC). Used for multi-council deployments.'),
      '#default_value' => $config->get('authority_source'),
    ];

    $form['cache'] = [
      '#type' => 'details',
      '#title' => $this->t('Caching'),
      '#open' => FALSE,
    ];

    $form['cache']['cache_lifetime'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache Lifetime (seconds)'),
      '#description' => $this->t('How long to cache API responses. Set to 0 to disable caching.'),
      '#default_value' => $config->get('cache_lifetime'),
      '#min' => 0,
      '#max' => 86400,
    ];

    $form['search'] = [
      '#type' => 'details',
      '#title' => $this->t('Search Defaults'),
      '#open' => FALSE,
    ];

    $form['search']['default_radius_events'] = [
      '#type' => 'number',
      '#title' => $this->t('Default Events Radius (km)'),
      '#default_value' => $config->get('default_radius_events'),
      '#min' => 1,
      '#max' => 50,
      '#step' => 0.5,
    ];

    $form['search']['default_radius_planning'] = [
      '#type' => 'number',
      '#title' => $this->t('Default Planning Radius (km)'),
      '#default_value' => $config->get('default_radius_planning'),
      '#min' => 0.5,
      '#max' => 10,
      '#step' => 0.5,
    ];

    $form['services'] = [
      '#type' => 'details',
      '#title' => $this->t('Enabled Services'),
      '#description' => $this->t('Choose which localisation services to enable. Disabled services will not appear in search results.'),
      '#open' => TRUE,
    ];

    $enabled = $config->get('enabled_services') ?? [];

    $service_options = [
      'addresses' => $this->t('Address Lookup'),
      'collections' => $this->t('Bin Collections'),
      'councillors' => $this->t('Councillors'),
      'planning' => $this->t('Planning Applications'),
      'planning_constraints' => $this->t('Planning Constraints'),
      'events' => $this->t('Local Events'),
      'democracy' => $this->t('Democracy / Elections'),
      'localisation' => $this->t('Combined Localisation (all-in-one)'),
    ];

    foreach ($service_options as $key => $label) {
      $form['services']['service_' . $key] = [
        '#type' => 'checkbox',
        '#title' => $label,
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
      'addresses', 'collections', 'councillors', 'planning',
      'planning_constraints', 'events', 'democracy', 'localisation',
    ];

    foreach ($service_keys as $key) {
      $enabled_services[$key] = (bool) $form_state->getValue('service_' . $key);
    }

    $this->config('localgov_localisation.settings')
      ->set('api_base_url', $form_state->getValue('api_base_url'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('authority_source', $form_state->getValue('authority_source'))
      ->set('cache_lifetime', $form_state->getValue('cache_lifetime'))
      ->set('default_radius_events', $form_state->getValue('default_radius_events'))
      ->set('default_radius_planning', $form_state->getValue('default_radius_planning'))
      ->set('enabled_services', $enabled_services)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
