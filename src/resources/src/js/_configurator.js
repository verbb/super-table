if (typeof Craft.SuperTable === typeof undefined) {
    Craft.SuperTable = {};
}

(function($) {

Craft.SuperTable.Configurator = Garnish.Base.extend({
    fieldTypeInfo: null,

    inputNamePrefix: null,
    inputIdPrefix: null,
    fieldTypeSettingsNamespace: null,
    placeholderKey: null,

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

    _fieldTypeSettingsHtml: null,
    _cancelToken: null,
    _ignoreFailedRequest: false,

    init: function(id, fieldTypeInfo, inputNamePrefix, fieldTypeSettingsNamespace, placeholderKey) {
        this.fieldTypeSettingsNamespace = fieldTypeSettingsNamespace;
        this.fieldTypeInfo = fieldTypeInfo;
        this.id = id;
        this.placeholderKey = placeholderKey;

        this.inputNamePrefix = inputNamePrefix + '[blockTypes][' + this.id + ']';
        this.inputIdPrefix = Craft.formatInputId(this.inputNamePrefix);

        this.$container = $('#' + Craft.formatInputId(inputNamePrefix) + '-supertable-configurator:first .input:first');

        this.$fieldsColumnContainer = this.$container.children('.fields').children();
        this.$fieldSettingsColumnContainer = this.$container.children('.stc-settings').children();
        this.$fieldItemsOuterContainer = this.$fieldsColumnContainer.children('.field-items');
        this.$fieldSettingItemsContainer = this.$fieldSettingsColumnContainer.children('.field-items');

        this._fieldTypeSettingsHtml = {};
        
        this.$newFieldBtn = this.$fieldItemsOuterContainer.children('.btn');

        // Find the field items container if it exists, otherwise create it
        this.$fieldItemsContainer = this.$fieldItemsOuterContainer.children('[data-id="' + this.id + '"]:first');

        if (!this.$fieldItemsContainer.length) {
            this.$fieldItemsContainer = $('<div data-id="' + this.id + '"/>').insertBefore(this.$newFieldBtn);
        }

        // Find the field settings container if it exists, otherwise create it
        this.$fieldSettingsContainer = this.$fieldSettingItemsContainer.children('[data-id="' + this.id + '"]:first');

        if (!this.$fieldSettingsContainer.length) {
            this.$fieldSettingsContainer = $('<div data-id="' + this.id + '"/>').appendTo(this.$fieldSettingItemsContainer);
        }

        // Find the existing fields
        this.fields = {};

        var $fieldItems = this.$fieldItemsContainer.children();

        for (var i = 0; i < $fieldItems.length; i++) {
            var $fieldItem = $($fieldItems[i]),
                id = $fieldItem.data('id');

            this.fields[id] = new Craft.SuperTable.Field(this, this, $fieldItem);

            // Pre-select first field
            if (i == 0) {
                this.fields[id].select();
            }

            // Is this a new field?
            var newMatch = (typeof id === 'string' && id.match(/new(\d+)/));

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

                    field.$fieldSettingsContainer.appendTo(this.$fieldSettingsContainer);
                }
            }, this)
        });
    },

    getFieldTypeInfo: function(type) {
        for (var i = 0; i < this.fieldTypeInfo.length; i++) {
            if (this.fieldTypeInfo[i].type === type) {
                return this.fieldTypeInfo[i];
            }
        }
    },

    addField: function() {
        this.totalNewFields++;
        var id = 'new' + this.totalNewFields;

        var $item = $(
            '<div class="supertableconfigitem stci-field" data-id="' + id + '">' +
                '<div class="name"><em class="light">' + Craft.t('super-table', '(blank)') + '</em>&nbsp;</div>' +
                '<div class="handle code">&nbsp;</div>' +
                '<div class="actions">' +
                    '<a class="move icon" title="' + Craft.t('super-table', 'Reorder') + '"></a>' +
                '</div>' +
            '</div>'
        ).appendTo(this.$fieldItemsContainer);

        this.fields[id] = new Craft.SuperTable.Field(this, this, $item);
        this.fields[id].select();

        this.fieldSort.addItems($item);
    },

    getFieldTypeSettingsHtml: function(type) {
        return new Promise((resolve, reject) => {
            if (typeof this._fieldTypeSettingsHtml[type] !== 'undefined') {
                resolve(this._fieldTypeSettingsHtml[type]);
                return;
            }

            // Cancel the current request
            if (this._cancelToken) {
                this._ignoreFailedRequest = true;
                this._cancelToken.cancel();
                Garnish.requestAnimationFrame(() => {
                    this._ignoreFailedRequest = false;
                });
            }

            // Create a cancel token
            this._cancelToken = axios.CancelToken.source();

            Craft.sendActionRequest('POST', 'fields/render-settings', {
                cancelToken: this._cancelToken.token,
                data: {
                    type: type,
                    namespace: this.fieldTypeSettingsNamespace,
                }
            }).then(response => {
                this._fieldTypeSettingsHtml[type] = response.data;
                
                resolve(response.data);
            }).catch(() => {
                if (!this._ignoreFailedRequest) {
                    Craft.cp.displayError(Craft.t('app', 'A server error occurred.'));
                }
                reject();
            });
        });
    },

    // selfDestruct: function() {
    //     this.deselect();
    //     this.$item.remove();
    //     this.$fieldItemsContainer.remove();
    //     this.$fieldSettingsContainer.remove();

    //     this.configurator.blockTypes[this.id] = null;
    //     delete this.configurator.blockTypes[this.id];
    // },

});


