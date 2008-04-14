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

include_once($phpbb_root_path . 'includes/functions_display.' . $phpEx);

// Get portal config
function obtain_portal_config()
{
	global $db, $cache;

	if (($portal_config = $cache->get('portal_config')) !== true)
	{
		$portal_config = $cached_portal_config = array();

		$sql = 'SELECT config_name, config_value
			FROM ' . PORTAL_CONFIG_TABLE;
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$cached_portal_config[$row['config_name']] = $row['config_value'];
			$portal_config[$row['config_name']] = $row['config_value'];
		}
		$db->sql_freeresult($result);

		$cache->put('portal_config', $cached_portal_config);
	}

	return $portal_config;
}

/**
* Set config value. Creates missing config entry.
*/
function set_portal_config($config_name, $config_value)
{
	global $db, $cache, $portal_config;

	$sql = 'UPDATE ' . PORTAL_CONFIG_TABLE . "
		SET config_value = '" . $db->sql_escape($config_value) . "'
		WHERE config_name = '" . $db->sql_escape($config_name) . "'";
	$db->sql_query($sql);

	if (!$db->sql_affectedrows() && !isset($portal_config[$config_name]))
	{
		$sql = 'INSERT INTO ' . PORTAL_CONFIG_TABLE . ' ' . $db->sql_build_array('INSERT', array(
			'config_name'	=> $config_name,
			'config_value'	=> $config_value));
		$db->sql_query($sql);
	}

	$portal_config[$config_name] = $config_value;
}

// 
include($phpbb_root_path . 'includes/message_parser.'.$phpEx);

