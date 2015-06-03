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


## Roadmap

- Better error-handling when saving field.
- Integrate options for static, non-repeatable table - ie [Set Table](https://github.com/engram-design/SetTable).
- Test more third-party fieldtypes, purely for a complete list.
- Add selection label field for new rows/columns
- Add limit field.
- Add required field.
- Add ability to collapse rows.


## Thanks / Contributions

Thanks go to [@brandonkelly](https://github.com/brandonkelly) and [@benparizek](https://github.com/benparizek) for their input, ideas and suggestions.


## Changelog

#### 0.2.8

- Minor fix for Row Layout and Element Selection fields. Clicking delete on an element select would remove the entire row.

#### 0.2.7

- Full support for Matrix.

#### 0.2.6

- Added Row layout option [see example](https://github.com/engram-design/SuperTable#layout)
- Removed background colouring for cells.

#### 0.2.5

- Added width option to each column.
- Added Label field type.
- Fixed some minor validation bugs.
- Fixed namespacing issues for element-selects and some other fields inside Matrix blocks. 

#### 0.2

- Major refactor of field settings JS. Caused namespacing issues for certain field types.

#### 0.1

- Initial release.

