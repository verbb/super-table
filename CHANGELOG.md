#### 0.3.8

- Ensure Craft 2.3.2615 is minimum required version.

#### 0.3.7

- Added relations support, thanks to [joshangell](https://github.com/joshangell).

#### 0.3.6

- Fix for validation when inside a Matrix field.

#### 0.3.5

- Change to content table naming when inside Matrix field. When two Super Table fields in different Matrix fields had the same handle, when one ST field was deleted, content for both would be deleted. Now prefixes tables with Matrix field id - ie: `supertablecontent_matrixId_fieldhandle`. See [notes](https://github.com/engram-design/SuperTable/blob/master/README.md#updating-from-034-to-035).
- Fix for some UI elements not initializing for Matrix > Super Table > Matrix layout [#28](https://github.com/engram-design/SuperTable/issues/28).


#### 0.3.4

- Minor visual fix for Row layout and table overflow [#24](https://github.com/engram-design/SuperTable/issues/24).
- Fix for Table layout and deleting element select items [#25](https://github.com/engram-design/SuperTable/issues/25).

#### 0.3.3

- Minor fixes to issues introduced accidentally in 0.3.2.

#### 0.3.2

- Fix for Matrix-SuperTable-Matrix field configuration and correct namespacing. Caused numerous inner-Matrix issues.

#### 0.3.1

- Fix for field labels on inner-Matrix field being hidden [#16](https://github.com/engram-design/SuperTable/issues/16).
- Latest Redactor version fixes [#3](https://github.com/engram-design/SuperTable/issues/3).
- Fix for width not being applied for columns [#15](https://github.com/engram-design/SuperTable/issues/15).
- Fix issues with multiple Matrix Configurators causing many javascript issues [see Craft 2.4.2677](https://buildwithcraft.com/updates#build2677).

#### 0.3.0

- Added composer support.
- Improved performance (thanks to [@boboldehampsink](https://github.com/boboldehampsink) and [@takobell](https://github.com/takobell))

#### 0.2.9

- Added Static option for fields. Allows for blocks to be non-repeatable [see more](https://github.com/engram-design/SuperTable#staticoption).
- Fixed validation when creating Super Table fields.
- Added option to set the 'Add a row' button text.
- Added Max Rows option.
- Added required option for fields.

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

