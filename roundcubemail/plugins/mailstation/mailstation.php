<?php

/**
 * Roundcube plugin to allow setting vacation messages and changing password on a
 * qmailadmin backend.
 *
 * Version 1.0.1-dev
 * Copyright (c) 2011 David C A Croft.
 *
 * This work is licensed under the Creative Commons Attribution-ShareAlike 3.0 Unported License.
 * To view a copy of this license, visit http://creativecommons.org/licenses/by-sa/3.0/
 * or send a letter to Creative Commons, 444 Castro Street, Suite 900, Mountain View,
 * California 94140, USA.
 */

class mailstation extends rcube_plugin
{

	private $mail_conf;
	private $create_user = false;

	function init()
	{

		if (!defined('RCMAIL_EXT_DIR')) {
			define('RCMAIL_EXT_DIR', INSTALL_PATH . 'ext');
		}

		if (!defined('RCMAIL_PROC_EXEC')) {
			define('RCMAIL_PROC_EXEC', '/var/packages/MailStation/target/bin/procmail');
		}

		if (!defined('RCMAIL_FETCH_EXEC')) {
			define('RCMAIL_FETCH_EXEC', '/usr/syno/bin/synofetch');
		}

		$this->add_texts('localization/', true);
		$this->rc = rcmail::get_instance();
		$this->db = rcmail::get_instance()->get_dbh();
		$this->user = rcmail::get_instance()->user;
		$this->userID = rcmail::get_instance()->user->ID;
	
		$mail_conf = $this->getMailServerConf();

		// smtp
		$this->register_action('plugin.syno_smtp', array($this, 'smtp_actions'));
		$this->register_action('plugin.smtp_save', array($this, 'smtp_save'));
		$this->register_action('plugin.smtp_delete', array($this, 'smtp_delete'));


		$this->add_hook('login_after', array($this, 'login_after'));
		$this->add_hook('user_create', array($this, 'user_create'));
		$this->add_hook('ready', array($this, 'task_change'));
		$this->add_hook('identity_delete', array($this, 'identity_delete'));
		$this->add_hook('storage_connect', array($this, 'imap_connect'));

		$this->add_hook('smtp_connect', array($this, 'before_connect'));
		$this->add_hook('message_before_send', array($this, 'before_send'));

		// admin
		$this->register_action('plugin.syno_admin', array($this, 'admin_actions'));
		$this->register_action('plugin.admin_save', array($this, 'admin_save'));

		// extmail
		$this->register_action('plugin.syno_extmail', array($this, 'extmail_actions'));
		$this->register_action('plugin.extmail_add', array($this, 'extmail_add'));	
		$this->register_action('plugin.extmail_step', array($this, 'extmail_step'));	
		$this->register_action('plugin.extmail_email_check', array($this, 'extmail_email_check'));
		$this->register_action('plugin.extmail_pop_check', array($this, 'extmail_pop_check'));

		$this->include_script('mailstation.js');
		$this->init_admin_ui();
		if ($this->rc->task == 'settings') {
			$this->init_smtp_ui();
			$this->init_extmail_ui();
		}
	}
	
	function task_change($arg)
	{
		if ($arg['action'] == 'plugin.syno_admin') {
			$arg['task'] = 'admin';
		}

		return $arg;
	}

	function imap_connect($arg)
	{
		if (!isset($this->mail_conf)) {
			$this->mail_conf = $this->getMailServerConf();
		}

		if ('local' != $this->mail_conf['domain_name']) {	
			if('ldap' == $this->mail_conf["account_type"]) {
				if (strpos($arg['user'], '@')) {
					list($arg['user'], $dump) = explode('@', $arg['user']);
				}
			} else if ('win' == $this->mail_conf["account_type"] ) {
				if (strpos($arg['user'], "\\")) {
					syslog(LOG_ERR, 'split: ');
					list($dump, $arg['user']) = explode("\\", $arg['user']);
				}
			}
		}

		return $arg;
	}

	function user_create($arg)
	{
		$this->create_user = true;
		if (!isset($this->mail_conf)) {
			$this->mail_conf = $this->getMailServerConf();
		}

		if ('local' != $this->mail_conf['domain_name']) {	
			if('ldap' == $this->mail_conf["account_type"]) {
				$retval['user'] = $arg['user'] . '@' . $this->mail_conf['domain_name'];
				$retval['user_name'] = $arg['user'];
			} else if ('win' == $this->mail_conf["account_type"] ) {
				$retval['user'] = $this->mail_conf['domain_name'] . "\\" . $arg['user'];
				$retval['user_name'] = $arg['user'];
			}

			$retval['user_email'] = $arg['user'] . '@' . $this->rc->config->mail_domain($arg['host']);
		}

		return $retval;
	}

	function login_after()
	{
		$this->userID = rcmail::get_instance()->user->ID;

		if ($this->create_user) {
			$this->after_create_user($this->userID);
		}

		if ($this->userID && !$this->list_smtp()) {
			$this->create_default_smtp($this->userID);
		}
	}

	function before_send($argv)
	{
		$identity = $this->get_identity_by_email($argv['from']);
		$this->sender_id = $identity['identity_id'];
	}

	function before_connect($arg)
	{
		$popinfo = $this->get_popinfo($this->sender_id);
		$reveal_smtp = isset($popinfo['select_smtp'])? $this->get_smtp($popinfo['select_smtp']): $this->get_smtp();

		$retval = $arg;
		$arg['smtp_server'] = $reveal_smtp['iftls']? 'tls://' . $reveal_smtp['smtpserver'] : $reveal_smtp['smtpserver'];
		$arg['smtp_port'] = $reveal_smtp['smtpport'];
		$arg['smtp_user'] = $reveal_smtp['smtpuser'];
		$arg['smtp_pass'] = $reveal_smtp['smtppass'];

		return $arg;
	}

	function init_smtp_ui()
	{
		if ($this->smtp_ui_initialized)
			return;

		$this->include_script('syno_smtp.js');
		$this->smtp_ui_initialized = true;
	}

	function init_admin_ui()
	{
		if (!$this->Is_AdminGroup()) {
			return;
		}

		if ($this->admin_ui_initialized)
			return;

		$this->include_script('syno_admin.js');
		$this->admin_ui_initialized = true;
	}

	function init_extmail_ui()
	{

		if (!$this->rc->config->get('extmailallow')) {
			return;
		}

		if ($this->extmail_ui_initialized)
			return;

		$this->include_script('syno_extmail.js');

		$this->extmail_ui_initialized = true;
	}

	function smtp_actions()
	{
		$this->init_smtp_ui();

		if ($action = get_input_value('_act', RCUBE_INPUT_GPC)) {	
			if ($action == 'delete') {
				$sid = get_input_value('_sid', RCUBE_INPUT_GPC);
				if ($this->smtp_delete($sid)) {	
					$this->rc->output->command('parent.smtp_list_update', 'del' , array('name' => '', 'id' => $sid));
				}
			}
		}
		$this->smtp_start();

		$this->smtp_send();

	}

	function admin_actions()
	{
		$this->init_admin_ui();

		$this->admin_start();

		$this->admin_send();

	}

	function extmail_actions()
	{
		$this->init_extmail_ui();

		if ($action = get_input_value('_act', RCUBE_INPUT_GPC)) {
			if ($action == 'add') {
				$this->clean_session();
				$this->init_session(); 
			} else if ($action == 'edit') {
				$this->extmail_edit_save();
			} else if ($action == 'cancel') {
				$this->rc->output->command('parent.extmail_list_update', 'del' , array('name' => '', 'id' => $iid));
				$this->clean_session();
			} else if($action == 'delete') {
				$iid = get_input_value('_id', RCUBE_INPUT_GPC);
				if ($iid = $this->extmail_delete($iid)) {
					$this->rc->output->command('parent.extmail_list_update', 'del' , array('name' => '', 'id' => $iid));
				}
			}
		}

		$this->extmail_start();
		$this->extmail_send();
	}

	function smtp_start()
	{	
		$this->rc->output->add_handlers(array(
			'smtpframe' => array($this, 'blank_frame'),
			'smtpeditform' => array($this, 'smtp_form'),
			'smtplist' => array($this, 'smtp_list'),
		));
	}

	function admin_start()
	{
		$this->rc->output->add_handlers(array(
			'adminframe' => array($this, 'blank_frame'),
			'adminoptlist' => array($this, 'admin_opt_list'),
			'adminform' => array($this, 'admin_form'),
		));
	}

	function extmail_start()
	{
		$this->rc->output->add_handlers(array(
			'extmailframe' => array($this, 'blank_frame'),
			'extmailaddform' => array($this, 'extmail_add_form'),
			'extmaileditform' => array($this, 'extmail_edit'),
			'extmailaccountlist' => array($this, 'rcmail_extaccount_list'),
			'extmailsteptitle' => array($this, 'extmail_step_title')
		));
	}

	function extmail_send()
	{ 
		if ('cancel' == get_input_value('_act', RCUBE_INPUT_GPC)) {
			$this->rc->output->send('iframe');
		}

		// Handle form action
		if (isset($_GET['_iid']) || isset($_POST['_iid'])) {
			//$this->rc->output->send('syno_extmail.extmailedit');
			$this->rc->output->send('mailstation.extmailedit');
		} else if(isset($_GET['_step']) || isset($_POST['_step'])){
			//$this->rc->output->send('syno_extmail.extmailadd');
			$this->rc->output->send('mailstation.extmailadd');
		} else {
			$this->rc->output->send('mailstation.extmail');
		}
	}

	function smtp_send()
	{
		if (isset($_GET['_sid']) || isset($_POST['_sid'])) {
			$this->rc->output->send('mailstation.smtpedit');
		} else {
			$this->rc->output->send('mailstation.smtp');
		}
	}

	function admin_send()
	{
		if (isset($_GET['_opt']) || isset($_POST['_opt'])) {
			$this->rc->output->send('mailstation.admin_edit');
		} else {
			$this->rc->output->send('mailstation.admin');
		}

	} 

	function blank_frame($attrib)
	{
		$rcmail = rcmail::get_instance();
		if (!$attrib['id'])
			$attrib['id'] = 'rcmblankframe';

		$attrib['name'] = $attrib['id'];

		$rcmail->output->set_env('contentframe', $attrib['name']);
		$rcmail->output->set_env('blankpage', $attrib['src'] ?
			$rcmail->output->abs_url($attrib['src']) : 'program/resources/blank.gif');

		return $rcmail->output->frame($attrib);
	}

	function smtp_list($attrib)
	{
		// add id to message list table if not specified
		if (!strlen($attrib['id']))
			$attrib['id'] = 'rcmsmtpList';


		$list= $this->list_smtp();

		foreach ($list as $idx => $row) {
			if (!$list[$idx]['ifdefault']) {
				continue;
			}
			$list[$idx]['smtpserver'] = trim($row['smtpserver'] . ' ('. rcube_label('mailstation.default'). ') ');
		}
		// get all identites from DB and define list of cols to be displayed
		$plugin = array('list' => $list, 'cols' => array('smtpserver'));

		// @TODO: use <UL> instead of <TABLE> for identities list
		// create XHTML table
		$out = rcube_table_output($attrib, $plugin['list'], $plugin['cols'], 'smtp_id');

		// set client env
		//
		$this->rc->output->include_script('list.js');
		$this->rc->output->add_gui_object('smtplist', $attrib['id']);

		return $out;
	}

