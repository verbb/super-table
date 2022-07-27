# Changelog

## 3.0.0 - 2022-07-27

### Added
- Add missing English Translations.
- Add resave console command for elements.
- Add checks for registering events for performance.
- Add `archiveTableIfExists()` to install migration.

### Changed
- Now requires PHP `^8.0.2`.
- Now requires Craft `^4.0.0`.
- Super Table database tables and content is now permanently deleted when uninstalling the plugin.
- Rename model classes.
- Rename record classes.
- Rename service classes.
- Rename base plugin methods.

### Fixed
- Fixed a bug where fields were assuming their values hadn’t been eager-loaded on element save.
- Fixed block ordering issues, and ensure sort order is updated when block ownership is duplicated. (thanks @mmikkel).
- Fixed a bug where changes to existing blocks weren’t saving for element types that supported drafts but not change tracking.
- Fix Matrix > Super Table fields not saving new fields when editing the field
- Fix move/delete button sizing for table layout.
- Fix a JS error when viewing a static field, in Matrix layout.
- Fix JS not initialising when selecting new fields in settings (`footHtml` to `bodyHtml`).
- Fix an error when running the “check content tables” helper.
- Fix an error with new static fields not having any field content output in the control panel.
- Fix an error where return type of `prepareQuery` was incorrect. (thanks @davidwebca).
- Fix potential error in migration.

### Removed
- Remove deprecated Craft and Super Table functions.

## 2.7.2 - 2022-05-15

