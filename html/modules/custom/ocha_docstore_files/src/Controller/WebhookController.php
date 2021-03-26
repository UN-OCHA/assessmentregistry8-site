<?php

namespace Drupal\ocha_docstore_files\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Controller for incoming webhooks.
 */
class WebhookController extends ControllerBase {

  /**
   * Mapping info.
   *
   * @var array
   */
  protected $entityTypeMapping = [
    'knowledge_management' => [
      'external_entity_type' => 'km',
      'datasource' => 'km',
      'index_name' => 'km',
      'field_names' => [],
    ],
    'assessment' => [
      'external_entity_type' => 'assessment',
      'datasource' => 'assessment',
      'index_name' => 'assessments',
      'field_names' => [],
    ],
    'assessment_document' => [
      'external_entity_type' => 'assessment_document',
      'datasource' => 'assessment',
      'index_name' => 'assessments',
      'field_names' => [
        'field_assessment_data',
        'field_assessment_questionnaire',
        'field_assessment_report',
      ],
    ],
    'ar_assessment_status' => [
      'external_entity_type' => 'assessment_status',
      'datasource' => 'assessment',
      'index_name' => 'assessments',
      'field_names' => [
        'field_status',
      ],
    ],
    'organizations' => [
      'external_entity_type' => 'organization',
      'datasource' => 'assessment',
      'index_name' => 'assessments',
      'field_names' => [
        'field_organizations',
        'field_asst_organizations',
        'field_population_types',
      ],
    ],
    'local_coordination_groups' => [
      'external_entity_type' => 'local_group',
      'datasource' => 'assessment',
      'index_name' => 'assessments',
      'field_names' => [
        'field_local_coordination_groups',
      ],
    ],
    'locations' => [
      'external_entity_type' => 'location',
      'datasource' => 'assessment',
      'index_name' => 'assessments',
      'field_names' => [
        'field_locations',
      ],
    ],
    'operations' => [
      'external_entity_type' => 'operation',
      'datasource' => 'assessment',
      'index_name' => 'assessments',
      'field_names' => [
        'field_operations',
      ],
    ],
    // @todo this also exists on km.
    'population_types' => [
      'external_entity_type' => 'population_type',
      'datasource' => 'assessment',
      'index_name' => 'assessments',
      'field_names' => [
        'field_population_types',
      ],
    ],
    'themes' => [
      'external_entity_type' => 'theme',
      'datasource' => 'assessment',
      'index_name' => 'assessments',
      'field_names' => [
        'field_themes',
      ],
    ],
    'ar_context' => [
      'external_entity_type' => 'context',
      'datasource' => 'km',
      'index_name' => 'km',
      'field_names' => [
        'field_context',
      ],
    ],
    'countries' => [
      'external_entity_type' => 'country',
      'datasource' => 'km',
      'index_name' => 'km',
      'field_names' => [
        'field_countries',
      ],
    ],
    'ar_document_type' => [
      'external_entity_type' => 'document_type',
      'datasource' => 'km',
      'index_name' => 'km',
      'field_names' => [
        'field_document_type',
      ],
    ],
    'global_coordination_groups' => [
      'external_entity_type' => 'global_cluster',
      'datasource' => 'km',
      'index_name' => 'km',
      'field_names' => [
        'field_global_clusters',
      ],
    ],
    'ar_hpc_document_repository' => [
      'external_entity_type' => 'hpc_document_repository',
      'datasource' => 'km',
      'index_name' => 'km',
      'field_names' => [
        'field_hpc_document_repository',
      ],
    ],
    'ar_life_cycle_steps' => [
      'external_entity_type' => 'life_cycle_step',
      'datasource' => 'km',
      'index_name' => 'km',
      'field_names' => [
        'field_life_cycle_steps',
      ],
    ],
  ];

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Download a file.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   */
  public function listen(Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    if (!isset($params['event'])) {
      throw new BadRequestHttpException('Illegal payload');
    }

    $parts = explode(':', $params['event']);
    if (count($parts) !== 3) {
      throw new BadRequestHttpException('Bad event');
    }

    $entity_type = $parts[0];
    $bundle = $parts[1];
    $action = $parts[2];
    $uuid = $params['payload']['uuid'];

    if ($entity_type === 'document') {
      return $this->handleDocument($bundle, $action, $uuid);
    }

    if ($entity_type === 'term') {
      return $this->handleTerm($bundle, $action, $uuid);
    }

    $response = new JsonResponse('OK');
    return $response;
  }

