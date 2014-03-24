/* Show user-info plugin script */

if (window.rcmail) {
	rcmail.addEventListener('init', function(evt) {
		var button = $('<a>').attr('href', rcmail.env.comm_path+'&_action=plugin.syno_admin').addClass('button-settings').attr('id', 'admin_setting');
		var text = $('<span>').addClass('button-inner').html(rcmail.gettext('mailstation.adminsettings')).appendTo(button);

		// TODO: use another way
		if (rcmail.env.action === 'plugin.syno_admin') {
			button.addClass('button-selected');
		}

		// add button and register command
		rcmail.add_element(button, 'taskbar');
		rcmail.register_command('plugin.syno_admin', function(){ 
			rcmail.goto_url('plugin.syno_admin');
		}, true);

		rcmail.register_command('plugin.admin_save', function() { rcmail.admin_save(); }, true);

		if (rcmail.env.action == 'plugin.syno_admin' || rcmail.env.action == 'plugin.admin-save') {
			if (rcmail.gui_objects.adminoptlist) {
				rcmail.adminopt_list = new rcube_list_widget(rcmail.gui_objects.adminoptlist, {multiselect:false, draggable:false, keyboard:false});
				rcmail.adminopt_list.addEventListener('select', function(e) { rcmail.load_admin_opt(e); });
				rcmail.adminopt_list.init();
				rcmail.adminopt_list.focus();

			}
		}
	})
}

rcube_webmail.prototype.admin_save = function()
{
	if (this.gui_objects.adminform) {
		var opt = this.env.admin_opt;
		switch (opt) {
			case 'smtp':
				if (!this.admin_smtp_validate(this.gui_objects.adminform)) {
					return;
				}
				break;
			case 'extmail':
				if (!this.admin_extmail_validate(this.gui_objects.adminform)) {
					return;
				}
				break;
			default:
				return;
		}

		this.gui_objects.adminform.submit();
	}
}

rcube_webmail.prototype.load_admin_opt = function(list)
{
	var id = list.get_single_selection();
	if (id != null) {
		if (this.env.contentframe && window.frames && window.frames[this.env.contentframe]) {
			target = window.frames[this.env.contentframe];
			target.location.href = this.env.comm_path+'&_action=plugin.syno_admin&_opt=' + id;
		} 
	}
}

rcube_webmail.prototype.admin_smtp_validate = function(form)
{
	var input;
	var attachment_limit;

	if ((attachment_limit = $("input[name='_attachment_limit']", form)) && attachment_limit.length && isNaN(parseInt(attachment_limit.val()))) {
		attachment_limit = 32;
	} else {
		attachment_limit = attachment_limit.val();
	}

	input = $("input[name='_smtp_port']", form);
	if (input.length && isNaN(parseInt(input.val()))) {
		alert(this.get_label('mailstation.no_smtpport'));
		input.focus();
		return false;
	}

	if (!this.syno_port_validate(input.val())) {
		alert(this.get_label('mailstation.no_smtpport'));
		input.focus();
		return false;
	}

	input = $("input[name='_attachment_size']", form);
	if (input.length && isNaN(parseInt(input.val()))) {
		alert(this.get_label('mailstation.no_attachmentsize'));
		input.focus();
		return false;
	}

	if (parseInt(input.val()) <= 0 || parseInt(input.val()) > attachment_limit) {
		// FIXME
		var msg = this.get_label('mailstation.bound_attachment_limit').replace('$num', attachment_limit + 'MB');
		alert(msg);
		input.focus();
		return false;
	}

	return true;
}

rcube_webmail.prototype.admin_extmail_validate = function(form)
{
	var input = $("input[name='_extmailperiod']", form);
	if (input.length && isNaN(parseInt(input.val()))) {
		alert(this.get_label('mailstation.no_extmail_period'));
		input.focus();
		return false;
	}

	if (!this.syno_is_int(input.val()) || input.val() <= 0) {
		alert(this.get_label('mailstation.no_extmail_period'));
		input.focus();
		return false;
	}

	return true;
}
