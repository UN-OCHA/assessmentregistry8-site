[![Development build](https://travis-ci.com/UN-OCHA/assessmentregistry8-site.svg?branch=develop)](https://travis-ci.com/UN-OCHA/assessmentregistry8-site)
[![Master build](https://travis-ci.com/UN-OCHA/assessmentregistry8-site.svg?branch=master)](https://travis-ci.com/UN-OCHA/assessmentregistry8-site)
![Development image](https://github.com/UN-OCHA/assessmentregistry8-site/workflows/Build%20docker%20image/badge.svg?branch=develop)
![Master image](https://github.com/UN-OCHA/assessmentregistry8-site/workflows/Build%20docker%20image/badge.svg?branch=master)

# Assessment Registry

## Development

For local development, add this line to settings.local.php:
`$config['config_split.config_split.config_dev']['status'] = TRUE;`
After importing a fresh database, run `drush csim` to enable devel, database
log and stage_file_proxy.

## Pages

- https://assessmentregistry8-site.docksal/assessments/table
- https://assessmentregistry8-site.docksal/assessments/list
- https://assessmentregistry8-site.docksal/assessments/map
- https://assessmentregistry8-site.docksal/knowledge-management

## JSON API

Has a problem outputting the label of a select list.

Test link: `http://assessmentregistry8-site.docksal/jsonapi/index/knowledge_management?filter[field_countries]=210`

## REST + json

Works:

- http://assessmentregistry8-site.docksal/rest/knowledge-management
- http://assessmentregistry8-site.docksal/rest/knowledge-management?x=&f[0]=country%3A106
- http://assessmentregistry8-site.docksal/rest/assessments?items_per_page=All

### JSON map

Add new endpoint, change existing for the map and return all data.

## Todo

1. Use templates for formatters
2. ~~Fix json import~~
3. ~~Do full sync if cache is empty~~
4. ~~Create base class~~
5. ~~Move helpers to ocha_integrations~~
6. Update existing assessments
7. ~~Use JSON callback for map~~
8. Sidebar has max width for map filters
9. Add content pages

## Cron jobs

```bash
fin drush eval --verbose "ocha_countries_cron()"
fin drush eval --verbose "ocha_disasters_cron()"
fin drush eval --verbose "ocha_local_groups_cron()"
fin drush eval --verbose "ocha_locations_cron()"
fin drush eval --verbose "ocha_organizations_cron()"
fin drush eval --verbose "ocha_themes_cron()"
```

## Migrate

```sql
select * from node__field_ass_date limit 10;
select * from node__field_assessment_data limit 10;
select * from node__field_assessment_questionnaire limit 10;
select * from node__field_assessment_report limit 10;
select * from node__field_asst_organizations limit 10;
select * from node__field_collection_methods limit 10;
select * from node__field_countries limit 10;
select * from node__field_disasters limit 10;
select * from node__field_frequency limit 10;
select * from node__field_key_findings limit 10;
select * from node__field_local_groups limit 10;
select * from node__field_locations limit 10;
select * from node__field_methodology limit 10;
select * from node__field_organizations limit 10;
select * from node__field_other_location limit 10;
select * from node__field_population_types limit 10;
select * from node__field_status limit 10;
select * from node__field_subject_objective limit 10;
select * from node__field_units_of_measurement limit 10;
```

### Wrong mapping

```sql
select * from node__field_themes limit 10; # wrong mapping
```

### Missing data

```sql
select * from node__field_level_of_representation limit 10;
select * from node__field_related_content limit 10;
select * from node__field_sample_size limit 10;
select * from node__field_sources limit 10;
```

Quick check using

```sql
select table_schema as database_name, table_name
   from information_schema.tables
where table_type = 'BASE TABLE' and table_rows = 0 and table_schema = 'default' and table_name like 'node__field%'
order by table_name;
```

### KM view (D7)

```php
$view = new view();
$view->name = 'km';
$view->description = '';
$view->tag = 'default';
$view->base_table = 'node';
$view->human_name = 'KM';
$view->core = 7;
$view->api_version = '3.0';
$view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

/* Display: Master */
$handler = $view->new_display('default', 'Master', 'default');
$handler->display->display_options['title'] = 'KM';
$handler->display->display_options['use_more_always'] = FALSE;
$handler->display->display_options['access']['type'] = 'perm';
$handler->display->display_options['cache']['type'] = 'none';
$handler->display->display_options['query']['type'] = 'views_query';
$handler->display->display_options['exposed_form']['type'] = 'basic';
$handler->display->display_options['pager']['type'] = 'full';
$handler->display->display_options['pager']['options']['items_per_page'] = '10';
$handler->display->display_options['style_plugin'] = 'table';
/* Field: Content: Title */
$handler->display->display_options['fields']['title']['id'] = 'title';
$handler->display->display_options['fields']['title']['table'] = 'node';
$handler->display->display_options['fields']['title']['field'] = 'title';
$handler->display->display_options['fields']['title']['alter']['word_boundary'] = FALSE;
$handler->display->display_options['fields']['title']['alter']['ellipsis'] = FALSE;
/* Field: Content: Context */
$handler->display->display_options['fields']['field_km_context']['id'] = 'field_km_context';
$handler->display->display_options['fields']['field_km_context']['table'] = 'field_data_field_km_context';
$handler->display->display_options['fields']['field_km_context']['field'] = 'field_km_context';
$handler->display->display_options['fields']['field_km_context']['settings'] = array(
  'bypass_access' => 0,
  'link' => 0,
);
$handler->display->display_options['fields']['field_km_context']['delta_offset'] = '0';
/* Field: Content: Country */
$handler->display->display_options['fields']['field_km_country']['id'] = 'field_km_country';
$handler->display->display_options['fields']['field_km_country']['table'] = 'field_data_field_km_country';
$handler->display->display_options['fields']['field_km_country']['field'] = 'field_km_country';
$handler->display->display_options['fields']['field_km_country']['settings'] = array(
  'bypass_access' => 0,
  'link' => 0,
);
/* Field: Content: Document */
$handler->display->display_options['fields']['field_km_document']['id'] = 'field_km_document';
$handler->display->display_options['fields']['field_km_document']['table'] = 'field_data_field_km_document';
$handler->display->display_options['fields']['field_km_document']['field'] = 'field_km_document';
$handler->display->display_options['fields']['field_km_document']['click_sort_column'] = 'fid';
$handler->display->display_options['fields']['field_km_document']['type'] = 'file_url_plain';
$handler->display->display_options['fields']['field_km_document']['delta_offset'] = '0';
/* Field: Content: Document type */
$handler->display->display_options['fields']['field_km_document_type']['id'] = 'field_km_document_type';
$handler->display->display_options['fields']['field_km_document_type']['table'] = 'field_data_field_km_document_type';
$handler->display->display_options['fields']['field_km_document_type']['field'] = 'field_km_document_type';
$handler->display->display_options['fields']['field_km_document_type']['settings'] = array(
  'bypass_access' => 0,
  'link' => 0,
);
/* Field: Content: Global cluster */
$handler->display->display_options['fields']['field_km_global_cluster']['id'] = 'field_km_global_cluster';
$handler->display->display_options['fields']['field_km_global_cluster']['table'] = 'field_data_field_km_global_cluster';
$handler->display->display_options['fields']['field_km_global_cluster']['field'] = 'field_km_global_cluster';
$handler->display->display_options['fields']['field_km_global_cluster']['type'] = 'list_key';
$handler->display->display_options['fields']['field_km_global_cluster']['delta_offset'] = '0';
/* Field: Content: HPC Document Repository */
$handler->display->display_options['fields']['field_km_hpc_repository']['id'] = 'field_km_hpc_repository';
$handler->display->display_options['fields']['field_km_hpc_repository']['table'] = 'field_data_field_km_hpc_repository';
$handler->display->display_options['fields']['field_km_hpc_repository']['field'] = 'field_km_hpc_repository';
$handler->display->display_options['fields']['field_km_hpc_repository']['settings'] = array(
  'bypass_access' => 0,
  'link' => 0,
);
$handler->display->display_options['fields']['field_km_hpc_repository']['delta_offset'] = '0';
/* Field: Content: Life cycle steps */
$handler->display->display_options['fields']['field_km_life_cycle_steps']['id'] = 'field_km_life_cycle_steps';
$handler->display->display_options['fields']['field_km_life_cycle_steps']['table'] = 'field_data_field_km_life_cycle_steps';
$handler->display->display_options['fields']['field_km_life_cycle_steps']['field'] = 'field_km_life_cycle_steps';
$handler->display->display_options['fields']['field_km_life_cycle_steps']['settings'] = array(
  'bypass_access' => 0,
  'link' => 0,
);
$handler->display->display_options['fields']['field_km_life_cycle_steps']['delta_offset'] = '0';
/* Field: Content: Media */
$handler->display->display_options['fields']['field_km_media']['id'] = 'field_km_media';
$handler->display->display_options['fields']['field_km_media']['table'] = 'field_data_field_km_media';
$handler->display->display_options['fields']['field_km_media']['field'] = 'field_km_media';
$handler->display->display_options['fields']['field_km_media']['click_sort_column'] = 'fid';
$handler->display->display_options['fields']['field_km_media']['type'] = 'file_url_plain';
$handler->display->display_options['fields']['field_km_media']['delta_offset'] = '0';
/* Field: Content: Original publication date */
$handler->display->display_options['fields']['field_km_publication_date']['id'] = 'field_km_publication_date';
$handler->display->display_options['fields']['field_km_publication_date']['table'] = 'field_data_field_km_publication_date';
$handler->display->display_options['fields']['field_km_publication_date']['field'] = 'field_km_publication_date';
$handler->display->display_options['fields']['field_km_publication_date']['type'] = 'date_plain';
$handler->display->display_options['fields']['field_km_publication_date']['settings'] = array(
  'format_type' => 'custom',
  'custom_date_format' => 'Y-m-d',
  'fromto' => 'both',
  'multiple_number' => '',
  'multiple_from' => '',
  'multiple_to' => '',
  'show_remaining_days' => 0,
);
/* Field: Content: Population Type(s) */
$handler->display->display_options['fields']['field_population_types']['id'] = 'field_population_types';
$handler->display->display_options['fields']['field_population_types']['table'] = 'field_data_field_population_types';
$handler->display->display_options['fields']['field_population_types']['field'] = 'field_population_types';
$handler->display->display_options['fields']['field_population_types']['settings'] = array(
  'bypass_access' => 0,
  'link' => 0,
);
$handler->display->display_options['fields']['field_population_types']['delta_offset'] = '0';
/* Field: Content: Post date */
$handler->display->display_options['fields']['created']['id'] = 'created';
$handler->display->display_options['fields']['created']['table'] = 'node';
$handler->display->display_options['fields']['created']['field'] = 'created';
$handler->display->display_options['fields']['created']['date_format'] = 'custom';
$handler->display->display_options['fields']['created']['custom_date_format'] = 'Y-m-d';
$handler->display->display_options['fields']['created']['second_date_format'] = 'search_api_facetapi_YEAR';
$handler->display->display_options['fields']['created']['timezone'] = 'UTC';
/* Field: Content: Published */
$handler->display->display_options['fields']['status']['id'] = 'status';
$handler->display->display_options['fields']['status']['table'] = 'node';
$handler->display->display_options['fields']['status']['field'] = 'status';
$handler->display->display_options['fields']['status']['not'] = 0;
/* Field: Content: Updated date */
$handler->display->display_options['fields']['changed']['id'] = 'changed';
$handler->display->display_options['fields']['changed']['table'] = 'node';
$handler->display->display_options['fields']['changed']['field'] = 'changed';
$handler->display->display_options['fields']['changed']['date_format'] = 'custom';
$handler->display->display_options['fields']['changed']['custom_date_format'] = 'Y-m-d';
$handler->display->display_options['fields']['changed']['second_date_format'] = 'search_api_facetapi_YEAR';
$handler->display->display_options['fields']['changed']['timezone'] = 'UTC';
/* Field: Field: Description */
$handler->display->display_options['fields']['description_field']['id'] = 'description_field';
$handler->display->display_options['fields']['description_field']['table'] = 'field_data_description_field';
$handler->display->display_options['fields']['description_field']['field'] = 'description_field';
$handler->display->display_options['fields']['description_field']['link_to_entity'] = 0;
/* Field: Content: Path */
$handler->display->display_options['fields']['path']['id'] = 'path';
$handler->display->display_options['fields']['path']['table'] = 'node';
$handler->display->display_options['fields']['path']['field'] = 'path';
/* Field: Content: Nid */
$handler->display->display_options['fields']['nid']['id'] = 'nid';
$handler->display->display_options['fields']['nid']['table'] = 'node';
$handler->display->display_options['fields']['nid']['field'] = 'nid';
/* Sort criterion: Content: Post date */
$handler->display->display_options['sorts']['created']['id'] = 'created';
$handler->display->display_options['sorts']['created']['table'] = 'node';
$handler->display->display_options['sorts']['created']['field'] = 'created';
$handler->display->display_options['sorts']['created']['order'] = 'DESC';
/* Filter criterion: Content: Published */
$handler->display->display_options['filters']['status']['id'] = 'status';
$handler->display->display_options['filters']['status']['table'] = 'node';
$handler->display->display_options['filters']['status']['field'] = 'status';
$handler->display->display_options['filters']['status']['value'] = 1;
$handler->display->display_options['filters']['status']['group'] = 1;
$handler->display->display_options['filters']['status']['expose']['operator'] = FALSE;
/* Filter criterion: Content: Type */
$handler->display->display_options['filters']['type']['id'] = 'type';
$handler->display->display_options['filters']['type']['table'] = 'node';
$handler->display->display_options['filters']['type']['field'] = 'type';
$handler->display->display_options['filters']['type']['value'] = array(
  'reg_knowledge_management' => 'reg_knowledge_management',
);

/* Display: Page */
$handler = $view->new_display('page', 'Page', 'page');
$handler->display->display_options['path'] = 'admin/migrate/km';

/* Display: Data export */
$handler = $view->new_display('views_data_export', 'Data export', 'views_data_export_1');
$handler->display->display_options['pager']['type'] = 'none';
$handler->display->display_options['pager']['options']['offset'] = '0';
$handler->display->display_options['style_plugin'] = 'views_data_export_csv';
$handler->display->display_options['path'] = 'admin/migrate/km/reg_km.csv';
$handler->display->display_options['displays'] = array(
  'page' => 'page',
  'default' => 0,
);
$handler->display->display_options['use_batch'] = 'batch';
$handler->display->display_options['return_path'] = 'admin/migrate/km';
$handler->display->display_options['segment_size'] = '100';
$translatables['km'] = array(
  t('Master'),
  t('KM'),
  t('more'),
  t('Apply'),
  t('Reset'),
  t('Sort by'),
  t('Asc'),
  t('Desc'),
  t('Items per page'),
  t('- All -'),
  t('Offset'),
  t('« first'),
  t('‹ previous'),
  t('next ›'),
  t('last »'),
  t('Title'),
  t('Context'),
  t('Country'),
  t('Document'),
  t('Document type'),
  t('Global cluster'),
  t('HPC Document Repository'),
  t('Life cycle steps'),
  t('Media'),
  t('Original publication date'),
  t('Population Type(s)'),
  t('Post date'),
  t('Published'),
  t('Updated date'),
  t('Description'),
  t('Path'),
  t('Nid'),
  t('Page'),
  t('Data export'),
);
```
