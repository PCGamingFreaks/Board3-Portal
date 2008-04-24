<?php

/**
*
* @package - Board3portal
* @version $Id$
* @copyright (c) kevin / saint ( http://www.board3.de/ ), (c) Ice, (c) nickvergessen ( http://www.flying-bits.org/ ), (c) redbull254 ( http://www.digitalfotografie-foren.de )
* @based on: phpBB3 Portal by Sevdin Filiz, www.phpbb3portal.com
* @license http://opensource.org/licenses/gpl-license.php GNU Public License 
*
*/

if (!defined('IN_PHPBB'))
{
   exit;
}

if (!defined('IN_PORTAL'))
{
   exit;
}

//
// Fetch Posts for news from portal/includes/functions.php
//
$template->assign_vars(array(
	'NEWEST_POST_IMG'			=> $user->img('icon_topic_newest', 'VIEW_NEWEST_POST'),
	'READ_POST_IMG'				=> $user->img('icon_topic_latest', 'VIEW_NEWEST_POST'),
	'GOTO_PAGE_IMG'				=> $user->img('icon_post_target', 'GOTO_PAGE'),
	'S_DISPLAY_NEWS'			=> true,
));

$news = request_var('news', -1);

$user->add_lang('viewforum');
$start = request_var('np', 0);
$start = ($start < 0) ? 0 : $start;

// Pagnition
if ( $portal_config['portal_news_archive'] )
{
	$permissions = $portal_config['portal_news_permissions'];
	$forum_from = $portal_config['portal_news_forum'];

	$forum_from = ( strpos($forum_from, ',') !== FALSE ) ? explode(',', $forum_from) : (($forum_from != '') ? array($forum_from) : array());

	$str_where = '';

	if( $permissions == TRUE )
	{
		$disallow_access = array_unique(array_keys($auth->acl_getf('!f_read', true)));
	} else {
		$disallow_access = array();
	}
	
	if( sizeof($forum_from) )
	{
		$disallow_access = array_diff($forum_from, $disallow_access);		
		if( !sizeof($disallow_access) )
		{
			return array();
		}
		
		foreach( $disallow_access as $acc_id)
		{
			$acc_id = (int) $acc_id;
			$str_where .= "forum_id = $acc_id OR ";
		}
	}
	else
	{
		foreach( $disallow_access as $acc_id )
		{
			$acc_id = (int) $acc_id;
			$str_where .= "forum_id <> $acc_id AND ";
		}
	}

    $str_where = ( strlen($str_where) > 0 ) ? 'AND (' . trim(substr($str_where, 0, -4)) . ')' : '';

	$topic_type = ($portal_config['portal_show_all_news']) ? '( topic_type <> ' . POST_ANNOUNCE . ' ) AND ( topic_type <> ' . POST_GLOBAL . ')' : 'topic_type = ' . POST_NORMAL;

	$sql = 'SELECT COUNT(topic_id) AS num_topics
		FROM ' . TOPICS_TABLE . '
		WHERE ' . $topic_type . '
			AND topic_approved = 1
			AND topic_moved_id = 0
			' . $str_where;
		$result = $db->sql_query($sql);
		$total_news = (int) $db->sql_fetchfield('num_topics');
		$db->sql_freeresult($result);
}

