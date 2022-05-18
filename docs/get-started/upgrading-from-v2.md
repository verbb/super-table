# Upgrading from v2
While the [changelog](https://github.com/verbb/super-table/blob/craft-4/CHANGELOG.md) is the most comprehensive list of changes, this guide provides high-level overview and organizes changes by category.

## Renamed Classes
The following classes have been renamed.

Old | What to do instead
--- | ---
| `verbb\supertable\models\SuperTableBlockTypeModel` | `verbb\supertable\models\SuperTableBlockType`
| `verbb\supertable\record\SuperTableBlockRecord` | `verbb\supertable\record\SuperTableBlock`
| `verbb\supertable\record\SuperTableBlockTypeRecord` | `verbb\supertable\record\SuperTableBlockType`
| `verbb\supertable\services\SuperTableService` | `verbb\supertable\services\Service`

## Query Params
Some element query params have been removed:

Old Param | What to do instead
--- | ---
| `ownerLocale` | `site` or `siteId`
| `ownerSite` | `site`
| `ownerSiteId` | `siteId`
