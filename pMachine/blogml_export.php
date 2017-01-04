<?php
// BLOGML 2.0 EXPORT
// Simple export for a single pMachine blog to BlogML 2.0 format.

// INSTALLATION
// Place this file in the folder alongside your main pMachine weblog.php file.
// Update the "include" statement below to properly point to your pm_inc.php
// include file.  If you have multiple blogs in pMachine, update the BLOG_ID
// constant value to be the ID of the blog you want to export.  Generally you
// shouldn't have to touch it if you used default settings.
//
// The entries are formatted using the template
// "Weblog - Individual Entry - Alt 3."  You can access/modify this template by
// going to the pMachine control panel, selecting "Edit Templates," then
// "Weblog," then "Weblog Single-entry Templates."
//
// Standard preferences that are configured in the pMachine control panel
// are also used.  Where there is a duplicate between preference meanings
// (e.g., sitename vs. rss_title_weblog), the more general preference will
// be used.  (In the example, that means 'sitename' will be used rather than
// 'rss_title_weblog.')
//
// USAGE
// Access this file via your browser and save the resulting XML file (e.g.,
// http://www.mysite.com/blog/blogml_export.php).

// Update this 'include' statement to point to your pm_inc.php file.
include("pm_inc.php");

// Update these IDs to be the blog ID to export.
define('BLOG_ID', "weblog");

// ----------------------------------------------------------------------------
// No need to change anything else for this to work.

// Line break
define('LINEBREAK', "\r\n");

// CDATA
define('CDATA_START', "<![CDATA[");
define('CDATA_END', "]]>");

// Generate the BlogML
write_blogml();


// ----------------------------------------------------------------------------
// Main function that writes the BlogML document and MIME headers.
// ----------------------------------------------------------------------------
function write_blogml()
{
	header('Content-type: text/xml');

	// XML header - done in several lines to avoid confusing the PHP parser
	echo '<';
	echo '?xml';
	echo " version=\"1.0\" encoding=\"iso-8859-1\" ";
	echo '?';
	echo '>';
	echo LINEBREAK;

	// BlogML document
	write_line("<blog date-created=\"" . format_timestamp(time()) . "\" xmlns=\"http://www.blogml.com/2006/09/BlogML\">");
	write_line("<title type=\"text\">" . cdata(get_pref("sitename")) . "</title>");
	write_line("<sub-title type=\"text\">" . cdata(get_pref("rss_description_weblog")) . "</sub-title>");
	write_authors();
	write_extended_properties();
	write_categories();
	write_posts();
	write_line("</blog>");
	return;
}

// ----------------------------------------------------------------------------
// Writes the <authors /> element of the BlogML document and all blog authors.
// ----------------------------------------------------------------------------
function write_authors()
{
	// Get the list of anyone who has posted in the past or anyone who is an administrator
	global $db_members;
	$db = new DB();
	$query = new DB_query($db, "select id, joindate, email, username from $db_members where numentries > 0 or status > 6");

	write_line("<authors>");
	while ($query->db_fetch_object())
	{
		write_line("<author id=\"" . $query->obj->id ."\" date-created=\"" . format_timestamp($query->obj->joindate) . "\" date-modified=\"" . format_timestamp($query->obj->joindate) . "\" approved=\"true\" email=\"" . $query->obj->email . "\">");
		write_line("<title type=\"text\">" . cdata($query->obj->username) . "</title>");
		write_line("</author>");
	}
	write_line("</authors>");
	unset($query);
}

// ----------------------------------------------------------------------------
// Writes the <categories /> element of the BlogML document and all blog
// categories.
// ----------------------------------------------------------------------------
function write_categories()
{
	// Get the list of all available categories
	global $db_categories;
	$db = new DB();
	$query = new DB_query($db, "select id, category from $db_categories order by id");

	write_line("<categories>");
	while ($query->db_fetch_object())
	{
		// pMachine doesn't track when categories were created and doesn't have
		// descriptions for the categories, so we leave those attributes out.
		write_line("<category id=\"" . $query->obj->id ."\" approved=\"true\">");
		write_line("<title type=\"text\">" . cdata($query->obj->category) . "</title>");
		write_line("</category>");
	}
	write_line("</categories>");
	unset($query);
}

