<?php

use CRM_Membershiprelationshiptypeeditor_ExtensionUtil as E;

/**
 * Shows the processing queue and lets an administrator add membership types
 * to it or take them out.
 */
class CRM_Membershiprelationshiptypeeditor_Form_UpdateMembershipTypes extends CRM_Core_Form {

  public function buildQuickForm() {
    $defaults = [];

    $this->add(
      'select2',
      'membership_types',
        E::ts('Membership Types'),
      $this->getMembershipTypes(),
      FALSE,
      [
        'multiple' => TRUE,
      ]
    );
    $defaults['membership_types'] = array_keys(CRM_Membershiprelationshiptypeeditor_Queue::get());

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Save Queue'),
        'isDefault' => TRUE,
      ],
    ]);

    $this->setDefaults($defaults);
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
    $membershipTypes = array_filter(explode(',', $values['membership_types'] ?? ''));

    CRM_Membershiprelationshiptypeeditor_Queue::replace($membershipTypes);

    CRM_Core_Session::setStatus(
      E::ts('The queue now holds the selected membership types.'),
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
