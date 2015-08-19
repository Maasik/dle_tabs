<?php
/*
=====================================================
 DataLife Engine - by SoftNews Media Group
-----------------------------------------------------
 http://dle-news.ru/
-----------------------------------------------------
 Copyright (c) 2004,2015 SoftNews Media Group
=====================================================
 ������ ��� ������� ���������� �������
=====================================================
 ����: parse.class.php
-----------------------------------------------------
 ����������: ����� ������� ������
=====================================================
*/

if( ! defined( 'DATALIFEENGINE' ) ) {
	die( "Hacking attempt!" );
}

class ParseFilter {
	var $tagsArray;
	var $attrArray;
	var $tagsMethod;
	var $attrMethod;
	var $xssAuto;
	var $video_config = array ();
	var $code_text = array ();
	var $code_count = 0;
	var $frame_code = array ();
	var $codes_param = array ();
	var $frame_count = 0;
	var $wysiwyg = false;
	var $safe_mode = false;
	var $allow_code = true;
	var $leech_mode = false;
	var $filter_mode = true;
	var $allow_url = true;
	var $allow_image = true;
	var $edit_mode = true;
	var $not_allowed_tags = false;
	var $not_allowed_text = false;
	var $allowed_domains = array("http://vkontakte.ru/", "http://www.vkontakte.ru/", "http://vk.com/", "http://www.vk.com/", "http://youtube.com/", "http://www.youtube.com/", "https://youtube.com/", "https://www.youtube.com/", "//www.youtube.com/", "http://maps.google.ru/", "http://maps.google.com/", "http://player.vimeo.com/", "http://www.facebook.com/", "http://mover.uz/", "http://v.kiwi.kz/", "http://www.dailymotion.com/", "http://www.bing.com/", "http://www.ustream.tv/", "http://w.soundcloud.com/", "http://www.coveritlive.com/", "http://video.yandex.ru/", "http://player.rutv.ru/", "http://promodj.com/", "https://maps.google.ru/", "https://maps.google.com/", "http://rutube.ru/", "//rutube.ru/", "https://skydrive.live.com/", "https://docs.google.com/", "http://api.video.mail.ru/", "http://megogo.net/", "https://mapsengine.google.com/", "//rutube.ru/", "//www.dailymotion.com/", "//vk.com/", "//www.vk.com/", "//player.vimeo.com/");
	var $tagBlacklist = array ('applet', 'body', 'bgsound', 'base', 'basefont', 'frame', 'frameset', 'head', 'html', 'id', 'ilayer', 'layer', 'link', 'meta', 'name', 'script', 'style', 'title', 'xml' );
	var $attrBlacklist = array ('action', 'background', 'codebase', 'dynsrc', 'lowsrc', 'data' );

	var $font_sizes = array (1 => '8', 2 => '10', 3 => '12', 4 => '14', 5 => '18', 6 => '24', 7 => '36' );

	function ParseFilter($tagsArray = array(), $attrArray = array(), $tagsMethod = 0, $attrMethod = 0, $xssAuto = 1) {
		for($i = 0; $i < count( $tagsArray ); $i ++)
			$tagsArray[$i] = strtolower( $tagsArray[$i] );
		for($i = 0; $i < count( $attrArray ); $i ++)
			$attrArray[$i] = strtolower( $attrArray[$i] );
		$this->tagsArray = ( array ) $tagsArray;
		$this->attrArray = ( array ) $attrArray;
		$this->tagsMethod = $tagsMethod;
		$this->attrMethod = $attrMethod;
		$this->xssAuto = $xssAuto;
	}
	function process($source) {

		if( function_exists( "get_magic_quotes_gpc" ) && get_magic_quotes_gpc() ) $source = stripslashes( $source );

		$source = str_ireplace( "{include", "&#123;include", $source );
		$source = str_ireplace( "{content", "&#123;content", $source );
		$source = str_ireplace( "{custom", "&#123;custom", $source );

		$source = $this->remove( $this->decode( $source ) );

		if( $this->code_count ) {
			foreach ( $this->code_text as $key_find => $key_replace ) {
				$find[] = $key_find;
				$replace[] = $key_replace;
			}

			$source = str_replace( $find, $replace, $source );
		}

		$this->code_count = 0;
		$this->code_text = array ();

		$source = preg_replace( "#<script#i", "&lt;script", $source );

		if ( !$this->safe_mode ) {
			$source = preg_replace_callback( "#<iframe(.+?)src=['\"](.+?)['\"](.*?)>(.*?)</iframe>#is", array( &$this, 'check_frame'), $source );
		}

		$source = str_ireplace( "<iframe", "&lt;iframe", $source );
		$source = str_ireplace( "</iframe>", "&lt;/iframe&gt;", $source );
		$source = str_replace( "<?", "&lt;?", $source );
		$source = str_replace( "?>", "?&gt;", $source );

		$source = addslashes( $source );
		return $source;

	}
	function remove($source) {
		$loopCounter = 0;
		while ( $source != $this->filterTags( $source ) ) {
			$source = $this->filterTags( $source );
			$loopCounter ++;
		}
		return $source;
	}
	function filterTags($source) {
		$preTag = NULL;
		$postTag = $source;
		$tagOpen_start = strpos( $source, '<' );
		while ( $tagOpen_start !== FALSE ) {
			$preTag .= substr( $postTag, 0, $tagOpen_start );
			$postTag = substr( $postTag, $tagOpen_start );
			$fromTagOpen = substr( $postTag, 1 );
			$tagOpen_end = strpos( $fromTagOpen, '>' );
			if( $tagOpen_end === false ) break;
			$tagOpen_nested = strpos( $fromTagOpen, '<' );
			if( ($tagOpen_nested !== false) && ($tagOpen_nested < $tagOpen_end) ) {
				$preTag .= substr( $postTag, 0, ($tagOpen_nested + 1) );
				$postTag = substr( $postTag, ($tagOpen_nested + 1) );
				$tagOpen_start = strpos( $postTag, '<' );
				continue;
			}
			$tagOpen_nested = (strpos( $fromTagOpen, '<' ) + $tagOpen_start + 1);
			$currentTag = substr( $fromTagOpen, 0, $tagOpen_end );
			$tagLength = strlen( $currentTag );
			if( ! $tagOpen_end ) {
				$preTag .= $postTag;
				$tagOpen_start = strpos( $postTag, '<' );
			}
			$tagLeft = $currentTag;
			$attrSet = array ();
			$currentSpace = strpos( $tagLeft, ' ' );
			if( substr( $currentTag, 0, 1 ) == "/" ) {
				$isCloseTag = TRUE;
				list ( $tagName ) = explode( ' ', $currentTag );
				$tagName = substr( $tagName, 1 );
			} else {
				$isCloseTag = FALSE;
				list ( $tagName ) = explode( ' ', $currentTag );
			}
			if( (! preg_match( "/^[a-z][a-z0-9]*$/i", $tagName )) || (! $tagName) || ((in_array( strtolower( $tagName ), $this->tagBlacklist )) && ($this->xssAuto)) ) {
				$postTag = substr( $postTag, ($tagLength + 2) );
				$tagOpen_start = strpos( $postTag, '<' );
				continue;
			}
			while ( $currentSpace !== FALSE ) {
				$fromSpace = substr( $tagLeft, ($currentSpace + 1) );
				$nextSpace = strpos( $fromSpace, ' ' );
				$openQuotes = strpos( $fromSpace, '"' );
				$closeQuotes = strpos( substr( $fromSpace, ($openQuotes + 1) ), '"' ) + $openQuotes + 1;
				if( strpos( $fromSpace, '=' ) !== FALSE ) {
					if( ($openQuotes !== FALSE) && (strpos( substr( $fromSpace, ($openQuotes + 1) ), '"' ) !== FALSE) ) $attr = substr( $fromSpace, 0, ($closeQuotes + 1) );
					else $attr = substr( $fromSpace, 0, $nextSpace );
				} else
					$attr = substr( $fromSpace, 0, $nextSpace );
				if( ! $attr ) $attr = $fromSpace;
				$attrSet[] = $attr;
				$tagLeft = substr( $fromSpace, strlen( $attr ) );
				$currentSpace = strpos( $tagLeft, ' ' );
			}
			$tagFound = in_array( strtolower( $tagName ), $this->tagsArray );
			if( (! $tagFound && $this->tagsMethod) || ($tagFound && ! $this->tagsMethod) ) {
				if( ! $isCloseTag ) {
					$attrSet = $this->filterAttr( $attrSet, strtolower( $tagName ) );
					$preTag .= '<' . $tagName;
					for($i = 0; $i < count( $attrSet ); $i ++)
						$preTag .= ' ' . $attrSet[$i];
					if( strpos( $fromTagOpen, "</" . $tagName ) ) $preTag .= '>';
					else $preTag .= ' />';
				} else
					$preTag .= '</' . $tagName . '>';
			}
			$postTag = substr( $postTag, ($tagLength + 2) );
			$tagOpen_start = strpos( $postTag, '<' );
		}
		$preTag .= $postTag;
		return $preTag;
	}

	function filterAttr($attrSet, $tagName) {

		global $config;

		$newSet = array ();
		for($i = 0; $i < count( $attrSet ); $i ++) {
			if( ! $attrSet[$i] ) continue;

			$attrSet[$i] = trim( $attrSet[$i] );

			$exp = strpos( $attrSet[$i], '=' );
			if( $exp === false ) $attrSubSet = Array ($attrSet[$i] );
			else {
				$attrSubSet = Array ();
				$attrSubSet[] = substr( $attrSet[$i], 0, $exp );
				$attrSubSet[] = substr( $attrSet[$i], $exp + 1 );
			}
			$attrSubSet[1] = stripslashes( $attrSubSet[1] );

			list ( $attrSubSet[0] ) = explode( ' ', $attrSubSet[0] );

			$attrSubSet[0] = strtolower( $attrSubSet[0] );

			if( (! preg_match( "/^[a-z\-]*$/i", $attrSubSet[0] )) || (($this->xssAuto) && ((in_array( $attrSubSet[0], $this->attrBlacklist )) || (substr( $attrSubSet[0], 0, 2 ) == 'on'))) ) continue;
			if( $attrSubSet[1] ) {
				$attrSubSet[1] = str_replace( '&#', '', $attrSubSet[1] );

				if ( strtolower($config['charset']) == "utf-8") $attrSubSet[1] = preg_replace( '/\s+/u', ' ', $attrSubSet[1] );
				else $attrSubSet[1] = preg_replace( '/\s+/', ' ', $attrSubSet[1] );

				$attrSubSet[1] = str_replace( '"', '', $attrSubSet[1] );
				if( (substr( $attrSubSet[1], 0, 1 ) == "'") && (substr( $attrSubSet[1], (strlen( $attrSubSet[1] ) - 1), 1 ) == "'") ) $attrSubSet[1] = substr( $attrSubSet[1], 1, (strlen( $attrSubSet[1] ) - 2) );
			}

			if( ((strpos( strtolower( $attrSubSet[1] ), 'expression' ) !== false) && ($attrSubSet[0] == 'style')) || (strpos( strtolower( $attrSubSet[1] ), 'javascript:' ) !== false) || (strpos( strtolower( $attrSubSet[1] ), 'behaviour:' ) !== false) || (strpos( strtolower( $attrSubSet[1] ), 'vbscript:' ) !== false) || (strpos( strtolower( $attrSubSet[1] ), 'mocha:' ) !== false) || (strpos( strtolower( $attrSubSet[1] ), 'data:' ) !== false and $attrSubSet[0] == "href") || (strpos( strtolower( $attrSubSet[1] ), 'data:' ) !== false and $attrSubSet[0] == "data") || (strpos( strtolower( $attrSubSet[1] ), 'data:' ) !== false and $attrSubSet[0] == "src") || ($attrSubSet[0] == "href" and @strpos( strtolower( $attrSubSet[1] ), $config['admin_path'] ) !== false and preg_match( "/[?&%<\[\]]/", $attrSubSet[1] )) || (strpos( strtolower( $attrSubSet[1] ), 'livescript:' ) !== false) ) continue;

			$attrFound = in_array( $attrSubSet[0], $this->attrArray );
			if( (! $attrFound && $this->attrMethod) || ($attrFound && ! $this->attrMethod) ) {
				if( $attrSubSet[1] ) $newSet[] = $attrSubSet[0] . '="' . $attrSubSet[1] . '"';
				elseif( $attrSubSet[1] == "0" ) $newSet[] = $attrSubSet[0] . '="0"';
				else $newSet[] = $attrSubSet[0] . '=""';
			}
		}
		;
		return $newSet;
	}

