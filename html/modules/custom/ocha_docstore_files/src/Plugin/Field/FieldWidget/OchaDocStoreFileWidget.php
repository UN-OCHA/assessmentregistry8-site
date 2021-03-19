<?php

namespace Drupal\ocha_docstore_files\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Plugin implementation of the 'ocha_doc_store_file_widget' widget.
 *
 * @FieldWidget(
 *   id = "ocha_doc_store_file_widget",
 *   module = "ocha_docstore_files",
 *   label = @Translation("OCHA Document store file widget"),
 *   multiple_values = true,
 *   field_types = {
 *     "ocha_doc_store_file"
 *   }
 * )
 */
class OchaDocStoreFileWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ElementInfoManagerInterface $element_info) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->elementInfo = $element_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['third_party_settings'], $container->get('element_info'));
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'endpoint' => 'http://docstore.local.docksal/api/v1/files',
      'api-key' => 'abcd',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API end point'),
      '#default_value' => $this->getSetting('endpoint'),
      '#weight' => 17,
    ];

    $element['api-key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#default_value' => $this->getSetting('api-key'),
      '#weight' => 18,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = $this->t('Progress indicator: @progress_indicator', ['@progress_indicator' => $this->getSetting('progress_indicator')]);
    $summary[] = $this->t('Endpoint: @endpoint', ['@endpoint' => ocha_docstore_files_get_endpoint_base($this->getSetting('endpoint'))]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    return $this->formMultipleElements($items, $form, $form_state);
  }

  /**
   * Overrides \Drupal\Core\Field\WidgetBase::formMultipleElements().
   *
   * Special handling for draggable multiple widgets and 'add more' button.
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $parents = $form['#parents'];

    // Needed for inline entity form.
    $trigger = $form_state->getTriggeringElement();
    if ($trigger && isset($trigger['#method'])) {
      $this->extractFormValues($items, $form, $form_state);
    }

    $field_state = static::getWidgetState($form['#parents'], $field_name, $form_state);

    if (!isset($field_state['queued_files'])) {
      $field_state['queued_files'] = [];
    }
    if (!isset($field_state['deleted_files'])) {
      $field_state['deleted_files'] = [];
    }
    if (!isset($field_state['existing_files'])) {
      $field_state['existing_files'] = $items->getValue();
      static::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
    }

    if (isset($field_state['ajax_wrapper_id'])) {
      $ajax_wrapper_id = $field_state['ajax_wrapper_id'];
    }
    else {
      $ajax_wrapper_id = Html::getUniqueId('ajax-wrapper');
      $field_state['ajax_wrapper_id'] = $ajax_wrapper_id;
      static::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
    }

    $elements = [
      '#process' => [[get_class($this), 'process']],
      '#queue' => ['test'],
      'existing_files' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Existing files'),
        '#access' => FALSE,
      ],
      'deleted_files' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Files to be removed'),
        '#access' => FALSE,
      ],
      'queued_files' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Queued files'),
        '#access' => FALSE,
      ],
      'add_files' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Add files'),
        'from_local' => [
          '#type' => 'fieldset',
          '#title' => $this->t('Local file(s)'),
          'local_file' => [
            '#type' => 'file',
            '#name' => 'files[]',
            '#multiple' => TRUE,
            '#error_no_message' => TRUE,
            '#limit_validation_errors' => [],
          ],
          'upload' => [
            '#type' => 'button',
            '#method' => 'local_files',
            '#value' => $this->t('Upload file(s)'),
            '#ajax' => [
              'callback' => [get_called_class(), 'rebuildWidgetFormLocal'],
              'options' => [
                'query' => [
                  'element_parents' => implode('/', $parents),
                  'ajax_wrapper_id' => $ajax_wrapper_id,
                ],
              ],
              'wrapper' => $ajax_wrapper_id,
              'effect' => 'fade',
              'progress' => [
                'type' => 'throbber',
                'message' => $this->t('Uploading files'),
              ],
            ],
          ],
        ],
        'from_uri' => [
          '#type' => 'fieldset',
          '#title' => $this->t('External URL(s)'),
          'uri' => [
            '#type' => 'textarea',
            '#rows' => 2,
            '#description' => $this->t('One URL per line.'),
            '#default_value' => '',
            '#limit_validation_errors' => [],
          ],
          'fetch' => [
            '#type' => 'button',
            '#method' => 'fetch_files',
            '#value' => $this->t('Add file(s)'),
            '#ajax' => [
              'callback' => [get_called_class(), 'rebuildWidgetFormRemote'],
              'options' => [
                'query' => [
                  'element_parents' => implode('/', $parents),
                  'ajax_wrapper_id' => $ajax_wrapper_id,
                ],
              ],
              'wrapper' => $ajax_wrapper_id,
              'effect' => 'fade',
              'progress' => [
                'type' => 'throbber',
                'message' => $this->t('Fetching files'),
              ],
            ],
          ],
        ],
      ],
    ];

    $delta = 0;
    // Add an element for every existing item.
    foreach ($field_state['existing_files'] as $item) {
      // Add existing files.
      if (isset($item['media_uuid'])) {
        $elements['existing_files']['#access'] = TRUE;
        $elements['existing_files'][$delta] = [
          'filelink' => [
            '#type' => 'link',
            '#title' => $item['filename'],
            '#url' => Url::fromUri($item['uri']),
          ],
          'remove' => [
            '#type' => 'button',
            '#value' => $this->t('Remove file'),
            '#name' => 'remove-existing-' . $delta,
            '#method' => 'remove_existing',
          ],
        ];
      }

      $delta++;
    }

    // Add deleted files.
    foreach ($field_state['deleted_files'] as $delta => $item) {
      $elements['deleted_files']['#access'] = TRUE;
      $elements['deleted_files'][] = [
        'filelink' => [
          '#type' => 'link',
          '#title' => $item['filename'],
          '#url' => Url::fromUri($item['uri']),
        ],
        'restore' => [
          '#type' => 'button',
          '#value' => 'Restore deleted file',
          '#name' => 'restore-deleted-' . $delta,
          '#method' => 'restore_deleted',
        ],
      ];
    }

    // Add all queued files.
    foreach ($field_state['queued_files'] as $delta => $queued_file) {
      $elements['queued_files']['#access'] = TRUE;
      if (isset($queued_file['uri'])) {
        $elements['queued_files'][] = [
          'filelink' => [
            '#type' => 'link',
            '#title' => $queued_file['filename'],
            '#url' => Url::fromUri($queued_file['uri']),
          ],
          'remove' => [
            '#type' => 'button',
            '#value' => 'Remove queued file',
            '#name' => 'remove-queued-' . $delta,
            '#method' => 'remove_queue',
          ],
        ];
      }
      else {
        $elements['queued_files'][] = [
          'filelink' => [
            '#type' => 'markup',
            '#markup' => $queued_file['filename'],
          ],
          'remove' => [
            '#type' => 'button',
            '#value' => 'Remove queued file',
            '#name' => 'remove-queued-' . $delta,
            '#method' => 'remove_queue',
          ],
        ];
      }
    }

    $elements['#tree'] = TRUE;
    $elements['#prefix'] = '<div class="xyzzy" id="' . $ajax_wrapper_id . '">';
    $elements['#suffix'] = '</div>';
    $elements['#id'] = $ajax_wrapper_id;

    return $elements;
  }

  /**
   * Form API callback.
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $ajax_wrapper_id = $element['#id'];
    $element['add_files']['from_uri']['fetch']['#ajax']['options'] = [
      'query' => [
        'element_parents' => implode('/', $element['#array_parents']),
        'ajax_wrapper_id' => $ajax_wrapper_id,
      ],
    ];

    $element['add_files']['from_local']['upload']['#ajax']['options'] = [
      'query' => [
        'element_parents' => implode('/', $element['#array_parents']),
        'ajax_wrapper_id' => $ajax_wrapper_id,
      ],
    ];

    $fieldsets = [
      'existing_files' => 'remove',
      'queued_files' => 'remove',
      'deleted_files' => 'restore',
    ];

    foreach ($fieldsets as $fieldset => $child) {
      if (isset($element[$fieldset]) && $element[$fieldset]['#access']) {
        foreach ($element[$fieldset] as $delta => $existing_file) {
          if (strpos($delta, '#') !== FALSE) {
            continue;
          }

          $element[$fieldset][$delta][$child]['#index'] = $delta;

          $element[$fieldset][$delta][$child]['#ajax'] = [
            'callback' => [get_called_class(), 'rebuildWidgetForm'],
            'options' => [
              'query' => [
                'element_parents' => implode('/', $element['#array_parents']),
                'ajax_wrapper_id' => $ajax_wrapper_id,
              ],
            ],
            'wrapper' => $element['add_files']['from_local']['upload']['#ajax']['wrapper'],
            'effect' => 'fade',
            'progress' => [
              'type' => 'throbber',
              'message' => NULL,
            ],
          ];
        }
      }
    }

    return $element;
  }

  /**
   * Rebuild form.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response of the ajax upload.
   */
  public static function rebuildWidgetFormRemote(array &$form, FormStateInterface &$form_state, Request $request) {
    $form_parents = explode('/', $request->query->get('element_parents'));

    // Remove entered URLs.
    $form[$form_parents[0]]['widget']['add_files']['from_uri']['uri']['#value'] = '';

    return static::rebuildWidgetForm($form, $form_state, $request);
  }

  /**
   * Rebuild form.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response of the ajax upload.
   */
  public static function rebuildWidgetFormLocal(array &$form, FormStateInterface &$form_state, Request $request) {
    return static::rebuildWidgetForm($form, $form_state, $request);
  }

  /**
   * Rebuild form.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response of the ajax upload.
   */
  public static function rebuildWidgetForm(array &$form, FormStateInterface &$form_state, Request $request) {
    $form_parents = explode('/', $request->query->get('element_parents'));
    $ajax_wrapper_id = $request->query->get('ajax_wrapper_id');

    // Sanitize form parents before using them.
    $form_parents = array_filter($form_parents, [Element::class, 'child']);

    // Retrieve the element to be rendered.
    $form = NestedArray::getValue($form, $form_parents);

    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');

    // Rebuild complete element form, so remote files are added to queued_files.
    $output = $renderer->renderRoot($form);

    $response = new AjaxResponse();
    $response->setAttachments($form['#attached']);

    return $response->addCommand(new ReplaceCommand('#' . $ajax_wrapper_id, $output));
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $field_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
    $uuids = [];

    // Add existing files.
    if (isset($field_state['existing_files'])) {
      $uuids = $field_state['existing_files'];
    }

    // Process queued files.
    if (isset($field_state['queued_files'])) {
      foreach ($field_state['queued_files'] as &$queued_file) {
        // File upload.
        if (isset($queued_file['tmp_path'])) {
          $contents = file_get_contents($queued_file['tmp_path']);
          // phpcs:ignore
          $response = \Drupal::httpClient()->request(
            'POST',
            ocha_docstore_files_get_endpoint_base($this->getSetting('endpoint')),
            [
              'body' => json_encode([
                'filename' => $queued_file['filename'],
                'data' => base64_encode($contents),
                'private' => FALSE,
              ]),
              'headers' => [
                'API-KEY' => ocha_docstore_files_get_endpoint_apikey($this->getSetting('api-key')),
              ],
            ]
          );

          $body = $response->getBody() . '';
          $body = json_decode($body);

          // @todo Check return value.
          if ($body->uuid) {
            $uuids[] = $body->media_uuid;
            $field_state['existing_files'][] = $body->media_uuid;
          }

          // Avoid duplicate uploads.
          unset($queued_file['tmp_path']);
        }

        // Remote file.
        if (isset($queued_file['uri'])) {
          // phpcs:ignore
          $response = \Drupal::httpClient()->request(
            'POST',
            ocha_docstore_files_get_endpoint_base($this->getSetting('endpoint')),
            [
              'body' => json_encode([
                'filename' => $queued_file['filename'],
                'uri' => $queued_file['uri'],
                'private' => FALSE,
              ]),
              'headers' => [
                'API-KEY' => ocha_docstore_files_get_endpoint_apikey($this->getSetting('api-key')),
              ],
            ]
          );

          $body = $response->getBody() . '';
          $body = json_decode($body);

          // @todo Check return value.
          if ($body->uuid) {
            $uuids[] = $body->media_uuid;
            $field_state['existing_files'][] = $body->media_uuid;
          }

          // Avoid duplicate uploads.
          unset($queued_file['uri']);
        }
      }
    }

    static::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);

    return $uuids;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    parent::extractFormValues($items, $form, $form_state);

    $trigger = $form_state->getTriggeringElement();
    if (!$trigger || !isset($trigger['#method'])) {
      return;
    }

    $field_name = $this->fieldDefinition->getName();
    $field_state = static::getWidgetState($form['#parents'], $field_name, $form_state);

    if (!isset($field_state['queued_files'])) {
      $field_state['queued_files'] = [];
    }
    if (!isset($field_state['deleted_files'])) {
      $field_state['deleted_files'] = [];
    }
    if (!isset($field_state['existing_files'])) {
      $field_state['existing_files'] = [];
    }

    $field_state['items'] = $items->getValue();

    $element_parents = $trigger['#parents'];
    array_pop($element_parents);

    switch ($trigger['#method']) {
      case 'remove_existing':
        $index = $trigger['#index'];

        if (isset($field_state['existing_files'][$index])) {
          $field_state['deleted_files'][] = $field_state['existing_files'][$trigger['#index']];
          unset($field_state['existing_files'][$index]);

          // Re-key the array.
          $field_state['existing_files'] = array_values($field_state['existing_files']);
        }

        break;

      case 'restore_deleted':
        $index = $trigger['#index'];

        if (isset($field_state['deleted_files'][$index])) {
          $field_state['existing_files'][] = $field_state['deleted_files'][$trigger['#index']];
          unset($field_state['deleted_files'][$index]);

          // Re-key the array.
          $field_state['deleted_files'] = array_values($field_state['deleted_files']);
        }

        break;

      case 'fetch_files':
        $element_parents[] = 'uri';
        $from_uri = $form_state->getValue($element_parents);
        if (!empty($from_uri)) {
          $uris = explode("\n", $from_uri);
          foreach ($uris as $uri) {
            $uri = trim($uri);
            if (empty($uri)) {
              continue;
            }

            $field_state['queued_files'][] = [
              'filename' => basename(parse_url($uri, PHP_URL_PATH)),
              'uri' => $uri,
            ];
          }
        }

        // Clear entered URIs.
        $form_state->setValue($element_parents, '');

        break;

      case 'local_files':
        // Get uploaded files.
        // phpcs:ignore
        $all_files = \Drupal::request()->files->get('files', []);

        // Add files without uploading.
        foreach ($all_files as $field) {
          foreach ($field as $file_info) {
            // Move file if it's rally uploaded.
            if (is_uploaded_file($file_info->getRealPath())) {
              $destination = 'temporary://' . microtime();
              if (file_prepare_directory($destination, FILE_CREATE_DIRECTORY)) {
                $destination .= '/' . trim($file_info->getClientOriginalName(), '.');
                if (move_uploaded_file($file_info->getRealPath(), $destination)) {
                  $field_state['queued_files'][] = [
                    'filename' => trim($file_info->getClientOriginalName(), '.'),
                    'tmp_path' => $destination,
                  ];
                }
              }
            }
          }
        }

        break;

      case 'remove_queue':
        $index = $trigger['#index'];

        if (isset($field_state['queued_files'][$index])) {
          unset($field_state['queued_files'][$index]);

          // Re-key the array.
          $field_state['queued_files'] = array_values($field_state['queued_files']);
        }

        break;
    }

    static::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
  }

}
