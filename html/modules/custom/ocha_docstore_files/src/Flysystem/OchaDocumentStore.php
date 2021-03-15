<?php

namespace Drupal\ocha_docstore_files\Flysystem;

/**
 * @file
 * OCHA Document store connector.
 */

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\flysystem\Flysystem\Adapter\MissingAdapter;
use Drupal\flysystem\Plugin\FlysystemPluginInterface;
use Drupal\flysystem\Plugin\FlysystemUrlTrait;
use Drupal\ocha_docstore_files\Flysystem\Adapter\OchaDocumentStoreAdapter;

/**
 * Drupal plugin for the "OCHA Document store" Flysystem adapter.
 *
 * @Adapter(
 *   id = "ocha_docstore"
 * )
 */
class OchaDocumentStore implements FlysystemPluginInterface {

  use FlysystemUrlTrait;

  /**
   * Plugin configuration.
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
   * Constructs an Ftp object.
   *
   * @param array $configuration
   *   Plugin configuration array.
   */
  public function __construct(array $configuration) {
    $this->configuration = $configuration;
    $this->httpClient = \Drupal::httpClient();
  }

  /**
   * {@inheritdoc}
   */
  public function getAdapter() {
    try {
      $adapter = new OchaDocumentStoreAdapter($this->configuration, $this->httpClient);
    }
    catch (\RuntimeException $e) {
      // A problem connecting to the server.
      $adapter = new MissingAdapter();
    }

    return $adapter;
  }

  /**
   * {@inheritdoc}
   */
  public function ensure($force = FALSE) {
    // Try to connect to the me endpoint.
    $result = $this->httpClient->get($this->configuration['uri'] . '/api/v1/me', [
      'headers' => [
        'API-KEY' => $this->configuration['api_key'],
        'Content-Type' => 'application/json',
      ],
    ]);

    if ($result->getStatusCode() === 200) {
      return [
        [
          'severity' => RfcLogLevel::INFO,
          'message' => 'Successfully connected to %uri.',
          'context' => [
            '%uri' => $this->configuration['uri'],
          ],
        ],
      ];
    }

    return [
      [
        'severity' => RfcLogLevel::ERROR,
        'message' => 'There was an error connecting to %uri.',
        'context' => [
          '%uri' => $this->configuration['uri'],
        ],
      ],
    ];
  }

}