	function decode($source) {
		global $config;

		if( $this->allow_code )
			$source = preg_replace_callback( "#\[code\](.+?)\[/code\]#is",  array( &$this, 'code_tag'), $source );

		if( $this->safe_mode AND !$this->wysiwyg ) {

			$source = htmlspecialchars( $source, ENT_QUOTES, $config['charset'] );
			$source = str_replace( '&amp;', '&', $source );

		} else {

			$source = str_replace( "<>", "&lt;&gt;", str_replace( ">>", "&gt;&gt;", str_replace( "<<", "&lt;&lt;", $source ) ) );
			$source = str_replace( "<!--", "&lt;!--", $source );

		}

		return $source;
	}

	function BB_Parse($source, $use_html = TRUE) {

		global $config, $lang;

		$find = array ('/data:/i', '/about:/i', '/vbscript:/i', '/onclick/i', '/onload/i', '/onunload/i', '/onabort/i', '/onerror/i', '/onblur/i', '/onchange/i', '/onfocus/i', '/onreset/i', '/onsubmit/i', '/ondblclick/i', '/onkeydown/i', '/onkeypress/i', '/onkeyup/i', '/onmousedown/i', '/onmouseup/i', '/onmouseover/i', '/onmouseout/i', '/onselect/i', '/javascript/i' );

		$replace = array ("d&#097;ta:", "&#097;bout:", "vbscript<b></b>:", "&#111;nclick", "&#111;nload", "&#111;nunload", "&#111;nabort", "&#111;nerror", "&#111;nblur", "&#111;nchange", "&#111;nfocus", "&#111;nreset", "&#111;nsubmit", "&#111;ndblclick", "&#111;nkeydown", "&#111;nkeypress", "&#111;nkeyup", "&#111;nmousedown", "&#111;nmouseup", "&#111;nmouseover", "&#111;nmouseout", "&#111;nselect", "j&#097;vascript" );

		if( $use_html == false ) {
			$find[] = "'\r'";
			$replace[] = "";
			$find[] = "'\n'";
			$replace[] = "<br />";
		} else {
			$source = str_replace( "\r\n\r\n", "\n", $source );
		}

		$smilies_arr = explode( ",", $config['smilies'] );
		foreach ( $smilies_arr as $smile ) {
			$smile = trim( $smile );
			$find[] = "':$smile:'";
			$replace[] = "<!--smile:{$smile}--><img style=\"vertical-align: middle;border: none;\" alt=\"$smile\" src=\"" . $config['http_home_url'] . "engine/data/emoticons/{$smile}.gif\" /><!--/smile-->";
		}

		if( $this->filter_mode ) $source = $this->word_filter( $source );
		$source = $this->build_tabs($source, true);/*****CSS TABS FOR DLE BY MAD*****/
		
		$source = preg_replace( $find, $replace, $source );
		$source = preg_replace( "#<iframe#i", "&lt;iframe", $source );
		$source = preg_replace( "#<script#i", "&lt;script", $source );

		$source = str_replace( "`", "&#96;", $source );
		$source = str_ireplace( "{THEME}", "&#123;THEME}", $source );
		$source = str_ireplace( "{comments}", "&#123;comments}", $source );
		$source = str_ireplace( "{addcomments}", "&#123;addcomments}", $source );
		$source = str_ireplace( "{navigation}", "&#123;navigation}", $source );
		$source = str_ireplace( "[declination", "&#91;declination", $source );

		$source = str_replace( "<?", "&lt;?", $source );
		$source = str_replace( "?>", "?&gt;", $source );

		if ($config['parse_links']) {
			$source = preg_replace("#(^|\s|>)((http|https|ftp)://\w+[^\s\[\]\<]+)#i", '\\1[url]\\2[/url]', $source);
		}

		$count_start = substr_count ($source, "[quote");
		$count_end = substr_count ($source, "[/quote]");

		if ($count_start AND $count_start == $count_end) {
			$source = str_ireplace( "[quote=]", "[quote]", $source );

			if ( !$this->allow_code ) {
				$source = preg_replace_callback( "#\[(quote)\](.+?)\[/quote\]#is", array( &$this, 'clear_div_tag'), $source );
				$source = preg_replace_callback( "#\[(quote)=(.+?)\](.+?)\[/quote\]#is", array( &$this, 'clear_div_tag'), $source );
			}

			while( preg_match( "#\[quote\](.+?)\[/quote\]#is", $source ) ) {
				$source = preg_replace( "#\[quote\](.+?)\[/quote\]#is", "<!--QuoteBegin--><div class=\"quote\"><!--QuoteEBegin-->\\1<!--QuoteEnd--></div><!--QuoteEEnd-->", $source );
			}
			
			while( preg_match( "#\[quote=([^\]|\[|<]+)\](.+?)\[/quote\]#is", $source ) ) {
				$source = preg_replace( "#\[quote=([^\]|\[|<]+)\](.+?)\[/quote\]#is", "<!--QuoteBegin \\1 --><div class=\"title_quote\">{$lang['i_quote']} \\1</div><div class=\"quote\"><!--QuoteEBegin-->\\2<!--QuoteEnd--></div><!--QuoteEEnd-->", $source );
			}
		}

		$count_start = substr_count ($source, "[spoiler");
		$count_end = substr_count ($source, "[/spoiler]");

		if ($count_start AND $count_start == $count_end) {
			$source = str_ireplace( "[spoiler=]", "[spoiler]", $source );

			if ( !$this->allow_code ) {
				$source = preg_replace_callback( "#\[(spoiler)\](.+?)\[/spoiler\]#is", array( &$this, 'clear_div_tag'), $source );
				$source = preg_replace_callback( "#\[(spoiler)=(.+?)\](.+?)\[/spoiler\]#is", array( &$this, 'clear_div_tag'), $source );
			}
			while( preg_match( "#\[spoiler\](.+?)\[/spoiler\]#is", $source ) ) {
				$source = preg_replace_callback( "#\[spoiler\](.+?)\[/spoiler\]#is", array( &$this, 'build_spoiler'), $source );
			}
			
			while( preg_match( "#\[spoiler=([^\]|\[|<]+)\](.+?)\[/spoiler\]#is", $source ) ) {
				$source = preg_replace_callback( "#\[spoiler=([^\]|\[|<]+)\](.+?)\[/spoiler\]#is", array( &$this, 'build_spoiler'), $source);
			}

		}

		$source = preg_replace( "#\[code\](.+?)\[/code\]#is", "<pre><code>\\1</code></pre>", $source );

		if ( !$this->allow_code AND $this->edit_mode) {
			$source = preg_replace_callback( "#<pre><code>(.+?)</code></pre>#is", array( &$this, 'clear_p_tag'), $source );
		}

		$source = preg_replace( "#\[(left|right|center)\](.+?)\[/\\1\]#is", "<div style=\"text-align:\\1;\">\\2</div>", $source );

		while( preg_match( "#\[(b|i|s|u)\](.+?)\[/\\1\]#is", $source ) ) {
			$source = preg_replace( "#\[(b|i|s|u)\](.+?)\[/\\1\]#is", "<\\1>\\2</\\1>", $source );
		}
		
		if( $this->allow_url ) {

			$source = preg_replace_callback( "#\[(url)\](\S.+?)\[/url\]#i", array( &$this, 'build_url'), $source );
			$source = preg_replace_callback( "#\[(url)\s*=\s*\&quot\;\s*(\S+?)\s*\&quot\;\s*\](.*?)\[\/url\]#i", array( &$this, 'build_url'), $source );
			$source = preg_replace_callback( "#\[(url)\s*=\s*(\S.+?)\s*\](.*?)\[\/url\]#i", array( &$this, 'build_url'), $source );

			$source = preg_replace_callback( "#\[(leech)\](\S.+?)\[/leech\]#i", array( &$this, 'build_url'), $source );
			$source = preg_replace_callback( "#\[(leech)\s*=\s*\&quot\;\s*(\S+?)\s*\&quot\;\s*\](.*?)\[\/leech\]#i", array( &$this, 'build_url'), $source );
			$source = preg_replace_callback( "#\[(leech)\s*=\s*(\S.+?)\s*\](.*?)\[\/leech\]#i", array( &$this, 'build_url'), $source );

		} else {

			if( stristr( $source, "[url" ) !== false ) $this->not_allowed_tags = true;
			if( stristr( $source, "[leech" ) !== false ) $this->not_allowed_tags = true;
			if( stristr( $source, "&lt;a" ) !== false ) $this->not_allowed_tags = true;

		}

		if( $this->allow_image ) {

			$source = preg_replace_callback( "#\[img\](.+?)\[/img\]#i", array( &$this, 'build_image'), $source );
			$source = preg_replace_callback( "#\[img=(.+?)\](.+?)\[/img\]#i", array( &$this, 'build_image'), $source );

		} else {

			if( stristr( $source, "[img" ) !== false ) $this->not_allowed_tags = true;
			if( stristr( $source, "&lt;img" ) !== false ) $this->not_allowed_tags = true;

		}

		$source = preg_replace_callback( "#\[email\s*=\s*\&quot\;([\.\w\-]+\@[\.\w\-]+\.[\.\w\-]+)\s*\&quot\;\s*\](.*?)\[\/email\]#i", array( &$this, 'build_email'), $source );
		$source = preg_replace_callback( "#\[email\s*=\s*([\.\w\-]+\@[\.\w\-]+\.[\w\-]+)\s*\](.*?)\[\/email\]#i", array( &$this, 'build_email'), $source );

		if( ! $this->safe_mode ) {

			$source = preg_replace_callback( "'\[thumb\](.+?)\[/thumb\]'i", array( &$this, 'build_thumb'), $source );
			$source = preg_replace_callback( "'\[thumb=(.+?)\](.+?)\[/thumb\]'i", array( &$this, 'build_thumb'), $source );
			$source = preg_replace_callback( "'\[medium\](.+?)\[/medium\]'i", array( &$this, 'build_medium'), $source );
			$source = preg_replace_callback( "'\[medium=(.+?)\](.+?)\[/medium\]'i", array( &$this, 'build_medium'), $source );
			$source = preg_replace_callback( "#\[video\s*=\s*(\S.+?)\s*\]#i", array( &$this, 'build_video'), $source );
			$source = preg_replace_callback( "#\[audio\s*=\s*(\S.+?)\s*\]#i", array( &$this, 'build_audio'), $source );
			$source = preg_replace_callback( "#\[flash=([^\]]+)\](.+?)\[/flash\]#i", array( &$this, 'build_flash'), $source );
			$source = preg_replace_callback( "#\[media=([^\]]+)\]#i", array( &$this, 'build_media'), $source );

			$source = preg_replace_callback( "#\[ol=([^\]]+)\]\[\*\]#is", array( &$this, 'build_list'), $source );
			$source = preg_replace_callback( "#\[ol=([^\]]+)\](.+?)\[\*\]#is", array( &$this, 'build_list'), $source );
			$source = str_ireplace("[list][*]", "<!--dle_list--><ul><li>", $source);
			$source = preg_replace( "#\[list\](.+?)\[\*\]#is", "<!--dle_list--><ul><li>", $source );
			$source = str_replace("[*]", "</li><!--dle_li--><li>", $source);
			$source = str_ireplace("[/list]", "</li></ul><!--dle_list_end-->", $source);
			$source = str_ireplace("[/ol]", "</li></ol><!--dle_list_end-->", $source);

			$source = preg_replace_callback( "#\[(size)=([^\]]+)\]#i", array( &$this, 'font_change'), $source );
			$source = preg_replace_callback( "#\[(font)=([^\]]+)\]#i", array( &$this, 'font_change'), $source );
			$source = str_ireplace("[/size]", "<!--sizeend--></span><!--/sizeend-->", $source);
			$source = str_ireplace("[/font]", "<!--fontend--></span><!--/fontend-->", $source);

			if( $this->frame_count ) {
				$find=array();$replace=array();
				foreach ( $this->frame_code as $key_find => $key_replace ) {
					$find[] = $key_find;
					$replace[] = $key_replace;
				}

				$source = str_replace( $find, $replace, $source );
			}
		}

		$source = preg_replace_callback( "#\[(color)=([^\]]+)\]#i", array( &$this, 'font_change'), $source );

		$source = str_ireplace("[/color]", "<!--colorend--></span><!--/colorend-->", $source);

		$source = str_replace( "__CODENR__", "\r", $source );
		$source = str_replace( "__CODENN__", "\n", $source );

		return trim( $source );

	}

