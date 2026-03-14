<?php

/**
 * @file
 * Defines functions related to views integration.
 */

/**
 * Implements hook_views_data().
 */
function counter_views_data() {
  // Basic table information.
  $data['counter']['table']['group'] = t('Counter');

  // Advertise this table as a possible base table.
  $data['counter']['table']['base'] = [
    'field' => 'counter_id',
    'title' => t('Counter'),
    'help' => t('Counter list.'),
    'weight' => 10,
  ];

  $data['users_field_data']['table']['join'] = [
    'counter' => [
      'left_field' => 'uid',
      'field' => 'uid',
      'type' => 'LEFT',
    ],
  ];

  $data['counter']['counter_id'] = [
    'title' => t('Counter ID'),
    'help' => t('The Counter ID.'),
    'field' => [
      'id' => 'numeric',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
  ];

  $data['counter']['ip'] = [
    'title' => t('IP Address'),
    'help' => t('The Counter IP Address.'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $data['counter']['url'] = [
    'title' => t('URL'),
    'help' => t('The URL when Counter counted.'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $data['counter']['created'] = [
     'title' => t('Created'),
    'help' => t('The created time of Counter item.'),
    'field' => [
      'id' => 'date',
    ],
    'filter' => [
      'id' => 'date',
    ],
    'sort' => [
      'id' => 'date',
    ],
  ];

  $data['counter']['uid'] = [
    'title' => t('UID'),
    'help' => t('The UID who visit Counter URL.'),
    'relationship' => [
      'title' => t('Counter user'),
      'help' => t('Relate content to the user for which the data was counted.'),
      'base' => 'users_field_data',
      'base field' => 'uid',
      'id' => 'standard',
       'label' => t('Counter User'),
    ],
    'field' => [
      'id' => 'numeric',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
  ];

  // Data definition for nid, type, browser_name, browser_version, platform.
  $data['counter']['nid'] = [
    'title' => t('Node ID'),
    'help' => t('The NID of URL if node was visited.'),
    'field' => [
      'id' => 'numeric',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
  ];

  $data['counter']['type'] = [
   'title' => t('Content Type'),
    'help' => t('The content type of node if node was visited.'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $data['counter']['browser_name'] = [
    'title' => t('Browser name'),
    'help' => t('The Browser Name of Client Browser.'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $data['counter']['browser_version'] = [
    'title' => t('Browser version'),
    'help' => t('The Browser Version of Client Browser.'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $data['counter']['platform'] = [
    'title' => t('Platform'),
    'help' => t('The Platform Name of Client System/OS.'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  return $data;
}
