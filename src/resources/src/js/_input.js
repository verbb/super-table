if (typeof Craft.SuperTable === typeof undefined) {
    Craft.SuperTable = {};
}

(function($) {

    Craft.SuperTable.Input = Garnish.Base.extend({
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

        $field: null,

        init: function(id, blockType, inputNamePrefix, settings) {
            blockType = blockType[0];

            if (settings.fieldLayout == 'table') {
                this.$field = new Craft.SuperTable.InputTable(id, blockType, inputNamePrefix, settings);
            } else if (settings.fieldLayout == 'matrix') {
                this.$field = new Craft.SuperTable.InputMatrix(id, blockType, inputNamePrefix, settings);
            } else {
                this.$field = new Craft.SuperTable.InputRow(id, blockType, inputNamePrefix, settings);
            }
        },

        addRow: function() {
            this.$field.addRow();
        },
    });

    Craft.SuperTable.InputTable = Garnish.Base.extend({
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

            this.$table = $('table#' + id);
            this.$tbody = this.$table.children('tbody');

            this.sorter = new Craft.DataTableSorter(this.$table, {
                handle: 'td.super-table-action .move',
                helperClass: 'editablesupertablesorthelper',
                copyDraggeeInputValuesToHelper: true
            });

            this.$addRowBtn = this.$table.next('.add');
            this.addListener(this.$addRowBtn, 'activate', 'addRow');

            var $rows = this.$tbody.children();

            for (var i = 0; i < $rows.length; i++) {
                new Craft.EditableTable.Row(this, $rows[i]);

                var $block = $($rows[i]),
                    id = $block.data('id');

                // Is this a new block?
                var newMatch = (typeof id == 'string' && id.match(/new(\d+)/));

                if (newMatch && newMatch[1] > this.totalNewBlocks) {
                    this.totalNewBlocks = parseInt(newMatch[1]);
                }
            }

            this.updateAddBlockBtn();
        },

        addRow: function() {
            var type = this.blockType.type;
            var isStatic = this.settings.staticField;

            this.totalNewBlocks++;

            var id = 'new' + this.totalNewBlocks;

            var bodyHtml = this.getParsedBlockHtml(this.blockType.bodyHtml, id),
                footHtml = this.getParsedBlockHtml(this.blockType.footHtml, id);

            var html = '<tr data-id="' + id + '" data-type="' + type + '">' +
                '<input type="hidden" name="' + this.inputNamePrefix + '[sortOrder][]" value="' + id + '" />' +
                '<input type="hidden" name="' + this.inputNamePrefix + '[blocks][' + id + '][type]" value="' + type + '" />' +
                '' + bodyHtml + '';

            if (!isStatic) {
                html += '<td class="thin action super-table-action"><a class="move icon" title="' + Craft.t('super-table', 'Reorder') + '"></a></td>' +
                    '<td class="thin action super-table-action"><a class="delete icon" title="' + Craft.t('super-table', 'Delete') + '"></a></td>';
            }

            html += '</tr>';

            var $tr = $(html).appendTo(this.$tbody);

            Garnish.$bod.append(footHtml);

            Craft.initUiElements($tr);

            new Craft.EditableTable.Row(this, $tr);
            this.sorter.addItems($tr);

            this.updateAddBlockBtn();
        },

        getParsedBlockHtml: function(html, id) {
            if (typeof html == 'string') {
                return html.replace(new RegExp(`__BLOCK_${this.settings.placeholderKey}__`, 'g'), id);
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

        canDeleteRows: function() {
            return (!this.settings.minRows || this.$tbody.children().length > this.settings.minRows);
        },

        deleteRow: function(row) {
            if (!this.canDeleteRows()) {
                return;
            }

            // Pause the draft editor
            if (window.draftEditor) {
                window.draftEditor.pause();
            }

            this.sorter.removeItems(row.$tr);
            row.$tr.remove();

            this.updateAddBlockBtn();

            // Resume the draft editor
            if (window.draftEditor) {
                window.draftEditor.resume();
            }
        },
    });






    Craft.SuperTable.InputRow = Garnish.Base.extend({
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
                handle: '.tfoot-actions .reorder .move',
                axis: 'y',
                collapseDraggees: true,
                magnetStrength: 4,
                helperLagBase: 1.5,
                helperOpacity: 0.9,
            });

            for (var i = 0; i < this.$rows.length; i++) {
                new Craft.SuperTable.InputRow.Row(this, this.$rows[i]);

                var $block = $(this.$rows[i]),
                    id = $block.data('id');

                // Is this a new block?
                var newMatch = (typeof id == 'string' && id.match(/new(\d+)/));

                if (newMatch && newMatch[1] > this.totalNewBlocks) {
                    this.totalNewBlocks = parseInt(newMatch[1]);
                }
            }

            this.$addRowBtn = this.$divInner.next('.add');
            this.addListener(this.$addRowBtn, 'activate', 'addRow');

            this.updateAddBlockBtn();

            this.addListener(this.$div, 'resize', 'onResize');
            Garnish.$doc.ready($.proxy(this, 'onResize'));
        },

        addRow: function() {
            var type = this.blockType.type;
            var isStatic = this.settings.staticField;

            this.totalNewBlocks++;

            var id = 'new' + this.totalNewBlocks;

            var bodyHtml = this.getParsedBlockHtml(this.blockType.bodyHtml, id),
                footHtml = this.getParsedBlockHtml(this.blockType.footHtml, id);

            var html = '<div class="superTableRow" data-id="' + id + '" data-type="' + type + '">' +
                '<input type="hidden" name="' + this.inputNamePrefix + '[sortOrder][]" value="' + id + '">' +
                '<input type="hidden" name="' + this.inputNamePrefix + '[blocks][' + id  +'][type]" value="' + type + '">' +
                '<div id="' + id + '" class="superTable-layout-row-new">' +
                    '<div class="superTable-layout-row-new-body ' + (isStatic ? 'static-field' : '') + '">' +
                        bodyHtml +
                    '</div>';

            if (!isStatic) {
                html += '<div class="superTable-layout-row-new-actions tfoot-actions">' +
                    '<div class="floating reorder"><a class="move icon" title="' + Craft.t('super-table', 'Reorder') + '"></a></div>' +
                    '<div class="floating delete"><a class="delete icon" title="' + Craft.t('super-table', 'Delete') + '"></a></div>' + 
                '</div>';
            }

            html += '</div>' +
                '</div>';

            var $tr = $(html).appendTo(this.$divInner);

            Garnish.$bod.append(footHtml);

            Craft.initUiElements($tr);

            var row = new Craft.SuperTable.InputRow.Row(this, $tr);
            this.sorter.addItems($tr);

            this.updateAddBlockBtn();
        },

        getParsedBlockHtml: function(html, id) {
            if (typeof html == 'string') {
                return html.replace(new RegExp(`__BLOCK_${this.settings.placeholderKey}__`, 'g'), id);
            } else {
                return '';
            }
        },

        canAddMoreRows: function() {
            return (!this.settings.maxRows || this.$divInner.children('.superTableRow').length < this.settings.maxRows);
        },

        onResize: function() {
            // A minor fix if this row contains a Matrix field. For Matrix fields with lots of blocks,
            // we need to make sure we trigger the resize-handling, which turns the Add Block buttons into a dropdown
            // otherwise, we get a nasty overflow of buttons.

            // Get the Super Table overall width, with some padding
            var actionBtnWidth = this.$divInner.find('.tfoot-actions').width();
            var rowHeaderWidth = this.$divInner.find('.rowHeader').width();
            var rowWidth = this.$divInner.width() - actionBtnWidth - rowHeaderWidth - 20;
            var $matrixFields = this.$divInner.find('.matrix.matrix-field');

            if ($matrixFields.length) {
                $.each($matrixFields, function(i, element) {
                    var $matrixField = $(element);
                    var matrixButtonWidth = $matrixField.find('.buttons').outerWidth(true);

                    if (matrixButtonWidth > rowWidth) {
                        // showNewBlockBtn is a custom function in MatrixInputAlt.js for minor impact
                        $matrixField.trigger('showNewBlockBtn');
                    }
                });
            }
        },

        updateAddBlockBtn: function() {
            if (this.canAddMoreRows()) {
                this.$addRowBtn.removeClass('disabled');
            } else {
                this.$addRowBtn.addClass('disabled');
            }
        },
    });

    Craft.SuperTable.InputRow.Row = Garnish.Base.extend({
        table: null,

        $tr: null,
        $deleteBtn: null,

        init: function(table, tr) {
            this.table = table;
            this.$tr = $(tr);

            var $deleteBtn = this.$tr.children().last().find('> .tfoot-actions .delete');
            this.addListener($deleteBtn, 'click', 'deleteRow');
        },

        canDeleteRows: function() {
            return (!this.table.settings.minRows || this.table.$divInner.children('.superTableRow').length > this.table.settings.minRows);
        },

        deleteRow: function() {
            if (!this.canDeleteRows()) {
                return;
            }

            // Pause the draft editor
            if (window.draftEditor) {
                window.draftEditor.pause();
            }

            this.table.sorter.removeItems(this.$tr);

            this.$tr.remove();

            this.table.updateAddBlockBtn();

            // Resume the draft editor
            if (window.draftEditor) {
                window.draftEditor.resume();
            }
        },

    });

    Craft.SuperTable.InputMatrix = Garnish.Base.extend({
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
            this.$divInner = this.$div.children('.matrixLayoutContainer');

            this.$rows = this.$divInner.children('.superTableMatrix');
            collapsedRows = Craft.SuperTable.InputMatrix.getCollapsedBlockIds();

            this.sorter = new Garnish.DragSort(this.$rows, {
                handle: '> .actions .move',
                axis: 'y',
                collapseDraggees: true,
                magnetStrength: 4,
                helperLagBase: 1.5,
                helperOpacity: 0.9,
            });

            for (var i = 0; i < this.$rows.length; i++) {
                var row = new Craft.SuperTable.InputMatrix.Row(this, this.$rows[i]);

                var $block = $(this.$rows[i]),
                    id = $block.data('id');

                // Is this a new block?
                var newMatch = (typeof id == 'string' && id.match(/new(\d+)/));

                if (newMatch && newMatch[1] > this.totalNewBlocks) {
                    this.totalNewBlocks = parseInt(newMatch[1]);
                }

                if (id && $.inArray('' + id, collapsedRows) !== -1) {
                    row.collapse();
                }
            }

            this.$addRowBtn = this.$divInner.next('.add');
            this.addListener(this.$addRowBtn, 'activate', 'addRow');

            this.updateAddBlockBtn();

            this.addListener(this.$div, 'resize', 'onResize');
            Garnish.$doc.ready($.proxy(this, 'onResize'));
        },

        addRow: function() {
            var type = this.blockType.type;
            var isStatic = this.settings.staticField;

            this.totalNewBlocks++;

            var id = 'new'+ this.totalNewBlocks;

            var bodyHtml = this.getParsedBlockHtml(this.blockType.bodyHtml, id),
                footHtml = this.getParsedBlockHtml(this.blockType.footHtml, id);

            var html = '<div class="superTableMatrix matrixblock ' + (isStatic ? 'static' : '') + '" data-id="' + id + '">' +
                '<input type="hidden" name="' + this.inputNamePrefix + '[sortOrder][]" value="' + id + '">' +
                '<input type="hidden" name="' + this.inputNamePrefix + '[blocks][' + id + '][type]" value="' + type + '">';

            if (!isStatic) {
                html += '<div class="titlebar">' +
                '<div class="blocktype"></div>' +
                '<div class="preview"></div>' +
                '</div>' +
                '<div class="actions">' +
                '<a class="settings icon menubtn" title="' + Craft.t('app', 'Actions') + '" role="button"></a>' +
                '<div class="menu">' +
                '<ul class="padded">' +
                '<li><a data-icon="collapse" data-action="collapse">' + Craft.t('app', 'Collapse') + '</a></li>' +
                '<li class="hidden"><a data-icon="expand" data-action="expand">' + Craft.t('app', 'Expand') + '</a></li>' +
                '</ul>' +
                '<hr class="padded">' +
                '<ul class="padded">' +
                '<li><a class="error" data-icon="remove" data-action="delete">' + Craft.t('super-table', 'Delete') + '</a></li>' +
                '</ul>' +
                '</div>' +
                '<a class="move icon" title="' + Craft.t('super-table', 'Reorder') + '" role="button"></a>' +
                '</div>';
            }

            html += '<div class="fields">' + bodyHtml + '</div>' +
            '</div>';

            var $tr = $(html).appendTo(this.$divInner);

            Garnish.$bod.append(footHtml);

            Craft.initUiElements($tr);

            var row = new Craft.SuperTable.InputMatrix.Row(this, $tr);
            this.sorter.addItems($tr);

            row.expand();

            this.updateAddBlockBtn();
        },

        getParsedBlockHtml: function(html, id) {
            if (typeof html == 'string') {
                return html.replace(new RegExp(`__BLOCK_${this.settings.placeholderKey}__`, 'g'), id);
            } else {
                return '';
            }
        },

        canAddMoreRows: function() {
            return (!this.settings.maxRows || this.$divInner.children('.superTableMatrix').length < this.settings.maxRows);
        },

        onResize: function() {
            // A minor fix if this row contains a Matrix field. For Matrix fields with lots of blocks,
            // we need to make sure we trigger the resize-handling, which turns the Add Block buttons into a dropdown
            // otherwise, we get a nasty overflow of buttons.

            // Get the Super Table overall width, with some padding
            var rowWidth = this.$divInner.width() - 20;
            var $matrixFields = this.$divInner.find('.matrix.matrix-field');

            if ($matrixFields.length) {
                $.each($matrixFields, function(i, element) {
                    var $matrixField = $(element);
                    var matrixButtonWidth = $matrixField.find('.buttons').outerWidth(true);

                    if (matrixButtonWidth > rowWidth) {
                        // showNewBlockBtn is a custom function in MatrixInputAlt.js for minor impact
                        $matrixField.trigger('showNewBlockBtn');
                    }
                });
            }
        },

        updateAddBlockBtn: function() {
            if (this.canAddMoreRows()) {
                this.$addRowBtn.removeClass('disabled');
            } else {
                this.$addRowBtn.addClass('disabled');
            }
        },
    }, {
        collapsedBlockStorageKey: 'Craft-' + Craft.systemUid + '.SuperTable.InputMatrix.collapsedBlocks',

        getCollapsedBlockIds: function() {
            if (typeof localStorage[Craft.SuperTable.InputMatrix.collapsedBlockStorageKey] === 'string') {
                return Craft.filterArray(localStorage[Craft.SuperTable.InputMatrix.collapsedBlockStorageKey].split(','));
            }
            else {
                return [];
            }
        },

        setCollapsedBlockIds: function(ids) {
            localStorage[Craft.SuperTable.InputMatrix.collapsedBlockStorageKey] = ids.join(',');
        },

        rememberCollapsedBlockId: function(id) {
            if (typeof Storage !== 'undefined') {
                var collapsedBlocks = Craft.SuperTable.InputMatrix.getCollapsedBlockIds();

                if ($.inArray('' + id, collapsedBlocks) === -1) {
                    collapsedBlocks.push(id);
                    Craft.SuperTable.InputMatrix.setCollapsedBlockIds(collapsedBlocks);
                }
            }
        },

        forgetCollapsedBlockId: function(id) {
            if (typeof Storage !== 'undefined') {
                var collapsedBlocks = Craft.SuperTable.InputMatrix.getCollapsedBlockIds(),
                    collapsedBlocksIndex = $.inArray('' + id, collapsedBlocks);

                if (collapsedBlocksIndex !== -1) {
                    collapsedBlocks.splice(collapsedBlocksIndex, 1);
                    Craft.SuperTable.InputMatrix.setCollapsedBlockIds(collapsedBlocks);
                }
            }
        }
    });

    Craft.SuperTable.InputMatrix.Row = Garnish.Base.extend({
        table: null,

        $tr: null,
        $deleteBtn: null,
        $titlebar: null,
        $actionMenu: null,

        id: null,
        collapsed: false,
        $previewContainer: null,
        $fieldsContainer: null,

        init: function(table, tr) {
            this.table = table;
            this.$tr = $(tr);
            this.id = this.$tr.data('id');

            this.$titlebar = this.$tr.children('.titlebar');
            this.$previewContainer = this.$titlebar.children('.preview');
            this.$fieldsContainer = this.$tr.children('.fields');
            var $menuBtn = this.$tr.find('> .actions > .settings');

            // Check for a menu button, which isn't there for static fields
            if ($menuBtn) {
                var menuBtn = new Garnish.MenuBtn($menuBtn);

                if (menuBtn.menu) {
                    this.$actionMenu = menuBtn.menu.$container;

                    menuBtn.menu.settings.onOptionSelect = $.proxy(this, 'onMenuOptionSelect');
                }
            }

            // Was this block already collapsed?
            if (Garnish.hasAttr(this.$tr, 'data-collapsed')) {
                this.collapse();
            }

            this._handleTitleBarClick = function(ev) {
                ev.preventDefault();
                this.toggle();
            };

            this.addListener(this.$titlebar, 'doubletap', this._handleTitleBarClick);
        },

        deleteRow: function() {
            this.table.sorter.removeItems(this.$tr);

            // Pause the draft editor
            if (window.draftEditor) {
                window.draftEditor.pause();
            }

            this.$tr.velocity({
                opacity: 0,
                marginBottom: -(this.$tr.outerHeight())
            }, 'fast', $.proxy(function() {
                this.$tr.remove();
                this.table.updateAddBlockBtn();

                // Resume the draft editor
                if (window.draftEditor) {
                    window.draftEditor.resume();
                }
            }, this));
        },

        toggle: function() {
            if (this.collapsed) {
                this.expand();
            }
            else {
                this.collapse(true);
            }
        },

        collapse: function(animate) {
            if (this.collapsed) {
                return;
            }

            this.$tr.addClass('collapsed');

            var previewHtml = '',
                $fields = this.$fieldsContainer.children();

            for (var i = 0; i < $fields.length; i++) {
                var $field = $($fields[i]),
                    $inputs = $field.children('.input').find('select,input[type!="hidden"],textarea,.label'),
                    inputPreviewText = '';

                for (var j = 0; j < $inputs.length; j++) {
                    var $input = $($inputs[j]),
                        value;

                    if ($input.hasClass('label')) {
                        var $maybeLightswitchContainer = $input.parent().parent();

                        if ($maybeLightswitchContainer.hasClass('lightswitch') && (
                                ($maybeLightswitchContainer.hasClass('on') && $input.hasClass('off')) ||
                                (!$maybeLightswitchContainer.hasClass('on') && $input.hasClass('on'))
                            )) {
                            continue;
                        }

                        value = $input.text();
                    }
                    else {
                        value = Craft.getText(Garnish.getInputPostVal($input));
                    }

                    if (value instanceof Array) {
                        value = value.join(', ');
                    }

                    if (value) {
                        value = Craft.trim(value);

                        if (value) {
                            if (inputPreviewText) {
                                inputPreviewText += ', ';
                            }

                            inputPreviewText += value;
                        }
                    }
                }

                if (inputPreviewText) {
                    previewHtml += (previewHtml ? ' <span>|</span> ' : '') + inputPreviewText;
                }
            }

            this.$previewContainer.html(previewHtml);

            this.$fieldsContainer.velocity('stop');
            this.$tr.velocity('stop');

            if (animate) {
                this.$fieldsContainer.velocity('fadeOut', {duration: 'fast'});
                this.$tr.velocity({height: 16}, 'fast');
            }
            else {
                this.$previewContainer.show();
                this.$fieldsContainer.hide();
                this.$tr.css({height: 16});
            }

            setTimeout($.proxy(function() {
                this.$actionMenu.find('a[data-action=collapse]:first').parent().addClass('hidden');
                this.$actionMenu.find('a[data-action=expand]:first').parent().removeClass('hidden');
            }, this), 200);

            // Remember that?
            if (!this.isNew) {
                Craft.SuperTable.InputMatrix.rememberCollapsedBlockId(this.id);
            }
            else {
                if (!this.$collapsedInput) {
                    this.$collapsedInput = $('<input type="hidden" name="' + this.matrix.inputNamePrefix + '[' + this.id + '][collapsed]" value="1"/>').appendTo(this.$container);
                }
                else {
                    this.$collapsedInput.val('1');
                }
            }

            this.collapsed = true;
        },

        expand: function() {
            if (!this.collapsed) {
                return;
            }

            this.$tr.removeClass('collapsed');

            this.$fieldsContainer.velocity('stop');
            this.$tr.velocity('stop');

            var collapsedContainerHeight = this.$tr.height();
            this.$tr.height('auto');
            this.$fieldsContainer.show();
            var expandedContainerHeight = this.$tr.height();
            var displayValue = this.$fieldsContainer.css('display') || 'block';
            this.$tr.height(collapsedContainerHeight);
            this.$fieldsContainer.hide().velocity('fadeIn', {duration: 'fast', display: displayValue});
            this.$tr.velocity({height: expandedContainerHeight}, 'fast', $.proxy(function() {
                this.$previewContainer.html('');
                this.$tr.height('auto');
            }, this));

            setTimeout($.proxy(function() {
                this.$actionMenu.find('a[data-action=collapse]:first').parent().removeClass('hidden');
                this.$actionMenu.find('a[data-action=expand]:first').parent().addClass('hidden');
            }, this), 200);

            // Remember that?
            if (!this.isNew && typeof Storage !== 'undefined') {
                var collapsedBlocks = Craft.SuperTable.InputMatrix.getCollapsedBlockIds(),
                    collapsedBlocksIndex = $.inArray('' + this.id, collapsedBlocks);

                if (collapsedBlocksIndex !== -1) {
                    collapsedBlocks.splice(collapsedBlocksIndex, 1);
                    Craft.SuperTable.InputMatrix.setCollapsedBlockIds(collapsedBlocks);
                }
            }

            if (!this.isNew) {
                Craft.SuperTable.InputMatrix.forgetCollapsedBlockId(this.id);
            }
            else if (this.$collapsedInput) {
                this.$collapsedInput.val('');
            }

            this.collapsed = false;
        },

        onMenuOptionSelect: function(option) {
            var $option = $(option);

            switch ($option.data('action')) {
                case 'collapse': {
                    this.collapse(true);
                    break;
                }

                case 'expand': {
                    this.expand();
                    break;
                }

                case 'delete': {
                    this.deleteRow();
                    break;
                }
            }
        },
    });

})(jQuery);
