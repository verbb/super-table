# Front-end Form

Updating a Super Table field from a front-end form is straightforward - it's very similar to a Matrix.

Refer to the following code - we have a Super Table field with handle `mySuperTableField` in our User profile, which we want to modify. This could apply to any endpoint in Craft, be it Entries, Global Set, etc.

First, we need to output any existing rows for this field, otherwise they will be overwritten. These can be hidden inputs if you don't want them to appear.

The variables below will help you figure out the correct block type to use (even though a Super Table field will only ever have one block type).

```twig
{% set fieldHandle = 'mySuperTableField' %}
{% set field = craft.app.fields.getFieldByHandle(fieldHandle) %}
{% set blocktype = craft.superTable.getSuperTableBlocks(field.id)[0] %}

<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    <input type="hidden" name="action" value="users/save-user">
    <input type="hidden" name="userId" value="{{ currentUser.id }}">

    {# Start with the top-level Super Table field #}
    <input type="hidden" name="fields[{{ fieldHandle }}]" value="">

    {# Ensure any existing rows in your Super Table field are saved #}
    {% for block in currentUser[fieldHandle] %}
        <input type="hidden" name="fields[{{ fieldHandle }}][{{ block.id }}][type]" value="{{ blocktype }}">
        <input type="hidden" name="fields[{{ fieldHandle }}][{{ block.id }}][enabled]" value="1">

        <input type="text" name="fields[{{ fieldHandle }}][{{ block.id }}][fields][firstName]" value="{{ block.firstName }}">
    {% endfor %}

    {# Add a new row of data - note the `new1` #}
    <input type="hidden" name="fields[{{ fieldHandle }}][new1][type]" value="{{ blocktype }}">
    <input type="hidden" name="fields[{{ fieldHandle }}][new1][enabled]" value="1">

    {# Repeat for all your fields in your Super Table field #}
    <input type="text" name="fields[{{ fieldHandle }}][new1][fields][firstName]" value="John Smith">

    {# Add another new row of data #}
    <input type="hidden" name="fields[{{ fieldHandle }}][new2][type]" value="{{ blocktype }}">
    <input type="hidden" name="fields[{{ fieldHandle }}][new2][enabled]" value="1">
    
    {# Repeat for all your fields in your Super Table field #}
    <input type="text" name="fields[{{ fieldHandle }}][new2][fields][firstName]" value="Jane Smith">

    <input type="submit">
</form>
```
