(function($){

Craft.SuperTableInputTable = Garnish.Base.extend({
    id: null,
    blockType: null,
    inputNamePrefix: null,

    totalNewBlocks: 0,

    sorter: null,

    $div: null,
    $divInner: null,

    $table: null,
    $tbody: null,
    $addRowBtn: null,

    init: function(id, blockType, inputNamePrefix, settings) {
        this.id = id
        this.blockType = blockType;
        this.inputNamePrefix = inputNamePrefix;
        this.setSettings(settings, {
            rowIdPrefix: '',
            onAddRow: $.noop,
            onDeleteRow: $.proxy(this, 'deleteRow')
        });

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

        this.updateAddBlockBtn();

        // For a static field, and if no rows added yet, add one automatically
        if (settings.staticField && $rows.length == 0) {
            this.$addRowBtn.trigger('activate');
        }
    },

    addRow: function() {
        var type = this.blockType.type;

        this.totalNewBlocks++;

        var id = 'new'+this.totalNewBlocks;

        var bodyHtml = this.getParsedBlockHtml(this.blockType.bodyHtml, id),
            footHtml = this.getParsedBlockHtml(this.blockType.footHtml, id);
        
        var staticFieldStyle = (this.settings.staticField) ? 'style="display: none;"' : '';

        var html = '<tr data-id="'+this.totalNewBlocks+'">' +
            '<input type="hidden" name="'+this.inputNamePrefix+'['+id+'][type]" value="'+type+'" />' +
            '' + bodyHtml + '' +
            '<td '+staticFieldStyle+' class="thin action"><a class="move icon" title="'+Craft.t('Reorder')+'"></a></td>' +
            '<td '+staticFieldStyle+' class="thin action"><a class="delete icon" title="'+Craft.t('Delete')+'"></a></td>' +
        '</tr>';

        var $tr = $(html).appendTo(this.$tbody);

        Garnish.$bod.append(footHtml);
        
        Craft.initUiElements($tr);

        new Craft.EditableTable.Row(this, $tr);
        this.sorter.addItems($tr);

        this.updateAddBlockBtn();
    },

    getParsedBlockHtml: function(html, id) {
        if (typeof html == 'string') {
            return html.replace(/__BLOCK_ST__/g, id);
        } else {
            return '';
        }
    },

    canAddMoreRows: function() {
        return (!this.settings.maxRows || this.$tbody.children().length < this.settings.maxRows);
    },

    updateAddBlockBtn: function() {
        if (this.canAddMoreRows()) {
            this.$addRowBtn.removeClass('disabled');
        } else {
            this.$addRowBtn.addClass('disabled');
        }
    },

    deleteRow: function() {
        this.updateAddBlockBtn();
    },

});






Craft.SuperTableInputRow = Garnish.Base.extend({
    id: null,
    blockType: null,
    inputNamePrefix: null,
    settings: null,

    totalNewBlocks: 0,

    sorter: null,

    $div: null,
    $divInner: null,
    $rows: null,

    $table: null,
    $tbody: null,
    $addRowBtn: null,

    init: function(id, blockType, inputNamePrefix, settings) {
        this.id = id
        this.blockType = blockType;
        this.inputNamePrefix = inputNamePrefix;
        this.settings = settings;

        this.$div = $('div#'+id);
        this.$divInner = this.$div.children('.rowLayoutContainer');

        this.$rows = this.$divInner.children('.superTableRow');

        this.sorter = new Garnish.DragSort(this.$rows, {
            handle: 'tfoot .reorder .move',
            axis: 'y',
            collapseDraggees: true,
            magnetStrength: 4,
            helperLagBase: 1.5,
            helperOpacity: 0.9,
        });

        for (var i = 0; i < this.$rows.length; i++) {
            new Craft.SuperTableInputRow.Row(this, this.$rows[i]);
        }

        this.$addRowBtn = this.$divInner.next('.add');
        this.addListener(this.$addRowBtn, 'activate', 'addRow');

        this.updateAddBlockBtn();

        // For a static field, and if no rows added yet, add one automatically
        if (settings.staticField && this.$rows.length == 0) {
            this.$addRowBtn.trigger('activate');
        }
    },

    addRow: function() {
        var type = this.blockType.type;

        this.totalNewBlocks++;

        var id = 'new'+this.totalNewBlocks;

        var bodyHtml = this.getParsedBlockHtml(this.blockType.bodyHtml, id),
            footHtml = this.getParsedBlockHtml(this.blockType.footHtml, id);

        var staticFieldStyle = (this.settings.staticField) ? 'style="display: none;"' : '';

        var html = '<div class="superTableRow">' +
            '<input type="hidden" name="'+this.inputNamePrefix+'['+id+'][type]" value="'+type+'">' +
            '<table id="'+id+'" class="shadow-box editable superTable">' +
                '<tbody>' +
                    '' + bodyHtml + '' +
                '</tbody>' +
                '<tfoot ' + staticFieldStyle + '>' +
                    '<tr>' +
                        '<td class="floating reorder"><a class="move icon" title="'+Craft.t('Reorder')+'"></a></td>' +
                        '<td class="floating delete"><a class="delete icon" title="'+Craft.t('Delete')+'"></a></td>' +
                    '</tr>' +
                '</tfoot>' +
            '</table>' +
        '</div>';

        var $tr = $(html).appendTo(this.$divInner);

        Garnish.$bod.append(footHtml);
        
        Craft.initUiElements($tr);

        new Craft.SuperTableInputRow.Row(this, $tr);
        this.sorter.addItems($tr);

        this.updateAddBlockBtn();
    },

    getParsedBlockHtml: function(html, id) {
        if (typeof html == 'string') {
            return html.replace(/__BLOCK_ST__/g, id);
        } else {
            return '';
        }
    },

    canAddMoreRows: function() {
        return (!this.settings.maxRows || this.$divInner.children('.superTableRow').length < this.settings.maxRows);
    },

    updateAddBlockBtn: function() {
        if (this.canAddMoreRows()) {
            this.$addRowBtn.removeClass('disabled');
        } else {
            this.$addRowBtn.addClass('disabled');
        }
    },
});

Craft.SuperTableInputRow.Row = Garnish.Base.extend({
    table: null,

    $tr: null,
    $deleteBtn: null,

    init: function(table, tr) {
        this.table = table;
        this.$tr = $(tr);

        var $deleteBtn = this.$tr.children().last().find('tfoot .delete');
        this.addListener($deleteBtn, 'click', 'deleteRow');
    },

    deleteRow: function() {
        this.table.sorter.removeItems(this.$tr);
        this.$tr.remove();

        this.table.updateAddBlockBtn();
    },
});



})(jQuery);
