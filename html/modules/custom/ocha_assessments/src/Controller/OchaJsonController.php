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
    $src = $base_url . '/rest/map-data';

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
    $src = $base_url . '/rest/table-data?sort=-field_date';

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
    $src = $base_url . '/rest/list-data?sort=-field_date';

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
    return $this->fetchData($request, 0, 1000);
  }

  /**
   * Return list data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   */
  public function listData(Request $request) {
    return $this->fetchData($request);
  }

  /**
   * Return table data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   */
  public function tableData(Request $request) {
    return $this->fetchData($request);
  }

  /**
   * Redirect old to new.
   */
  public function redirectLegacy() {
    return $this->redirect('ocha_assessments.map');
  }

  protected function fetchData($request, $offset = 0, $limit = 50) {
    $facet_to_entity = [
      'organizations' => [
        'entity' => 'organization',
        'field' => 'field_organizations__facet',
        'title' => 'Leading/Coordinating Organization',
      ],
      'participating_organizations' => [
        'entity' => 'organization',
        'field' => 'field_asst_organizations__facet',
        'title' => 'Participating Organization(s)',
      ],
      'status' => [
        'entity' => 'assessment_status',
        'field' => 'field_status__facet',
        'title' => 'Status',
      ],
      'locations' => [
        'entity' => 'location',
        'field' => 'field_locations__facet',
        'title' => 'Location(s)',
      ],
      'population_types' => [
        'entity' => 'population_type',
        'field' => 'field_population_types__facet',
        'title' => 'Population type(s)',
      ],
      'disaster' => [
        'entity' => 'disaster',
        'field' => 'field_disaster__facet',
        'title' => 'Disaster',
      ],
    ];

    $index = Index::load('assessments');
    $query = $index->query();
    $query->range($offset * $limit, $limit);

    // Check for pager.
    if ($request->query->has('page')) {
      $query->range($request->query->get('page') * $limit, $limit);
    }

    // Only published content.
    $query->addCondition('field_published', TRUE);

    // Add filters.
    $active_filters = [];
    if ($request->query->has('f')) {
      $filters = $request->query->get('f');
      foreach ($filters as $filter) {
        $parts = explode(':', $filter);
        $query->addCondition($parts[0], $parts[1]);
        $active_filters[$parts[0]][$parts[1]] = TRUE;
      }
    }

    // Parse sort parameter.
    if ($request->query->has('sort')) {
      $sorts = explode('sort', $request->query->get('sort'));
      foreach ($sorts as $sort) {
        if (strpos($sort, '-') === 0) {
          $query->sort(substr($sort, 1), 'DESC');
        }
        else {
          $query->sort($sort, 'ASC');
        }
      }
    }
    else {
      $query->sort('field_date', 'DESC');
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

    // Add pagers if needed.
    if ($extra = $results->getExtraData('search_api_solr_response')) {
      $data['pager']['current_page'] = $extra['response']['start'] / $limit;
      $data['pager']['total_pages'] = ceil($extra['response']['numFound'] / $limit);
    }

    // Prepare results.
    $data['search_results'] = [];
    foreach ($results as $item) {
      // There shouldn't be any extra load there because the entity data is
      // returned by Solr and cached.
      // @see \Drupal\ocha_docstore_files\Plugin\search_api\processor\StoreEntity
      $entity = $item->getOriginalObject(TRUE)->getEntity();

      $date = '';
      if (!empty($entity->field_date->value)) {
        $date = date('d.m.Y', strtotime($entity->field_date->value));
      }

      // As mentioned above, normally the data of all the entities below
      // was retrieved from Solr and there shouldn't be any extra load.
      $data['search_results'][] = [
        'uuid' => $entity->id(),
        'title' => $entity->label(),
        'field_organizations' => array_map(function ($item) {
          return $item->entity->uuid();
        }, iterator_to_array($entity->field_organizations->filterEmptyItems())),
        'field_locations_lat_lon' => array_map(function ($item) {
          return $item->entity->field_geolocation->lat . ',' . $item->entity->field_geolocation->lon;
        }, iterator_to_array($entity->field_locations->filterEmptyItems())),
        'field_locations_label' => array_map(function ($item) {
          return $item->entity->label();
        }, iterator_to_array($entity->field_locations->filterEmptyItems())),
        'field_organizations_label' => array_map(function ($item) {
          return $item->entity->label();
        }, iterator_to_array($entity->field_organizations->filterEmptyItems())),
        'field_asst_organizations_label' => array_map(function ($item) {
          return $item->entity->label();
        }, iterator_to_array($entity->field_asst_organizations->filterEmptyItems())),
        'field_status' => $entity->field_status->entity ? $entity->field_status->entity->label() : '',
        'field_ass_date' => $date,
      ];
    }

    // Handle facets.
    foreach ($facets as $key => $facet_values) {
      if (!isset($facet_to_entity[$key])) {
        continue;
      }

      $field = str_replace('__facet', '', $facet_to_entity[$key]['field']);

      $options = [];
      foreach ($facet_values as $facet_value) {
        // The `xxx__facet` facets contain data in the form `uuid:label`.
        // The `xxx` version which contains only uuids is used for filtering.
        // @see \Drupal\ocha_docstore_files\Plugin\search_api\processor\EntityReferenceFacet.
        list($uuid, $label) = explode(':', trim($facet_value['filter'], '"'), 2);
        $options[] = [
          'key' => $field . ':' . $uuid,
          'label' => $label . ' (' . $facet_value['count'] . ')',
          'active' => isset($active_filters[$field][$uuid]),
        ];
      }

      uasort($options, function ($a, $b) {
        return strnatcasecmp($a['label'], $b['label']);
      });

      $data['facets'][$key]['label'] = $facet_to_entity[$key]['title'];
      $data['facets'][$key]['name'] = $field;
      $data['facets'][$key]['options'] = array_values($options);
    }

    $response = new JsonResponse($data);
    $response->setStatusCode(200);
    return $response;
  }

}
