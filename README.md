Membership Relationship Type Editor for CiviCRM
------

In CiviCRM currently, if a Membership Type has Membership records in CiviCRM there is no way in the CiviCRM administration interface to change the Relationship Types that will be used to inherit this membership. This is problematic when your membership structure changes and you need to add or change the Relationship Types used for membership inheritance. This type of change can be implemented using direct database queries or API calls, however this is time-consuming, costly and potentially problematic to implement correctly. This issue has been raised and discussed on the CiviCRM Stack Exchange see https://civicrm.stackexchange.com/questions/14497/need-to-change-membership-inheritance

This extension enables the inherited Relationship Types for an existing Membership Type to be changed and update all existing Memberships affected by this change so that their inherited membership is correct according to the new inherited Relationship Types.

On the Membership Type page, the message: "You cannot modify relationship type because there are membership records associated with this membership type." is removed and the inherited Relationship Type fields are available for editing.

If any change is made to the Relationship Types field on the Membership Type edit page then this extension:

1. Displays a message and confirmation prompt to the user. Message informs them that the Relationship Types for this membership type have changed, affected memberships will be updated. Requests confirmation to proceed with the change.
2. The user can then confirm or cancel this change.
3. If the user confirms the change, then all affected inherited memberships will be re-calculated in the background using the CiviCRM batch API, executed by a Scheduled Task.
4. This process may take some time to complete depending on the changes and how many membership exist in the database.


Installation
------

1. Download the [latest version of this extension](https://github.com/agileware/au.com.agileware.membershiprelationshiptypeeditor/archive/master.zip)
2. Unzip in the CiviCRM extension directory, as defined in 'System Settings / Directories'.
3. Go to "Administer / System Settings / Extensions" and enable the "Membership Relationship Type Editor (au.com.agileware.membershiprelationshiptypeeditor)" extension.
4. Go to "Administer / System Settings / Scheduled Jobs" and check that the **Update Memberships based on Membershiptypes** Scheduled Job is enabled. The run frequency should be "Every time cron job is run"

Usage
------

1. After enabling the extension, edit an existing Membership Type.
2. Change the values for the inherited Relationship Type field.
3. Click save and confirm the change.
4. The affected inherited memberships will be re-calculated in the background.
5. Memberships will not be recalculated until the next cron run. If you want to update immediately, you can do so using the Scheduled Jobs interface or using your API command line, e.g.:
   ```wp --user=cron --url=https://example.org --path=$DOCUMENT_ROOT civicrm api MembershipType.updatemembershipsbyrelationships```
6. A job is run daily - at 1 a.m. if cronplus is installed - to queue recalculation of all inherited memberships.

About the Authors
------

This CiviCRM extension was developed by the team at [Agileware](https://agileware.com.au).

[Agileware](https://agileware.com.au) provide a range of CiviCRM services including:

  * CiviCRM migration
  * CiviCRM integration
  * CiviCRM extension development
  * CiviCRM support
  * CiviCRM hosting
  * CiviCRM remote training services

Support your Australian [CiviCRM](https://civicrm.org) developers, [contact Agileware](https://agileware.com.au/contact) today!


![Agileware](logo/agileware-logo.png)