### Fixed
- Fix an incompatibility with Craft 3.7.11 or less.
- Fix checking against a derivative block.
- Fix in some cases not fetching the correct supported sites for a block.
- Fix GraphQL mutations. Blocks now require `type_blockTypeId`. (see https://github.com/verbb/super-table/issues/449).

## 2.7.1 - 2021-12-31

### Fixed
- Fixed a bug where fields weren’t getting reverted properly when reverting an entry’s content to a prior revision, if they were nested within a Neo or Matrix field.
- Fixed a bug where blocks within drafts could lose track of their canonical blocks when they were updated upstream, resulting in duplicated blocks.
- Fixed an error with GQL mutations.
- Find and fix tables with missing indexes. (thanks @gbowne-quickbase).
- Fixed field instructions showing raw HTML. (thanks @mmikkel).

## 2.7.0 - 2021-11-30

### Added
- Add SuperTable Block Type to superTableMatrix, superTableRow, tr.
- Craft 3.7+ compatibility.
- Blocks now have an `id` field to allow modifying existing Super Table blocks when using mutations with the GraphQL API.
- Allow for widths in Matrix styled Super Tables.

### Changed
- Now requires Craft 3.7+.
- Update GraphQL classes to Craft 3.7+.
- Show `.heading-text` of nested SuperTables in SuperTableMatrix. And there is no element with `.heading-text` in SuperTableMatrix related templates.

### Fixed
- Fix a potential XSS vulnerability.
- Fix elements overflowing grid column bounds in Row Layout.
- Fix change status indicator alignment for blocks.
- Fix a missing class include.
- Fix new static-field row blocks not getting the `static-field` class.
- Fix missing css class to fields container of Matrix styled SuperTable.

## 2.6.8 - 2021-05-19

### Fixed
- Fix delete row behaviour when an outer static Super Table field, Matrix and inner Super Table field.
- Fix inconsistent project config warnings when using the content table fixer.
- Fix for draft change merging bug. (thanks @brandonkelly).

## 2.6.7 - 2021-02-06

### Fixed
- Fix an issue with Gatsby Helper plugin and Super Table blocks.

## 2.6.6 - 2021-01-27

### Fixed
- Fix being unable to directly access the first block in a Super Table field, which would otherwise return `null`. Please see [this issue](https://github.com/verbb/super-table/issues/399#issuecomment-768015110) in that if you're getting this error, you're likely using incorrect/unsupported template syntax. Using `entry.superTableField.myField` as a means for direct-access is reserved for static fields.

## 2.6.5 - 2020-12-16

### Fixed
- Fix an `Undefined property` fatal error when saving an element containing a Super Table field, on Craft 3.5.17.

## 2.6.4 - 2020-11-28

### Changed
- Sub-fields of Super Table fields now have “Use this field’s values as search keywords” unchecked for new fields. This is inline with Craft's native Matrix behaviour.

### Fixed
- Fix block type model `getHandle()` returning values included a hyphen, causing issues with GraphQL.
- Fix typehint for `getRelatedElementsQuery()`.

## 2.6.3 - 2020-09-28

### Fixed
- Fix InvalidArgumentException not found error. (thanks @smcyr).
- Fix static matrix layout visual issue.

## 2.6.2 - 2020-08-21

### Fixed
- Fix duplicate instructions for blocks in Craft 3.5+.
- Fix checkbox styling in Craft 3.5+.

## 2.6.1 - 2020-08-13

### Fixed
- Fix content table checking to cater for potentially problematic fields.

## 2.6.0.4 - 2020-08-11

### Fixed
- Fix layout issue with row layout fields, where any overflow was hidden.

## 2.6.0.3 - 2020-08-10

### Fixed
- Fix error when rebuilding project config.

## 2.6.0.2 - 2020-08-10

### Fixed
- Fix potential error during migration from Craft 2.

## 2.6.0.1 - 2020-08-10

### Fixed
- Fix GQL error.
- Fix errors with block query.

## 2.6.0 - 2020-08-10

### Added
- Add Craft 3.5+ compatibility.
- Now requires Craft 3.5+.

## 2.5.4 - 2020-07-30

### Fixed
- Fix an error when translating the description if the propagation method is set to PROPAGATION_METHOD_LANGUAGE. (thanks @andersaloof).
- Fix JS error when adding new blocks.

## 2.5.3 - 2020-07-28

### Fixed
- Refactor nested Matrix handling. Removed bespoke Matrix code, thanks to core Craft changes. Addresses a few upcoming changes in Craft 3.5+ (but backward-compatible).
- Now requires Craft 3.4.30+.

## 2.5.2 - 2020-07-28

### Fixed
- Fix Super Table field inside Matrix blocks not getting their content propagated. (thanks @brandonkelly).
- Fix error when removing a row for Table layout.
- Fix blocks not updating when nested in Matrix fields.
- Fix row layout overflow in some field cases.
- Improve handling of re-save field utility to handle parent Matrix fields.

## 2.5.1 - 2020-05-31

### Changed
- Requires Craft 3.4.23+.

### Fixed
- Fixed an error that could occur when rendering field type settings, if the field’s `getSettingsHtml()` method was expecting to be called from a Twig template.
- Fixed a race condition that could result in lost content under very specific conditions. ([#6154](https://github.com/craftcms/cms/issues/6154))

## 2.5.0 - 2020-05-30

### Changed
- Requires Craft 3.4.22+.
- Field settings are now lazy-loaded when the Field Type selection changes, improving the up-front load time of Edit Field pages.

## 2.4.9 - 2020-04-16

### Fixed
- Fix logging error `Call to undefined method setFileLogging()`.

## 2.4.8 - 2020-04-15

### Changed
- File logging now checks if the overall Craft app uses file logging.
- Log files now only include `GET` and `POST` additional variables.

### Fixed
- Fix Table field (and some other fields) not working correctly for Super Table field settings.

## 2.4.7 - 2020-04-02

### Changed
- Refactor row layout with CSS Grid (no style change).

### Fixed
- Additional checks for Webhooks compatibility. (thanks @johndwells).
- Fix nested Matrix not focusing on Redactor fields.
- Fix migration check for missing fieldLayoutId on blocktypes.
- Fix content table fixer where the content database table doesn’t exist at all.
- Fix lack of line-breaking for table layout column headings.
- Fix row layout overflowing the page content in some cases.

## 2.4.6 - 2020-02-28

### Fixed
- Fix PHP error.
- Fix number field alignment.

## 2.4.5 - 2020-02-24

### Changed
- Move `getRelatedElements()` to Service. (thanks @joshua-martin).
- Now requires Craft ^3.4.8.

### Fixed
- Fixed a bug where querying for blocks on a newly-created element’s Super Table field value would yield no results..

### Deprecated
- Deprecated `verbb\supertable\queue\jobs\ApplySuperTablePropagationMethod`.

## 2.4.4 - 2020-02-03

### Fixed
- Fix being unable to save Matrix-SuperTable-Matrix fields.

## 2.4.3 - 2020-02-02

### Fixed
- Fix issue with static blocks not appearing correctly.

## 2.4.2 - 2020-01-31

### Fixed
- Fix more Craft 3.4 issues.
- Fixed a bug where fields weren’t always showing validation errors.
- Fixed a bug where unsaved blocks could be lost if an entry was saved with validation errors, and any unsaved blocks weren’t modified before reattempting to save the entry.
- Fixed a bug where it wasn’t possible to eager-load blocks on a draft.

## 2.4.1 - 2020-01-30

### Fixed
- Fix Craft 3.4 issues.

## 2.4.0 - 2020-01-29

### Added
- Craft 3.4 compatibility.

## 2.3.3 - 2020-01-19

### Fixed
- Fix webhooks incompatibility.
- Remove `SuperTableBlockNotFoundException` class.

## 2.3.2 - 2020-01-09

### Added
- Added `verbb\supertable\queue\jobs\ApplySuperTablePropagationMethod`.
- Added `verbb\supertable\services\SuperTableService::getSupportedSiteIds()`.

### Changed
- When a Super Table field’s Propagation Method setting changes, the field’s blocks are now duplicated into any sites where their content would have otherwise been deleted.

### Deprecated
- Deprecated `verbb\supertable\services\SuperTableService::getSupportedSiteIdsForField()`. `getSupportedSiteIds()` should be used instead.

### Fixed
- Fixed an error that could occur when syncing the project config, that could occur if a Super Table block had been changed to something else.
- Fixed a bug where importing project config changes would break if they contained a changed global set and orphaned Super Table block types.
- Fixed an error that could occur when saving a Super Table field.

## 2.3.1 - 2019-11-27

### Fixed
- Fix matrix layout in some instances.
- Ensure search keywords is checked by default.
- Fix being unable to query ST fields directly.
- Fix up GraphQL.
- Fix width settings not saving correctly for project config.
- Fix template mode for checker/fixers, producing 404 errors on direct-access.

## 2.3.0 - 2019-09-11

### Added
- Add GraphQL support.
- Add data loss warnings for Propagation Method settings.

### Changed
- Now requires Craft ^3.3.1.2.

### Fixed
- Fixed bug where disabled Matrix blocks would be missing from Super Table inputs, then deleted. ([#288](https://github.com/verbb/super-table/issues/288))
- Add back site FK checks in migration.
- Fix where it wasn’t possible to delete blocks if Min and Max rows were set to the same value, and an element already had more than that many blocks.
- Block queries no longer include blocks owned by drafts or revisions by default.
- Fix blocks not getting duplicated to newly-enabled sites for elements if the field’s Propagation Method setting wasn’t set to “Save blocks to all sites the owner element is saved in”.
- Fix where default field values weren’t being applied to blocks that were autocreated per the Min Row setting.
- Fix not allowing block fields to be saved when set to “Translate for each site”.

## 2.2.1 - 2019-07-14

### Fixed
- Fix layout for static matrix field.
- Fix incorrect `SuperTableBlock` references causing elements not to save correctly.

## 2.2.0 - 2019-07-14

### Added
- Add support for `craft\base\BlockElementInterface`.
- Add support for setting the content of a Super Table field to be searchable.
- Add `verbb\supertable\services\SuperTableService::getSupportedSiteIdsForField()`.

### Changed
- Super Table now requires Craft 3.2+.
- Super Table fields now have a “Propagation Method” setting, enabling blocks to only be propagated to other sites in the same site group, or with the same language.
- `verbb\supertable\services\SuperTableService::saveField()` now has a `$checkOtherSites` argument.
- Improve block duplication.
- Improve element saving performance.

### Fixed
- Fix search index updating when upgrading to Craft 3.2+.

### Deprecated
- Deprecated the `ownerSite` and `ownerSiteId` block query params.
- Deprecated `verbb\supertable\elements\SuperTableBlockElement::$ownerSiteId`.

## 2.1.21 - 2019-07-14

### Fixed 
- Fix C2 > C3 migration for foreign keys not setup correctly for sites/locales.
- Fix error thrown when no `type` property in field project config.

## 2.1.20 - 2019-05-21

### Fixed
- Fix content table checker/fix incorrectly finding issues with Matrix nested Super Table fields in project config.
- Reset the ST field value after saving new owner. (thanks @brandonkelly).
- Don't call limit() or anyStatus() when displaying Matrix sub-fields. (thanks @brandonkelly).

## 2.1.19 - 2019-05-11

### Fixed
- Fix project config rebuild event creating incorrect config.
- Improve content table checker to show field id for manual fixing.

## 2.1.18 - 2019-04-24

### Fixed
- Fix nested Matrix fields in Matrix layout not being properly instantiated. 

## 2.1.17 - 2019-04-08

### Added
- Added support for the Project Config `rebuild` functionality.

### Changed
- Now requires Craft 3.1.20+.

### Fixed
- Fixed a bug where entry drafts weren’t showing previous changes to Super Table fields on the draft.

## 2.1.16 - 2019-03-09

### Fixed
- Fix layout issue in 3.1.16.
- Fix fields nested in Matrix fields still being on fields for project config.

## 2.1.15 - 2019-03-01

### Fixed
- Fix project config changes when not allowed or already applied

## 2.1.14 - 2019-03-01

### Added
- Add re-save function for Super Table fields.

## 2.1.13.4 - 2019-03-01

### Fixed
- More migration fixes.

## 2.1.13.3 - 2019-02-27

### Fixed
- Fix potential migration issue.

## 2.1.13.2 - 2019-02-27

### Fixed
- Fix/improve project config migration.

## 2.1.13.1 - 2019-02-27

### Fixed
- Fix potential migration issue from 2.1.13 where some fields have no settings.

## 2.1.13 - 2019-02-27

### Fixed
- Added project config migration to help seed project config for Super Tables when migrating from Craft 3 and lower.

## 2.1.12 - 2019-02-25

### Fixed
- Fix content tables updating (and potentially removing data) when fields are missing in Craft.
- Added `maxPowerCaptain()` to actionFixContentTables, allow fixing table contents to take a while.

## 2.1.11 - 2019-02-22

### Fixed
- Add more checks when fixing missing field columns during migration.
- Fix project inconsistency migration check.

## 2.1.10 - 2019-02-22

### Fixed
- Add fix/checks for project config mismatches from Craft 3 > 3.1.

## 2.1.9 - 2019-02-21

### Added
- Use the new `ProjectConfig::defer()` from Craft to help with Matrix combinations and Project Config. (thanks @brandonkelly).
- Add checks/fixes for content tables with incorrect field columns.
- Add checker/fixer to plugin settings.

### Changed
- Super Table now requires Craft 3.1.13 or later.

### Fixed
- Fix issues with Project Config and Matrix.

## 2.1.8 - 2019-02-18

### Fixed
- Provide a fix for Matrix + Super Table project config issues.
- Fix overflow for inner fields.

## 2.1.7 - 2019-02-10

### Fixed
- Fix migration issue with Matrix-nested content tables when upgrading from Craft 2 > 3.
- Min rows description typo. (thanks @alexjcollins).
- Fix sprout import incompatibility.

## 2.1.6 - 2019-01-31

### Added
- Added two events to let other js know when nested Matrix blocks are added. (thanks @joshangell).

### Fixed
- Improve checks around missing content tables during migration.
- Fix migration causing missing fields to be saved as a missing field.

## 2.1.5.3 - 2019-01-22

### Fixed
- Fix existing table-checking in content table check

## 2.1.5.2 - 2019-01-22

### Fixed
- Fix table cleanup in migration when correct table already exists

## 2.1.5.1 - 2019-01-22

### Fixed
- Update migration checks when updating from older version of Super Table.
- Check content tables clarity when all done/all okay.

## 2.1.5 - 2019-01-22

### Fixed
- Fix migration having to run twice to complete required steps in some cases.
- Added controller action `actions/super-table/plugin/fix-content-tables` to aid in debugging content table issues.
- Added controller action `actions/super-table/plugin/check-content-tables` to aid in debugging content table issues.
- Add a bunch of debugging to assist with content table migrations.

## 2.1.4.4 - 2019-01-22

### Fixed
- Fix more migration issues...

## 2.1.4.3 - 2019-01-22

### Fixed
- Remove old content table migration potentially causing issues.

## 2.1.4.2 - 2019-01-21

### Fixed
- Fix content table migration to cater for Neo.
- Fix inclusion of `_cleanUpTable()` in migration (not needed).

## 2.1.4.1 - 2019-01-20

### Fixed
- Fix migration issue in 2.1.4.

## 2.1.4 - 2019-01-20

### Fixed
- Fix any disconnected content tables for any Super Table field.

## 2.1.3 - 2019-01-19

### Fixed
- Prevent content field exception during migrations.

## 2.1.2 - 2019-01-19

### Fixed
- Ditch (incorrect) project config migration.

## 2.1.1 - 2019-01-19

### Added
- Added support for Craft 3.1 soft deletes.
- Added support for Craft 3.1 project config.

### Changed
- Clarify width field setting label for new fields.
- Tweak/improve minor field setting translations.

### Fixed
- Fixed a bug where a Super Table fields’ block types and content table could be deleted even if something set `$isValid` to `false` on the `beforeDelete` event.
- Fixed issue with Matrix + Super Table field combinations losing their fields, or content tables (thanks @brandonkelly).
- Fixed dragging issues with nested Matrix field when using Matrix Layout.

## 2.1.0 - 2019-01-16

### Fixed
- Fix for ST + Matrix field combination throwing errors during migration for Craft 3.1.x.
- Fixed an error that could occur when duplicating an element with a Super Table field with “Manage blocks on a per-site basis” disabled.
- Fixed an error that occurred when querying for Super Table blocks if both the `with` and `indexBy` parameters were set.
- Fixed a bug where Super Table blocks wouldn’t retain their content translations when an entry was duplicated from the Edit Entry page.
- Fix settings dropdown/table fields not working on some cases.
- Remove plugin settings page (It's not supposed to be there).

## 2.0.14 - 2018-11-12

### Fixed
- Fix CP section turning up.

## 2.0.13 - 2018-11-11

### Fixed
- Fix lack of styles when editing an entry version.

## 2.0.12 - 2018-11-10

### Changed
- Update styles to be inline with Craft 3.
- Use `duplicateElement()` to clone Super Table blocks after making them localized.

### Fixed
- Fix error when viewing previous versions of elements that contained a Super Table field.

## 2.0.11 - 2018-10-24

### Fixed
- Fixed Dashboard error (thanks @brandonkelly).
- Fix error when throwing an error for field handles (ironic hey?).
- Drop indexes before renaming instead of after. Otherwise this causes errors on mariadb. (thanks @born05).

## 2.0.10 - 2018-09-26

### Fixed
- Allow block element methods being called on static super tables.
- Ensure setups with min/max limits can still reorder items.
- Fix JS error when editing a Super Table field, but only when validation has failed..

## 2.0.9 - 2018-09-05

### Changed
- Updated min Craft version to 3.0.17.

### Fixed
- Fixed a SQL error that occurred when saving a Super Table field with new sub-fields on PostgreSQL (thanks @brandonkelly).
- Fixed Twig error in Craft 3.0.23 (thanks @brandonkelly).
- Fixed a typo: 'colapse' > 'collapse' (thanks @joshangell).

## 2.0.8 - 2018-08-18

### Added
- Add translation icon to fields that are set to be translatable.
- Restore column with functionality for table layouts.
- Allow fields to be translated for each site group.
- Added support for optional `ownerSite` parameter in `$params` for `getRelatedElements()` to allow for querying relations stored in Super Table fields in entries in a different site to the current site. (thanks @steverowling).
- Sprout import support (thanks @timkelty).

### Changed
- Refactor static table querying into actual query class. Fixed lots of cases related to static layouts.
- Namespace alternate JS inner-matrix functions, just in case.
- Improve validation on owner elements.
- Remove default limits of queries.
- Ensure field options are sorted by name.
- Make use of `anyStatus()` query function.
- Allow Field objects to be passed into SuperTableField::setBlockTypes (thanks @pinfirestudios)

### Fixed
- Fixed nested Super Table (in Matrix) fields Support for [Schematic](https://github.com/nerds-and-company/schematic)
- Fixed an error when saving an entry from the front-end with a static Super Table field was attached to an entry.
- Fixed dropdowns, etc not having their default values set on-load when setting a minimum row.
- Fix Matrix layout fields not saving correctly when set to static
- Fix when removing a row in an inner table field would collapse the entire Super Table field if set to Table layout and static.
- Fix modal form overrides on inner-Matrix field, causing all sorts of errors
- Fix Matrix > SuperTable > Matrix validation not firing correctly.
- Don't override siteId when deleting a Super Table block.
- Fix issue when viewing an entry revision where a field may have been deleted.
- Fix Eager Loading (thanks @mostlyserious).
- Fixes for schematic integration with a supertable nested in a matrix field (thanks @bvangennep).


## 2.0.7 - 2018-05-08

### Added
- Added Support for [Schematic](https://github.com/nerds-and-company/schematic)

### Fixed
- Fix nested Super Table (in Matrix) fields needing each field handle to be unique

## 2.0.6 - 2018-04-25

### Added
- Added Matrix Layout option (thanks [@Rias500](https://github.com/Rias500))
- Added back support for `getRelatedElements()`

### Changed
- Now sets `$propagating` to `true` when saving blocks, if the owner element is propagating.

### Fixed
- Fixed a bug where relational fields within Super Table fields wouldn’t save relations to elements that didn’t exist on all of the sites the owner element existed on. ([#2683](https://github.com/craftcms/cms/issues/2683))
- Fix query issue when requesting from console (for static fields)
- Fixed validation-handling when used in a Matrix field. Would allow saving invalid field handles in this context.

## 2.0.5 - 2018-04-05

### Fixed
- Updates to be inline with Craft 3.0.0 GA, now minimum requirement
- Improve field validation
- Fix post location path for inner fields
- Fix some minor layout issues with Matrix combinations
- Fix row layout (and a few other things) not using `site` as the translation key
- Fix table fields rows now being able to be deleted
- Fix Row layout not respecting minRows value
- Field instructions should parse markdown
- Fix namespacing for nested Matrix fields
- Fix errors caused by SuperTableBlockElement.eagerLoadingMap()

## 2.0.4 - 2018-02-21

### Added
- Minimum requirement for Super Table is now `^3.0.0-RC10`

### Changed
- Make use of `FieldLayoutBehavior::getFields()` and `setFields()`
- No longer executes two queries per block type when preparing a Super Table block query.

### Fixed
- Fixed an issue caused when upgrading a multi-locale site from Craft 2 to Craft 3.
- Fixed typo in Super Table's field context column
- Fixed blocks not appearing during Live Preview

## 2.0.3 - 2018-02-12

### Fixed
- Ensure field names are required
- Fix missing field validation
- Fix plugin icon in some circumstances

## 2.0.2 - 2018-02-11

### Added
- Added migration to handle converting existing field and element types to new namespaced format
- Add some field properties for backward-compatibility with Craft 2 upgrades

### Fixed
- Fix missing `sortOrder` column in install migration

## 2.0.1 - 2018-02-11

### Fixed
- Fix migration to not rely on service class. Fixes #122
- Fix for leftover `sortOrder` for Block Types. Fixes #123

## 2.0.0 - 2018-02-10

### Added
- Craft 3 initial release.

## 1.0.6 - 2017-10-17

### Added
- Verbb marketing (new plugin icon, readme, etc).

### Changed
- Improved [Feed Me](http://sgroup.com.au/plugins/feedme) inside Matrix support.

## 1.0.5 - 2017-06-22

### Changed
- [Feed Me](http://sgroup.com.au/plugins/feedme) 2.0.6 support.
- Minor migration fix for pre 0.4.0.

## 1.0.4 - 2017-03-25

### Added
- Added Minimum Rows field setting.

### Changed
- `type` is now a reserved field handle.
- Blocks are now deleted when an owner is deleted (think deleting an entry also deletes blocks for that entry). Otherwise, end up with orphaned blocks.

## 1.0.3 - 2017-02-27

### Added
- [Feed Me](http://sgroup.com.au/plugins/feedme) 2.0 support.

### Changed
- Support for Craft 2.6.2951.

### Fixed
- Fix Row Layout and limit rows issue. Wait for removal contract function to fire before updating the `canAddMoreRows()` function. Otherwise, button stays disabled when blocks can still be added.
- Fix for selected fieldtype JS not firing on select in settings.
- Fix lack of translation for field titles and instructions.

## 1.0.2 - 2016-11-02

### Fixed
- Minor layout fixes for Matrix-ST-Matrix, in particular when a lot of blocks are present.

## 1.0.1 - 2016-08-29

### Fixed
- Minor layout fix for Matrix-ST-Matrix in Safari.

## 1.0.0 - 2016-08-29

### Changed
- Brand new (yet awfully familiar!) settings layout for Super Table. Provides much more flexibility, future growth and performance improvements.
- No more modal for editing field settings - now inline and Matrix-style.
- Added instructions field to Super Table. Appears as small `info` button on table headers.
- Allow fields to be translatable.
- Revert Row Layout `table-layout: fixed` behaviour. Row Layout labels will now fit to the longest label without word-breaking.
- Improved Linkit styling inside Super Table.
- Cleanup and organise css.
- Added sliding animation when adding/deleting blocks (thanks [@benjamminf](https://github.com/benjamminf)!)

### Fixed
- Fixed performance issues with modal field settings (the modal no longer exists).
- Fixed field settings validation not firing and providing feedback.
- Fixed field validation when inside Matrix.
- Fixed field validation when outside Matrix.
- Fixed some issues with an inner Matrix field.
- Update `prepForFeedMeFieldType` to handle static field option
- Field names are now compulsary.

## 0.4.8 - 2016-07-15

### Fixed
- Fixed minor issue where width column settings weren't being saved correctly.

## 0.4.7 - 2016-07-09

### Added
- Added support for eager loading.
- Added `blocks()` template tag for Super Table block elements.

### Changed
- Column layout width now allows either px or % values. Defaults to % if unit-less.

### Fixed
- Fix for certain fields not settings content properly from draft.
- Fixed column layout not applying width correctly.

## 0.4.6 - 2016-03-16

### Fixed
- Adding a new locale when using with a Craft Commerce product type results in a stuck background task [#58](https://github.com/engram-design/SuperTable/issues/58).
- Fix Table layout overflow issues with Matrix-in-SuperTable [#59](https://github.com/engram-design/SuperTable/issues/59)

### Changed
- Improvements to Feed Me support.

## 0.4.5 - 2016-02-29

### Fixed
- Correct plugin version number (oops!).

## 0.4.4 - 2016-02-28

### Fixed
- Fixed issue with rows not displaying correctly for Static Field option.

## 0.4.3 - 2016-02-28

### Fixed
- Fixed issue with JS not firing correctly in field settings - caused some field types to not allow editing of settings.
- Fixed issue where blocks were being overwritten when entries are saved as drafts.

### Changed
- You now have direct access to fields when using the Static Field option. No more looping or using `superTableField[0].fieldHandle`.

## 0.4.2 - 2016-01-13

### Fixed
- Fixed issue with plugin release feed url.
- Fixes for PHP 5.3 compatibility.

## 0.4.1 - 2015-12-01

### Fixed
- Some files not correctly updated (for some strange reason).

## 0.4.0 - 2015-12-01

### Added
- Craft 2.5 support, including release feed and icons.
- Support for [Feed Me](https://github.com/engram-design/FeedMe).
- Support for [Export](https://github.com/boboldehampsink/Export).

### Fixed
- Labels in Row Layout are now top-aligned.

## 0.3.8 - 2015-11-30

- Ensure Craft 2.3.2615 is minimum required version.

## 0.3.7 - 2015-11-30

- Added relations support, thanks to [joshangell](https://github.com/joshangell).

## 0.3.6 - 2015-11-30

- Fix for validation when inside a Matrix field.

## 0.3.5 - 2015-11-30

- Change to content table naming when inside Matrix field. When two Super Table fields in different Matrix fields had the same handle, when one ST field was deleted, content for both would be deleted. Now prefixes tables with Matrix field id - ie: `supertablecontent_matrixId_fieldhandle`. See [notes](https://github.com/engram-design/SuperTable/blob/master/README.md#updating-from-034-to-035).
- Fix for some UI elements not initializing for Matrix > Super Table > Matrix layout [#28](https://github.com/engram-design/SuperTable/issues/28).

## 0.3.4 - 2015-11-30

- Minor visual fix for Row layout and table overflow [#24](https://github.com/engram-design/SuperTable/issues/24).
- Fix for Table layout and deleting element select items [#25](https://github.com/engram-design/SuperTable/issues/25).

## 0.3.3 - 2015-11-30

- Minor fixes to issues introduced accidentally in 0.3.2.

## 0.3.2 - 2015-11-30

- Fix for Matrix-SuperTable-Matrix field configuration and correct namespacing. Caused numerous inner-Matrix issues.

## 0.3.1 - 2015-11-30

- Fix for field labels on inner-Matrix field being hidden [#16](https://github.com/engram-design/SuperTable/issues/16).
- Latest Redactor version fixes [#3](https://github.com/engram-design/SuperTable/issues/3).
- Fix for width not being applied for columns [#15](https://github.com/engram-design/SuperTable/issues/15).
- Fix issues with multiple Matrix Configurators causing many javascript issues [see Craft 2.4.2677](https://buildwithcraft.com/updates#build2677).

## 0.3.0 - 2015-11-30

- Added composer support.
- Improved performance (thanks to [@boboldehampsink](https://github.com/boboldehampsink) and [@takobell](https://github.com/takobell))

## 0.2.9 - 2015-11-30

- Added Static option for fields. Allows for blocks to be non-repeatable [see more](https://github.com/engram-design/SuperTable#staticoption).
- Fixed validation when creating Super Table fields.
- Added option to set the 'Add a row' button text.
- Added Max Rows option.
- Added required option for fields.

## 0.2.8 - 2015-11-30

- Minor fix for Row Layout and Element Selection fields. Clicking delete on an element select would remove the entire row.

## 0.2.7 - 2015-11-30

- Full support for Matrix.

## 0.2.6 - 2015-11-30

- Added Row layout option [see example](https://github.com/engram-design/SuperTable#layout)
- Removed background colouring for cells.

## 0.2.5 - 2015-11-30

- Added width option to each column.
- Added Label field type.
- Fixed some minor validation bugs.
- Fixed namespacing issues for element-selects and some other fields inside Matrix blocks.

## 0.2 - 2015-11-30

- Major refactor of field settings JS. Caused namespacing issues for certain field types.

## 0.1 - 2015-11-30

- Initial release.
