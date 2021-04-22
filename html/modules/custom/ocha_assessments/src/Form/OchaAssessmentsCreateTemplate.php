<?php

namespace Drupal\ocha_assessments\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class OchaAssessmentsCreateTemplate to create template.
 */
class OchaAssessmentsCreateTemplate extends FormBase {

  use StringTranslationTrait;

  /**
   * File system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ocha_assessments_create_template';
  }

  /**
   * Class constructor.
   */
  public function __construct(FileSystem $fileSystem, EntityTypeManagerInterface $entityTypeManager) {
    $this->fileSystem = $fileSystem;
    $this->entityTypeManager = $entityTypeManager;
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
    $form['country'] = [
      '#type' => 'select',
      '#options' => ocha_assessments_load_countries_from_docstore(),
      '#title' => $this->t('Country'),
      '#description' => $this->t('Country'),
      '#required' => TRUE,
    ];

    $form['operation'] = [
      '#type' => 'select',
      '#options' => ocha_assessments_load_reference_data_from_docstore('operations'),
      '#title' => $this->t('Limit local cluster by operation'),
      '#required' => FALSE,
      '#empty_option' => $this->t('- None -'),
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
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $country = $this->entityTypeManager->getStorage('location')->load($form_state->getValue('country'));
    $operation = $this->entityTypeManager->getStorage('operation')->load($form_state->getValue('operation'));
    $export_filters = ocha_assessments_load_all_reference_data();

    // Set paths.
    $destination_name = 'template_' . str_replace(' ', '_', $country->label()) . '_' . date('Ymdhni') . '.xlsx';
    $source = drupal_get_path('module', 'ocha_assessments') . '/bulk_template.xlsx';
    $filename = $this->fileSystem->realpath($source);

    $reader = new Xlsx();
    $reader->setReadDataOnly(FALSE);
    $spreadsheet = $reader->load($filename);

    // Hide Accessibility.
    $worksheet = $spreadsheet->getSheetByName('Accessibility');
    $worksheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    $worksheet->getProtection()->setSheet(TRUE);

    // Hide Status.
    $worksheet = $spreadsheet->getSheetByName('Status');
    $worksheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    $worksheet->getProtection()->setSheet(TRUE);

    // Hide Units.
    $worksheet = $spreadsheet->getSheetByName('Units');
    $worksheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    $worksheet->getProtection()->setSheet(TRUE);

    // Hide Collection method.
    $worksheet = $spreadsheet->getSheetByName('CollectionMethod');
    $worksheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    $worksheet->getProtection()->setSheet(TRUE);

    // Add local clusters.
    $worksheet = $spreadsheet->getSheetByName('Clusters');
    $options = $export_filters['local_coordination_groups'];

    // Limit by operation.
    if (!empty($operation)) {
      foreach ($options as $key => $value) {
        if (!strpos($value, '(' . $operation->label() . ')')) {
          unset($options[$key]);
        }
        else {
          // Remove operation name.
          $options[$key] = str_replace(' (' . $operation->label() . ')', '', $value);
        }
      }
    }
    $worksheet->fromArray($this->optionsToXlsArray($options), NULL, 'A1');
    $worksheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    $worksheet->getProtection()->setSheet(TRUE);

    // Organizations.
    $worksheet = $spreadsheet->getSheetByName('Organizations');
    $options = $export_filters['organizations'];
    $worksheet->fromArray($this->optionsToXlsArray($options), NULL, 'A1');
    $worksheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    $worksheet->getProtection()->setSheet(TRUE);

    // Admin levels.
    $admin_levels1 = [];
    $admin_levels2 = [];
    $admin_levels3 = [];

    $children = ocha_assessments_load_country_childs_from_docstore($country->uuid());
    foreach ($children as $l1) {
      $admin_levels1[] = [$this->makeLabel($l1['label'], $l1['uuid'])];
      if (!empty($l1['children'])) {
        foreach ($l1['children'] as $l2) {
          $admin_levels2[] = [
            $this->makeLabel($l1['label'], $l1['uuid']),
            $this->makeLabel($l2['label'], $l2['uuid']),
          ];
          if (!empty($l2['children'])) {
            foreach ($l2['children'] as $l3) {
              $admin_levels3[] = [
                $this->makeLabel($l1['label'], $l1['uuid']),
                $this->makeLabel($l2['label'], $l2['uuid']),
                $this->makeLabel($l3['label'], $l3['uuid']),
              ];
            }
          }
        }
      }
    }
    $worksheet = $spreadsheet->getSheetByName('AdminLevel1');
    $worksheet->fromArray($admin_levels1, NULL, 'A1');
    $worksheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    $worksheet->getProtection()->setSheet(TRUE);

    $worksheet = $spreadsheet->getSheetByName('AdminLevel2');
    $worksheet->fromArray($admin_levels2, NULL, 'A1');
    $worksheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    $worksheet->getProtection()->setSheet(TRUE);

    $worksheet = $spreadsheet->getSheetByName('AdminLevel3');
    $worksheet->fromArray($admin_levels3, NULL, 'A1');
    $worksheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    $worksheet->getProtection()->setSheet(TRUE);

    // Add population type.
    $worksheet = $spreadsheet->getSheetByName('PopulationTypes');
    $options = $export_filters['population_types'];
    $worksheet->fromArray($this->optionsToXlsArray($options), NULL, 'A1');
    $worksheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    $worksheet->getProtection()->setSheet(TRUE);

    // Set validators for clusters.
    $worksheet = $spreadsheet->getSheetByName('Clusters');
    $highest_row = $worksheet->getHighestRow();
    $worksheet = $spreadsheet->getSheetByName('Assessments');
    $validation = $worksheet->getCell('B9')->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $validation->setAllowBlank(TRUE);
    $validation->setShowInputMessage(FALSE);
    $validation->setShowErrorMessage(TRUE);
    $validation->setShowDropDown(TRUE);
    $validation->setErrorTitle('Input error');
    $validation->setError('Value is not in list.');
    $validation->setPromptTitle('Pick from list');
    $validation->setPrompt('Please pick a value from the drop-down list.');
    $validation->setFormula1('Clusters!$A$1:$A$' . $highest_row);
    $worksheet->setDataValidation('B9:B999', $validation);

    // Set validators for organizations.
    $worksheet = $spreadsheet->getSheetByName('Organizations');
    $highest_row = $worksheet->getHighestRow();
    $worksheet = $spreadsheet->getSheetByName('Assessments');
    $validation = $worksheet->getCell('C9')->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $validation->setAllowBlank(TRUE);
    $validation->setShowInputMessage(FALSE);
    $validation->setShowErrorMessage(TRUE);
    $validation->setShowDropDown(TRUE);
    $validation->setErrorTitle('Input error');
    $validation->setError('Value is not in list.');
    $validation->setPromptTitle('Pick from list');
    $validation->setPrompt('Please pick a value from the drop-down list.');
    $validation->setFormula1('Organizations!$A$1:$A$' . $highest_row);
    $worksheet->setDataValidation('C9:D999', $validation);

    // Set validators for admin level 1.
    $worksheet = $spreadsheet->getSheetByName('AdminLevel1');
    $highest_row = $worksheet->getHighestRow();
    $worksheet = $spreadsheet->getSheetByName('Assessments');
    $validation = $worksheet->getCell('E9')->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $validation->setAllowBlank(TRUE);
    $validation->setShowInputMessage(FALSE);
    $validation->setShowErrorMessage(TRUE);
    $validation->setShowDropDown(TRUE);
    $validation->setErrorTitle('Input error');
    $validation->setError('Value is not in list.');
    $validation->setPromptTitle('Pick from list');
    $validation->setPrompt('Please pick a value from the drop-down list.');
    $validation->setFormula1('AdminLevel1!$A$1:$A$' . $highest_row);
    $worksheet->setDataValidation('E9:E999', $validation);

    // Set validators for admin level 2.
    $worksheet = $spreadsheet->getSheetByName('AdminLevel2');
    $highest_row = $worksheet->getHighestRow();
    $worksheet = $spreadsheet->getSheetByName('Assessments');
    $validation = $worksheet->getCell('F9')->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $validation->setAllowBlank(TRUE);
    $validation->setShowInputMessage(FALSE);
    $validation->setShowErrorMessage(TRUE);
    $validation->setShowDropDown(TRUE);
    $validation->setErrorTitle('Input error');
    $validation->setError('Value is not in list.');
    $validation->setPromptTitle('Pick from list');
    $validation->setPrompt('Please pick a value from the drop-down list.');
    $validation->setFormula1('OFFSET(AdminLevel2!A1,MATCH(E9,AdminLevel2!$A$1:$A$' . $highest_row . ',0)-1,1,COUNTIF(AdminLevel2!$A$1:$A$' . $highest_row . ',E9),1)');
    $worksheet->setDataValidation('F9:F999', $validation);

    // Set validators for admin level 3.
    $worksheet = $spreadsheet->getSheetByName('AdminLevel3');
    $highest_row = $worksheet->getHighestRow();
    $worksheet = $spreadsheet->getSheetByName('Assessments');
    $validation = $worksheet->getCell('G9')->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $validation->setAllowBlank(TRUE);
    $validation->setShowInputMessage(FALSE);
    $validation->setShowErrorMessage(TRUE);
    $validation->setShowDropDown(TRUE);
    $validation->setErrorTitle('Input error');
    $validation->setError('Value is not in list.');
    $validation->setPromptTitle('Pick from list');
    $validation->setPrompt('Please pick a value from the drop-down list.');
    $validation->setFormula1('OFFSET(AdminLevel3!B1,MATCH(F9,AdminLevel3!$B$1:$B$' . $highest_row . ',0)-1,1,COUNTIF(AdminLevel3!$B$1:$B$' . $highest_row . ',F9),1)');
    $worksheet->setDataValidation('G9:G999', $validation);

    // Set validators for units of measurement.
    $worksheet = $spreadsheet->getSheetByName('Units');
    $highest_row = $worksheet->getHighestRow();
    $worksheet = $spreadsheet->getSheetByName('Assessments');
    $validation = $worksheet->getCell('M9')->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $validation->setAllowBlank(TRUE);
    $validation->setShowInputMessage(FALSE);
    $validation->setShowErrorMessage(TRUE);
    $validation->setShowDropDown(TRUE);
    $validation->setErrorTitle('Input error');
    $validation->setError('Value is not in list.');
    $validation->setPromptTitle('Pick from list');
    $validation->setPrompt('Please pick a value from the drop-down list.');
    $validation->setFormula1('Units!$A$1:$A$' . $highest_row);
    $worksheet->setDataValidation('M9:M999', $validation);

    // Set validators for collection method.
    $worksheet = $spreadsheet->getSheetByName('CollectionMethod');
    $highest_row = $worksheet->getHighestRow();
    $worksheet = $spreadsheet->getSheetByName('Assessments');
    $validation = $worksheet->getCell('N9')->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $validation->setAllowBlank(TRUE);
    $validation->setShowInputMessage(FALSE);
    $validation->setShowErrorMessage(TRUE);
    $validation->setShowDropDown(TRUE);
    $validation->setErrorTitle('Input error');
    $validation->setError('Value is not in list.');
    $validation->setPromptTitle('Pick from list');
    $validation->setPrompt('Please pick a value from the drop-down list.');
    $validation->setFormula1('CollectionMethod!$A$1:$A$' . $highest_row);
    $worksheet->setDataValidation('N9:N999', $validation);

    // Set validators for status.
    $worksheet = $spreadsheet->getSheetByName('Status');
    $highest_row = $worksheet->getHighestRow();
    $worksheet = $spreadsheet->getSheetByName('Assessments');
    $validation = $worksheet->getCell('I9')->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $validation->setAllowBlank(TRUE);
    $validation->setShowInputMessage(FALSE);
    $validation->setShowErrorMessage(TRUE);
    $validation->setShowDropDown(TRUE);
    $validation->setErrorTitle('Input error');
    $validation->setError('Value is not in list.');
    $validation->setPromptTitle('Pick from list');
    $validation->setPrompt('Please pick a value from the drop-down list.');
    $validation->setFormula1('Status!$A$1:$A$' . $highest_row);
    $worksheet->setDataValidation('I9:I999', $validation);

    // Set validators for population types.
    $worksheet = $spreadsheet->getSheetByName('PopulationTypes');
    $highest_row = $worksheet->getHighestRow();
    $worksheet = $spreadsheet->getSheetByName('Assessments');
    $validation = $worksheet->getCell('L9')->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $validation->setAllowBlank(TRUE);
    $validation->setShowInputMessage(FALSE);
    $validation->setShowErrorMessage(TRUE);
    $validation->setShowDropDown(TRUE);
    $validation->setErrorTitle('Input error');
    $validation->setError('Value is not in list.');
    $validation->setPromptTitle('Pick from list');
    $validation->setPrompt('Please pick a value from the drop-down list.');
    $validation->setFormula1('PopulationTypes!$A$1:$A$' . $highest_row);
    $worksheet->setDataValidation('L9:L999', $validation);

    // Set validators for accessibility.
    $worksheet = $spreadsheet->getSheetByName('Accessibility');
    $highest_row = $worksheet->getHighestRow();
    $worksheet = $spreadsheet->getSheetByName('Assessments');
    $validation = $worksheet->getCell('O9')->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $validation->setAllowBlank(TRUE);
    $validation->setShowInputMessage(FALSE);
    $validation->setShowErrorMessage(TRUE);
    $validation->setShowDropDown(TRUE);
    $validation->setErrorTitle('Input error');
    $validation->setError('Value is not in list.');
    $validation->setPromptTitle('Pick from list');
    $validation->setPrompt('Please pick a value from the drop-down list.');
    $validation->setFormula1('Accessibility!$A$1:$A$' . $highest_row);
    $worksheet->setDataValidation('O9:O999', $validation);
    $worksheet->setDataValidation('Q9:Q999', clone $validation);
    $worksheet->setDataValidation('S9:S999', clone $validation);

    // Set title.
    $worksheet = $spreadsheet->getSheetByName('Assessments');
    $worksheet->getCell('C2')->setValue('Assessment Registry – ' . $country->label());

    // Protect headers.
    $spreadsheet->getDefaultStyle()->getProtection()->setLocked(TRUE);
    $worksheet = $spreadsheet->getSheetByName('Assessments');
    $worksheet->getProtection()
      ->setSheet(TRUE)
      ->setInsertColumns(TRUE)
      ->setInsertRows(TRUE)
      ->setFormatCells(TRUE);
    $worksheet->getStyle('A9:X999')->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);

    // Freeze.
    $worksheet = $spreadsheet->getSheetByName('Assessments');
    $worksheet->freezePane('A9');

    // Set focus to main sheet.
    $spreadsheet->setActiveSheetIndexByName('Assessments');

    // Set metadata.
    $spreadsheet->getProperties()
      ->setCreator('OCHA')
      ->setLastModifiedBy('OCHA')
      ->setTitle('Assessment Registry – ' . $country->label())
      ->setSubject('Assessment Registry – ' . $country->label())
      ->setKeywords('OCHA AR')
      ->setCustomProperty('Country', $country->label())
      ->setCustomProperty('CountryId', $country->uuid());

    // Stream to browser.
    ob_start();
    $writer = new XlsxWriter($spreadsheet);
    $writer->save('php://output');
    $content = ob_get_clean();

    $response = new Response();
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');
    $response->headers->set('Content-Type', 'application/vnd.ms-excel');
    $response->headers->set('Content-Transfer-Encoding', 'binary');
    $response->headers->set('Content-Disposition', 'attachment; filename=' . $destination_name);
    $response->setContent($content);

    $form_state->setResponse($response);

    // Clean up.
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
  }

  /**
   * Make label containing Id.
   */
  protected function makeLabel($label, $id) {
    return $label . ' [' . $id . ']';
  }

  /**
   * Convert options to array for xlsx.
   */
  protected function optionsToXlsArray($options) {
    $results = [];

    foreach ($options as $id => $label) {
      $results[] = [$label . ' [' . $id . ']'];
    }

    return $results;
  }

}