// ----------------------------------------------------------------------------
// Writes the <extended-properties /> element of the BlogML document and common
// extended property values.
// ----------------------------------------------------------------------------
function write_extended_properties()
{
	write_line("<extended-properties>");

	// pMachine requires a name for comment posting and might require a user
	// account so comments aren't anonymous, but it also doesn't necessarily
	// support moderation of comments.
	write_line("<property name=\"CommentModeration\" value=\"Disabled\" />");

	write_line("<property name=\"SendTrackback\" value=\"" . ucfirst(get_pref("trackback_weblog")) . "\" />");

	write_line("</extended-properties>");
}

// ----------------------------------------------------------------------------
// Echoes a string and appends a line break.
// ----------------------------------------------------------------------------
function write_line($string_to_write)
{
	echo $string_to_write . LINEBREAK;
}

// ----------------------------------------------------------------------------
// Writes the <posts /> element of the BlogML document and iterates through
// each post to be written.
// ----------------------------------------------------------------------------
function write_posts()
{
	global $db_weblog, $db_upload_prefs;
	$db = new DB();

	// Get the set of "upload" folders for resolving URLs of uploaded items
	$img_array = array();
	$img_count = array();
	$query = new DB_query($db, "select id, abspath from $db_upload_prefs order by id");
	while ($query->db_fetch_object())
	{
		$abspath = ensure_trailing_slash($query->obj->abspath);
		$img_array[$query->obj->id] = $abspath;
		array_push($img_count,$query->obj->id);
	}
	unset($query);
	
	// Get the format for post links
	$pagespath = ensure_trailing_slash(get_pref("pages_path_abs_" . BLOG_ID));
	$page = get_pref(BLOG_ID . "_page");
	$permalink_format = $pagespath . $page ."?id=P%d";
	
	// Get the set of weblog entry IDs to display
	$query = new DB_query($db, "select $db_weblog.post_id from $db_weblog order by $db_weblog.t_stamp asc");

	write_line("<posts>");
	while ($query->db_fetch_object())
	{
		write_post($query->obj->post_id, $img_count, $img_array, $permalink_format);
	}
	write_line("</posts>");
}

