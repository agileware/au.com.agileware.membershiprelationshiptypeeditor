<?php
use CRM_Membershiprelationshiptypeeditor_ExtensionUtil as E;

return array(
  'membershiprelationshiptypeeditor_mtypes_process' => array(
    'group_name' => E::ts('Membership Relationship Types Settings'),
    'group' => 'membershiprelationshiptypeeditor',
    'name' => 'membershiprelationshiptypeeditor_mtypes_process',
    'type' => 'Text',
    'add' => '4.7',
    'default' => '',
    'title' => E::ts('Membership Types to Process'),
    'is_domain' => 1,
    'is_contact' => 0,
  ),
);
