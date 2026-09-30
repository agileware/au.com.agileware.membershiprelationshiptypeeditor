<?php

/**
 * Thrown when saving an inherited membership would pass a membership back to
 * a contact it was already inherited from.
 */
class CRM_Membershiprelationshiptypeeditor_InheritanceLoopException extends CRM_Core_Exception {

}