// ----------------------------------------------------------------------------
// Writes individual posts in BlogML format.
// $post_id: ID of the post to write.
// $upload_id_array: Set of upload location IDs.
// $upload_location_array: Upload ID-to-location map.
// $permalink_format: String used with sprintf to create links to posts -
//   Put a %d where the post ID should be inserted.
//
// $upload_location_array[$upload_id_array[x]] = Path to upload location
// ----------------------------------------------------------------------------
function write_post($post_id, $upload_id_array, $upload_location_array, $permalink_format)
{
	global $db_weblog, $db_members, $db_nonmembers, $db_comments, $db_trackback, $db_pingback; 
	$db = new DB();
	$post_query = new DB_query($db, "select * from $db_weblog where post_id = $post_id");

	while ($post_query->db_fetch_object())
	{
		write_line("<post id=\"" . $post_id . "\" post-url=\"". xml_encode(sprintf($permalink_format, $post_query->obj->post_id)) ."\" date-created=\"". format_timestamp($post_query->obj->t_stamp) . "\" date-modified=\"". format_timestamp($post_query->obj->t_stamp) . "\" approved=\"" . boolstring($post_query->obj->status == "open") . "\" type=\"normal\" hasexcerpt=\"false\" views=\"" . ($post_query->obj->c_hits + $post_query->obj->m_hits) . "\">");
		write_line("<title type=\"text\">" . cdata($post_query->obj->title) . "</title>");
		
		// Process the body into HTML
		$post_body = process_post_body($post_query->obj);

		// Fixup links to the upload directories
		for ($j=0; $j< sizeof($upload_id_array); $j++)
		{
			$post_body = str_replace("%%dir[$upload_id_array[$j]]%%",$upload_location_array[$upload_id_array[$j]], $post_body);
		}

		write_line("<content type=\"html\">" . cdata($post_body) . "</content>");
		write_line("<post-name type=\"text\">" . cdata($post_query->obj->title) . "</post-name>");

		// Categories
		if($post_query->obj->category <> "")
		{
			write_line("<categories>");
			write_line("<category ref=\"" . $post_query->obj->category . "\" />");
			write_line("</categories>");
		}
		
		// Comments
		$comment_query = new DB_query($db, "select count(*) as count from $db_comments where post_id = '$post_id' and preview = '0'");
		$comment_query->db_fetch_array();
		$comment_count = $comment_query->row['count'];
		unset($comment_query);
		if($comment_count > 0)
		{
			write_line("<comments>");
			$comment_query = new DB_query($db, "select * from $db_comments where post_id = '$post_id' and preview = '0' order by t_stamp asc");
			while($comment_query->db_fetch_object())
			{
				$comment_author_sql = "select signature, email, url from %s where id='%s'";
				if(substr($comment_query->obj->member_id, 0, 2) == "NM")
				{
					$comment_author_sql = sprintf($comment_author_sql, $db_nonmembers, substr($comment_query->obj->member_id,2));
				}
				else
				{
					$comment_author_sql = sprintf($comment_author_sql, $db_members, $comment_query->obj->member_id);
				}
				$comment_author_query = new DB_query($db, $comment_author_sql);
				$comment_author_query->db_fetch_object();
				$comment_open_tag = "<comment id=\"" . $comment_query->obj->comment_id . "\" date-created=\"" . format_timestamp($comment_query->obj->t_stamp) . "\" date-modified=\"" . format_timestamp($comment_query->obj->t_stamp) . "\" approved=\"true\" user-name=\"" . xml_encode($comment_author_query->obj->signature) . "\"";
				if($comment_author_query->obj->email <> "")
				{
					$comment_open_tag .= " user-email=\"" . $comment_author_query->obj->email . "\"";
				}
				if($comment_author_query->obj->url <> "")
				{
					$comment_open_tag .= " user-url=\"" . xml_encode($comment_author_query->obj->url) ."\"";
				}
				$comment_open_tag .= ">";
				write_line($comment_open_tag);
				write_line("<title type=\"text\">" . cdata("Re: " . $post_query->obj->title) . "</title>");
				write_line("<content type=\"html\">" . cdata(nl2brExceptPre(pmcode_decode($comment_query->obj->body))) . "</content>");
				write_line("</comment>");
				unset($comment_author_sql);
				unset($comment_author_query);
			}
			unset($comment_query);
			write_line("</comments>");
		}
		unset($comment_count);
		
		
		// Trackbacks and Pingbacks
		$trackback_count_query = new DB_query($db, "select count(*) as count from $db_trackback where post_id = '$post_id'");
		$trackback_count_query->db_fetch_array();
		$trackback_count = $trackback_count_query->row['count'];
		unset($trackback_count_query);
		$pingback_count_query = new DB_query($db, "select count(*) as count from $db_pingback where post_id = '$post_id'");
		$pingback_count_query->db_fetch_array();
		$pingback_count = $pingback_count_query->row['count'];
		unset($pingback_count_query);
		if($pingback_count + $trackback_count > 0)
		{
			write_line("<trackbacks>");
			$trackback_query = new DB_query($db, "select id, entry_title, entry_url, t_stamp from $db_trackback where post_id = '$post_id' order by t_stamp asc");
			while($trackback_query->db_fetch_object())
			{
				write_line("<trackback id=\"" . $trackback_query->obj->id ."\" date-created=\"" . format_timestamp($trackback_query->obj->t_stamp) . "\" date-modified=\"" . format_timestamp($trackback_query->obj->t_stamp) . "\" approved=\"true\" url=\"" . xml_encode($trackback_query->obj->entry_url) . "\">");
				write_line("<title type=\"text\">" . cdata($trackback_query->obj->entry_title) . "</title>");
				write_line("</trackback>");
			}
			unset($trackback_query);
			$pingback_query = new DB_query($db, "select id, from_title, from_url, t_stamp from $db_pingback where post_id = '$post_id' order by t_stamp asc");
			while($pingback_query->db_fetch_object())
			{
				write_line("<trackback id=\"" . $pingback_query->obj->id ."\" date-created=\"" . format_timestamp($pingback_query->obj->t_stamp) . "\" date-modified=\"" . format_timestamp($pingback_query->obj->t_stamp) . "\" approved=\"true\" url=\"" . xml_encode($pingback_query->obj->from_url) . "\">");
				write_line("<title type=\"text\">" . cdata($pingback_query->obj->from_title) . "</title>");
				write_line("</trackback>");
			}
			unset($pingback_query);
			write_line("</trackbacks>");
		}
		unset($pingback_count);
		unset($trackback_count);
		
		// Authors
		write_line("<authors>");
		write_line("<author ref=\"" . $post_query->obj->member_id . "\" />");
		write_line("</authors>");

		write_line("</post>");
	}
	unset($post_query);
}

