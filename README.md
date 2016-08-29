# Super Table

Super Table is a Craft CMS field type to allow you to create powerful tables. You can utilise all your favourite native Craft field types in your tables, including Assets, Users, Entries and even Matrix. Also supports many third-party field types.

<img src="https://raw.githubusercontent.com/engram-design/SuperTable/master/screenshots/input.png" />


### Field Settings

<img src="https://raw.githubusercontent.com/engram-design/SuperTable/master/screenshots/settings.png" />

Creating a Super Table field is very similar to a Matrix field. First, select your desired Field Layout, then use the Configuration to define your fields.


### Supported FieldTypes

**Craft**

* Assets
* Categories
* Checkboxes
* Color
* Date/Time
* Dropdown
* Entries
* Lightswitch
* Matrix
* Multi-select
* Number
* Plain Text
* Position Select
* Radio Buttons
* Rich Text
* Table
* Tags
* Users

**Third-Party**

* [ButtonBox](https://github.com/supercool/Button-Box)
* [Linkit](https://github.com/fruitstudios/LinkIt)

...and many more. Super Table can handle just about any FieldType, the above are simply those that have been tested.


## Layout

For any Super Table, you can choose between two layout options - Row and Table. This is an option when creating your Super Table field. The Table layout will present fields vertically and in a tabular format - exactly as you'd expect from a Table field. Row on the other hand will present fields horizontally, similar to how a Matrix field works.

Which layout you choose will likely depend on what sort of fields you have in your Super Table, and the number of fields. For a Super Table containing 4 or more fields, your best option is to use the Row Layout.

To illustrate the different layout options, refer to the below, which are the same field using both Table and Row layouts.

<img src="https://raw.githubusercontent.com/engram-design/SuperTable/master/screenshots/layouts.png" />

The Row Layout also shines brightest when using inside a Matrix field as below.

<img src="https://raw.githubusercontent.com/engram-design/SuperTable/master/screenshots/rowLayout.png" />


## Static option

A Super Table field can be set to be static, which turns the field into a non-repeatable collection of fields. This can be useful for a multitude of cases where you wish to simply group a collection of fields together, and not necessarily have them repeatable.

<img src="https://raw.githubusercontent.com/engram-design/SuperTable/master/screenshots/static.png" />


## Roadmap

- Add ability to collapse rows.
- Allow for SuperTable-in-SuperTable - because why not.
- Support column duplication in SuperTable field settings.


## Release Notes

Below are major release notes when updating from one version to another. Breaking changes will be listed here.

- [Updating from 0.4.2 to 0.4.3](https://github.com/engram-design/SuperTable/wiki/Release-Notes#updating-from-042-to-043)
- [Updating from 0.3.4 to 0.3.5](https://github.com/engram-design/SuperTable/wiki/Release-Notes#updating-from-034-to-035)


## Documentation

As a rule of thumb, a Super Table field acts almost identically to a Matrix field, so templating and custom development should be similar to a Matrix field. Below are a few resources for developers.

- [Templating examples](https://github.com/engram-design/SuperTable/wiki/Templating-examples)
- [Updating a Super Table field from a front end form](https://github.com/engram-design/SuperTable/wiki/Updating-a-Super-Table-field-from-a-front-end-form)
- [Fetching content from a Super Table field via plugin](https://github.com/engram-design/SuperTable/wiki/Fetching-content-from-a-Super-Table-field)
- [Programatically saving a Super Table field with content via plugin](https://github.com/engram-design/SuperTable/wiki/Programatically-saving-a-Super-Table-field-with-content)


## Troubleshooting

**Errors or trouble saving Matrix / Super Table combination**

If you're using a Matrix / Super Table combination, you'll likely need to alter the `max_input_vars` and `post_max_size` setting in your `php.ini` file. Whether this is a necessary change depends on your server setup, but its advised that you make this change regardless to ensure data isn't lost. This will ensure your fields save correctly, and data is not lost. You may experience a 500 error on save, or a semi-blank screen when saving your content. This can also be a common problem with Matrix and other fields - see [http://craftcms.stackexchange.com/a/2777](http://craftcms.stackexchange.com/a/2777).


## Thanks / Contributions

Thanks go to [@brandonkelly](https://github.com/brandonkelly) and [@benparizek](https://github.com/benparizek) for their input, ideas and suggestions.


## Requirements

- Craft 2.3.2615+.
- PHP 5.4+


## Changelog

[View JSON Changelog](https://github.com/engram-design/SuperTable/blob/master/changelog.json)
