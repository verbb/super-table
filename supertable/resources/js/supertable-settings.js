

$(function() {

	$(document).on('click', '#SuperTable table.editable a.settings.icon', function(e) {
		e.preventDefault();

		var tableId = $(this).parents('table.editable').data('id');
		var fieldId = $(this).parents('tr').data('fieldid');
		var fieldType = $(this).parent().parent().find('select').val();

		var myModal = new Craft.FieldSettingsModal(fieldId, tableId, fieldType);
    });
});




Craft.FieldSettingsModal = Garnish.Modal.extend(
{
	fieldId: null,
	tableId: null,
	fieldType: null,

	$body: null,
	$buttons: null,
	$cancelBtn: null,
	$saveBtn: null,
	$footerSpinner: null,

	init: function(fieldId, tableId, fieldType, settings)
	{
		this.fieldId = fieldId;
		this.tableId = tableId;
		this.fieldType = fieldType;
		this.setSettings(settings, Craft.FieldSettingsModal.defaults);

		// Build the modal
		var $container = $('<div class="modal fieldsettingsmodal"></div>').appendTo(Garnish.$bod),
			$body = $('<div class="body"><div class="spinner big"></div></div>').appendTo($container),
			$footer = $('<div class="footer"/>').appendTo($container);

		this.base($container, this.settings);

		this.$footerSpinner = $('<div class="spinner hidden"/>').appendTo($footer);
		this.$buttons = $('<div class="buttons rightalign first"/>').appendTo($footer);
		this.$cancelBtn = $('<div class="btn">'+Craft.t('Cancel')+'</div>').appendTo(this.$buttons);
		this.$saveBtn = $('<div class="btn submit">'+Craft.t('Save')+'</div>').appendTo(this.$buttons);

		this.$body = $body;

		this.addListener(this.$cancelBtn, 'activate', 'onFadeOut');
		this.addListener(this.$saveBtn, 'activate', 'saveSettings');
	},

	onFadeIn: function()
	{
		var data = {
			fieldId: this.fieldId,
			tableId: this.tableId,
			fieldType: this.fieldType,
		};

		Craft.postActionRequest('superTable/getModalBody', data, $.proxy(function(response, textStatus) {
			if (textStatus == 'success') {
				this.$body.html(response);

				Craft.initUiElements(this.$body);
			}
		}, this));

		this.base();
	},

	onFadeOut: function() {
		this.hide();
		this.$container.empty();

		this.removeListener(this.$saveBtn, 'click');
		this.removeListener(this.$cancelBtn, 'click');
	},

	saveSettings: function()
	{
		var settingsForm = this.$body.find('form').serializeObject();

		var params = {
			fieldId: this.fieldId,
			tableId: this.tableId,
			fieldType: this.fieldType,
			settings: settingsForm
		};

		this.$footerSpinner.removeClass('hidden');

		Craft.postActionRequest('superTable/setSettingsFromModal', params, $.proxy(function(response, textStatus) {
			this.$footerSpinner.addClass('hidden');

			if (response.error) {
				$.each(response.error, function(index, value) {
					Craft.cp.displayError(value);
				});
			} else if (response.success) {
				Craft.cp.displayNotice(Craft.t('Settings updated.'));
			} else {
				Craft.cp.displayError(Craft.t('Could not update settings'));
			}

			this.hide();
			this.$container.empty();

		}, this));

		this.removeListener(this.$saveBtn, 'click');
		this.removeListener(this.$cancelBtn, 'click');
	},

	show: function()
	{
		this.base();
	},
},
{
	defaults: {

	}
});






(function($) {
    var methods = {
        setValue: function(path, value, obj) {
            if(path.length) {
                var attr = path.shift();
                if(attr) {
                    obj[attr] = methods.setValue(path, value, obj[attr] || {});
                    return obj;
                } else {
                    if(obj.push) {
                        obj.push(value);
                        return obj;
                    } else {
                        return [value];
                    }
                }
            } else {
                return value;
            }
        }
    };
    
    $.fn.serializeObject = function() {
        var obj     = {},
            params  = this.serializeArray(),
            path    = null;
            
        $.each(params, function() {
            path = this.name.replace(/\]/g, "").split(/\[/);
            methods.setValue(path, this.value, obj);
        });
        
        return obj;
    };
})(jQuery);