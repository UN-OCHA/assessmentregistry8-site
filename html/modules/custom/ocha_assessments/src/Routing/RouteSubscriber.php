<?php

namespace Drupal\ocha_assessments\Routing;

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
      if (strpos($route->getPath(), '/assessment/add') === 0) {
        $route->setOption('_admin_route', TRUE);
      }

      if (strpos($route->getPath(), '/assessment/') === 0 && strpos(strrev($route->getPath()), strrev('/edit')) === 0) {
        $route->setOption('_admin_route', TRUE);
      }

      if (strpos($route->getPath(), '/assessment/') === 0 && strpos(strrev($route->getPath()), strrev('/delete')) === 0) {
        $route->setOption('_admin_route', TRUE);
      }
    }
  }

}