	function decodeBBCodes($txt, $use_html = TRUE, $wysiwig = false) {

		global $config;

		$find = array ();
		$result = array ();
		$txt = stripslashes( $txt );
		if( $this->filter_mode ) $txt = $this->word_filter( $txt, false );
		$txt = $this->build_tabs($txt, false);/*****CSS TABS FOR DLE BY MAD*****/
		
		$txt = preg_replace_callback( "#<!--(TBegin|MBegin):(.+?)-->(.+?)<!--(TEnd|MEnd)-->#i", array( &$this, 'decode_thumb'), $txt );
		$txt = preg_replace_callback( "#<!--TBegin-->(.+?)<!--TEnd-->#i", array( &$this, 'decode_oldthumb'), $txt );
		$txt = preg_replace( "#<!--QuoteBegin-->(.+?)<!--QuoteEBegin-->#", '[quote]', $txt );
		$txt = preg_replace( "#<!--QuoteBegin ([^>]+?) -->(.+?)<!--QuoteEBegin-->#", "[quote=\\1]", $txt );
		$txt = preg_replace( "#<!--QuoteEnd-->(.+?)<!--QuoteEEnd-->#", '[/quote]', $txt );
		$txt = preg_replace( "#<!--code1-->(.+?)<!--ecode1-->#", '[code]', $txt );
		$txt = preg_replace( "#<!--code2-->(.+?)<!--ecode2-->#", '[/code]', $txt );
		$txt = preg_replace_callback( "#<!--dle_leech_begin--><a href=\"(.+?)\"(.+?)>(.+?)</a><!--dle_leech_end-->#i", array( &$this, 'decode_leech'), $txt );
		$txt = preg_replace( "#<!--dle_video_begin-->(.+?)src=\"(.+?)\"(.+?)<!--dle_video_end-->#is", '[video=\\2]', $txt );
		$txt = preg_replace( "#<!--dle_video_begin:(.+?)-->(.+?)<!--dle_video_end-->#is", '[video=\\1]', $txt );
		$txt = preg_replace( "#<!--dle_audio_begin:(.+?)-->(.+?)<!--dle_audio_end-->#is", '[audio=\\1]', $txt );
		$txt = preg_replace_callback( "#<!--dle_image_begin:(.+?)-->(.+?)<!--dle_image_end-->#is", array( &$this, 'decode_dle_img'), $txt );
		$txt = preg_replace( "#<!--dle_youtube_begin:(.+?)-->(.+?)<!--dle_youtube_end-->#is", '[media=\\1]', $txt );
		$txt = preg_replace( "#<!--dle_media_begin:(.+?)-->(.+?)<!--dle_media_end-->#is", '[media=\\1]', $txt );
		$txt = preg_replace_callback( "#<!--dle_flash_begin:(.+?)-->(.+?)<!--dle_flash_end-->#is", array( &$this, 'decode_flash'), $txt );
		$txt = preg_replace( "#<!--dle_spoiler-->(.+?)<!--spoiler_text-->#is", '[spoiler]', $txt );
		$txt = preg_replace( "#<!--dle_spoiler (.+?) -->(.+?)<!--spoiler_text-->#is", '[spoiler=\\1]', $txt );
		$txt = str_replace( "<!--spoiler_text_end--></div><!--/dle_spoiler-->", '[/spoiler]', $txt );
		$txt = str_replace( "<!--dle_list--><ul><li>", "[list]\n[*]", $txt );
		$txt = str_replace( "</li></ul><!--dle_list_end-->", '[/list]', $txt );
		$txt = str_replace( "</li></ol><!--dle_list_end-->", '[/ol]', $txt );
		$txt = str_replace( "</li><!--dle_li--><li>", '[*]', $txt );
		$txt = str_replace( "<pre><code>", '[code]', $txt );
		$txt = str_replace( "</code></pre>", '[/code]', $txt );
		$txt = preg_replace( "#<!--dle_ol_(.+?)-->(.+?)<!--/dle_ol-->#i", "[ol=\\1]\n[*]", $txt );

		if( !$wysiwig ) {

			$txt = str_replace( "<b>", "[b]", str_replace( "</b>", "[/b]", $txt ) );
			$txt = str_replace( "<i>", "[i]", str_replace( "</i>", "[/i]", $txt ) );
			$txt = str_replace( "<u>", "[u]", str_replace( "</u>", "[/u]", $txt ) );
			$txt = str_replace( "<s>", "[s]", str_replace( "</s>", "[/s]", $txt ) );

			$txt = preg_replace_callback( "#<img src=[\"'](\S+?)['\"](.+?)>#i", array( &$this, 'decode_img'), $txt );

			$txt = preg_replace( "#<a href=[\"']mailto:(.+?)['\"]>(.+?)</a>#i", "[email=\\1]\\2[/email]", $txt );
			$txt = preg_replace_callback( "#<noindex><a href=\"(.+?)\"(.+?)>(.+?)</a></noindex>#i", array( &$this, 'decode_url'), $txt );
			$txt = preg_replace_callback( "#<a href=\"(.+?)\"(.+?)>(.+?)</a>#i", array( &$this, 'decode_url'), $txt );

			$txt = preg_replace( "#<!--sizestart:(.+?)-->(.+?)<!--/sizestart-->#", "[size=\\1]", $txt );
			$txt = preg_replace( "#<!--colorstart:(.+?)-->(.+?)<!--/colorstart-->#", "[color=\\1]", $txt );
			$txt = preg_replace( "#<!--fontstart:(.+?)-->(.+?)<!--/fontstart-->#", "[font=\\1]", $txt );

			$txt = str_replace( "<!--sizeend--></span><!--/sizeend-->", "[/size]", $txt );
			$txt = str_replace( "<!--colorend--></span><!--/colorend-->", "[/color]", $txt );
			$txt = str_replace( "<!--fontend--></span><!--/fontend-->", "[/font]", $txt );

			$txt = preg_replace( "#<div align=['\"](left|right|center)['\"]>(.+?)</div>#is", "[\\1]\\2[/\\1]", $txt );
			$txt = preg_replace( "#<div style=['\"]text-align:(left|right|center);['\"]>(.+?)</div>#is", "[\\1]\\2[/\\1]", $txt );



		} else {

			$txt = str_replace( "<!--sizeend--></span><!--/sizeend-->", "</span>", $txt );
			$txt = str_replace( "<!--colorend--></span><!--/colorend-->", "</span>", $txt );
			$txt = str_replace( "<!--fontend--></span><!--/fontend-->", "</span>", $txt );
			$txt = str_replace( "<!--/sizestart-->", "", $txt );
			$txt = str_replace( "<!--/colorstart-->", "", $txt );
			$txt = str_replace( "<!--/fontstart-->", "", $txt );
			$txt = preg_replace( "#<!--sizestart:(.+?)-->#", "", $txt );
			$txt = preg_replace( "#<!--colorstart:(.+?)-->#", "", $txt );
			$txt = preg_replace( "#<!--fontstart:(.+?)-->#", "", $txt );

		}

		$txt = preg_replace( "#<!--smile:(.+?)-->(.+?)<!--/smile-->#is", ':\\1:', $txt );

		$smilies_arr = explode( ",", $config['smilies'] );

		foreach ( $smilies_arr as $smile ) {
			$smile = trim( $smile );
			$replace[] = ":$smile:";
			$find[] = "#<img style=['\"]border: none;['\"] alt=['\"]" . $smile . "['\"] align=['\"]absmiddle['\"] src=['\"](.+?)" . $smile . ".gif['\"] />#is";
		}

		$txt = preg_replace( $find, $replace, $txt );

		if( ! $use_html ) {
			$txt = str_ireplace( "<br>", "\n", $txt );
			$txt = str_ireplace( "<br />", "\n", $txt );
		}

		if (!$this->safe_mode AND $this->edit_mode) $txt = htmlspecialchars( $txt, ENT_QUOTES, $config['charset'] );
		$this->codes_param['html'] = $use_html;
		$this->codes_param['wysiwig'] = $wysiwig;
		$txt = preg_replace_callback( "#\[code\](.+?)\[/code\]#is", array( &$this, 'decode_code'), $txt );

		return trim( $txt );

	}
	