// ----------------------------------------------------------------------------
// Formats a timestamp value into an XSD compatible date string.
// The proper format wasn't added until PHP 5.1.3 so this needs to be here for
// older versions of PHP.
// ----------------------------------------------------------------------------
function format_timestamp($timestamp_to_format)
{
;	$formatted = date('Y-m-d\TH:i:sO', $timestamp_to_format);
	$formatted = substr($formatted, 0, strlen($formatted) - 2) . ":" . substr($formatted, strlen($formatted) - 2);
	return $formatted;
}

// ----------------------------------------------------------------------------
// Ensures the string passed in has a trailing slash - used in path
// calculations.
// ----------------------------------------------------------------------------
function ensure_trailing_slash($check_for_slash)
{
	if (!eregi('/$', $check_for_slash))
	{
		return $check_for_slash . "/";
	}
	return $check_for_slash;
}

// ----------------------------------------------------------------------------
// Creates a CDATA string block.
// ----------------------------------------------------------------------------
function cdata($data_string)
{
	return CDATA_START . $data_string . CDATA_END;
}

// ----------------------------------------------------------------------------
// Converts a Boolean to an XSD compatible Boolean string.
// ----------------------------------------------------------------------------
function boolstring($bool_to_convert)
{
	if($bool_to_convert)
	{
		return "true";
	}
	return "false";
}