	function smtp_form($attrib)
	{
		if (!$attrib['id'])
			$attrib['id'] = 'rcmsmtpform';

		$smtp_id = (isset($_GET['_sid']) || isset($_POST['_sid']))?get_input_value('_sid', RCUBE_INPUT_GET):0;
		$reveal_smtp = $this->get_smtp($smtp_id);

		$this->rc->output->command('parent.enable_command', 'plugin.smtp_delete', ($smtp_id != 0 && !$reveal_smtp['ifdefault']));
			
		$out = '<form name="smtp_form" action="./" method="post">'."\n";
		$hiddenfields = new html_hiddenfield(array('name' => '_action', 'value' => 'plugin.smtp_save'));
		$hiddenfields->add(array('name' => '_sid', 'value' => $smtp_id));
		$hiddenfields->add(array('name' => '_task', 'value' => $this->rc->task));
		$hiddenfields->add(array('name' => '_framed', 'value' => 1));
		$hiddenfields->add(array('name' => '_isdefault', 'value' => $smtp_id==0?false:$reveal_smtp['ifdefault']));
		$out .= $hiddenfields->show();

		$blocks = array('main' => array('name' => Q(rcube_label('mainoptions'))),);

		$field_id = 'rcmfd_smtpdesc';
		$smtpdesc = new html_inputfield(array('type' => 'text', 'name'=>'_smtpdesc', 'id'=>$field_id, 'size'=>'40'));

		$blocks['main']['options']['smtpdesc'] = array(
			'title' => html::label($field_id,Q($this->gettext('smtpdesc'))),
			'content'  => $smtpdesc->show($smtp_id==0?'':$reveal_smtp['smtpdesc'])
		);

		$field_id = 'rcmfd_smtpserver';
		$smtpserver = new html_inputfield(array('type' =>'text', 'name'=>'_smtpserver', 'id'=>$field_id, 'size'=>'40'));

		$blocks['main']['options']['smtpserver'] = array(
			'title' => html::label($field_id,Q($this->gettext('smtpserver'))),
			'content'  => $smtpserver->show($smtp_id==0?'':$reveal_smtp['smtpserver'])
		);

		$field_id = 'rcmfd_smtpport';
		$smtpport = new  html_inputfield(array('type'=>'text', 'name'=>'_smtpport', 'id'=>$field_id, 'size'=>'40'));

		$blocks['main']['options']['smtpport'] = array(
			'title' => html::label($field_id,Q($this->gettext('smtpport'))),
			'content'  => $smtpport->show($smtp_id==0?'':$reveal_smtp['smtpport'])
		);

		$field_id = 'rcmfd_smtpuser';
		$localname=($reveal_smtp['smtpuser']=="%u")?$this->rc->user->get_username('local'):$reveal_smtp['smtpuser'];
		$smtpuser = new  html_inputfield(array('type'=>'text', 'name'=>'_smtpuser', 'id'=>$field_id, 'size'=>'40'));

		$blocks['main']['options']['smtpuser'] = array(
			'title' => html::label($field_id,Q($this->gettext('smtpuser'))),
			'content'  => $smtpuser->show($smtp_id==0?'':$localname)
		);

		$field_id = 'rcmfd_smtppass';
		$smtppass = new  html_inputfield(array('type'=>'password', 'name'=>'_smtppass', 'id'=>$field_id, 'size'=>'40'));

		$blocks['main']['options']['smtppass'] = array(
			'title' => html::label($field_id,Q($this->gettext('smtppass'))),
			'content'  => $smtppass->show($smtp_id==0?'':$reveal_smtp['smtppass'])
		);

		$field_id = 'rcmfd_iftls';
		$iftls = new html_checkbox(array('name'=>'_iftls', 'id'=>$field_id, 'value' => 1,
			'onchange' => "document.getElementById('rcmfd_smtpport').value=this.checked?587:25"));

		$blocks['main']['options']['iftls'] = array(
			'title' => html::label($field_id,Q($this->gettext('iftls'))),
			'content'  => $iftls->show($smtp_id==0?false:$reveal_smtp['iftls'])
		);

		$field_id = 'rcmfd_ifdefault';
		$ifdefault = new html_checkbox(array('name'=>'_ifdefault', 'id'=>$field_id, 'value' => 1, 
			'disabled' => $reveal_smtp['ifdefault']==1));

		$blocks['main']['options']['ifdefault'] = array(
			'title' => html::label($field_id,Q($this->gettext('ifdefault'))),
			'content'  => $ifdefault->show($smtp_id==0?false:$reveal_smtp['ifdefault'])
		);

		$out .= $this->block_output($blocks, $attrib);
		$out .= '</form>';
		$this->rc->output->add_gui_object('smtpform', 'smtp_form');
		return $out;
	}

	function admin_opt_list($attrib)
	{ 
		// add id to message list table if not specified
		if (!strlen($attrib['id']))
			$attrib['id'] = 'rcmadminoptlist';

		// define list of cols to be displayed
		$a_show_cols = array('name');

		$list[] = array(
			'admin_opt' => 'smtp',
			'name' => $this->gettext('presmtpsettings'),
		);

		$list[] = array(
			'admin_opt' => 'extmail',
			'name' => $this->gettext('extmail_settings'),
		);

		$out = rcube_table_output($attrib, $list, $a_show_cols, 'admin_opt');
		// set client env
		$this->rc->output->add_gui_object('adminoptlist', $attrib['id']);
		$this->rc->output->include_script('list.js');

		// add some labels to client
		$this->rc->output->add_label('test label');

		return $out;

	}

	function admin_form($attrib)
	{
		$opt = $_GET['_opt'];

		switch($opt) {
		case 'smtp':
			return $this->admin_smtp_frame($attrib);
		case 'extmail':
			return $this->admin_extmail_frame($attrib);
		}
		return 'hello world';
	}

	function extmail_step_title($attrib)
	{ 
		$step = isset($_GET['_step'])? $_GET['_step']: $_POST['_step'];

		switch((int)$step) {
		case 1:
			return $this->gettext('mailstation.step1_extmail_settings');
		case 2:
			return $this->gettext('mailstation.step2_extmail_options');
		case 3:
			return $this->gettext('mailstation.step3_ext_smtp_server');
		case 4:
			return $this->gettext('mailstation.step4_first_time_action');
		}
	}

	function extmail_add_form($attrib)
	{
		$step = isset($_GET['_step'])? $_GET['_step']: $_POST['_step'];

		$this->rc->output->set_env('extmail_step', (int)$step);
		$this->rc->output->add_label('noemailwarning', 'mailstation.no_ext_username', 'mailstation.no_ext_password', 'mailstation.no_pop_server', 'mailstation.no_pop_port', 'mailstation.connerror');

		switch($step) {
		case 1:
			return $this->extmail_form_step1($attrib);
		case 2:
			return $this->extmail_form_step2($attrib);
		case 3:
			return $this->extmail_form_step3($attrib);
		case 4:
			return $this->extmail_form_step4($attrib);
		}
	}

	function extmail_edit($attrib)
	{

		if (($_GET['_iid'] || $_POST['_iid'] )) {
			$ACCOUNT_RECORD = $this->get_popinfo(get_input_value('_iid', RCUBE_INPUT_GPC));
		} 	

		$this->rc->output->add_label('noemailwarning', 'mailstation.no_ext_username', 'mailstation.no_ext_password', 'mailstation.no_pop_server', 'mailstation.no_pop_port');
		$this->rc->output->include_script('common.js');

		$out = '<form name="extmail_form" action="./" method="post"'."\n";

		$hiddenfields = new html_hiddenfield(array('name' => '_task', 'value' => $this->rc->task));
		$hiddenfields->add(array('name' => '_framed', 'value' => 1));
		$hiddenfields->add(array('name' => '_action', 'value' => 'plugin.syno_extmail'));
		$hiddenfields->add(array('name' => '_act', 'value' => 'edit'));
		$hiddenfields->add(array('name' => '_iid', 'value' => $_GET['_iid']));

		$out .= $hiddenfields->show();

		$blocks = array(
			'popinfo' => array('name' => Q($this->gettext('extmail_settings'))),
			'popfolder' => array('name' => Q($this->gettext('where_to_place'))),
			'popkeeplocal' => array('name' => Q($this->gettext('if_retain'))),
			'smtpinfo' => array('name' => Q($this->gettext('select_smtp'))),
		); 

		// pop info
		$field_id = 'rcmfd_email';
		$email = new html_inputfield(array('type' => 'text', 'size' => $i_size, 'name'=>'_email'));
		$blocks['popinfo']['options']['email'] = array(
			'title' => html::label($field_id, Q(rcube_label('email'))),
			'content'  => $email->show($ACCOUNT_RECORD['email']),
		);

		$field_id = 'rcmfd_extusername';
		$username = new html_inputfield(array('type' => 'text', 'size' => $i_size, 'id' => 'rcmfd_extusername', 'name'=>'_extusername'));
		$blocks['popinfo']['options']['username'] = array(
			'title' => html::label($field_id, Q(rcube_label('username'))),
			'content' => $username->show($ACCOUNT_RECORD['extusername']),
		);

		$field_id = 'rcmfd_extpd';
		$password = new  html_inputfield(array('type' => 'password', 'size' => $i_size, 'id' => 'rcmfd_extpd', 'name'=>'_extpd'));
		$blocks['popinfo']['options']['password'] = array(
			'title' => html::label($field_id, Q(rcube_label('password'))),
			'content' => $password->show($ACCOUNT_RECORD['extpd']),
		);

		$field_id = 'rcmfd_popserver';
		$popserver= new  html_inputfield(array('type' => 'text', 'size' => $i_size, 'id' => 'rcmfd_popserver', 'name'=>'_popserver'));
		$blocks['popinfo']['options']['pop_server'] = array(
			'title' => html::label($field_id, Q($this->gettext('popserver'))),
			'content' => $popserver->show($ACCOUNT_RECORD['popserver']),
		);

		$field_id = 'rcmfd_popport';
		$popport  = new  html_inputfield(array('type' => 'text', 'size' => $i_size, 'id' => 'rcmfd_popport', 'name'=>'_popport'));
		$blocks['popinfo']['options']['pop_port'] = array(
			'title' => html::label($field_id, Q($this->gettext('popport'))),
			'content' => $popport->show($ACCOUNT_RECORD['popport']),
		);

		$field_id = 'rcmfd_ifssl';
		$ifssl = new  html_checkbox(array('name' => '_ifssl', 'id' => 'rcmfd_ifssl', 'value'=>1,
			'onchange' => "document.getElementById('rcmfd_popport').value=this.checked?995:110"));
		$blocks['popinfo']['options']['if_ssl'] = array(
			'title' => html::label($field_id, Q($this->gettext('ifssl'))),
			'content'  => $ifssl->show($ACCOUNT_RECORD['ifssl']),
		);

		// pop folder
		$field_id = 'rcmfd_select_folder';
		$select_folder = rcmail_mailbox_select(array('name'=>'_select_folder', 'realname' => 'true','maxlength' => 30,
			'exceptions'=>array('Drafts','Sent Items','Junk','Trash'), 'style'=>"width:150px"));

		$blocks['popfolder']['options']['select_folder'] = array(
			'title' => html::label($field_id, Q($this->gettext('use_current'))),
			'content'  => $select_folder->show($ACCOUNT_RECORD['select_folder']),
		);

		// pop keep local
		$field_id = 'rcmfd_remove_mail';
		$remove_mail = new html_checkbox(array('name' => '_remove_mail', 'id' => 'rcmfd_remove_mail', 'value' => 1));
		$blocks['popkeeplocal']['options']['remove_mail'] = array(
			'title' => html::label($field_id, Q($this->gettext('remove_mail'))),
			'content'  => $remove_mail->show($ACCOUNT_RECORD['remove_mail']),
		);

		// smtp
		$field_id = 'rcmfd_use_default_smtp';
		$select_smtp = $this->rcmail_smtp_select(array('name'=>'_select_smtp','style'=>"width:300px"));
		$result = $this->list_smtp(sprintf('AND ifdefault = 1'));
		$smtp_default = $result[0]['smtp_id'];

		$blocks['smtpinfo']['options']['select_smtp'] = array(
			'title' => html::label($field_id, Q(rcube_label('mailstation.select_smtp'))),
			'content'  => $select_smtp->show($ACCOUNT_RECORD['select_smtp']? (int)$ACCOUNT_RECORD['select_smtp']: (int)$smtp_default),
		);

		$this->rc->output->add_gui_object('extmailform', 'extmail_form');
		$out .= $this->block_output($blocks, $attrib);
		return $out . '</form>';

	}