// fetch post for news & announce
function phpbb_fetch_posts($forum_from, $permissions, $number_of_posts, $text_length, $time, $type)
{
	global $db, $phpbb_root_path, $auth, $user, $bbcode_bitfield, $bbcode;
	
	$posts = array();

	$post_time = ($time == 0) ? '' : 'AND t.topic_last_post_time > ' . (time() - $time * 86400);

	$forum_from = ( strpos($forum_from, ',') !== FALSE ) ? explode(',', $forum_from) : (($forum_from != '') ? array($forum_from) : array());

	$str_where = '';

	if( $permissions == TRUE )
	{
		$disallow_access = array_unique(array_keys($auth->acl_getf('!f_read', true)));
	} else {
		$disallow_access = array();
	}

	$global_f = 0;
	
	if( sizeof($forum_from) )
	{
		$disallow_access = array_diff($forum_from, $disallow_access);		
		if( !sizeof($disallow_access) )
		{
			return array();
		}
		
		foreach( $disallow_access as $acc_id )
		{
			$str_where .= "t.forum_id = $acc_id OR ";
		}
	}
	else
	{
		foreach( $disallow_access as $acc_id )
		{
			$str_where .= "t.forum_id <> $acc_id OR ";
		}
	}

	switch( $type )
	{
		case "announcements":

			$topic_type = '(( t.topic_type = ' . POST_ANNOUNCE . ') OR ( t.topic_type = ' . POST_GLOBAL . '))';
			$str_where = ( strlen($str_where) > 0 ) ? 'AND (t.forum_id = 0 OR ' . substr($str_where, 0, -4) . ')' : '';
			$user_link = 't.topic_poster = u.user_id';
			$post_link = 't.topic_first_post_id = p.post_id';
			$topic_order = 't.topic_time DESC';

		break;
		case "news":

			$topic_type = 't.topic_type = ' . POST_NORMAL;
			$str_where = ( strlen($str_where) > 0 ) ? 'AND (' . substr($str_where, 0, -4) . ')' : '';
			$user_link = 't.topic_last_poster_id = u.user_id';
			$post_link = 't.topic_last_post_id = p.post_id';
			$topic_order = 't.topic_last_post_time DESC';

		break;
		case "news_all":

			$topic_type = '( t.topic_type != ' . POST_ANNOUNCE . ' ) AND ( t.topic_type != ' . POST_GLOBAL . ')';
			$str_where = ( strlen($str_where) > 0 ) ? 'AND (' . substr($str_where, 0, -4) . ')' : '';
			$user_link = 't.topic_last_poster_id = u.user_id';
			$post_link = 't.topic_last_post_id = p.post_id';
			$topic_order = 't.topic_last_post_time DESC';

		break;
	}

	$sql = 'SELECT
				t.forum_id,
				t.topic_id,
				t.topic_last_post_id,
				t.topic_last_post_time,
				t.topic_time,
				t.topic_title,
				t.topic_attachment,
				t.topic_views,
				t.poll_title,
				t.topic_replies,
				t.topic_poster,
				u.username,
				u.user_id,
				u.user_type,
				u.user_colour,
				p.post_id,
				p.post_time,
				p.post_text,
				p.post_attachment,
				p.enable_smilies,
				p.enable_bbcode,
				p.enable_magic_url,
				p.bbcode_bitfield,
				p.bbcode_uid,
				f.forum_name
			FROM
				' . TOPICS_TABLE . ' AS t
			LEFT JOIN
				' . USERS_TABLE . ' as u
			ON
				' . $user_link . '
			LEFT JOIN
				' . FORUMS_TABLE . ' as f
			ON
				t.forum_id=f.forum_id
			LEFT JOIN
				' . POSTS_TABLE . ' as p
			ON
				' . $post_link . '
			WHERE
				' . $topic_type . '
				' . $post_time . '
				AND t.topic_status <> 2
				AND t.topic_approved = 1
				AND t.topic_moved_id = 0
				' . $str_where .'
			ORDER BY
				' . $topic_order;

	if( $number_of_posts == '' OR $number_of_posts == 0)
	{
		$result = $db->sql_query($sql);
	}
	else
	{
		$result = $db->sql_query_limit($sql, $number_of_posts);
	}

	// Instantiate BBCode if need be
	if ($bbcode_bitfield !== '')
	{
		$phpEx = substr(strrchr(__FILE__, '.'), 1);
		include_once($phpbb_root_path . 'includes/bbcode.' . $phpEx);
		$bbcode = new bbcode(base64_encode($bbcode_bitfield));
	}

	$i = 0;

	while ( $row = $db->sql_fetchrow($result) )
	{
		if ($row['user_id'] != ANONYMOUS && $row['user_colour'])
		{
			$row['username'] = '<b style="color:#' . $row['user_colour'] . '">' . $row['username'] . '</b>';
		}

		$posts[$i]['bbcode_uid'] = $row['bbcode_uid'];
		$len_check = $row['post_text'];
		$maxlen = $text_length;

		if (($text_length != 0) && (strlen($len_check) > $text_length))
		{
			$message = censor_text(get_sub_taged_string(str_replace("\n", '<br/> ', $row['post_text']), $row['bbcode_uid'], $maxlen));
			$posts[$i]['striped'] = true;
		}
		else 
		{
			$message = censor_text( str_replace("\n", '<br/> ', $row['post_text']) );
		}

		if ($auth->acl_get('f_html', $row['forum_id'])) 
		{
			$message = preg_replace('#<!\-\-(.*?)\-\->#is', '', $message); // Remove Comments from post content
		}

		// Second parse bbcode here
		if ($row['bbcode_bitfield'])
		{
			$bbcode->bbcode_second_pass($message, $row['bbcode_uid'], $row['bbcode_bitfield']);
		}
		$message = smiley_text($message); // Always process smilies after parsing bbcodes
		
		if( $global_f < 1 )
		{				
			$global_f = $row['forum_id'];
		}

		$posts[$i]['post_text']				= ap_validate($message);
		$posts[$i]['topic_id']				= $row['topic_id'];
		$posts[$i]['topic_last_post_id']	= $row['topic_last_post_id'];
		$posts[$i]['forum_id']				= $row['forum_id'];
		$posts[$i]['topic_replies'] 		= $row['topic_replies'];
		$posts[$i]['topic_time']			= $user->format_date($row['post_time']);
		$posts[$i]['topic_last_post_time']	= $row['topic_last_post_time'];
		$posts[$i]['topic_title']			= $row['topic_title'];
		$posts[$i]['username']				= $row['username'];
		$posts[$i]['user_id']				= $row['user_id'];
		$posts[$i]['user_type']				= $row['user_type'];
		$posts[$i]['user_user_colour']		= $row['user_colour'];
		$posts[$i]['poll']					= ($row['poll_title']) ? true : false;
		$posts[$i]['attachment']			= ($row['topic_attachment']) ? true : false;
		$posts[$i]['topic_views']			= $row['topic_views'];
		$posts[$i]['forum_name']			= $row['forum_name'];
		$posts['global_id']					= $global_f;
								
		$i++;
	}
	return $posts;
}

