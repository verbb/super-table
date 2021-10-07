# Available Variables

The following are common methods you will want to call in your front end templates:

### `craft.superTable.blocks()`

See [Super Table Block Queries](docs:getting-elements/super-table-block-queries)

### `craft.superTable.getRelatedElements()`

Expands the default relationship behaviour to include Super Table fields so that the user can filter by those too.

```twig
{% set reverseRelatedElements = craft.superTable.getRelatedElements({
  relatedTo: {
      targetElement: entry,
      field: 'superTableFieldHandle.columnHandle',
  },
  ownerSite: 'siteHandle',
  elementType: 'craft\\elements\\Entry',
  criteria: {
      id: 'not 123',
      section: 'someSection',
  }
}).all() %}
```
