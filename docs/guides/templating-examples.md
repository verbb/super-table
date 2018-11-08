# Templating Examples

## Super Table field

**Field Settings** ![](/docs/screenshots/field_supertable.png)

**Template code**

```twig
{% for row in entry.superTablePlainText %}
    {{ row.plainText }}
{% endfor %}
```twig

## Static Super Table field

**Field Settings** ![](/docs/screenshots/field_supertable_static.png)

**Template code**

```twig
{{ entry.superTableRichText.richText }}
```twig

## Matrix in Super Table field

**Field Settings** ![](/docs/screenshots/field_supertable_matrix.png)

![](/docs/screenshots/field_supertable_matrix_settings.png)

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