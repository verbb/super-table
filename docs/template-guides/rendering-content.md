# Rendering Content

The below shows some typical Twig templating examples for field setups.

## Super Table field

**Field Settings** ![](/docs/screenshots/field_supertable.png)

**Template code**

```twig
{% for row in entry.superTablePlainText.all() %}
    {{ row.plainText }}
{% endfor %}
```

## Static Super Table field

**Field Settings** ![](/docs/screenshots/field_supertable_static.png)

**Template code**

```twig
{{ entry.superTableRichText.richText }}
```

## Matrix in Super Table field

**Field Settings** ![](/docs/screenshots/field_supertable_matrix.png)

![](/docs/screenshots/field_supertable_matrix_settings.png)

**Template code**

```twig
{% for row in entry.superTableMatrix.all() %}
    {% for block in row.matrix.all() %}
        {% if block.type == 'block1' %}
            {{ block.plainText }}
        {% endif %}
    {% endfor %}
{% endfor %}
```
