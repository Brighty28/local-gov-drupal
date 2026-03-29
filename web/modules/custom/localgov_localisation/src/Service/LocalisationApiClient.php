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

  /**
   * In-memory cache for the Entra ID access token within a single request.
   */
  protected ?string $accessToken = NULL;

  /**
   * Timestamp when the in-memory token expires.
   */
  protected int $tokenExpires = 0;

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
      'auth_method' => $config->get('auth_method') ?? 'api_key',
      'entra_tenant_id' => $config->get('entra_tenant_id') ?? '',
      'entra_client_id' => $config->get('entra_client_id') ?? '',
      'entra_client_secret' => $config->get('entra_client_secret') ?? '',
      'entra_scope' => $config->get('entra_scope') ?? '',
      'cache_lifetime' => $config->get('cache_lifetime') ?? 3600,
      'address_source' => $config->get('address_source') ?? 'Alloy',
      'authority_source' => $config->get('authority_source') ?? 'SCDC',
      'default_radius_events' => $config->get('default_radius_events') ?? 5,
      'default_radius_planning' => $config->get('default_radius_planning') ?? 2,
    ];
  }

  /**
   * Acquires an OAuth2 access token from Microsoft Entra ID.
   *
   * Uses the Client Credentials flow (application-to-application).
   * Tokens are cached in Drupal's cache and in-memory for the request lifetime.
   *
   * @return string|null
   *   The bearer token, or NULL on failure.
   */
  protected function getEntraToken(): ?string {
    // Check in-memory cache first.
    if ($this->accessToken && time() < $this->tokenExpires) {
      return $this->accessToken;
    }

    // Check Drupal cache.
    $cached = $this->cache->get('localgov_localisation:entra_token');
    if ($cached && !empty($cached->data['access_token']) && time() < ($cached->data['expires'] ?? 0)) {
      $this->accessToken = $cached->data['access_token'];
      $this->tokenExpires = $cached->data['expires'];
      return $this->accessToken;
    }

    $config = $this->getConfig();
    $tenant_id = $config['entra_tenant_id'];
    $client_id = $config['entra_client_id'];
    $client_secret = $config['entra_client_secret'];
    $scope = $config['entra_scope'];

    if (empty($tenant_id) || empty($client_id) || empty($client_secret)) {
      $this->logger->error('Entra ID authentication is configured but tenant ID, client ID, or client secret is missing.');
      return NULL;
    }

    // Default scope to the API's .default if not specified.
    if (empty($scope)) {
      $scope = $config['base_url'] . '/.default';
    }

    $token_url = 'https://login.microsoftonline.com/' . $tenant_id . '/oauth2/v2.0/token';

    try {
      $response = $this->httpClient->request('POST', $token_url, [
        'form_params' => [
          'grant_type' => 'client_credentials',
          'client_id' => $client_id,
          'client_secret' => $client_secret,
          'scope' => $scope,
        ],
        'timeout' => 10,
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);

      if (!empty($data['access_token'])) {
        // Cache the token. Subtract 60 seconds as a safety margin.
        $expires_in = ($data['expires_in'] ?? 3600) - 60;
        $expires_at = time() + $expires_in;

        $this->accessToken = $data['access_token'];
        $this->tokenExpires = $expires_at;

        $this->cache->set('localgov_localisation:entra_token', [
          'access_token' => $data['access_token'],
          'expires' => $expires_at,
        ], $expires_at);

        return $this->accessToken;
      }

      $this->logger->error('Entra ID token response did not contain an access_token. Response: @response', [
        '@response' => json_encode($data),
      ]);
      return NULL;
    }
    catch (GuzzleException $e) {
      $this->logger->error('Failed to acquire Entra ID token: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
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

    // Authenticate based on configured method.
    if ($config['auth_method'] === 'entra_id') {
      $token = $this->getEntraToken();
      if ($token) {
        $options['headers']['Authorization'] = 'Bearer ' . $token;
      }
      else {
        $this->logger->error('Cannot make API request to @endpoint: Entra ID token acquisition failed.', [
          '@endpoint' => $endpoint,
        ]);
        return NULL;
      }
    }
    elseif (!empty($config['api_key'])) {
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
      $status_code = method_exists($e, 'getResponse') && $e->getResponse()
        ? $e->getResponse()->getStatusCode()
        : 0;
      $response_body = '';
      if (method_exists($e, 'getResponse') && $e->getResponse()) {
        $response_body = $e->getResponse()->getBody()->getContents();
      }

      $this->logger->error('Localisation API request failed for @endpoint (HTTP @code): @message | Response: @body', [
        '@endpoint' => $endpoint,
        '@code' => $status_code,
        '@message' => $e->getMessage(),
        '@body' => $response_body,
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

  // ---------------------------------------------------------------------------
  // Connection Test
  // ---------------------------------------------------------------------------

  /**
   * Tests the API connection and authentication.
   *
   * @return array
   *   Status array with 'success', 'message', and optional 'details'.
   */
  public function testConnection(): array {
    $config = $this->getConfig();

    if (empty($config['base_url'])) {
      return [
        'success' => FALSE,
        'message' => 'API base URL is not configured.',
      ];
    }

    // For Entra ID, first verify we can get a token.
    if ($config['auth_method'] === 'entra_id') {
      if (empty($config['entra_tenant_id']) || empty($config['entra_client_id']) || empty($config['entra_client_secret'])) {
        return [
          'success' => FALSE,
          'message' => 'Entra ID credentials are incomplete. Please fill in Tenant ID, Client ID, and Client Secret.',
        ];
      }

      $token = $this->getEntraToken();
      if (!$token) {
        return [
          'success' => FALSE,
          'message' => 'Failed to acquire Entra ID access token. Check your Tenant ID, Client ID, Client Secret, and Scope. See the Drupal log for details.',
        ];
      }
    }

    // Try a simple geocode call as a connectivity test.
    $test_data = $this->geocode('CB23 4JG');
    if ($test_data !== NULL) {
      return [
        'success' => TRUE,
        'message' => 'Connection successful. API is responding and authentication is working.',
        'details' => $test_data,
      ];
    }

    return [
      'success' => FALSE,
      'message' => 'API request failed. Check the Drupal log (/admin/reports/dblog) for detailed error information.',
    ];
  }

}
