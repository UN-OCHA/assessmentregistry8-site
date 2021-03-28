<?php

namespace Drupal\ocha_docstore_files\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\external_entities\ExternalEntityInterface;
use Drupal\external_entities\Plugin\ExternalEntities\StorageClient\Rest;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * External entities storage client based on a REST API.
 *
 * @ExternalEntityStorageClient(
 *   id = "restjson",
 *   label = @Translation("REST (JSON)"),
 *   description = @Translation("Retrieves external entities from a strict JSON REST API.")
 * )
 */
class RestJson extends Rest implements PluginFormInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('external_entities.response_decoder_factory'),
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'endpoint' => NULL,
      'response_format' => 'json',
      'pager' => [
        'default_limit' => 50,
        'page_parameter' => NULL,
        'page_parameter_type' => NULL,
        'page_size_parameter' => NULL,
        'page_size_parameter_type' => NULL,
      ],
      'api_key' => [
        'header_name' => NULL,
        'key' => NULL,
      ],
      'parameters' => [
        'list' => NULL,
        'single' => NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL) {
    if (empty($ids) || !is_array($ids)) {
      return [];
    }

    $chunk_size = $this->configuration['pager']['default_limit'];

    $entities = [];
    foreach (array_chunk($ids, $chunk_size) as $chunk) {
      // The docstore returns the full data for each resource, so it's much
      // faster to perform a single request (or a few if the number of ids
      // is larger that the maximum number of items the docstore can return).
      $results = $this->query([
        [
          'field' => 'uuid',
          'value' => $chunk,
          'operator' => 'IN',
        ],
      ]);
      foreach ($results as $result) {
        if (!empty($result['uuid'])) {
          $entities[$result['uuid']] = $result;
        }
      }
    }

    return $entities;
  }

  /**
   * Loads one entity.
   *
   * @param mixed $id
   *   The ID of the entity to load.
   *
   * @return array|null
   *   A raw data array, NULL if no data returned.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function load($id) {
    $response = $this->httpClient->request(
      'GET',
      ocha_docstore_files_get_endpoint_base($this->configuration['endpoint'] . '/' . $id),
      [
        'headers' => $this->getHttpHeaders(),
        'query' => $this->getSingleQueryParameters($id),
      ]
    );

    $body = $response->getBody();

    return $this
      ->getResponseDecoderFactory()
      ->getDecoder($this->configuration['response_format'])
      ->decode($body);
  }

  /**
   * {@inheritdoc}
   */
  public function save(ExternalEntityInterface $entity) {
    if ($entity->id()) {
      $this->httpClient->request(
        'PUT',
        ocha_docstore_files_get_endpoint_base($this->configuration['endpoint'] . '/' . $entity->id()),
        [
          'body' => json_encode($entity->extractRawData()),
          'headers' => $this->getHttpHeaders(),
        ]
      );
      $result = $entity->id();
    }
    else {
      // Remove uuid.
      $raw_data = $entity->extractRawData();
      unset($raw_data['uuid']);

      $response = $this->httpClient->request(
        'POST',
        ocha_docstore_files_get_endpoint_base($this->configuration['endpoint']),
        [
          'body' => json_encode($raw_data),
          'headers' => $this->getHttpHeaders(),
        ]
      );

      $body = $response->getBody() . '';
      $body = json_decode($body);

      if ($body->uuid) {
        sleep(1);
        $result = $body->uuid;
      }
    }

    return $result;
  }

  /**
   * Query the docstore and return the raw result.
   *
   * @param array $parameters
   *   Query parameters.
   * @param array $sorts
   *   Sort options for the query.
   * @param int|null $start
   *   Starting offset.
   * @param int|null $length
   *   Maximum number of items to return.
   *
   * @return array
   *   Query results. When querying multiple items, it will be an associative
   *   array with the `_count` (total number of items), `_start` (starting
   *   offset) and `results` (list of resources). Otherwise if will an array
   *   with the resource's data.
   */
  public function queryRaw(array $parameters = [], array $sorts = [], $start = NULL, $length = NULL) {
    $response = $this->httpClient->request(
      'GET',
      ocha_docstore_files_get_endpoint_base($this->configuration['endpoint']),
      [
        'headers' => $this->getHttpHeaders(),
        'query' => $this->getListQueryParameters($parameters, $start, $length),
      ]
    );

    $body = $response->getBody() . '';
    $results = $this
      ->getResponseDecoderFactory()
      ->getDecoder($this->configuration['response_format'])
      ->decode($body);

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function query(array $parameters = [], array $sorts = [], $start = NULL, $length = NULL) {
    $results = $this->queryRaw($parameters, $sorts, $start, $length);

    // Return only items for lists.
    if (isset($results['_count']) && isset($results['results'])) {
      $results = $results['results'];
    }
    return $results;
  }

  /**
   * Prepares and returns parameters used for list queries.
   *
   * @param array $parameters
   *   (optional) Raw parameter values.
   * @param int|null $start
   *   (optional) The first item to return.
   * @param int|null $length
   *   (optional) The number of items to return.
   *
   * @return array
   *   An associative array of parameters.
   */
  public function getListQueryParameters(array $parameters = [], $start = NULL, $length = NULL) {
    $query_parameters = [];

    // Currently always providing a limit.
    $query_parameters += $this->getPagingQueryParameters($start, $length);

    foreach ($parameters as $parameter) {
      // Map field names.
      $external_field_name = $this->externalEntityType->getFieldMapping($parameter['field'], 'value');

      if (!$external_field_name) {
        $external_field_name = $this->externalEntityType->getFieldMapping($parameter['field'], 'target_id');
        if (!$external_field_name) {
          $external_field_name = $parameter['field'];
        }
        else {
          // We only need the property name, a bit ugly.
          $external_field_name = reset(explode('/', $external_field_name));
        }
      }

      if (isset($parameter['operator'])) {
        $query_parameters['filter'][$external_field_name]['condition']['operator'] = $parameter['operator'];
        $query_parameters['filter'][$external_field_name]['condition']['path'] = $external_field_name;
        $query_parameters['filter'][$external_field_name]['condition']['value'] = $parameter['value'];
      }
      else {
        $query_parameters['filter'][$external_field_name] = $parameter['value'];
      }
    }

    if (!empty($this->configuration['parameters']['list'])) {
      $query_parameters += $this->configuration['parameters']['list'];
    }

    return $query_parameters;
  }

  /**
   * Gets the HTTP headers to add to a request.
   *
   * @return array
   *   Associative array of headers to add to the request.
   */
  public function getHttpHeaders() {
    $headers = [];

    if ($this->configuration['api_key']['header_name'] && $this->configuration['api_key']['key']) {
      $headers[$this->configuration['api_key']['header_name']] = ocha_docstore_files_get_endpoint_apikey($this->configuration['api_key']['key']);
    }

    return $headers;
  }

  /**
   * {@inheritdoc}
   */
  public function countQuery(array $parameters = []) {
    $response = $this->httpClient->request(
      'GET',
      ocha_docstore_files_get_endpoint_base($this->configuration['endpoint']),
      [
        'headers' => $this->getHttpHeaders(),
        'query' => $this->getListQueryParameters($parameters, 0, 1),
      ]
    );

    $body = $response->getBody() . '';
    $results = $this
      ->getResponseDecoderFactory()
      ->getDecoder($this->configuration['response_format'])
      ->decode($body);

    return $results['_count'];
  }

}
