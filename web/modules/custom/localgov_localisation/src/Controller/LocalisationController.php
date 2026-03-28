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

  protected LocalisationApiClient $apiClient;

  public function __construct(LocalisationApiClient $api_client) {
    $this->apiClient = $api_client;
  }

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
   * AJAX: combined localisation search via /api/v1/Localisations/{postcode}.
   */
  public function ajaxSearch(string $postcode, Request $request): JsonResponse {
    $premise_id = $request->query->get('premiseId');
    $data = $this->apiClient->localise($postcode, $premise_id);
    if ($data === NULL) {
      return new JsonResponse([
        'error' => 'Unable to retrieve data for this postcode.',
      ], 503);
    }
    return new JsonResponse($data);
  }

  /**
   * AJAX: address lookup via /api/v1/Addresses/PostcodeSearch/{postcode}.
   */
  public function addressLookup(string $postcode): JsonResponse {
    $data = $this->apiClient->addressPostcodeSearch($postcode);
    if ($data === NULL) {
      return new JsonResponse(['error' => 'Address lookup failed.'], 503);
    }
    return new JsonResponse($data);
  }

  /**
   * AJAX: bin collections via /api/v1/Collections/Search/{premiseId}.
   */
  public function collections(string $premiseId, Request $request): JsonResponse {
    $num = (int) $request->query->get('numberOfCollections', 999);
    $date = $request->query->get('date');
    $include_events = (bool) $request->query->get('includeBinEvents', FALSE);
    $data = $this->apiClient->collections($premiseId, $num, $date, $include_events);
    if ($data === NULL) {
      return new JsonResponse(['error' => 'Collection lookup failed.'], 503);
    }
    return new JsonResponse($data);
  }

  /**
   * AJAX: councillors via /api/v1/ModernGov/GetCouncillors/{postcode}.
   */
  public function councillors(string $postcode): JsonResponse {
    $data = $this->apiClient->councillorsByPostcode($postcode);
    if ($data === NULL) {
      return new JsonResponse(['error' => 'Councillor lookup failed.'], 503);
    }
    return new JsonResponse($data);
  }

  /**
   * AJAX: planning via /api/v1/Planning/ApplicationByPostcode/{postcode}.
   */
  public function planning(string $postcode, Request $request): JsonResponse {
    $radius = $request->query->get('radius');
    $data = $this->apiClient->planningByPostcode(
      $postcode,
      $radius ? (float) $radius : NULL,
    );
    if ($data === NULL) {
      return new JsonResponse(['error' => 'Planning lookup failed.'], 503);
    }
    return new JsonResponse($data);
  }

}
