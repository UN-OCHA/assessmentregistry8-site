<?php

namespace Drupal\ocha_docstore_files\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\external_entities\Entity\ExternalEntity;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Controller for incoming webhooks.
 */
class WebhookController extends ControllerBase {

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
    $entity_type = $parts[0];
    $action = $parts[1];

    $uuid = $params['payload']['uuid'];
    $index = FALSE;
    $datasource_id = FALSE;
    $external_entity_type = FALSE;

    // Get index and data source.
    switch ($entity_type) {
      case 'knowledge_management':
        $index = Index::load('km');
        $datasource_id = 'entity:km';
        $external_entity_type = 'km';
        break;

      case 'assessment':
        $index = Index::load('assessments');
        $datasource_id = 'entity:assessment';
        $external_entity_type = 'assessment';
        break;

      case 'assessment_document':
        $index = Index::load('assessments');
        $datasource_id = 'entity:assessment';
        $external_entity_type = 'assessment_document';
        break;

      case 'term':
        $datasource_id = 'organization';
        $external_entity_type = 'organization';
        break;

    }

    if (!$datasource_id) {
      $response = new JsonResponse('Ignored');
      return $response;
    }

    // Trigger action.
    switch ($action) {
      case 'create':
        \Drupal::entityTypeManager()->getStorage($external_entity_type)->resetCache([$uuid]);
        $entity = \Drupal::entityTypeManager()->getStorage($external_entity_type)->load($uuid);
        search_api_entity_insert($entity);
        break;

      case 'update':
        \Drupal::entityTypeManager()->getStorage($external_entity_type)->resetCache([$uuid]);
        $entity = \Drupal::entityTypeManager()->getStorage($external_entity_type)->load($uuid);
        search_api_entity_update($entity);
        break;

      case 'delete':
        $index->trackItemsDeleted($datasource_id, [$uuid]);
        \Drupal::entityTypeManager()->getStorage($external_entity_type)->resetCache([$uuid]);
        break;

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

}
