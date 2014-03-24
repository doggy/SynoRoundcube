/* Show user-info plugin script */

if (window.rcmail) {
	rcmail.addEventListener('init', function(evt) {

		var tab = $('<span>').attr('id', 'settingstabpluginsmtp').addClass('tablink');
		var button = $('<a>').attr('href', rcmail.env.comm_path+'&_action=plugin.syno_smtp').html(rcmail.gettext('mailstation.smtpsettings')).appendTo(tab);

		// TODO: use another way
		if (rcmail.env.action === 'plugin.syno_smtp') {
			tab.addClass('selected');
		}

		rcmail.add_element(tab, 'tabs');

		if (rcmail.gui_objects.smtplist) {
			rcmail.smtp_list = new rcube_list_widget(rcmail.gui_objects.smtplist, 
				{multiselect:false, draggable:false, keyboard:false});
			rcmail.smtp_list.addEventListener('select', function(e){ rcmail.smtp_list_select(e); }, true);

			rcmail.smtp_list.init();
			rcmail.smtp_list.focus();

		}

		rcmail.register_command('plugin.smtp_save', function() { rcmail.smtp_save(); }, true);
		rcmail.register_command('plugin.smtp_add', function() { rcmail.smtp_add(); }, true);
		rcmail.register_command('plugin.smtp_delete', function() { rcmail.smtp_delete(); }, false);

	})
}

rcube_webmail.prototype.toggle_smtp_select = function(select)
{
	if (select.value){
		location.href = this.env.comm_path+'&_action=plugin.syno_smtp&_sid='+select.value;
	}
};

rcube_webmail.prototype.smtp_save = function()
{
	if (this.gui_objects.smtpform && this.smtp_validate(this.gui_objects.smtpform)) {
		this.gui_objects.smtpform.submit();
	}
}

rcube_webmail.prototype.smtp_delete = function()
{
	if (this.gui_objects.smtpform) {
		this.gui_objects.smtpform._action.value = 'plugin.smtp_delete';
		this.gui_objects.smtpform.submit();
	}
}

rcube_webmail.prototype.smtp_validate = function(form)
{
	var input;

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

rcube_webmail.prototype.smtp_list_select = function(list)
{	
	var id = list.get_single_selection();
	if (null != id) {
		if (this.env.contentframe && window.frames && window.frames[this.env.contentframe]) {
			target = window.frames[this.env.contentframe];
			target.location.href = this.env.comm_path+'&_action=plugin.syno_smtp&_sid=' + id;
		}
	}
}

rcube_webmail.prototype.smtp_add = function()
{	
	if (this.env.contentframe && window.frames && window.frames[this.env.contentframe]) {
		target = window.frames[this.env.contentframe];
		target.location.href = this.env.comm_path+'&_action=plugin.syno_smtp&_sid=' + 0;
	}

}

rcube_webmail.prototype.smtp_delete = function()
{
	var id = this.smtp_list.get_single_selection();

	this.http_post('plugin.syno_smtp', '_act=delete&_sid='+this.smtp_list.rows[id].uid);

	this.enable_command('plugin.smtp_delete', false);
}

rcube_webmail.prototype.smtp_list_update = function(action, o)
{

	this.show_contentframe(false);

	switch (action) {
		case 'update':
			var list = this.smtp_list;
			var row = $('#rcmrow' + o.id);
        		$('td', row).text(o.name);
			list.clear_selection();
			break;
		case 'del':
			var list = this.smtp_list;
			list.remove_row(o.id);
			list.clear_selection();
			list.init();
			break;
		case 'add':
			var list = this.smtp_list;
			var row = $('<tr><td class="name"></td></tr>');
			$('td', row).html(o.name);
			row.attr('id', 'rcmrow'+o.id);
			list.insert_row(row.get(0));
			break;
	}
}

