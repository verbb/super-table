# Release Notes

## Updating from 0.4.2 to 0.4.3

The Static field option was changed to now allow direct access to your fields inside a Super Table field. This means you no longer need to loop through all rows in a Super Table field, as there will always only ever be one.

For example, where you might have done the following:

```twig
{% for row in entry.superTableField %}
    {% set field = row.field %}
{% endfor %}
```

You can now simply use:

```twig
{% set field = row.superTableField.field %}
```

Please note that this change is purely for when using the Static Field option.

## Updating from 0.3.4 to 0.3.5

The 0.3.5 update changed the way that Super Table's within Matrix fields store their content. Because two Super Tables with the same handle could be created in different Matrix fields, both these Super Table fields shared a single content table. This creates all sorts of issues when it comes to deleting your Super Table fields.

While the update will automatically rename and migrate all your Super Table content in a non-destructive fashion, you may come across a particular issue which causes the plugin update to fail. This revolves around one of the primary keys becomes too long for MySQL to handle.

If your receive the error when updating the plugin, please check out `craft/storage/runtime/logs/craft.log` for a line that looks similar to:

```
[error] [system.db.CDbCommand] CDbCommand::execute() failed: SQLSTATE[42000]: Syntax error or access violation: 1059 Identifier name 'craft_supertablecontent_NUMBER_HANDLE_elementId_locale_unq_idx' is too long.
```

If you find this error, you will need to manually rename the provided table before performing the plugin update. Simple rename the table from `craft_supertablecontent_HANDLE` to `craft_supertablecontent_NUMBER_HANDLE` with `NUMBER` and `HANDLE` obviously specific to your particular table. This will be identified in the error line in your `craft.log` file. Perform the plugin update once you have renamed these tables.