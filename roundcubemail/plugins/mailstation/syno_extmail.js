/* Show user-info plugin script */

if (window.rcmail) {
	rcmail.addEventListener('init', function(evt) {

		var tab = $('<span>').attr('id', 'settingstabpluginextmail').addClass('tablink');
		var button = $('<a>').attr('href', rcmail.env.comm_path+'&_action=plugin.syno_extmail').html(rcmail.gettext('mailstation.extmail')).appendTo(tab);

		// TODO: use another way
		if (rcmail.env.action === 'plugin.syno_extmail') {
			tab.addClass('selected');
		}

		// add tab
		rcmail.add_element(tab, 'tabs');

		if (rcmail.gui_objects.extmailaccountlist) {
			rcmail.extmail_list = new rcube_list_widget(rcmail.gui_objects.extmailaccountlist, 
				{multiselect:false, draggable:false, keyboard:false});

			rcmail.extmail_list.addEventListener('select', function(e){ rcmail.extmail_select(e); }, true);

			rcmail.extmail_list.init();
			rcmail.extmail_list.focus();

		}

		rcmail.register_command('plugin.extmail_add', function() { rcmail.extmail_add(); }, true);
		rcmail.register_command('plugin.extmail_delete', function() { rcmail.extmail_delete(); }, false);
		rcmail.register_command('plugin.extmail_add_cancel', function() { rcmail.extmail_add_cancel(); }, true);

		rcmail.register_command('plugin.extmail_add_back', function() { rcmail.extmail_add_back(); }, true);
		rcmail.register_command('plugin.extmail_add_forwardb', function() { rcmail.extmail_add_forwardb();}, true);
		rcmail.register_command('plugin.extmail_add_save', function() { rcmail.extmail_add_save(); }, true);
		rcmail.register_command('plugin.extmail_edit_save', function() { rcmail.extmail_edit_save(); }, true);

	})
}

rcube_webmail.prototype.extmail_submit = function()
{
	if (this.gui_objects.extmailform) {
		this.gui_objects.extmailform.submit();
	}
}

rcube_webmail.prototype.toggle_newfolder = function(radio)
{
	if (radio.value == 0 ) {
		document.getElementById('rcmfd_new_folder').value='';
		document.getElementById('rcmfd_new_folder').disabled=true;
	} else {
		document.getElementById('rcmfd_new_folder').disabled=false;
	}
	this.env.newfolder = radio.value;
};

rcube_webmail.prototype.toggle_fetchpass = function(radio)
{
	document.getElementById('rcmfd_fetch_days').value=7;
	document.getElementById('rcmfd_fetch_days').disabled=(radio.value==0)?false:true;    
}

rcube_webmail.prototype.toggle_addsmtp = function(radio)
{
	var elems = new Array('rcmfd_smtpdesc', 'rcmfd_smtpserver', 'rcmfd_smtpport','rcmfd_smtpuser','rcmfd_smtppass' ,'rcmfd_iftls', 'rcmfd_ifdefault');
	var elem;
	for (var i=0; i<7 ; i++) {
		if (elem = document.getElementById(elems[i])) {
			if (elem.disabled = radio.value==0) {
				switch(i){
					case 0:
					case 1:
					case 2:
					case 3:
					case 4:
						elem.value = '';
						break;
					default:
						elem.checked = false;
						break;
				}
			} else if (i==2){
				elem.value = '25';
			}
		}
	}
	this.env.addsmtp = radio.value;
}

rcube_webmail.prototype.extmail_add = function()
{
	if (this.env.contentframe && window.frames && window.frames[this.env.contentframe]) {

		target = window.frames[this.env.contentframe];
		target.location.href = this.env.comm_path+'&_action=plugin.syno_extmail&_step=1&_act=add';
	}
};

rcube_webmail.prototype.extmail_delete = function()
{
	var id = this.extmail_list.get_single_selection();
	this.http_post('plugin.syno_extmail', '_act=delete&_id='+this.extmail_list.rows[id].uid);
};

rcube_webmail.prototype.extmail_add_cancel = function()
{
	location.href = this.env.comm_path+'&_action=plugin.syno_extmail&_act=cancel';	
};

rcube_webmail.prototype.extmail_add_back = function()
{
	this.gui_objects.extmailform._save.value = 'back';
	this.extmail_submit();
};

rcube_webmail.prototype.extmail_add_forwardb = function()
{
	var step = this.env.extmail_step;
	switch(step) {
		case 1:
			if (this.extmail_step1_validate(this.gui_objects.extmailform)) {
				var form = this.gui_objects.extmailform;
				this.http_post('plugin.extmail_pop_check', '_email=' + $("input[name='_email']", form).val()
						+ '&_server=' + $("input[name='_popserver']", form).val()
						+ '&_port=' + $("input[name='_popport']", form).val());
			}
			return;

		case 2:
			if (this.extmail_step2_validate(this.gui_objects.extmailform)) {
				break;
			}
			return;

		case 3:
			if (this.extmail_step3_validate(this.gui_objects.extmailform)) {
				break;
			}
			return;

		default:
			return;
	}

	this.gui_objects.extmailform._save.value = 'next';
	this.extmail_submit();
};

