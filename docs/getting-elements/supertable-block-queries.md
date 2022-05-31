# Super Table Block Queries

You can fetch Super Table blocks in your templates or PHP code using **Super Table block queries**.

::: code
```twig Twig
{# Create a new Super Table block query #}
{% set mySuperTableBlockQuery = craft.superTable.blocks() %}
```
```php
// Create a new Super Table block query
$mySuperTableBlockQuery = \verbb\supertable\elements\SuperTableBlockElement::find();
```
:::

Once you’ve created a Super Table block query, you can set [parameters](#parameters) on it to narrow down the results, and then execute it by calling `.all()`. An array of Super Table Block objects will be returned.

::: tip
See [Introduction to Element Queries](https://craftcms.com/docs/3.x/element-queries.html) to learn about how element queries work.
:::

## Example

We can display content from all the Super Table blocks of an element by doing the following:

1. Create a Super Table block query with `craft.superTable.blocks()`.
2. Set the [owner](#owner), and [fieldId](#fieldid) parameters on it.
3. Fetch the Super Table blocks with `.all()`.
4. Loop through the Super Table blocks using a [for](https://twig.symfony.com/doc/2.x/tags/for.html) tag to output the contents.

```twig
{# Create a Super Table block query with the 'owner', and 'fieldId' parameters #}
{% set mySuperTableBlockQuery = craft.superTable.blocks()
    .owner(myEntry)
    .fieldId(10) %}

{# Fetch the Super Table blocks #}
{% set superTableBlocks = mySuperTableBlockQuery.all() %}

{# Display their contents #}
{% for block in blocks %}
    <p>{{ block.text }}</p>
{% endfor %}
```

::: warning
In order for the returned Super Table block(s) to be populated with their custom field content, you will need to either set the [fieldId](#fieldid) or [id](#id) parameter.
:::

## Parameters

Super Table block queries support the following parameters:

<!-- BEGIN PARAMS -->

### `asArray`

Causes the query to return matching Super Table blocks as arrays of data, rather than Super Table block objects.

::: code
```twig Twig
{# Fetch Super Table blocks as arrays #}
{% set mySuperTableBlocks = craft.superTable.blocks()
    .asArray()
    .all() %}
```

```php PHP
// Fetch Super Table blocks as arrays
$superTableBlocks = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->asArray()
    ->all();
```
:::



### `dateCreated`

Narrows the query results based on the Super Table blocks’ creation dates.

Possible values include:

| Value | Fetches Super Table blocks…
| - | -
| `'>= 2018-04-01'` | that were created on or after 2018-04-01.
| `'< 2018-05-01'` | that were created before 2018-05-01
| `['and', '>= 2018-04-04', '< 2018-05-01']` | that were created between 2018-04-01 and 2018-05-01.

::: code
```twig Twig
{# Fetch Super Table blocks created last month #}
{% set start = date('first day of last month')|atom %}
{% set end = date('first day of this month')|atom %}

{% set mySuperTableBlocks = craft.superTable.blocks()
    .dateCreated(['and', ">= #{start}", "< #{end}"])
    .all() %}
```

```php PHP
// Fetch Super Table blocks created last month
$start = new \DateTime('first day of next month')->format(\DateTime::ATOM);
$end = new \DateTime('first day of this month')->format(\DateTime::ATOM);

$superTableBlocks = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->dateCreated(['and', ">= {$start}", "< {$end}"])
    ->all();
```
:::



### `dateUpdated`

Narrows the query results based on the Super Table blocks’ last-updated dates.

Possible values include:

| Value | Fetches Super Table blocks…
| - | -
| `'>= 2018-04-01'` | that were updated on or after 2018-04-01.
| `'< 2018-05-01'` | that were updated before 2018-05-01
| `['and', '>= 2018-04-04', '< 2018-05-01']` | that were updated between 2018-04-01 and 2018-05-01.

::: code
```twig Twig
{# Fetch Super Table blocks updated in the last week #}
{% set lastWeek = date('1 week ago')|atom %}

{% set mySuperTableBlocks = craft.superTable.blocks()
    .dateUpdated(">= #{lastWeek}")
    .all() %}
```

```php PHP
// Fetch Super Table blocks updated in the last week
$lastWeek = new \DateTime('1 week ago')->format(\DateTime::ATOM);

$superTableBlocks = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->dateUpdated(">= {$lastWeek}")
    ->all();
```
:::



### `fieldId`

Narrows the query results based on the field the Super Table blocks belong to, per the fields’ IDs.

Possible values include:

| Value | Fetches Super Table blocks…
| - | -
| `1` | in a field with an ID of 1.
| `'not 1'` | not in a field with an ID of 1.
| `[1, 2]` | in a field with an ID of 1 or 2.
| `['not', 1, 2]` | not in a field with an ID of 1 or 2.

::: code
```twig Twig
{# Fetch Super Table blocks in the field with an ID of 1 #}
{% set mySuperTableBlocks = craft.superTable.blocks()
    .fieldId(1)
    .all() %}
```

```php PHP
// Fetch Super Table blocks in the field with an ID of 1
$superTableBlocks = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->fieldId(1)
    ->all();
```
:::



### `fixedOrder`

Causes the query results to be returned in the order specified by [id](#id).

::: code
```twig Twig
{# Fetch Super Table blocks in a specific order #}
{% set mySuperTableBlocks = craft.superTable.blocks()
    .id([1, 2, 3, 4, 5])
    .fixedOrder()
    .all() %}
```

```php PHP
// Fetch Super Table blocks in a specific order
$superTableBlocks = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->id([1, 2, 3, 4, 5])
    ->fixedOrder()
    ->all();
```
:::



### `id`

Narrows the query results based on the Super Table blocks’ IDs.

Possible values include:

| Value | Fetches Super Table blocks…
| - | -
| `1` | with an ID of 1.
| `'not 1'` | not with an ID of 1.
| `[1, 2]` | with an ID of 1 or 2.
| `['not', 1, 2]` | not with an ID of 1 or 2.

::: code
```twig Twig
{# Fetch the Super Table block by its ID #}
{% set superTableBlock = craft.superTable.blocks()
    .id(1)
    .one() %}
```

```php PHP
// Fetch the Super Table block by its ID
$superTableBlock = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->id(1)
    ->one();
```
:::

::: tip
This can be combined with [fixedOrder](#fixedorder) if you want the results to be returned in a specific order.
:::



### `inReverse`

Causes the query results to be returned in reverse order.

::: code
```twig Twig
{# Fetch Super Table blocks in reverse #}
{% set mySuperTableBlocks = craft.superTable.blocks()
    .inReverse()
    .all() %}
```

```php PHP
// Fetch Super Table blocks in reverse
$superTableBlocks = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->inReverse()
    ->all();
```
:::



### `limit`

Determines the number of Super Table blocks that should be returned.

::: code
```twig Twig
{# Fetch up to 10 Super Table blocks  #}
{% set mySuperTableBlocks = craft.superTable.blocks()
    .limit(10)
    .all() %}
```

```php PHP
// Fetch up to 10 Super Table blocks
$superTableBlocks = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->limit(10)
    ->all();
```
:::



### `offset`

Determines how many Super Table blocks should be skipped in the results.

::: code
```twig Twig
{# Fetch all Super Table blocks except for the first 3 #}
{% set mySuperTableBlocks = craft.superTable.blocks()
    .offset(3)
    .all() %}
```

```php PHP
// Fetch all Super Table blocks except for the first 3
$superTableBlocks = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->offset(3)
    ->all();
```
:::



### `orderBy`

Determines the order that the Super Table blocks should be returned in.

::: code
```twig Twig
{# Fetch all Super Table blocks in order of date created #}
{% set mySuperTableBlocks = craft.superTable.blocks()
    .orderBy('elements.dateCreated asc')
    .all() %}
```

```php PHP
// Fetch all Super Table blocks in order of date created
$superTableBlocks = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->orderBy('elements.dateCreated asc')
    ->all();
```
:::



### `owner`

Sets the [ownerId](#ownerid) and [siteId](#siteid) parameters based on a given element.

::: code
```twig Twig
{# Fetch Super Table blocks created for this entry #}
{% set mySuperTableBlocks = craft.superTable.blocks()
    .owner(myEntry)
    .all() %}
```

```php PHP
// Fetch Super Table blocks created for this entry
$superTableBlocks = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->owner($myEntry)
    ->all();
```
:::



### `ownerId`

Narrows the query results based on the owner element of the Super Table blocks, per the owners’ IDs.

Possible values include:

| Value | Fetches Super Table blocks…
| - | -
| `1` | created for an element with an ID of 1.
| `'not 1'` | not created for an element with an ID of 1.
| `[1, 2]` | created for an element with an ID of 1 or 2.
| `['not', 1, 2]` | not created for an element with an ID of 1 or 2.

::: code
```twig Twig
{# Fetch Super Table blocks created for an element with an ID of 1 #}
{% set mySuperTableBlocks = craft.superTable.blocks()
    .ownerId(1)
    .all() %}
```

```php PHP
// Fetch Super Table blocks created for an element with an ID of 1
$superTableBlocks = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->ownerId(1)
    ->all();
```
:::



### `search`

Narrows the query results to only Super Table blocks that match a search query.

See [Searching](https://docs.craftcms.com/v3/searching.html) for a full explanation of how to work with this parameter.

::: code
```twig Twig
{# Get the search query from the 'q' query string param #}
{% set searchQuery = craft.request.getQueryParam('q') %}

{# Fetch all Super Table blocks that match the search query #}
{% set mySuperTableBlocks = craft.superTable.blocks()
    .search(searchQuery)
    .all() %}
```

```php PHP
// Get the search query from the 'q' query string param
$searchQuery = \Craft::$app->request->getQueryParam('q');

// Fetch all Super Table blocks that match the search query
$superTableBlocks = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->search($searchQuery)
    ->all();
```
:::



### `site`

Determines which site the Super Table blocks should be queried in.

The current site will be used by default.

Possible values include:

| Value | Fetches Super Table blocks…
| - | -
| `'foo'` | from the site with a handle of `foo`.
| a [Site](https://docs.craftcms.com/api/v3/craft-models-site.html) object | from the site represented by the object.

::: code
```twig Twig
{# Fetch Super Table blocks from the Foo site #}
{% set mySuperTableBlocks = craft.superTable.blocks()
    .site('foo')
    .all() %}
```

```php PHP
// Fetch Super Table blocks from the Foo site
$superTableBlocks = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->site('foo')
    ->all();
```
:::



### `siteId`

Determines which site the Super Table blocks should be queried in, per the site’s ID.

The current site will be used by default.

::: code
```twig Twig
{# Fetch Super Table blocks from the site with an ID of 1 #}
{% set mySuperTableBlocks = craft.superTable.blocks()
    .siteId(1)
    .all() %}
```

```php PHP
// Fetch Super Table blocks from the site with an ID of 1
$superTableBlocks = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->siteId(1)
    ->all();
```
:::



### `uid`

Narrows the query results based on the Super Table blocks’ UIDs.

::: code
```twig Twig
{# Fetch the Super Table block by its UID #}
{% set superTableBlock = craft.superTable.blocks()
    .uid('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
    .one() %}
```

```php PHP
// Fetch the Super Table block by its UID
$superTableBlock = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->uid('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
    ->one();
```
:::



### `with`

Causes the query to return matching Super Table blocks eager-loaded with related elements.

See [Eager-Loading Elements](https://docs.craftcms.com/v3/dev/eager-loading-elements.html) for a full explanation of how to work with this parameter.

::: code
```twig Twig
{# Fetch Super Table blocks eager-loaded with the "Related" field’s relations #}
{% set mySuperTableBlocks = craft.superTable.blocks()
    .with(['related'])
    .all() %}
```

```php PHP
// Fetch Super Table blocks eager-loaded with the "Related" field’s relations
$superTableBlocks = \verbb\supertable\elements\SuperTableBlockElement::find()
    ->with(['related'])
    ->all();
```
:::


<!-- END PARAMS -->
