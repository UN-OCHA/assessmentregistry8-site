<?php

namespace Drupal\ocha_docstore_files\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'ocha_doc_store_assessment_document' formatter.
 *
 * @FieldFormatter (
 *   id = "ocha_doc_store_assessment_document_default",
 *   label = @Translation("OCHA assessment document default formatter"),
 *   field_types = {
 *     "ocha_doc_store_assessment_document"
 *   }
 * )
 */
class OchaAssessmentDocumentDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'display_accessibility' => FALSE,
      'display_file' => TRUE,
      'display_link' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['display_accessibility'] = [
      '#title' => $this->t('Display accessibility'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('display_accessibility'),
    ];

    $form['display_file'] = [
      '#title' => $this->t('Display file link'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('display_file'),
    ];

    $form['display_link'] = [
      '#title' => $this->t('Display link'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('display_link'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    if ($items->count()) {
      foreach ($items as $delta => $item) {
        $output = [];

        if ($this->getSetting('display_accessibility')) {
          $output[] = $item->accessibility;
        }

        // Publicly Available.
        if ($item->accessibility == 'Publicly Available') {
          if ($this->getSetting('display_link')) {
            if (!empty($item->uri)) {
              $link_text = !empty($item->title) ? $item->title : $item->uri;
              $output[] = Link::fromTextAndUrl($link_text, Url::fromUri($item->uri, []))->toString();
            }
          }
        }

        // Available on Request.
        if ($item->accessibility == 'Available on Request') {
          $output[] = $item->instructions;
        }

        if (!empty($output)) {
          $elements[$delta] = [
            '#markup' => implode('<br>', $output),
          ];
        }
      }
    }

    return $elements;
  }

}
