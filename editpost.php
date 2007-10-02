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

define("IN_MYBB", 1);

$templatelist = "editpost,previewpost,redirect_postedited,loginbox,posticons,changeuserbox,attachment,posticons,codebuttons,smilieinsert,post_attachments_attachment_postinsert,post_attachments_attachment_mod_approve,post_attachments_attachment_unapproved,post_attachments_attachment_mod_unapprove,post_attachments_attachment,post_attachments_new,post_attachments,newthread_postpoll,editpost_disablesmilies";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_upload.php";

// Load global language phrases
$lang->load("editpost");

// No permission for guests
if(!$mybb->user['uid'])
{
	error_no_permission();
}

// Get post info
$pid = intval($mybb->input['pid']);

$query = $db->simple_select("posts", "*", "pid='$pid'");
$post = $db->fetch_array($query);

if(!$post['pid'])
{
	error($lang->error_invalidpost);
}

// Get thread info
$tid = $post['tid'];
$thread = get_thread($tid);

if(!$thread['tid'])
{
	error($lang->error_invalidthread);
}

$thread['subject'] = htmlspecialchars_uni($thread['subject']);

// Get forum info
$fid = $post['fid'];
$forum = get_forum($fid);
if(!$forum || $forum['type'] != "f")
{
	error($lang->error_closedinvalidforum);
}
if($forum['open'] == 0 || $mybb->user['suspendposting'] == 1)
{
	error_no_permission();
}

// Make navigation
build_forum_breadcrumb($fid);
add_breadcrumb($thread['subject'], get_thread_link($thread['tid']));
add_breadcrumb($lang->nav_editpost);

$forumpermissions = forum_permissions($fid);


if($mybb->settings['bbcodeinserter'] != 0 && $forum['allowmycode'] != 0 && $mybb->user['showcodebuttons'] != 0)
{
	$codebuttons = build_mycode_inserter();
}
if($mybb->settings['smilieinserter'] != 0)
{
	$smilieinserter = build_clickable_smilies();
}

if(!$mybb->input['action'] || $mybb->input['previewpost'])
{
	$mybb->input['action'] = "editpost";
}

if($mybb->input['action'] == "deletepost" && $mybb->request_method == "post")
{
	if(!is_moderator($fid, "candeleteposts"))
	{
		if($thread['closed'] == 1)
		{
			error($lang->redirect_threadclosed);
		}
		if($forumpermissions['candeleteposts'] == 0)
		{
			error_no_permission();
		}
		if($mybb->user['uid'] != $post['uid'])
		{
			error_no_permission();
		}
	}
}
else
{
	if(!is_moderator($fid, "caneditposts"))
	{
		if($thread['closed'] == 1)
		{
			error($lang->redirect_threadclosed);
		}
		if($forumpermissions['caneditposts'] == 0)
		{
			error_no_permission();
		}
		if($mybb->user['uid'] != $post['uid'])
		{
			error_no_permission();
		}
		// Edit time limit
		$time = TIME_NOW;
		if($mybb->settings['edittimelimit'] != 0 && $post['dateline'] < ($time-($mybb->settings['edittimelimit']*60)))
		{
			$lang->edit_time_limit = sprintf($lang->edit_time_limit, $mybb->settings['edittimelimit']);
			error($lang->edit_time_limit);
		}
	}
}

// Check if this forum is password protected and we have a valid password
check_forum_password($forum['fid']);

if((empty($_POST) && empty($_FILES)) && $mybb->input['proccessed'] == '1')
{
	error($lang->error_cannot_upload_php_post);
}

if(!$mybb->input['attachmentaid'] && ($mybb->input['newattachment'] || ($mybb->input['action'] == "do_editpost" && $mybb->input['submit'] && $_FILES['attachment'])))
{
	// If there's an attachment, check it and upload it
	if($_FILES['attachment']['size'] > 0 && $forumpermissions['canpostattachments'] != 0)
	{
		$attachedfile = upload_attachment($_FILES['attachment']);
	}
	if($attachedfile['error'])
	{
		eval("\$attacherror = \"".$templates->get("error_attacherror")."\";");
		$mybb->input['action'] = "editpost";
	}
	if(!$mybb->input['submit'])
	{
		$mybb->input['action'] = "editpost";
	}
}

