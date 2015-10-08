(function($){

Craft.SuperTableConfigurator = Garnish.Base.extend({
    id: null,
    idPrefix: null,
    inputNamePrefix: null,
    inputIdPrefix: null,
    fields: null,
    totalNewFields: 0,

    $table: null,
    $tbody: null,
    sorter: null,
    fieldTypeInfo: null,
    settings: {
        rowIdPrefix: 'new',
        onAddRow: $.noop,
        onDeleteRow: $.noop,
    },

    init: function(id, idPrefix, fieldTypeInfo, inputNamePrefix) {
        this.id = id;
        this.idPrefix = idPrefix;
        this.fieldTypeInfo = fieldTypeInfo;

        this.inputNamePrefix = inputNamePrefix+'[blockTypes]['+this.id+']';
        this.inputIdPrefix = Craft.formatInputId(this.inputNamePrefix)+'-blockTypes-'+this.id;

        this.$table = $('#' + this.idPrefix);
        this.$tbody = this.$table.children('tbody');

        this.sorter = new Craft.DataTableSorter(this.$table, {
            helperClass: 'editablecolumntablesorthelper',
            copyDraggeeInputValuesToHelper: true
        });

        var $rows = this.$tbody.children('tr');

        for (var i = 0; i < $rows.length; i++) {
            new Craft.EditableTable.Row(this, $rows[i]);
        }

        this.$addRowBtn = this.$table.next('.add');
        this.addListener(this.$addRowBtn, 'activate', 'addRow');

        // Find the existing fields
        this.fields = {};

        var $fieldItems = this.$tbody.children('tr');

        for (var i = 0; i < $fieldItems.length; i++) {
            var $fieldItem = $($fieldItems[i]),
                id = $fieldItem.data('id');

            this.fields[id] = new SuperTableField(this, $fieldItem);

            // Is this a new field?
            var newMatch = (typeof id == 'string' && id.match(/new(\d+)/));

            if (newMatch && newMatch[1] > this.totalNewFields) {
                this.totalNewFields = parseInt(newMatch[1]);
            }
        }
    },

    getFieldTypeInfo: function(type) {
        for (var i = 0; i < this.fieldTypeInfo.length; i++) {
            if (this.fieldTypeInfo[i].type == type) {
                return this.fieldTypeInfo[i];
            }
        }
    },

    addRow: function() {
        this.totalNewFields++;
        var id = 'new'+this.totalNewFields;

        var rowHtml = '<tr data-id="'+id+'" data-blocktype="'+this.id+'"></tr>';
        var $item = $(rowHtml).appendTo(this.$tbody);

        this.fields[id] = new SuperTableField(this, $item);

        new Craft.EditableTable.Row(this, $item);
        this.sorter.addItems($item);
    },
});


SuperTableField = Garnish.Base.extend({
    blockType: null,
    id: null,

    inputNamePrefix: null,
    inputIdPrefix: null,

    selectedFieldType: null,
    initializedFieldTypeSettings: null,

    $item: null,

    $fieldSettingsContainer: null,
    $nameInput: null,
    $handleInput: null,
    $typeSelect: null,
    $typeSettingsContainer: null,
    $typeSettingsButton: null,
    $typeSettingsCol: null,

    init: function(blockType, $item) {
        this.blockType = blockType;
        this.$item = $item;
        this.id = this.$item.data('id');

        this.inputNamePrefix = this.blockType.inputNamePrefix+'[fields]['+this.id+']';
        this.inputIdPrefix = this.blockType.inputIdPrefix+'-fields-'+this.id;

        this.initializedFieldTypeSettings = {};

        // Find the field settings container if it exists, otherwise create it
        this.$fieldSettingsContainer = $item;

        var isNew = (!this.$fieldSettingsContainer.children().length);

        if (isNew) {
            this.$fieldSettingsContainer = $(this.getDefaultFieldSettingsHtml()).appendTo(this.$fieldSettingsContainer).parent();
        }

        this.$nameInput = this.$fieldSettingsContainer.find('textarea[name$="[name]"]:first');
        this.$handleInput = this.$fieldSettingsContainer.find('textarea[name$="[handle]"]:first');
        this.$typeSelect = this.$fieldSettingsContainer.find('select[name$="[type]"]:first');
        this.$typeSettingsContainer = this.$fieldSettingsContainer.find('.fieldtype-settings:first');
        this.$typeSettingsButton = this.$fieldSettingsContainer.find('td.action a.settings.icon:first');
        this.$typeSettingsCol = this.$fieldSettingsContainer.find('td.settings-col:first');

        if (isNew) {
            this.setFieldType('PlainText');
        } else {
            this.selectedFieldType = this.$typeSelect.val();
            this.initializedFieldTypeSettings[this.selectedFieldType] = this.$typeSettingsContainer.children();
        }

        if (!this.$handleInput.val()) {
            new Craft.HandleGenerator(this.$nameInput, this.$handleInput);
        }

        this.addListener(this.$typeSelect, 'change', 'onTypeSelectChange');
        this.addListener(this.$typeSettingsButton, 'click', 'onTypeSettingsClick');
    },

    onTypeSelectChange: function() {
        this.setFieldType(this.$typeSelect.val());
    },

    onTypeSettingsClick: function() {
        var info = this.blockType.getFieldTypeInfo(this.selectedFieldType),
            footHtml = this.getParsedFieldTypeHtml(info.settingsFootHtml),
            settingsContainer = this.$typeSettingsContainer;

        this.$typeSettingsContainer.remove();

        new Craft.SuperTableSettingsModal(this, footHtml, settingsContainer);
    },

    restoreSettingsHtml: function($html) {
        this.$typeSettingsCol.html($html);
    },

    setFieldType: function(type) {
        if (this.selectedFieldType) {
            this.initializedFieldTypeSettings[this.selectedFieldType].detach();
        }

        this.selectedFieldType = type;
        this.$typeSelect.val(type);

        var firstTime = (typeof this.initializedFieldTypeSettings[type] == 'undefined');

        if (firstTime) {
            var info = this.blockType.getFieldTypeInfo(type),
                bodyHtml = this.getParsedFieldTypeHtml(info.settingsBodyHtml),
                footHtml = this.getParsedFieldTypeHtml(info.settingsFootHtml),
                $body = $('<div>'+bodyHtml+'</div>');

            this.initializedFieldTypeSettings[type] = $body;
        } else {
            var $body = this.initializedFieldTypeSettings[type];
        }

        $body.appendTo(this.$typeSettingsContainer);

        if (firstTime) {
            Craft.initUiElements($body);
            Garnish.$bod.append(footHtml);
        }
    },

    getParsedFieldTypeHtml: function(html) {
        var newHtml = html;

        if (typeof newHtml == 'string') {
            newHtml = newHtml.replace(/__BLOCK_TYPE_ST__/g, this.blockType.id);
            newHtml = newHtml.replace(/__FIELD_ST__/g, this.id);
        } else {
            newHtml = '';
        }

        return newHtml;
    },

    getDefaultFieldSettingsHtml: function() {
        var html = '<td class="textual">' +
                '<textarea name="'+this.inputNamePrefix+'[name]" rows="1"></textarea>' +
            '</td>' +
            '<td class="textual code">' +
                '<textarea name="'+this.inputNamePrefix+'[handle]" rows="1"></textarea>' +
            '</td>' +
            '<td class="textual code" width="50">' +
                '<textarea name="'+this.inputNamePrefix+'[width]" rows="1"></textarea>' +
            '</td>' +
            '<td width="20">' +
                '<input type="hidden" name="'+this.inputNamePrefix+'[required]">' +
                '<input type="checkbox" name="'+this.inputNamePrefix+'[required]" value="1">' +
            '</td>' +
            '<td class="thin">' +
                '<div class="select small">' + 
                    '<select id="type" class="fieldtoggle" name="'+this.inputNamePrefix+'[type]">';
                        for (var i = 0; i < this.blockType.fieldTypeInfo.length; i++) {
                            var info = this.blockType.fieldTypeInfo[i], selected = (info.type == 'PlainText');
                            html += '<option value="'+info.type+'"'+(selected ? ' selected=""' : '')+'>'+info.name+'</option>';
                        }
                    html += '</select>' + 
                '</div>' + 
            '</td>' +
            '<td class="settings-col hidden">' +
                '<div class="fieldtype-settings" name="'+this.inputNamePrefix+'[typesettings]"></div>' +
            '</td>' +
            '<td class="thin action"><a class="settings icon" title="'+Craft.t('Settings')+'"></a></td>' +
            '<td class="thin action"><a class="move icon" title="'+Craft.t('Reorder')+'"></a></td>' +
            '<td class="thin action"><a class="delete icon" title="'+Craft.t('Delete')+'"></a></td>';

        return html;
    },

});


Craft.SuperTableSettingsModal = Garnish.Modal.extend({
    field: null,
    fieldTypeFootHtml: null,
    $settingsContainer: null,

    $body: null,
    $buttons: null,
    $closeBtn: null,
    $saveBtn: null,
    $fieldSettings: null,

    init: function(field, fieldTypeFootHtml, $settingsContainer) {
        this.field = field;
        this.fieldTypeFootHtml = fieldTypeFootHtml;
        this.$settingsContainer = $settingsContainer;

        // Build the modal
        var $container = $('<div class="modal fieldsettingsmodal"></div>').appendTo(Garnish.$bod),
            $body = $('<div class="body"></div>').appendTo($container),
            $content = $('<div class="content"></div>').appendTo($body),
            $main = $('<div class="main"></div>').appendTo($content),
            $footer = $('<div class="footer"/>').appendTo($container);

        this.base($container, this.settings);

        this.$buttons = $('<div class="buttons rightalign first"/>').appendTo($footer);
        this.$closeBtn = $('<div class="btn">'+Craft.t('Close')+'</div>').appendTo(this.$buttons);

        this.$fieldSettings = this.$settingsContainer.appendTo($main);

        // Give the modal window some time to get it together
        setTimeout($.proxy(function() {
            Craft.initUiElements(this.$fieldSettings);
            Garnish.$bod.append(this.fieldTypeFootHtml);
        }, this), 1);

        this.addListener(this.$closeBtn, 'activate', 'closeModal');
    },

    restoreSettingsToTable: function() {

        // Special case for Matrix - reset field back to defaults, otherwise causes UI havok
        this.$fieldSettings.find('.matrixconfigitem.sel').removeClass('sel');
        this.$fieldSettings.find('.mc-sidebar.fields .col-inner-container').addClass('hidden');
        this.$fieldSettings.find('.field-settings .col-inner-container').addClass('hidden');
        this.$fieldSettings.find('.field-settings .col-inner-container .items div[data-id]').addClass('hidden');

        this.field.restoreSettingsHtml(this.$fieldSettings);
    },

    onFadeOut: function() {
        this.restoreSettingsToTable();

        this.destroy();
        this.$shade.remove();
        this.$container.remove();

        this.removeListener(this.$closeBtn, 'click');
        this.removeListener(this.$saveBtn, 'click');
    },

    closeModal: function() {
        this.hide();
    },

    saveSettings: function() {
        this.hide();
    },

    show: function() {
        this.base();
    },
});



})(jQuery);