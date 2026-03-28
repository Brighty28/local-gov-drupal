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
 * Provides methods to query postcode-based local authority services including
 * address lookup, bin collections, councillors, planning, and events.
 */
class LocalisationApiClient {

  /**
   * The HTTP client.
   */
  protected ClientInterface $httpClient;

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The logger.
   */
  protected $logger;

  /**
   * The cache backend.
   */
  protected CacheBackendInterface $cache;

  /**
   * Constructs a LocalisationApiClient.
   */
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
    ];
  }

  /**
   * Makes a GET request to the API.
   *
   * @param string $endpoint
   *   The API endpoint path.
   * @param array $query
   *   Query parameters.
   *
   * @return array|null
   *   Decoded JSON response or NULL on failure.
   */
  protected function get(string $endpoint, array $query = []): ?array {
    $config = $this->getConfig();
    $cache_key = 'localgov_localisation:' . md5($endpoint . serialize($query));

    // Check cache.
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

    // Add API key if configured.
    if (!empty($config['api_key'])) {
      $options['headers']['X-Api-Key'] = $config['api_key'];
    }

    try {
      $response = $this->httpClient->request('GET', $url, $options);
      $data = json_decode($response->getBody()->getContents(), TRUE);

      // Cache successful responses.
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

  /**
   * Look up addresses by postcode.
   *
   * @param string $postcode
   *   UK postcode.
   *
   * @return array|null
   *   Array of address results.
   */
  public function addressLookup(string $postcode): ?array {
    return $this->get('/api/Address/' . urlencode($postcode));
  }

  /**
   * Get address details by UPRN.
   *
   * @param string $uprn
   *   The UPRN identifier.
   *
   * @return array|null
   *   Address details.
   */
  public function addressByUprn(string $uprn): ?array {
    return $this->get('/api/Address/uprn/' . urlencode($uprn));
  }

  /**
   * Get bin collection schedule by premise ID.
   *
   * @param string $premise_id
   *   The premise identifier.
   *
   * @return array|null
   *   Collection schedule data.
   */
  public function binCollections(string $premise_id): ?array {
    return $this->get('/api/Collections/' . urlencode($premise_id));
  }

  /**
   * Get councillors by postcode.
   *
   * @param string $postcode
   *   UK postcode.
   *
   * @return array|null
   *   Array of councillor data.
   */
  public function councillors(string $postcode): ?array {
    return $this->get('/api/Councillors/' . urlencode($postcode));
  }

  /**
   * Get council meetings/events.
   *
   * @return array|null
   *   Array of meeting data.
   */
  public function meetings(): ?array {
    return $this->get('/api/Meetings');
  }

  /**
   * Get planning applications by postcode.
   *
   * @param string $postcode
   *   UK postcode.
   * @param float $radius
   *   Search radius in km.
   *
   * @return array|null
   *   Array of planning application data.
   */
  public function planningApplications(string $postcode, float $radius = 2): ?array {
    return $this->get('/api/Planning/' . urlencode($postcode), [
      'radius' => $radius,
    ]);
  }

  /**
   * Get planning constraints for a location.
   *
   * @param string $postcode
   *   UK postcode.
   *
   * @return array|null
   *   Planning constraints data (listed buildings, TPOs, conservation, flood).
   */
  public function planningConstraints(string $postcode): ?array {
    return $this->get('/api/PlanningConstraints/' . urlencode($postcode));
  }

  /**
   * Get local events by postcode.
   *
   * @param string $postcode
   *   UK postcode.
   * @param float $radius
   *   Search radius in km.
   *
   * @return array|null
   *   Array of local events.
   */
  public function events(string $postcode, float $radius = 5): ?array {
    return $this->get('/api/Events/' . urlencode($postcode), [
      'radius' => $radius,
    ]);
  }

  /**
   * Get democracy data (elections) by postcode.
   *
   * @param string $postcode
   *   UK postcode.
   *
   * @return array|null
   *   Democracy Club election data.
   */
  public function democracy(string $postcode): ?array {
    return $this->get('/api/Democracy/' . urlencode($postcode));
  }

  /**
   * Get combined localisation data for a postcode.
   *
   * Returns all available data for a postcode in one call.
   *
   * @param string $postcode
   *   UK postcode.
   *
   * @return array|null
   *   Combined localisation data.
   */
  public function localise(string $postcode): ?array {
    return $this->get('/api/Localisation/' . urlencode($postcode));
  }

}
