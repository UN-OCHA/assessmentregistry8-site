<?php

namespace Drupal\ocha_map\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * An ocha_map controller.
 */
class OchaMapController extends ControllerBase {

  /**
   * Returns a map.
   */
  public function map() {
    global $base_url;
    $src = $base_url . '/map-data';

    return [
      '#theme' => 'ocha_map_map',
      '#base_url' => $base_url,
      '#src' => $src,
      '#component_url' => '/modules/custom/ocha_map/component/',
    ];
  }

  public function mapData() {
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
    ];

    $index = Index::load('assessments');
    $query = $index->query();

    $query->setOption('search_api_facets', [
      'organizations' => [
        'field' => 'field_organizations',
        'limit' => 20,
        'operator' => 'AND',
        'min_count' => 1,
        'missing' => TRUE,
      ],
      'asst_organizations' => [
        'field' => 'field_asst_organizations',
        'limit' => 20,
        'operator' => 'AND',
        'min_count' => 1,
        'missing' => TRUE,
      ],
    ]);

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

      $entities = \Drupal::entityTypeManager()
        ->getListBuilder($facet_to_entity[$key]['entity'])
        ->getStorage()
        ->loadMultiple($uuids);

      $options = [];
      foreach ($facet_values as $facet_value) {
        $uuid = trim($facet_value['filter'], '"');
        if (isset($entities[$uuid])) {
          $options[] = [
            'key' => $key . ':' . $uuid,
            'label' => $entities[$uuid]->label() . ' (' . $facet_value['count'] . ')',
            'active' => false,
          ];
        }
      }

      uasort($options, function ($a, $b) {
        return strnatcasecmp($a['label'], $b['label']);
      });

      $data['facets'][$key]['label'] = $facet_to_entity[$key]['title'];
      $data['facets'][$key]['name'] = $facet_to_entity[$key]['field'];
      $data['facets'][$key]['options'] = $options;
    }

    $response = new JsonResponse($data);
    $response->setStatusCode(200);
    return $response;
  }

  /**
   * Redirect old to new.
   */
  public function redirectLegacy() {
    return $this->redirect('ocha_map.map');
  }

}
