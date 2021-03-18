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
   *
   */
  public function listen(Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    if (!isset($params['event'])) {
      throw new BadRequestHttpException('Illegal payload');
    }

    switch ($params['event']) {
      case 'knowledge_management:create':
        $index = Index::load('km');
        $uuid = $params['payload']['uuid'];
        $datasource_id = 'entity:km';
        $index->trackItemsInserted($datasource_id, [$uuid]);
        break;

      case 'knowledge_management:update':
        // Trigger re-index for 1 item.
        $index = Index::load('km');
        $uuid = $params['payload']['uuid'];
        $datasource_id = 'entity:km';
        $index->trackItemsUpdated($datasource_id, [$uuid]);
        break;

      case 'knowledge_management:delete':
        // Remove from tracking and index.
        $index = Index::load('km');
        $uuid = $params['payload']['uuid'];
        $datasource_id = 'entity:km';
        $index->trackItemsDeleted($datasource_id, [$uuid]);
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
