<?php

namespace Drupal\ocha_docstore_files\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Field\WidgetBase;

/**
 * Plugin implementation of the 'ocha_doc_store_assessment_document' widget.
 *
 * @FieldWidget (
 *   id = "ocha_doc_store_assessment_document_widget",
 *   label = @Translation("OCHA assessment document widget"),
 *   field_types = {
 *     "ocha_doc_store_assessment_document"
 *   }
 * )
 */
class OchaAssessmentDocumentWidget extends WidgetBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $state_name = $this->fieldDefinition->getName() . '[' . $delta . '][accessibility]';
    $element = [
      '#type' => 'fieldset',
      '#title' => $this->fieldDefinition->getLabel(),
    ];

    $element['document'] = [];
    $element['document']['#title'] = $this->t('Document');
    $element['document']['#process'] = array_merge($element_info['#process'],
    [[get_class($this), 'process']]);

    $element['accessibility'] = [
      '#type' => 'select',
      '#title' => $this->t('Accessibility'),
      '#options' => [
        'Not Applicable' => $this->t('Not Applicable'),
        'Publicly Available' => $this->t('Publicly Available'),
        'Available on Request' => $this->t('Available on Request'),
        'Not Available' => $this->t('Not Available'),
      ],
      '#default_value' => $items[$delta]->accessibility,
      '#weight' => -10,
    ];

    $element['uri'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      '#default_value' => $items[$delta]->uri,
      '#maxlength' => 2048,
      '#link_type' => $this->getFieldSetting('link_type'),
      '#description' => $this->t('This must be an external URL such as %url.', ['%url' => 'http://example.com']),
      '#weight' => 20,
      '#states' => [
        'visible' => [
          ':input[name="' . $state_name . '"]' => ['value' => 'Publicly Available'],
        ],
      ],
    ];

    $element['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#default_value' => $items[$delta]->title,
      '#maxlength' => 255,
      '#weight' => 21,
      '#states' => [
        'visible' => [
          ':input[name="' . $state_name . '"]' => ['value' => 'Publicly Available'],
        ],
      ],
    ];

    $element['instructions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Instructions'),
      '#default_value' => $items[$delta]->instructions,
      '#maxlength' => 255,
      '#weight' => 21,
      '#rows' => 5,
      '#attributes' => [
        'class' => [
          'js-text-full',
          'text-full',
        ],
      ],
      '#states' => [
        'visible' => [
          ':input[name="' . $state_name . '"]' => ['value' => 'Available on Request'],
        ],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $new_values = [];
    foreach ($values as &$value) {
      $value['fids'] = $value['document']['fids'];
      $value['display'] = $value['document']['display'];
      $value['description'] = $value['document']['description'];
      unset($value['document']);

      if (empty($value['fids'])) {
        $new_value = $value;
        $new_value['target_id'] = 0;
        unset($new_value['fids']);
        $new_values[] = $new_value;
      }
      else {
        foreach ($value['fids'] as $fid) {
          $new_value = $value;
          $new_value['target_id'] = $fid;
          unset($new_value['fids']);
          $new_values[] = $new_value;
        }
      }
    }

    return $new_values;
  }

  /**
   * Form API callback: Processes a file_generic field element.
   *
   * Expands the file_generic type to include the description and display
   * fields.
   *
   * This method is assigned as a #process callback in formElement() method.
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    //$element = parent::process($element, $form_state, $form);
    $state_name = $element['#field_name'] . '[' . $element['#delta'] . '][accessibility]';

    foreach ([
      'upload',
      'description',
      'upload_button',
      'remove_button',
    ] as $key) {
      if (isset($element[$key])) {
        $element[$key]['#states'] = [
          'visible' => [
            ':input[name="' . $state_name . '"]' => ['value' => 'Publicly Available'],
          ],
        ];
      }
    }

    // Add to rendered file.
    if (isset($element['#value']['target_id'])) {
      if (isset($element['file_' . $element['#value']['target_id']])) {
        $element['file_' . $element['#value']['target_id']]['filename']['#type'] = 'container';
        $element['file_' . $element['#value']['target_id']]['filename']['#states'] = [
          'visible' => [
            ':input[name="' . $state_name . '"]' => ['value' => 'Publicly Available'],
          ],
        ];
      }
    }

    return $element;
  }

  /**
   * Overrides \Drupal\Core\Field\WidgetBase::formMultipleElements().
   *
   * Special handling for draggable multiple widgets and 'add more' button.
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $parents = $form['#parents'];

    // Load the items for form rebuilds from the field state as they might not
    // be in $form_state->getValues() because of validation limitations. Also,
    // they are only passed in as $items when editing existing entities.
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    if (isset($field_state['items'])) {
      $items->setValue($field_state['items']);
    }

    // Determine the number of widgets to display.
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    switch ($cardinality) {
      case FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED:
        $max = count($items);
        $is_multiple = TRUE;
        break;

      default:
        $max = $cardinality - 1;
        $is_multiple = ($cardinality > 1);
        break;
    }

    $title = $this->fieldDefinition->getLabel();
    $description = $this->getFilteredDescription();

    $elements = [];

    $delta = 0;
    // Add an element for every existing item.
    foreach ($items as $item) {
      $element = [
        '#title' => $title,
        '#description' => $description,
      ];
      $element = $this->formSingleElement($items, $delta, $element, $form, $form_state);

      if ($element) {
        // Input field for the delta (drag-n-drop reordering).
        if ($is_multiple) {
          // We name the element '_weight' to avoid clashing with elements
          // defined by widget.
          $element['_weight'] = [
            '#type' => 'weight',
            '#title' => $this->t('Weight for row @number', ['@number' => $delta + 1]),
            '#title_display' => 'invisible',
            // Note: this 'delta' is the FAPI #type 'weight' element's property.
            '#delta' => $max,
            '#default_value' => $item->_weight ?: $delta,
            '#weight' => 100,
          ];
        }

        $elements[$delta] = $element;
        $delta++;
      }
    }

    $empty_single_allowed = ($cardinality == 1 && $delta == 0);
    $empty_multiple_allowed = ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED || $delta < $cardinality) && !$form_state->isProgrammed();

    // Add one more empty row for new uploads except when this is a programmed
    // multiple form as it is not necessary.
    if ($empty_single_allowed || $empty_multiple_allowed) {
      // Create a new empty item.
      $items->appendItem();
      $element = [
        '#title' => $title,
        '#description' => $description,
      ];
      $element = $this->formSingleElement($items, $delta, $element, $form, $form_state);
      if ($element) {
        $element['document']['#required'] = ($element['document']['#required'] && $delta == 0);
        $elements[$delta] = $element;
      }
    }

    if ($is_multiple) {
      // The group of elements all-together need some extra functionality after
      // building up the full list (like draggable table rows).
      $elements['#file_upload_delta'] = $delta;
      $elements['#type'] = 'details';
      $elements['#open'] = TRUE;
      $elements['#theme'] = 'file_widget_multiple';
      $elements['#theme_wrappers'] = ['details'];
      $elements['#process'] = [[get_class($this), 'processMultiple']];
      $elements['#title'] = $title;

      $elements['#description'] = $description;
      $elements['#field_name'] = $field_name;
      $elements['#language'] = $items->getLangcode();
      // The field settings include defaults for the field type. However, this
      // widget is a base class for other widgets (e.g., ImageWidget) that may
      // act on field types without these expected settings.
      $field_settings = $this->getFieldSettings() + ['display_field' => NULL];
      $elements['#display_field'] = (bool) $field_settings['display_field'];

      // Add some properties that will eventually be added to the file upload
      // field. These are added here so that they may be referenced easily
      // through a hook_form_alter().
      $elements['#file_upload_title'] = $this->t('Add a new file');
      $elements['#file_upload_description'] = [
        '#theme' => 'file_upload_help',
        '#description' => '',
        '#upload_validators' => $elements[0]['#upload_validators'],
        '#cardinality' => $cardinality,
      ];
    }

    return $elements;
  }

}