if($mybb->input['attachmentaid'] && isset($mybb->input['attachmentact'])) // Lets remove/approve/unapprove the attachment
{ 
	$mybb->input['attachmentaid'] = intval($mybb->input['attachmentaid']);
	if($mybb->input['attachmentact'] == "remove")
	{
		remove_attachment($pid, $mybb->input['posthash'], $mybb->input['attachmentaid']);
	}
	elseif($mybb->input['attachmentact'] == "approve")
	{
		$update_sql = array("visible" => 1);
		$db->update_query("attachments", $update_sql, "aid='{$mybb->input['attachmentaid']}'");
	}
	elseif($mybb->input['attachmentact'] == "unapprove")
	{
		$update_sql = array("visible" => 0);
		$db->update_query("attachments", $update_sql, "aid='{$mybb->input['attachmentaid']}'");
	}
	if(!$mybb->input['submit'])
	{
		$mybb->input['action'] = "editpost";
	}
}

if($mybb->input['action'] == "deletepost" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("editpost_deletepost");

	if($mybb->input['delete'] == 1)
	{
		$query = $db->simple_select("posts", "pid", "tid='{$tid}'", array("limit" => 1, "order_by" => "dateline", "order_dir" => "asc"));
		$firstcheck = $db->fetch_array($query);
		if($firstcheck['pid'] == $pid)
		{
			$firstpost = 1;
		}
		else
		{
			$firstpost = 0;
		}
		$modlogdata['fid'] = $fid;
		$modlogdata['tid'] = $tid;
		if($firstpost)
		{
			if($forumpermissions['candeletethreads'] == 1)
			{
				delete_thread($tid);
				mark_reports($tid, "thread");
				log_moderator_action($modlogdata, $lang->thread_deleted);
				redirect(get_forum_link($fid), $lang->redirect_threaddeleted);
			}
			else
			{
				error_no_permission();
			}
		}
		else
		{
			if($forumpermissions['candeleteposts'] == 1)
			{
				// Select the first post before this
				delete_post($pid, $tid);
				mark_reports($pid, "post");
				log_moderator_action($modlogdata, $lang->post_deleted);
				$query = $db->simple_select("posts", "pid", "tid='{$tid}' AND dateline <= '{$post['dateline']}'", array("limit" => 1, "order_by" => "dateline", "order_dir" => "desc"));
				$next_post = $db->fetch_array($query);
				if($next_post['pid'])
				{
					$redirect = get_post_link($next_post['pid'], $tid)."#pid{$next_post['pid']}";
				}
				else
				{
					$redirect = get_thread_link($tid);
				}
				redirect($redirect, $lang->redirect_postdeleted);
			}
			else
			{
				error_no_permission();
			}
		}
	}
	else
	{
		error($lang->redirect_nodelete);
	}
}

if($mybb->input['action'] == "do_editpost" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("editpost_do_editpost_start");

	// Set up posthandler.
	require_once MYBB_ROOT."inc/datahandlers/post.php";
	$posthandler = new PostDataHandler("update");
	$posthandler->action = "post";

	// Set the post data that came from the input to the $post array.
	$post = array(
		"pid" => $mybb->input['pid'],
		"subject" => $mybb->input['subject'],
		"icon" => $mybb->input['icon'],
		"uid" => $mybb->user['uid'],
		"username" => $mybb->user['username'],
		"edit_uid" => $mybb->user['uid'],
		"message" => $mybb->input['message'],
	);

	// Set up the post options from the input.
	$post['options'] = array(
		"signature" => $mybb->input['postoptions']['signature'],
		"subscriptionmethod" => $mybb->input['postoptions']['subscriptionmethod'],
		"disablesmilies" => $mybb->input['postoptions']['disablesmilies']
	);

	$posthandler->set_data($post);

	// Now let the post handler do all the hard work.
	if(!$posthandler->validate_post())
	{
		$post_errors = $posthandler->get_friendly_errors();
		$post_errors = inline_error($post_errors);
		$mybb->input['action'] = "editpost";
	}
	// No errors were found, we can call the update method.
	else
	{
		$postinfo = $posthandler->update_post();
		$visible = $postinfo['visible'];
		$first_post = $postinfo['first_post'];

		// Help keep our attachments table clean.
		$db->delete_query("attachments", "filename='' OR filesize<1");

		// Did the user choose to post a poll? Redirect them to the poll posting page.
		if($mybb->input['postpoll'] && $forumpermissions['canpostpolls'])
		{
			$url = "polls.php?action=newpoll&tid=$tid&polloptions=".$mybb->input['numpolloptions'];
			$lang->redirect_postedited = $lang->redirect_postedited_poll;
		}
		else if($visible == 0 && $first_post)
		{
			// Moderated post
			$lang->redirect_postedited .= $lang->redirect_thread_moderation;
			$url = get_forum_link($fid);
		}
		else if($visible == 0)
		{
			$lang->redirect_postedited .= $lang->redirect_post_moderation;
			$url = get_thread_link($tid);
		}
		// Otherwise, send them back to their post
		else
		{
			$lang->redirect_postedited .= $lang->redirect_postedited_redirect;
			$url = get_post_link($pid, $tid)."#pid{$pid}";
		}
		$plugins->run_hooks("editpost_do_editpost_end");

		redirect($url, $lang->redirect_postedited);
	}
}

