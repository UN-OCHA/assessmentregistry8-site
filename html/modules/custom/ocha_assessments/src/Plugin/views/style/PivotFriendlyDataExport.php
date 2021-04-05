<?php

namespace Drupal\ocha_assessments\Plugin\views\style;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ocha_docstore_files\Plugin\ExternalEntities\StorageClient\RestJson;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\views\ViewExecutable;
use Drupal\views_data_export\Plugin\views\style\DataExport;
use League\Csv\Writer;

/**
 * A style plugin for pivot friendly data export views.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "ar_pivot_friendly_data_export",
 *   title = @Translation("Pivot friendly data export"),
 *   help = @Translation("Configurable row output for data exports."),
 *   display_types = {"data"}
 * )
 */
class PivotFriendlyDataExport extends DataExport {

  /**
   * Field labels should be enabled by default for this Style.
   *
   * @var bool
   */
  protected $defaultFieldLabels = TRUE;

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();
    $options['csv_settings']['contains']['pivot_friendly']['default'] = FALSE;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    switch ($form_state->get('section')) {
      case 'style_options':
        $form['csv_settings']['pivot_friendly'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Pivot friendly'),
          '#description' => $this->t('Duplicate rows for multi-value cells.'),
          '#default_value' => !empty($this->options['csv_settings']['pivot_friendly']),
        ];
        break;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @todo This should implement AttachableStyleInterface once
   * https://www.drupal.org/node/2779205 lands.
   */
  public function attachTo(array &$build, $display_id, Url $url, $title) {
    // Ensure the pretty facets if any are preserved.
    // phpcs:ignore
    $facets_query = \Drupal::routeMatch()->getParameter('facets_query');
    if (!empty($facets_query)) {
      $path = rtrim(parse_url($url->toString(), PHP_URL_PATH), '/');
      $url = Url::fromUserInput($path . '/' . $facets_query, $url->getOptions());
    }

    $url_options = ['absolute' => TRUE];

    // Add any data from the exposed filters etc.
    $input = $this->view->getExposedInput();
    if (!empty($input)) {
      $url_options['query'] = $input;
    }
    $url->setOptions($url_options);
    $url = $url->toString();

    // Add the icon to the view.
    $format = $this->displayHandler->getContentType();
    $format = mb_strtoupper($format);
    if (!empty($this->options['csv_settings']['pivot_friendly'])) {
      $format = $this->t('@format (disaggregated)', ['@format' => $format]);
    }
    $this->view->feedIcons[] = [
      '#theme' => 'export_icon',
      '#url' => $url,
      '#format' => $format,
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => [
            'class' => [
              Html::cleanCssIdentifier($format) . '-feed',
              'views-data-export-feed',
            ],
          ],
        ],
      ],
      '#attached' => [
        'library' => [
          'views_data_export/views_data_export',
        ],
      ],
      '#cache' => [
        'contexts' => ['user'],
      ],
      '#access' => $this->view->access([$this->view->current_display]),
    ];

    // Attach a link to the CSV feed, which is an alternate representation.
    $build['#attached']['html_head_link'][][] = [
      'rel' => 'alternate',
      'type' => $this->displayHandler->getMimeType(),
      'title' => $title,
      'href' => $url,
    ];

    // Add the cache context to the build as well, just in case...
    $build['#cache']['contexts'][] = 'user';
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $format = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';

