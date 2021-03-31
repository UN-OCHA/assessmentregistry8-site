<?php

namespace Drupal\ocha_assessments\Encoder;

use Drupal\csv_serialization\Encoder\CsvEncoder as OriginalCsvEncoder;

/**
 * CSV encoder that duplicates rows for cells with mulitple values.
 */
class CsvEncoder extends OriginalCsvEncoder {

  /**
   * {@inheritdoc}
   */
  public function encode($data, $format, array $context = []) {
    switch (gettype($data)) {
      case "array":
        break;

      case 'object':
        $data = (array) $data;
        break;

      // May be bool, integer, double, string, resource, NULL, or unknown.
      default:
        $data = [$data];
        break;
    }

    if (isset($data[0])) {
      $data = static::duplicateRows($data);
    }

    return parent::encode($data, $format, $context);
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
  public static function duplicateRows(array $data) {
    $rows = [];
    foreach ($data as $row) {
      $rows = array_merge($rows, static::duplicateRow($row, 0));
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
  public static function duplicateRow(array $row, $index = 0) {
    if ($index >= count($row)) {
      return [$row];
    }

    $key = array_keys($row)[$index];
    $rows = [];
    $cell = $row[$key];

    foreach (explode('||', $cell) as $value) {
      $new_row = $row;
      $new_row[$key] = $value;
      $rows = array_merge($rows, static::duplicateRow($new_row, $index + 1));
    }

    return $rows;
  }

}
