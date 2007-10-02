<?php
/**
 * MyBB 1.2
 * Copyright � 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->mycode, "index.php?".SID."&amp;module=config/mycode");

if($mybb->input['action'] == "toggle_status")
{
	$query = $db->simple_select("mycode", "*", "cid='".intval($mybb->input['cid'])."'");
	$mycode = $db->fetch_array($query);
	
	if(!$mycode['cid'])
	{
		flash_message($lang->error_invalid_mycode, 'error');
		admin_redirect("index.php?".SID."&module=config/mycode");
	}

	if($mycode['active'] == 1)
	{
		$new_status = 0;
		$phrase = $lang->success_deactivated_mycode;
	}
	else
	{
		$new_status = 1;
		$phrase = $lang->success_activated_mycode;
	}
	$mycode = array(
		'active' => $new_status,
	);

	$db->update_query("mycode", $mycode, "cid='".intval($mybb->input['cid'])."'");

	$cache->update_mycode();

	// Log admin action
	log_admin_action($mycode['cid'], $mycode['title'], $new_status);

	flash_message($phrase, 'success');
	admin_redirect('index.php?'.SID.'&module=config/mycode');
}

if($mybb->input['action'] == "xmlhttp_test_mycode" && $mybb->request_method == "post")
{
	// Send no cache headers
	header("Expires: Sat, 1 Jan 2000 01:00:00 GMT");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
	header("Cache-Control: no-cache, must-revalidate");
	header("Pragma: no-cache");
	header("Content-type: text/html");
	
	$sandbox = test_regex($mybb->input['regex'], $mybb->input['replacement'], $mybb->input['test_value']);
	
	echo $sandbox['actual'];
	exit;
}

if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}

		if(!trim($mybb->input['regex']))
		{
			$errors[] = $lang->error_missing_regex;
		}
		
		if(!trim($mybb->input['replacement']))
		{
			$errors[] = $lang->error_missing_replacement;
		}
		
		if($mybb->input['test'])
		{
			$errors[] = $lang->changes_not_saved;
			$sandbox = test_regex($mybb->input['regex'], $mybb->input['replacement'], $mybb->input['test_value']);
		}

		if(!$errors)
		{
			$new_mycode = array(
				'title'	=> $db->escape_string($mybb->input['title']),
				'description' => $db->escape_string($mybb->input['description']),
				'regex' => $db->escape_string($mybb->input['regex']),
				'replacement' => $db->escape_string($mybb->input['replacement']),
				'active' => $db->escape_string($mybb->input['active']),
				'parseorder' => intval($mybb->input['parseorder'])
			);

			$cid = $db->insert_query("mycode", $new_mycode);

			$cache->update_mycode();

			// Log admin action
			log_admin_action($cid, $mybb->input['title']);

			flash_message($lang->success_added_mycode, 'success');
			admin_redirect('index.php?'.SID.'&module=config/mycode');
		}
	}

	$page->add_breadcrumb_item($lang->add_mycode);
	$page->output_header($lang->custom_mycode." - ".$lang->add_mycode);

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['active'] = 1;
	}

	$form = new Form("index.php?".SID."&amp;module=config/mycode&amp;action=add", "post", "add");
	$form_container = new FormContainer($lang->add_mycode);
	$form_container->output_row($lang->title." <em>*</em>", '', $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->short_description, '', $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
	$form_container->output_row($lang->regular_expression." <em>*</em>", $lang->regular_expression_desc.'<br /><strong>'.$lang->example.'</strong> \[b\](.*?)\[/b\]', $form->generate_text_area('regex', $mybb->input['regex'], array('id' => 'regex')), 'regex');
	$form_container->output_row($lang->replacement." <em>*</em>", $lang->replacement_desc.'<br /><strong>'.$lang->example.'</strong> &lt;strong&gt;$1&lt;/strong&gt;', $form->generate_text_area('replacement', $mybb->input['replacement'], array('id' => 'replacement')), 'replacement');
	$form_container->output_row($lang->enabled." <em>*</em>", '', $form->generate_yes_no_radio('active', $mybb->input['active']));
	$form_container->output_row($lang->parse_order, $lang->parse_order_desc, $form->generate_text_box('parseorder', $mybb->input['parseorder'], array('id' => 'parseorder')), 'parseorder');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_mycode);
	$form->output_submit_wrapper($buttons);
	
	// Sandbox
	echo "<br />\n";
	$form_container = new FormContainer($lang->sandbox);
	$form_container->output_row($lang->sandbox_desc);
	$form_container->output_row($lang->test_value, $lang->test_value_desc, $form->generate_text_area('test_value', $mybb->input['test_value'], array('id' => 'test_value'))."<br />".$form->generate_submit_button($lang->test, array('id' => 'test', 'name' => 'test')), 'test_value');
	$form_container->output_row($lang->result_html, $lang->result_html_desc, $form->generate_text_area('result_html', $sandbox['html'], array('id' => 'result_html', 'disabled' => 1)), 'result_html');
	$form_container->output_row($lang->result_actual, $lang->result_actual_desc, "<div id=\"result_actual\">{$sandbox['actual']}</div>");
	$form_container->end();
	echo '<script type="text/javascript" src="./jscripts/mycode_sandbox.js"></script>';
	echo '<script type="text/javascript">
//<![CDATA[
Event.observe(window, "load", function() {
    new MyCodeSandbox("index.php?'.SID.'&module=config/mycode&action=xmlhttp_test_mycode", $("test"), $("regex"), $("replacement"), $("test_value"), $("result_html"), $("result_actual"));
});
//]]>
</script>';

	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("mycode", "*", "cid='".intval($mybb->input['cid'])."'");
	$mycode = $db->fetch_array($query);
	
	if(!$mycode['cid'])
	{
		flash_message($lang->error_invalid_mycode, 'error');
		admin_redirect("index.php?".SID."&module=config/mycode");
	}

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}

		if(!trim($mybb->input['regex']))
		{
			$errors[] = $lang->error_missing_regex;
		}
		
		if(!trim($mybb->input['replacement']))
		{
			$errors[] = $lang->error_missing_replacement;
		}
		
		if($mybb->input['test'])
		{
			$errors[] = $lang->changes_not_saved;
			$sandbox = test_regex($mybb->input['regex'], $mybb->input['replacement'], $mybb->input['test_value']);
		}

		if(!$errors)
		{
			$mycode = array(
				'title'	=> $db->escape_string($mybb->input['title']),
				'description' => $db->escape_string($mybb->input['description']),
				'regex' => $db->escape_string($mybb->input['regex']),
				'replacement' => $db->escape_string($mybb->input['replacement']),
				'active' => $db->escape_string($mybb->input['active']),
				'parseorder' => intval($mybb->input['parseorder'])
			);

			$db->update_query("mycode", $mycode, "cid='".intval($mybb->input['cid'])."'");

			$cache->update_mycode();

			// Log admin action
			log_admin_action($mycode['cid'], $mybb->input['title']);

			flash_message($lang->success_updated_mycode, 'success');
			admin_redirect('index.php?'.SID.'&module=config/mycode');
		}
	}
	
	$page->add_breadcrumb_item($lang->edit_mycode);
	$page->output_header($lang->custom_mycode." - ".$lang->edit_mycode);

	$form = new Form("index.php?".SID."&amp;module=config/mycode&amp;action=edit", "post", "edit");
	echo $form->generate_hidden_field('cid', $mycode['cid']);

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = $mycode;
	}

	$form_container = new FormContainer($lang->edit_mycode);
	$form_container->output_row($lang->title." <em>*</em>", '', $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->short_description, '', $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
	$form_container->output_row($lang->regular_expression." <em>*</em>", $lang->regular_expression_desc.'<br /><strong>'.$lang->example.'</strong> \[b\](.*?)\[/b\]', $form->generate_text_area('regex', $mybb->input['regex'], array('id' => 'regex')), 'regex');
	$form_container->output_row($lang->replacement." <em>*</em>", $lang->replacement_desc.'<br /><strong>'.$lang->example.'</strong> &lt;strong&gt;$1&lt;/strong&gt;', $form->generate_text_area('replacement', $mybb->input['replacement'], array('id' => 'replacement')), 'replacement');
	$form_container->output_row($lang->enabled." <em>*</em>", '', $form->generate_yes_no_radio('active', $mybb->input['active']));
	$form_container->output_row($lang->parse_order, $lang->parse_order_desc, $form->generate_text_box('parseorder', $mybb->input['parseorder'], array('id' => 'parseorder')), 'parseorder');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_mycode);

	$form->output_submit_wrapper($buttons);

	// Sandbox
	echo "<br />\n";
	$form_container = new FormContainer($lang->sandbox);
	$form_container->output_row($lang->sandbox_desc);
	$form_container->output_row($lang->test_value, $lang->test_value_desc, $form->generate_text_area('test_value', $mybb->input['test_value'], array('id' => 'test_value'))."<br />".$form->generate_submit_button($lang->test, array('id' => 'test', 'name' => 'test')), 'test_value');
	$form_container->output_row($lang->result_html, $lang->result_html_desc, $form->generate_text_area('result_html', $sandbox['html'], array('id' => 'result_html', 'disabled' => 1)), 'result_html');
	$form_container->output_row($lang->result_actual, $lang->result_actual_desc, "<div id=\"result_actual\">{$sandbox['actual']}</div>");
	$form_container->end();
	echo '<script type="text/javascript" src="./jscripts/mycode_sandbox.js"></script>';
	echo '<script type="text/javascript">

Event.observe(window, "load", function() {
//<![CDATA[
    new MyCodeSandbox("index.php?'.SID.'&module=config/mycode&action=xmlhttp_test_mycode", $("test"), $("regex"), $("replacement"), $("test_value"), $("result_html"), $("result_actual"));
});
//]]>
</script>';

	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("mycode", "*", "cid='".intval($mybb->input['cid'])."'");
	$mycode = $db->fetch_array($query);
	
	if(!$mycode['cid'])
	{
		flash_message($lang->error_invalid_mycode, 'error');
		admin_redirect("index.php?".SID."&module=config/mycode");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=config/mycode");
	}

	if($mybb->request_method == "post")
	{
		$db->delete_query("mycode", "cid='{$mycode['cid']}'");

		$cache->update_mycode();

		// Log admin action
		log_admin_action($mycode['title']);

		flash_message($lang->success_deleted_mycode, 'success');
		admin_redirect("index.php?".SID."&module=config/mycode");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&amp;module=config/mycode&amp;action=delete&amp;cid={$mycode['cid']}", $lang->confirm_mycode_deletion);
	}
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->custom_mycode);

	$sub_tabs['mycode'] = array(
		'title'	=> $lang->mycode,
		'link' => "index.php?".SID."&amp;module=config/mycode",
		'description' => $lang->mycode_desc
	);

	$sub_tabs['add_new_mycode'] = array(
		'title'	=> $lang->add_new_mycode,
		'link' => "index.php?".SID."&amp;module=config/mycode&amp;action=add"
	);

	$page->output_nav_tabs($sub_tabs, 'mycode');

	$table = new Table;
	$table->construct_header($lang->title);
	$table->construct_header($lang->controls, array('class' => 'align_center', 'width' => 150));

	$query = $db->simple_select("mycode", "*", "", array('order_by' => 'parseorder'));
	while($mycode = $db->fetch_array($query))
	{
		if($mycode['active'] == 1)
		{
			$phrase = $lang->deactivate_mycode;
			$indicator = '';
		}
		else
		{
			$phrase = $lang->activate_mycode;
			$indicator = "<div class=\"float_right\"><small>{$lang->deactivated}</small></div>";
		}
		$table->construct_cell("{$indicator}<strong><a href=\"index.php?".SID."&amp;module=config/mycode&amp;action=edit&amp;cid={$mycode['cid']}\">{$mycode['title']}</a></strong><br /><small>{$mycode['description']}</small>");

		$popup = new PopupMenu("mycode_{$mycode['cid']}", $lang->options);
		$popup->add_item($lang->edit_mycode, "index.php?".SID."&amp;module=config/mycode&amp;action=edit&amp;cid={$mycode['cid']}");
		$popup->add_item($phrase, "index.php?".SID."&amp;module=config/mycode&amp;action=toggle_status&amp;cid={$mycode['cid']}");
		$popup->add_item($lang->delete_mycode, "index.php?".SID."&amp;module=config/mycode&amp;action=delete&amp;cid={$mycode['cid']}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_mycode_deletion}')");
		$table->construct_cell($popup->fetch(), array('class' => 'align_center'));
		$table->construct_row();
	}
	
	if(count($table->rows) == 0)
	{
		$table->construct_cell($lang->no_mycode, array('colspan' => 2));
		$table->construct_row();
	}

	$table->output($lang->custom_mycode);

	$page->output_footer();
}

function test_regex($regex, $replacement, $test)
{
	$array = array();
	$array['actual'] = @preg_replace("#".$regex."#si", $replacement, $test);
	$array['html'] = htmlspecialchars($array['actual']);
	return $array;
}
?>