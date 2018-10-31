# Templating Examples

## Super Table field

**Field Settings** ![](https://raw.githubusercontent.com/verbb/super-table/craft-2/screenshots/field_supertable.png)

**Template code**

```twig
{% for row in entry.superTablePlainText %}
    {{ row.plainText }}
{% endfor %}
```

## Static Super Table field

**Field Settings** ![](https://raw.githubusercontent.com/verbb/super-table/craft-2/screenshots/field_supertable_static.png)

**Template code**

```twig
{{ entry.superTableRichText.richText }}
```

## Matrix in Super Table field

**Field Settings** ![](https://raw.githubusercontent.com/verbb/super-table/craft-2/screenshots/field_supertable_matrix.png)

![](https://raw.githubusercontent.com/verbb/super-table/craft-2/screenshots/field_supertable_matrix_settings.png)

**Template code**

```twig
{% for row in entry.superTableMatrix %}
    {% for block in row.matrix %}
        {% if block.type == 'block1' %}
            {{ block.plainText }}
        {% endif %}
    {% endfor %}
{% endfor %}
```