	/*****CSS TABS FOR DLE BY MAD*****/
	function build_tabs($source, $html = ""){

		if ($html == TRUE) {

			$count_tab = preg_match_all("#\[tab\](.+?)\[/tab\]#is", $source, $tab);
			$num_tab = 1;

			for ($x = 0; $x < $count_tab; $x++) {

				$count_tabs = preg_match_all("#\[tabs=(.*?)\](.*?)\[/tabs\]#si", $tab[1][$x], $tabs);

				for ($i = 0; $i < $count_tabs; $i++) {

					$checked = ($num_tab == 1)? 'checked': '';
					$title = "<input id=\"tab{$num_tab}\" type=\"radio\" name=\"tabs\" {$checked}><label for=\"tab{$num_tab}\" title=".$tabs[1][$i].">".$tabs[1][$i]."</label>";
					$text  = "<section id=\"content{$num_tab}\">" . $tabs[2][$i] . "</section>";

					$tabs2[$x] .= $text;
					$tabs1[$x] .= $title;
					$num_tab++;
				}

				$source = str_replace($tab[0][$x], "<!--TabsBegin--><div class=\"tabs\">".$tabs1[$x].$tabs2[$x]."</div><!--/TabsEnd-->", $source);
			}

		} else {
			
			$count_tb = preg_match_all("#<!--TabsBegin--><div class=\"tabs\">(.*)<\/div><!--\/TabsEnd-->#si", $source, $tb);

			for ($x=0; $x< $count_tb; $x++){

				preg_match_all("#<label(.+?)>(.+?)</label>#si",$tb[1][$x], $title);
				preg_match_all("#<section(.+?)>(.+?)</section>#si",$tb[1][$x], $text);

				for ($i = 0; $i < count($title[2]); $i++) {

					$tabs2[$x] .="[tabs=".$title[2][$i]."]".$text[2][$i]."[/tabs]\n";
				}

				$source = str_replace($tb[0][$x],"[tab]\n".$tabs2[$x]."[/tab]",$source);
			}
		}

		return $source;
	}
	/*****CSS TABS FOR DLE BY MAD*****/
	
	function build_list( $matches=array() ) {
		$type = $matches[1];

		$allowed_types = array ("A", "a", "I", "i", "1");

		if (in_array($type, $allowed_types))
			return "<!--dle_ol_{$type}--><ol type=\"{$type}\"><li><!--/dle_ol-->";
		else
			return "<!--dle_ol_1--><ol type=\"1\"><li><!--/dle_ol-->";

	}

	function font_change( $matches=array() ) {

		$style = $matches[2];
		$type = $matches[1];

		$style = str_replace( '&quot;', '', $style );
		$style = preg_replace( "/[&\(\)\.\%\[\]<>\'\"]/", "", preg_replace( "#^(.+?)(?:;|$)#", "\\1", $style ) );

		if( $type == 'size' ) {
			$style = intval( $style );

			if( $this->font_sizes[$style] ) {
				$real = $this->font_sizes[$style];
			} else {
				$real = 12;
			}

			return "<!--sizestart:{$style}--><span style=\"font-size:" . $real . "pt;\"><!--/sizestart-->";
		}

		if( $type == 'font' ) {
			$style = preg_replace( "/[^\d\w\#\-\_\s]/s", "", $style );
			return "<!--fontstart:{$style}--><span style=\"font-family:" . $style . "\"><!--/fontstart-->";
		}

		$style = preg_replace( "/[^\d\w\#\s]/s", "", $style );
		return "<!--colorstart:{$style}--><span style=\"color:" . $style . "\"><!--/colorstart-->";
	}

	function build_email( $matches=array() ) {

		$matches[1] = $this->clear_url( $matches[1] );

		return "<a href=\"mailto:{$matches[1]}\">{$matches[2]}</a>";

	}

	function build_flash( $matches=array() ) {

		$size = $matches[1];
		$url = $matches[2];
		$size = explode(",", $size);

		$width = trim(intval($size[0]));
		$height = trim(intval($size[1]));

		if (!$width OR !$height) return "[flash=".implode(",",$size)."]".$url."[/flash]";

		$url = $this->clear_url( urldecode( $url ) );

		if( $url == "" ) return;

		if( preg_match( "/[?&;<\[\]]/", $url ) ) {

			return "[flash=".implode(",",$size)."]".$url."[/flash]";

		}

		return "<!--dle_flash_begin:{$width}||{$height}||{$url}--><object classid='clsid:D27CDB6E-AE6D-11cf-96B8-444553540000' width='$width' height='$height'><param name='movie' value='$url'><param name='wmode' value='transparent' /><param name='play' value='true'><param name='loop' value='true'><param name='quality' value='high'><param name='allowscriptaccess' value='never'><embed AllowScriptAccess='never' src='$url' width='$width' height='$height' play='true' loop='true' quality='high' wmode='transparent'></embed></object><!--dle_flash_end-->";


	}

	function decode_flash( $matches=array() )
	{
		$url = explode( "||", $matches[1] );

		return '[flash='.$url[0].','.$url[1].']'.$url[2].'[/flash]';
	}

	function build_media( $matches=array() ) {
		global $config;

		$url = $matches[1];

		if (!count($this->video_config)) {

			include (ENGINE_DIR . '/data/videoconfig.php');
			$this->video_config = $video_config;

		}

		$get_size = explode( ",", trim( $url ) );
		$sizes = array();

		if (count($get_size) == 2)  {

			$url = $get_size[1];
			$sizes = explode( "x", trim( $get_size[0] ) );

			$width = intval($sizes[0]) > 0 ? intval($sizes[0]) : $this->video_config['width'];
			$height = intval($sizes[1]) > 0 ? intval($sizes[1]) : $this->video_config['height'];

			if (substr ( $sizes[0], - 1, 1 ) == '%') $width = $width."%";
			if (substr ( $sizes[1], - 1, 1 ) == '%') $height = $height."%";

		} else {

			$width = $this->video_config['width'];
			$height = $this->video_config['height'];

		}

		$url = $this->clear_url( urldecode( $url ) );
		$url = str_replace("&amp;","&", $url );
		$url = str_replace("&amp;","&", $url );

		if( $url == "" ) return;

		if ( count($get_size) == 2 ) $decode_url = $width."x".$height.",".$url;
		else $decode_url = $url;

		$source = @parse_url ( $url );

		$source['host'] = str_replace( "www.", "", strtolower($source['host']) );

		if ($source['host'] != "youtube.com" AND $source['host'] != "youtu.be" AND $source['host'] != "vimeo.com" AND $source['host'] != "video.mail.ru" AND $source['host'] != "smotri.com" AND $source['host'] != "gametrailers.com") return "[media=".$url."]";

		if ($source['host'] == "youtube.com") {

			$a = explode('&', $source['query']);
			$i = 0;

			while ($i < count($a)) {
			    $b = explode('=', $a[$i]);
			    if ($b[0] == "v") $video_link = htmlspecialchars($b[1], ENT_QUOTES, $config['charset']);
			    $i++;
			}

		}

		if ($source['host'] == "youtu.be") {
			$video_link = str_replace( "/", "", $source['path'] );
			$video_link = htmlspecialchars($video_link, ENT_QUOTES, $config['charset']);
		}

		if ($source['host'] == "youtube.com" OR $source['host'] == "youtu.be") {

			if ( $this->video_config['tube_dle'] ) {

				if ( count($get_size) == 2 ) $decode_url = $width."x".$height.",http://www.youtube.com/watch?v=".$video_link;
				else $decode_url = "http://www.youtube.com/watch?v=".$video_link;

				if( $this->video_config['flv_watermark'] ) $watermark = "&amp;showWatermark=true&amp;watermarkPosition={$this->video_config['flv_watermark_pos']}&amp;watermarkMargin=0&amp;watermarkAlpha={$this->video_config['flv_watermark_al']}&amp;watermarkImageUrl={THEME}/dleimages/flv_watermark.png";
				else $watermark = "&amp;showWatermark=false";

				$preview = "&amp;showPreviewImage=true&amp;getYouTubeVideoInfo=true";

				if ( $this->video_config['play'] ) {

					$preview = "&amp;showPreviewImage=false&amp;autoPlays=true";

				}

				$this->video_config['buffer'] = intval($this->video_config['buffer']);

				if ( $this->video_config['autohide'] ) $autohide = "&amp;autoHideNav=true&amp;autoHideNavTime=3";
				else $autohide = "&amp;autoHideNav=false";

				$id_player = md5( microtime() );

				if( $this->video_config['use_html5'] ) {

					return "<!--dle_media_begin:{$decode_url}--><video width=\"{$width}\" height=\"{$height}\" preload=\"none\" controls=\"controls\">
							<source type=\"video/youtube\" src=\"http://www.youtube.com/watch?v={$video_link}\"></source>
							</video><!--dle_media_end-->";
				} else {

					return "<!--dle_media_begin:{$decode_url}--><object classid=\"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000\" codebase=\"http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,0,0\" width=\"{$width}\" height=\"{$height}\" id=\"Player-{$id_player}\">
						<param name=\"movie\" value=\"" . $config['http_home_url'] . "engine/classes/flashplayer/youtube.swf?width={$width}&amp;height={$height}&amp;showYouTubeHD=true&amp;videoUrl=http://www.youtube.com/watch?v={$video_link}{$watermark}{$preview}&amp;youTubePlaybackQuality={$this->video_config['youtube_q']}&amp;isYouTube=true&amp;rollOverAlpha=0.5&amp;contentBgAlpha=0.8&amp;progressBarColor={$this->video_config['progressBarColor']}&amp;defaultVolume=1&amp;fullSizeView=3&amp;showRewind=false&amp;showInfo=false&amp;showFullscreen=true&amp;showScale=true&amp;showSound=true&amp;showTime=true&amp;showCenterPlay=true{$autohide}&amp;videoLoop=false&amp;defaultBuffer={$this->video_config['buffer']}\" />
						<param name=\"allowFullScreen\" value=\"true\" />
						<param name=\"scale\" value=\"noscale\" />
						<param name=\"quality\" value=\"high\" />
						<param name=\"bgcolor\" value=\"#000000\" />
						<param name=\"wmode\" value=\"opaque\" />
						<embed src=\"" . $config['http_home_url'] . "engine/classes/flashplayer/youtube.swf?width={$width}&amp;height={$height}&amp;showYouTubeHD=true&amp;videoUrl=http://www.youtube.com/watch?v={$video_link}{$watermark}{$preview}&amp;youTubePlaybackQuality={$this->video_config['youtube_q']}&amp;isYouTube=true&amp;rollOverAlpha=0.5&amp;contentBgAlpha=0.8&amp;progressBarColor={$this->video_config['progressBarColor']}&amp;defaultVolume=1&amp;fullSizeView=3&amp;showRewind=false&amp;showInfo=false&amp;showFullscreen=true&amp;showScale=true&amp;showSound=true&amp;showTime=true&amp;showCenterPlay=true{$autohide}&amp;videoLoop=false&amp;defaultBuffer={$this->video_config['buffer']}\" quality=\"high\" bgcolor=\"#000000\" wmode=\"opaque\" allowFullScreen=\"true\" width=\"{$width}\" height=\"{$height}\" align=\"middle\" type=\"application/x-shockwave-flash\" pluginspage=\"http://www.macromedia.com/go/getflashplayer\"></embed>
						</object><!--dle_media_end-->";
				}

			} else return '<!--dle_media_begin:'.$decode_url.'--><iframe title="YouTube video player" width="'.$width.'" height="'.$height.'" src="http://www.youtube.com/embed/'.$video_link.'?rel='.intval($this->video_config['tube_related']).'&amp;wmode=transparent" frameborder="0" allowfullscreen></iframe><!--dle_media_end-->';

		} elseif ($source['host'] == "smotri.com") {

			$a = explode('&', $source['query']);
			$i = 0;

			while ($i < count($a)) {
			    $b = explode('=', $a[$i]);
			    if ($b[0] == "id") $video_link = totranslit($b[1], false);
			    $i++;
			}

			if ( count($get_size) == 2 ) $decode_url = $width."x".$height.",http://smotri.com/video/view/?id=".$video_link;
			else $decode_url = "http://smotri.com/video/view/?id=".$video_link;

			return '<!--dle_media_begin:'.$decode_url.'--><object id="smotriComVideoPlayer" classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" width="'.$width.'" height="'.$height.'"><param name="movie" value="http://pics.smotri.com/player.swf?file='.$video_link.'&amp;bufferTime=3&amp;autoStart=false&amp;str_lang=rus&amp;xmlsource=http%3A%2F%2Fpics.smotri.com%2Fcskins%2Fblue%2Fskin_color.xml&xmldatasource=http%3A%2F%2Fpics.smotri.com%2Fskin_ng.xml" /><param name="allowScriptAccess" value="always" /><param name="allowFullScreen" value="true" /><param name="wmode" value="opaque" /><embed src="http://pics.smotri.com/player.swf?file='.$video_link.'&amp;bufferTime=3&amp;autoStart=false&amp;str_lang=rus&amp;xmlsource=http%3A%2F%2Fpics.smotri.com%2Fcskins%2Fblue%2Fskin_color.xml&amp;xmldatasource=http%3A%2F%2Fpics.smotri.com%2Fskin_ng.xml" quality="high" allowscriptaccess="always" allowfullscreen="true" wmode="opaque"  width="'.$width.'" height="'.$height.'" type="application/x-shockwave-flash"></embed></object><!--dle_media_end-->';

		} elseif ($source['host'] == "vimeo.com") {

			$video_link = intval( substr($source['path'], 1) );

			if ( count($get_size) == 2 ) $decode_url = $width."x".$height.",http://vimeo.com/".$video_link;
			else $decode_url = "http://vimeo.com/".$video_link;

			return '<!--dle_media_begin:'.$decode_url.'--><iframe width="'.$width.'" height="'.$height.'" src="http://player.vimeo.com/video/'.$video_link.'" frameborder="0" allowfullscreen></iframe><!--dle_media_end-->';

		} elseif ($source['host'] == "gametrailers.com") {

			if (substr ( $source['path'], - 1, 1 ) == '/') $source['path'] = substr ( $source['path'], 0, - 1 );
			$video_link = explode( "/", $source['path'] );
			$video_link = intval( end( $video_link ) );

			if ( count($get_size) == 2 ) $decode_url = $width."x".$height.",http://www.gametrailers.com".$source['path'];
			else $decode_url = "http://www.gametrailers.com".$source['path'];

			$decode_url = $this->clear_url( urldecode( $decode_url ) );

			return '<!--dle_media_begin:'.$decode_url.'--><embed src="http://media.mtvnservices.com/mgid:moses:video:gametrailers.com:'.$video_link.'" width="'.$width.'" height="'.$height.'" type="application/x-shockwave-flash" allowFullScreen="true" allowScriptAccess="always" base="." flashVars="" wmode="opaque"></embed><!--dle_media_end-->';



		} elseif ($source['host'] == "video.mail.ru") {

			$video_link = substr($source['path'], 1);
			$video_link = str_replace( ".html", "", $video_link );

			if ( count($get_size) == 2 ) $decode_url = $width."x".$height.",http://video.mail.ru/".$video_link.".html";
			else $decode_url = "http://video.mail.ru/".$video_link.".html";

			return '<!--dle_media_begin:'.$decode_url.'--><object width="'.$width.'" height="'.$height.'"><param name="allowScriptAccess" value="always" /><param name="movie" value="http://img.mail.ru/r/video2/player_v2.swf?movieSrc='.$video_link.'" /><param name="wmode" value="transparent" /><param name="allowFullScreen" value="true" /><embed src="http://img.mail.ru/r/video2/player_v2.swf?movieSrc='.$video_link.'" type="application/x-shockwave-flash" wmode="transparent" width="'.$width.'" height="'.$height.'" allowScriptAccess="always" allowFullScreen="true"></embed></object><!--dle_media_end-->';

		}

	}

