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
* Multi-select
* Number
* Plain Text
* Position Select
* Radio Buttons
* Rich Text
* Table
* Tags
* Users

**ButtonBox by [Supercool](https://github.com/supercool/Button-Box)**

* Buttons
* Colours
* Text Size
* Stars
* Width

**Planned support**

* Matrix

...and many more. Super Table can handle just about any FieldType, the above are simply those that have been tested.


## Early Matrix Support

There is early Matrix support for Super Table, in that a Super Table can be used inside your Matrix blocks as below. Currently, you cannot add a Matrix field as a field to your Super Table. Instead, you'll need to put your Super Table field inside Matrix blocks.

<img src="https://raw.githubusercontent.com/engram-design/SuperTable/master/screenshots/matrix.png" />


## Roadmap

- Fix Matrix rendering issue.
- Add column width support.
- Allow layout option to display columns vertically (Table) or horizontally (Matrix). 
- Integrate options for static, non-repeatable table - ie [Set Table](https://github.com/engram-design/SetTable).
- Add Label Fieldtype.
- Test more third-party fieldtypes, purely for a complete list.


## Thanks / Contributions

Thanks go to [@brandonkelly](https://github.com/brandonkelly) and [@benparizek](https://github.com/benparizek) for their input, ideas and suggestions.


## Changelog

#### 0.1

- Initial release.

