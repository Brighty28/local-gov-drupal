<?php

namespace Drupal\localgov_localisation\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Client for the LocalGov Localisation API.
 *
 * Provides methods to query postcode-based local authority services.
 * Endpoint paths match the OpenAPI 3.0.4 spec at:
 * /api/v1/{Controller}/{Action}/{parameter}
 *
 * @see https://localisationapi.azurewebsites.net/Swagger/index.html
 */
class LocalisationApiClient {

  protected ClientInterface $httpClient;
  protected ConfigFactoryInterface $configFactory;
  protected $logger;
  protected CacheBackendInterface $cache;

  public function __construct(
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    CacheBackendInterface $cache,
  ) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('localgov_localisation');
    $this->cache = $cache;
  }

  /**
   * Gets the module configuration.
   */
  protected function getConfig(): array {
    $config = $this->configFactory->get('localgov_localisation.settings');
    return [
      'base_url' => rtrim($config->get('api_base_url') ?? '', '/'),
      'api_key' => $config->get('api_key') ?? '',
      'cache_lifetime' => $config->get('cache_lifetime') ?? 3600,
      'address_source' => $config->get('address_source') ?? 'Alloy',
      'authority_source' => $config->get('authority_source') ?? 'SCDC',
      'default_radius_events' => $config->get('default_radius_events') ?? 5,
      'default_radius_planning' => $config->get('default_radius_planning') ?? 2,
    ];
  }

  /**
   * Makes a GET request to the API.
   *
   * @param string $endpoint
   *   The API endpoint path (relative to base URL).
   * @param array $query
   *   Query parameters.
   *
   * @return array|null
   *   Decoded JSON response or NULL on failure.
   */
  protected function get(string $endpoint, array $query = []): ?array {
    $config = $this->getConfig();
    $cache_key = 'localgov_localisation:' . md5($endpoint . serialize($query));

    $cached = $this->cache->get($cache_key);
    if ($cached) {
      return $cached->data;
    }

    $url = $config['base_url'] . '/' . ltrim($endpoint, '/');
    $options = [
      'query' => $query,
      'headers' => [
        'Accept' => 'application/json',
      ],
      'timeout' => 15,
    ];

    if (!empty($config['api_key'])) {
      $options['headers']['X-Api-Key'] = $config['api_key'];
    }

    try {
      $response = $this->httpClient->request('GET', $url, $options);
      $data = json_decode($response->getBody()->getContents(), TRUE);

      if ($data !== NULL) {
        $this->cache->set($cache_key, $data, time() + $config['cache_lifetime']);
      }

      return $data;
    }
    catch (GuzzleException $e) {
      $this->logger->error('Localisation API request failed for @endpoint: @message', [
        '@endpoint' => $endpoint,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  // ---------------------------------------------------------------------------
  // Addresses
  // ---------------------------------------------------------------------------

  /**
   * Search addresses by postcode.
   *
   * @param string $postcode
   *   UK postcode.
   * @param string|null $source
   *   Address source: "Alloy" or "OSData". Defaults to config value.
   */
  public function addressPostcodeSearch(string $postcode, ?string $source = NULL): ?array {
    $config = $this->getConfig();
    $query = ['source' => $source ?? $config['address_source']];
    return $this->get('/api/v1/Addresses/PostcodeSearch/' . urlencode($postcode), $query);
  }

  /**
   * Search address by UPRN.
   *
   * @param string $uprn
   *   Unique Property Reference Number.
   * @param string|null $source
   *   Address source: "Alloy" or "OSData".
   */
  public function addressUprnSearch(string $uprn, ?string $source = NULL): ?array {
    $config = $this->getConfig();
    $query = ['source' => $source ?? $config['address_source']];
    return $this->get('/api/v1/Addresses/UprnSearch/' . urlencode($uprn), $query);
  }

  /**
   * Geocode a postcode (get lat/lng).
   *
   * @param string $postcode
   *   UK postcode.
   */
  public function geocode(string $postcode): ?array {
    return $this->get('/api/v1/Addresses/Geocode/' . urlencode($postcode));
  }

  // ---------------------------------------------------------------------------
  // Collections (Bin Collections)
  // ---------------------------------------------------------------------------

  /**
   * Get bin collection schedule by premise ID.
   *
   * @param string $premise_id
   *   The premise identifier (from address lookup).
   * @param int $number_of_collections
   *   Max collections to return (default 999 = all).
   * @param string|null $date
   *   Optional date filter.
   * @param bool $include_bin_events
   *   Include bin-related events (missed collections, etc).
   */
  public function collections(string $premise_id, int $number_of_collections = 999, ?string $date = NULL, bool $include_bin_events = FALSE): ?array {
    $query = [
      'numberOfCollections' => $number_of_collections,
      'includeBinEvents' => $include_bin_events ? 'true' : 'false',
    ];
    if ($date !== NULL) {
      $query['date'] = $date;
    }
    return $this->get('/api/v1/Collections/Search/' . urlencode($premise_id), $query);
  }

  // ---------------------------------------------------------------------------
  // DemocracyClubs
  // ---------------------------------------------------------------------------

  /**
   * Get Democracy Club election data by postcode.
   *
   * @param string $postcode
   *   UK postcode.
   */
  public function democracyByPostcode(string $postcode): ?array {
    return $this->get('/api/v1/DemocracyClubs/Postcode/' . urlencode($postcode));
  }

  /**
   * Get Democracy Club election data by UPRN.
   *
   * @param string $uprn
   *   Unique Property Reference Number.
   */
  public function democracyByUprn(string $uprn): ?array {
    return $this->get('/api/v1/DemocracyClubs/UPRN/' . urlencode($uprn));
  }

  // ---------------------------------------------------------------------------
  // Events
  // ---------------------------------------------------------------------------

  /**
   * Get local events by postcode.
   *
   * @param string $postcode
   *   UK postcode.
   * @param float|null $radius
   *   Search radius in km. Defaults to config value (5km).
   */
  public function events(string $postcode, ?float $radius = NULL): ?array {
    $config = $this->getConfig();
    return $this->get('/api/v1/Events/' . urlencode($postcode), [
      'radius' => $radius ?? $config['default_radius_events'],
    ]);
  }

  // ---------------------------------------------------------------------------
  // Localisations (combined all-in-one lookup)
  // ---------------------------------------------------------------------------

  /**
   * Get combined localisation data for a postcode.
   *
   * This is the main endpoint — returns addresses, councillors,
   * collections, planning, events, democracy all in one call.
   *
   * @param string $postcode
   *   UK postcode.
   * @param string|null $premise_id
   *   Optional premise ID for bin collection data.
   * @param float|null $radius_events
   *   Events search radius. Defaults to config value.
   * @param float|null $radius_planning
   *   Planning search radius. Defaults to config value.
   */
  public function localise(string $postcode, ?string $premise_id = NULL, ?float $radius_events = NULL, ?float $radius_planning = NULL): ?array {
    $config = $this->getConfig();
    $query = [
      'RadiusEvents' => $radius_events ?? $config['default_radius_events'],
      'RadiusPlanning' => $radius_planning ?? $config['default_radius_planning'],
    ];
    if ($premise_id !== NULL) {
      $query['premiseId'] = $premise_id;
    }
    return $this->get('/api/v1/Localisations/' . urlencode($postcode), $query);
  }

  // ---------------------------------------------------------------------------
  // ModernGov (Councillors & Council Meetings)
  // ---------------------------------------------------------------------------

  /**
   * Get councillors by postcode.
   *
   * @param string $postcode
   *   UK postcode.
   */
  public function councillorsByPostcode(string $postcode): ?array {
    return $this->get('/api/v1/ModernGov/GetCouncillors/' . urlencode($postcode));
  }

  /**
   * Get councillors by ward name.
   *
   * @param string $ward
   *   Ward name.
   */
  public function councillorsByWard(string $ward): ?array {
    return $this->get('/api/v1/ModernGov/GetWardCouncillors/' . urlencode($ward));
  }

  /**
   * Get council meetings/events from ModernGov.
   *
   * @param int $number_of_months
   *   Number of months to look ahead (default 12).
   * @param int $number_of_events
   *   Max events to return (0 = all).
   * @param string|null $authority_source
   *   Authority filter: "SCDC", "CCC", or "All". Defaults to config.
   * @param string|null $exclude_titles
   *   Comma-separated event titles to exclude.
   */
  public function meetings(int $number_of_months = 12, int $number_of_events = 0, ?string $authority_source = NULL, ?string $exclude_titles = NULL): ?array {
    $config = $this->getConfig();
    $query = [
      'numberOfEvents' => $number_of_events,
      'authoritySource' => $authority_source ?? $config['authority_source'],
    ];
    if ($exclude_titles !== NULL) {
      $query['excludeEventTitles'] = $exclude_titles;
    }
    return $this->get('/api/v1/ModernGov/GetEvents/' . $number_of_months, $query);
  }

  // ---------------------------------------------------------------------------
  // Planning
  // ---------------------------------------------------------------------------

  /**
   * Get planning applications by postcode.
   *
   * @param string $postcode
   *   UK postcode.
   * @param float|null $radius
   *   Search radius in km.
   */
  public function planningByPostcode(string $postcode, ?float $radius = NULL): ?array {
    $config = $this->getConfig();
    return $this->get('/api/v1/Planning/ApplicationByPostcode/' . urlencode($postcode), [
      'radius' => $radius ?? $config['default_radius_planning'],
    ]);
  }

  /**
   * Get planning applications by UPRN.
   *
   * @param string $uprn
   *   Unique Property Reference Number.
   * @param float|null $radius
   *   Search radius in km.
   */
  public function planningByUprn(string $uprn, ?float $radius = NULL): ?array {
    $config = $this->getConfig();
    return $this->get('/api/v1/Planning/ApplicationByUPRN/' . urlencode($uprn), [
      'radius' => $radius ?? $config['default_radius_planning'],
    ]);
  }

  // ---------------------------------------------------------------------------
  // Planning Constraints
  // ---------------------------------------------------------------------------

  /**
   * Get all planning constraints for a postcode.
   *
   * Returns listed buildings, TPOs, conservation areas, and flood zones.
   *
   * @param string $postcode
   *   UK postcode.
   * @param string|null $premise_id
   *   Optional premise ID.
   * @param array $radii
   *   Optional radius overrides. Keys: 'planning', 'listedBuilding',
   *   'conservationAreas', 'treePreservationOrder', 'floodZone'.
   * @param string|null $area
   *   Optional area filter.
   * @param string|null $reference
   *   Optional reference filter.
   */
  public function planningConstraints(string $postcode, ?string $premise_id = NULL, array $radii = [], ?string $area = NULL, ?string $reference = NULL): ?array {
    $query = [
      'radiusPlanning' => $radii['planning'] ?? 1,
      'radiusListedBuilding' => $radii['listedBuilding'] ?? 5,
      'radiusConservationAreas' => $radii['conservationAreas'] ?? 5,
      'radiusTreePreservationOrder' => $radii['treePreservationOrder'] ?? 5,
      'radiusFloodZone' => $radii['floodZone'] ?? 5,
    ];
    if ($premise_id !== NULL) {
      $query['premiseId'] = $premise_id;
    }
    if ($area !== NULL) {
      $query['area'] = $area;
    }
    if ($reference !== NULL) {
      $query['reference'] = $reference;
    }
    return $this->get('/api/v1/PlanningConstraints/' . urlencode($postcode), $query);
  }

  /**
   * Get listed buildings near a postcode.
   */
  public function listedBuildings(string $postcode, float $radius = 5, ?string $area = NULL, ?string $reference = NULL): ?array {
    $query = ['radius' => $radius];
    if ($area !== NULL) {
      $query['area'] = $area;
    }
    if ($reference !== NULL) {
      $query['reference'] = $reference;
    }
    return $this->get('/api/v1/PlanningConstraints/Get-Listed-Buildings/' . urlencode($postcode), $query);
  }

  /**
   * Get tree preservation orders near a postcode.
   */
  public function treePreservation(string $postcode, float $radius = 5, ?string $premise_id = NULL, ?string $area = NULL, ?string $reference = NULL): ?array {
    $query = ['radius' => $radius];
    if ($premise_id !== NULL) {
      $query['premiseId'] = $premise_id;
    }
    if ($area !== NULL) {
      $query['area'] = $area;
    }
    if ($reference !== NULL) {
      $query['reference'] = $reference;
    }
    return $this->get('/api/v1/PlanningConstraints/Get-Tree-Preservation/' . urlencode($postcode), $query);
  }

  /**
   * Get conservation areas near a postcode.
   */
  public function conservationAreas(string $postcode, float $radius = 5, ?string $premise_id = NULL, ?string $area = NULL, ?string $reference = NULL): ?array {
    $query = ['radius' => $radius];
    if ($premise_id !== NULL) {
      $query['premiseId'] = $premise_id;
    }
    if ($area !== NULL) {
      $query['area'] = $area;
    }
    if ($reference !== NULL) {
      $query['reference'] = $reference;
    }
    return $this->get('/api/v1/PlanningConstraints/Get-Conservation-Area/' . urlencode($postcode), $query);
  }

  /**
   * Get flood zones near a postcode.
   */
  public function floodZones(string $postcode, float $radius = 5, ?string $premise_id = NULL, ?string $area = NULL, ?string $reference = NULL): ?array {
    $query = ['radius' => $radius];
    if ($premise_id !== NULL) {
      $query['premiseId'] = $premise_id;
    }
    if ($area !== NULL) {
      $query['area'] = $area;
    }
    if ($reference !== NULL) {
      $query['reference'] = $reference;
    }
    return $this->get('/api/v1/PlanningConstraints/Get-Flood-Zone/' . urlencode($postcode), $query);
  }

}