if(!$mybb->input['action'] || $mybb->input['action'] == "editpost")
{
	$plugins->run_hooks("editpost_start");

	if(!$mybb->input['previewpost'])
	{
		$icon = $post['icon'];
	}

	if($forum['allowpicons'] != 0)
	{
		$posticons = get_post_icons();
	}

	if($mybb->user['uid'] != 0)
	{
		eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");
	}
	else
	{
		eval("\$loginbox = \"".$templates->get("loginbox")."\";");
	}

	// Setup a unique posthash for attachment management
	$query = $db->simple_select("posts", "posthash", "pid='{$pid}'");
	$posthash = $db->fetch_field($query, "posthash");

	$bgcolor = "trow1";
	if($forumpermissions['canpostattachments'] != 0)
	{ // Get a listing of the current attachments, if there are any
		$attachcount = 0;
		if($posthash)
		{
			$posthash_query = "posthash='{$posthash}' OR ";
		}
		else
		{
			$posthash_query = "";
		}
		$query = $db->simple_select("attachments", "*", "{$posthash_query}pid='{$pid}'");
		$attachments = '';
		while($attachment = $db->fetch_array($query))
		{
			$attachment['size'] = get_friendly_size($attachment['filesize']);
			$attachment['icon'] = get_attachment_icon(get_extension($attachment['filename']));
			if($forum['allowmycode'] != 0)
			{
				eval("\$postinsert = \"".$templates->get("post_attachments_attachment_postinsert")."\";");
			}
			// Moderating options
			$attach_mod_options = '';
			if(is_moderator($fid))
			{
				if($attachment['visible'] == 1)
				{
					eval("\$attach_mod_options = \"".$templates->get("post_attachments_attachment_mod_unapprove")."\";");
				}
				else
				{
					eval("\$attach_mod_options = \"".$templates->get("post_attachments_attachment_mod_approve")."\";");
				}
			}
			if($attachment['visible'] != 1)
			{
				eval("\$attachments .= \"".$templates->get("post_attachments_attachment_unapproved")."\";");
			}
			else
			{
				eval("\$attachments .= \"".$templates->get("post_attachments_attachment")."\";");
			}
			$attachcount++;
		}
		$query = $db->simple_select("attachments", "SUM(filesize) AS ausage", "uid='".$mybb->user['uid']."'");
		$usage = $db->fetch_array($query);
		if($usage['ausage'] > ($mybb->usergroup['attachquota']*1024) && $mybb->usergroup['attachquota'] != 0)
		{
			$noshowattach = 1;
		}
		if($mybb->usergroup['attachquota'] == 0)
		{
			$friendlyquota = $lang->unlimited;
		}
		else
		{
			$friendlyquota = get_friendly_size($mybb->usergroup['attachquota']*1024);
		}
		$friendlyusage = get_friendly_size($usage['ausage']);
		$lang->attach_quota = sprintf($lang->attach_quota, $friendlyusage, $friendlyquota);
		if($mybb->settings['maxattachments'] == 0 || ($mybb->settings['maxattachments'] != 0 && $attachcount <= $mybb->settings['maxattachments']) && !$noshowattach)
		{
			eval("\$newattach = \"".$templates->get("post_attachments_new")."\";");
		}
		eval("\$attachbox = \"".$templates->get("post_attachments")."\";");
	}
	if(!$mybb->input['attachmentaid'] && !$mybb->input['newattachment'] && !$mybb->input['previewpost'] && !$maximageserror)
	{
		$message = $post['message'];
		$subject = $post['subject'];
	}
	else
	{
		$message = $mybb->input['message'];
		$subject = $mybb->input['subject'];
	}

	$query = $db->simple_select("posts", "*", "tid='{$tid}'", array("limit" => 1, "order_by" => "dateline", "order_dir" => "asc"));
	$firstcheck = $db->fetch_array($query);
	if($firstcheck['pid'] == $pid && $forumpermissions['canpostpolls'] != 0 && $thread['poll'] < 1)
	{
		$lang->max_options = sprintf($lang->max_options, $mybb->settings['maxpolloptions']);
		$numpolloptions = "2";
		eval("\$pollbox = \"".$templates->get("newthread_postpoll")."\";");
	}

	if($mybb->input['previewpost'] || $post_errors)
	{
		// Set up posthandler.
		require_once MYBB_ROOT."inc/datahandlers/post.php";
		$posthandler = new PostDataHandler("update");
		$posthandler->action = "post";
	
		// Set the post data that came from the input to the $post array.
		$post = array(
			"pid" => $mybb->input['pid'],
			"subject" => $mybb->input['subject'],
			"icon" => $mybb->input['icon'],
			"uid" => $post['uid'],
			"edit_uid" => $mybb->user['uid'],
			"message" => $mybb->input['message'],
		);

		if(!$mybb->input['previewpost'])
		{
			$post['uid'] = $mybb->user['uid'];
			$post['username'] = $mybb->user['username'];
		}
	
		// Set up the post options from the input.
		$post['options'] = array(
			"signature" => $mybb->input['postoptions']['signature'],
			"emailnotify" => $mybb->input['postoptions']['emailnotify'],
			"disablesmilies" => $mybb->input['postoptions']['disablesmilies']
		);
	
		$posthandler->set_data($post);
	
		// Now let the post handler do all the hard work.
		if(!$posthandler->validate_post())
		{
			$post_errors = $posthandler->get_friendly_errors();
			$post_errors = inline_error($post_errors);
			$mybb->input['action'] = "editpost";
			$mybb->input['previewpost'] = 0;
		}
		else
		{
			$previewmessage = $message;
			$message = htmlspecialchars_uni($message);
			$subject = htmlspecialchars_uni($subject);

			$postoptions = $mybb->input['postoptions'];

			if($postoptions['signature'] == 1)
			{
				$postoptionschecked['signature'] = " checked=\"checked\"";
			}

			if($postoptions['subscriptionmethod'] == "none")
			{
				$postoptions_subscriptionmethod_none = "selected=\"selected\"";
			}
			else if($postoptions['subscriptionmethod'] == "instant")
			{
				$postoptions_subscriptionmethod_instant = "selected=\"selected\"";
			}

			if($postoptions['disablesmilies'] == 1)
			{
				$postoptionschecked['disablesmilies'] = " checked=\"checked\"";
			}
		}
	}

	if($mybb->input['previewpost'])
	{
		// Figure out the poster's other information.
		$query = $db->query("
			SELECT u.*, f.*, p.dateline
			FROM ".TABLE_PREFIX."users u
			LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
			LEFT JOIN ".TABLE_PREFIX."posts p ON (p.uid=u.uid)
			WHERE u.uid='{$post['uid']}' AND p.pid='{$pid}'
			LIMIT 1
		");
		$postinfo = $db->fetch_array($query);

		$query = $db->simple_select("attachments", "*", "pid='{$pid}'");
		while($attachment = $db->fetch_array($query))
		{
			$attachcache[0][$attachment['aid']] = $attachment;
		}

		// Set the values of the post info array.
		$postinfo['userusername'] = $postinfo['username'];
		$postinfo['message'] = $previewmessage;
		$postinfo['subject'] = $subject;
		$postinfo['icon'] = $icon;
		$postinfo['smilieoff'] = $postoptions['disablesmilies'];

		$postbit = build_postbit($postinfo, 1);
		eval("\$preview = \"".$templates->get("previewpost")."\";");
	}
	else if(!$post_errors)
	{
		$message = htmlspecialchars_uni($message);
		$subject = htmlspecialchars_uni($subject);

		if($post['includesig'] != 0)
		{
			$postoptionschecked['signature'] = " checked=\"checked\"";
		}

		if($post['smilieoff'] == 1)
		{
			$postoptionschecked['disablesmilies'] = " checked=\"checked\"";
		}

		$query = $db->simple_select("threadsubscriptions", "notification", "tid='{$tid}' AND uid='{$mybb->user['uid']}'");
		if($db->num_rows($query) > 0)
		{
			$notification = $db->fetch_field($query, 'notification');

			if($notification ==  0)
			{
				$postoptions_subscriptionmethod_none = "selected=\"selected\"";
			}
			else if($notification == 1)
			{
				$postoptions_subscriptionmethod_instant = "selected=\"selected\"";
			}
		}
	}

	// Fetch subscription select box
	eval("\$subscriptionmethod = \"".$templates->get("post_subscription_method")."\";");

	// Can we disable smilies or are they disabled already?
	if($forum['allowsmilies'] != 0)
	{
		eval("\$disablesmilies = \"".$templates->get("editpost_disablesmilies")."\";");
	}
	else
	{
		$disablesmilies = "<input type=\"hidden\" name=\"postoptions[disablesmilies]\" value=\"no\" />";
	}

	$plugins->run_hooks("editpost_end");

	eval("\$editpost = \"".$templates->get("editpost")."\";");
	output_page($editpost);
}
?>