Craft.SuperTable.Field = Garnish.Base.extend({
    configurator: null,
    blockType: null,
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

    init: function(configurator, blockType, $item) {
        this.configurator = configurator;
        this.blockType = blockType;
        this.$item = $item;
        this.id = this.$item.data('id');

        this.inputNamePrefix = this.blockType.inputNamePrefix + '[fields][' + this.id + ']';
        this.inputIdPrefix = this.blockType.inputIdPrefix + '-fields-' + this.id;

        this.initializedFieldTypeSettings = {};
        this.fieldTypeSettingsTemplates = {};

        this.$nameLabel = this.$item.children('.name');
        this.$handleLabel = this.$item.children('.handle');

        // Find the field settings container if it exists, otherwise create it
        this.$fieldSettingsContainer = this.blockType.$fieldSettingsContainer.children('[data-id="' + this.id + '"]:first');

        var isNew = (!this.$fieldSettingsContainer.length);

        if (isNew) {
            this.$fieldSettingsContainer = this.getDefaultFieldSettings().appendTo(this.blockType.$fieldSettingsContainer);
        }

        this.$nameInput = $('#' + this.inputIdPrefix + '-name');
        this.$handleInput = $('#' + this.inputIdPrefix + '-handle');
        this.$requiredCheckbox = $('#' + this.inputIdPrefix + '-required');
        this.$typeSelect = $('#' + this.inputIdPrefix + '-type');
        this.$translationSettingsContainer = $('#' + this.inputIdPrefix + '-translation-settings');
        this.$typeSettingsContainer = this.$fieldSettingsContainer.children('.fieldtype-settings:first');
        this.$deleteBtn = this.$fieldSettingsContainer.children('a.delete:first');

        if (isNew) {
            this.setFieldType('craft\\fields\\PlainText');
        } else {
            this.selectedFieldType = this.$typeSelect.val();
            this.initializedFieldTypeSettings[this.selectedFieldType] = this.$typeSettingsContainer.children();
        }

        if (!this.$handleInput.val()) {
            new Craft.HandleGenerator(this.$nameInput, this.$handleInput);
        }

        this.addListener(this.$item, 'click', 'select');
        this.addListener(this.$nameInput, 'input', 'updateNameLabel');
        this.addListener(this.$handleInput, 'input', 'updateHandleLabel');
        this.addListener(this.$requiredCheckbox, 'change', 'updateRequiredIcon');
        this.addListener(this.$typeSelect, 'change', 'onTypeSelectChange');
        this.addListener(this.$deleteBtn, 'click', 'confirmDelete');
    },

    select: function() {
        if (this.blockType.selectedField === this) {
            return;
        }

        if (this.blockType.selectedField) {
            this.blockType.selectedField.deselect();
        }

        this.configurator.$fieldSettingsColumnContainer.removeClass('hidden').trigger('resize');
        this.blockType.$fieldSettingsContainer.removeClass('hidden');
        this.$fieldSettingsContainer.removeClass('settings-hidden');
        this.$item.addClass('sel');
        this.blockType.selectedField = this;

        if (!Garnish.isMobileBrowser()) {
            setTimeout($.proxy(function() {
                this.$nameInput.focus();
            }, this), 100);
        }

        Garnish.$win.trigger('resize');
    },

    deselect: function() {
        this.$item.removeClass('sel');
        this.configurator.$fieldSettingsColumnContainer.addClass('hidden').trigger('resize');
        this.blockType.$fieldSettingsContainer.addClass('hidden');
        this.$fieldSettingsContainer.addClass('settings-hidden');
        this.blockType.selectedField = null;
    },

    updateNameLabel: function() {
        var val = this.$nameInput.val();
        this.$nameLabel.html((val ? Craft.escapeHtml(val) : '<em class="light">' + Craft.t('super-table', '(blank)') + '</em>') + '&nbsp;');
    },

    updateHandleLabel: function() {
        this.$handleLabel.html(Craft.escapeHtml(this.$handleInput.val()) + '&nbsp;');
    },

    updateRequiredIcon: function() {
        if (this.$requiredCheckbox.prop('checked')) {
            this.$nameLabel.addClass('required');
        }
        else {
            this.$nameLabel.removeClass('required');
        }
    },

    onTypeSelectChange: function() {
        this.setFieldType(this.$typeSelect.val());
    },

    setFieldType: function(type) {
        // Update the Translation Method settings
        Craft.updateTranslationMethodSettings(type, this.$translationSettingsContainer);

        if (this.selectedFieldType) {
            this.initializedFieldTypeSettings[this.selectedFieldType].detach();
        }

        this.selectedFieldType = type;
        this.$typeSelect.val(type);

        // Show a spinner
        this.$typeSettingsContainer.html('<div class="zilch"><div class="spinner"></div></div>');

        this.getFieldTypeSettings(type).then(({fresh, $settings, headHtml, bodyHtml}) => {
            this.$typeSettingsContainer.html('').append($settings);
            
            if (fresh) {
                Craft.initUiElements($settings);
                Craft.appendHeadHtml(headHtml);
                Craft.appendBodyHtml(bodyHtml);
            }

            // In case Firefox was sleeping on the job
            this.$typeSettingsContainer.trigger('resize');
        }).catch(() => {
            this.$typeSettingsContainer.html('');
        });
    },

    getFieldTypeSettings: function(type) {
        return new Promise((resolve, reject) => {
            if (typeof this.initializedFieldTypeSettings[type] !== 'undefined') {
                resolve({
                    fresh: false,
                    $settings: this.initializedFieldTypeSettings[type],
                });

                return;
            }

            this.configurator.getFieldTypeSettingsHtml(type).then(({settingsHtml, headHtml, bodyHtml}) => {
                settingsHtml = this.getParsedFieldTypeHtml(settingsHtml);
                headHtml = this.getParsedFieldTypeHtml(headHtml);
                bodyHtml = this.getParsedFieldTypeHtml(bodyHtml);
                let $settings = $('<div/>').html(settingsHtml);
                this.initializedFieldTypeSettings[type] = $settings;
                
                resolve({
                    fresh: true,
                    $settings: $settings,
                    headHtml: headHtml,
                    bodyHtml: bodyHtml,
                });
            }).catch($.noop);
        });
    },

    getParsedFieldTypeHtml: function(html) {
        if (typeof html === 'string') {
            console.log('ST Placeholder: ' + this.configurator.placeholderKey)
            html = html.replace(new RegExp(`__BLOCK_TYPE_${this.configurator.placeholderKey}__`, 'g'), this.blockType.id);
            html = html.replace(new RegExp(`__FIELD_${this.configurator.placeholderKey}__`, 'g'), this.id);
        }
        else {
            html = '';
        }

        return html;
    },

    getDefaultFieldSettings: function() {
        var $container = $('<div/>', {
            'data-id': this.id
        });

        Craft.ui.createTextField({
            label: Craft.t('super-table', 'Name'),
            id: this.inputIdPrefix + '-name',
            name: this.inputNamePrefix + '[name]'
        }).appendTo($container);

        Craft.ui.createTextField({
            label: Craft.t('super-table', 'Handle'),
            id: this.inputIdPrefix + '-handle',
            'class': 'code',
            name: this.inputNamePrefix + '[handle]',
            maxlength: 64,
            required: true
        }).appendTo($container);

        Craft.ui.createTextareaField({
            label: Craft.t('super-table', 'Instructions'),
            id: this.inputIdPrefix + '-instructions',
            'class': 'nicetext',
            name: this.inputNamePrefix + '[instructions]'
        }).appendTo($container);

        Craft.ui.createTextField({
            label: Craft.t('super-table', 'Column Width'),
            instructions: Craft.t('super-table', 'Please save this Super Table field first to edit its width.'),
            size: 8,
            disabled: true,
        }).appendTo($container);

        Craft.ui.createCheckboxField({
            label: Craft.t('super-table', 'This field is required'),
            id: this.inputIdPrefix + '-required',
            name: this.inputNamePrefix + '[required]'
        }).appendTo($container);

        Craft.ui.createCheckboxField({
            label: Craft.t('app', 'Use this field’s values as search keywords'),
            id: this.inputIdPrefix + '-searchable',
            name: this.inputNamePrefix + '[searchable]',
            checked: false,
        }).appendTo($container);

        var fieldTypeOptions = [];

        for (var i = 0; i < this.configurator.fieldTypeInfo.length; i++) {
            fieldTypeOptions.push({
                value: this.configurator.fieldTypeInfo[i].type,
                label: this.configurator.fieldTypeInfo[i].name
            });
        }

        Craft.ui.createSelectField({
            label: Craft.t('super-table', 'Field Type'),
            id: this.inputIdPrefix + '-type',
            name: this.inputNamePrefix + '[type]',
            options: fieldTypeOptions,
            value: 'craft\\fields\\PlainText'
        }).appendTo($container);

        if (Craft.isMultiSite) {
            var $translationSettingsContainer = $('<div/>', {
                id: this.inputIdPrefix + '-translation-settings'
            }).appendTo($container);

            Craft.ui.createSelectField({
                label: Craft.t('super-table', 'Translation Method'),
                id: this.inputIdPrefix + '-translation-method',
                name: this.inputNamePrefix + '[translationMethod]',
                options: [],
                value: 'none',
                toggle: true,
                targetPrefix: this.inputIdPrefix + '-translation-method-'
            }).appendTo($translationSettingsContainer);

            var $translationKeyFormatContainer = $('<div/>', {
                id: this.inputIdPrefix + '-translation-method-custom',
                'class': 'hidden'
            }).appendTo($translationSettingsContainer);

            Craft.ui.createTextField({
                label: Craft.t('super-table', 'Translation Key Format'),
                id: this.inputIdPrefix + '-translation-key-format',
                name: this.inputNamePrefix + '[translationKeyFormat]'
            }).appendTo($translationKeyFormatContainer);
        }

        $('<hr/>').appendTo($container);

        $('<div/>', {
            'class': 'fieldtype-settings'
        }).appendTo($container);

        $('<hr/>').appendTo($container);

        $('<a/>', {
            'class': 'error delete',
            text: Craft.t('super-table', 'Delete')
        }).appendTo($container);

        return $container;
    },

    confirmDelete: function() {
        if (confirm(Craft.t('super-table', 'Are you sure you want to delete this field?'))) {
            this.selfDestruct();
        }
    },

    selfDestruct: function() {
        this.deselect();
        this.$item.remove();
        this.$fieldSettingsContainer.remove();

        this.blockType.fields[this.id] = null;
        delete this.blockType.fields[this.id];
    }

});


})(jQuery);