<?php

return [
  [
    'module' => 'au.com.agileware.membershiprelationshiptypeeditor',
    'name' => 'membershipType_relationshipType_process_cron',
    'entity' => 'Job',
    'update' => 'never',
    'params' => [
      'version' => 3,
      'run_frequency' => 'Always',
      'name' => 'Update Memberships based on Membershiptypes',
      'description' => 'Process pending membership types in which relationship types are updated.',
      'api_entity' => 'MembershipType',
      'api_action' => 'updatemembershipsbyrelationships',
      'parameters' => '',
      'is_active'  => '1',
    ],
  ],
];