rcube_webmail.prototype.extmail_select = function(list)
{	
	var id = list.get_single_selection();
	if (null != id) {
		this.enable_command('plugin.extmail_delete', true);
		if (this.env.contentframe && window.frames && window.frames[this.env.contentframe]) {
			target = window.frames[this.env.contentframe];
			target.location.href = this.env.comm_path+'&_action=plugin.syno_extmail&_iid=' + id;
		}
	}
}

rcube_webmail.prototype.extmail_list_update = function(action, o)
{

	this.show_contentframe(false);

	switch (action) {
		case 'update':
			var list = this.extmail_list;
			var row = $('#rcmrow' + o.id);
        		$('td', row).text(o.name);
			list.clear_selection();
			list.init();
			break;
		case 'del':
			var list = this.extmail_list;
			list.remove_row(o.id);
			list.clear_selection();
			list.init();
			break;
		case 'add':
			var list = this.extmail_list;
			var row = $('<tr><td class="name"></td></tr>');
			$('td', row).html(o.name);
			row.attr('id', 'rcmrow'+o.id);
			list.insert_row(row.get(0));

			break;
	}
}

rcube_webmail.prototype.extmail_add_save = function()
{
	if (this.extmail_step4_validate(this.gui_objects.extmailform)) {
		this.gui_objects.extmailform.submit();
	}
}

rcube_webmail.prototype.extmail_edit_save = function()
{	
	//this.set_busy();
	if (this.gui_objects.extmailform && this.extmail_popinfo_validate(this.gui_objects.extmailform)) {
		var form = this.gui_objects.extmailform;
		this.http_post('plugin.extmail_email_check', '_email=' + $("input[name='_email']", form).val() + '&_iid=' + form._iid.value);
	}
}

rcube_webmail.prototype.extmail_popinfo_validate = function(form)
{
	var input;

	if ((input = $("input[name='_email']", form)) && input.length && !rcube_check_email(input.val())) {
		alert(this.get_label('noemailwarning'));
		input.focus();
		return false;
	}

	if ((input = $("input[name='_extusername']", form)) && input.length && input.val().length == 0) {
		alert(this.get_label('mailstation.no_ext_username'));
		input.focus();
		return false;
	}

	if ((input = $("input[name='_extpd']", form)) && input.length && input.val().length == 0) {
		alert(this.get_label('mailstation.no_ext_password'));
		input.focus();
		return false;
	}

	if ((input = $("input[name='_popserver']", form)) && input.length && input.val().length == 0) {
		alert(this.get_label('mailstation.no_pop_server'));
		input.focus();
		return false;
	}

	if ((input = $("input[name='_popport']", form)) && input.length && input.val().length == 0) {
		alert(this.get_label('mailstation.no_pop_port'));
		input.focus();
		return false;
	}

	if (!this.syno_port_validate(input.val())) {
		alert(this.get_label('mailstation.no_pop_port'));
		input.focus();
		return false;
	}

	return true;
}

rcube_webmail.prototype.extmail_step1_validate = function(form)
{
	return this.extmail_popinfo_validate(form);
}

rcube_webmail.prototype.extmail_step2_validate = function(form)
{
	var input = $("input[name='_use_newfolder']", form);
	var new_folder;
	if (input[1].checked) {
		if ((new_folder = $("input[name='_new_folder']", form)) && new_folder.length && new_folder.val().length == 0) {
			alert(this.get_label('mailstation.no_folder'));
			input.focus();
			return false;
		}
	}

	return true;
}

rcube_webmail.prototype.extmail_step3_validate = function(form)
{
	var input = $("input[name='_addsmtp']", form);
	if (input[0].checked) {
		return true;
	}

	if ((input = $("input[name='_smtpserver']", form)) && input.length && input.val().length == 0) {
		alert(this.get_label('mailstation.no_smtpserver'));
		input.focus();
		return false;
	}

	if ((input = $("input[name='_smtpport']", form)) && input.length && isNaN(parseInt(input.val()))) {
		alert(this.get_label('mailstation.no_smtpport'));
		input.focus();
		return false;
	}

	if (!this.syno_port_validate(input.val())) {
		alert(this.get_label('mailstation.no_smtpport'));
		input.focus();
		return false;
	}

	return true;
}


rcube_webmail.prototype.extmail_step4_validate = function(form)
{
	var input = $("input[name='_fetchpass']", form);
	var days;

	if (input[0].checked) {
		if ((days = $("input[name='_fetch_days']", form)) && days.length && isNaN(parseInt(days.val()))) {
			alert(this.get_label('mailstation.error_fetch_days'));
			input.focus();
			return false;
		}

		if (!this.syno_is_int(days.val())|| parseInt(days.val()) <= 0) {
			alert(this.get_label('mailstation.error_fetch_days'));
			input.focus();
			return false;
		}
	}

	return true;
}
