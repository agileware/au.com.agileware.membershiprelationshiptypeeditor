<?php

require_once 'membershiprelationshiptypeeditor.civix.php';
use CRM_Membershiprelationshiptypeeditor_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function membershiprelationshiptypeeditor_civicrm_config(&$config) {
  _membershiprelationshiptypeeditor_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function membershiprelationshiptypeeditor_civicrm_install() {
  _membershiprelationshiptypeeditor_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function membershiprelationshiptypeeditor_civicrm_postInstall() {
  _membershiprelationshiptypeeditor_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function membershiprelationshiptypeeditor_civicrm_uninstall() {
  _membershiprelationshiptypeeditor_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function membershiprelationshiptypeeditor_civicrm_enable() {
  _membershiprelationshiptypeeditor_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function membershiprelationshiptypeeditor_civicrm_disable() {
  _membershiprelationshiptypeeditor_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function membershiprelationshiptypeeditor_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _membershiprelationshiptypeeditor_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function membershiprelationshiptypeeditor_civicrm_entityTypes(&$entityTypes) {
  _membershiprelationshiptypeeditor_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Enabled relationship type field on edit membership type form.
 *
 * @param $formName
 * @param $form
 */
function membershiprelationshiptypeeditor_civicrm_buildForm($formName, &$form) {
  if ($formName === 'CRM_Member_Form_MembershipType' && isset($form->_id) && isset($form->_action) && $form->_action == CRM_Core_Action::UPDATE) {
    CRM_Core_Resources::singleton()->addScriptFile('au.com.agileware.membershiprelationshiptypeeditor', 'js/membership_type.js');
    $form->assign('membershipRecordsExists', FALSE);
    $form->_elements[$form->_elementIndex['relationship_type_id']]->_flagFrozen = 0;
    $form->assign('selectedRelationshipTypes', $form->_elements[$form->_elementIndex['relationship_type_id']]->_values);
  }

  if ($formName == "CRM_Member_Form_MembershipType") {
    $form->assign('formAction', $form->_action);
    CRM_Core_Region::instance('page-footer')->add([
      'template' => E::path('templates/membership_type_relationship.tpl'),
    ]);
  }
}

/**
 * Add membership type in queue to process if relationship types value has been changed.
 *
 * @param $formName
 * @param $form
 */
function membershiprelationshiptypeeditor_civicrm_postProcess($formName, &$form) {
  if ($formName == "CRM_Member_Form_MembershipType" && isset($form->_id) && isset($form->_action) && $form->_action == CRM_Core_Action::UPDATE) {
    $defaultRelationshipTypes = $form->_defaultValues['relationship_type_id'];
    $submittedRelationshipTypes = $form->_submitValues['relationship_type_id'];

    if (!is_array($defaultRelationshipTypes)) {
      $defaultRelationshipTypes = [];
    }

    if (!is_array($submittedRelationshipTypes)) {
      $submittedRelationshipTypes = [];
    }

    $modifiedRelationshipTypes = array_diff($defaultRelationshipTypes, $submittedRelationshipTypes);
    $modifiedRelationshipTypes = array_merge(array_diff($submittedRelationshipTypes, $defaultRelationshipTypes), $modifiedRelationshipTypes);

    if (count($modifiedRelationshipTypes) > 0) {
      $typesToProcess = Civi::settings()->get('membershiprelationshiptypeeditor_mtypes_process');
      if ($typesToProcess == '' || $typesToProcess == NULL) {
        $typesToProcess = [];
      }
      $typesToProcess[$form->_id] = TRUE;
      Civi::settings()->set('membershiprelationshiptypeeditor_mtypes_process', $typesToProcess);
    }
  }
}

/**
 * Manipulates CiviCRM menu.
 *
 * @param $menu
 */
function membershiprelationshiptypeeditor_civicrm_navigationMenu(&$menu) {
  _membershiprelationshiptypeeditor_civix_insert_navigation_menu($menu, 'Administer', [
    'label' => E::ts('Membership Relationship Type Editor'),
    'name' => 'MembershipRelationshipTypeEditor',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ]);
  _membershiprelationshiptypeeditor_civix_insert_navigation_menu($menu, 'Administer/MembershipRelationshipTypeEditor', [
    'label' => E::ts('Settings'),
    'name' => 'MembershipRelationshipTypeEditorSettings',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'url' => 'civicrm/membershiprelationshiptypeeditor/settings',
    'separator' => 0,
  ]);
}
