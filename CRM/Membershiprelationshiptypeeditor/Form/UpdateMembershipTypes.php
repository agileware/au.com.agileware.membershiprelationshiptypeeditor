<?php

use CRM_Membershiprelationshiptypeeditor_ExtensionUtil as E;

/**
 * Form controller class
 *
 */
class CRM_Membershiprelationshiptypeeditor_Form_UpdateMembershipTypes extends CRM_Core_Form {

  public function buildQuickForm() {
    $this->add(
      'select2',
      'membership_types',
        E::ts('Membership Types'),
      $this->getMembershipTypes(),
      TRUE,
      [
        'multiple' => TRUE,
      ]
    );
    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Update Selected Membership Types'),
        'isDefault' => TRUE,
      ],
    ]);

    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  /**
   * Get membership types for select2 element
   *
   * @return array
   */
  private function getMembershipTypes() {
    $membershipTypes = CRM_Member_PseudoConstant::membershipType();
    $options = [];

    foreach ($membershipTypes as $membershipTypeId => $membershipType) {
      $options[] = [
        'text' => $membershipType,
        'id'   => $membershipTypeId,
      ];
    }

    return $options;
  }

  public function postProcess() {
    $values = $this->exportValues();
    $membershipTypes = explode(',', $values['membership_types']);

    civicrm_api3('MembershipType', 'addmembershiptypesinqueue', [
      'membershiptypes' => $membershipTypes,
    ]);

    CRM_Core_Session::setStatus(
      E::ts('Selected membership types has been added into queue.'),
      E::ts('Update Membership Types'), 'success');

    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/membershiprelationshiptypeeditor/settings'));

    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    $elementNames = [];
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
