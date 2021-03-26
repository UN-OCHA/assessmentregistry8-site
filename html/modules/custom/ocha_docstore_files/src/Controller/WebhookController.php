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
   */
  protected $entity_type_mapping = [
    'knowledge_management' => [
      'external_entity_type' => 'km',
      'datasource_id' => 'entity:km',
      'index_name' => 'km',
      'field_names' => [],
    ],
    'assessment' => [
      'external_entity_type' => 'assessment',
      'datasource_id' => 'entity:assessment',
      'index_name' => 'assessments',
      'field_names' => [],
    ],
    'assessment_document' => [
      'external_entity_type' => 'assessment_document',
      'datasource_id' => 'entity:assessment',
      'index_name' => 'assessments',
      'field_names' => [
        'field_assessment_data',
        'field_assessment_questionnaire',
        'field_assessment_report',
      ],
    ],
    'ar_assessment_status' => [
      'external_entity_type' => 'assessment_status',
      'datasource_id' => 'entity:assessment',
      'index_name' => 'assessments',
      'field_names' => [
        'field_status',
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
    if (!isset($this->entity_type_mapping[$bundle])) {
      throw new BadRequestHttpException('Unknown document type');
    }

    $index_name = $this->entity_type_mapping[$bundle]['index_name'];
    $datasource_id = $this->entity_type_mapping[$bundle]['datasource_id'];
    $external_entity_type = $this->entity_type_mapping[$bundle]['external_entity_type'];
    $field_names = $this->entity_type_mapping[$bundle]['field_names'];

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
      foreach ($uuids as $u) {
        $solr_ids[] = $u . ':und';
      }

      $index = Index::load($index_name);
      $index->trackItemsUpdated($datasource_id, $solr_ids);

      // phpcs:ignore
      \Drupal::entityTypeManager()->getStorage($external_entity_type)->resetCache($uuids);
    }

    if ($action === 'delete') {
      $index = Index::load($index_name);
      $index->trackItemsDeleted($datasource_id, [$uuid . ':und']);
    }

    // @todo Clear render cache.
    $response = new JsonResponse('OK');
    return $response;
  }

  /**
   * Handle term change.
   */
  protected function handleTerm($bundle, $action, $uuid) {
    if (!isset($this->entity_type_mapping[$bundle])) {
      throw new BadRequestHttpException('Unknown term type');
    }

    if ($action !== 'update' && $action !== 'delete') {
      $response = new JsonResponse('OK');
      return $response;
    }

    $index_name = $this->entity_type_mapping[$bundle]['index_name'];
    $datasource_id = $this->entity_type_mapping[$bundle]['datasource_id'];
    $external_entity_type = $this->entity_type_mapping[$bundle]['external_entity_type'];
    $field_names = $this->entity_type_mapping[$bundle]['field_names'];

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
      foreach ($uuids as $u) {
        $solr_ids[] = $u . ':und';
      }

      $index = Index::load($index_name);
      $index->trackItemsUpdated($datasource_id, $solr_ids);

      // phpcs:ignore
      \Drupal::entityTypeManager()->getStorage($external_entity_type)->resetCache($uuids);
    }

    // @todo Clear render cache.
    $response = new JsonResponse('OK');
    return $response;
  }

}