	function build_url( $matches=array() ) {
		global $config, $member_id, $user_group;

		$url = array();

		if ($matches[1] == "leech" ) $url['leech'] = 1;

		$option=explode("|", $matches[2]);
		
		$url['html'] = $option[0];
		$url['tooltip'] = $option[1];
		$url['show'] = $matches[3];
		
		if ( !$url['show'] ) $url['show'] = $url['html'];

		if ( $user_group[$member_id['user_group']]['force_leech'] ) $url['leech'] = 1;

		if( preg_match( "/([\.,\?]|&#33;)$/", $url['show'], $match ) ) {
			$url['end'] = $match[1];
			$url['show'] = preg_replace( "/([\.,\?]|&#33;)$/", "", $url['show'] );
		}

		$url['html'] = $this->clear_url( $url['html'] );
		$url['show'] = stripslashes( $url['show'] );

		if( $this->safe_mode ) {

			$url['show'] = str_replace( "&nbsp;", " ", $url['show'] );

			if (strlen(trim($url['show'])) < 3 )
				return "[url=" . $url['html'] . "]" . $url['show'] . "[/url]";

		}

		if( strpos( $url['html'], $config['http_home_url'] ) !== false AND strpos( $url['html'], $config['admin_path'] ) !== false ) {

			return "[url=" . $url['html'] . "]" . $url['show'] . "[/url]";

		}

		if( ! preg_match( "#^(http|news|https|ed2k|ftp|aim|mms)://|(magnet:?)#", $url['html'] ) AND $url['html'][0] != "/" AND $url['html'][0] != "#") {
			$url['html'] = 'http://' . $url['html'];
		}

		if ($url['html'] == 'http://' )
			return "[url=" . $url['html'] . "]" . $url['show'] . "[/url]";

		$url['show'] = str_replace( "&amp;amp;", "&amp;", $url['show'] );
		$url['show'] = preg_replace( "/javascript:/i", "javascript&#58; ", $url['show'] );

		if( $this->check_home( $url['html'] ) OR $url['html'][0] == "/" OR $url['html'][0] == "#") $target = "";
		else $target = " target=\"_blank\"";

		if( $url['tooltip'] ) {
			$url['tooltip'] = htmlspecialchars( strip_tags( stripslashes( $url['tooltip'] ) ), ENT_QUOTES, $config['charset'] );
			
			if( $this->safe_mode ) {
				$url['tooltip'] = str_replace( "&amp;", "&", $url['tooltip'] );
			}
			
			$target = "title=\"".$url['tooltip']."\"".$target;
		}
		
		if( $url['leech'] ) {

			$url['html'] = $config['http_home_url'] . "engine/go.php?url=" . rawurlencode( base64_encode( $url['html'] ) );

			return "<!--dle_leech_begin--><a href=\"" . $url['html'] . "\" " . $target . ">" . $url['show'] . "</a><!--dle_leech_end-->" . $url['end'];

		} else {

			if ($this->safe_mode AND !$config['allow_search_link'] AND $target)
				return "<a href=\"" . $url['html'] . "\" " . $target . " rel=\"nofollow\">" . $url['show'] . "</a>" . $url['end'];
			else
				return "<a href=\"" . $url['html'] . "\" " . $target . ">" . $url['show'] . "</a>" . $url['end'];

		}

	}

	function code_tag( $matches=array() ) {

		$txt = $matches[1];

		if( $txt == "" ) {
			return;
		}

		$this->code_count ++;

		if ( $this->edit_mode )	{
			$txt = str_replace( "&", "&amp;", $txt );
			$txt = str_replace( "'", "&#39;", $txt );
			$txt = str_replace( "<", "&lt;", $txt );
			$txt = str_replace( ">", "&gt;", $txt );
			$txt = str_replace( "&quot;", "&#34;", $txt );
			$txt = str_replace( '"', "&#34;", $txt );
			$txt = str_replace( ":", "&#58;", $txt );
			$txt = str_replace( "[", "&#91;", $txt );
			$txt = str_replace( "]", "&#93;", $txt );
			$txt = str_replace( "{", "&#123;", $txt );
			$txt = str_replace( "\r", "__CODENR__", $txt );
			$txt = str_replace( "\n", "__CODENN__", $txt );

		}

		$p = "[code]{" . $this->code_count . "}[/code]";

		$this->code_text[$p] = "[code]{$txt}[/code]";

		return $p;
	}

	function check_frame( $matches=array() ) {
		$allow_frame = false;

		if (strpos($matches[3], "src=") !== false) return "";

		foreach ($this->allowed_domains as $domain) {
			if (strpos($matches[2], $domain) === 0) {
				$matches[2] = str_replace ($domain, "", $matches[2]);
				$matches[2] = $domain.$matches[2];
				$allow_frame = true;
			}

		}

		if ( !$allow_frame ) return "";

		$this->frame_count ++;

		$p = "[frame{$this->frame_count}]";

		$this->frame_code[$p] = "<iframe src=\"{$matches[2]}\"".$matches[1].$matches[3]."></iframe>";

		return $p;

	}

	function decode_code( $matches=array() ) {

		$txt = $matches[1];

		if ( !$this->codes_param['wysiwig'] AND $this->edit_mode )	{

			$txt = str_replace( "&amp;", "&", $txt );
		}

		if( !$this->codes_param['wysiwig'] AND $this->codes_param['html'] ) {
			$txt = str_replace( "&lt;br /&gt;", "\n", $txt );
		}

		if ( $this->codes_param['wysiwig'] == 1 AND $this->edit_mode ) {
			$txt = str_replace( "\n", "&lt;br /&gt;", $txt );
			return "[code]".$txt."[/code]";
		}

		if ( $this->codes_param['wysiwig'] == 2 AND $this->edit_mode ) {
			$txt = str_replace( "\n", "&lt;/p&gt;&lt;p&gt;", $txt );
			return "&lt;p&gt;[code]".$txt."[/code]&lt;/p&gt;";
		}

		return "[code]".$txt."[/code]";
	}


