

$(function() {

	$(document).on('click', '.supertable-configurator table.editable a.settings.icon', function(e) {
		e.preventDefault();

		var rowElement = $(this).parents('tr').data('id');
		var blockTypeId = $(this).parents('tr').data('blocktype');
		var fieldType = $(this).parents('tr').find('td.thin .select.small select').val();
		var settingsContainer = $(this).parents('tr').find('.settings-col .fieldtype-settings');
		
		$(this).parents('tr').find('.settings-col .fieldtype-settings').remove();

		new Craft.SuperTableSettingsModal(rowElement, blockTypeId, fieldType, settingsContainer);
    });
});


SuperTableSettingsModalsArray = [];

Craft.SuperTableSettingsModals = Garnish.Base.extend({
	init: function(fieldTypeInfo) {
		for (var i = 0; i < fieldTypeInfo.length; i++) {
			SuperTableSettingsModalsArray[fieldTypeInfo[i].type] = fieldTypeInfo[i];
		}
	},
});

Craft.SuperTableSettingsModal = Garnish.Modal.extend(
{
	fieldId: null,
	tableId: null,
	fieldType: null,

	rowElement: null,
	blockTypeId: null,
	settingsContainer: null,

	$body: null,
	$buttons: null,
	$closeBtn: null,
	$saveBtn: null,
	$footerSpinner: null,
	$fieldSettings: null,

	init: function(rowElement, blockTypeId, fieldType, settingsContainer)
	{
		this.rowElement = rowElement;
		this.blockTypeId = blockTypeId;
		this.settingsContainer = settingsContainer;

		// Build the modal
		var $container = $('<div class="modal fieldsettingsmodal"></div>').appendTo(Garnish.$bod),
			$body = $('<div class="body"></div>').appendTo($container),
			$content = $('<div class="content"></div>').appendTo($body),
			$main = $('<div class="main"></div>').appendTo($content),
			$footer = $('<div class="footer"/>').appendTo($container);

		this.base($container, this.settings);

		this.$footerSpinner = $('<div class="spinner hidden"/>').appendTo($footer);
		this.$buttons = $('<div class="buttons rightalign first"/>').appendTo($footer);
		this.$closeBtn = $('<div class="btn">'+Craft.t('Close')+'</div>').appendTo(this.$buttons);

		this.$fieldSettings = settingsContainer.appendTo($main);

		Craft.initUiElements(this.$fieldSettings);

		var footHtml = this.getParsedFieldTypeHtml(SuperTableSettingsModalsArray[fieldType].settingsFootHtml, blockTypeId, rowElement);
		Garnish.$bod.append(footHtml);

		this.addListener(this.$closeBtn, 'activate', 'closeModal');
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

	restoreSettingsToTable: function() {
		$div = this.$fieldSettings;

		$(document).find('.supertable-configurator table.editable tr[data-id="'+this.rowElement+'"] .settings-col').html($div);
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

	show: function()
	{
		this.base();
	},
});
