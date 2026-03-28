<?php

use Civi\Api4\Extension;

$hasCronPlus = Extension::get(FALSE)
                        ->addWhere('key', '=', 'cronplus')
                        ->addWhere('status', '=', 'installed')
                        ->selectRowCount()
                        ->execute()
                        ->count() > 0;

return [
  [
    'module' => 'au.com.agileware.membershiprelationshiptypeeditor',
    'name' => 'membershipType_relationshipType_process_cron',
    'entity' => 'Job',
    'update' => 'never',
    'params' => [
      'version' => 3,
      'run_frequency' => 'Daily',
      'name' => 'Update Memberships based on Membershiptypes',
      'description' => 'Process pending membership types in which relationship types are updated.',
      'api_entity' => 'MembershipType',
      'api_action' => 'updatemembershipsbyrelationships',
      'parameters' => '',
      'is_active'  => '1',
      ...($hasCronPlus ? [
        'api.Cronplus.create' => [
          'job_id'     => '$value.id',
          'cron'       => '0 1 * * *'
        ]
      ] : [])
    ],
  ],
  [
    'module' => 'au.com.agileware.membershiprelationshiptypeeditor',
    'name' => 'membershipType_relationshipType_queueall_cron',
    'entity' => 'Job',
    'update' => 'never',
    'params' => [
      'version' => 3,
      'run_frequency' => 'Monthly',
      'name' => 'Queue all membership types for update',
      'description' => 'Adds all active membership types to the next processing queue, to ensure any changes that have been queued for processing already get executed.',
      'api_entity' => 'MembershipType',
      'api_action' => 'queueallmembershiptypesforupdate',
      'parameters' => '',
      'is_active'  => '1',
      ...($hasCronPlus ? [
          'api.Cronplus.create' => [
            'job_id'     => '$value.id',
            'cron'       => '0 0 1 * *'
          ]
        ] : [])
    ],
  ],
];
