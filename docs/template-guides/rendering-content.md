# Rendering Content
The below shows some typical Twig templating examples for field setups.

## Super Table field

```twig
{% for row in entry.superTablePlainText.all() %}
    {{ row.plainText }}
{% endfor %}
```

## Static Super Table field

```twig
{{ entry.superTableRichText.richText }}
```

## Matrix in Super Table field

```twig
{% for row in entry.superTableMatrix.all() %}
    {% for block in row.matrix.all() %}
        {% if block.type == 'block1' %}
            {{ block.plainText }}
        {% endif %}
    {% endfor %}
{% endfor %}
```