    if ($format === 'csv' && $this->view->query instanceof SearchApiQuery) {
      return static::renderCsv($this->view, !empty($this->options['csv_settings']['pivot_friendly']));
    }
    else {
      return parent::render();
    }
  }

  /**
   * Builds standard export response.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view to export.
   * @param bool $pivot_friendly
   *   Whether to duplicate rows for cells with multiple values to make the
   *   result pivot friendly.
   *
   * @return string
   *   The CSV output for the results.
   */
  protected static function renderCsv(ViewExecutable $view, $pivot_friendly) {
    $datasources = $view->query->getIndex()->getDatasources();
    $entity_type_id = reset($datasources)->getEntityTypeId();

    $field_definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions($entity_type_id, $entity_type_id);

    // Limit to the fields that have a mapping in the docstore.
    if (RestJson::isExternalEntityCacheable($entity_type_id)) {
      $field_mapping = \Drupal::service('entity_type.manager')
        ->getStorage('external_entity_type')
        ->load($entity_type_id)
        ->getFieldMappings();

      $field_definitions = array_intersect_key($field_definitions, $field_mapping);
    }

    // Remove internal fields.
    unset($field_definitions['id']);
    unset($field_definitions['path']);
    unset($field_definitions['field_author']);
    unset($field_definitions['field_published']);

    // Generate the base row with all the exportable fields initialized.
    $base_row = [];
    foreach ($field_definitions as $field_definition) {
      $field_label = (string) $field_definition->getLabel();

      switch ($field_definition->getType()) {
        case 'entity_reference':
          $target_entity_type_id = $field_definition
            ->getItemDefinition()
            ->getSetting('target_type');

          if ($target_entity_type_id === 'assessment_document') {
            $base_row[$field_label . ' - Title'] = '';
            $base_row[$field_label . ' - File'] = '';
            $base_row[$field_label . ' - Accessibility'] = '';
            $base_row[$field_label . ' - Instructions'] = '';
          }
          else {
            $base_row[$field_label] = '';
          }
          break;

        case 'daterange':
          $base_row[$field_label . ' - Start'] = '';
          $base_row[$field_label . ' - End'] = '';
          break;

        default:
          $base_row[$field_label] = '';
      }
    }

    // Instantiate CSV writer.
    $csv = Writer::createFromFileObject(new \SplTempFileObject());

    // Insert the headers.
    $csv->insertOne(array_keys($base_row));

    // Insert the row for each search result.
    static::processResults($csv, $field_definitions, $base_row, $view->result, $pivot_friendly);

    return $csv->getContent();
  }

  /**
   * Process search results.
   *
   * @param \League\Csv\Writer $csv
   *   The CSV writer.
   * @param array $field_definitions
   *   Field definitions for the entity type.
   * @param array $base_row
   *   Base row with all the cells pre-populated with an empty string. This will
   *   be used to generate the full row to insert.
   * @param \Drupal\search_api\Plugin\views\ResultRow[] $results
   *   Results from the search api query.
   * @param bool $pivot_friendly
   *   Whether to duplicate rows for cells with multiple values to make the
   *   result pivot friendly.
   *
   * @return string
   *   The CSV output for the results.
   */
  public static function processResults(Writer $csv, array $field_definitions, array $base_row, array $results, $pivot_friendly) {
    $total_rows = 0;
    foreach ($results as $result) {
      $entity = $result->_item->getOriginalObject(TRUE)->getEntity();
      $row = [];
      $num_rows = 1;

      foreach ($entity->getFields() as $field_name => $field_item_list) {
        if (!isset($field_definitions[$field_name]) || $field_item_list->isEmpty()) {
          continue;
        }

        $field_definition = $field_definitions[$field_name];
        $field_label = (string) $field_definition->getLabel();
        $field_type = $field_definition->getType();

        $values = [];
        foreach ($field_item_list as $field_item) {
          if ($field_item->isEmpty()) {
            continue;
          }

          switch ($field_type) {
            case 'string':
            case 'string_long':
              $values[] = strip_tags(Html::decodeEntities($field_item->value));
              break;

            case 'list_string':
              $options = $field_item->getFieldDefinition()->getSetting('allowed_values');
              $values[] = $options[$field_item->value] ?? $field_item->value;
              break;

            case 'entity_reference':
              $entity = $field_item->entity;

              // Attached documents.
              if ($entity->getEntityTypeId() === 'assessment_document') {
                // Files.
                $files = [];
                if (!$entity->field_files->isEmpty()) {
                  foreach ($entity->field_files as $file_item) {
                    if (!$file_item->isEmpty()) {
                      $files[] = Url::fromUserInput('/attachments/' . $file_item->media_uuid . '/' . $file_item->filename, [
                        'absolute' => TRUE,
                      ])->toString();
                    }
                  }
                }
                // Accessibility.
                $accessibility = '';
                if (!$entity->field_accessibility->isEmpty()) {
                  $accessibility_key = $entity->field_accessibility->value;
                  $accessibility_options = $entity->field_accessibility->getFieldDefinition()->getSetting('allowed_values');
                  $accessibility = $accessibility_options[$accessibility_key] ?? $accessibility_key;
                }
                // Instructions.
                $instructions = '';
                if (!$entity->field_instructions->isEmpty()) {
                  $instructions = strip_tags(Html::decodeEntities($entity->field_instructions->value));
                }
                $values[] = [
                  'Title' => $entity->label(),
                  'File' => implode(', ', $files),
                  'Accessibility' => $accessibility,
                  'Instructions' => $instructions,
                ];
              }
              else {
                $values[] = $entity->label();
              }
              break;

            case 'daterange':
              // The dates are stored as ISO 8601 dates. We use substr to
              // extract the date part.
              $values[] = [
                'Start' => substr($field_item->value, 0, 10),
                'End' => substr($field_item->end_value ?? '', 0, 10),
              ];
          }
        }

        // Add the values to the proper columns.
        if (!empty($values)) {
          $num_rows *= count($values);
          foreach ($values as $value) {
            if (is_array($value)) {
              foreach ($value as $key => $property) {
                $row[$field_label . ' - ' . $key][] = trim($property);
              }
            }
            else {
              $row[$field_label][] = trim($value);
            }
          }
        }
      }

      // Flatten fields with only one value.
      foreach ($row as $key => $value) {
        if (is_array($value) && count($value) === 1) {
          $row[$key] = reset($value);
        }
      }

      // If the result should be pivot friendly, duplicate the rows.
      if ($pivot_friendly) {
        if ($num_rows > 1) {
          static::duplicateCsvRow($csv, $base_row, $row, array_keys($row), 0);
        }
        else {
          $csv->insertOne(array_merge($base_row, $row));
        }
        $total_rows += $num_rows;
      }
      // Otherwise, concatenate the values.
      else {
        foreach ($row as $key => $value) {
          if (is_array($value)) {
            $row[$key] = implode(', ', $value);
          }
        }
        $csv->insertOne(array_merge($base_row, $row));
        $total_rows++;
      }
    }

    return $total_rows;
  }

  /**
   * Duplicate a row for each value in multi-values cells.
   *
   * @param \League\Csv\Writer $csv
   *   The CSV writer.
   * @param array $base_row
   *   Base row with all the cells pre-populated with an empty string. This will
   *   be used to generate the full row to insert.
   * @param array $row
   *   The row to duplicate.
   * @param array $keys
   *   The row's field names.
   * @param int $index
   *   The index of the field being processed for the duplication.
   */
  public static function duplicateCsvRow(Writer $csv, array $base_row, array $row, array $keys, $index) {
    if ($index === count($keys)) {
      // We insert the duplicate row so that we don't have to return it and
      // we can save quite a lot of memory usage.
      $csv->insertOne(array_merge($base_row, $row));
    }
    else {
      $field = $keys[$index];
      $values = $row[$field];

      if (is_array($values)) {
        foreach ($values as $value) {
          $row[$field] = $value;
          static::duplicateCsvRow($csv, $base_row, $row, $keys, $index + 1);
        }
      }
      else {
        static::duplicateCsvRow($csv, $base_row, $row, $keys, $index + 1);
      }
    }
  }

}
