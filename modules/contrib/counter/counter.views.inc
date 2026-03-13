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
    'title' => t('Counter', [], ['context' => 'counter']),
    'help' => t('Counter list.', [], ['context' => 'counter']),
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
    'title' => t('Counter ID', [], ['context' => 'counter']),
    'help' => t('The Counter ID.', [], ['context' => 'counter']),
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
    'title' => t('IP Address', [], ['context' => 'counter']),
    'help' => t('The Counter IP Address.', [], ['context' => 'counter']),
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
    'title' => t('URL', [], ['context' => 'counter']),
    'help' => t('The URL when Counter counted.', [], ['context' => 'counter']),
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
    'title' => t('Created', [], ['context' => 'counter']),
    'help' => t(
      'The created time of Counter item.',
      [],
      ['context' => 'counter'],
    ),
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
    'title' => t('UID', [], ['context' => 'counter']),
    'help' => t('The UID who visit Counter URL.', [], ['context' => 'counter']),
    'relationship' => [
      'title' => t('Counter user', [], ['context' => 'counter']),
      'help' => t(
        'Relate content to the user for which the data was counted.',
        [],
        ['context' => 'counter'],
      ),
      'base' => 'users_field_data',
      'base field' => 'uid',
      'id' => 'standard',
      'label' => t('Counter User', [], ['context' => 'counter']),
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
    'title' => t('Node ID', [], ['context' => 'counter']),
    'help' => t(
      'The NID of URL if node was visited.',
      [],
      ['context' => 'counter'],
    ),
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
    'title' => t('Content Type', [], ['context' => 'counter']),
    'help' => t(
      'The content type of node if node was visited.',
      [],
      ['context' => 'counter'],
    ),
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
    'title' => t('Browser name', [], ['context' => 'counter']),
    'help' => t(
      'The Browser Name of Client Browser.',
      [],
      ['context' => 'counter'],
    ),
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
    'title' => t('Browser version', [], ['context' => 'counter']),
    'help' => t(
      'The Browser Version of Client Browser.',
      [],
      ['context' => 'counter'],
    ),
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
    'title' => t('Platform', [], ['context' => 'counter']),
    'help' => t(
      'The Platform Name of Client System/OS.',
      [],
      ['context' => 'counter'],
    ),
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
