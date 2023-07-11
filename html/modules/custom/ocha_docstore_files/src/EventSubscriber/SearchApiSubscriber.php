<?php

namespace Drupal\ocha_docstore_files\EventSubscriber;

require '/srv/www/html/modules/contrib/search_api/src/Event/SearchApiEvents.php';

use Drupal\search_api\Event\GatheringPluginInfoEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribing to Search API events.
 */
class SearchApiSubscriber implements EventSubscriberInterface {

  /**
   * Use our own datasource class for docstore external entities.
   */
  public function onGatheringDataSources(GatheringPluginInfoEvent $event) {
    $definitions = &$event->getDefinitions();
    if (isset($definitions['entity:assessment'])) {
      $definitions['entity:assessment']['class'] = 'Drupal\ocha_docstore_files\Plugin\search_api\datasource\DocstoreEntity';
      $definitions['entity:assessment']['deriver'] = 'Drupal\ocha_docstore_files\Plugin\search_api\datasource\DocstoreEntityDeriver';
    }
    if (isset($definitions['entity:km'])) {
      $definitions['entity:km']['class'] = 'Drupal\ocha_docstore_files\Plugin\search_api\datasource\DocstoreEntity';
      $definitions['entity:km']['deriver'] = 'Drupal\ocha_docstore_files\Plugin\search_api\datasource\DocstoreEntityDeriver';
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      SearchApiEvents::GATHERING_DATA_SOURCES  => [
        'onGatheringDataSources',
        -10,
      ],
    ];
  }

}