	function build_video( $matches=array() ) {
		global $config;

		$url = $matches[1];

		if (!count($this->video_config)) {

			include (ENGINE_DIR . '/data/videoconfig.php');
			$this->video_config = $video_config;

		}

		$get_size = array();
		$sizes = array();

		$get_size = explode( ",", trim( $url ) );

		if (count($get_size) > 1 AND ( stripos ( $get_size[0], "http" ) === false OR stripos ( $get_size[0], "rtmp:" ) === false ) )  {

			$sizes = explode( "x", trim( $get_size[0] ) );

			if (count($sizes) == 2) {

				$width = intval($sizes[0]) > 0 ? intval($sizes[0]) : $this->video_config['width'];
				$height = intval($sizes[1]) > 0 ? intval($sizes[1]) : $this->video_config['height'];

				if (substr ( $sizes[0], - 1, 1 ) == '%') $width = $width."%";
				if (substr ( $sizes[1], - 1, 1 ) == '%') $height = $height."%";

			} else {

				$width = $this->video_config['width'];
				$height = $this->video_config['height'];


			}

		} else {

			$width = $this->video_config['width'];
			$height = $this->video_config['height'];

		}

		if (count($get_size) == 3)  $url = $get_size[1].",".$get_size[2];
		elseif (count($get_size) == 2 AND count($sizes) == 2) $url = $get_size[1];

		$option = explode( "|", trim( $url ) );

		$url = $this->clear_url( $option[0] );

		$type = explode( ".", $url );
		$type = strtolower( end( $type ) );

		if( preg_match( "/[?&;<\[\]]/", $url ) ) {

			return "[video=" . $url . "]";

		}

		if( $option[1] != "" ) {

			$option[1] = htmlspecialchars( strip_tags( stripslashes( $option[1] ) ), ENT_QUOTES, $config['charset'] );
			$decode_url = $url . "|" . $option[1];

		} else
			$decode_url = $url;


		if ( count($sizes) == 2 ) $decode_url = $width."x".$height.",".$decode_url;

		if ( stripos ( $url, "rtmp:" ) === false ) $detect_rtmp = false; else $detect_rtmp = true;

		if( $type == "flv" or $type == "mp4" or $type == "m4v" or $type == "m4a" or $type == "mov" or $type == "3gp" or $type == "f4v" or $detect_rtmp) {

			if( $this->video_config['flv_watermark'] ) $watermark = "&amp;showWatermark=true&amp;watermarkPosition={$this->video_config['flv_watermark_pos']}&amp;watermarkMargin=0&amp;watermarkAlpha={$this->video_config['flv_watermark_al']}&amp;watermarkImageUrl={THEME}/dleimages/flv_watermark.png";
			else $watermark = "&amp;showWatermark=false";

			$preview = "&amp;showPreviewImage=true&amp;previewImageUrl=";

			if ( $this->video_config['preview'] ) $preview = "&amp;showPreviewImage=true&amp;previewImageUrl={THEME}/dleimages/videopreview.jpg";

			if ( $this->video_config['startframe'] ) $preview = "&amp;showPreviewImage=false";

			if( $option[1] != "" ) {

				$preview = "&amp;showPreviewImage=true&amp;previewImageUrl=".$option[1];

			}

			if ( $this->video_config['play'] ) {

				$preview = "&amp;showPreviewImage=false&amp;autoPlays=true";

			}


			$this->video_config['buffer'] = intval($this->video_config['buffer']);
			$this->video_config['fullsizeview'] = intval($this->video_config['fullsizeview']);

			if ( $this->video_config['autohide'] ) $autohide = "&amp;autoHideNav=true&amp;autoHideNavTime=3";
			else $autohide = "&amp;autoHideNav=false";

			$id_player = md5( microtime() );

			$list = explode( ",", $url );
			$url = trim($list[0]);

			if (count($list) > 1 ){

				$url_hd = trim($list[1]);

			} else {

				$url_hd = '';
			}

			if ( $detect_rtmp ) {

				$video_url = "&amp;rtmpURI=".$url."&amp;videoUrl=".$url_hd;

			} else {

				$video_url = "&amp;videoUrl=".$url."&amp;videoHDUrl=".$url_hd;

			}

			if( $type == "mp4" AND $this->video_config['use_html5'] ) {

				if( $option[1] != "" ) {

					$preview = $option[1];

				} else {

					$preview = "";
				}

				return "<!--dle_video_begin:{$decode_url}--><video width=\"{$width}\" height=\"{$height}\" preload=\"metadata\" poster=\"{$preview}\" controls=\"controls\">
						<source type=\"video/mp4\" src=\"{$url}\"></source>
						</video><!--dle_video_end-->";

			} else {

						return "<!--dle_video_begin:{$decode_url}--><object classid=\"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000\" codebase=\"http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,0,0\" width=\"{$width}\" height=\"{$height}\" id=\"Player-{$id_player}\">
							<param name=\"movie\" value=\"" . $config['http_home_url'] . "engine/classes/flashplayer/media_player_v3.swf?stageW={$width}&amp;stageH={$height}&amp;contentType=video{$video_url}{$watermark}{$preview}&amp;isYouTube=false&amp;rollOverAlpha=0.5&amp;contentBgAlpha=0.8&amp;progressBarColor={$this->video_config['progressBarColor']}&amp;defaultVolume=1&amp;fullSizeView={$this->video_config['fullsizeview']}&amp;showRewind=false&amp;showInfo=false&amp;showFullscreen=true&amp;showScale=true&amp;showSound=true&amp;showTime=true&amp;showCenterPlay=true{$autohide}&amp;videoLoop=false&amp;defaultBuffer={$this->video_config['buffer']}\" />
							<param name=\"allowFullScreen\" value=\"true\" />
							<param name=\"scale\" value=\"noscale\" />
							<param name=\"quality\" value=\"high\" />
							<param name=\"bgcolor\" value=\"#000000\" />
							<param name=\"wmode\" value=\"opaque\" />
							<embed src=\"" . $config['http_home_url'] . "engine/classes/flashplayer/media_player_v3.swf?stageW={$width}&amp;stageH={$height}&amp;contentType=video{$video_url}{$watermark}{$preview}&amp;isYouTube=false&amp;rollOverAlpha=0.5&amp;contentBgAlpha=0.8&amp;progressBarColor={$this->video_config['progressBarColor']}&amp;defaultVolume=1&amp;fullSizeView={$this->video_config['fullsizeview']}&amp;showRewind=false&amp;showInfo=false&amp;showFullscreen=true&amp;showScale=true&amp;showSound=true&amp;showTime=true&amp;showCenterPlay=true{$autohide}&amp;videoLoop=false&amp;defaultBuffer={$this->video_config['buffer']}\" quality=\"high\" bgcolor=\"#000000\" wmode=\"opaque\" allowFullScreen=\"true\" width=\"{$width}\" height=\"{$height}\" align=\"middle\" type=\"application/x-shockwave-flash\" pluginspage=\"http://www.macromedia.com/go/getflashplayer\"></embed>
							</object><!--dle_video_end-->";
			}

		} elseif( $type == "avi" OR $type == "divx" OR $type == "mkv" ) {

			$url = htmlspecialchars( trim( $url ) , ENT_QUOTES, $config['charset'] );

			return "<!--dle_video_begin:{$decode_url}--><object classid=\"clsid:67DABFBF-D0AB-41fa-9C46-CC0F21721616\" width=\"{$width}\" height=\"{$height}\" codebase=\"http://go.divx.com/plugin/DivXBrowserPlugin.cab\">
				<param name=\"custommode\" value=\"none\" />
				<param name=\"mode\" value=\"zero\" />
				<param name=\"autoPlay\" value=\"{$this->video_config['play']}\" />
				<param name=\"minVersion\" value=\"2.0.0\" />
				<param name=\"src\" value=\"{$url}\" />
				<param name=\"previewImage\" value=\"{$option[1]}\" />
				<embed type=\"video/divx\" src=\"{$url}\" custommode=\"none\" width=\"{$width}\" height=\"{$height}\" mode=\"zero\"  autoPlay=\"{$this->video_config['play']}\" previewImage=\"{$option[1]}\" minVersion=\"2.0.0\" pluginspage=\"http://go.divx.com/plugin/download/\">
				</embed>
				</object><!--dle_video_end-->";

		} else {

			$url = htmlspecialchars( trim( $url ) , ENT_QUOTES, $config['charset'] );

			return "<!--dle_video_begin:{$decode_url}--><object id=\"mediaPlayer\" width=\"{$width}\" height=\"{$height}\" classid=\"CLSID:6BF52A52-394A-11d3-B153-00C04F79FAA6\" standby=\"Loading Microsoft Windows Media Player components...\" type=\"application/x-oleobject\">
				<param name=\"url\" VALUE=\"{$url}\" />
				<param name=\"autoStart\" VALUE=\"{$this->video_config['play']}\" />
				<param name=\"showControls\" VALUE=\"true\" />
				<param name=\"TransparentatStart\" VALUE=\"false\" />
				<param name=\"AnimationatStart\" VALUE=\"true\" />
				<param name=\"StretchToFit\" VALUE=\"true\" />
				<embed pluginspage=\"http://www.microsoft.com/Windows/Downloads/Contents/MediaPlayer/\" src=\"{$url}\" width=\"{$width}\" height=\"{$height}\" type=\"application/x-mplayer2\" autorewind=\"1\" showstatusbar=\"1\" showcontrols=\"1\" autostart=\"{$this->video_config['play']}\" allowchangedisplaysize=\"1\" volume=\"70\" stretchtofit=\"1\"></embed>
				</object><!--dle_video_end-->";
		}

	}
	function build_audio( $matches=array() ) {
		global $config;

		$url = $matches[1];

		if( $url == "" ) return;

		if (!count($this->video_config)) {

			include (ENGINE_DIR . '/data/videoconfig.php');
			$this->video_config = $video_config;

		}

		$get_size = explode( ",", trim( $url ) );
		$sizes = array();

		if (count($get_size) == 2)  {

			$url = $get_size[1];
			$sizes = explode( "x", trim( $get_size[0] ) );

			$width = intval($sizes[0]) > 0 ? intval($sizes[0]) : $this->video_config['audio_width'];
			$height = intval($sizes[1]) > 0 ? intval($sizes[1]) : "27";

			if (substr ( $sizes[0], - 1, 1 ) == '%') $width = $width."%";
			if (substr ( $sizes[1], - 1, 1 ) == '%') $height = $height."%";

		} else {

			$width = $this->video_config['audio_width'];
			$height = 27;

		}

		if( preg_match( "/[?&;%<\[\]]/", $url ) ) {

			return "[audio=" . $url . "]";
		}

		$url = $this->clear_url( $url );

		if ( count($get_size) == 2 ) $decode_url = $width."x".$height.",".$url;
		else $decode_url = $url;

		$id_player = md5( microtime() );

		$this->video_config['buffer'] = intval($this->video_config['buffer']);


			return "<!--dle_audio_begin:{$decode_url}--><object classid=\"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000\" codebase=\"http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,0,0\" width=\"{$width}\" height=\"{$height}\" id=\"Player-{$id_player}\">
				<param name=\"movie\" value=\"" . $config['http_home_url'] . "engine/classes/flashplayer/media_player_v3.swf?stageW={$width}&amp;stageH={$height}&amp;contentType=audio&amp;videoUrl={$url}&amp;showWatermark=false&amp;showPreviewImage=true&amp;previewImageUrl=&amp;autoPlays={$this->video_config['play']}&amp;isYouTube=false&amp;rollOverAlpha=0.5&amp;contentBgAlpha=0.8&amp;progressBarColor={$this->video_config['progressBarColor']}&amp;defaultVolume=1&amp;fullSizeView=2&amp;showRewind=false&amp;showInfo=false&amp;showFullscreen=true&amp;showScale=false&amp;showSound=true&amp;showTime=true&amp;showCenterPlay=false&amp;autoHideNav=false&amp;videoLoop=false&amp;defaultBuffer={$this->video_config['buffer']}\" />
				<param name=\"allowFullScreen\" value=\"false\" />
				<param name=\"scale\" value=\"noscale\" />
				<param name=\"quality\" value=\"high\" />
				<param name=\"bgcolor\" value=\"#000000\" />
				<param name=\"wmode\" value=\"opaque\" />
				<embed src=\"" . $config['http_home_url'] . "engine/classes/flashplayer/media_player_v3.swf?stageW={$width}&amp;stageH={$height}&amp;contentType=audio&amp;videoUrl={$url}&amp;showWatermark=false&amp;showPreviewImage=true&amp;previewImageUrl=&amp;autoPlays={$this->video_config['play']}&amp;isYouTube=false&amp;rollOverAlpha=0.5&amp;contentBgAlpha=0.8&amp;progressBarColor={$this->video_config['progressBarColor']}&amp;defaultVolume=1&amp;fullSizeView=2&amp;showRewind=false&amp;showInfo=false&amp;showFullscreen=true&amp;showScale=false&amp;showSound=true&amp;showTime=true&amp;showCenterPlay=false&amp;autoHideNav=false&amp;videoLoop=false&amp;defaultBuffer={$this->video_config['buffer']}\" quality=\"high\" bgcolor=\"#000000\" wmode=\"opaque\" allowFullScreen=\"false\" width=\"{$width}\" height=\"{$height}\" align=\"middle\" type=\"application/x-shockwave-flash\" pluginspage=\"http://www.macromedia.com/go/getflashplayer\"></embed>
				</object><!--dle_audio_end-->";


	}

