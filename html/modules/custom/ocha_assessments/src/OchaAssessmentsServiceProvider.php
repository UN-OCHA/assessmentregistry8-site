<?php

namespace Drupal\ocha_assessments;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Overrides services.
 */
class OchaAssessmentsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Use our own CSV encoder so we can handle multiple values fields.
    $definition = $container->getDefinition('csv_serialization.encoder.csv');
    $definition->setClass('Drupal\ocha_assessments\Encoder\CsvEncoder');
  }

}