  /**
   * Get the request content.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API Request.
   *
   * @return array
   *   Request content.
   *
   * @throw \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Throw a 400 when the request doesn't have a valid JSON content.
   */
  public function getRequestContent(Request $request) {
    $content = json_decode($request->getContent(), TRUE);
    if (empty($content) || !is_array($content)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }
    return $content;
  }

  /**
   * Handle document change.
   */
  protected function handleDocument($bundle, $action, $uuid) {
    if (!isset($this->entityTypeMapping[$bundle])) {
      throw new BadRequestHttpException('Unknown document type');
    }

    $index_name = $this->entityTypeMapping[$bundle]['index_name'];
    $datasource = $this->entityTypeMapping[$bundle]['datasource'];
    $datasource_id = 'entity:' . $datasource;
    $external_entity_type = $this->entityTypeMapping[$bundle]['external_entity_type'];
    $field_names = $this->entityTypeMapping[$bundle]['field_names'];

    $uuids = [];

    // Add new item to index.
    if ($action === 'create') {
      // Ignore assessment documents.
      if ($bundle === 'assessment_document') {
        $response = new JsonResponse('OK');
        return $response;
      }

      $index = Index::load($index_name);
      $index->trackItemsInserted($datasource_id, [$uuid . ':und']);

      $response = new JsonResponse('OK');
      return $response;
    }

    // Track self for updates.
    if ($action === 'update') {
      $uuids[] = $uuid;
    }

    // Find references.
    if (!empty($field_names)) {
      foreach ($field_names as $field_name) {
        $index = Index::load($index_name);
        $query = $index->query();
        $query->addCondition($field_name, $uuid);
        $results = $query->execute();
        foreach ($results as $item) {
          $uuids = array_merge($uuids, $item->getField('uuid')->getValues());
        }
      }
    }

    if (!empty($uuids)) {
      $uuids = array_unique($uuids);
      $solr_ids = [];
      $tags = [];

      foreach ($uuids as $id) {
        $solr_ids[] = $id . ':und';
        $tags[] = $datasource . ':' . $id;
      }

      $index = Index::load($index_name);
      $index->trackItemsUpdated($datasource_id, $solr_ids);

      // phpcs:ignore
      \Drupal::entityTypeManager()->getStorage($external_entity_type)->resetCache($uuids);

      // phpcs:ignore
      \Drupal::service('cache_tags.invalidator')->invalidateTags($tags);
    }

    if ($action === 'delete') {
      $index = Index::load($index_name);
      $index->trackItemsDeleted($datasource_id, [$uuid . ':und']);
    }

    $response = new JsonResponse('OK');
    return $response;
  }

  /**
   * Handle term change.
   */
  protected function handleTerm($bundle, $action, $uuid) {
    if (!isset($this->entityTypeMapping[$bundle])) {
      throw new BadRequestHttpException('Unknown term type');
    }

    if ($action !== 'update' && $action !== 'delete') {
      $response = new JsonResponse('OK');
      return $response;
    }

    $index_name = $this->entityTypeMapping[$bundle]['index_name'];
    $datasource = $this->entityTypeMapping[$bundle]['datasource'];
    $datasource_id = 'entity:' . $datasource;
    $external_entity_type = $this->entityTypeMapping[$bundle]['external_entity_type'];
    $field_names = $this->entityTypeMapping[$bundle]['field_names'];

    $uuids = [];

    // Track referenced items.
    if (!empty($field_names)) {
      foreach ($field_names as $field_name) {
        $index = Index::load($index_name);
        $query = $index->query();
        $query->addCondition($field_name, $uuid);
        $results = $query->execute();
        foreach ($results as $item) {
          $uuids = array_merge($uuids, $item->getField('uuid')->getValues());
        }
      }
    }

    if (!empty($uuids)) {
      $uuids = array_unique($uuids);
      $solr_ids = [];
      $tags = [];

      foreach ($uuids as $id) {
        $solr_ids[] = $id . ':und';
        $tags[] = $datasource . ':' . $id;
      }

      $index = Index::load($index_name);
      $index->trackItemsUpdated($datasource_id, $solr_ids);

      // phpcs:ignore
      \Drupal::entityTypeManager()->getStorage($external_entity_type)->resetCache($uuids);

      // phpcs:ignore
      \Drupal::service('cache_tags.invalidator')->invalidateTags($tags);
    }

    $response = new JsonResponse('OK');
    return $response;
  }

}
