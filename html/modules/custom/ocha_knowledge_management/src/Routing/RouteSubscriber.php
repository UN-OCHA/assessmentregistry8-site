<?php

namespace Drupal\ocha_knowledge_management\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Force admin theme for add, edit and delete form.
    foreach ($collection->all() as $route) {
      if (strpos($route->getPath(), '/km/add') === 0) {
        $route->setOption('_admin_route', TRUE);
      }

      if (strpos($route->getPath(), '/km/') === 0 && strpos(strrev($route->getPath()), strrev('/edit')) === 0) {
        $route->setOption('_admin_route', TRUE);
      }

      if (strpos($route->getPath(), '/km/') === 0 && strpos(strrev($route->getPath()), strrev('/delete')) === 0) {
        $route->setOption('_admin_route', TRUE);
      }
    }
  }

}
