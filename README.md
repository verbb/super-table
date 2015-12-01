# Super Table

Super Table is a Craft CMS field type to allow you to create powerful tables. You can utilise all your favourite native Craft field types in your tables, including Assets, Users, Entries and even Matrix. Also supports many third-party field types.

<img src="https://raw.githubusercontent.com/engram-design/SuperTable/master/screenshots/input.png" />


### Field Settings

<img src="https://raw.githubusercontent.com/engram-design/SuperTable/master/screenshots/settings.png" />

Editing a Super Table is very similar to editing a Table. You define your columns, giving them a Name and Handle, and now have the option to select any installed field type.

To edit the settings of a particular field, click on the small 'cog' icon on the far right of the table row. This will open a modal window where you can edit any settings for that field type. Don't forgot to hit Save button to save these field settings!


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

- Test more third-party fieldtypes, purely for a complete list.
- Add ability to collapse rows.
- New settings layout, including drag/drop, full control over field layout. Allows you to set out the field exactly as you like.
- Add template hook to allow other plugins to provide layouts for editing field.
- Allow for SuperTable-in-SuperTable - because why not.
- Support column duplication in SuperTable field settings.


## Updating from 0.3.4 to 0.3.5
The 0.3.5 update changed the way that Super Table's within Matrix fields store their content. Because two Super Tables with the same handle could be created in different Matrix fields, both these Super Table fields shared a single content table. This creates all sorts of issues when it comes to deleting your Super Table fields.

While the update will automatically rename and migrate all your Super Table content in a non-destructive fashion, you may come across a particular issue which causes the plugin update to fail. This revolves around one of the primary keys becomes too long for MySQL to handle.

If your receive the error when updating the plugin, please check out `craft/storage/runtime/logs/craft.log` for a line that looks similar to:

```
[error] [system.db.CDbCommand] CDbCommand::execute() failed: SQLSTATE[42000]: Syntax error or access violation: 1059 Identifier name 'craft_supertablecontent_NUMBER_HANDLE_elementId_locale_unq_idx' is too long.
```

If you find this error, you will need to manually rename the provided table before performing the plugin update. Simple rename the table from `craft_supertablecontent_HANDLE` to `craft_supertablecontent_NUMBER_HANDLE` with `NUMBER` and `HANDLE` obviously specific to your particular table. This will be identified in the error line in your `craft.log` file. Perform the plugin update once you have renamed these tables.


## Thanks / Contributions

Thanks go to [@brandonkelly](https://github.com/brandonkelly) and [@benparizek](https://github.com/benparizek) for their input, ideas and suggestions.


## Requirements

Super Table requires a minimum of Craft 2.3.2615 in order to function.


## Changelog

[View JSON Changelog](https://github.com/engram-design/SuperTable/blob/master/changelog.json)