	function build_image( $matches=array() ) {
		global $config;

		if(count($matches) == 2 ) {

			$align = "";
			$url = $matches[1];

		} else {
			$align = $matches[1];
			$url = $matches[2];
		}

		$url = trim( $url );
		$url = urldecode( $url );
		$option = explode( "|", trim( $align ) );
		$align = $option[0];

		if( $align != "left" and $align != "right" ) $align = '';

		if( preg_match( "/[?&;%<\[\]]/", $url ) ) {

			if( $align != "" ) return "[img=" . $align . "]" . $url . "[/img]";
			else return "[img]" . $url . "[/img]";

		}

		$url = $this->clear_url( urldecode( $url ) );

		$info = $url;

		$info = $info."|".$align;

		if( $url == "" ) return;

		if( $option[1] != "" ) {

			$alt = htmlspecialchars( strip_tags( stripslashes( $option[1] ) ), ENT_QUOTES, $config['charset'] );
			$info = $info."|".$alt;
			$caption = "<span class=\"highslide-caption\">" . $alt . "</span>";
			$alt = "alt=\"" . $alt . "\" title=\"" . $alt . "\" ";

		} else {

			$alt = htmlspecialchars( strip_tags( stripslashes( $_POST['title'] ) ), ENT_QUOTES, $config['charset'] );
			$caption = "";
			$alt = "alt=\"" . $alt . "\" title=\"" . $alt . "\" ";

		}

		if( intval( $config['tag_img_width'] ) ) {

			if (clean_url( $config['http_home_url'] ) != clean_url ( $url ) ) {

				$img_info = @getimagesize( $url );

				if( $img_info[0] > $config['tag_img_width'] ) {

					$out_heigh = ($img_info[1] / 100) * ($config['tag_img_width'] / ($img_info[0] / 100));
					$out_heigh = floor( $out_heigh );

					if( $align == '' ) return "<!--dle_image_begin:{$info}--><a href=\"{$url}\" onclick=\"return hs.expand(this)\" ><img src=\"$url\" width=\"{$config['tag_img_width']}\" height=\"{$out_heigh}\" {$alt} /></a>{$caption}<!--dle_image_end-->";
					else return "<!--dle_image_begin:{$info}--><a href=\"{$url}\" onclick=\"return hs.expand(this)\" ><img src=\"$url\" width=\"{$config['tag_img_width']}\" height=\"{$out_heigh}\" style=\"float:{$align};\" {$alt} /></a>{$caption}<!--dle_image_end-->";


				}
			}
		}


		if( $align == '' ) return "<!--dle_image_begin:{$info}--><img src=\"{$url}\" {$alt} /><!--dle_image_end-->";
		else return "<!--dle_image_begin:{$info}--><img src=\"{$url}\" style=\"float:{$align};\" {$alt} /><!--dle_image_end-->";

	}

	function decode_dle_img( $matches=array() ) {

		$txt = $matches[1];
		$txt = explode("|", $txt );
		$url = $txt[0];
		$align = $txt[1];
		$alt = $txt[2];
		$extra = "";

		if( ! $align and ! $alt ) return "[img]" . $url . "[/img]";

		if( $align ) $extra = $align;
		if( $alt ) {

			$alt = str_replace("&#039;", "'", $alt);
			$alt = str_replace("&quot;", '"', $alt);
			$alt = str_replace("&amp;", '&', $alt);
			$extra .= "|" . $alt;

		}

		return "[img=" . $extra . "]" . $url . "[/img]";

	}

	function clear_p_tag( $matches=array() ) {

		$txt = $matches[1];

		$txt = str_replace("\r", "", $txt);
		$txt = str_replace("\n", "", $txt);

		$txt = preg_replace('/<p[^>]*>/', '', $txt);
		$txt = str_replace("</p>", "\n", $txt);
		$txt = preg_replace('/<div[^>]*>/', '', $txt);
		$txt = str_replace("</div>", "\n", $txt);
		$txt = preg_replace('/<br[^>]*>/', "\n", $txt);

		return "<pre><code>".$txt."</code></pre>";

	}

	function clear_div_tag( $matches=array() ) {

		$spoiler = array();

		if ( count($matches) == 3 ) {
			$spoiler['title'] = '';
			$spoiler['txt'] = $matches[2];
		} else {
			$spoiler['title'] = $matches[2];
			$spoiler['txt'] = $matches[3];
		}

		$tag = $matches[1];

		$spoiler['txt'] = preg_replace('/<div[^>]*>/', '', $spoiler['txt']);
		$spoiler['txt'] = preg_replace('/<p[^>]*>/', '', $spoiler['txt']);
		$spoiler['txt'] = str_replace("</p>", "<br />", $spoiler['txt']);
		$spoiler['txt'] = str_replace("</div>", "<br />", $spoiler['txt']);

		if ($spoiler['title'])
			return "[{$tag}={$spoiler['title']}]".$spoiler['txt']."[/{$tag}]";
		else
			return "[{$tag}]".$spoiler['txt']."[/{$tag}]";

	}

	function build_thumb( $matches=array() ) {
		global $config;

		if (count($matches) == 2 ) {
			$align = "";
			$gurl = $matches[1];
		} else {
			$align = $matches[1];
			$gurl = $matches[2];
		}

		if( preg_match( "/[?&;%<\[\]]/", $gurl ) ) {

			if( $align != "" ) return "[thumb=" . $align . "]" . $gurl . "[/thumb]";
			else return "[thumb]" . $gurl . "[/thumb]";

		}

		$gurl = $this->clear_url( urldecode( $gurl ) );

		$url = preg_replace( "'([^\[]*)([/\\\\])(.*?)'i", "\\1\\2thumbs\\2\\3", $gurl );

		$url = trim( $url );
		$gurl = trim( $gurl );
		$option = explode( "|", trim( $align ) );

		$align = $option[0];

		if( $align != "left" and $align != "right" ) $align = '';

		$url = $this->clear_url( urldecode( $url ) );

		$info = $gurl;
		$info = $info."|".$align;

		if( $gurl == "" or $url == "" ) return;

		if( $option[1] != "" ) {

			$alt = htmlspecialchars( strip_tags( stripslashes( $option[1] ) ), ENT_QUOTES, $config['charset'] );

			$alt = str_replace("&amp;amp;","&amp;",$alt);

			$info = $info."|".$alt;
			$caption = "<span class=\"highslide-caption\">" . $alt . "</span>";
			$alt = "alt=\"" . $alt . "\" title=\"" . $alt . "\" ";

		} else {

			$alt = htmlspecialchars( strip_tags( stripslashes( $_POST['title'] ) ), ENT_QUOTES, $config['charset'] );
			$alt = "alt='" . $alt . "' title='" . $alt . "' ";
			$caption = "";

		}

		if( $align == '' ) return "<!--TBegin:{$info}--><a href=\"$gurl\" rel=\"highslide\" class=\"highslide\"><img src=\"$url\" {$alt} /></a>{$caption}<!--TEnd-->";
		else return "<!--TBegin:{$info}--><a href=\"$gurl\" rel=\"highslide\" class=\"highslide\"><img src=\"$url\" style=\"float:{$align};\" {$alt} /></a>{$caption}<!--TEnd-->";

	}


	function build_medium( $matches=array() ) {
		global $config;

		if (count($matches) == 2 ) {
			$align = "";
			$gurl = $matches[1];
		} else {
			$align = $matches[1];
			$gurl = $matches[2];
		}

		if( preg_match( "/[?&;%<\[\]]/", $gurl ) ) {

			if( $align != "" ) return "[medium=" . $align . "]" . $gurl . "[/medium]";
			else return "[medium]" . $gurl . "[/medium]";

		}

		$gurl = $this->clear_url( urldecode( $gurl ) );

		$url = preg_replace( "'([^\[]*)([/\\\\])(.*?)'i", "\\1\\2medium\\2\\3", $gurl );

		$url = trim( $url );
		$gurl = trim( $gurl );
		$option = explode( "|", trim( $align ) );

		$align = $option[0];

		if( $align != "left" and $align != "right" ) $align = '';

		$url = $this->clear_url( urldecode( $url ) );

		$info = $gurl;
		$info = $info."|".$align;

		if( $gurl == "" or $url == "" ) return;

		if( $option[1] != "" ) {

			$alt = htmlspecialchars( strip_tags( stripslashes( $option[1] ) ), ENT_QUOTES, $config['charset'] );

			$alt = str_replace("&amp;amp;","&amp;",$alt);

			$info = $info."|".$alt;
			$caption = "<span class=\"highslide-caption\">" . $alt . "</span>";
			$alt = "alt=\"" . $alt . "\" title=\"" . $alt . "\" ";

		} else {

			$alt = htmlspecialchars( strip_tags( stripslashes( $_POST['title'] ) ), ENT_QUOTES, $config['charset'] );
			$alt = "alt='" . $alt . "' title='" . $alt . "' ";
			$caption = "";

		}

		if( $align == '' ) return "<!--MBegin:{$info}--><a href=\"$gurl\" rel=\"highslide\" class=\"highslide\"><img src=\"$url\" {$alt} /></a>{$caption}<!--MEnd-->";
		else return "<!--MBegin:{$info}--><a href=\"$gurl\" rel=\"highslide\" class=\"highslide\"><img src=\"$url\" style=\"float:{$align};\" {$alt} /></a>{$caption}<!--MEnd-->";

	}

