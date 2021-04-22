<?php

namespace Drupal\ocha_assessments\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\ocha_persons\Entity\PersonEntity;
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
   * @var Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Entity query.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityQuery;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ocha_assessments_bulk_import';
  }

  /**
   * Class constructor.
   */
  public function __construct(FileSystem $fileSystem, EntityTypeManagerInterface $entityQuery) {
    $this->fileSystem = $fileSystem;
    $this->entityQuery = $entityQuery;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('entity_type.manager')
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

    $countries = ocha_assessments_country_list();
    $form['country'] = [
      '#type' => 'select',
      '#options' => $countries,
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
        'country' => $form_state->getValue('country'),
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

    // Create node object.
    $data = [
      'type' => 'assessment',
      'title' => $item['title'],
      'field_local_groups' => [],
      'field_organizations' => [],
      'field_asst_organizations' => [],
      'field_locations' => [],
      'field_population_types' => [],
      'field_themes' => [],
      'field_units_of_measurement' => [],
      'field_disasters' => [],
      'field_assessment_data' => [],
      'field_assessment_report' => [],
      'field_assessment_questionnaire' => [],
    ];

    // Local group aka Cluster(s)/Sector(s).
    if (isset($item['clusters']) && !empty($item['clusters'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['clusters']));
      foreach ($values as $input) {
        $data['field_local_groups'][] = [
          'value' => $this->extractIdFromInput($input),
        ];
      }
    }

    // Leading/Coordinating Organization(s).
    if (isset($item['agency']) && !empty($item['agency'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['agency']));
      foreach ($values as $input) {
        $data['field_organizations'][] = [
          'value' => $this->extractIdFromInput($input),
        ];
      }
    }

    // Participating Organization(s).
    if (isset($item['partners']) && !empty($item['partners'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['partners']));
      foreach ($values as $input) {
        $data['field_asst_organizations'][] = [
          'value' => $this->extractIdFromInput($input),
        ];
      }
    }

    // Location(s).
    if (isset($item['admin 3']) && !empty($item['admin 3'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['admin 3']));
      foreach ($values as $input) {
        $data['field_locations'][] = [
          'value' => $this->extractIdFromInput($input),
        ];
      }
    }
    elseif (isset($item['admin 2']) && !empty($item['admin 2'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['admin 2']));
      foreach ($values as $input) {
        $data['field_locations'][] = [
          'value' => $this->extractIdFromInput($input),
        ];
      }
    }
    elseif (isset($item['admin 1']) && !empty($item['admin 1'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['admin 1']));
      foreach ($values as $input) {
        $data['field_locations'][] = [
          'value' => $this->extractIdFromInput($input),
        ];
      }
    }

    // Other location.
    if (isset($item['admin 4']) && !empty($item['admin 4'])) {
      $data['field_other_location'] = substr($item['admin 4'], 0, 255);
    }

    // Population Type(s).
    if (isset($item['types']) && !empty($item['types'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['types']));
      foreach ($values as $input) {
        $data['field_population_types'][] = [
          'value' => $this->extractIdFromInput($input),
        ];
      }
    }

    // Unit(s) of Measurement.
    if (isset($item['units']) && !empty($item['units'])) {
      $data['field_units_of_measurement'][] = [
        'value' => $item['units'],
      ];
    }

    // Collection Method(s).
    if (isset($item['type']) && !empty($item['type'])) {
      $data['field_collection_methods'][] = [
        'value' => $item['type'],
      ];
    }

    // Operation/Country.
    if (isset($item['country']) && !empty($item['country'])) {
      $data['field_countries'][] = [
        'value' => $item['country'],
      ];
    }

    // Status.
    if (isset($item['status']) && !empty($item['status'])) {
      $data['field_status'] = $item['status'];
    }

    // Assessment Date(s).
    if (isset($item['start']) && !empty($item['start'])) {
      if (strpos($item['start'], '-')) {
        $data['field_ass_date'][0] = [
          'value' => $item['start'],
        ];
      }
      else {
        $data['field_ass_date'][0] = [
          'value' => date('Y-m-d', Date::excelToTimestamp($item['start'])),
        ];
      }

      // End date.
      if (isset($item['end']) && !empty($item['end'])) {
        if (strpos($item['end'], '-')) {
          $data['field_ass_date'][0]['end_value'] = $item['end'];
        }
        else {
          $data['field_ass_date'][0]['end_value'] = date('Y-m-d', Date::excelToTimestamp($item['end']));
        }
      }
    }

    $instructions = isset($item['instructions']) ? $item['instructions'] : '';
    if (isset($item['data availability']) && !empty($item['data availability'])) {
      $data['field_assessment_data'][] = ocha_assessments_create_document($item['data availability'], $item['data url'], $instructions);
    }

    if (isset($item['report availability']) && !empty($item['report availability'])) {
      $data['field_assessment_report'][] = ocha_assessments_create_document($item['report availability'], $item['report url'], $instructions);
    }

    if (isset($item['questionnaire availability']) && !empty($item['questionnaire availability'])) {
      $data['field_assessment_questionnaire'][] = ocha_assessments_create_document($item['questionnaire availability'], $item['questionnaire url'], $instructions);
    }

    // Contact.
    if (isset($item['name']) && !empty($item['name'])) {
      $person_name = $item['name'];
      $person_email = $item['email'] ?? '';
      $person_tel = $item['tel'] ?? '';
      if ($person_id = $this->addPerson($person_name, $person_email, $person_tel)) {
        $data['field_contacts'][] = [
          'target_id' => $person_id,
        ];
      }
    }

    $node = Node::create($data);
    $node->save();
  }

  /**
   * Extract Id from input string.
   */
  protected function extractIdFromInput($input) {
    $pos = strrpos($input, '[');
    return substr($input, $pos + 1, -1);
  }

  /**
   * Create contact using HID Id.
   */
  protected function addPerson($name, $email, $tel) {
    // Email and tel can be multivalue.
    $email = str_replace(['&', 'and'], '|', $email);
    $tel = str_replace(['&', 'and'], '|', $tel);

    $emails = explode('|', $email);
    $tels = explode('|', $tel);

    $existing_person_id = FALSE;
    foreach ($emails as $e) {
      $e = trim($e);
      $existing_person_id = $this->lookupPersonByEmail($e);
      if ($existing_person_id) {
        return $existing_person_id;
      }
    }

    // Create new person.
    $data['name'] = $name;
    $data['field_email'] = [];
    $data['field_phone'] = [];

    foreach ($tels as $t) {
      $t = trim($t);
      $data['field_phone'][] = $t;
    }

    foreach ($emails as $e) {
      $e = trim($e);
      $data['field_email'][] = $e;
    }

    $person = PersonEntity::create($data);
    $person->save();

    return $person->id();
  }

  /**
   * Lookup contact using email.
   */
  protected function lookupPersonByEmail($email) {
    $query = $this->entityQuery
      ->get('person_entity')
      ->condition('field_email', $email);
    $entity_ids = $query->execute();

    if (empty($entity_ids)) {
      return FALSE;
    }

    return reset($entity_ids);
  }

}
