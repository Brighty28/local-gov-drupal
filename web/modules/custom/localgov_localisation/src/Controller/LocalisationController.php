<?php

namespace Drupal\localgov_localisation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\localgov_localisation\Service\LocalisationApiClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for localisation pages and AJAX endpoints.
 */
class LocalisationController extends ControllerBase {

  /**
   * The localisation API client.
   */
  protected LocalisationApiClient $apiClient;

  /**
   * Constructs a LocalisationController.
   */
  public function __construct(LocalisationApiClient $api_client) {
    $this->apiClient = $api_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('localgov_localisation.api_client'),
    );
  }

  /**
   * The "In my area" postcode search page.
   */
  public function searchPage(Request $request): array {
    $postcode = $request->query->get('postcode', '');
    $results = NULL;

    if (!empty($postcode)) {
      $results = $this->apiClient->localise($postcode);
    }

    return [
      '#theme' => 'localgov_localisation_search',
      '#postcode' => $postcode,
      '#results' => $results,
      '#attached' => [
        'library' => ['localgov_localisation/localisation'],
        'drupalSettings' => [
          'localgov_localisation' => [
            'ajax_url' => '/api/localisation/search/',
          ],
        ],
      ],
    ];
  }

  /**
   * AJAX endpoint: combined localisation search.
   */
  public function ajaxSearch(string $postcode): JsonResponse {
    $data = $this->apiClient->localise($postcode);
    if ($data === NULL) {
      return new JsonResponse([
        'error' => 'Unable to retrieve data for this postcode.',
      ], 503);
    }
    return new JsonResponse($data);
  }

  /**
   * AJAX endpoint: address lookup.
   */
  public function addressLookup(string $postcode): JsonResponse {
    $data = $this->apiClient->addressLookup($postcode);
    if ($data === NULL) {
      return new JsonResponse(['error' => 'Address lookup failed.'], 503);
    }
    return new JsonResponse($data);
  }

  /**
   * AJAX endpoint: bin collections.
   */
  public function collections(string $premiseId): JsonResponse {
    $data = $this->apiClient->binCollections($premiseId);
    if ($data === NULL) {
      return new JsonResponse(['error' => 'Collection lookup failed.'], 503);
    }
    return new JsonResponse($data);
  }

  /**
   * AJAX endpoint: councillors.
   */
  public function councillors(string $postcode): JsonResponse {
    $data = $this->apiClient->councillors($postcode);
    if ($data === NULL) {
      return new JsonResponse(['error' => 'Councillor lookup failed.'], 503);
    }
    return new JsonResponse($data);
  }

  /**
   * AJAX endpoint: planning applications.
   */
  public function planning(string $postcode): JsonResponse {
    $config = $this->config('localgov_localisation.settings');
    $radius = $config->get('default_radius_planning') ?? 2;
    $data = $this->apiClient->planningApplications($postcode, $radius);
    if ($data === NULL) {
      return new JsonResponse(['error' => 'Planning lookup failed.'], 503);
    }
    return new JsonResponse($data);
  }

}
