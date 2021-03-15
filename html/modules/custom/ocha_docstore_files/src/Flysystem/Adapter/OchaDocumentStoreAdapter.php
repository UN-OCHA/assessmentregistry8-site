<?php

namespace Drupal\ocha_docstore_files\Flysystem\Adapter;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;

/**
 * OCHA document store Flysystem adapter.
 */
class OchaDocumentStoreAdapter implements AdapterInterface {

  /**
   * The configuration.
   *
   * @var array
   */
  protected $configuration;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Constructs a new Flysystem adapter.
   */
  public function __construct($configuration, $http_client) {
    $this->configuration = $configuration;
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public function write($path, $contents, Config $config) {
    $result = $this->httpClient->get($config['uri'] . '/api/v1/files', [
      'data' => json_encode($contents),
      'headers' => [
        'API-KEY' => $this->configuration['api_key'],
        'Content-Type' => 'application/json',
      ],
    ]);

    return json_decode($result->getBody);
  }

  /**
   * {@inheritdoc}
   */
  public function writeStream($path, $resource, Config $config) {
    rewind($resource);
    $contents = stream_get_contents($resource);

    $response = $this->httpClient->post($this->configuration['uri'] . '/api/v1/files', [
      'body' => json_encode([
        'private' => FALSE,
        'filename' => $path,
        'data' => base64_encode($contents),
      ]),
      'headers' => [
        'API-KEY' => $this->configuration['api_key'],
        'Content-Type' => 'application/json',
      ],
    ]);

    $data = $response->getBody()->getContents();
    $data = json_decode($data);

    return [
      'contents' => $contents,
      'mimetype' => '',
      'type' => 'file',
      'uuid' => $data->uuid,
      'timestamp' => REQUEST_TIME,
      'visibility' => AdapterInterface::VISIBILITY_PUBLIC,
      'path' => $data->uuid . '/' . $path,
      'uri' => $data->uuid . '/' . $path,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function update($path, $contents, Config $config) {
  }

  /**
   * {@inheritdoc}
   */
  public function updateStream($path, $resource, Config $config) {
  }

  /**
   * {@inheritdoc}
   */
  public function rename($path, $newpath) {
  }

  /**
   * {@inheritdoc}
   */
  public function copy($path, $newpath) {
  }

  /**
   * {@inheritdoc}
   */
  public function delete($path) {
  }

  /**
   * {@inheritdoc}
   */
  public function deleteDir($dirname) {
  }

  /**
   * {@inheritdoc}
   */
  public function createDir($dirname, Config $config) {
  }

  /**
   * {@inheritdoc}
   */
  public function setVisibility($path, $visibility) {
  }

  /**
   * {@inheritdoc}
   */
  public function has($path) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function read($path) {
    return $this->adapter->read($path);
  }

  /**
   * {@inheritdoc}
   */
  public function readStream($path) {
    $response = $this->httpClient->get($this->configuration['uri'] . '/api/v1/files/d941efaf-aa68-4dcd-a067-869bfb017a5d/content', [
      'headers' => [
        'API-KEY' => $this->configuration['api_key'],
        'Content-Type' => 'application/json',
      ],
    ]);

    $data = $response->getBody()->getContents();

    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, $data);
    rewind($stream);

    return [
      'type' => 'file',
      'path' => $path,
      'stream' => $stream,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function listContents($directory = '', $recursive = FALSE) {
    return $this->adapter->listContents($directory, $recursive);
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata($path) {
    $response = $this->httpClient->get($this->configuration['uri'] . '/api/v1/files/d941efaf-aa68-4dcd-a067-869bfb017a5d', [
      'headers' => [
        'API-KEY' => $this->configuration['api_key'],
        'Content-Type' => 'application/json',
      ],
    ]);

    $data = $response->getBody()->getContents();
    $data = json_decode($data);

    $this->metadata[$path] = [
      'type' => 'file',
      'path' => $data->filename,
      'timestamp' => $data->created,
      'visibility' => AdapterInterface::VISIBILITY_PUBLIC,
      'size' => $data->size,
      'mimetype' => 'xxx',
    ];

    return $this->metadata[$path];
  }

  /**
   * {@inheritdoc}
   */
  public function getSize($path) {
    return $this->fetchMetadataKey($path, 'size');
  }

  /**
   * {@inheritdoc}
   */
  public function getMimetype($path) {
    return $this->fetchMetadataKey($path, 'mimetype');
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestamp($path) {
    return $this->fetchMetadataKey($path, 'timestamp');
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibility($path) {
    return $this->fetchMetadataKey($path, 'visibility');
  }

  /**
   * Fetches a specific key from metadata.
   *
   * @param string $path
   *   The path to load metadata for.
   * @param string $key
   *   The key in metadata, such as 'mimetype', to load metadata for.
   *
   * @return array
   *   The array of metadata.
   */
  protected function fetchMetadataKey($path, $key) {
    return $this->metadata[$path][$key] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDestinationFilename($destination, $replace) {
    // Add uuid from docstore.
    return $destination;
  }

}
