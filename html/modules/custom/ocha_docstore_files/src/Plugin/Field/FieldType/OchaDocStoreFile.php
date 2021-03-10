<?php

namespace Drupal\ocha_docstore_files\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'ocha_doc_store_file' field type.
 *
 * @FieldType(
 *   id = "ocha_doc_store_file",
 *   label = @Translation("OCHA Document store file"),
 *   description = @Translation("File field for files from the document store"),
 *   default_widget = "ocha_doc_store_file_widget",
 *   default_formatter = "ocha_doc_store_file_formatter"
 * )
 */
class OchaDocStoreFile extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // Prevent early t() calls by using the TranslatableMarkup.
    $properties['media_uuid'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('UUID'))
      ->setRequired(TRUE);

    $properties['filename'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('File name'))
      ->setSetting('case_sensitive', TRUE)
      ->setRequired(FALSE);

    $properties['uri'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('URI'))
      ->setSetting('case_sensitive', TRUE)
      ->setRequired(FALSE);

    $properties['private'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Private'))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'media_uuid' => [
          'type' => 'varchar',
          'binary' => FALSE,
        ],
        'filename' => [
          'type' => 'varchar',
          'binary' => TRUE,
        ],
        'uri' => [
          'type' => 'varchar',
          'binary' => TRUE,
        ],
        'private' => [
          'type' => 'boolean',
        ],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $random = new Random();
    $values['media_uuid'] = $random->word(mt_rand(1, $field_definition->getSetting('max_length')));
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $elements = [];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('media_uuid')->getValue();
    return $value === NULL || $value === '';
  }

}