	function rcmail_extaccount_list($attrib)
	{
		// add id to message list table if not specified
		if (!strlen($attrib['id']))
			$attrib['id'] = 'rcmextaccountList';

		// get identities list and define 'mail' column
		$list = $this->list_popinfo();
		foreach ($list as $idx => $row) {
			if (!$list[$idx]['ext']) {
				unset($list[$idx]);
				continue;
			}
			$list[$idx]['mail'] = trim($row['name'] . ' <' . $row['email'] .'>');
		}

		// get all identites from DB and define list of cols to be displayed
		$plugin = array('list' => $list, 'cols' => array('mail'));

		// @TODO: use <UL> instead of <TABLE> for identities list
		// create XHTML table
		$out = rcube_table_output($attrib, $plugin['list'], $plugin['cols'], 'identity_id');

		// set client env
		//
		$this->rc->output->include_script('list.js');
		$this->rc->output->add_gui_object('extmailaccountlist', $attrib['id']);

		return $out;

	}

	function smtp_save($args)
	{
		$save_data_smtp = array(
			'smtpdesc'   => get_input_value('_smtpdesc', RCUBE_INPUT_POST),
			'smtpserver'   => get_input_value('_smtpserver', RCUBE_INPUT_POST),
			'smtpport'  => intval (get_input_value('_smtpport', RCUBE_INPUT_POST)),
			'smtpuser'  => get_input_value('_smtpuser', RCUBE_INPUT_POST),
			'smtppass'  => get_input_value('_smtppass', RCUBE_INPUT_POST),
			'iftls'  => isset($_POST['_iftls'])?get_input_value('_iftls', RCUBE_INPUT_POST):0,
			'ifdefault'  => isset($_POST['_ifdefault'])?get_input_value('_ifdefault', RCUBE_INPUT_POST):0,
		);

		$select_smtp = get_input_value('_sid', RCUBE_INPUT_POST);
		$sid= get_input_value('_sid', RCUBE_INPUT_POST);
		$smtp_result = $this->list_smtp(sprintf('AND ifdefault = 1'));
		$smtp_default = $smtp_result[0]['smtp_id'];
		if ($smtp_default == $select_smtp) {
			$save_data_smtp['ifdefault'] = 1; 
		}

		if (!$sid) {
			$select_smtp = $this->insert_smtp($save_data_smtp);
		}

		if ($save_data_smtp['ifdefault'] == 1) {
			$this->set_default_smtp($select_smtp);
		}

		$_GET['_sid'] =  $select_smtp;

		if (isset($save_data_smtp) && $sid) {
			if (!$this->update_smtp($select_smtp, $save_data_smtp)) {
				$this->rc->output->show_message('errorsaving', 'error', null, false);
			} else {
				$this->rc->output->show_message('successfullysaved', 'confirmation', null, false);
			}
		}

		$name = $save_data_smtp['smtpserver'];
		if ($save_data_smtp['ifdefault']) {
			$name .= ' ('. rcube_label('mailstation.default'). ') ';
			// update default
			$this->rc->output->command('parent.smtp_list_update', 'update', array('id' => $smtp_default, 'name' => $smtp_result[0]['smtpserver']));
		}

		$this->rc->output->command('parent.smtp_list_update', $sid? 'update': 'add', array( 'name' => $name, 'id' => $select_smtp,));
	
		// Init plugin and handle managesieve connection
		$this->smtp_start();

		$this->smtp_send();
	}

	function smtp_delete($sid)
	{
		if (!$sid) {
			return;
		}

		$smtp_result = $this->list_smtp(sprintf('AND ifdefault = 1'));
		$smtp_default = $smtp_result[0]['smtp_id'];

		if ($sid == $smtp_default) {
			$this->rc->output->show_message('you cannot delete default');
			return;
		}

		if (!$this->delete_smtp($sid)) {	
			$this->rc->output->show_message('errorsaving', 'error', null, false);
			return;
		}

		return $smtp_default;
	}

	function admin_save($attrib)
	{
		$opt = $_POST['_opt'];

		switch($opt) {
		case 'smtp':
			$this->admin_smtp_save();
			break;
		case 'extmail':
			$this->admin_extmail_save();
			break;
		}

		// to keep in the same page
		$_GET['_opt'] = $opt;
		$this->admin_start(); 
		$this->admin_send();
	}

	function extmail_step() 
	{
		$step = isset($_GET['_step'])? $_GET['_step']: $_POST['_step'];
		$save = isset($_GET['_save'])? $_GET['_save']: $_POST['_save'];
		$err = false;

		$this->step_input_record($step);

		if ($save == 'next') {
			$step = $step + 1;
		} else if ($save == 'back') {
			$step = $step - 1;
		}

		if ($save == 'save') {
			$iid = $this->extmail_add_save();
			if (!$iid) {
				$this->rc->output->command('parent.show_alert', 'save extmail add fail');
				$_GET['_step'] = $step;
			} else {
				$popinfo = $this->get_popinfo($iid);
				$this->rc->output->command('parent.extmail_list_update', 'add', array(
					// &#60 and &#62 is escaped html char < >
					'name' => trim($popinfo['name'] . ' &#60' . $popinfo['email'] .'&#62'),
					'id' => $iid,
				));
			}
		} else {
			$_GET['_step'] = $step;
		}

		$this->extmail_start();
		$this->include_script('syno_extmail.js');

		$this->extmail_send();

	}

	function extmail_edit_save()
	{
		$a_save_cols = array('ext','email', 'extusername', 'extpd', 'popserver', 'popport', 'select_folder', 'remove_mail', 'ifssl', 'select_smtp','ca_failure');
		$iid = get_input_value('_iid', RCUBE_INPUT_POST);
		foreach ($a_save_cols as $col) {
			$save_data[$col] = get_input_value('_' . $col, RCUBE_INPUT_POST);
		}

		$save_data['ext'] = 1;
		$save_data['ifssl'] = $save_data['ifssl']? get_input_value('_ifssl', RCUBE_INPUT_POST): 0;
		$save_data['remove_mail'] = $save_data['remove_mail']? get_input_value('_remove_mail', RCUBE_INPUT_POST): 0;

		$save_data['ca_failure'] = $this->ca_failure_test($save_data['popserver'],$save_data['popport']) ? 1 : 0;

		if (!$this->writerc($save_data,'-1',$iid)) {
			syslog(LOG_ERR, 'writerc fail');
			$this->rc->output->show_message('errorsaving', 'error', null, false);
			return;
		}
		unset($save_data['ca_failure']);

		list($save_data,$popinfo) = $this->filter_popinfo($save_data);

		$this->update_popinfo($popinfo, $iid);

		if (!$this->rc->user->update_identity($iid, $save_data)) {
			syslog(LOG_ERR, 'update identity fail');
			$this->rc->output->show_message('errorsaving', 'error', null, false);
			return;
		}

		$this->rc->output->show_message('successfullysaved', 'confirmation');

		$identity = $this->get_identity_by_email($save_data['email']);
		$this->rc->output->command('parent.extmail_list_update', 'update', array(
					'name' => trim($identity['name'] . ' <' . $identity['email'] .'>'),
					'id' => $iid,
		));
		if (!empty($_POST['_standard']))
			$default_id = get_input_value('_iid', RCUBE_INPUT_POST);

		$_GET['_iid'] = $iid;

	}

	function admin_smtp_save()
	{
		$smtp_server = $_POST['_smtp_server'];
		$smtp_port = $_POST['_smtp_port'];
		$attachment_size = $_POST['_attachment_size'];

		$org_smtp_server = $_POST['_org_smtp_server'];
		$org_smtp_port = $_POST['_org_smtp_port'];
		$org_attachment_size = $_POST['_org_attachment_size'];



		$attachment_limit = preg_replace ("/M/s",'',ini_get('post_max_size'));

		if ($_POST['_smtp_server']=='localhost' && ($message_size_limit = $this->parse_max_messages_size())) {
			$attachment_limit = ($attachment_limit < $message_size_limit)?$attachment_limit:$message_size_limit;
		}

		$filepath = RCMAIL_CONFIG_DIR . '/main.inc.php';

		if ($this->writedata('smtp_server', $_POST['_smtp_server'], $_POST['_org_smtp_server'], $filepath)) {
			syslog(LOG_ERR, 'save smtp server fail');
			$this->rc->output->show_message('errorsaving', 'error', null, false);
			return;
		}

		if ($this->writedata('smtp_port', $_POST['_smtp_port'], $_POST['_org_smtp_port'], $filepath)) {
			syslog(LOG_ERR, 'save smtp port fail');
			$this->rc->output->show_message('errorsaving', 'error', null, false);
			return;
		}

		$this->rc->config->set('smtp_server', $_POST['_smtp_server']);
		$this->rc->config->set('smtp_port', $_POST['_smtp_port']);

		$cmd = RCMAIL_FETCH_EXEC . ' ' . 'uploadsize' . ' ' . escapeshellarg($_POST['_attachment_size']);
		system($cmd);
		$this->rc->config->set('attachment_size', $_POST['_attachment_size']);
		$cmd = RCMAIL_FETCH_EXEC . ' ' .$_POST['_smtp_server'] .':'.preg_replace("/^0*/i",'',$_POST['_smtp_port']) . ' 3';
		system($cmd);

		$this->rc->output->show_message('successfullysaved', 'confirmation', null, false);
	}

