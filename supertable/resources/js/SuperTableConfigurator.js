(function($) {


Craft.SuperTableConfigurator = Garnish.Base.extend({
    fieldTypeInfo: null,

    inputNamePrefix: null,
    inputIdPrefix: null,

    blockTypeId: null,
    blockTypeNamePrefix: null,
    blockTypeIdPrefix: null,

    $container: null,

    $fieldsColumnContainer: null,
    $fieldSettingsColumnContainer: null,

    $fieldItemsContainer: null,
    $fieldSettingItemsContainer: null,

    $newFieldBtn: null,

    fields: null,
    selectedField: null,
    fieldSort: null,
    totalNewFields: 0,
    fieldSettings: null,

    init: function(id, idPrefix, fieldTypeInfo, inputNamePrefix) {
        this.fieldTypeInfo = fieldTypeInfo;

        this.blockTypeId = id;
        this.blockTypeNamePrefix = inputNamePrefix+'[blockTypes]['+this.blockTypeId+']';
        this.blockTypeIdPrefix = Craft.formatInputId(this.blockTypeNamePrefix);

        this.inputNamePrefix = inputNamePrefix;
        this.inputIdPrefix = Craft.formatInputId(this.inputNamePrefix);

        this.$container = $('#' + this.inputIdPrefix + '-supertable-configurator:first .input:first');

        this.$fieldsColumnContainer = this.$container.children('.fields').children();
        this.$fieldSettingsColumnContainer = this.$container.children('.stc-settings').children();
        this.$fieldItemsOuterContainer = this.$fieldsColumnContainer.children('.field-items');
        this.$fieldSettingItemsContainer = this.$fieldSettingsColumnContainer.children('.field-items');

        this.$newFieldBtn = this.$fieldItemsOuterContainer.children('.btn');

        // Find the field items container if it exists, otherwise create it
        this.$fieldItemsContainer = this.$fieldItemsOuterContainer.children('[data-id="'+this.blockTypeId+'"]:first');

        if (!this.$fieldItemsContainer.length) {
            this.$fieldItemsContainer = $('<div data-id="'+this.blockTypeId+'"/>').insertBefore(this.$newFieldBtn);
        }

        // Find the field settings container if it exists, otherwise create it
        this.$fieldSettingsContainer = this.$fieldSettingItemsContainer.children('[data-id="'+this.blockTypeId+'"]:first');

        if (!this.$fieldSettingsContainer.length) {
            this.$fieldSettingsContainer = $('<div data-id="'+this.blockTypeId+'"/>').appendTo(this.$fieldSettingItemsContainer);
        }

        // Find the existing fields
        this.fields = {};

        var $fieldItems = this.$fieldItemsContainer.children();

        for (var i = 0; i < $fieldItems.length; i++) {
            var $fieldItem = $($fieldItems[i]),
                id = $fieldItem.data('id');

            this.fields[id] = new Craft.SuperTableField(this, $fieldItem);

            // Pre-select first field
            if (i == 0) {
                this.selectedField = this.fields[id];
            }

            // Is this a new field?
            var newMatch = (typeof id == 'string' && id.match(/new(\d+)/));

            if (newMatch && newMatch[1] > this.totalNewFields) {
                this.totalNewFields = parseInt(newMatch[1]);
            }
        }

        this.addListener(this.$newFieldBtn, 'click', 'addField');

        this.fieldSort = new Garnish.DragSort($fieldItems, {
            handle: '.move',
            axis: 'y',
            onSortChange: $.proxy(function() {
                // Adjust the field setting containers to match the new sort order
                for (var i = 0; i < this.fieldSort.$items.length; i++) {
                    var $item = $(this.fieldSort.$items[i]),
                        id = $item.data('id'),
                        field = this.fields[id];

                    if (field) {
                        field.$fieldSettingsContainer.appendTo(this.$fieldSettingsContainer);
                    }
                }
            }, this)
        });
    },

    getFieldTypeInfo: function(type) {
        for (var i = 0; i < this.fieldTypeInfo.length; i++) {
            if (this.fieldTypeInfo[i].type == type) {
                return this.fieldTypeInfo[i];
            }
        }
    },

    addField: function() {
        this.totalNewFields++;
        var id = 'new'+this.totalNewFields;

        var $item = $(
            '<div class="supertableconfigitem stci-field" data-id="'+id+'">' +
                '<div class="name"><em class="light">'+Craft.t('(blank)')+'</em>&nbsp;</div>' +
                '<div class="handle code">&nbsp;</div>' +
                '<div class="actions">' +
                    '<a class="move icon" title="'+Craft.t('Reorder')+'"></a>' +
                '</div>' +
            '</div>'
        ).appendTo(this.$fieldItemsContainer);

        this.fields[id] = new Craft.SuperTableField(this, $item);
        this.fields[id].select();

        this.fieldSort.addItems($item);
    },

});


Craft.SuperTableField = Garnish.Base.extend({
    configurator: null,
    id: null,

    inputNamePrefix: null,
    inputIdPrefix: null,

    selectedFieldType: null,
    initializedFieldTypeSettings: null,

    $item: null,
    $nameLabel: null,
    $handleLabel: null,

    $fieldSettingsContainer: null,
    $nameInput: null,
    $handleInput: null,
    $requiredCheckbox: null,
    $typeSelect: null,
    $typeSettingsContainer: null,
    $deleteBtn: null,

    init: function(configurator, $item) {
        this.configurator = configurator;
        this.$item = $item;
        this.id = this.$item.data('id');

        this.inputNamePrefix = this.configurator.blockTypeNamePrefix+'[fields]['+this.id+']';
        this.inputIdPrefix = this.configurator.blockTypeIdPrefix+'-fields-'+this.id;

        this.initializedFieldTypeSettings = {};

        this.$nameLabel = this.$item.children('.name');
        this.$handleLabel = this.$item.children('.handle');

        // Find the field settings container if it exists, otherwise create it
        this.$fieldSettingsContainer = this.configurator.$fieldSettingsContainer.children('[data-id="'+this.id+'"]:first');

        var isNew = (!this.$fieldSettingsContainer.length);

        if (isNew) {
            this.$fieldSettingsContainer = $(this.getDefaultFieldSettingsHtml()).appendTo(this.configurator.$fieldSettingsContainer);
        }

        this.$nameInput = this.$fieldSettingsContainer.find('input[name$="[name]"]:first');
        this.$handleInput = this.$fieldSettingsContainer.find('input[name$="[handle]"]:first');
        this.$requiredCheckbox = this.$fieldSettingsContainer.find('input[type="checkbox"][name$="[required]"]:first');
        this.$typeSelect = this.$fieldSettingsContainer.find('select[name$="[type]"]:first');
        this.$typeSettingsContainer = this.$fieldSettingsContainer.children('.fieldtype-settings:first');
        this.$deleteBtn = this.$fieldSettingsContainer.children('a.delete:first');

        if (isNew) {
            this.setFieldType('PlainText');
        } else {
            this.selectedFieldType = this.$typeSelect.val();
            this.initializedFieldTypeSettings[this.selectedFieldType] = this.$typeSettingsContainer.children();

            //this.setFieldType(this.selectedFieldType);
        }

        if (!this.$handleInput.val()) {
            new Craft.HandleGenerator(this.$nameInput, this.$handleInput);
        }

        this.addListener(this.$item, 'click', 'select');
        this.addListener(this.$nameInput, 'textchange', 'updateNameLabel');
        this.addListener(this.$handleInput, 'textchange', 'updateHandleLabel');
        this.addListener(this.$requiredCheckbox, 'change', 'updateRequiredIcon');
        this.addListener(this.$typeSelect, 'change', 'onTypeSelectChange');
        this.addListener(this.$deleteBtn, 'click', 'confirmDelete');
    },

    select: function() {
        if (this.configurator.selectedField == this) {
            return;
        }

        if (this.configurator.selectedField) {
            this.configurator.selectedField.deselect();
        }

        this.configurator.$fieldSettingsContainer.removeClass('hidden');
        this.$fieldSettingsContainer.removeClass('hidden');
        this.$item.addClass('sel');
        this.configurator.selectedField = this;

        this.setFieldType(this.selectedFieldType);

        if (!Garnish.isMobileBrowser()) {
            setTimeout($.proxy(function() {
                this.$nameInput.focus()
            }, this), 100);
        }
    },

    deselect: function() {
        this.$item.removeClass('sel');
        this.configurator.$fieldSettingsContainer.addClass('hidden');
        this.$fieldSettingsContainer.addClass('hidden');
        this.configurator.selectedField = null;
    },

    updateNameLabel: function() {
        var val = this.$nameInput.val();
        this.$nameLabel.html((val ? Craft.escapeHtml(val) : '<em class="light">'+Craft.t('(blank)')+'</em>')+'&nbsp;');
    },

    updateHandleLabel: function() {
        this.$handleLabel.html(Craft.escapeHtml(this.$handleInput.val())+'&nbsp;');
    },

    updateRequiredIcon: function() {
        if (this.$requiredCheckbox.prop('checked')) {
            this.$nameLabel.addClass('required');
        } else {
            this.$nameLabel.removeClass('required');
        }
    },

    onTypeSelectChange: function() {
        this.setFieldType(this.$typeSelect.val());
    },

    setFieldType: function(type) {
        if (this.selectedFieldType) {
            this.initializedFieldTypeSettings[this.selectedFieldType].detach();
        }

        this.selectedFieldType = type;
        this.$typeSelect.val(type);

        var firstTime = (typeof this.initializedFieldTypeSettings[type] == 'undefined');

        if (firstTime) {
            var info = this.configurator.getFieldTypeInfo(type),
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

        // Firefox might have been sleeping on the job.
        this.$typeSettingsContainer.trigger('resize');
    },

    getParsedFieldTypeHtml: function(html) {
        var newHtml = html;

        if (typeof newHtml == 'string') {
            newHtml = newHtml.replace(/__BLOCK_TYPE_ST__/g, this.configurator.blockTypeId);
            newHtml = newHtml.replace(/__FIELD_ST__/g, this.id);
        } else {
            newHtml = '';
        }

        return newHtml;
    },

    getDefaultFieldSettingsHtml: function() {
        var html = '<div data-id="'+this.id+'">' +
            '<div class="field" id="'+this.inputIdPrefix+'-name-field">' +
                '<div class="heading">' +
                    '<label class="required" for="'+this.inputIdPrefix+'-name">'+Craft.t('Name')+'</label>' +
                    '<div class="instructions"><p>'+Craft.t('What this field will be called in the CP.')+'</p></div>' +
                '</div>' +
                '<div class="input">' +
                    '<input class="text fullwidth" type="text" id="'+this.inputIdPrefix+'-name" name="'+this.inputNamePrefix+'[name]" autofocus="" autocomplete="off"/>' +
                '</div>' +
            '</div>' +
            '<div class="field" id="'+this.inputIdPrefix+'-handle-field">' +
                '<div class="heading">' +
                    '<label class="required" for="'+this.inputIdPrefix+'-handle">'+Craft.t('Handle')+'</label>' +
                    '<div class="instructions"><p>'+Craft.t('How you’ll refer to this field in the templates.')+'</p></div>' + 
                '</div>' +
                '<div class="input">' +
                    '<input class="text fullwidth code" type="text" id="'+this.inputIdPrefix+'-handle" name="'+this.inputNamePrefix+'[handle]" autofocus="" autocomplete="off"/>' +
                '</div>' +
            '</div>' +
            '<div class="field" id="'+this.inputIdPrefix+'-instructions-field">' +
                '<div class="heading">' +
                    '<label for="'+this.inputIdPrefix+'-instructions">'+Craft.t('Instructions')+'</label>' +
                    '<div class="instructions"><p>'+Craft.t('Helper text to guide the author. Will appear as a small <code>info</code> icon in table headers.')+'</p></div>' +
                '</div>' +
                '<div class="input">' +
                    '<textarea class="text nicetext fullwidth" rows="2" cols="50" id="'+this.inputIdPrefix+'-instructions" name="'+this.inputNamePrefix+'[instructions]"></textarea>' +
                '</div>' +
            '</div>' +
            '<div class="field" id="'+this.inputIdPrefix+'-width-field">' +
                '<div class="heading">' +
                    '<label for="'+this.inputIdPrefix+'-width">'+Craft.t('Column Width')+'</label>' +
                    '<div class="instructions"><p>'+Craft.t('Only applies for Table Layout. Set the width for this column in either pixels or percentage. i.e. <code>10px</code> or <code>10%</code>.')+'</p></div>' +
                '</div>' +
                '<div class="input ltr">' +
                    '<input class="text" type="text" id="'+this.inputIdPrefix+'-width" size="4" name="'+this.inputNamePrefix+'[width]" autocomplete="off">' +
                '</div>' +
            '</div>' +
            '<div class="field checkboxfield">' +
                '<label>' +
                    '<input type="hidden" name="'+this.inputNamePrefix+'[required]" value=""/>' +
                    '<input type="checkbox" value="1" name="'+this.inputNamePrefix+'[required]"/> ' +
                    Craft.t('This field is required') +
                '</label>' +
            '</div>';

            if (Craft.isLocalized) {
                html += '<div class="field checkboxfield">' +
                    '<label>' +
                        '<input type="hidden" name="'+this.inputNamePrefix+'[translatable]" value=""/>' +
                        '<input type="checkbox" value="1" name="'+this.inputNamePrefix+'[translatable]"/> ' +
                        Craft.t('This field is translatable') +
                    '</label>' +
                '</div>';
            }

            html += '<hr/>' +
                '<div class="field" id="type-field">' +
                    '<div class="heading">' +
                        '<label for="type">'+Craft.t('Field Type')+'</label>' +
                    '</div>' +
                    '<div class="input">' +
                        '<div class="select">' +
                            '<select id="type" class="fieldtoggle" name="'+this.inputNamePrefix+'[type]">';
                                for (var i = 0; i < this.configurator.fieldTypeInfo.length; i++) {
                                    var info = this.configurator.fieldTypeInfo[i],
                                        selected = (info.type == 'PlainText');

                                    html += '<option value="'+info.type+'"'+(selected ? ' selected=""' : '')+'>'+info.name+'</option>';
                                }

                                html +=
                        '</select>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="fieldtype-settings"/>' +
            '<hr/>' +
            '<a class="error delete">'+Craft.t('Delete')+'</a>' +
        '</div>';

        return html;
    },

    confirmDelete: function() {
        if (confirm(Craft.t('Are you sure you want to delete this field?'))) {
            this.selfDestruct();
        }
    },

    selfDestruct: function() {
        this.deselect();
        this.$item.remove();
        this.$fieldSettingsContainer.remove();

        this.configurator.fields[this.id] = null;
        delete this.configurator.fields[this.id];
    }

});


})(jQuery);