<?php

namespace Drupal\ocha_assessments\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * An ocha_map controller.
 */
class OchaJsonController extends ControllerBase {

  /**
   * Returns a map.
   */
  public function map() {
    global $base_url;
    $src = $base_url . '/map-data';

    return [
      '#theme' => 'ocha_assessments_map',
      '#base_url' => $base_url,
      '#src' => $src,
      '#component_url' => '/modules/custom/ocha_assessments/component/build/',
    ];
  }

  /**
   * Returns a table.
   */
  public function table() {
    global $base_url;
    $src = $base_url . '/table-data';

    return [
      '#theme' => 'ocha_assessments_table',
      '#base_url' => $base_url,
      '#src' => $src,
      '#component_url' => '/modules/custom/ocha_assessments/component/build/',
    ];
  }

  /**
   * Returns a list.
   */
  public function list() {
    global $base_url;
    $src = $base_url . '/list-data';

    return [
      '#theme' => 'ocha_assessments_list',
      '#base_url' => $base_url,
      '#src' => $src,
      '#component_url' => '/modules/custom/ocha_assessments/component/build/',
    ];
  }

  /**
   * Return map data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   */
  public function mapData(Request $request) {
    $facet_to_entity = [
      'organizations' => [
        'entity' => 'organization',
        'field' => 'field_organizations',
        'title' => 'Leading/Coordinating Organization(s)',
      ],
      'participating_organizations' => [
        'entity' => 'organization',
        'field' => 'field_asst_organizations',
        'title' => 'Participating Organization(s)',
      ],
      'status' => [
        'entity' => 'assessment_status',
        'field' => 'field_status',
        'title' => 'Status',
      ],
    ];

    $index = Index::load('assessments');
    $query = $index->query();
    $query->range(0, 9999);

    // Add filters.
    if ($request->query->has('f')) {
      $filters = $request->query->get('f');
      foreach ($filters as $filter) {
        $parts = explode(':', $filter);
        $query->addCondition($parts[0], $parts[1]);
      }
    }

    // Add facets.
    $facet_options = [];
    foreach ($facet_to_entity as $key => $info) {
      $facet_options[$key] = [
        'field' => $info['field'],
        'limit' => 20,
        'operator' => 'AND',
        'min_count' => 1,
        'missing' => FALSE,
      ];
    }
    $query->setOption('search_api_facets', $facet_options);

    $results = $query->execute();
    $facets = $results->getExtraData('search_api_facets', []);

    // Prepare results.
    $data['search_results'] = [];
    foreach ($results as $item) {
      $data['search_results'][] = [
        'uuid' => $item->getField('uuid')->getValues(),
        'title' => $item->getField('title')->getValues(),
        'field_organizations' => $item->getField('field_organizations')->getValues(),
        'field_locations_lat_lon' => $item->getField('latlon')->getValues(),
        'field_locations_label' => $item->getField('field_locations_label')->getValues(),
        'field_organizations_label' => $item->getField('field_organizations_label')->getValues(),
        'field_asst_organizations_label' => $item->getField('field_asst_organizations_label')->getValues(),
        'field_status' => $item->getField('field_status_label')->getValues(),
        // 'field_local_groups_label' => $item->getField('field_local_groups_label')->getValues(),
      ];
    }

    // Handle facets.
    foreach ($facets as $key => $facet_values) {
      if (!isset($facet_to_entity[$key])) {
        continue;
      }

      $uuids = [];
      foreach ($facet_values as $facet_value) {
        $uuid = trim($facet_value['filter'], '"');
        $uuids[] = $uuid;
      }

      // phpcs:ignore
      $entities = \Drupal::entityTypeManager()
        ->getListBuilder($facet_to_entity[$key]['entity'])
        ->getStorage()
        ->loadMultiple($uuids);

      $options = [];
      foreach ($facet_values as $facet_value) {
        $uuid = trim($facet_value['filter'], '"');
        if (isset($entities[$uuid])) {
          $options[] = [
            'key' => $facet_to_entity[$key]['field'] . ':' . $uuid,
            'label' => $entities[$uuid]->label() . ' (' . $facet_value['count'] . ')',
            'active' => FALSE,
          ];
        }
      }

      uasort($options, function ($a, $b) {
        return strnatcasecmp($a['label'], $b['label']);
      });

      $data['facets'][$key]['label'] = $facet_to_entity[$key]['title'];
      $data['facets'][$key]['name'] = $facet_to_entity[$key]['field'];
      $data['facets'][$key]['options'] = array_values($options);
    }

    $response = new JsonResponse($data);
    $response->setStatusCode(200);
    return $response;
  }

  /**
   * Return list data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   */
  public function listData(Request $request) {
    return $this->mapData($request);
  }

  /**
   * Return table data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   */
  public function tableData(Request $request) {
    return $this->mapData($request);
  }

  /**
   * Redirect old to new.
   */
  public function redirectLegacy() {
    return $this->redirect('ocha_assessments.map');
  }

}