	function admin_extmail_save()
	{
		$extmail_allow = isset($_POST['_extmailallow'])? $_POST['_extmailallow']: 0;
		$extmail_period = isset($_POST['_extmailperiod'])? $_POST['_extmailperiod']: $_POST['_org_extmailperiod'];

		$org_extmail_allow = $_POST['_org_extmailallow'];
		$org_extmail_period = $_POST['_org_extmailperiod'];

		if (!$org_extmail_allow && $extmail_allow)
			$rcflag = true;

		$extmail_allow = ($extmail_allow) ? "true" : "false";
		$org_extmail_allow = ($org_extmail_allow) ? "true" : "false";

		if ($org_extmail_allow == "true" && $extmail_allow == "false")
			$this->rcmail_daemon_stop();

		if ($org_extmail_allow == "false" && $extmail_allow == "true")
			$this->rcmail_daemon_start();

		$filepath = RCMAIL_CONFIG_DIR . '/main.inc.php';

		if ($this->writedata('extmailallow', $extmail_allow, $org_extmail_allow, $filepath)) {
			syslog(LOG_ERR, 'save extmailallow fail');
			$this->rc->output->show_message('errorsaving', 'error', null, false);
			return;
		}

		//
		//$userlist = $this->rc->user->list_users();
		$userlist = $this->list_users();
		foreach ($userlist as $user) {
			$fetchtemp = RCMAIL_EXT_DIR . '/'.$user['username'].'_fetch';
			if (!file_exists($fetchtemp)) {
				continue;
			}

			$cmd = RCMAIL_FETCH_EXEC . ' ' . escapeshellarg($user['username']) . ' 1';
			system($cmd);
			if ($this->writedata('daemon', 60*$extmail_period, 60*$org_extmail_period , $fetchtemp)) {
				syslog(LOG_ERR, 'writedate ' . $fetchtemp . ' fail');
				$this->rc->output->show_message('errorsaving', 'error', null, false);
			}

			$cmd = RCMAIL_FETCH_EXEC . ' ' . escapeshellarg($user['username']) . ' 2';
			system($cmd);
		}

		$fetchtemp = RCMAIL_EXT_DIR . '/fetchmailrc';

		if ($this->writedata('daemon', 60*$extmail_period , 60*$org_extmail_period, $fetchtemp)) {
			syslog(LOG_ERR, 'writedate ' . $fetchtemp . ' fail');
			$this->rc->output->show_message('errorsaving', 'error', null, false);
			return;
		}

		$filepath = RCMAIL_CONFIG_DIR . '/main.inc.php';
		if ($this->writedata('extmailperiod', $extmail_period, $org_extmail_period, $filepath)) {
			syslog(LOG_ERR, 'writedate ' . $filepath . ' fail');
			$this->rc->output->show_message('errorsaving', 'error', null, false);
			return;
		}

		$this->rc->config->set('extmailallow', $extmail_allow); 
		$this->rc->config->set('extmailperiod', $extmail_period);

		$this->rc->output->show_message('successfullysaved', 'confirmation', null, false);
	}

	function admin_extmail_frame($attrib)
	{

		if (!$attrib['id'])
			$attrib['id'] = 'rcmadminextmailform';

		$this->rc->output->add_label('mailstation.no_extmail_period');
		$this->rc->output->set_env('admin_opt', 'extmail');

		$extmail_allow = $this->rc->config->get('extmailallow');
		$extmail_period = $this->rc->config->get('extmailperiod', 5);

		$extmail_allow = ($extmail_allow == 'true') ? 1 : 0;

		$out = '<form name="admin_extmail_form" action="./" method="post">'."\n";
		$hiddenfields = new html_hiddenfield(array('name' => '_action', 'value' => 'plugin.admin_save'));
		$hiddenfields->add(array('name' => '_opt', 'value' => 'extmail'));
		$hiddenfields->add(array('name' => '_framed', 'value' => 1));
		$hiddenfields->add(array('name' => '_org_extmailallow', 'value' => $extmail_allow));
		$hiddenfields->add(array('name' => '_org_extmailperiod', 'value' => $extmail_period));
		$out .= $hiddenfields->show();

		$blocks = array(
			'extmailsettings' => array('name' => Q($this->gettext('extmail_settings'))),
		);

		$field_id = 'rcmfd_extmailallow';
		$extmailallow = new html_checkbox(array('name'=>'_extmailallow', 'id'=>$field_id, 'value' => 1,
			'checked'   => "checked",
			'onclick'   => "document.getElementById('rcmfd_extmailperiod').disabled=this.checked==false")
		);

		$blocks['extmailsettings']['options']['extmailallow'] = array(
			'title' => html::label($field_id, Q($this->gettext('extmail_allow'))),
			'content'  => $extmailallow->show($extmail_allow)
		);

		$field_id = 'rcmfd_extmailperiod';
		$extmailperiod = new html_inputfield(array('type' =>'text', 'name'=>'_extmailperiod', 'id'=>$field_id, 'size'=>'40',
			'disabled' => ($extmail_allow != 1),
			'onkeydown' => "if (event.keyCode == 13) return rcmail.command('save','',this)",
		));

		$blocks['extmailsettings']['options']['extmailperiod'] = array(
			'title' => html::label($field_id, Q($this->gettext('extmail_period'))),
			'content'  => $extmailperiod->show($extmail_period)
		);

		$this->rc->output->add_gui_object('adminform', 'admin_extmail_form');

		$out .= $this->block_output($blocks, $attrib);

		return $out . '</form>';

	}

	function admin_smtp_frame($attrib)
	{

		if (!$attrib['id'])
			$attrib['id'] = 'rcmadminsmtpform';

		$this->rc->output->add_label('mailstation.no_attachmentsize', 'mailstation.bound_attachment_limit', 'mailstation.no_smtpport');
		$this->rc->output->set_env('admin_opt', 'smtp');

		$this->load_attachment_size();
		$smtp_server = $this->rc->config->get('smtp_server');
		$smtp_port = $this->rc->config->get('smtp_port');
		$attachment_size = preg_replace ("/M/s", '', $this->rc->config->get('attachment_size'));
		$attachment_limit = preg_replace ("/M/s",'',ini_get('post_max_size'));

		$out = '<form name="admin_smtp_form" action="./" method="post">'."\n";
		$hiddenfields = new html_hiddenfield(array('name' => '_action', 'value' => 'plugin.admin_save'));
		$hiddenfields->add(array('name' => '_opt', 'value' => 'smtp'));
		$hiddenfields->add(array('name' => '_framed', 'value' => 1));
		$hiddenfields->add(array('name' => '_org_smtp_server', 'value' => $smtp_server));
		$hiddenfields->add(array('name' => '_org_smtp_port', 'value' => $smtp_port));
		$hiddenfields->add(array('name' => '_org_attachment_size', 'value' => $attachment_size));
		$hiddenfields->add(array('name' => '_attachment_limit', 'value' => $attachment_limit));
		$out .= $hiddenfields->show();


		$blocks = array(
			'smtpsettings' => array('name' => Q($this->gettext('presmtpsettings'))),
		); 

		$blocks['smtpsettings']['descr'] = html::label($field_id, Q($this->gettext('descr_predefined_smtpsetting')));

		$field_id = 'rcmfd_smtp_server';
		$smtpserver = new html_inputfield(array('type' =>'text', 'name'=>'_smtp_server', 'id'=>$field_id, 'size'=>'40'));
		$blocks['smtpsettings']['options']['smtp_server'] = array(
			'title' => html::label($field_id, Q($this->gettext('smtpserver'))),
			'content'  => $smtpserver->show($smtp_server),
		);

		$field_id = 'rcmfd_smtp_port';
		$smtpport = new  html_inputfield(array('type'=>'text', 'name'=>'_smtp_port', 'id'=>$field_id, 'size'=>'40'));
		$blocks['smtpsettings']['options']['smtp_port'] = array(
			'title' => html::label($field_id, Q($this->gettext('smtpport'))),
			'content'  => $smtpport->show($smtp_port),
		);

		$field_id = 'rcmfd_attachment_size';
		$attachmentsize = new  html_inputfield(array('type'=>'text', 'name'=>'_attachment_size', 'id'=>$field_id, 'size'=>'40'));
		$blocks['smtpsettings']['options']['attachment_size'] = array(
			'title' => html::label($field_id, Q($this->gettext('attachmentsize'))),
			'content' => $attachmentsize->show($attachment_size),
		);


		$out .= $this->block_output($blocks, $attrib);
		$out .= '</form>';
		$this->rc->output->add_gui_object('adminform', 'admin_smtp_form');

		return $out;
	}

	function block_output($blocks, $attrib)
	{
		// FIXME: use css
		if ($this->rc->output->get_env('skin') == 'larry') {
			$fieldset_style = 'margin-bottom: 20px; border: 0; padding: 0;';
			$legend_style = 'display: block; font-size: 14px; font-weight: bold; padding-bottom: 10px; margin-bottom: 0';
		} else {
			$fieldset_style = null;
			$legend_style = null;
		}

		foreach ($blocks as $idx => $block) {
			if (!empty($block['options'])) {
				$table = new html_table(array('cols' => 2));
				foreach ($block['options'] as $option) {
					if ($option['advanced'])
						$table->set_row_attribs('advanced');

					if (isset($option['title'])) {
						$table->add('title', $option['title']);
						$table->add(null, $option['content']);
					} else {
						$table->add(array('colspan' => 2), $option['content']);
					}
				}
				// FIXME: don't use style
				$out .= html::tag('fieldset', array('style' => $fieldset_style), html::tag('legend', array('style' => $legend_style), $block['name']) . $table->show($attrib));

			} else if (!empty($block['content'])) {
				$out .= html::tag('fieldset', array('style' => $fieldset_style), html::tag('legend', array('style' => $legend_style), $block['name']) . $block['content']);
			}
		}

		return $out;
	}

	function get_smtp($sid = null)
	{
		$result = $this->list_smtp($sid ? sprintf('AND smtp_id = %d', $sid) : 'AND ifdefault = 1');
		return $result[0];
	}

	function list_smtp($sql_add = '')
	{
		$result = array();

		if (!$this->userID) {
			return;
		}
		$sql_result = $this->db->query(
			"SELECT * FROM ".get_table_name('smtp').  
			" WHERE del <> 1 AND user_id = ?".  
			($sql_add ? " ".$sql_add : "").  
			" ORDER BY smtp_id ASC", 
			$this->userID);
		while ($sql_arr = $this->db->fetch_assoc($sql_result)) {
			$result[] = $sql_arr;
		}

		return $result;
	}

	function insert_smtp($data) 
	{
		if (!$this->userID)
			return false;

		unset($data['user_id']);

		$insert_cols = $insert_values = array();
		foreach ((array)$data as $col => $value) {
			$insert_cols[]   = $this->db->quoteIdentifier($col);
			$insert_values[] = $value;
		}
		$insert_cols[]   = 'user_id';
		$insert_values[] = $this->userID;

		$sql = "INSERT INTO ".get_table_name('smtp').  
			" (changed, ".join(', ', $insert_cols).")".  
			" VALUES (".$this->db->now().", ".join(', ', array_pad(array(), sizeof($insert_values), '?')).")";

		call_user_func_array(array($this->db, 'query'),
			array_merge(array($sql), $insert_values));

		return $this->db->insert_id('smtp_ids');
	}