if($news < 0)
{
	$fetch_news = phpbb_fetch_posts($portal_config['portal_news_forum'], $portal_config['portal_news_permissions'], $portal_config['portal_number_of_news'], $portal_config['portal_news_length'], 0, ($portal_config['portal_show_all_news']) ? 'news_all' : 'news', $start);

	if (count($fetch_news) == 0)
	{
		$template->assign_block_vars('news_row', array(
			'S_NO_TOPICS'	=> true,
			'S_NOT_LAST'	=> false
		));
	}
	else
	{
		$count = count($fetch_news)-1;
		for ($i = 0; $i < $count; $i++)
		{
			if( isset($fetch_news[$i]['striped']) && $fetch_news[$i]['striped'] == true )
			{
				$open_bracket = '[ ';
				$close_bracket = ' ]';
				$read_full = $user->lang['READ_FULL'];
			}
			else
			{
				$open_bracket = '';
				$close_bracket = '';
				$read_full = '';
			}
			// unread?
			$forum_id = $fetch_news[$i]['forum_id'];
			$topic_id = $fetch_news[$i]['topic_id'];
			$topic_tracking_info = get_complete_topic_tracking($forum_id, $topic_id, $global_announce_list = false);
			$unread_topic = (isset($topic_tracking_info[$topic_id]) && $fetch_news[$i]['topic_last_post_time'] > $topic_tracking_info[$topic_id]) ? true : false;
			
			$read_full_url = (isset($_GET['np'])) ? 'np='. $start . '&amp;news=' . $i . '#n' . $i : 'news=' . $i . '#n' . $i;
			$view_topic_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . (($fetch_news[$i]['forum_id']) ? $fetch_news[$i]['forum_id'] : $forum_id) . '&amp;t=' . $topic_id);
			if ( $portal_config['portal_news_archive'] )
			{
				$pagination = generate_portal_pagination(append_sid("{$phpbb_root_path}portal.$phpEx"), $total_news, $portal_config['portal_number_of_news'], $start, ($portal_config['portal_show_all_news']) ? 'news_all' : 'news');
			}
			
			$template->assign_block_vars('news_row', array(
				'ATTACH_ICON_IMG'	=> ($fetch_news[$i]['attachment']) ? $user->img('icon_topic_attach', $user->lang['TOTAL_ATTACHMENTS']) : '',
				'FORUM_NAME'		=> ( $forum_id ) ? $fetch_news[$i]['forum_name'] : '',
				'TITLE'				=> $fetch_news[$i]['topic_title'],
				'POSTER'			=> $fetch_news[$i]['username'],
				'POSTER_FULL'		=> $fetch_news[$i]['username_full'],
				'U_USER_PROFILE'	=> (($fetch_news[$i]['user_type'] == USER_NORMAL || $fetch_news[$i]['user_type'] == USER_FOUNDER) && $fetch_news[$i]['user_id'] != ANONYMOUS) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=viewprofile&amp;u=' . $fetch_news[$i]['user_id']) : '',
				'TIME'				=> $fetch_news[$i]['topic_time'],
				'TEXT'				=> $fetch_news[$i]['post_text'],
				'REPLIES'			=> $fetch_news[$i]['topic_replies'],
				'TOPIC_VIEWS'		=> $fetch_news[$i]['topic_views'],
				'N_ID'				=> $i,
				'U_VIEWFORUM'		=> append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $fetch_news[$i]['forum_id']),
				'U_LAST_COMMENTS'	=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $fetch_news[$i]['forum_id'] . '&amp;p=' . $fetch_news[$i]['topic_last_post_id'] . '#p' . $fetch_news[$i]['topic_last_post_id']),
				'U_VIEW_COMMENTS'	=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $fetch_news[$i]['forum_id'] . '&amp;t=' . $fetch_news[$i]['topic_id']),
				'U_POST_COMMENT'	=> append_sid("{$phpbb_root_path}posting.$phpEx", 'mode=reply&amp;f=' . $fetch_news[$i]['forum_id'] . '&amp;t=' . $fetch_news[$i]['topic_id']),
				'U_READ_FULL'		=> append_sid("{$phpbb_root_path}portal.$phpEx", $read_full_url),
				'L_READ_FULL'		=> $read_full,
				'OPEN'				=> $open_bracket,
				'CLOSE'				=> $close_bracket,
				'S_NOT_LAST'		=> ($i < count($fetch_news) - 1) ? true : false,
				'S_POLL'			=> $fetch_news[$i]['poll'],
				'S_UNREAD_INFO'		=> $unread_topic,
				'PAGINATION'		=> topic_generate_pagination($fetch_news[$i]['topic_replies'], $view_topic_url),
			));
			
			if ($portal_config['portal_number_of_news'] <> 0 && $portal_config['portal_news_archive'])
			{
				$template->assign_vars(array(
					'POSTED_BY_TEXT'	=> ( $portal_config['portal_news_show_last'] ) ? $user->lang['LAST_POST'] : $user->lang['POSTED'],
					'NP_PAGINATION'		=> $pagination,
					'TOTAL_NEWS'		=> ($total_news == 1) ? $user->lang['VIEW_FORUM_TOPIC'] : sprintf($user->lang['VIEW_FORUM_TOPICS'], $total_news),
					'NP_PAGE_NUMBER'	=> on_page($total_news, $portal_config['portal_number_of_news'], $start))
				);
			}
		}
	}
}
else
{
	$fetch_news = phpbb_fetch_posts($portal_config['portal_news_forum'], $portal_config['portal_news_permissions'], $portal_config['portal_number_of_news'], 0, 0, ($portal_config['portal_show_all_news']) ? 'news_all' : 'news', $start);

	$i = $news;
	$forum_id = $fetch_news[$i]['forum_id'];
	$topic_id = $fetch_news[$i]['topic_id'];
	$topic_tracking_info = get_complete_topic_tracking($forum_id, $topic_id, $global_announce_list = false);
	$unread_topic = (isset($topic_tracking_info[$topic_id]) && $fetch_news[$i]['topic_last_post_time'] > $topic_tracking_info[$topic_id]) ? true : false;
	$open_bracket = '[ ';
	$close_bracket = ' ]';
	$read_full = $user->lang['BACK'];
			
	$read_full_url = (isset($_GET['np'])) ? append_sid("{$phpbb_root_path}portal.$phpEx", "np=$start#n$i") : append_sid("{$phpbb_root_path}portal.$phpEx#n$i");
	$view_topic_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . (($fetch_news[$i]['forum_id']) ? $fetch_news[$i]['forum_id'] : $forum_id) . '&amp;t=' . $topic_id);
	if ( $portal_config['portal_news_archive'] )
	{
		$pagination = generate_portal_pagination(append_sid("{$phpbb_root_path}portal.$phpEx"), $total_news, $portal_config['portal_number_of_news'], $start, ($portal_config['portal_show_all_news']) ? 'news_all' : 'news');
	}

	$template->assign_block_vars('news_row', array(
		'ATTACH_ICON_IMG'	=> ($fetch_news[$i]['attachment']) ? $user->img('icon_topic_attach', $user->lang['TOTAL_ATTACHMENTS']) : '',
		'FORUM_NAME'		=> ( $forum_id ) ? $fetch_news[$i]['forum_name'] : '',
		'TITLE'				=> $fetch_news[$i]['topic_title'],
		'POSTER'			=> $fetch_news[$i]['username'],
		'POSTER_FULL'		=> $fetch_news[$i]['username_full'],
		'TIME'				=> $fetch_news[$i]['topic_time'],
		'TEXT'				=> $fetch_news[$i]['post_text'],
		'REPLIES'			=> $fetch_news[$i]['topic_replies'],
		'TOPIC_VIEWS'		=> $fetch_news[$i]['topic_views'],
		'N_ID'				=> $i,
		'U_VIEWFORUM'		=> append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $fetch_news[$i]['forum_id']),
		'U_LAST_COMMENTS'	=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $fetch_news[$i]['forum_id'] . '&amp;p=' . $fetch_news[$i]['topic_last_post_id'] . '#p' . $fetch_news[$i]['topic_last_post_id']),
		'U_VIEW_COMMENTS'	=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $fetch_news[$i]['forum_id'] . '&amp;t=' . $fetch_news[$i]['topic_id']),
		'U_POST_COMMENT'	=> append_sid("{$phpbb_root_path}posting.$phpEx", 'mode=reply&amp;f=' . $fetch_news[$i]['forum_id'] . '&amp;t=' . $fetch_news[$i]['topic_id']),
		'S_POLL'			=> $fetch_news[$i]['poll'],
		'S_UNREAD_INFO'		=> $unread_topic,
		'U_READ_FULL'      	=> $read_full_url,
		'L_READ_FULL'      	=> $read_full,      
		'OPEN'            	=> $open_bracket,
		'CLOSE'            	=> $close_bracket,
		'PAGINATION'		=> topic_generate_pagination($fetch_news[$i]['topic_replies'], $view_topic_url),
	));
	
	if ($portal_config['portal_number_of_news'] <> 0 && $portal_config['portal_news_archive'])
	{
		$template->assign_vars(array(
			'NP_PAGINATION'		=> $pagination,
			'TOTAL_NEWS'		=> ($total_news == 1) ? $user->lang['VIEW_FORUM_TOPIC'] : sprintf($user->lang['VIEW_FORUM_TOPICS'], $total_news),
			'NP_PAGE_NUMBER'	=> on_page($total_news, $portal_config['portal_number_of_news'], $start))
		);
	}
}

?>