/**
* Censor title, return short title
*
* @param $title string title to censor
* @param $limit int short title character limit
*
*/
function character_limit(&$title, $limit = 0)
{
   $title = censor_text($title);
   if ($limit > 0)
   {
	  return (strlen(utf8_decode($title)) > $limit + 3) ? truncate_string($title, $limit) . '...' : $title;
   }
   else
   {
	  return $title;
   }
}

// Don't let them mess up the complete portal layout in cut messages and do some real AP magic

function is_valid_bbtag($str, $bbuid) {
  return (substr($str,0,1) == '[') && (strpos($str, ':'.$bbuid.']') > 0);
}

function get_end_bbtag($tag, $bbuid) {
  $etag = '';
  for($i=0;$i<strlen($tag);$i++) {
    if ($tag[$i] == '[') $etag .= $tag[$i] . '/';
    else if (($tag[$i] == '=') || ($tag[$i] == ':')) {
      if ($tag[1] == '*') $etag .= ':m:'.$bbuid.']';
      else if (strpos($tag, 'list')) $etag .= ':u:'.$bbuid.']';
      else $etag .= ':'.$bbuid.']';
      break;
    } else $etag .= $tag[$i];
  }

  return $etag;
}

function get_next_word($str) {
  $ret = '';
  for($i=0;$i<strlen($str);$i++) {
    switch ($str[$i]) {
      case ' ': //$ret .= ' '; break; break;
                return $ret . ' ';
      case '\\': 
        if ($str[$i+1] == 'n') return $ret . '\n';
      case '[': if ($i != 0) return $ret;
      default: $ret .= $str[$i];
    }    
  }
  return $ret;
}

function get_sub_taged_string($str, $bbuid, $maxlen) {
  $sl = $str;
  $ret = '';
  $ntext = '';
  $lret = '';
  $i = 0;
  $cnt = $maxlen;
  $last = '';
  $arr = array();

  while((strlen($ntext) < $cnt) && (strlen($sl) > 0)) {
    $sr = '';
    if (substr($sl, 0, 1) == '[') $sr = substr($sl,0,strpos($sl,']')+1);
    /* GESCHLOSSENE HTML-TAGS BEACHTEN */
    if (substr($sl, 0, 1) == '<') {
      $sr = substr($sl,0,strpos($sl,'>')+1);
      $ret .= $sr;
    } else if (is_valid_bbtag($sr, $bbuid)) {
      if ($sr[1] == '/') {
        /* entfernt das endtag aus dem tag array */
        $tarr = array();
        $j = 0;
        foreach ($arr as $elem) {
          if (strcmp($elem[1],$sr) != 0) $tarr[$j++] = $elem;
        }
        $arr = $tarr;
      } else {
        $arr[$i][0] = $sr;
        $arr[$i++][1] = get_end_bbtag($sr, $bbuid);
      } 
      $ret .= $sr;
    } else {
      $sr = get_next_word($sl);
      $ret .= $sr;
      $ntext .= $sr;
      $last = $sr;
    }
    $sl = substr($sl, strlen($sr), strlen($sl)-strlen($sr));
  }

  $ret = trim($ret) . '...';

  $ap = '';
  foreach ($arr as $elem) {
     $ap = $elem[1] . $ap;
  }
  $ret .= $ap;

  return $ret;
}

function ap_validate($str) {
  $s = str_replace('<br />', '<br/>', $str);
  return str_replace('</li><br/>', '</li>', $s);
}

?>