	function update_smtp($sid, $data) 
	{
		if (!$this->userID)
			return false;

		$query_cols = $query_params = array();

		foreach ((array)$data as $col => $value) {
			$query_cols[]   = $this->db->quoteIdentifier($col) . ' = ?';
			$query_params[] = $value;
		}
		$query_params[] = $sid;
		$query_params[] = $this->userID;

		$sql = "UPDATE ".get_table_name('smtp').
			" SET changed = ".$this->db->now().", ".join(', ', $query_cols).
			" WHERE smtp_id = ?".
			" AND user_id = ?".
			" AND del <> 1";

		call_user_func_array(array($this->db, 'query'),
			array_merge(array($sql), $query_params));

		return $this->db->affected_rows(); 
	}

	function delete_smtp($sid) 
	{
		if (!$this->userID)
			return false;

		$sql_result = $this->db->query(
			"SELECT count(*) AS smtp_count FROM ".get_table_name('smtp').
			" WHERE user_id = ? AND del <> 1",
			$this->userID);

		$sql_arr = $this->db->fetch_assoc($sql_result);

		// we'll not delete last smtp
		if ($sql_arr['smtp_count'] <= 1)
			return false;

		$this->db->query(
			"UPDATE ".get_table_name('smtp').
			" SET del = 1, changed = ".$this->db->now().
			" WHERE user_id = ?".
			" AND smtp_id = ?",
			$this->userID,
			$sid);

		return $this->db->affected_rows();
	}

	function set_default_smtp($sid)
	{
		if ($this->userID && $sid) {
			$this->db->query(
				"UPDATE ".get_table_name('smtp').  
				" SET ".$this->db->quoteIdentifier('ifdefault')." = '0'".  
				" WHERE user_id = ?".
				" AND smtp_id <> ?". 
				" AND del <> 1",
				$this->userID,
				$sid); 
		}
	}

	function rcmail_smtp_select($p = array())
	{
		//global $RCMAIL,$USER;

		$p += array('name'=>'_select_smtp','style'=>"width:300px");
		$select_smtp = new html_select($p);
		if ($p['noselection'])
			$select_smtp->add($p['noselection'], 0);

		$smtplist = $this->list_smtp();
		foreach ($smtplist as $sp) {
			$elem = $sp['smtpdesc']?$sp['smtpdesc'].' - '.$sp['smtpserver']:$sp['smtpserver'];
			if ($sp['ifdefault']) {
				$elem .= ' ('. rcube_label('default'). ') ';
				$smtp_default = $sp['smtp_id'];
			}
			$select_smtp->add($elem,$sp['smtp_id']);
		}
		return $select_smtp;
	}

	function load_attachment_size()
	{
		$max_postsize = preg_replace ("/M/s",'',ini_get('post_max_size'));
		$smtp_server = $this->rc->config->get('smtp_server', 'localhost');
		if ( $smtp_server == 'localhost' && ($message_size_limit = $this->parse_max_messages_size())) {
			$max_postsize = ($max_postsize < $message_size_limit)?$max_postsize:$message_size_limit;
		}

		$attachment_size = $this->rc->config->get('attachment_size');
		if (empty($attachment_size)) {
			$this->rc->config->set('attachment_size', ini_get('upload_max_filesize'));
		}
		$attachment_size = preg_replace ("/M/s", '', $this->rc->config->get('attachment_size'));
		if ($max_postsize < $attachment_size) {
			$this->writedata('attachment_size', $max_postsize, $attachment_size, INSTALL_PATH . '.htaccess');
			$this->rc->config->set('attachment_size', $max_postsize.'M');
		}
	}

	function parse_max_messages_size()
	{
		if (!($content = file_get_contents("/etc/synoinfo.conf"))) {
			return false;
		}
		if (!mb_ereg ("message_size_limit=\"(\d+)\"\s",$content,$regs)){
			return false;
		}
		return $regs[1]?$regs[1]:false;

	}

	function writedata($name, $newvalue, $oldvalue, $filename)
	{
		$name = ($name=='attachment_size')?'upload_max_filesize':$name;	
		$err = true;
		$newline = '';
		$surplus = '';

		// replace value base on line
		if (!($fd = fopen($filename, 'rb'))) {
			return $err;
		}

		while ($oldline = rtrim(fgets($fd), 64)) {
			if (strstr($oldline,$name)) {
				if ($name=='daemon') {
					$newline='set '.$name.' '.$newvalue."\n";
					break;
				}
				if (!($newline=str_replace($oldvalue,$newvalue,$oldline))) { 
					return $err;
				}
				break; 
			}
		} 

		fclose($fd);

		if (!$newline) {
			if (strstr($filename, 'main.inc.php')) {
				$surplus = "\n" . '$rcmail_config[' . $name . ']  = ' . $newvalue . ';';
			}
		}

		// replace value base on contect
		if (!($content = file_get_contents($filename))) {
			return $err;
		}
		if (!($content = str_replace($oldline, $newline, $content))) {
			return $err;
		}
		if (!($fw = fopen($filename,'wb'))) {
			return $err;
		}
		if (!(fwrite($fw, $content.$surplus))) {
			return $err;
		}
		fclose($fw);
		$err = false;

		return $err;
	}

	function rcmail_daemon_stop()
	{
		$cmd = '';
		$userlist = $this->list_users();
		foreach ($userlist as $user) {
			$cmd = RCMAIL_FETCH_EXEC . ' ' . escapeshellarg($user['username']) . ' -2'; 
			$buffer = system($cmd);
		}
		return true;

	}

	function rcmail_daemon_start()
	{
		$cmd = '';
		$userlist = $this->list_users();
		foreach ($userlist as $user) {
			$cmd = RCMAIL_FETCH_EXEC . ' ' . escapeshellarg($user['username']) . ' -1'; 
			$buffer = system($cmd);
		}
		return true;
	}

	function list_users()
	{
		$result = array();

		$sql_result = $this->db->query(
			"SELECT * FROM ".get_table_name('users'));
		while ($sql_arr = $this->db->fetch_assoc($sql_result)) {
			$result[] = $sql_arr;
		}

		return $result;
	}

	function get_identity_by_email($email)
	{
		$result = $this->rc->user->list_identities($email ? sprintf("AND email = '%s'", $email) : '');
		return $result[0];
	}

	function step_input_record($step)
	{
		$ret = false;

		switch($step){
		case 1:
			$a_reveal_cols = array('email', 'extusername', 'extpd', 'popserver', 'popport', 'ifssl');
			$_SESSION['ifssl'] = isset($_POST['_ifssl'])? get_input_value('_ifssl', RCUBE_INPUT_POST): 0;

			if ($_SESSION['ifssl'] && $this->ca_failure_test($_SESSION['popserver'], $_SESSION['popport'])) {
				$_SESSION['ca_failure'] = 1;
			}

			break;
		case 2:
			$a_reveal_cols = array('select_folder', 'new_folder', 'use_newfolder', 'remove_mail');
			break;
		case 3:
			$a_reveal_cols = array('select_smtp', 'addsmtp', 'smtpport', 'smtpuser', 'smtppass', 'iftls','ifdefault','smtpdesc', 'smtpserver');
			break;
		case 4:
			$a_reveal_cols = array('fetch_days', 'fetchpass');
			break;
		}

		foreach ($a_reveal_cols as $col) {
			$fname = '_'.$col;
			if (isset($_POST[$fname])) {
				$_SESSION[$col]  = get_input_value($fname, RCUBE_INPUT_POST);
			} 
		}

	}

	function extmail_form_step1($attrib)
	{

		if (!$attrib['id'])
			$attrib['id'] = 'rcmextmailform';

		$ACCOUNT_RECORD = $this->get_account_record();

		$out = '<form name="extmail_form" action="./" method="post">'."\n";
		//$hiddenfields = new html_hiddenfield(array('name' => '_task', 'value' => $this->rc->task));
		$hiddenfields = new html_hiddenfield(array('name' => '_action', 'value' => 'plugin.extmail_step'));
		$hiddenfields->add(array('name' => '_step', 'value' => 1));
		$hiddenfields->add(array('name' => '_save', 'value' => 'next'));
		$out .= $hiddenfields->show();

		$blocks = array(
			'popinfo' => array('name' => Q($this->gettext('extmail_settings'))),
		); 


		$field_id = 'rcmfd_email';
		$email = new html_inputfield(array('type' => 'text', 'size' => $i_size, 'name'=>'_email'));
		$blocks['popinfo']['options']['email'] = array(
			'title' => html::label($field_id, Q(rcube_label('email'))),
			'content'  => $email->show($ACCOUNT_RECORD['email']),
		);

		$field_id = 'rcmfd_extusername';
		$username = new html_inputfield(array('type' => 'text', 'size' => $i_size, 'id' => 'rcmfd_extusername', 'name'=>'_extusername'));
		$blocks['popinfo']['options']['username'] = array(
			'title' => html::label($field_id, Q(rcube_label('username'))),
			'content' => $username->show($ACCOUNT_RECORD['extusername']),
		);

		$field_id = 'rcmfd_extpd';
		$password = new  html_inputfield(array('type' => 'password', 'size' => $i_size, 'id' => 'rcmfd_extpd', 'name'=>'_extpd'));
		$blocks['popinfo']['options']['password'] = array(
			'title' => html::label($field_id, Q(rcube_label('password'))),
			'content' => $password->show($ACCOUNT_RECORD['extpd']),
		);

		$field_id = 'rcmfd_popserver';
		$popserver= new  html_inputfield(array('type' => 'text', 'size' => $i_size, 'id' => 'rcmfd_popserver', 'name'=>'_popserver'));
		$blocks['popinfo']['options']['pop_server'] = array(
			'title' => html::label($field_id, Q($this->gettext('popserver'))),
			'content' => $popserver->show($ACCOUNT_RECORD['popserver']),
		);

		$field_id = 'rcmfd_popport';
		$popport  = new  html_inputfield(array('type' => 'text', 'size' => $i_size, 'id' => 'rcmfd_popport', 'name'=>'_popport'));
		$blocks['popinfo']['options']['pop_port'] = array(
			'title' => html::label($field_id, Q($this->gettext('popport'))),
			'content' => $popport->show($ACCOUNT_RECORD['popport']),
		);

		$field_id = 'rcmfd_ifssl';
		$ifssl    = new  html_checkbox(array('name' => '_ifssl', 'id' => 'rcmfd_ifssl', 'value'=>1,
			'onchange' => "document.getElementById('rcmfd_popport').value=this.checked?995:110"));
		$blocks['popinfo']['options']['if_ssl'] = array(
			'title' => html::label($field_id, Q($this->gettext('ifssl'))),
			'content'  => $ifssl->show($ACCOUNT_RECORD['ifssl']),
		);


		$this->rc->output->add_gui_object('extmailform', 'extmail_form');
		$out .= $this->block_output($blocks, $attrib);
		return $out . '</form>';

	}

