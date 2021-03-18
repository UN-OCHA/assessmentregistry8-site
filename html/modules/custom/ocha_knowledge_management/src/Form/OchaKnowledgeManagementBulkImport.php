<?php

namespace Drupal\ocha_knowledge_management\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\Entity\ExternalEntity;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class OchaKnowledgeManagementBulkImport for bulk imports.
 */
class OchaKnowledgeManagementBulkImport extends FormBase {

  /**
   * File system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Entity query.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityQuery;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ocha_knowledge_management_bulk_import';
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
      '#description' => $this->t('Excel file containing knowledge management documents to import'),
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
      'km',
    ]);
    $spreadsheet = $reader->load($filename);

    // Assume headers on first row.
    $header_row = 1;

    $header = [];
    $header_lowercase = [];

    $worksheet = $spreadsheet->getActiveSheet();
    if ($worksheet->getHighestRow() === 1) {
      $this->messenger()->addError($this->t('No data found, sheet has to be named "km" (case-sensitive)!'));
    }

    foreach ($worksheet->getRowIterator() as $row) {
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
    $km = new ExternalEntity([], 'km');
    $km->title = $item['title'];
    $km->field_author = $this->currentUser()->getDisplayName();

    // Context.
    if (isset($item['context']) && !empty($item['context'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['context']));
      $uuids = [];
      foreach ($values as $input) {
        $uuids[] = [
          'target_id' => $this->getReferencedEntity($input, 'context'),
        ];
      }
      $km->set('field_context', $uuids);
    }

    // Country.
    if (isset($item['country']) && !empty($item['country'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['country']));
      $uuids = [];
      foreach ($values as $input) {
        $uuids[] = [
          'target_id' => $this->getReferencedEntity($input, 'country'),
        ];
      }
      $km->set('field_countries', $uuids);
    }

    // Document type.
    if (isset($item['document type']) && !empty($item['document type'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['document type']));
      $uuids = [];
      foreach ($values as $input) {
        $uuids[] = [
          'target_id' => $this->getReferencedEntity($input, 'document_type'),
        ];
      }
      $km->set('field_document_type', $uuids);
    }

    // Global cluster.
    if (isset($item['global cluster']) && !empty($item['global cluster'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['global cluster']));
      $uuids = [];
      foreach ($values as $input) {
        $uuids[] = [
          'target_id' => $this->getReferencedEntity($input, 'global_cluster'),
        ];
      }
      $km->set('field_global_clusters', $uuids);
    }

    // HPC Document Repository.
    if (isset($item['hpc document repository']) && !empty($item['hpc document repository'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['hpc document repository']));
      $uuids = [];
      foreach ($values as $input) {
        $uuids[] = [
          'target_id' => $this->getReferencedEntity($input, 'hpc_document_repository'),
        ];
      }
      $km->set('field_hpc_document_repository', $uuids);
    }

    // Life cycle steps.
    if (isset($item['life cycle steps']) && !empty($item['life cycle steps'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['life cycle steps']));
      $uuids = [];
      foreach ($values as $input) {
        $uuids[] = [
          'target_id' => $this->getReferencedEntity($input, 'life_cycle_step'),
        ];
      }
      $km->set('field_life_cycle_steps', $uuids);
    }

    // Original publication date.
    if (isset($item['original publication date']) && !empty($item['original publication date'])) {
      if (strpos($item['original publication date'], '-')) {
        $km->set('field_original_publication_date', [
          'value' => strtotime($item['original publication date']),
        ]);
      }
      else {
        $km->set('field_original_publication_date', [
          'value' => Date::excelToTimestamp($item['original publication date']),
        ]);
      }
    }

    // Description.
    if (isset($item['description']) && !empty($item['description'])) {
      $km->set('field_description', [
        'value' => $item['description'],
      ]);
    }

    // Population Type(s).
    if (isset($item['population type(s)']) && !empty($item['population type(s)'])) {
      // Split and trim.
      $values = array_map('trim', explode(',', $item['population type(s)']));
      $uuids = [];
      foreach ($values as $input) {
        $uuids[] = [
          'target_id' => $this->getReferencedEntity($input, 'population_type'),
        ];
      }
      $km->set('field_population_types', $uuids);
    }

    // Files and media.
    if ((isset($item['document']) && !empty($item['document'])) || (isset($item['media']) && !empty($item['media']))) {
      // Split and trim.
      $uuids = [];

      if (isset($item['document']) && !empty($item['document'])) {
        $values = array_map('trim', explode(',', $item['document']));
        foreach ($values as $input) {
          $media_uuid = $this->createFileInDocstore($input);
          if ($media_uuid) {
            $uuids[] = [
              'target_id' => $media_uuid,
            ];
          }
        }
      }

      if (isset($item['media']) && !empty($item['media'])) {
        $values = array_map('trim', explode(',', $item['media']));
        foreach ($values as $input) {
          $media_uuid = $this->createFileInDocstore($input);
          if ($media_uuid) {
            $uuids[] = [
              'target_id' => $media_uuid,
            ];
          }
        }
      }

      $km->set('field_files', $uuids);
    }

    $km->save();
  }

  /**
   * Get uuid from referenced external entity.
   */
  protected function getReferencedEntity($input, $bundle) {
    $query = $this->entityQuery->getStorage($bundle)->getQuery();
    $query->condition('title', mb_strtolower($input));
    $ids = $query->execute();

    if (empty($ids)) {
      // Create new one.
    }
    else {
      return reset($ids);
    }
  }

  /**
   * Create a file based on url.
   */
  protected function createFileInDocstore($uri, $filename = '') {
    if (empty($filename)) {
      $filename = urldecode(basename(parse_url($uri, PHP_URL_PATH)));
    }

    // phpcs:ignore
    $response = \Drupal::httpClient()->request(
      'POST',
      ocha_docstore_files_get_endpoint_base('http://docstore.local.docksal/api/v1/files'),
      [
        'body' => json_encode([
          'filename' => $filename,
          'uri' => $uri,
          'private' => FALSE,
        ]),
        'headers' => [
          'API-KEY' => ocha_docstore_files_get_endpoint_apikey('abcd'),
        ],
      ]
    );

    $body = $response->getBody() . '';
    $body = json_decode($body);

    // @todo Check return value.
    if ($body->uuid) {
      return $body->media_uuid;
    }

    return FALSE;
  }

}
