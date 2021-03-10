<?php

namespace Drupal\ocha_knowledge_management\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;

/**
 * Plugin implementation of the 'ocha_knowledge_management_lfs' formatter.
 *
 * @FieldFormatter (
 *   id = "ocha_knowledge_management_lfs",
 *   label = @Translation("Life cycle steps formatter"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class LifeCycleStepsFormatter extends EntityReferenceLabelFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $output_as_link = $this->getSetting('link');

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $term) {
      $label = $term->label();
      if ($term->bundle() === 'life_cycle_steps') {
        if (!empty($term->get('field_display_label')->value)) {
          $label = $term->get('field_display_label')->value;
        }
      }
      elseif ($term->field_parent) {
        $parents = $term->field_parent->referencedEntities();
        if (count($parents)) {
          $label = $parents[0]->label() . ' >> ' . $label;
        }
      }

      if ($output_as_link && !$term->isNew()) {
        try {
          $uri = $term->toUrl();
        }
        catch (UndefinedLinkTemplateException $e) {
          $output_as_link = FALSE;
        }
      }

      if ($output_as_link && isset($uri) && !$term->isNew()) {
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $label,
          '#url' => $uri,
          '#options' => $uri->getOptions(),
        ];

        if (!empty($items[$delta]->_attributes)) {
          $elements[$delta]['#options'] += [
            'attributes' => [],
          ];
          $elements[$delta]['#options']['attributes'] += $items[$delta]->_attributes;

          unset($items[$delta]->_attributes);
        }
      }
      else {
        $elements[$delta] = [
          '#plain_text' => $label,
        ];
      }

      $elements[$delta]['#cache']['tags'] = $term->getCacheTags();
    }

    return $elements;
  }

}