	function extmail_form_step2($attrib)
	{
		//
		if (!$attrib['id'])
			$attrib['id'] = 'rcmextmailform';

		$ACCOUNT_RECORD = $this->get_account_record();

		$out = '<form name="extmail_form" action="./" method="post">'."\n";
		$hiddenfields = new html_hiddenfield(array('name' => '_action', 'value' => 'plugin.extmail_step'));
		$hiddenfields->add(array('name' => '_step', 'value' => 2));
		$hiddenfields->add(array('name' => '_save', 'value' => 'next'));
		$out .= $hiddenfields->show();

		$blocks = array(
			'popfolder' => array('name' => Q($this->gettext('where_to_place'))),
			'popkeeplocal' => array('name' => Q($this->gettext('if_retain'))),
		); 

		$current_folder = new html_radiobutton(array('name'=>'_use_newfolder', 'id'=>'rcmfd_use_newfolder','value'=>'0',
			'onchange' => JS_OBJECT_NAME.'.toggle_newfolder(this)'));

		$create_folder = new html_radiobutton(array('name'=>'_use_newfolder', 'id'=>'rcmfd_use_newfolder','value'=>'1',
			'onchange' => JS_OBJECT_NAME.'.toggle_newfolder(this)'));

		$field_id = 'rcmfd_select_folder';
		$select_folder = rcmail_mailbox_select(array('name'=>'_select_folder', 'realname' => 'true','maxlength' => 30,
			'exceptions'=>array('Drafts','Sent Items','Junk','Trash'), 'style'=>"width:150px"));

		$blocks['popfolder']['options']['select_folder'] = array(
			'title' => $current_folder->show($ACCOUNT_RECORD['use_newfolder']?$ACCOUNT_RECORD['use_newfolder']:0) . html::label($field_id, Q($this->gettext('mailstation.use_current'))),
			'content'  => $select_folder->show($ACCOUNT_RECORD['select_folder']),
		);


		$field_id = 'rcmfd_new_folder';
		$new_folder = new html_inputfield(array('type' => 'text', 'size' => 18,'name'=>'_new_folder','id'=>'rcmfd_new_folder',
			'disabled'=> $ACCOUNT_RECORD['use_newfolder']==0,
			'onchange' => JS_OBJECT_NAME.".env.newfolder=1" , 'onkeypress'=>"if(event.keyCode==13) return false;"));

		$blocks['popfolder']['options']['new_folder'] = array(
			'title' => $create_folder->show($ACCOUNT_RECORD['use_newfolder']) . html::label($field_id, Q($this->gettext('mailstation.use_newfolder'))),
			'content'  => $new_folder->show($ACCOUNT_RECORD['use_newfolder']==0?'':$ACCOUNT_RECORD['new_folder']),
		);

		$field_id = 'rcmfd_remove_mail';
		$remove_mail = new html_checkbox(array('name' => '_remove_mail', 'id' => 'rcmfd_remove_mail', 'value' => 1));
		$blocks['popkeeplocal']['options']['remove_mail'] = array(
			'title' => html::label($field_id, Q($this->gettext('remove_mail'))),
			'content'  => $remove_mail->show($ACCOUNT_RECORD['remove_mail']),
		);

		$out .= $this->block_output($blocks, $attrib);
		$out .= '</form>';
		$this->rc->output->add_gui_object('extmailform', 'extmail_form');
		return $out;

	}

	function extmail_form_step3($attrib)
	{

		if (!$attrib['id'])
			$attrib['id'] = 'rcmextmailform';

		$ACCOUNT_RECORD = $this->get_account_record();

		$out = '<form name="extmail_form" action="./" method="post">'."\n";
		$hiddenfields = new html_hiddenfield(array('name' => '_action', 'value' => 'plugin.extmail_step'));
		$hiddenfields->add(array('name' => '_step', 'value' => 3));
		$hiddenfields->add(array('name' => '_save', 'value' => 'next'));
		$out .= $hiddenfields->show();

		$blocks = array(
			'smtpinfo' => array('name' => Q($this->gettext('mailstation.smtpsettings'))),
		);

		$current_smtp = new html_radiobutton(array('name'=>'_addsmtp', 'id'=>'rcmfd_addsmtp','value'=>'0',
			'onchange' => JS_OBJECT_NAME.'.toggle_addsmtp(this)'));

		$add_smtp = new html_radiobutton(array('name'=>'_addsmtp', 'id'=>'rcmfd_addsmtp','value'=>'1',
			'onchange' => JS_OBJECT_NAME.'.toggle_addsmtp(this)'));

		$field_id = 'rcmfd_use_default_smtp';
		$select_smtp = $this->rcmail_smtp_select(array('name'=>'_select_smtp','style'=>"width:300px"));
		$result = $this->list_smtp(sprintf('AND ifdefault = 1'));
		$smtp_default = $result[0]['smtp_id'];

		$blocks['smtpinfo']['options']['select_smtp'] = array(
			'title' => $current_smtp->show($ACCOUNT_RECORD['addsmtp']) . html::label($field_id, Q($this->gettext('use_default_smtp'))),
			'content'  => $select_smtp->show($ACCOUNT_RECORD['select_smtp']? (int)$ACCOUNT_RECORD['select_smtp']: (int)$smtp_default),
		);

		$blocks['smtpinfo']['options']['create_smtp'] = array(
			'title' => $add_smtp->show($ACCOUNT_RECORD['addsmtp']) . html::label($field_id, Q($this->gettext('addsmtp'))),
		);

		$field_id = 'rcmfd_smtpdesc';
		$smtpdesc = new html_inputfield(array('type' => 'text','name'=>'_smtpdesc','id'=>'rcmfd_smtpdesc',
			'size' => $i_size, 'disabled' => $ACCOUNT_RECORD['addsmtp']==0));

		$blocks['smtpinfo']['options']['smtp_desc'] = array(
			'title' => html::label($field_id, Q($this->gettext('smtpdesc'))),
			'content' => $smtpdesc->show($ACCOUNT_RECORD['smtpdesc']),
		);

		$field_id = 'rcmfd_smtpserver';
		$smtpserver = new html_inputfield(array('type' => 'text','name'=>'_smtpserver','id'=>'rcmfd_smtpserver',
			'size' => $i_size, 'disabled'=>$ACCOUNT_RECORD['addsmtp']==0,
			'onchange' => JS_OBJECT_NAME.".env.addsmtp=1"));	  
		$blocks['smtpinfo']['options']['smtp_server'] = array(
			'title' => html::label($field_id, Q($this->gettext('smtpserver'))),
			'content'  => $smtpserver->show($ACCOUNT_RECORD['smtpserver']),
		);

		$field_id = 'rcmfd_smtpport';
		$smtpport = new html_inputfield(array('type' => 'text','name'=>'_smtpport','id'=>'rcmfd_smtpport',
			'size' => $i_size, 'disabled'=>$ACCOUNT_RECORD['addsmtp']==0,
			'onchange' => JS_OBJECT_NAME.".env.addsmtp=1"));
		$blocks['smtpinfo']['options']['smtp_port'] = array(
			'title' => html::label($field_id, Q($this->gettext('smtpport'))),
			'content'  => $smtpport->show($ACCOUNT_RECORD['smtpport']),
		);

		$field_id = 'rcmfd_smtpuser';
		$smtpuser = new html_inputfield(array('type' => 'text','name'=>'_smtpuser','id'=>'rcmfd_smtpuser',
			'size' => $i_size, 'disabled'=>$ACCOUNT_RECORD['addsmtp']==0));

		$blocks['smtpinfo']['options']['smtp_user'] = array(
			'title' => html::label($field_id, Q($this->gettext('smtpuser'))),
			'content'  => $smtpuser->show($ACCOUNT_RECORD['smtpuser']),
		);

		$field_id = 'rcmfd_smtppass';
		$smtppass = new html_inputfield(array('type' => 'password','name'=>'_smtppass','id'=>'rcmfd_smtppass',
			'size' => $i_size, 'disabled'=>$ACCOUNT_RECORD['addsmtp']==0));

		$blocks['smtpinfo']['options']['smtp_pass'] = array(
			'title' => html::label($field_id, Q($this->gettext('smtppass'))),
			'content'  => $smtppass->show($ACCOUNT_RECORD['smtppass']),
		);

		$field_id = 'rcmfd_iftls';
		$iftls = new html_checkbox(array('name' => '_iftls', 'id' => 'rcmfd_iftls', 'value' => 1,
			'disabled'=>$ACCOUNT_RECORD['addsmtp'] == 0,
			'onchange' => "document.getElementById('rcmfd_smtpport').value=this.checked?587:25"));

		$blocks['smtpinfo']['options']['if_tls'] = array(
			'title' => html::label($field_id, Q($this->gettext('iftls'))),
			'content'  => $iftls->show($ACCOUNT_RECORD['iftls']),
		);

		$field_id = 'rcmfd_ifdefault';
		$ifdefault = new html_checkbox(array('name' => '_ifdefault', 'id' => 'rcmfd_ifdefault', 'value'=>1,
			'disabled'=>$ACCOUNT_RECORD['addsmtp']==0));

		$blocks['smtpinfo']['options']['if_default'] = array(
			'title' => html::label($field_id, Q($this->gettext('ifdefault'))),
			'content'  => $ifdefault->show($ACCOUNT_RECORD['ifdefault']),
		);

		$out .= $this->block_output($blocks, $attrib);
		$out .= '</form>';
		$this->rc->output->add_gui_object('extmailform', 'extmail_form');
		return $out;

	}

	function extmail_form_step4($attrib)
	{

		if (!$attrib['id'])
			$attrib['id'] = 'rcmextmailform';

		$ACCOUNT_RECORD = $this->get_account_record();

		$out = '<form name="extmail_form" action="./" method="post">'."\n";
		$hiddenfields = new html_hiddenfield(array('name' => '_action', 'value' => 'plugin.extmail_step'));
		$hiddenfields->add(array('name' => '_step', 'value' => 4));
		$hiddenfields->add(array('name' => '_save', 'value' => 'save'));
		$out .= $hiddenfields->show();

		$blocks = array(
			'fetchinfo' => array('name' => Q($this->gettext('days_to_fetch'))),
		); 

		$fetch_recent = new html_radiobutton(array('name'=>'_fetchpass', 'id'=>'rcmfd_fetchpass','value'=>'0',
			'onchange' => JS_OBJECT_NAME.'.toggle_fetchpass(this)'));

		$field_id = 'rcmfd_fetch_days';
		$fetchdays = new html_inputfield(array('type' => 'text','name'=>'_fetch_days','id'=>'rcmfd_fetch_days',
			'onkeypress'=>"if(event.keyCode==13) return false;",
			'size' => $i_size, 'disabled'=>$ACCOUNT_RECORD['fetpass']!=0));

		$blocks['fetchinfo']['options']['fetch_recent'] = array(
			'title' => $fetch_recent->show($ACCOUNT_RECORD['fetchpass']) .  html::label($field_id, Q($this->gettext('fetch_recently'))),
			'content' => $fetchdays->show($ACCOUNT_RECORD['fetch_days']) . " " . html::label($field_id, Q($this->gettext('fetch_days'))),
		);

		$fetch_all = new html_radiobutton(array('name'=>'_fetchpass', 'id'=>'rcmfd_fetchpass','value'=>'1',
			'onchange' => JS_OBJECT_NAME.'.toggle_fetchpass(this)'));  
		$blocks['fetchinfo']['options']['fetch_all'] = array(
			'title' => $fetch_all->show($ACCOUNT_RECORD['fetchpass']) . html::label($field_id, Q($this->gettext('fetch_all'))),
		);

		$fetch_new = new html_radiobutton(array('name'=>'_fetchpass', 'id'=>'rcmfd_fetchpass','value'=>'2',
			'onchange' => JS_OBJECT_NAME.'.toggle_fetchpass(this)'));  
		$blocks['fetchinfo']['options']['fetch_new'] = array(
			'title' => $fetch_new->show($ACCOUNT_RECORD['fetchpass']) . html::label($field_id, Q($this->gettext('fetch_new'))),
		);

		$out .= $this->block_output($blocks, $attrib);
		$out .= '</form>';
		$this->rc->output->add_gui_object('extmailform', 'extmail_form');
		return $out;

	}

