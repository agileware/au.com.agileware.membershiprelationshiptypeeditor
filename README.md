# Membership Relationship Type Editor (au.com.agileware.membershiprelationshiptypeeditor)

In CiviCRM, if a Membership Type already has Membership records, the CiviCRM administration
interface will not let you change the Relationship Types used to inherit that membership. This is
a problem when your membership structure changes and you need to add or remove the Relationship
Types used for membership inheritance. This kind of change can be made using direct database
queries or API calls, but that is time-consuming, costly, and risks being implemented
incorrectly. This issue has been raised and discussed on the CiviCRM Stack Exchange, see
https://civicrm.stackexchange.com/questions/14497/need-to-change-membership-inheritance

This extension allows the inherited Relationship Types for an existing Membership Type to be
edited, and then updates all existing Memberships affected by the change so that inherited
memberships are recalculated according to the new Relationship Types.

On the Membership Type edit page, the message "You cannot modify relationship type because there
are membership records associated with this membership type." is suppressed and the inherited
Relationship Type field is made editable.

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## How it works

If a change is made to the Relationship Types field on the Membership Type edit page, this
extension:

1. Displays a confirmation prompt to the user, warning that the Relationship Types for this
   Membership Type have changed and that affected memberships will be updated. The user must
   confirm before the change is saved.
2. If confirmed, the Membership Type is added to a processing queue (stored in a CiviCRM setting).
3. A Scheduled Job processes one queued Membership Type at a time in the background:
   * Existing inherited (related) memberships for that Membership Type are deleted, except where
     the relationship linking the inherited contact is still current and expected.
   * Inherited memberships are then recreated for every "owner" membership of that type, based on
     the current Relationship Types configured on the Membership Type.
4. This process may take some time to complete, depending on the number of affected memberships.

## Usage

1. Go to **Administer / CiviMember / Membership Types** and edit an existing Membership Type.
2. Change the values in the inherited Relationship Type field.
3. Click Save. If the Relationship Types were changed, a confirmation prompt is shown; confirm to
   proceed (or cancel to discard the change without saving).
4. The Membership Type is added to the internal processing queue, ready for the Scheduled Job to
   recalculate its related memberships.

### Scheduled Jobs

This extension provides two Scheduled Jobs (**Administer / System Settings / Scheduled Jobs**):

* **Update Memberships based on Membershiptypes** (`MembershipType.updatemembershipsbyrelationships`)
  processes one queued Membership Type each time it runs: it deletes and recreates the inherited
  memberships for that type based on its current Relationship Types. Default run frequency is
  Daily, to avoid performance issues on large sites. To recalculate immediately, run this
  Scheduled Job manually, or trigger the API directly, e.g. via WP-CLI:
  ```
  wp --user=cron --url=https://example.org --path=$DOCUMENT_ROOT civicrm api MembershipType.updatemembershipsbyrelationships
  ```
* **Queue all membership types for update** (`MembershipType.queueallmembershiptypesforupdate`)
  adds every active Membership Type to the processing queue. Default run frequency is Monthly.
  This is intended as a safety net to catch any memberships that may have been missed by the
  regular recalculation process.

If you have a large number of Membership Types and/or Memberships, you may need to adjust the
scheduling of these two jobs so that they do not overlap.

If the [CronPlus](https://civicrm.org/extensions/cron-plus) extension is installed and enabled
when this extension is installed, both Scheduled Jobs are additionally configured with a CronPlus
schedule (1am daily / midnight on the 1st of the month respectively). CronPlus is optional; it is
only used to set a specific cron schedule for these jobs if present.

### Settings page

Go to **Administer / Membership Relationship Type Editor / Settings**
(`civicrm/membershiprelationshiptypeeditor/settings`) to manually add Membership Types to the
processing queue. This is useful if you want to force a recalculation for specific Membership
Types without waiting for the automatic detection of a Relationship Type change, or without
running the monthly "queue all" job. The form shows which Membership Types are currently queued
and lets you select additional ones to add.

### API

This extension adds the following CiviCRM API v3 actions on the `MembershipType` entity:

* `MembershipType.Addmembershiptypesinqueue` - adds one or more Membership Type IDs to the
  processing queue.
* `MembershipType.Queueallmembershiptypesforupdate` - adds every active Membership Type to the
  processing queue.
* `MembershipType.Updatemembershipsbyrelationships` - processes a single queued Membership Type
  (used by the "Update Memberships based on Membershiptypes" Scheduled Job).

## Special configuration requirements

No special configuration, credentials, or dependent extensions are required. After enabling the
extension, confirm that the **Update Memberships based on Membershiptypes** Scheduled Job is
active (it is enabled by default with a Daily run frequency). No CiviCRM permissions beyond
"access CiviCRM" (settings page) and "administer CiviCRM" (navigation menu items) are required to
use the extension's UI.

## Requirements

* CiviCRM 5.51+

## Installation (Web UI)

Learn more about installing CiviCRM extensions in the [CiviCRM Sysadmin
Guide](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/).

## About the Authors

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