	function build_spoiler( $matches=array() ) {
		global $lang;
		
		if (count($matches) == 3 ) {
			
			$title = $matches[1];
			$title = trim( $title );
	
			$title = str_replace( "&amp;amp;", "&amp;", $title );
			$title = preg_replace( "/javascript:/i", "javascript&#58; ", $title );
			
		} else $title = false;
		
		$id_spoiler = "sp".md5( microtime().uniqid( mt_rand(), TRUE ) );

		if( !$title ) {

			return "<!--dle_spoiler--><div class=\"title_spoiler\"><a href=\"javascript:ShowOrHide('" . $id_spoiler . "')\"><img id=\"image-" . $id_spoiler . "\" style=\"vertical-align: middle;border: none;\" alt=\"\" src=\"{THEME}/dleimages/spoiler-plus.gif\" /></a>&nbsp;<a href=\"javascript:ShowOrHide('" . $id_spoiler . "')\"><!--spoiler_title-->" . $lang['spoiler_title'] . "<!--spoiler_title_end--></a></div><div id=\"" . $id_spoiler . "\" class=\"text_spoiler\" style=\"display:none;\"><!--spoiler_text-->{$matches[1]}<!--spoiler_text_end--></div><!--/dle_spoiler-->";

		} else {

			return "<!--dle_spoiler $title --><div class=\"title_spoiler\"><a href=\"javascript:ShowOrHide('" . $id_spoiler . "')\"><img id=\"image-" . $id_spoiler . "\" style=\"vertical-align: middle;border: none;\" alt=\"\" src=\"{THEME}/dleimages/spoiler-plus.gif\" /></a>&nbsp;<a href=\"javascript:ShowOrHide('" . $id_spoiler . "')\"><!--spoiler_title-->" . $title . "<!--spoiler_title_end--></a></div><div id=\"" . $id_spoiler . "\" class=\"text_spoiler\" style=\"display:none;\"><!--spoiler_text-->{$matches[2]}<!--spoiler_text_end--></div><!--/dle_spoiler-->";

		}

	}
	
	function clear_url($url) {
		global $config;

		$url = strip_tags( trim( stripslashes( $url ) ) );

		$url = str_replace( '\"', '"', $url );
		$url = str_replace( "'", "", $url );
		$url = str_replace( '"', "", $url );

		if( !$this->safe_mode OR $this->wysiwyg ) {

			$url = htmlspecialchars( $url, ENT_QUOTES, $config['charset'] );

		}

		$url = str_ireplace( "document.cookie", "d&#111;cument.cookie", $url );
		$url = str_replace( " ", "%20", $url );
		$url = str_replace( "<", "&#60;", $url );
		$url = str_replace( ">", "&#62;", $url );
		$url = preg_replace( "/javascript:/i", "j&#097;vascript:", $url );
		$url = preg_replace( "/data:/i", "d&#097;ta:", $url );

		return $url;

	}

	function decode_leech( $matches=array() ) {

		$url = 	$matches[1];
		$show = $matches[3];

		if( $this->leech_mode ) return "[url=" . $url . "]" . $show . "[/url]";

		$url = explode( "url=", $url );
		$url = end( $url );
		$url = rawurldecode( $url );
		$url = base64_decode( $url );
		$url = str_replace("&amp;","&", $url );
		
		if( preg_match( "#title=['\"](.+?)['\"]#i", $matches[2], $match ) ) {
			$match[1] = str_replace("&quot;", '"', $match[1]);
			$match[1] = str_replace("&#039;", "'", $match[1]);
			$match[1] = str_replace("&amp;", "&", $match[1]);
			$url = $url."|".$match[1];
		}
		
		return "[leech=" . $url . "]" . $show . "[/leech]";
	}

	function decode_url( $matches=array() ) {

		$show =  $matches[3];
		$url = $matches[1];
		$params = trim($matches[2]);

		if( preg_match( "#title=['\"](.+?)['\"]#i", $params, $match ) ) {
			$match[1] = str_replace("&quot;", '"', $match[1]);
			$match[1] = str_replace("&#039;", "'", $match[1]);
			$match[1] = str_replace("&amp;", "&", $match[1]);
			$url = $url."|".$match[1];
			$params = trim(str_replace($match[0], "", $params));
		}
		
		if (!$params OR $params == 'target="_blank"' OR $params == 'target="_blank" rel="nofollow"' OR $params == 'rel="nofollow"') {

			$url = str_replace("&amp;","&", $url );

			return "[url=" . $url . "]" . $show . "[/url]";

 		} else {

			return $matches[0];

		}
	}

	function decode_thumb( $matches=array() ) {

		if ($matches[1] == "TBegin") $tag="thumb"; else $tag="medium";
		$txt = $matches[2];

		$txt = stripslashes( $txt );
		$txt = explode("|", $txt );
		$url = $txt[0];
		$align = $txt[1];
		$alt = $txt[2];
		$extra = "";

		if( ! $align and ! $alt ) return "[{$tag}]{$url}[/{$tag}]";

		if( $align ) $extra = $align;
		if( $alt ) {

			$alt = str_replace("&#039;", "'", $alt);
			$alt = str_replace("&quot;", '"', $alt);
			$alt = str_replace("&amp;", '&', $alt);
			$extra .= "|" . $alt;

		}

		return "[{$tag}={$extra}]{$url}[/{$tag}]";

	}

	function decode_oldthumb( $matches=array() ) {

		$txt = $matches[1];

		$align = false;
		$alt = false;
		$extra = "";
		$txt = stripslashes( $txt );

		$url = str_replace( "<a href=\"", "", $txt );
		$url = explode( "\"", $url );
		$url = reset( $url );

		if( strpos( $txt, "align=\"" ) !== false ) {

			$align = preg_replace( "#(.+?)align=\"(.+?)\"(.*)#is", "\\2", $txt );
		}

		if( strpos( $txt, "alt=\"" ) !== false ) {

			$alt = preg_replace( "#(.+?)alt=\"(.+?)\"(.*)#is", "\\2", $txt );
		}

		if( $align != "left" and $align != "right" ) $align = false;

		if( ! $align and ! $alt ) return "[thumb]" . $url . "[/thumb]";

		if( $align ) $extra = $align;
		if( $alt ) {
			$alt = str_replace("&#039;", "'", $alt);
			$alt = str_replace("&quot;", '"', $alt);
			$alt = str_replace("&amp;", '&', $alt);
			$extra .= "|" . $alt;

		}

		return "[thumb=" . $extra . "]" . $url . "[/thumb]";

	}

	function decode_img( $matches=array() ) {

		$img = $matches[1];
		$txt = $matches[2];
		$align = false;
		$alt = false;
		$extra = "";

		if( strpos( $txt, "align=\"" ) !== false ) {

			$align = preg_replace( "#(.+?)align=\"(.+?)\"(.*)#is", "\\2", $txt );
		}

		if( strpos( $txt, "alt=\"\"" ) !== false ) {

			$alt = false;

		} elseif( strpos( $txt, "alt=\"" ) !== false ) {

			$alt = preg_replace( "#(.+?)alt=\"(.+?)\"(.*)#is", "\\2", $txt );
		}

		if( $align != "left" and $align != "right" ) $align = false;

		if( ! $align and ! $alt ) return "[img]" . $img . "[/img]";

		if( $align ) $extra = $align;
		if( $alt ) $extra .= "|" . $alt;

		return "[img=" . $extra . "]" . $img . "[/img]";

	}

	function check_home($url) {
		global $config;

		$value = str_replace( "http://", "", $config['http_home_url'] );
		$value = str_replace( "www.", "", $value );
		$value = explode( '/', $value );
		$value = reset( $value );
		if( $value == "" ) return false;

		if( strpos( $url, $value ) === false ) return false;
		else return true;
	}

	function word_filter($source, $encode = true) {
		global $config;

		if( $encode ) {

			$all_words = @file( ENGINE_DIR . '/data/wordfilter.db.php' );
			$find = array ();
			$replace = array ();

			if( ! $all_words or ! count( $all_words ) ) return $source;

			foreach ( $all_words as $word_line ) {
				$word_arr = explode( "|", $word_line );

				if( function_exists( "get_magic_quotes_gpc" ) && get_magic_quotes_gpc() ) {

					$word_arr[1] = addslashes( $word_arr[1] );

				}

				if( $word_arr[4] ) {

					$register ="";

				} else $register ="i";

				if ( $config['charset'] == "utf-8" ) $register .= "u";

				$allow_find = true;

				if ( $word_arr[5] == 1 AND $this->safe_mode ) $allow_find = false;
				if ( $word_arr[5] == 2 AND !$this->safe_mode ) $allow_find = false;

				if ( $allow_find ) {

					if( $word_arr[3] ) {

						$find_text = "#(^|\b|\s|\<br \/\>)" . preg_quote( $word_arr[1], "#" ) . "(\b|\s|!|\?|\.|,|$)#".$register;

						if( $word_arr[2] == "" ) $replace_text = "\\1";
						else $replace_text = "\\1<!--filter:" . $word_arr[1] . "-->" . $word_arr[2] . "<!--/filter-->\\2";

					} else {

						$find_text = "#(" . preg_quote( $word_arr[1], "#" ) . ")#".$register;

						if( $word_arr[2] == "" ) $replace_text = "";
						else $replace_text = "<!--filter:" . $word_arr[1] . "-->" . $word_arr[2] . "<!--/filter-->";

					}

					if ( $word_arr[6] ) {

						if ( preg_match($find_text, $source) ) {

							$this->not_allowed_text = true;
							return $source;

						}

					} else {

						$find[] = $find_text;
						$replace[] = $replace_text;
					}

				}

			}

			if( !count( $find ) ) return $source;

			$source = preg_split( '((>)|(<))', $source, - 1, PREG_SPLIT_DELIM_CAPTURE );
			$count = count( $source );

			for($i = 0; $i < $count; $i ++) {
				if( $source[$i] == "<" or $source[$i] == "[" ) {
					$i ++;
					continue;
				}

				if( $source[$i] != "" ) $source[$i] = preg_replace( $find, $replace, $source[$i] );
			}

			$source = join( "", $source );

		} else {

			$source = preg_replace( "#<!--filter:(.+?)-->(.+?)<!--/filter-->#", "\\1", $source );

		}

		return $source;
	}

}
?>