	function extmail_add_save($attrib)
	{
		$a_save_cols = array('ext','email', 'extusername', 'extpd', 'popserver', 'popport', 'select_folder', 'remove_mail', 'ifssl', 'select_smtp','ca_failure');
		$a_smtp_cols= array('smtpport', 'smtpuser', 'smtppass','iftls','ifdefault','smtpdesc', 'smtpserver');

		if ($_SESSION['use_newfolder'] == 1) {
			if ($this->rc->storage->create_folder($_SESSION['new_folder'], true)) {
				$_SESSION['select_folder'] = $_SESSION['new_folder'];
			}
		}

		unset($_SESSION['use_newfolder']);
		unset($_SESSION['new_folder']);

		//save cols of session, identity and smtp

		foreach ($a_save_cols as $col) {
			$save_data[$col] = $_SESSION[$col];
			unset($_SESSION[$col]);
		}


		$save_data['name'] = strstr($save_data['email'], '@', true);
		if (IDENTITIES_LEVEL == 1)
			$save_data['email'] = $this->rc->user->get_username();

		if (($_SESSION['addsmtp'] == 1)) {
			foreach ($a_smtp_cols as $col){
				$save_data_smtp[$col] = $_SESSION[$col];
				unset($_SESSION[$col]);
			}

			if ($save_data_smtp['smtpserver'] && $save_data_smtp['smtpport']) {
				$sid = $this->insert_smtp($save_data_smtp);
				$save_data['select_smtp'] = $sid;
			} else {
				syslog(LOG_ERR, 'insert smtp fail');
				$this->rc->output->show_message('errorsaving', 'error', null, false);
				return;
			}

			if ($save_data_smtp['ifdefault'] == 1) {
				$this->set_default_smtp($sid);
			}
		}

		unset($_SESSION['addsmtp']);

		$fetchpass = get_input_value('_fetchpass', RCUBE_INPUT_POST);
		$fetchdays = ($fetchpass==1)?-1:0;
		$fetchdays = ($fetchpass==0)?get_input_value('_fetch_days', RCUBE_INPUT_POST):$fetchdays;

		//save new identity
		if (!$this->writerc($save_data, $fetchdays,'')) {
			$this->rc->output->show_message('errorsaving', 'error', null, false);
			//goto end;
		}

		unset($save_data['ca_failure']);

		list($save_data,$popinfo) = $this->filter_popinfo($save_data);
		if ($insert_id = $this->rc->user->insert_identity($save_data)) {
			$popinfo['identity_id'] = $insert_id;
			$this->insert_popinfo($popinfo);

			//$_GET['_iid'] = $insert_id;
			if (!empty($_POST['_standard']))
				$default_id = $insert_id;
		} else {
			syslog(LOG_ERR, 'insert identity fail');
			$this->show_message('errorsaving', 'error', null, false);
			//goto end;
		}

		return $insert_id;
	}

	function list_popinfo()
	{

		$identities = $this->rc->user->list_identities();
		$result_arr = array();
		foreach($identities as $identity) {
			$sql_result = $this->db->query(
				"SELECT * FROM ".get_table_name('popinfo'). 
				" WHERE identity_id = ?" ,$identity['identity_id']);
			$sql_arr = $this->db->fetch_assoc($sql_result);
			$result_arr[] = is_array($sql_arr)?array_merge($identity, $sql_arr):$identity;
		}

		return $result_arr;
	}

	function get_popinfo($iid)
	{
		if (!$iid) {
			return;
		}

		$identity = $this->rc->user->get_identity($iid);

		$sql_result = $this->db->query(
			"SELECT * FROM ".get_table_name('popinfo'). 
			" WHERE identity_id = ?" ,$iid);

		$sql_arr = $this->db->fetch_assoc($sql_result);

		return is_array($sql_arr)? array_merge($identity, $sql_arr): $identity;
	}

	function update_popinfo($popinfo,$iid)
	{
		if (!isset($popinfo['ext'])) {
			return;
		}
		$query_cols = $query_params = array();

		foreach ((array)$popinfo as $col => $value) {
			$query_cols[]   = $this->db->quoteIdentifier($col) . ' = ?';
			$query_params[] = $value;
		}
		$query_params[] = $iid;

		$sql = "UPDATE ".get_table_name('popinfo').
			" SET changed = ".$this->db->now().", ".join(', ', $query_cols).
			" WHERE identity_id = ?";

		call_user_func_array(array($this->db, 'query'),
			array_merge(array($sql), $query_params));

		return;
	}

	function clean_session()
	{
		$all_cols = array('ext', 'email', 'extusername', 'extpd', 'popserver', 'popport', 'select_folder', 'select_smtp',
			'new_folder','fetch_days',  'use_newfolder', 'remove_mail', 'fetchpass', 'addsmtp', 'smtpport','smtppass','smtpuser',
			'ifssl', 'iftls', 'ifdefault', 'smtpdesc', 'smtpserver', 'save','ca_failure');

		foreach ($all_cols as $col) {
			if (isset($_SESSION[$col])) {
				unset($_SESSION[$col]);
			}
		}
		return true;
	}

	function init_session()
	{
		$_SESSION['ext'] = 1; 
		$_SESSION['popport'] = 110;
		$_SESSION['ifssl'] = 0;
		$_SESSION['iftls'] = 0;
		$_SESSION['ifdefault'] = 0;
		$_SESSION['select_folder'] = 'INBOX';
		$_SESSION['new_folder'] = '';
		$_SESSION['remove_mail'] = 0;
		$_SESSION['fetchpass'] = 0;
		$_SESSION['fetch_days'] = 7;
		$_SESSION['use_newfolder'] = 0;
		$_SESSION['addsmtp'] = 0;
		$_SESSION['smtpdesc'] = '';
		$_SESSION['smtpserver'] = '';
		$_SESSION['smtpport'] = 25;
		$_SESSION['smtpuser'] = '';
		$_SESSION['smtppass'] = '';
		$_SESSION['ca_failure'] = 0;
	}

	function get_account_record()
	{
		$all_cols = array('ext', 'email', 'extusername', 'extpd', 'popserver', 'popport', 'select_folder', 'select_smtp',
			'new_folder','fetch_days','use_newfolder', 'remove_mail', 'fetchpass', 'addsmtp',
			'smtpport', 'smtpuser', 'smtppass', 'ifssl', 'iftls', 'ifdefault', 'smtpdesc', 'smtpserver');
		foreach ($all_cols as $col) {
			if (isset($_SESSION[$col])) {
				$ACCOUNT_RECORD[$col] = $_SESSION[$col];
			}
		}

		return $ACCOUNT_RECORD;
	}

	function ca_failure_test($popserver,$port)
	{
		$connecto = $popserver.':'.$port;
		$descriptorspec = array(                                                                                                                 
			0 => array("pipe", "r"),  // stdin is a pipe that the child will read from                                                            
			1 => array("pipe", "w"),  // stdout is a pipe that the child will write to                                                            
			2 => array("file", "/var/packages/MailStation/target/roundcubemail/logs/error", "a") // stderr is a file to write to                  
		);                                                                                                                                       
		$cmd = '/usr/syno/bin/openssl s_client -verify -purpose -connect ';
		$cmd .= $connecto . ' -CApath ' . RCMAIL_EXT_DIR . '/.cert';  
		$cwd = NULL;                                                                                                                             
		$env = NULL;                                                                                                                             

		$process = @proc_open($cmd, $descriptorspec, $pipes, $cwd, $env);                                                                         

		if (is_resource($process)) {
			@fwrite($pipes[0], 'quit');

			@fclose($pipes[0]);

			$result = stream_get_contents($pipes[1]);

			@fclose($pipes[1]);

			@fclose($pipes[2]);

		}
		@proc_close($process);
		if ( 0 < preg_match("/Verify return code: ([\d]+) \(/",$result,$matches) && 0 == intval($matches[1])) {
			return false;
		}
		return true;
	}

	/**
	 * Connect to pop server test
	 *
	 * @param string pop server hostname
	 * @param string pop server port
	 * @return false when connect failure form server
	 */
	function popserver_connect_test($popserver,$port)
	{
		$errno = 0;
		$fp = @fsockopen($popserver, $port, $errno, $errstr, 10);
		if (!$fp) {
			$errno = ($errno==0)?500:$errno;
		}
		fclose($fp);
		return $errno;
	}

	function filter_popinfo($data)
	{
		$popinfo_arr = array('ext', 'extusername', 'extpd', 'popserver', 'popport',
			'ifssl', 'select_folder', 'remove_mail', 'select_smtp');
		$popinf = array();
		if (!is_array($data)) {
			raise_error(array(
				'code' => 500,
				'type' => 'php',
				'line' => __LINE__,
				'file' => __FILE__,
				'message' => "Failed to create new user"), true, false);
			return;
		}

		if (!isset($data['ext'])) { 
			return array($data, $popinfo);
		}   
		foreach($data as $key => $value) {
			if (in_array($key,$popinfo_arr)) {
				$popinfo[$key] = $data[$key];
				unset($data[$key]);
			}
		}
		return array($data, $popinfo);
	}


	function insert_popinfo($popinfo)
	{
		if (!isset($popinfo['ext'])) {
			return;
		}

		$insert_cols = $insert_values = array();
		foreach ((array)$popinfo as $col => $value) {
			$insert_cols[]   = $this->db->quoteIdentifier($col);
			$insert_values[] = $value;
		}

		$sql = "INSERT INTO ".get_table_name('popinfo').
			" (changed, ".join(', ', $insert_cols).")".
			" VALUES (".$this->db->now().", ".join(', ', array_pad(array(), sizeof($insert_values), '?')).")";

		call_user_func_array(array($this->db, 'query'),
			array_merge(array($sql), $insert_values));
		return;
	}

	function writerc($data, $days = null , $_iid = null)
	{
		$rcmail = rcmail::get_instance();
		$err = true;
		//$username = $this->get_full_username();
		$username = $this->user->get_username();
		$iid = ($_iid)?$_iid:get_input_value('_iid', RCUBE_INPUT_POST);
		$old_data = $this->get_popinfo($iid);
		$old_userdomain = preg_replace('/@/','.',$old_data['email']);
		$cmd = RCMAIL_FETCH_EXEC . ' ' . $username;

		//pre-modify
		system ( $cmd.' 1 '.escapeshellarg($old_userdomain));

		//modify
		if (!$this->write_fetchmailrc($data,$_iid)){
			$err = false;
		}	

		if ($data)
			$err = $this->write_procmailrc($data,$days); 

		// post-modify
		$userdomain = $data?preg_replace('/@/','.',$data['email']):$old_userdomain;
		system ($cmd.' 2 '.escapeshellarg($userdomain));


		// delete: modify idfile and restart daemon
		if (!$data)
			system ($cmd.' -3 '.escapeshellarg($old_data['extusername'].'@'.$old_data['popserver']));
		// add/modify: awakening daemon
		else
			system($cmd.' -4');

		return $err;
	}

