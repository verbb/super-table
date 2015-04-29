(function($){

Craft.SuperTableInput = Garnish.Base.extend({
	id: null,
	blockType: null,
	inputNamePrefix: null,

	totalNewBlocks: 0,

	sorter: null,

	$table: null,
	$tbody: null,
	$addRowBtn: null,

	init: function(id, blockType, inputNamePrefix, settings) {
		this.id = id
		this.blockType = blockType;
		this.inputNamePrefix = inputNamePrefix;
		this.setSettings(settings, Craft.EditableTable.defaults);

		this.$table = $('table#'+id);
		this.$tbody = this.$table.children('tbody');

		this.sorter = new Craft.DataTableSorter(this.$table, {
			helperClass: 'editablesupertablesorthelper',
			copyDraggeeInputValuesToHelper: true
		});

		this.$addRowBtn = this.$table.next('.add');
		this.addListener(this.$addRowBtn, 'activate', 'addRow');

		var $rows = this.$tbody.children();

		for (var i = 0; i < $rows.length; i++) {
			new Craft.EditableTable.Row(this, $rows[i]);
		}
	},

	addRow: function() {
		var type = this.blockType.type;

		this.totalNewBlocks++;

		var id = 'new'+this.totalNewBlocks;

		var bodyHtml = this.getParsedBlockHtml(this.blockType.bodyHtml, id),
			footHtml = this.getParsedBlockHtml(this.blockType.footHtml, id);

		var html = '<tr data-id="'+this.totalNewBlocks+'">' +
			'<input type="hidden" name="'+this.inputNamePrefix+'['+id+'][type]" value="'+type+'" />' +
			'' + bodyHtml + '' +
			'<td class="thin action"><a class="move icon" title="'+Craft.t('Reorder')+'"></a></td>' +
			'<td class="thin action"><a class="delete icon" title="'+Craft.t('Delete')+'"></a></td>' +
		'</tr>';

		var $tr = $(html).appendTo(this.$tbody);

		Garnish.$bod.append(footHtml);
		
		Craft.initUiElements($tr);

		new Craft.EditableTable.Row(this, $tr);
		this.sorter.addItems($tr);
	},

	getParsedBlockHtml: function(html, id) {
		if (typeof html == 'string') {
			return html.replace(/__BLOCK__/g, id);
		} else {
			return '';
		}
	},

});


})(jQuery);