// ----------------------------------------------------------------------------
// Processes a single post record into the HTML for the post body.  Based on
// code for processing templates in pMachine Free.
// ----------------------------------------------------------------------------
function process_post_body($post_record)
{
	// Prepare for database queries
	$db = new DB();

	// Calculate the window target for links
	if (get_pref("link_opens_window_" . BLOG_ID) == "yes")
	{
		$target = "target=\"_blank\"";
		$pop = "yes";
	}
	else
	{
		$target = "";
		$pop = "";
	}

	// Get standard fields for macro substitution
	$blurb		= $post_record->blurb;
	$body		= $post_record->body;
	$c_total	= $post_record->c_total;
	$comment_hits	= $post_record->c_hits;
	$custom1	= $post_record->custom1;
	$custom2	= $post_record->custom2;
	$custom3	= $post_record->custom3;
	$entry_cat	= $post_record->category;
	$mem_id		= $post_record->member_id;
	$more		= $post_record->more;
	$more_hits	= $post_record->m_hits;
	$nl2brBlurb	= $post_record->nl2brBlurb;
	$nl2brBody	= $post_record->nl2brBody;
	$nl2brC1	= $post_record->nl2brC1;
	$nl2brC2	= $post_record->nl2brC2;
	$nl2brC3	= $post_record->nl2brC3;
	$nl2brMore	= $post_record->nl2brMore;
	$pb_total	= $post_record->pb_total;
	$postid		= $post_record->post_id;
	$showcomments	= $post_record->showcomments;
	$t_stamp	= $post_record->t_stamp;
	$tb_total	= $post_record->tb_total;
	$title		= $post_record->title;

	// Handle escaping for entry text
	if (get_magic_quotes_runtime())
	{
		$title   = stripslashes($title);
		$blurb   = stripslashes($blurb);
		$body    = stripslashes($body);
		$more    = stripslashes($more);
		$custom1 = stripslashes($custom1);
		$custom2 = stripslashes($custom2);
		$custom3 = stripslashes($custom3);
	}

	// Get the formatted date and time
	$date_and_time  = get_pref("date_and_time_" . BLOG_ID);
	if ($c_total == 0 || $c_total == "")
	{
		$c_date = "-";
	}
	else
	{
		$c_date = date("$date_and_time", $post_record->c_date);
	}
	$date = date("$date_and_time", $t_stamp);

	// Censor words as configured
	$word_censor = get_pref("word_censor_weblog");
	if ($word_censor == "yes")
	{
		$title = censor($title);
		$body  = censor($body);

		if ($blurb <> "") $blurb = censor($blurb);
		if ($more  <> "") $more = censor($more);
	}
	
	// Handle HTML conversion
	$html_display   = get_pref("html_display_options_" . BLOG_ID);
	if ($html_display == "convert_html")
	{
		$title = pmcode_remove_tags($title);
		$body  = pmcode_remove_tags($body);
		if ($blurb   <> "")
		{
			$blurb = pmcode_remove_tags($blurb);
		}
		if ($more    <> "")
		{
			$more = pmcode_remove_tags($more);
		}
		if ($custom1 <> "")
		{
			$custom1 = pmcode_remove_tags($custom1);
		}
		if ($custom2 <> "")
		{
			$custom2 = pmcode_remove_tags($custom2);
		}
		if ($custom3 <> "")
		{
			$custom3 = pmcode_remove_tags($custom3);
		}
	}
	
	// Decode pMCode entities
	$title = pmcode_decode($title, $pop);
	$body  = pmcode_decode($body, $pop);
        if ($blurb   <> "")
	{
		$blurb = pmcode_decode($blurb, $pop);
	}
        if ($more    <> "")
	{
		$more = pmcode_decode($more, $pop);
	}
        if ($custom1 <> "")
	{
		$custom1 = pmcode_decode($custom1, $pop);
	}
        if ($custom2 <> "")
	{
		$custom2 = pmcode_decode($custom2, $pop);
	}
        if ($custom3 <> "")
	{
		$custom3 = pmcode_decode($custom3, $pop);
	}
	
	// Process text - convert newline to BR or do XHTML processing
	global $auto_xhtml;
	if (isset($auto_xhtml) && $auto_xhtml != 0)
	{
		$text_process = "xhtml_typography";
	}
	else
	{
		$text_process = "nl2brExceptPre";
	}
	if ($nl2brBody == 1)
	{
		$body = $text_process($body);
	}
	if ($blurb <> "" && $nl2brBlurb == 1)
	{
		$blurb = $text_process($blurb);
	}
	if ($more <> "" && $nl2brMore == 1)
	{
		$more = $text_process($more);
	}
	if ($custom1 <> "" && $nl2brC1 == 1)
	{
		$custom1 = $text_process($custom1);
	}
	if ($custom2 <> "" && $nl2brC2 == 1)
	{
		$custom2 = $text_process($custom2);
	}
	if ($custom3 <> "" && $nl2brC3 == 1)
	{
		$custom3 = $text_process($custom3);
	}

	// Get author-related information
	$author_sql = "select id, signature, email, url, location, show_email from %s where id='%s'";
	if(substr($mem_id, 0, 2) == "NM")
	{
		$memberflag = 1;
		$author_sql = sprintf($author_sql, $db_nonmembers, substr($mem_id,2));
	}
	else
	{
		$memberflag = 0;
		$author_sql = sprintf($author_sql, $db_members, $mem_id);
	}
        $author_result = new DB_query($db, $author_sql);
        $author_result->db_fetch_object();
        $show_email	= $author_result->obj->show_email;
        $name		= $author_result->obj->signature;
        $email		= $author_result->obj->email;
        $url		= $author_result->obj->url;
        $location	= $author_result->obj->location;
        $turl		= $url;
        unset($author_result);
        unset($author_sql);

        // If the author is a member of the site, we can get the number of comments and entries they've posted
        if($memberflag == 0)
        {
		// Number of entries
		$newtime = get_offset_time();
		$mcount = new DB_query($db, "select count(*) as count from $db_weblog where member_id = '$mem_id' and preview != '1' and t_stamp <= '$newtime' and x_stamp >= '$newtime' and status = 'open'");
		$mcount->db_fetch_object();
		$numentries = $mcount->obj->count;
		unset($mcount);
		if ($numentries == "")
		{
			$numentries = "0";
		}

		// Number of comments
		$mcount = new DB_query($db, "select count(*) as count from $db_comments where member_id = '$mem_id'");
		$mcount->db_fetch_object();
		$numcomments = $mcount->obj->count;
		unset($mcount);
		if ($numcomments == "")
		{
			$numcomments = "0";
		}
        }
	if (!isset($numentries))
	{
		$numentries = "";
	}
	if (!isset($numcomments))
	{
		$numcomments = "";
	}

	// Handle escaping for author information
	if (get_magic_quotes_runtime())
	{
		$name		= stripslashes($name);
		$email		= stripslashes($email);
		$url		= stripslashes($url);
		$location	= stripslashes($location);
	}

	// Process author display options - email, url
	$if_email			= "";
	$if_email_as_link		= "";
	$if_email_or_url_as_link	= "";
	$if_email_as_name		= $name;
	$if_email_or_url_as_name	= $name;
	$if_url_or_email_as_name	= $name;
	$if_url_as_name			= $name;


	// Add the default protocol to the user's specified URL if it doesn't exist
	if ($url <> "")
	{
		if(!stristr($url, "http://"))
		{
			$turl = $url;
			$url = "http://" . $url;
		}
		if (eregi('/$', $turl))
		{
			$turl = substr($turl, 0, -1);
		}
		$if_url_or_email_as_name	= "<a href=\"$url\" $target>$name</a>";
		$if_url_as_name			= "<a href=\"$url\" $target>$name</a>";
	}

	// Set up display based on whether email address is being displayed
	if ($show_email == "yes")
	{
		if ($email <> "")
		{
			$if_email			= $email;
			$if_email_or_url		= $email;
			$if_email_as_link		= encoded_email($email, $email, "0");
			$if_email_as_name		= encoded_email($email, $name, "0");
			$if_url_or_email_as_name	= encoded_email($email, $name, "0");
			$if_email_or_url_as_name	= encoded_email($email, $name, "0");
			$if_email_or_url_as_link	= encoded_email($email, $email, "0");

			if ($url <> "")
			{
				$if_url_or_email_as_name =  "<a href=\"$url\" $target>$name</a>";
			}
		}
		else
		{
			if ($url <> "")
			{
				$if_email_or_url		= $url;
				$if_email_or_url_as_name	= "<a href=\"$url\" $target>$name</a>";
				$if_email_or_url_as_link	= "<a href=\"$url\" $target>$turl</a>";
				$if_url_or_email_as_name	= "<a href=\"$url\" $target>$name</a>";
			}
		}
	}
	elseif ($show_email == "no")
	{
		if ($url <> "")
		{
			$if_email_or_url		= "$url";
			$if_email_or_url_as_name	= "<a href=\"$url\" $target>$name</a>";
			$if_email_or_url_as_link	= "<a href=\"$url\" $target>$turl</a>";
		}
	}

	// Prepare for calculation of related page URLs
	$pagespath = ensure_trailing_slash(get_pref("pages_path_abs_" . BLOG_ID));

	// Determine the comment/permalink URLs
	$weblogpage = get_pref("{$weblog}_page");
	$comments_page = get_pref("comments_page_$weblog");
	$delim = '?id=';
	if ($url_rewriting == 1)
	{
		$delim = '/';
		$comments_page = str_replace($sfx, '', $comments_page);
		$weblogpage = str_replace($sfx, '', $weblogpage);
	}
	$comments_url = ($showcomments == 1) ? "$pagespath$comments_page$delim" . $post_record->post_id . "_0_1_0_C" : "";
	if ($url_rewriting == 1 AND $comments_url != '')
	{
		$comments_url = ensure_trailing_slash($comments_url);
	}
	$comment_permalink = "$pagespath$comments_page{$delim}P" . $post_record->postid . "_0_1_0";
	$weblog_permalink = "$pagespath$weblogpage{$delim}P" . $post_record->postid;
	if ($url_rewriting == 1)
	{
		$comment_permalink = ensure_trailing_slash($comment_permalink);
		$weblog_permalink = ensure_trailing_slash($weblog_permalink);
	}
	
	// Determine the trackback/pingback URLs
	$trackback_url = $pagespath . get_pref("trackbacks_page_$weblog") . "?id=" . $post_record->post_id;
	$pingback_url = $pagespath . get_pref("pingbacks_page_$weblog") . "?id=" . $post_record->post_id;

	// Determine the related category name/URL
	$entrycategory = "";
	$entrycategorylink = "";
	$category_result = new DB_query($db, "select id, category from $db_categories where id = '$entry_cat'");
	$category_result->db_fetch_object();
	$entrycategory = $category_result->obj->category;
	$entrycategorylink = "$pagespath$weblogpage{$delim}C0_" . $category_result->obj->id . "_1";
	if ($url_rewriting == 1)
	{
		$entrycategorylink = ensure_trailing_slash($entrycategorylink);
	}
	
	// Determine the URL to the member's profile
	global $profileviewpage, $sfx;
	$profile_link = get_pref("memdir","1") . "$profileviewpage$sfx?id=$mem_id";

	// Determine the entry ID
	$entry_id = "$delim{$postid}_0_1_0";
	if ($url_rewriting == 1)
	{
		$entry_id = ensure_trailing_slash($entry_id);
	}
	
	// Set up the array of template substitutions
	$swap = array(
		"%%entry_id%%"			=> $entry_id,
		"%%raw_entry_id%%"		=> $post_record->post_id . "_0_1_0",
		"%%name%%"			=> $name,
		"%%id%%"			=> $post_record->post_id,
		"%%date%%"			=> $date,
		"%%title%%"			=> $title,
		"%%blurb%%"			=> $blurb,
		"%%body%%"			=> $body,
		"%%more%%"			=> $more,
		"%%custom1%%"			=> $custom1,
		"%%custom2%%"			=> $custom2,
		"%%custom3%%"			=> $custom3,
		"%%location%%"			=> $location,
		"%%url%%"			=> $url,
		"%%if_email%%"			=> $if_email,
		"%%if_email_or_url_as_name%%"	=> $if_email_or_url_as_name,
		"%%if_url_or_email_as_name%%"	=> $if_url_or_email_as_name,
		"%%if_url_as_name%%"		=> $if_url_as_name,
		"%%if_email_as_name%%"		=> $if_email_as_name,
		"%%if_email_as_link%%"		=> $if_email_as_link,
		"%%if_email_or_url_as_link%%"	=> $if_email_or_url_as_link,
		"%%weblog_permalink%%"		=> $weblog_permalink,
		"%%comment_permalink%%"		=> $comment_permalink,
		"%%profile_link%%"		=> $profile_link,
		"%%category%%"			=> $entrycategory,
		"%%category_url%%"		=> $entrycategorylink,
		"%%comment_hits%%"		=> $comment_hits,
		"%%more_hits%%"			=> $more_hits,
		"%%comments_total%%"		=> $c_total,
		"%%comment_total%%"		=> $c_total,
		"%%trackback_total%%"		=> $tb_total,
		"%%trackback_url%%"		=> $trackback_url,
		"%%pingback_total%%"		=> $pb_total,
		"%%pingback_url%%"		=> $pingback_url,
		"%%member_total_entries%%"	=> $numentries,
		"%%member_total_comments%%"	=> $numcomments,
		"%%comments_url%%"		=> $comments_url,
		"%%comment_url%%"		=> $comments_url,
		"%%date_of_recent_comment%%"	=> $c_date,
		"%%a%%"				=> date("a",$t_stamp),
		"%%A%%"				=> date("A",$t_stamp),
		"%%B%%"				=> date("B",$t_stamp),
		"%%d%%"				=> date("d",$t_stamp),
		"%%D%%"				=> date("D",$t_stamp),
		"%%F%%"				=> date("F",$t_stamp),
		"%%g%%"				=> date("g",$t_stamp),
		"%%G%%"				=> date("G",$t_stamp),
		"%%h%%"				=> date("h",$t_stamp),
		"%%H%%"				=> date("H",$t_stamp),
		"%%i%%"				=> date("i",$t_stamp),
		"%%I%%"				=> date("I",$t_stamp),
		"%%j%%"				=> date("j",$t_stamp),
		"%%l%%"				=> date("l",$t_stamp),
		"%%L%%"				=> date("L",$t_stamp),
		"%%m%%"				=> date("m",$t_stamp),
		"%%M%%"				=> date("M",$t_stamp),
		"%%n%%"				=> date("n",$t_stamp),
		"%%r%%"				=> date("r",$t_stamp),
		"%%s%%"				=> date("s",$t_stamp),
		"%%S%%"				=> date("S",$t_stamp),
		"%%t%%"				=> date("t",$t_stamp),
		"%%T%%"				=> date("T",$t_stamp),
		"%%U%%"				=> date("U",$t_stamp),
		"%%w%%"				=> date("w",$t_stamp),
		"%%Y%%"				=> date("Y",$t_stamp),
		"%%y%%"				=> date("y",$t_stamp),
		"%%z%%"				=> date("z",$t_stamp),
		"%%Z%%"				=> date("Z",$t_stamp)
	);

	// Get the template to use (template 87 is "Individual Entry - Alt 3")
	$block = get_template(get_pref(BLOG_ID . "_templategroup"), 87);
	
	// Do the template macro substitution
	while(list($key, $value) = each($swap))
	{
		$block = str_replace("$key","$value",$block);
	}

	// Return the finished/processed entry
	return "$block";
}

// ----------------------------------------------------------------------------
// XML encodes a string.
// ----------------------------------------------------------------------------
function xml_encode($to_encode)
{
	$retval = str_replace("&", "&amp;", $to_encode);
	$retval = str_replace("<", "&lt;", $retval);
	$retval = str_replace(">", "&gt;", $retval);
	$retval = str_replace("\"", "&quot;", $retval);
	return $retval;
}

?>