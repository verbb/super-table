(function($) {

Craft.SuperTableConfigurator = Garnish.Base.extend(
{
	init: function(columnsTableName, columns, blockTypes, columnSettings, fieldTypeInfo, tableId)
	{
		var columnsTableId = Craft.formatInputId(columnsTableName);

		new Craft.EditableColumnTable(columnsTableId, columnsTableName, blockTypes, fieldTypeInfo, tableId, columnSettings, {
			rowIdPrefix: 'new',
			onAddRow: $.proxy(this, 'onAddColumn'),
		});
	},

});






Craft.EditableColumnTable = Garnish.Base.extend(
{
	id: null,
	baseName: null,
	columns: null,
	sorter: null,
	biggestId: -1,

	fieldTypeInfo: null,
	blockTypes: null,
	fieldName: null,
	tableId: null,

	$table: null,
	$tbody: null,
	$addRowBtn: null,

	init: function(id, baseName, blockTypes, fieldTypeInfo, tableId, columns, settings)
	{
		this.id = id;
		this.baseName = baseName;
		this.columns = columns;
		this.fieldTypeInfo = fieldTypeInfo;
		this.blockTypes = blockTypes;
		this.tableId = tableId;

		this.setSettings(settings, Craft.EditableColumnTable.defaults);

		this.$table = $('#' + tableId);
		this.$tbody = this.$table.children('tbody');

		this.sorter = new Craft.DataTableSorter(this.$table, {
			helperClass: 'editablecolumntablesorthelper',
			copyDraggeeInputValuesToHelper: true
		});

		var $rows = this.$tbody.children();

		for (var i = 0; i < $rows.length; i++) {
			new Craft.EditableTable.Row(this, $rows[i]);
		}

		this.$addRowBtn = this.$table.next('.add');
		this.addListener(this.$addRowBtn, 'activate', 'addRow');
	},

	addRow: function()
	{
		var rowId = this.settings.rowIdPrefix+(this.biggestId+2);

		if (this.blockTypes.length > 0) {
			var blockId = this.blockTypes[0].id;
		} else {
			var blockId = 'new1';
		}

		this.fieldName = this.baseName + '[blockTypes]['+blockId+'][fields]';
		
		var plainTextSettings = this.getFieldSettingsHtml('PlainText', blockId, rowId);

		var rowHtml = Craft.EditableColumnTable.getRowHtml(rowId, blockId, this.columns, this.fieldName, plainTextSettings, {});
		var $tr = $(rowHtml).appendTo(this.$tbody);

		new Craft.EditableTable.Row(this, $tr);
		this.sorter.addItems($tr);

		// Update the field settings
		var self = this;
		$tr.find('td.thin .select.small select').on('change', function() {
			var html = self.getFieldSettingsHtml($(this).val(), blockId, $tr.data('id'));

			$tr.find('.fieldtype-settings').html(html.bodyHtml);

			Craft.initUiElements($tr);

			Garnish.$bod.append(html.footHtml);
		});

		// Focus the first input in the row
		$tr.find('input,textarea,select').first().focus();

		// onAddRow callback
		this.settings.onAddRow($tr);
	},

	getFieldSettingsHtml: function(type, blockId, rowId) {
		var fieldTypeInfo = this.getfieldTypeInfo(type);

		if (fieldTypeInfo) {
			var bodyHtml = this.getParsedFieldTypeHtml(fieldTypeInfo.settingsBodyHtml, blockId, rowId);
			var footHtml = this.getParsedFieldTypeHtml(fieldTypeInfo.settingsFootHtml, blockId, rowId);

			$body = $('<div>'+bodyHtml+'</div>');

			return {
				bodyHtml: $body,
				footHtml: footHtml,
			};
		}
	},

	getfieldTypeInfo: function(type) {
		for (var i = 0; i < this.fieldTypeInfo.length; i++) {
			if (this.fieldTypeInfo[i].type == type) {
				return this.fieldTypeInfo[i];
			}
		}
	},

	getParsedFieldTypeHtml: function(html, blockTypeId, fieldId) {
		if (typeof html == 'string') {
			html = html.replace(/__BLOCK_TYPE__/g, blockTypeId);
			html = html.replace(/__FIELD__/g, fieldId);
		} else {
			html = '';
		}

		return html;
	},

},
{
	textualColTypes: ['singleline', 'multiline', 'number'],
	defaults: {
		rowIdPrefix: '',
		onAddRow: $.noop,
		onDeleteRow: $.noop
	},

	getRowHtml: function(rowId, blockId, columns, baseName, plainTextSettings, values)
	{
		var rowHtml = '<tr data-id="'+rowId+'" data-blocktype="'+blockId+'">';

		for (var colId in columns)
		{
			var col = columns[colId],
				name = baseName+'['+rowId+']['+colId+']',
				value = (typeof values[colId] != 'undefined' ? values[colId] : ''),
				textual = Craft.inArray(col.type, Craft.EditableColumnTable.textualColTypes);

			rowHtml += '<td class="'+(textual ? 'textual' : '')+' '+(typeof col['class'] != 'undefined' ? col['class'] : '')+'"' +
			              (typeof col['width'] != 'undefined' ? ' width="'+col['width']+'"' : '') +
			              '>';

			switch (col.type)
			{
				case 'select':
				{
					rowHtml += '<div class="select small"><select name="'+name+'">';

					var hasOptgroups = false;

					for (var key in col.options)
					{
						var option = col.options[key];

						if (typeof option.optgroup != 'undefined')
						{
							if (hasOptgroups)
							{
								rowHtml += '</optgroup>';
							}
							else
							{
								hasOptgroups = true;
							}

							rowHtml += '<optgroup label="'+option.optgroup+'">';
						}
						else
						{
							var optionLabel = (typeof option.label != 'undefined' ? option.label : option),
								optionValue = (typeof option.value != 'undefined' ? option.value : key),
								optionDisabled = (typeof option.disabled != 'undefined' ? option.disabled : false);

							rowHtml += '<option value="'+optionValue+'"'+(optionValue == 'PlainText' ? ' selected' : '')+(optionDisabled ? ' disabled' : '')+'>'+optionLabel+'</option>';
						}
					}

					if (hasOptgroups)
					{
						rowHtml += '</optgroup>';
					}

					rowHtml += '</select></div>';

					break;
				}

				default:
				{
					rowHtml += '<textarea name="'+name+'" rows="1">'+value+'</textarea>';
				}
			}

			rowHtml += '</td>';
		}

		rowHtml += '<td class="settings-col hidden">' +
			'<div class="fieldtype-settings">' +
				'<div>' +
					plainTextSettings.bodyHtml.html() + 
				'</div>' +
			'</div>' +
		'</td>';

		rowHtml += '<td class="thin action"><a class="settings icon" title="'+Craft.t('Settings')+'"></a></td>' +
				'<td class="thin action"><a class="move icon" title="'+Craft.t('Reorder')+'"></a></td>' +
				'<td class="thin action"><a class="delete icon" title="'+Craft.t('Delete')+'"></a></td>' +
			'</tr>';

		return rowHtml;
	}
});





})(jQuery);
