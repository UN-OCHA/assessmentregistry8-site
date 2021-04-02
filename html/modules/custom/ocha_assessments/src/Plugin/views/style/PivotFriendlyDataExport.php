<?php

namespace Drupal\ocha_assessments\Plugin\views\style;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Url;
use Drupal\views_data_export\Plugin\views\style\DataExport;

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

  use RedirectDestinationTrait;

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
    $options['pivot_friendly'] = ['default' => FALSE];
    $options['pivot_friendly_separator'] = ['default' => '||'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    switch ($form_state->get('section')) {
      case 'style_options':
        $form['pivot_friendly'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Pivot friendly'),
          '#description' => $this->t('Duplicate rows for multi-value cells.'),
          '#default_value' => !empty($this->options['pivot_friendly']),
        ];
        $form['pivot_friendly_separator'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Multi-value separator'),
          '#description' => $this->t('Separator for aggregated values.'),
          '#default_value' => $this->options['pivot_friendly_separator'],
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
      $url = Url::fromUserInput($url->toString() . '/' . $facets_query);
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
    if (!empty($this->options['pivot_friendly'])) {
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
    ];

    // Attach a link to the CSV feed, which is an alternate representation.
    $build['#attached']['html_head_link'][][] = [
      'rel' => 'alternate',
      'type' => $this->displayHandler->getMimeType(),
      'title' => $title,
      'href' => $url,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    // This is pretty close to the parent implementation.
    // Difference (noted below) stems from not being able to get anything other
    // than json rendered even when the display was set to export csv or xml.
    $rows = [];
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $rows[] = $this->view->rowPlugin->render($row);
    }

    unset($this->view->row_index);

    // Duplicate the rows for each value of multi-value cells.
    if (!empty($this->options['pivot_friendly'])) {
      $rows = $this->duplicateRows($rows);
    }

    // Get the format configured in the display or fallback to json.
    // We intentionally implement this different from the parent method because
    // $this->displayHandler->getContentType() will always return json due to
    // the request's header (i.e. "accept:application/json") and
    // we want to be able to render csv or xml data as well in accordance with
    // the data export format configured in the display.
    $format = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';

    // If data is being exported as a CSV we give the option to not use the
    // Symfony normalize method which increases performance on large data sets.
    // This option can be configured in the CSV Settings section of the data
    // export.
    if ($format === 'csv' && $this->options['csv_settings']['use_serializer_encode_only'] == 1) {
      return $this->serializer->encode($rows, $format, ['views_style_plugin' => $this]);
    }
    else {
      return $this->serializer->serialize($rows, $format, ['views_style_plugin' => $this]);
    }

  }

  /**
   * Handle cells with multiple values, duplicting rows for each.
   *
   * @param array $data
   *   List of rows.
   *
   * @return array
   *   List of rows after duplication.
   */
  public function duplicateRows(array $data) {
    $separator = $this->options['pivot_friendly_separator'];

    // For each field used in the view, check if the values are aggregated.
    $aggregated = [];
    foreach ($this->view->field as $key => $field) {
      $options = $field->options ?? [];

      $aggregated[$key] = !empty($field->multiple) &&
        !empty($options['group_rows']) &&
        !empty($options['multi_type']) &&
        !empty($options['separator']) &&
        $options['multi_type'] === 'separator' &&
        $options['separator'] === $separator;
    }

    // Duplicate the rows.
    $rows = [];
    foreach ($data as $row) {
      // Split the aggregsated values.
      foreach ($row as $key => $value) {
        if (!is_array($value) && !empty($aggregated[$key])) {
          $row[$key] = explode($separator, $value);
        }
      }
      $rows = array_merge($rows, $this->duplicateRow($row, 0));
    }
    return $rows;
  }

  /**
   * Duplicate a row for each value of the cell at the given index.
   *
   * @param array $row
   *   Row.
   * @param int $index
   *   Index of the cell.
   *
   * @return array
   *   List of rows, one for each value of the cell at the given index.
   */
  public function duplicateRow(array $row, $index = 0) {
    if ($index >= count($row)) {
      return [$row];
    }

    $key = array_keys($row)[$index];
    $values = $row[$key];

    // If the cell doesn't contain multiple values, check the next cell.
    if (!is_array($values)) {
      $rows = $this->duplicateRow($row, $index + 1);
    }
    // Otherwise recursively duplicate the row for each value.
    else {
      $rows = [];
      foreach ($values as $value) {
        $new_row = $row;
        $new_row[$key] = $value;
        $rows = array_merge($rows, $this->duplicateRow($new_row, $index + 1));
      }
    }

    return $rows;
  }

}