	function get_full_username()
	{
		if (!isset($this->mail_conf)) {
			$this->mail_conf = $this->getMailServerConf();
		}

		$username = $this->rc->user->data["username"];
		if('ldap' == $this->mail_conf["account_type"] ) {
			$username = $username . "@" . $this->mail_conf["domain_name"];
		} else if ('win' == $this->mail_conf["account_type"] ) {
			$username = $this->mail_conf["domain_name"] . "\\" . $username;
		}
		return $username;
	}

	/**
	 * 
	 *
	 * @param string IMAP user name
	 * @return object rcube_user New user instance
	 */
	function write_fetchmailrc($data,$_iid=null)
	{

		$err = false;
		$rcmail = rcmail::get_instance();
		//$username = $this->get_full_username();
		$username = $this->user->get_username();
		$DsmUserName = $this->getRealUserName($username);
		$DsmUserDirectory = $this->getUserDirectory($username);
		$fetchfile = RCMAIL_EXT_DIR . '/' . $username . '_fetch';
		$fetchtemp = RCMAIL_EXT_DIR . '/fetchmailrc';
		$iid = ($_iid)?$_iid:get_input_value('_iid', RCUBE_INPUT_POST);
		$old_data = $this->rc->user->get_identity($iid);

		$content = '';
		if (file_exists($fetchfile)) {
			// "update"
			if (!$content = file_get_contents($fetchfile)) {
				return $err;
			}

			$pollblock = '/#### "'.$old_data['email'].'".*"'.$old_data['email'].'"\s/s';

			if (!$content = preg_replace($pollblock,'',$content)) {
				return $err;
			}
		}
		else {
			// "Add"

			if (!($content = file_get_contents($fetchtemp))) {
				return $err;
			}
			if (!$content = preg_replace("/_currentuserdirectory/",$DsmUserDirectory,$content)) {
				return $err;
			}
		}
		// fixed fetch period
		$period = $rcmail->config->get('extmailperiod',5);
		settype($period,"integer");
		$period = $period * 60 ; // min to sec.
		if (!$content = preg_replace("/set daemon \d+/", "set daemon ".$period, $content)) {
			return $err;
		}

		if($data) {
			$userdomain = preg_replace('/@/','.',$data['email']);
			$procfile = RCMAIL_EXT_DIR . '/' . $username . '.proc.' . $userdomain;
			$content .= '#### "'.$data['email']."\"\n";                                                      
			$content .= 'poll "'. $data['popserver']. '" with protocol POP3 ';
			if ($data['remove_mail']!=1)
				$content .= 'uidl ';
			$content .= 'and port '. $data['popport'].":\n";
			$content .= "\tuser \"".$data['extusername']."\" pass \"".$data['extpd']."\" is \"".$DsmUserName."\" here\n";
			if ($data['ifssl']==1) {
				$content .=  "\toptions ssl ";
			}
			$content .=	"\n";
			if ($data['remove_mail']==1)
				$content .=  "\tno keep\n";
			else
				$content .=  "\tkeep\n";
			$content .= "mda \"" .RCMAIL_PROC_EXEC ." -m \\'".$procfile."\\'\"\n";
			$content .= "#### \"".$data['email']."\"\n";
		}

		return $this->writefile($fetchfile,$content);
	}

	function write_procmailrc($data,$days)
	{
		$err = false;
		//$username = $this->get_full_username();
		$username = $this->user->get_username();
		$DsmUserDirectory = $this->getUserDirectory($username);
		$userdomain = preg_replace('/@/','.',$data['email']);
		$procfile = RCMAIL_EXT_DIR . '/' . $username . '.proc.' . $userdomain;
		$proctemp = RCMAIL_EXT_DIR . '/procmailrc';

		$content = '';
		if (!($content .= file_get_contents($proctemp))) {
			return $err;
		}
		if (!$content = preg_replace("/_currentuserdirectory/",$DsmUserDirectory,$content)) {
			return $err;
		}

		$mailpath = '';
		$mailpath = ($data['select_folder'] == 'INBOX')? '.Maildir/':'.Maildir/.'.preg_replace('/ /','\ ',$data['select_folder']).'/';
		$content .= $this->proc_data_limit($days);                                                      
		$content .= "\n:0\n".$mailpath."\n";                                                     
		return $this->writefile($procfile,$content);

	}

	function getRealUserName($username)
	{
		$cmd = RCMAIL_FETCH_EXEC . ' ' . escapeshellarg($username);
		@exec($cmd.' 4 ' , $uid, $retval);

		if (0 == $retval && isset($uid[0])) {
			$info_arr = posix_getpwuid($uid[0]);
		} else {
			return $username;
		}

		if (isset($info_arr['name'])) {
			return $info_arr['name'];
		} else {
			return $username;
		}
	}

	function getUserDirectory($username)
	{
		$cmd = RCMAIL_FETCH_EXEC . ' ' . escapeshellarg($username);
		@exec($cmd.' 5 ' , $Dir, $retval);
		if (0 == $retval && isset($Dir[0])) {
			return $Dir[0];
		} else {
			return $username;
		}
	}

	function proc_data_limit($days)
	{
		$mon = array("Jan","Feb","Mar","Apr","May","Jue","Jul","Aug","Sep","Oct","Nov","Dec");
		$content = '';
		if ($days < 0)
			return $content;
		$time_lower_bound = array();
		$time_lower_bound = getdate(time() - ($days * 24 * 60 * 60));


		$content = "\n:0\n* ^Date.*(1970"; 
		for ($y = 1971 ; $y < $time_lower_bound['year'] ; $y++)
			$content .= '|' . $y;
		$content .= ")\n/dev/null\n";

		$m = 0;
		if ($time_lower_bound['mon'] > 1) {
			$content .= "\n:0\n* ^Date.*(Jan"; 
			for ($m = 1 ; $m < $time_lower_bound['mon'] - 1 ; $m++)
				$content .= '|' . $mon[$m];
			$content .= ") ".$y."\n/dev/null\n";
		}

		$content .= "\n:0\n* ^Date.*(01"; 
		for ($d = 2 ; $d < $time_lower_bound['mday'] ; $d++)
			$content .= ($d < 10 )? ('|0'.$d) : ('|'.$d);
		$content .= ") ".$mon[$m]." ".$y."\n/dev/null\n";

		return $content;

	}

	function writefile($filepath,$content)
	{
		$fp = fopen($filepath, 'w');
		fwrite($fp, $content);
		fclose($fp);
		return true;
	}

	function getMailServerConf()
	{
		$cmd = RCMAIL_FETCH_EXEC . ' ' . escapeshellarg('http');
		@exec($cmd.' 6 ' , $Conf, $retval);
		$result = array();

		if (0 == $retval && isset($Conf[0])) {
			$result['account_type'] = $Conf[0];
			$result['domain_name'] = $Conf[1];
			return $result;
		} else {
			$result['account_type'] = 'local';
			$result['domain_name'] = '';
			return $result;
		}
	}

	function rcmail_create_folder($name)
	{
		$this->rc->storage->create_folder($name, true);
	}

	function extmail_delete($iid)
	{
		if (!isset($iid))
			return;

		$this->writerc('','-1',$iid);
		$this->rc->user->delete_identity($iid);

		return $iid;
	}

	function create_default_smtp($user_id)
	{
		$rcmail = rcmail::get_instance();

		// create new smtp records
		$record = array();
		$record['user_id'] = $user_id;
		$record['smtpdesc'] = $rcmail->config->get('smtp_server', 'localhost');
		$record['smtpserver'] = $rcmail->config->get('smtp_server', 'localhost');
		$record['smtpport'] = intval($rcmail->config->get('smtp_port', 25));
		$record['smtpuser'] = $rcmail->config->get('smtp_user');
		$record['smtppass'] = $rcmail->config->get('smtp_pass');
		$record['ifdefault'] = '1';
		//$rcmail->user->ID = $user_id;
		return $this->insert_smtp($record);

	}

	function after_create_user($id)
	{

		$dbh = rcmail::get_instance()->get_dbh();
		$rcmail = rcmail::get_instance();
		$MailServerConf = $this->getMailServerConf();
		if(!isset($MailServerConf)){
			raise_error(array(
				'code' => 500,
				'type' => 'php',
				'line' => __LINE__,
				'file' => __FILE__,
				'message' => "Failed to create new user, can't read MailServerConf"), true, false);
			return false;
		}

		$dbh->query(
			"INSERT INTO users_type". 
			" (user_id, account_type, domain_name)".
			" VALUES (?, ?, ?)",
				strip_newlines($id),
				strip_newlines($MailServerConf['account_type']),
				strip_newlines($MailServerConf['domain_name'])
			);

		$record = array();
		$record['user_id'] = $id;
		$record['smtpdesc'] = $rcmail->config->get('smtp_server', 'localhost');
		$record['smtpserver'] = $rcmail->config->get('smtp_server', 'localhost');
		$record['smtpport'] = intval($rcmail->config->get('smtp_port', 25));
		$record['smtpuser'] = $rcmail->config->get('smtp_user');
		$record['smtppass'] = $rcmail->config->get('smtp_pass');
		$record['ifdefault'] = '1';
		$this->userID = $id;

		return $this->insert_smtp($record);


	}

	function identity_delete($arg)
	{
		$this->writerc('','-1',$arg['id']);
	}

	function Is_AdminGroup()
	{
		$blAdmin = false;
		$username = $this->user->get_username();

		$cmd = RCMAIL_FETCH_EXEC . ' ' . $username . ' 0';

		$buffer = system($cmd,$blAdmin);
		return $blAdmin;
	}

	function extmail_email_check($attrib)
	{
		$email = get_input_value('_email', RCUBE_INPUT_GPC);
		$iid = get_input_value('_iid', RCUBE_INPUT_GPC);

		$identity = $this->get_identity_by_email($email);

		if ($identity&& $iid != $identity['identity_id']) {
			$this->rc->output->command('show_alert', $this->gettext(array('name' => 'mailstation.emailduplicate', 'vars' => array('email' => $email))));
			return;
		}

		$this->rc->output->command('extmail_submit');
	}

	function extmail_pop_check($attrib)
	{	
		$email = get_input_value('_email', RCUBE_INPUT_GPC);
		$server = get_input_value('_server', RCUBE_INPUT_GPC);
		$port = get_input_value('_port', RCUBE_INPUT_GPC);

		$identity = $this->get_identity_by_email($email);
		if ($identity) {
			$this->rc->output->command('show_alert', $this->gettext(array('name' => 'mailstation.emailduplicate', 'vars' => array('email' => $email))));
			return;
		}

		if ($this->popserver_connect_test($server, $port)) {
			$this->rc->output->command('show_alert', $this->gettext('connerror'));
			return;
		}

		$this->rc->output->command('extmail_submit');

	}

}
