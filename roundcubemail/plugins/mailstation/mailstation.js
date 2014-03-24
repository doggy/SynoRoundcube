rcube_webmail.prototype.syno_is_int = function(num)
{
	return (num.toString().search(/^-?[0-9]+$/) == 0);
}

rcube_webmail.prototype.syno_port_validate = function(port)
{
	if (!port) {
		return false;
	}

	if (!this.syno_is_int(port)) {
		return false;
	}

	port = parseInt(port);
	if (port <= 0 || port > 65536) {
		return false;
	}

	return true;
}

rcube_webmail.prototype.show_alert = function(msg)
{
	alert(msg);
}

rcube_webmail.prototype.login_check_invalid_username = function()
{
	var v = document.getElementById("rcmloginuser").value;
	var usernameNotValid = /[\\\{\}\|\^\[\]\?\=\:\+\/\*\(\)\$\!"#%&',;<>@`~]/;
	var usernameVal = /^[^\-]/;
	var usernameNotspace = v.replace(/^\s+|\s+$/g, "");
	if((v.search(usernameNotValid) == -1) && usernameVal.test(v)
			&& (usernameNotspace != "") && (usernameNotspace.search(/\s/) == -1)) {
				return true;
			} else {
				alert(this.get_label('mailstation.invalid_username'));
				return false;
			}
	return true;

};

