<?php

use CRM_Membershiprelationshiptypeeditor_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Membershiprelationshiptypeeditor_Form_UpdateMembershipTypes extends CRM_Core_Form {
  public function buildQuickForm() {
    $this->add(
      'select2',
      'membership_types',
      'Membership Types',
      $this->getMembershipTypes(),
      TRUE,
      array(
        'multiple' => TRUE,
      )
    );
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Update Selected Membership Types'),
        'isDefault' => TRUE,
      ),
    ));

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
    $options = array();

    foreach ($membershipTypes as $membershipTypeId => $membershipType) {
      $options[] = array(
        'text' => $membershipType,
        'id'   => $membershipTypeId,
      );
    }

    return $options;
  }

  public function postProcess() {
    $values = $this->exportValues();
    $membershipTypes = explode(",", $values['membership_types']);

    civicrm_api3('MembershipType', 'addmembershiptypesinqueue', [
      'membershiptypes' => $membershipTypes,
    ]);

    CRM_Core_Session::setStatus(
      ts('Selected membership types has been added into queue.'),
      ts('Update Membership Types'), 'success');

    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/membershiprelationshiptypeeditor/settings'));

    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    $elementNames = array();
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
