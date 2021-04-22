<?php

namespace Drupal\ocha_assessments\Form;

use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class OchaAssessmentsBulkImport for bulk imports.
 */
class OchaAssessmentsBulkImport extends FormBase {

  /**
   * File system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ocha_assessments_bulk_import';
  }

  /**
   * Class constructor.
   */
  public function __construct(FileSystem $fileSystem) {
    $this->fileSystem = $fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['xlsx_file'] = [
      '#type' => 'file',
      '#title' => $this->t('Xlsx file'),
      '#description' => $this->t('Excel file containing assessments to import'),
    ];

    $form['skip_rows'] = [
      '#type' => 'radios',
      '#options' => [
        'no' => $this->t('First row contains row headers, other rows contain data.'),
        'yes' => $this->t('Import is using the template file, row 9 is the first row with data.'),
      ],
      '#title' => $this->t('Data file'),
      '#default_value' => 'yes',
      '#required' => TRUE,
    ];

    $operations = ocha_assessments_load_reference_data_from_docstore('operations');
    $form['operation'] = [
      '#type' => 'select',
      '#options' => $operations,
      '#title' => $this->t('Country'),
      '#description' => $this->t('Country'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $all_files = $this->getRequest()->files->get('files', []);
    if (!empty($all_files['xlsx_file'])) {
      $file_upload = $all_files['xlsx_file'];
      if ($file_upload->isValid()) {
        $form_state->setValue('xlsx_file', $file_upload->getRealPath());
        return;
      }
    }

    $form_state->setErrorByName('xlsx_file', $this->t('The file could not be uploaded.'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $validators = ['file_validate_extensions' => ['xlsx']];
    $file = file_save_upload('xlsx_file', $validators, FALSE, 0);
    if (!$file) {
      return;
    }

    $filename = $this->fileSystem->realpath($file->destination);

    $reader = new Xlsx();
    $reader->setReadDataOnly(TRUE);
    $reader->setLoadSheetsOnly([
      'Assessments',
      'assessments',
    ]);
    $spreadsheet = $reader->load($filename);

    // Assume headers on first row.
    $header_row = 1;

    $header = [];
    $header_lowercase = [];

    $worksheet = $spreadsheet->getActiveSheet();
    if ($worksheet->getHighestRow() === 1) {
      $this->messenger()->addError($this->t('No data found, sheet has to be named "Assessments" (case-sensitive)!'));
    }

    foreach ($worksheet->getRowIterator() as $row) {
      // Skip first 8 rows if needed.
      if ($form_state->getValue('skip_rows') === 'yes') {
        $header_row = 8;
        if ($row->getRowIndex() < 8) {
          continue;
        }
      }

      // Process headers.
      if ($row->getRowIndex() === $header_row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(TRUE);

        foreach ($cellIterator as $cell) {
          $header[$cell->getColumn()] = $cell->getValue();
          $header[$cell->getColumn()] = $cell->getValue();
        }

        $header_lowercase = array_map('strtolower', $header);
        $header_lowercase = array_map('trim', $header_lowercase);

        continue;
      }

      // Process data.
      $data = [
        '_row' => $row->getRowIndex(),
        'operation' => $form_state->getValue('operation'),
      ];

      $cellIterator = $row->getCellIterator();
      $cellIterator->setIterateOnlyExistingCells(TRUE);
      foreach ($cellIterator as $cell) {
        $data[$header_lowercase[$cell->getColumn()]] = $cell->getValue();
      }

      if (isset($data['title']) && !empty($data['title'])) {
        $this->createDocument($data);
      }
    }
  }

  /**
   * Create new assessment document.
   */
  protected function createDocument($item) {
    // Trim all fields.
    $item = array_map('trim', $item);

    // Create assessment.
    $data = [
      'title' => $item['title'],
      'author' => $this->currentUser()->getDisplayName(),
    ];

    // Local group aka Cluster(s)/Sector(s).
    if (isset($item['clusters']) && !empty($item['clusters'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['clusters']));
      foreach ($values as $input) {
        $data['local_groups'][] = $this->extractIdFromInput($input);
      }
    }

    // Leading/Coordinating Organization(s).
    if (isset($item['agency']) && !empty($item['agency'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['agency']));
      foreach ($values as $input) {
        $data['organizations'][] = $this->extractIdFromInput($input);
      }
    }

    // Participating Organization(s).
    if (isset($item['partners']) && !empty($item['partners'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['partners']));
      foreach ($values as $input) {
        $data['asst_organizations'][] = $this->extractIdFromInput($input);
      }
    }

    // Location(s).
    if (isset($item['admin 3']) && !empty($item['admin 3'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['admin 3']));
      foreach ($values as $input) {
        $data['locations'][] = $this->extractIdFromInput($input);
      }
    }
    elseif (isset($item['admin 2']) && !empty($item['admin 2'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['admin 2']));
      foreach ($values as $input) {
        $data['locations'][] = $this->extractIdFromInput($input);
      }
    }
    elseif (isset($item['admin 1']) && !empty($item['admin 1'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['admin 1']));
      foreach ($values as $input) {
        $data['locations'][] = $this->extractIdFromInput($input);
      }
    }

    // Other location.
    if (isset($item['admin 4']) && !empty($item['admin 4'])) {
      $data['other_location'] = substr($item['admin 4'], 0, 255);
    }

    // Population Type(s).
    if (isset($item['types']) && !empty($item['types'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['types']));
      foreach ($values as $input) {
        $data['population_types'][] = $this->extractIdFromInput($input);
      }
    }

    // Unit(s) of Measurement.
    if (isset($item['units']) && !empty($item['units'])) {
      $data['units_of_measurement'][] = [
        '_action' => 'lookup',
        '_reference' => 'term',
        '_target' => 'ar_units_of_measurement',
        '_field' => 'name',
        '_value' => $item['units'],
      ];
    }

    // Collection Method(s).
    if (isset($item['type']) && !empty($item['type'])) {
      $data['collection_method'] = $item['type'];
    }

    // Operation/Country.
    if (isset($item['operation']) && !empty($item['operation'])) {
      $data['operations'][] = $item['operation'];
    }

    // Status.
    if (isset($item['status']) && !empty($item['status'])) {
      $data['ar_status'][] = [
        '_action' => 'lookup',
        '_reference' => 'term',
        '_target' => 'ar_assessment_status',
        '_field' => 'name',
        '_value' => $item['status'],
      ];
    }

    // Assessment Date(s).
    if (isset($item['start']) && !empty($item['start'])) {
      if (strpos($item['start'], '-')) {
        $data['ar_date'][0] = [
          'value' => $item['start'],
        ];
      }
      else {
        $data['ar_date'][0] = [
          'value' => date('Y-m-d', Date::excelToTimestamp($item['start'])),
        ];
      }

      // End date.
      if (isset($item['end']) && !empty($item['end'])) {
        if (strpos($item['end'], '-')) {
          $data['ar_date'][0]['end_value'] = $item['end'];
        }
        else {
          $data['ar_date'][0]['end_value'] = date('Y-m-d', Date::excelToTimestamp($item['end']));
        }
      }
    }

    $instructions = isset($item['instructions']) ? $item['instructions'] : '';
    if (isset($item['data availability']) && !empty($item['data availability'])) {
      $data['assessment_data'][] = $this->createAssessmentDocument($item['data availability'], $item['data url'], $instructions);
    }

    if (isset($item['report availability']) && !empty($item['report availability'])) {
      $data['assessment_report'][] = $this->createAssessmentDocument($item['report availability'], $item['report url'], $instructions);
    }

    if (isset($item['questionnaire availability']) && !empty($item['questionnaire availability'])) {
      $data['assessment_questionnaire'][] = $this->createAssessmentDocument($item['questionnaire availability'], $item['questionnaire url'], $instructions);
    }

    // Contact.
    if (isset($item['name']) && !empty($item['name'])) {
      $value = $item['name'] . "\r\n";
      if (!empty($item['email'] ?? '')) {
        $value .= $item['email'] . "\r\n";
      }
      if (!empty($item['tel'] ?? '')) {
        $value .= $item['tel'] . "\r\n";
      }
      $data['contacts'] = $value;
    }

    // Post to docstore.
    $endpoint = 'http://docstore.local.docksal/api/v1/documents/assessments';
    $api_key = 'abcd';

    $endpoint = ocha_docstore_files_get_endpoint_base($endpoint);
    $api_key = ocha_docstore_files_get_endpoint_apikey($api_key);

    // phpcs:ignore
    $response = \Drupal::httpClient()->request(
      'POST',
      $endpoint,
      [
        'body' => json_encode($data),
        'headers' => [
          'API-KEY' => $api_key,
        ],
      ]
    );

    if ($response->getStatusCode() !== 201) {
      $body = $response->getBody() . '';
      $body = json_decode($body);

      $this->messenger()->addError(print_r($body, TRUE));
    }
  }

  /**
   * Extract Id from input string.
   */
  protected function extractIdFromInput($input) {
    $pos = strrpos($input, '[');
    return substr($input, $pos + 1, -1);
  }

  /**
   * Add a document.
   */
  protected function createAssessmentDocument($accessibility, $document_url, $instructions = '') {
    if (!isset($accessibility) || $accessibility === 'Not Available') {
      return FALSE;
    }

    if (empty($document_url) && empty($instructions)) {
      return FALSE;
    }

    $filename = 'Not applicable';
    if (!empty($document_url)) {
      $url_parts = explode('/', $document_url);
      $filename = end($url_parts);
    }

    $output = [
      '_action' => 'create',
      '_reference' => 'node',
      '_target' => 'assessment_document',
      '_data' => [
        'author' => $this->currentUser()->getDisplayName(),
        'title' => $filename,
        'files' => [],
        'accessibility' => $accessibility ?? 'Publicly Available',
        'instructions' => $instructions ?? '',
      ],
    ];

    if (isset($document_url) && !empty($document_url)) {
      $output['_data']['files'][] = [
        'uri' => $document_url,
      ];
    }

    return $output;
  }

}
