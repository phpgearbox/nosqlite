<?php
////////////////////////////////////////////////////////////////////////////////
//            _____                        __     _____   __        __          
//           /  _  \   ______ ______ _____/  |_  /     \ |__| ____ |__|         
//          /  /_\  \ /  ___//  ___// __ \   __\/  \ /  \|  |/    \|  |         
//         /    |    \\___ \ \___ \\  ___/|  | /    Y    \  |   |  \  |         
//         \____|__  /____  >____  >\___  >__| \____|__  /__|___|  /__|         
//                 \/     \/     \/     \/             \/        \/             
// -----------------------------------------------------------------------------
//          Designed and Developed by Brad Jones <brad @="bjc.id.au" />         
// -----------------------------------------------------------------------------
////////////////////////////////////////////////////////////////////////////////

// Do we have a request
if (isset($_GET['request']))
{
	// This is what we will output
	$output = '';
	
	// Define our base directory
	$base_dir = realpath(dirname(__FILE__));

	// Parse the request
	$info = pathinfo($_GET['request']);
	$type = $info['extension'];
	$files = explode(',', str_replace('.min', '', $info['filename']));
	$time = array_pop($files);
	$classname = $type.'min';
	
	// Create the group name
	$group = '';
	foreach ($files as $file) $group .= $file.',';
	$group = substr($group, 0, -1);
	
	// Create some file names
	$group_hash = $base_dir.'/'.$type.'/'.$group.'.hash';
	$group_min = $base_dir.'/'.$type.'/'.$info['filename'].'.'.$type;
	$group_gz = $group_min.'.gz';
	
	/*
	 * Check to see if the group file already exists
	 * I have noticed that from time to time nginx takes a few requests
	 * before it recongnises that the cached files exist, my guess at this
	 * point in file locking. ie: the php process still has the file pointer
	 * open. Anyway if the cached files don't exist lets create them.
	 */
	if (!file_exists($group_min))
	{
		// Clean up any old builds
		foreach(scandir($base_dir.'/'.$type) as $file)
		{
			if (strpos($file, '.min'))
			{
				unlink($base_dir.'/'.$type.'/'.$file);
			}
		}
		
		// This will contain a list of hashes for each file we minify.
		$hashes = array();
		
		// We might need these
		$less = new lessc(); $less->setImportDir([$base_dir.'/css']);
		$scssc = new scssc(); $scssc->setImportPaths([$base_dir.'/css']);
		
		// Loop through the files that make up this group
		foreach ($files as $file)
		{
			// Create the full asset file name
			$assetfilename = $base_dir.'/'.$type.'/'.$file.'.'.$type;
			$assetfilename_less = $base_dir.'/'.$type.'/'.$file.'.less';
			$assetfilename_scss = $base_dir.'/'.$type.'/'.$file.'.scss';
			
			// Does it exist
			if (file_exists($assetfilename))
			{
				// Read the file
				$data = file_get_contents($assetfilename);
				
				// Grab the hash
				$hashes[$assetfilename] = md5($data);
				
				// Minify it
				if ($time != 'debug') $output .= $classname::mini($data);
				else $output .= $data;
			}
			elseif (file_exists($assetfilename_less))
			{
				// Read the file
				$data = file_get_contents($assetfilename_less);
				
				// Grab the hash
				$hashes[$assetfilename_less] = md5($data);
				
				// Compile the css
				$data = $less->compile($data);
				
				// Minify it
				if ($time != 'debug') $output .= $classname::mini($data);
				else $output .= $data;
			}
			elseif (file_exists($assetfilename_scss))
			{
				// Read the file
				$data = file_get_contents($assetfilename_scss);
				
				// Grab the hash
				$hashes[$assetfilename_scss] = md5($data);
				
				// Compile the css
				$data = $scssc->compile($data);
				
				// Minify it
				if ($time != 'debug') $output .= $classname::mini($data);
				else $output .= $data;
			}
		}
		
		if ($time != 'debug')
		{
			// Compress the minfied data
			$output_gz = gzencode($output);
			
			// Cache the minfied version
			file_put_contents($group_min, $output);
			
			// Cache a gzipped version as well
			file_put_contents($group_gz, $output_gz);
			
			// Create a hash file so we can easily detect
			// when the cache is no longer valid
			file_put_contents($group_hash, json_encode($hashes));
			
			// Make sure all files have the same time
			touch($group_hash, $time);
			touch($group_min, $time);
			touch($group_gz, $time);
		}
	}
	else
	{
		// Just read in what we already have
		$output = file_get_contents($group_min);
		$output_gz = file_get_contents($group_gz);
	}
	
	// What content type is it?
	if ($type == 'css') header('Content-type: text/css;');
	if ($type == 'js') header('Content-type: text/javascript;');
	
	// Does the browser support gzip?
	if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false && $time != 'debug')
	{
		// We may as well return the gzipped data we just created.
		header('Vary: Accept-Encoding');
		header('Content-Encoding: gzip');
		$content = $output_gz;
	}
	else
	{
		$content = $output;
	}
	
	// How long is the content
	header('Content-Length: '.strlen($content));
	
	// Output the minfied asset
	echo $content;
}

/**
 * =============================================================================
 * AssetMini - View Helper Class
 * =============================================================================
 */
class AssetMini
{
	public static $debug = false;
	
	private static function general($type, $files, $link_builder, $link_builder_debug)
	{
		if (self::$debug)
		{
			// Just output the individual files
			foreach ($files as $file) echo $link_builder_debug($file);
		}
		else
		{
			// Define our base directory
			$base_dir = realpath(dirname(__FILE__));
			
			// Create the group name
			$group = '';
			foreach ($files as $file) $group .= $file.',';
			$group = substr($group, 0, -1);
			
			// Create the hash file name
			$hashfile = $base_dir.'/'.$type.'/'.$group.'.hash';
			
			// Has the group already been built?
			if (file_exists($hashfile))
			{
				// Do the hashes match the current files
				$hashes = json_decode(file_get_contents($hashfile));
				foreach ($hashes as $src => $hash)
				{
					if (md5(file_get_contents($src)) != $hash)
					{
						// Something changed lets invalidate the
						// client side and server side cache.
						$time = time(); break;
					}
				}
				
				// Nothing changed so lets use the time from the current cache.
				if (!isset($time)) $time = filemtime($hashfile);
			}
			else
			{
				// The group hasn't been built yet.
				$time = time();
			}
			
			// Output the minified link for the group
			echo $link_builder($group, $time);
		}
	}
	
	public static function css($files)
	{
		self::general
		(
			'css',
			$files,
			function($group, $time)
			{
				return '<link rel="stylesheet" href="'.$group.','.$time.'.min.css" />';
			},
			function($file)
			{
				$base_dir = realpath(dirname(__FILE__));
				$assetfilename_less = $base_dir.'/css/'.$file.'.less';
				$assetfilename_scss = $base_dir.'/css/'.$file.'.scss';
				if (file_exists($assetfilename_less) || file_exists($assetfilename_scss))
				{
					return '<link rel="stylesheet" href="'.$file.',debug.min.css?stopcache='.time().'" />';
				}
				
				return '<link rel="stylesheet" href="/assets/css/'.$file.'.css?stopcache='.time().'" />';
			}
		);
	}
	
	public static function js($files)
	{
		self::general
		(
			'js',
			$files,
			function($group, $time)
			{
				return '<script src="'.$group.','.$time.'.min.js"></script>';
			},
			function($file)
			{
				return '<script src="/assets/js/'.$file.'.js?stopcache='.time().'"></script>';
			}
		);
	}
}

/**
 * =============================================================================
 * CSS and JS Minification Classes Below
 * =============================================================================
 */

/**
 * cssmin.php rev 91c5ea5
 * Author: Tubal Martin - http://blog.margenn.com/
 * Repo: https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port
 *
 * This is a PHP port of the Javascript port of the CSS minification tool
 * distributed with YUICompressor, itself a port of the cssmin utility by
 * Isaac Schlueter - http://foohack.com/
 * Permission is hereby granted to use the PHP version under the same
 * conditions as the YUICompressor.
 * 
 * YUI Compressor
 * http://developer.yahoo.com/yui/compressor/
 * Author: Julien Lecomte - http://www.julienlecomte.net/
 * Copyright (c) 2011 Yahoo! Inc. All rights reserved.
 * The copyrights embodied in the content of this file are licensed
 * by Yahoo! Inc. under the BSD (revised) open source license.
 */

class cssmin
{
	const NL = '___YUICSSMIN_PRESERVED_NL___';
	const TOKEN = '___YUICSSMIN_PRESERVED_TOKEN_';
	const COMMENT = '___YUICSSMIN_PRESERVE_CANDIDATE_COMMENT_';
	const CLASSCOLON = '___YUICSSMIN_PSEUDOCLASSCOLON___';
	
	private $comments;
	private $preserved_tokens;
	private $memory_limit;
	private $max_execution_time;
	private $pcre_backtrack_limit;
	private $pcre_recursion_limit;
	private $raise_php_limits;
	
	public static function mini($css)
	{
		$compressor = new cssmin();
		return $compressor->run($css);
	}
	
	/**
	 * @param bool|int $raise_php_limits
	 * If true, PHP settings will be raised if needed
	 */
	public function __construct($raise_php_limits = TRUE)
	{
		// Set suggested PHP limits
		$this->memory_limit = 128 * 1048576; // 128MB in bytes
		$this->max_execution_time = 60; // 1 min
		$this->pcre_backtrack_limit = 1000 * 1000;
		$this->pcre_recursion_limit =  500 * 1000;
		
		$this->raise_php_limits = (bool) $raise_php_limits;
	}
	
	/**
	 * Minify a string of CSS
	 * @param string $css
	 * @param int|bool $linebreak_pos
	 * @return string
	 */
	public function run($css = '', $linebreak_pos = FALSE)
	{
		if (empty($css)) {
			return '';
		}
		
		if ($this->raise_php_limits) {
			$this->do_raise_php_limits();
		}
		
		$this->comments = array();
		$this->preserved_tokens = array();
		
		$start_index = 0;
		$length = strlen($css);
		
		$css = $this->extract_data_urls($css);
		
		// collect all comment blocks...
		while (($start_index = $this->index_of($css, '/*', $start_index)) >= 0) {
			$end_index = $this->index_of($css, '*/', $start_index + 2);
			if ($end_index < 0) {
				$end_index = $length;
			}
			$comment_found = $this->str_slice($css, $start_index + 2, $end_index);
			$this->comments[] = $comment_found;
			$comment_preserve_string = self::COMMENT . (count($this->comments) - 1) . '___';
			$css = $this->str_slice($css, 0, $start_index + 2) . $comment_preserve_string . $this->str_slice($css, $end_index);
			// Set correct start_index: Fixes issue #2528130
			$start_index = $end_index + 2 + strlen($comment_preserve_string) - strlen($comment_found);
		}
		
		// preserve strings so their content doesn't get accidentally minified
		$css = preg_replace_callback('/(?:"(?:[^\\\\"]|\\\\.|\\\\)*")|'."(?:'(?:[^\\\\']|\\\\.|\\\\)*')/S", array($this, 'replace_string'), $css);
		
		// Let's divide css code in chunks of 25.000 chars aprox.
		// Reason: PHP's PCRE functions like preg_replace have a "backtrack limit"
		// of 100.000 chars by default (php < 5.3.7) so if we're dealing with really
		// long strings and a (sub)pattern matches a number of chars greater than
		// the backtrack limit number (i.e. /(.*)/s) PCRE functions may fail silently
		// returning NULL and $css would be empty.
		$charset = '';
		$charset_regexp = '/@charset [^;]+;/i';
		$css_chunks = array();
		$css_chunk_length = 25000; // aprox size, not exact
		$start_index = 0;
		$i = $css_chunk_length; // save initial iterations
		$l = strlen($css);
		
		
		// if the number of characters is 25000 or less, do not chunk
		if ($l <= $css_chunk_length) {
			$css_chunks[] = $css;
		} else {
			// chunk css code securely
			while ($i < $l) {
				$i += 50; // save iterations. 500 checks for a closing curly brace }
				if ($l - $start_index <= $css_chunk_length || $i >= $l) {
					$css_chunks[] = $this->str_slice($css, $start_index);
					break;
				}
				if ($css[$i - 1] === '}' && $i - $start_index > $css_chunk_length) {
					// If there are two ending curly braces }} separated or not by spaces,
					// join them in the same chunk (i.e. @media blocks)
					$next_chunk = substr($css, $i);
					if (preg_match('/^\s*\}/', $next_chunk)) {
						$i = $i + $this->index_of($next_chunk, '}') + 1;
					}
					
					$css_chunks[] = $this->str_slice($css, $start_index, $i);
					$start_index = $i;
				}
			}
		}
		
		// Minify each chunk
		for ($i = 0, $n = count($css_chunks); $i < $n; $i++) {
			$css_chunks[$i] = $this->minify($css_chunks[$i], $linebreak_pos);
			// Keep the first @charset at-rule found
			if (empty($charset) && preg_match($charset_regexp, $css_chunks[$i], $matches)) {
				$charset = $matches[0];
			}
			// Delete all @charset at-rules
			$css_chunks[$i] = preg_replace($charset_regexp, '', $css_chunks[$i]);
		}
		
		// Update the first chunk and push the charset to the top of the file.
		$css_chunks[0] = $charset . $css_chunks[0];
		
		return implode('', $css_chunks);
	}
	
	/**
	 * Sets the memory limit for this script
	 * @param int|string $limit
	 */
	public function set_memory_limit($limit)
	{
		$this->memory_limit = $this->normalize_int($limit);
	}
	
	/**
	 * Sets the maximum execution time for this script
	 * @param int|string $seconds
	 */
	public function set_max_execution_time($seconds)
	{
		$this->max_execution_time = (int) $seconds;
	}
	
	/**
	 * Sets the PCRE backtrack limit for this script
	 * @param int $limit
	 */
	public function set_pcre_backtrack_limit($limit)
	{
		$this->pcre_backtrack_limit = (int) $limit;
	}
	
	/**
	 * Sets the PCRE recursion limit for this script
	 * @param int $limit
	 */
	public function set_pcre_recursion_limit($limit)
	{
		$this->pcre_recursion_limit = (int) $limit;
	}
	
	/**
	 * Try to configure PHP to use at least the suggested minimum settings
	 */
	private function do_raise_php_limits()
	{
		$php_limits = array(
		'memory_limit' => $this->memory_limit,
		'max_execution_time' => $this->max_execution_time,
		'pcre.backtrack_limit' => $this->pcre_backtrack_limit,
		'pcre.recursion_limit' =>  $this->pcre_recursion_limit
		);
		
		// If current settings are higher respect them.
		foreach ($php_limits as $name => $suggested) {
			$current = $this->normalize_int(ini_get($name));
			// memory_limit exception: allow -1 for "no memory limit".
			if ($current > -1 && ($suggested == -1 || $current < $suggested)) {
				ini_set($name, $suggested);
			}
		}
	}
	
	/**
	 * Does bulk of the minification
	 * @param string $css
	 * @param int|bool $linebreak_pos
	 * @return string
	 */
	private function minify($css, $linebreak_pos)
	{
		// strings are safe, now wrestle the comments
		for ($i = 0, $max = count($this->comments); $i < $max; $i++) {
			
			$token = $this->comments[$i];
			$placeholder = '/' . self::COMMENT . $i . '___/';
			
			// ! in the first position of the comment means preserve
			// so push to the preserved tokens keeping the !
			if (substr($token, 0, 1) === '!') {
				$this->preserved_tokens[] = $token;
				$token_tring = self::TOKEN . (count($this->preserved_tokens) - 1) . '___';
				$css = preg_replace($placeholder, $token_tring, $css, 1);
				// Preserve new lines for /*! important comments
				$css = preg_replace('/\s*[\n\r\f]+\s*(\/\*'. $token_tring .')/S', self::NL.'$1', $css);
				$css = preg_replace('/('. $token_tring .'\*\/)\s*[\n\r\f]+\s*/S', '$1'.self::NL, $css);
				continue;
			}
			
			// \ in the last position looks like hack for Mac/IE5
			// shorten that to /*\*/ and the next one to /**/
			if (substr($token, (strlen($token) - 1), 1) === '\\') {
				$this->preserved_tokens[] = '\\';
				$css = preg_replace($placeholder,  self::TOKEN . (count($this->preserved_tokens) - 1) . '___', $css, 1);
				$i = $i + 1; // attn: advancing the loop
				$this->preserved_tokens[] = '';
				$css = preg_replace('/' . self::COMMENT . $i . '___/',  self::TOKEN . (count($this->preserved_tokens) - 1) . '___', $css, 1);
				continue;
			}
			
			// keep empty comments after child selectors (IE7 hack)
			// e.g. html >/**/ body
			if (strlen($token) === 0) {
				$start_index = $this->index_of($css, $this->str_slice($placeholder, 1, -1));
				if ($start_index > 2) {
					if (substr($css, $start_index - 3, 1) === '>') {
						$this->preserved_tokens[] = '';
						$css = preg_replace($placeholder,  self::TOKEN . (count($this->preserved_tokens) - 1) . '___', $css, 1);
					}
				}
			}
			
			// in all other cases kill the comment
			$css = preg_replace('/\/\*' . $this->str_slice($placeholder, 1, -1) . '\*\//', '', $css, 1);
		}
		
		
		// Normalize all whitespace strings to single spaces. Easier to work with that way.
		$css = preg_replace('/\s+/', ' ', $css);
		
		// Shorten & preserve calculations calc(...) since spaces are important
		$css = preg_replace_callback('/calc(\((?:[^\(\)]+|(?1))*\))/i', array($this, 'replace_calc'), $css);
		
		// Replace positive sign from numbers preceded by : or a white-space before the leading space is removed
		// +1.2em to 1.2em, +.8px to .8px, +2% to 2%
		$css = preg_replace('/((?<!\\\\)\:|\s)\+(\.?\d+)/S', '$1$2', $css);
		
		// Remove leading zeros from integer and float numbers preceded by : or a white-space
		// 000.6 to .6, -0.8 to -.8, 0050 to 50, -01.05 to -1.05
		$css = preg_replace('/((?<!\\\\)\:|\s)(\-?)0+(\.?\d+)/S', '$1$2$3', $css);
		
		// Remove trailing zeros from float numbers preceded by : or a white-space
		// -6.0100em to -6.01em, .0100 to .01, 1.200px to 1.2px
		$css = preg_replace('/((?<!\\\\)\:|\s)(\-?)(\d?\.\d+?)0+([^\d])/S', '$1$2$3$4', $css);
		
		// Remove trailing .0 -> -9.0 to -9
		$css = preg_replace('/((?<!\\\\)\:|\s)(\-?\d+)\.0([^\d])/S', '$1$2$3', $css);
		
		// Replace 0 length numbers with 0
		$css = preg_replace('/((?<!\\\\)\:|\s)\-?\.?0+([^\d])/S', '${1}0$2', $css);
		
		// Remove the spaces before the things that should not have spaces before them.
		// But, be careful not to turn "p :link {...}" into "p:link{...}"
		// Swap out any pseudo-class colons with the token, and then swap back.
		$css = preg_replace_callback('/(?:^|\})(?:(?:[^\{\:])+\:)+(?:[^\{]*\{)/', array($this, 'replace_colon'), $css);
		$css = preg_replace('/\s+([\!\{\}\;\:\>\+\(\)\]\~\=,])/', '$1', $css);
		$css = preg_replace('/' . self::CLASSCOLON . '/', ':', $css);
		
		// retain space for special IE6 cases
		$css = preg_replace('/\:first\-(line|letter)(\{|,)/i', ':first-$1 $2', $css);
		
		// no space after the end of a preserved comment
		$css = preg_replace('/\*\/ /', '*/', $css);
		
		// Put the space back in some cases, to support stuff like
		// @media screen and (-webkit-min-device-pixel-ratio:0){
		$css = preg_replace('/\band\(/i', 'and (', $css);
		
		// Remove the spaces after the things that should not have spaces after them.
		$css = preg_replace('/([\!\{\}\:;\>\+\(\[\~\=,])\s+/S', '$1', $css);
		
		// remove unnecessary semicolons
		$css = preg_replace('/;+\}/', '}', $css);
		
		// Fix for issue: #2528146
		// Restore semicolon if the last property is prefixed with a `*` (lte IE7 hack)
		// to avoid issues on Symbian S60 3.x browsers.
		$css = preg_replace('/(\*[a-z0-9\-]+\s*\:[^;\}]+)(\})/', '$1;$2', $css);
		
		// Replace 0 length units 0(px,em,%) with 0.
		$css = preg_replace('/((?<!\\\\)\:|\s)\-?0(?:em|ex|ch|rem|vw|vh|vm|vmin|cm|mm|in|px|pt|pc|%)/iS', '${1}0', $css);
		
		// Replace 0 0; or 0 0 0; or 0 0 0 0; with 0.
		$css = preg_replace('/\:0(?: 0){1,3}(;|\})/', ':0$1', $css);
		
		// Fix for issue: #2528142
		// Replace text-shadow:0; with text-shadow:0 0 0;
		$css = preg_replace('/(text-shadow\:0)(;|\})/ie', "strtolower('$1 0 0$2')", $css);
		
		// Replace background-position:0; with background-position:0 0;
		// same for transform-origin
		$css = preg_replace('/(background\-position|(?:webkit|moz|o|ms|)\-?transform\-origin)\:0(;|\})/ieS', "strtolower('$1:0 0$2')", $css);
		
		// Shorten colors from rgb(51,102,153) to #336699, rgb(100%,0%,0%) to #ff0000 (sRGB color space)
		// Shorten colors from hsl(0, 100%, 50%) to #ff0000 (sRGB color space)
		// This makes it more likely that it'll get further compressed in the next step.
		$css = preg_replace_callback('/rgb\s*\(\s*([0-9,\s\-\.\%]+)\s*\)(.{1})/i', array($this, 'rgb_to_hex'), $css);
		$css = preg_replace_callback('/hsl\s*\(\s*([0-9,\s\-\.\%]+)\s*\)(.{1})/i', array($this, 'hsl_to_hex'), $css);
		
		// Shorten colors from #AABBCC to #ABC or short color name.
		$css = $this->compress_hex_colors($css);
		
		// border: none to border:0, outline: none to outline:0
		$css = preg_replace('/(border\-?(?:top|right|bottom|left|)|outline)\:none(;|\})/ieS', "strtolower('$1:0$2')", $css);
		
		// shorter opacity IE filter
		$css = preg_replace('/progid\:DXImageTransform\.Microsoft\.Alpha\(Opacity\=/i', 'alpha(opacity=', $css);
		
		// Remove empty rules.
		$css = preg_replace('/[^\};\{\/]+\{\}/S', '', $css);
		
		// Some source control tools don't like it when files containing lines longer
		// than, say 8000 characters, are checked in. The linebreak option is used in
		// that case to split long lines after a specific column.
		if ($linebreak_pos !== FALSE && (int) $linebreak_pos >= 0) {
			$linebreak_pos = (int) $linebreak_pos;
			$start_index = $i = 0;
			while ($i < strlen($css)) {
				$i++;
				if ($css[$i - 1] === '}' && $i - $start_index > $linebreak_pos) {
					$css = $this->str_slice($css, 0, $i) . "\n" . $this->str_slice($css, $i);
					$start_index = $i;
				}
			}
		}
		
		// Replace multiple semi-colons in a row by a single one
		// See SF bug #1980989
		$css = preg_replace('/;;+/', ';', $css);
		
		// Restore new lines for /*! important comments
		$css = preg_replace('/'. self::NL .'/', "\n", $css);
		
		// restore preserved comments and strings
		for ($i = 0, $max = count($this->preserved_tokens); $i < $max; $i++) {
			$css = preg_replace('/' . self::TOKEN . $i . '___/', $this->preserved_tokens[$i], $css, 1);
		}
		
		// Trim the final string (for any leading or trailing white spaces)
		return trim($css);
	}
	
	/**
	 * Utility method to replace all data urls with tokens before we start
	 * compressing, to avoid performance issues running some of the subsequent
	 * regexes against large strings chunks.
		*
	 * @param string $css
	 * @return string
	 */
	private function extract_data_urls($css)
	{
		// Leave data urls alone to increase parse performance.
		$max_index = strlen($css) - 1;
		$append_index = $index = $last_index = $offset = 0;
		$sb = array();
		$pattern = '/url\(\s*(["\']?)data\:/i';
		
		// Since we need to account for non-base64 data urls, we need to handle
		// ' and ) being part of the data string. Hence switching to indexOf,
		// to determine whether or not we have matching string terminators and
		// handling sb appends directly, instead of using matcher.append* methods.
		
		while (preg_match($pattern, $css, $m, 0, $offset)) {
			$index = $this->index_of($css, $m[0], $offset);
			$last_index = $index + strlen($m[0]);
			$start_index = $index + 4; // "url(".length()
			$end_index = $last_index - 1;
			$terminator = $m[1]; // ', " or empty (not quoted)
			$found_terminator = FALSE;
			
			if (strlen($terminator) === 0) {
				$terminator = ')';
			}
			
			while ($found_terminator === FALSE && $end_index+1 <= $max_index) {
				$end_index = $this->index_of($css, $terminator, $end_index + 1);
				
				// endIndex == 0 doesn't really apply here
				if ($end_index > 0 && substr($css, $end_index - 1, 1) !== '\\') {
					$found_terminator = TRUE;
					if (')' != $terminator) {
						$end_index = $this->index_of($css, ')', $end_index);
					}
				}
			}
			
			// Enough searching, start moving stuff over to the buffer
			$sb[] = $this->substring($css, $append_index, $index);
			
			if ($found_terminator) {
				$token = $this->substring($css, $start_index, $end_index);
				$token = preg_replace('/\s+/', '', $token);
				$this->preserved_tokens[] = $token;
				
				$preserver = 'url(' . self::TOKEN . (count($this->preserved_tokens) - 1) . '___)';
				$sb[] = $preserver;
				
				$append_index = $end_index + 1;
			} else {
				// No end terminator found, re-add the whole match. Should we throw/warn here?
				$sb[] = $this->substring($css, $index, $last_index);
				$append_index = $last_index;
			}
			
			$offset = $last_index;
		}
		
		$sb[] = $this->substring($css, $append_index);
		
		return implode('', $sb);
	}
	
	/**
	 * Utility method to compress hex color values of the form #AABBCC to #ABC or short color name.
		*
	 * DOES NOT compress CSS ID selectors which match the above pattern (which would break things).
	 * e.g. #AddressForm { ... }
		*
	 * DOES NOT compress IE filters, which have hex color values (which would break things).
	 * e.g. filter: chroma(color="#FFFFFF");
		*
	 * DOES NOT compress invalid hex values.
	 * e.g. background-color: #aabbccdd
		*
	 * @param string $css
	 * @return string
	 */
	private function compress_hex_colors($css)
	{
		// Look for hex colors inside { ... } (to avoid IDs) and which don't have a =, or a " in front of them (to avoid filters)
		$pattern = '/(\=\s*?["\']?)?#([0-9a-f])([0-9a-f])([0-9a-f])([0-9a-f])([0-9a-f])([0-9a-f])(\}|[^0-9a-f{][^{]*?\})/iS';
		$_index = $index = $last_index = $offset = 0;
		$sb = array();
		// See: http://ajaxmin.codeplex.com/wikipage?title=CSS%20Colors
		$short_safe = array(
		'#808080' => 'gray',
		'#008000' => 'green',
		'#800000' => 'maroon',
		'#000080' => 'navy',
		'#808000' => 'olive',
		'#800080' => 'purple',
		'#c0c0c0' => 'silver',
		'#008080' => 'teal',
		'#f00' => 'red'
		);
		
		while (preg_match($pattern, $css, $m, 0, $offset)) {
			$index = $this->index_of($css, $m[0], $offset);
			$last_index = $index + strlen($m[0]);
			$is_filter = (bool) $m[1];
			
			$sb[] = $this->substring($css, $_index, $index);
			
			if ($is_filter) {
				// Restore, maintain case, otherwise filter will break
				$sb[] = $m[1] . '#' . $m[2] . $m[3] . $m[4] . $m[5] . $m[6] . $m[7];
			} else {
				if (strtolower($m[2]) == strtolower($m[3]) &&
				strtolower($m[4]) == strtolower($m[5]) &&
				strtolower($m[6]) == strtolower($m[7])) {
					// Compress.
					$hex = '#' . strtolower($m[3] . $m[5] . $m[7]);
				} else {
					// Non compressible color, restore but lower case.
					$hex = '#' . strtolower($m[2] . $m[3] . $m[4] . $m[5] . $m[6] . $m[7]);
				}
				// replace Hex colors to short safe color names
				$sb[] = array_key_exists($hex, $short_safe) ? $short_safe[$hex] : $hex;
			}
			
			$_index = $offset = $last_index - strlen($m[8]);
		}
		
		$sb[] = $this->substring($css, $_index);
		
		return implode('', $sb);
	}
	
	/* CALLBACKS
	 * ---------------------------------------------------------------------------------------------
	 */
	
	private function replace_string($matches)
	{
		$match = $matches[0];
		$quote = substr($match, 0, 1);
		// Must use addcslashes in PHP to avoid parsing of backslashes
		$match = addcslashes($this->str_slice($match, 1, -1), '\\');
		
		// maybe the string contains a comment-like substring?
		// one, maybe more? put'em back then
		if (($pos = $this->index_of($match, self::COMMENT)) >= 0) {
			for ($i = 0, $max = count($this->comments); $i < $max; $i++) {
				$match = preg_replace('/' . self::COMMENT . $i . '___/', $this->comments[$i], $match, 1);
			}
		}
		
		// minify alpha opacity in filter strings
		$match = preg_replace('/progid\:DXImageTransform\.Microsoft\.Alpha\(Opacity\=/i', 'alpha(opacity=', $match);
		
		$this->preserved_tokens[] = $match;
		return $quote . self::TOKEN . (count($this->preserved_tokens) - 1) . '___' . $quote;
	}
	
	private function replace_colon($matches)
	{
		return preg_replace('/\:/', self::CLASSCOLON, $matches[0]);
	}
	
	private function replace_calc($matches)
	{
		$this->preserved_tokens[] = preg_replace('/\s?([\*\/\(\),])\s?/', '$1', $matches[0]);
		return self::TOKEN . (count($this->preserved_tokens) - 1) . '___';
	}
	
	private function rgb_to_hex($matches)
	{
		// Support for percentage values rgb(100%, 0%, 45%);
		if ($this->index_of($matches[1], '%') >= 0){
			$rgbcolors = explode(',', str_replace('%', '', $matches[1]));
			for ($i = 0; $i < count($rgbcolors); $i++) {
				$rgbcolors[$i] = $this->round_number(floatval($rgbcolors[$i]) * 2.55);
			}
		} else {
			$rgbcolors = explode(',', $matches[1]);
		}
		
		// Values outside the sRGB color space should be clipped (0-255)
		for ($i = 0; $i < count($rgbcolors); $i++) {
			$rgbcolors[$i] = $this->clamp_number(intval($rgbcolors[$i], 10), 0, 255);
			$rgbcolors[$i] = sprintf("%02x", $rgbcolors[$i]);
		}
		
		// Fix for issue #2528093
		if (!preg_match('/[\s\,\);\}]/', $matches[2])){
			$matches[2] = ' ' . $matches[2];
		}
		
		return '#' . implode('', $rgbcolors) . $matches[2];
	}
	
	private function hsl_to_hex($matches)
	{
		$values = explode(',', str_replace('%', '', $matches[1]));
		$h = floatval($values[0]);
		$s = floatval($values[1]);
		$l = floatval($values[2]);
		
		// Wrap and clamp, then fraction!
		$h = ((($h % 360) + 360) % 360) / 360;
		$s = $this->clamp_number($s, 0, 100) / 100;
		$l = $this->clamp_number($l, 0, 100) / 100;
		
		if ($s == 0) {
			$r = $g = $b = $this->round_number(255 * $l);
		} else {
			$v2 = $l < 0.5 ? $l * (1 + $s) : ($l + $s) - ($s * $l);
			$v1 = (2 * $l) - $v2;
			$r = $this->round_number(255 * $this->hue_to_rgb($v1, $v2, $h + (1/3)));
			$g = $this->round_number(255 * $this->hue_to_rgb($v1, $v2, $h));
			$b = $this->round_number(255 * $this->hue_to_rgb($v1, $v2, $h - (1/3)));
		}
		
		return $this->rgb_to_hex(array('', $r.','.$g.','.$b, $matches[2]));
	}
	
	/* HELPERS
	 * ---------------------------------------------------------------------------------------------
	 */
	
	private function hue_to_rgb($v1, $v2, $vh)
	{
		$vh = $vh < 0 ? $vh + 1 : ($vh > 1 ? $vh - 1 : $vh);
		if ($vh * 6 < 1) return $v1 + ($v2 - $v1) * 6 * $vh;
		if ($vh * 2 < 1) return $v2;
		if ($vh * 3 < 2) return $v1 + ($v2 - $v1) * ((2/3) - $vh) * 6;
		return $v1;
	}
	
	private function round_number($n)
	{
		return intval(floor(floatval($n) + 0.5), 10);
	}
	
	private function clamp_number($n, $min, $max)
	{
		return min(max($n, $min), $max);
	}
	
	/**
	 * PHP port of Javascript's "indexOf" function for strings only
	 * Author: Tubal Martin http://blog.margenn.com
		*
	 * @param string $haystack
	 * @param string $needle
	 * @param int    $offset index (optional)
	 * @return int
	 */
	private function index_of($haystack, $needle, $offset = 0)
	{
		$index = strpos($haystack, $needle, $offset);
		
		return ($index !== FALSE) ? $index : -1;
	}
	
	/**
	 * PHP port of Javascript's "substring" function
	 * Author: Tubal Martin http://blog.margenn.com
	 * Tests: http://margenn.com/tubal/substring/
		*
	 * @param string   $str
	 * @param int      $from index
	 * @param int|bool $to index (optional)
	 * @return string
	 */
	private function substring($str, $from = 0, $to = FALSE)
	{
		if ($to !== FALSE) {
			if ($from == $to || ($from <= 0 && $to < 0)) {
				return '';
			}
			
			if ($from > $to) {
				$from_copy = $from;
				$from = $to;
				$to = $from_copy;
			}
		}
		
		if ($from < 0) {
			$from = 0;
		}
		
		$substring = ($to === FALSE) ? substr($str, $from) : substr($str, $from, $to - $from);
		return ($substring === FALSE) ? '' : $substring;
	}
	
	/**
	 * PHP port of Javascript's "slice" function for strings only
	 * Author: Tubal Martin http://blog.margenn.com
	 * Tests: http://margenn.com/tubal/str_slice/
		*
	 * @param string   $str
	 * @param int      $start index
	 * @param int|bool $end index (optional)
	 * @return string
	 */
	private function str_slice($str, $start = 0, $end = FALSE)
	{
		if ($end !== FALSE && ($start < 0 || $end <= 0)) {
			$max = strlen($str);
			
			if ($start < 0) {
				if (($start = $max + $start) < 0) {
					return '';
				}
			}
			
			if ($end < 0) {
				if (($end = $max + $end) < 0) {
					return '';
				}
			}
			
			if ($end <= $start) {
				return '';
			}
		}
		
		$slice = ($end === FALSE) ? substr($str, $start) : substr($str, $start, $end - $start);
		return ($slice === FALSE) ? '' : $slice;
	}
	
	/**
	 * Convert strings like "64M" or "30" to int values
	 * @param mixed $size
	 * @return int
	 */
	private function normalize_int($size)
	{
		if (is_string($size)) {
			switch (substr($size, -1)) {
				case 'M': case 'm': return $size * 1048576;
				case 'K': case 'k': return $size * 1024;
				case 'G': case 'g': return $size * 1073741824;
			}
		}
		
		return (int) $size;
	}
}

/**
 * JSMin.php - modified PHP implementation of Douglas Crockford's JSMin.
 *
 * <code>
 * $minifiedJs = JSMin::minify($js);
 * </code>
 *
 * This is a modified port of jsmin.c. Improvements:
 * 
 * Does not choke on some regexp literals containing quote characters. E.g. /'/
 * 
 * Spaces are preserved after some add/sub operators, so they are not mistakenly 
 * converted to post-inc/dec. E.g. a + ++b -> a+ ++b
 *
 * Preserves multi-line comments that begin with /*!
 * 
 * PHP 5 or higher is required.
 *
 * Permission is hereby granted to use this version of the library under the
 * same terms as jsmin.c, which has the following license:
 *
 * --
 * Copyright (c) 2002 Douglas Crockford  (www.crockford.com)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * The Software shall be used for Good, not Evil.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * --
 *
 * @package JSMin
 * @author Ryan Grove <ryan@wonko.com> (PHP port)
 * @author Steve Clay <steve@mrclay.org> (modifications + cleanup)
 * @author Andrea Giammarchi <http://www.3site.eu> (spaceBeforeRegExp)
 * @copyright 2002 Douglas Crockford <douglas@crockford.com> (jsmin.c)
 * @copyright 2008 Ryan Grove <ryan@wonko.com> (PHP port)
 * @license http://opensource.org/licenses/mit-license.php MIT License
 * @link http://code.google.com/p/jsmin-php/
 */

class jsmin
{
	const ORD_LF            = 10;
	const ORD_SPACE         = 32;
	const ACTION_KEEP_A     = 1;
	const ACTION_DELETE_A   = 2;
	const ACTION_DELETE_A_B = 3;
	
	protected $a           = "\n";
	protected $b           = '';
	protected $input       = '';
	protected $inputIndex  = 0;
	protected $inputLength = 0;
	protected $lookAhead   = null;
	protected $output      = '';
	protected $lastByteOut  = '';
	
	/**
	 * Minify Javascript.
		*
	 * @param string $js Javascript to be minified
		*
	 * @return string
	 */
	public static function mini($js)
	{
		$jsmin = new jsmin($js);
		return $jsmin->min();
	}
	
	/**
	 * @param string $input
	 */
	public function __construct($input)
	{
		$this->input = $input;
	}
	
	/**
	 * Perform minification, return result
		*
	 * @return string
	 */
	public function min()
	{
		if ($this->output !== '') { // min already run
			return $this->output;
		}
		
		$mbIntEnc = null;
		if (function_exists('mb_strlen') && ((int)ini_get('mbstring.func_overload') & 2)) {
			$mbIntEnc = mb_internal_encoding();
			mb_internal_encoding('8bit');
		}
		$this->input = str_replace("\r\n", "\n", $this->input);
		$this->inputLength = strlen($this->input);
		
		$this->action(self::ACTION_DELETE_A_B);
		
		while ($this->a !== null) {
			// determine next command
			$command = self::ACTION_KEEP_A; // default
			if ($this->a === ' ') {
				if (($this->lastByteOut === '+' || $this->lastByteOut === '-') 
				&& ($this->b === $this->lastByteOut)) {
					// Don't delete this space. If we do, the addition/subtraction
					// could be parsed as a post-increment
				} elseif (! $this->isAlphaNum($this->b)) {
					$command = self::ACTION_DELETE_A;
				}
			} elseif ($this->a === "\n") {
				if ($this->b === ' ') {
					$command = self::ACTION_DELETE_A_B;
					// in case of mbstring.func_overload & 2, must check for null b,
					// otherwise mb_strpos will give WARNING
				} elseif ($this->b === null
				|| (false === strpos('{[(+-', $this->b)
				&& ! $this->isAlphaNum($this->b))) {
					$command = self::ACTION_DELETE_A;
				}
			} elseif (! $this->isAlphaNum($this->a)) {
				if ($this->b === ' '
				|| ($this->b === "\n" 
				&& (false === strpos('}])+-"\'', $this->a)))) {
					$command = self::ACTION_DELETE_A_B;
				}
			}
			$this->action($command);
		}
		$this->output = trim($this->output);
		
		if ($mbIntEnc !== null) {
			mb_internal_encoding($mbIntEnc);
		}
		return $this->output;
	}
	
	/**
	 * ACTION_KEEP_A = Output A. Copy B to A. Get the next B.
	 * ACTION_DELETE_A = Copy B to A. Get the next B.
	 * ACTION_DELETE_A_B = Get the next B.
		*
	 * @param int $command
	 * @throws JSMin_UnterminatedRegExpException|JSMin_UnterminatedStringException
	 */
	protected function action($command)
	{
		if ($command === self::ACTION_DELETE_A_B 
		&& $this->b === ' '
		&& ($this->a === '+' || $this->a === '-')) {
			// Note: we're at an addition/substraction operator; the inputIndex
			// will certainly be a valid index
			if ($this->input[$this->inputIndex] === $this->a) {
				// This is "+ +" or "- -". Don't delete the space.
				$command = self::ACTION_KEEP_A;
			}
		}
		switch ($command) {
			case self::ACTION_KEEP_A:
			$this->output .= $this->a;
			$this->lastByteOut = $this->a;
			
			// fallthrough
			case self::ACTION_DELETE_A:
			$this->a = $this->b;
			if ($this->a === "'" || $this->a === '"') { // string literal
				$str = $this->a; // in case needed for exception
				while (true) {
					$this->output .= $this->a;
					$this->lastByteOut = $this->a;
					
					$this->a       = $this->get();
					if ($this->a === $this->b) { // end quote
						break;
					}
					if (ord($this->a) <= self::ORD_LF) {
						throw new JSMin_UnterminatedStringException(
						"JSMin: Unterminated String at byte "
						. $this->inputIndex . ": {$str}");
					}
					$str .= $this->a;
					if ($this->a === '\\') {
						$this->output .= $this->a;
						$this->lastByteOut = $this->a;
						
						$this->a       = $this->get();
						$str .= $this->a;
					}
				}
			}
			// fallthrough
			case self::ACTION_DELETE_A_B:
			$this->b = $this->next();
			if ($this->b === '/' && $this->isRegexpLiteral()) { // RegExp literal
				$this->output .= $this->a . $this->b;
				$pattern = '/'; // in case needed for exception
				while (true) {
					$this->a = $this->get();
					$pattern .= $this->a;
					if ($this->a === '/') { // end pattern
						break; // while (true)
					} elseif ($this->a === '\\') {
						$this->output .= $this->a;
						$this->a       = $this->get();
						$pattern      .= $this->a;
					} elseif (ord($this->a) <= self::ORD_LF) {
						throw new JSMin_UnterminatedRegExpException(
						"JSMin: Unterminated RegExp at byte "
						. $this->inputIndex .": {$pattern}");
					}
					$this->output .= $this->a;
					$this->lastByteOut = $this->a;
				}
				$this->b = $this->next();
			}
			// end case ACTION_DELETE_A_B
		}
	}
	
	/**
	 * @return bool
	 */
	protected function isRegexpLiteral()
	{
		if (false !== strpos("\n{;(,=:[!&|?", $this->a)) { // we aren't dividing
			return true;
		}
		if (' ' === $this->a) {
			$length = strlen($this->output);
			if ($length < 2) { // weird edge case
				return true;
			}
			// you can't divide a keyword
			if (preg_match('/(?:case|else|in|return|typeof)$/', $this->output, $m)) {
				if ($this->output === $m[0]) { // odd but could happen
					return true;
				}
				// make sure it's a keyword, not end of an identifier
				$charBeforeKeyword = substr($this->output, $length - strlen($m[0]) - 1, 1);
				if (! $this->isAlphaNum($charBeforeKeyword)) {
					return true;
				}
			}
		}
		return false;
	}
	
	/**
	 * Get next char. Convert ctrl char to space.
		*
	 * @return string
	 */
	protected function get()
	{
		$c = $this->lookAhead;
		$this->lookAhead = null;
		if ($c === null) {
			if ($this->inputIndex < $this->inputLength) {
				$c = $this->input[$this->inputIndex];
				$this->inputIndex += 1;
			} else {
				return null;
			}
		}
		if ($c === "\r" || $c === "\n") {
			return "\n";
		}
		if (ord($c) < self::ORD_SPACE) { // control char
			return ' ';
		}
		return $c;
	}
	
	/**
	 * Get next char. If is ctrl character, translate to a space or newline.
		*
	 * @return string
	 */
	protected function peek()
	{
		$this->lookAhead = $this->get();
		return $this->lookAhead;
	}
	
	/**
	 * Is $c a letter, digit, underscore, dollar sign, escape, or non-ASCII?
		*
	 * @param string $c
		*
	 * @return bool
	 */
	protected function isAlphaNum($c)
	{
		return (preg_match('/^[0-9a-zA-Z_\\$\\\\]$/', $c) || ord($c) > 126);
	}
	
	/**
	 * @return string
	 */
	protected function singleLineComment()
	{
		$comment = '';
		while (true) {
			$get = $this->get();
			$comment .= $get;
			if (ord($get) <= self::ORD_LF) { // EOL reached
				// if IE conditional comment
				if (preg_match('/^\\/@(?:cc_on|if|elif|else|end)\\b/', $comment)) {
					return "/{$comment}";
				}
				return $get;
			}
		}
	}
	
	/**
	 * @return string
	 * @throws JSMin_UnterminatedCommentException
	 */
	protected function multipleLineComment()
	{
		$this->get();
		$comment = '';
		while (true) {
			$get = $this->get();
			if ($get === '*') {
				if ($this->peek() === '/') { // end of comment reached
					$this->get();
					// if comment preserved by YUI Compressor
					if (0 === strpos($comment, '!')) {
						return "\n/*!" . substr($comment, 1) . "*/\n";
					}
					// if IE conditional comment
					if (preg_match('/^@(?:cc_on|if|elif|else|end)\\b/', $comment)) {
						return "/*{$comment}*/";
					}
					return ' ';
				}
			} elseif ($get === null) {
				throw new JSMin_UnterminatedCommentException(
				"JSMin: Unterminated comment at byte "
				. $this->inputIndex . ": /*{$comment}");
			}
			$comment .= $get;
		}
	}
	
	/**
	 * Get the next character, skipping over comments.
	 * Some comments may be preserved.
		*
	 * @return string
	 */
	protected function next()
	{
		$get = $this->get();
		if ($get !== '/') {
			return $get;
		}
		switch ($this->peek()) {
			case '/': return $this->singleLineComment();
			case '*': return $this->multipleLineComment();
			default: return $get;
		}
	}
}

class JSMin_UnterminatedStringException extends Exception {}
class JSMin_UnterminatedCommentException extends Exception {}
class JSMin_UnterminatedRegExpException extends Exception {}

/**
 * lessphp v0.3.8
 * http://leafo.net/lessphp
	*
 * LESS css compiler, adapted from http://lesscss.org
	*
 * Copyright 2012, Leaf Corcoran <leafot@gmail.com>
 * Licensed under MIT or GPLv3, see LICENSE
 */


/**
 * The less compiler and parser.
	*
 * Converting LESS to CSS is a three stage process. The incoming file is parsed
 * by `lessc_parser` into a syntax tree, then it is compiled into another tree
 * representing the CSS structure by `lessc`. The CSS tree is fed into a
 * formatter, like `lessc_formatter` which then outputs CSS as a string.
	*
 * During the first compile, all values are *reduced*, which means that their
 * types are brought to the lowest form before being dump as strings. This
 * handles math equations, variable dereferences, and the like.
	*
 * The `parse` function of `lessc` is the entry point.
	*
 * In summary:
	*
 * The `lessc` class creates an intstance of the parser, feeds it LESS code,
 * then transforms the resulting tree to a CSS tree. This class also holds the
 * evaluation context, such as all available mixins and variables at any given
 * time.
	*
 * The `lessc_parser` class is only concerned with parsing its input.
	*
 * The `lessc_formatter` takes a CSS tree, and dumps it to a formatted string,
 * handling things like indentation.
 */
class lessc {
	static public $VERSION = "v0.3.8";
	static protected $TRUE = array("keyword", "true");
	static protected $FALSE = array("keyword", "false");
	
	protected $libFunctions = array();
	protected $registeredVars = array();
	protected $preserveComments = false;
	
	public $vPrefix = '@'; // prefix of abstract properties
	public $mPrefix = '$'; // prefix of abstract blocks
	public $parentSelector = '&';
	
	public $importDisabled = false;
	public $importDir = '';
	
	protected $numberPrecision = null;
	
	// set to the parser that generated the current line when compiling
	// so we know how to create error messages
	protected $sourceParser = null;
	protected $sourceLoc = null;
	
	static public $defaultValue = array("keyword", "");
	
	static protected $nextImportId = 0; // uniquely identify imports
	
	// attempts to find the path of an import url, returns null for css files
	protected function findImport($url) {
		foreach ((array)$this->importDir as $dir) {
			$full = $dir.(substr($dir, -1) != '/' ? '/' : '').$url;
			if ($this->fileExists($file = $full.'.less') || $this->fileExists($file = $full)) {
				return $file;
			}
		}
		
		return null;
	}
	
	protected function fileExists($name) {
		return is_file($name);
	}
	
	static public function compressList($items, $delim) {
		if (!isset($items[1]) && isset($items[0])) return $items[0];
		else return array('list', $delim, $items);
	}
	
	static public function preg_quote($what) {
		return preg_quote($what, '/');
	}
	
	protected function tryImport($importPath, $parentBlock, $out) {
		if ($importPath[0] == "function" && $importPath[1] == "url") {
			$importPath = $this->flattenList($importPath[2]);
		}
		
		$str = $this->coerceString($importPath);
		if ($str === null) return false;
		
		$url = $this->compileValue($this->lib_e($str));
		
		// don't import if it ends in css
		if (substr_compare($url, '.css', -4, 4) === 0) return false;
		
		$realPath = $this->findImport($url);
		if ($realPath === null) return false;
		
		if ($this->importDisabled) {
			return array(false, "/* import disabled */");
		}
		
		$this->addParsedFile($realPath);
		$parser = $this->makeParser($realPath);
		$root = $parser->parse(file_get_contents($realPath));
		
		// set the parents of all the block props
		foreach ($root->props as $prop) {
			if ($prop[0] == "block") {
				$prop[1]->parent = $parentBlock;
			}
		}
		
		// copy mixins into scope, set their parents
		// bring blocks from import into current block
		// TODO: need to mark the source parser	these came from this file
		foreach ($root->children as $childName => $child) {
			if (isset($parentBlock->children[$childName])) {
				$parentBlock->children[$childName] = array_merge(
				$parentBlock->children[$childName],
				$child);
			} else {
				$parentBlock->children[$childName] = $child;
			}
		}
		
		$pi = pathinfo($realPath);
		$dir = $pi["dirname"];
		
		list($top, $bottom) = $this->sortProps($root->props, true);
		$this->compileImportedProps($top, $parentBlock, $out, $parser, $dir);
		
		return array(true, $bottom, $parser, $dir);
	}
	
	protected function compileImportedProps($props, $block, $out, $sourceParser, $importDir) {
		$oldSourceParser = $this->sourceParser;
		
		$oldImport = $this->importDir;
		
		// TODO: this is because the importDir api is stupid
		$this->importDir = (array)$this->importDir;
		array_unshift($this->importDir, $importDir);
		
		foreach ($props as $prop) {
			$this->compileProp($prop, $block, $out);
		}
		
		$this->importDir = $oldImport;
		$this->sourceParser = $oldSourceParser;
	}
	
	/**
	 * Recursively compiles a block.
		*
	 * A block is analogous to a CSS block in most cases. A single LESS document
	 * is encapsulated in a block when parsed, but it does not have parent tags
	 * so all of it's children appear on the root level when compiled.
		*
	 * Blocks are made up of props and children.
		*
	 * Props are property instructions, array tuples which describe an action
	 * to be taken, eg. write a property, set a variable, mixin a block.
		*
	 * The children of a block are just all the blocks that are defined within.
	 * This is used to look up mixins when performing a mixin.
		*
	 * Compiling the block involves pushing a fresh environment on the stack,
	 * and iterating through the props, compiling each one.
		*
	 * See lessc::compileProp()
		*
	 */
	protected function compileBlock($block) {
		switch ($block->type) {
			case "root":
			$this->compileRoot($block);
			break;
			case null:
			$this->compileCSSBlock($block);
			break;
			case "media":
			$this->compileMedia($block);
			break;
			case "directive":
			$name = "@" . $block->name;
			if (!empty($block->value)) {
				$name .= " " . $this->compileValue($this->reduce($block->value));
			}
			
			$this->compileNestedBlock($block, array($name));
			break;
			default:
			$this->throwError("unknown block type: $block->type\n");
		}
	}
	
	protected function compileCSSBlock($block) {
		$env = $this->pushEnv();
		
		$selectors = $this->compileSelectors($block->tags);
		$env->selectors = $this->multiplySelectors($selectors);
		$out = $this->makeOutputBlock(null, $env->selectors);
		
		$this->scope->children[] = $out;
		$this->compileProps($block, $out);
		
		$block->scope = $env; // mixins carry scope with them!
		$this->popEnv();
	}
	
	protected function compileMedia($media) {
		$env = $this->pushEnv($media);
		$parentScope = $this->mediaParent($this->scope);
		
		$query = $this->compileMediaQuery($this->multiplyMedia($env));
		
		$this->scope = $this->makeOutputBlock($media->type, array($query));
		$parentScope->children[] = $this->scope;
		
		$this->compileProps($media, $this->scope);
		
		if (count($this->scope->lines) > 0) {
			$orphanSelelectors = $this->findClosestSelectors();
			if (!is_null($orphanSelelectors)) {
				$orphan = $this->makeOutputBlock(null, $orphanSelelectors);
				$orphan->lines = $this->scope->lines;
				array_unshift($this->scope->children, $orphan);
				$this->scope->lines = array();
			}
		}
		
		$this->scope = $this->scope->parent;
		$this->popEnv();
	}
	
	protected function mediaParent($scope) {
		while (!empty($scope->parent)) {
			if (!empty($scope->type) && $scope->type != "media") {
				break;
			}
			$scope = $scope->parent;
		}
		
		return $scope;
	}
	
	protected function compileNestedBlock($block, $selectors) {
		$this->pushEnv($block);
		$this->scope = $this->makeOutputBlock($block->type, $selectors);
		$this->scope->parent->children[] = $this->scope;
		
		$this->compileProps($block, $this->scope);
		
		$this->scope = $this->scope->parent;
		$this->popEnv();
	}
	
	protected function compileRoot($root) {
		$this->pushEnv();
		$this->scope = $this->makeOutputBlock($root->type);
		$this->compileProps($root, $this->scope);
		$this->popEnv();
	}
	
	protected function compileProps($block, $out) {
		foreach ($this->sortProps($block->props) as $prop) {
			$this->compileProp($prop, $block, $out);
		}
	}
	
	protected function sortProps($props, $split = false) {
		$vars = array();
		$imports = array();
		$other = array();
		
		foreach ($props as $prop) {
			switch ($prop[0]) {
				case "assign":
				if (isset($prop[1][0]) && $prop[1][0] == $this->vPrefix) {
					$vars[] = $prop;
				} else {
					$other[] = $prop;
				}
				break;
				case "import":
				$id = self::$nextImportId++;
				$prop[] = $id;
				$imports[] = $prop;
				$other[] = array("import_mixin", $id);
				break;
				default:
				$other[] = $prop;
			}
		}
		
		if ($split) {
			return array(array_merge($vars, $imports), $other);
		} else {
			return array_merge($vars, $imports, $other);
		}
	}
	
	protected function compileMediaQuery($queries) {
		$compiledQueries = array();
		foreach ($queries as $query) {
			$parts = array();
			foreach ($query as $q) {
				switch ($q[0]) {
					case "mediaType":
					$parts[] = implode(" ", array_slice($q, 1));
					break;
					case "mediaExp":
					if (isset($q[2])) {
						$parts[] = "($q[1]: " .
						$this->compileValue($this->reduce($q[2])) . ")";
					} else {
						$parts[] = "($q[1])";
					}
					break;
				}
			}
			
			if (count($parts) > 0) {
				$compiledQueries[] =  implode(" and ", $parts);
			}
		}
		
		$out = "@media";
		if (!empty($parts)) {
			$out .= " " .
			implode($this->formatter->selectorSeparator, $compiledQueries);
		}
		return $out;
	}
	
	protected function multiplyMedia($env, $childQueries = null) {
		if (is_null($env) ||
		!empty($env->block->type) && $env->block->type != "media")
		{
			return $childQueries;
		}
		
		// plain old block, skip
		if (empty($env->block->type)) {
			return $this->multiplyMedia($env->parent, $childQueries);
		}
		
		$out = array();
		$queries = $env->block->queries;
		if (is_null($childQueries)) {
			$out = $queries;
		} else {
			foreach ($queries as $parent) {
				foreach ($childQueries as $child) {
					$out[] = array_merge($parent, $child);
				}
			}
		}
		
		return $this->multiplyMedia($env->parent, $out);
	}
	
	protected function expandParentSelectors(&$tag, $replace) {
		$parts = explode("$&$", $tag);
		$count = 0;
		foreach ($parts as &$part) {
			$part = str_replace($this->parentSelector, $replace, $part, $c);
			$count += $c;
		}
		$tag = implode($this->parentSelector, $parts);
		return $count;
	}
	
	protected function findClosestSelectors() {
		$env = $this->env;
		$selectors = null;
		while ($env !== null) {
			if (isset($env->selectors)) {
				$selectors = $env->selectors;
				break;
			}
			$env = $env->parent;
		}
		
		return $selectors;
	}
	
	
	// multiply $selectors against the nearest selectors in env
	protected function multiplySelectors($selectors) {
		// find parent selectors
		
		$parentSelectors = $this->findClosestSelectors();
		if (is_null($parentSelectors)) {
			// kill parent reference in top level selector
			foreach ($selectors as &$s) {
				$this->expandParentSelectors($s, "");
			}
			
			return $selectors;
		}
		
		$out = array();
		foreach ($parentSelectors as $parent) {
			foreach ($selectors as $child) {
				$count = $this->expandParentSelectors($child, $parent);
				
				// don't prepend the parent tag if & was used
				if ($count > 0) {
					$out[] = trim($child);
				} else {
					$out[] = trim($parent . ' ' . $child);
				}
			}
		}
		
		return $out;
	}
	
	// reduces selector expressions
	protected function compileSelectors($selectors) {
		$out = array();
		
		foreach ($selectors as $s) {
			if (is_array($s)) {
				list(, $value) = $s;
				$out[] = $this->compileValue($this->reduce($value));
			} else {
				$out[] = $s;
			}
		}
		
		return $out;
	}
	
	protected function eq($left, $right) {
		return $left == $right;
	}
	
	protected function patternMatch($block, $callingArgs) {
		// match the guards if it has them
		// any one of the groups must have all its guards pass for a match
		if (!empty($block->guards)) {
			$groupPassed = false;
			foreach ($block->guards as $guardGroup) {
				foreach ($guardGroup as $guard) {
					$this->pushEnv();
					$this->zipSetArgs($block->args, $callingArgs);
					
					$negate = false;
					if ($guard[0] == "negate") {
						$guard = $guard[1];
						$negate = true;
					}
					
					$passed = $this->reduce($guard) == self::$TRUE;
					if ($negate) $passed = !$passed;
					
					$this->popEnv();
					
					if ($passed) {
						$groupPassed = true;
					} else {
						$groupPassed = false;
						break;
					}
				}
				
				if ($groupPassed) break;
			}
			
			if (!$groupPassed) {
				return false;
			}
		}
		
		$numCalling = count($callingArgs);
		
		if (empty($block->args)) {
			return $block->isVararg || $numCalling == 0;
		}
		
		$i = -1; // no args
		// try to match by arity or by argument literal
		foreach ($block->args as $i => $arg) {
			switch ($arg[0]) {
				case "lit":
				if (empty($callingArgs[$i]) || !$this->eq($arg[1], $callingArgs[$i])) {
					return false;
				}
				break;
				case "arg":
				// no arg and no default value
				if (!isset($callingArgs[$i]) && !isset($arg[2])) {
					return false;
				}
				break;
				case "rest":
				$i--; // rest can be empty
				break 2;
			}
		}
		
		if ($block->isVararg) {
			return true; // not having enough is handled above
		} else {
			$numMatched = $i + 1;
			// greater than becuase default values always match
			return $numMatched >= $numCalling;
		}
	}
	
	protected function patternMatchAll($blocks, $callingArgs) {
		$matches = null;
		foreach ($blocks as $block) {
			if ($this->patternMatch($block, $callingArgs)) {
				$matches[] = $block;
			}
		}
		
		return $matches;
	}
	
	// attempt to find blocks matched by path and args
	protected function findBlocks($searchIn, $path, $args, $seen=array()) {
		if ($searchIn == null) return null;
		if (isset($seen[$searchIn->id])) return null;
		$seen[$searchIn->id] = true;
		
		$name = $path[0];
		
		if (isset($searchIn->children[$name])) {
			$blocks = $searchIn->children[$name];
			if (count($path) == 1) {
				$matches = $this->patternMatchAll($blocks, $args);
				if (!empty($matches)) {
					// This will return all blocks that match in the closest
					// scope that has any matching block, like lessjs
					return $matches;
				}
			} else {
				$matches = array();
				foreach ($blocks as $subBlock) {
					$subMatches = $this->findBlocks($subBlock,
					array_slice($path, 1), $args, $seen);
					
					if (!is_null($subMatches)) {
						foreach ($subMatches as $sm) {
							$matches[] = $sm;
						}
					}
				}
				
				return count($matches) > 0 ? $matches : null;
			}
		}
		
		if ($searchIn->parent === $searchIn) return null;
		return $this->findBlocks($searchIn->parent, $path, $args, $seen);
	}
	
	// sets all argument names in $args to either the default value
	// or the one passed in through $values
	protected function zipSetArgs($args, $values) {
		$i = 0;
		$assignedValues = array();
		foreach ($args as $a) {
			if ($a[0] == "arg") {
				if ($i < count($values) && !is_null($values[$i])) {
					$value = $values[$i];
				} elseif (isset($a[2])) {
					$value = $a[2];
				} else $value = null;
				
				$value = $this->reduce($value);
				$this->set($a[1], $value);
				$assignedValues[] = $value;
			}
			$i++;
		}
		
		// check for a rest
		$last = end($args);
		if ($last[0] == "rest") {
			$rest = array_slice($values, count($args) - 1);
			$this->set($last[1], $this->reduce(array("list", " ", $rest)));
		}
		
		$this->env->arguments = $assignedValues;
	}
	
	// compile a prop and update $lines or $blocks appropriately
	protected function compileProp($prop, $block, $out) {
		// set error position context
		$this->sourceLoc = isset($prop[-1]) ? $prop[-1] : -1;
		
		switch ($prop[0]) {
			case 'assign':
			list(, $name, $value) = $prop;
			if ($name[0] == $this->vPrefix) {
				$this->set($name, $value);
			} else {
				$out->lines[] = $this->formatter->property($name,
				$this->compileValue($this->reduce($value)));
			}
			break;
			case 'block':
			list(, $child) = $prop;
			$this->compileBlock($child);
			break;
			case 'mixin':
			list(, $path, $args, $suffix) = $prop;
			
			$args = array_map(array($this, "reduce"), (array)$args);
			$mixins = $this->findBlocks($block, $path, $args);
			
			if ($mixins === null) {
				// fwrite(STDERR,"failed to find block: ".implode(" > ", $path)."\n");
				break; // throw error here??
			}
			
			foreach ($mixins as $mixin) {
				$haveScope = false;
				if (isset($mixin->parent->scope)) {
					$haveScope = true;
					$mixinParentEnv = $this->pushEnv();
					$mixinParentEnv->storeParent = $mixin->parent->scope;
				}
				
				$haveArgs = false;
				if (isset($mixin->args)) {
					$haveArgs = true;
					$this->pushEnv();
					$this->zipSetArgs($mixin->args, $args);
				}
				
				$oldParent = $mixin->parent;
				if ($mixin != $block) $mixin->parent = $block;
				
				foreach ($this->sortProps($mixin->props) as $subProp) {
					if ($suffix !== null &&
					$subProp[0] == "assign" &&
					is_string($subProp[1]) &&
					$subProp[1]{0} != $this->vPrefix)
					{
						$subProp[2] = array(
						'list', ' ',
						array($subProp[2], array('keyword', $suffix))
						);
					}
					
					$this->compileProp($subProp, $mixin, $out);
				}
				
				$mixin->parent = $oldParent;
				
				if ($haveArgs) $this->popEnv();
				if ($haveScope) $this->popEnv();
			}
			
			break;
			case 'raw':
			$out->lines[] = $prop[1];
			break;
			case "directive":
			list(, $name, $value) = $prop;
			$out->lines[] = "@$name " . $this->compileValue($this->reduce($value)).';';
			break;
			case "comment":
			$out->lines[] = $prop[1];
			break;
			case "import";
			list(, $importPath, $importId) = $prop;
			$importPath = $this->reduce($importPath);
			
			if (!isset($this->env->imports)) {
				$this->env->imports = array();
			}
			
			$result = $this->tryImport($importPath, $block, $out);
			
			$this->env->imports[$importId] = $result === false ?
			array(false, "@import " . $this->compileValue($importPath).";") :
			$result;
			
			break;
			case "import_mixin":
			list(,$importId) = $prop;
			$import = $this->env->imports[$importId];
			if ($import[0] === false) {
				$out->lines[] = $import[1];
			} else {
				list(, $bottom, $parser, $importDir) = $import;
				$this->compileImportedProps($bottom, $block, $out, $parser, $importDir);
			}
			
			break;
			default:
			$this->throwError("unknown op: {$prop[0]}\n");
		}
	}
	
	
	/**
	 * Compiles a primitive value into a CSS property value.
		*
	 * Values in lessphp are typed by being wrapped in arrays, their format is
	 * typically:
		*
	 *     array(type, contents [, additional_contents]*)
		*
	 * The input is expected to be reduced. This function will not work on
	 * things like expressions and variables.
	 */
	protected function compileValue($value) {
		switch ($value[0]) {
			case 'list':
			// [1] - delimiter
			// [2] - array of values
			return implode($value[1], array_map(array($this, 'compileValue'), $value[2]));
			case 'raw_color':
			if (!empty($this->formatter->compressColors)) {
				return $this->compileValue($this->coerceColor($value));
			}
			return $value[1];
			case 'keyword':
			// [1] - the keyword
			return $value[1];
			case 'number':
			list(, $num, $unit) = $value;
			// [1] - the number
			// [2] - the unit
			if ($this->numberPrecision !== null) {
				$num = round($num, $this->numberPrecision);
			}
			return $num . $unit;
			case 'string':
			// [1] - contents of string (includes quotes)
			list(, $delim, $content) = $value;
			foreach ($content as &$part) {
				if (is_array($part)) {
					$part = $this->compileValue($part);
				}
			}
			return $delim . implode($content) . $delim;
			case 'color':
			// [1] - red component (either number or a %)
			// [2] - green component
			// [3] - blue component
			// [4] - optional alpha component
			list(, $r, $g, $b) = $value;
			$r = round($r);
			$g = round($g);
			$b = round($b);
			
			if (count($value) == 5 && $value[4] != 1) { // rgba
				return 'rgba('.$r.','.$g.','.$b.','.$value[4].')';
			}
			
			$h = sprintf("#%02x%02x%02x", $r, $g, $b);
			
			if (!empty($this->formatter->compressColors)) {
				// Converting hex color to short notation (e.g. #003399 to #039)
				if ($h[1] === $h[2] && $h[3] === $h[4] && $h[5] === $h[6]) {
					$h = '#' . $h[1] . $h[3] . $h[5];
				}
			}
			
			return $h;
			
			case 'function':
			list(, $name, $args) = $value;
			return $name.'('.$this->compileValue($args).')';
			default: // assumed to be unit
			$this->throwError("unknown value type: $value[0]");
		}
	}
	
	protected function lib_isnumber($value) {
		return $this->toBool($value[0] == "number");
	}
	
	protected function lib_isstring($value) {
		return $this->toBool($value[0] == "string");
	}
	
	protected function lib_iscolor($value) {
		return $this->toBool($this->coerceColor($value));
	}
	
	protected function lib_iskeyword($value) {
		return $this->toBool($value[0] == "keyword");
	}
	
	protected function lib_ispixel($value) {
		return $this->toBool($value[0] == "number" && $value[2] == "px");
	}
	
	protected function lib_ispercentage($value) {
		return $this->toBool($value[0] == "number" && $value[2] == "%");
	}
	
	protected function lib_isem($value) {
		return $this->toBool($value[0] == "number" && $value[2] == "em");
	}
	
	protected function lib_rgbahex($color) {
		$color = $this->coerceColor($color);
		if (is_null($color))
		$this->throwError("color expected for rgbahex");
		
		return sprintf("#%02x%02x%02x%02x",
		isset($color[4]) ? $color[4]*255 : 255,
		$color[1],$color[2], $color[3]);
	}
	
	protected function lib_argb($color){
		return $this->lib_rgbahex($color);
	}
	
	// utility func to unquote a string
	protected function lib_e($arg) {
		switch ($arg[0]) {
			case "list":
			$items = $arg[2];
			if (isset($items[0])) {
				return $this->lib_e($items[0]);
			}
			return self::$defaultValue;
			case "string":
			$arg[1] = "";
			return $arg;
			case "keyword":
			return $arg;
			default:
			return array("keyword", $this->compileValue($arg));
		}
	}
	
	protected function lib__sprintf($args) {
		if ($args[0] != "list") return $args;
		$values = $args[2];
		$string = array_shift($values);
		$template = $this->compileValue($this->lib_e($string));
		
		$i = 0;
		if (preg_match_all('/%[dsa]/', $template, $m)) {
			foreach ($m[0] as $match) {
				$val = isset($values[$i]) ?
				$this->reduce($values[$i]) : array('keyword', '');
				
				// lessjs compat, renders fully expanded color, not raw color
				if ($color = $this->coerceColor($val)) {
					$val = $color;
				}
				
				$i++;
				$rep = $this->compileValue($this->lib_e($val));
				$template = preg_replace('/'.self::preg_quote($match).'/',
				$rep, $template, 1);
			}
		}
		
		$d = $string[0] == "string" ? $string[1] : '"';
		return array("string", $d, array($template));
	}
	
	protected function lib_floor($arg) {
		$value = $this->assertNumber($arg);
		return array("number", floor($value), $arg[2]);
	}
	
	protected function lib_ceil($arg) {
		$value = $this->assertNumber($arg);
		return array("number", ceil($value), $arg[2]);
	}
	
	protected function lib_round($arg) {
		$value = $this->assertNumber($arg);
		return array("number", round($value), $arg[2]);
	}
	
	/**
	 * Helper function to get arguments for color manipulation functions.
	 * takes a list that contains a color like thing and a percentage
	 */
	protected function colorArgs($args) {
		if ($args[0] != 'list' || count($args[2]) < 2) {
			return array(array('color', 0, 0, 0), 0);
		}
		list($color, $delta) = $args[2];
		$color = $this->assertColor($color);
		$delta = floatval($delta[1]);
		
		return array($color, $delta);
	}
	
	protected function lib_darken($args) {
		list($color, $delta) = $this->colorArgs($args);
		
		$hsl = $this->toHSL($color);
		$hsl[3] = $this->clamp($hsl[3] - $delta, 100);
		return $this->toRGB($hsl);
	}
	
	protected function lib_lighten($args) {
		list($color, $delta) = $this->colorArgs($args);
		
		$hsl = $this->toHSL($color);
		$hsl[3] = $this->clamp($hsl[3] + $delta, 100);
		return $this->toRGB($hsl);
	}
	
	protected function lib_saturate($args) {
		list($color, $delta) = $this->colorArgs($args);
		
		$hsl = $this->toHSL($color);
		$hsl[2] = $this->clamp($hsl[2] + $delta, 100);
		return $this->toRGB($hsl);
	}
	
	protected function lib_desaturate($args) {
		list($color, $delta) = $this->colorArgs($args);
		
		$hsl = $this->toHSL($color);
		$hsl[2] = $this->clamp($hsl[2] - $delta, 100);
		return $this->toRGB($hsl);
	}
	
	protected function lib_spin($args) {
		list($color, $delta) = $this->colorArgs($args);
		
		$hsl = $this->toHSL($color);
		
		$hsl[1] = $hsl[1] + $delta % 360;
		if ($hsl[1] < 0) $hsl[1] += 360;
		
		return $this->toRGB($hsl);
	}
	
	protected function lib_fadeout($args) {
		list($color, $delta) = $this->colorArgs($args);
		$color[4] = $this->clamp((isset($color[4]) ? $color[4] : 1) - $delta/100);
		return $color;
	}
	
	protected function lib_fadein($args) {
		list($color, $delta) = $this->colorArgs($args);
		$color[4] = $this->clamp((isset($color[4]) ? $color[4] : 1) + $delta/100);
		return $color;
	}
	
	protected function lib_hue($color) {
		$hsl = $this->toHSL($this->assertColor($color));
		return round($hsl[1]);
	}
	
	protected function lib_saturation($color) {
		$hsl = $this->toHSL($this->assertColor($color));
		return round($hsl[2]);
	}
	
	protected function lib_lightness($color) {
		$hsl = $this->toHSL($this->assertColor($color));
		return round($hsl[3]);
	}
	
	// get the alpha of a color
	// defaults to 1 for non-colors or colors without an alpha
	protected function lib_alpha($value) {
		if (!is_null($color = $this->coerceColor($value))) {
			return isset($color[4]) ? $color[4] : 1;
		}
	}
	
	// set the alpha of the color
	protected function lib_fade($args) {
		list($color, $alpha) = $this->colorArgs($args);
		$color[4] = $this->clamp($alpha / 100.0);
		return $color;
	}
	
	protected function lib_percentage($arg) {
		$num = $this->assertNumber($arg);
		return array("number", $num*100, "%");
	}
	
	// mixes two colors by weight
	// mix(@color1, @color2, @weight);
	// http://sass-lang.com/docs/yardoc/Sass/Script/Functions.html#mix-instance_method
	protected function lib_mix($args) {
		if ($args[0] != "list" || count($args[2]) < 3)
		$this->throwError("mix expects (color1, color2, weight)");
		
		list($first, $second, $weight) = $args[2];
		$first = $this->assertColor($first);
		$second = $this->assertColor($second);
		
		$first_a = $this->lib_alpha($first);
		$second_a = $this->lib_alpha($second);
		$weight = $weight[1] / 100.0;
		
		$w = $weight * 2 - 1;
		$a = $first_a - $second_a;
		
		$w1 = (($w * $a == -1 ? $w : ($w + $a)/(1 + $w * $a)) + 1) / 2.0;
		$w2 = 1.0 - $w1;
		
		$new = array('color',
		$w1 * $first[1] + $w2 * $second[1],
		$w1 * $first[2] + $w2 * $second[2],
		$w1 * $first[3] + $w2 * $second[3],
		);
		
		if ($first_a != 1.0 || $second_a != 1.0) {
			$new[] = $first_a * $weight + $second_a * ($weight - 1);
		}
		
		return $this->fixColor($new);
	}
	
	protected function assertColor($value, $error = "expected color value") {
		$color = $this->coerceColor($value);
		if (is_null($color)) $this->throwError($error);
		return $color;
	}
	
	protected function assertNumber($value, $error = "expecting number") {
		if ($value[0] == "number") return $value[1];
		$this->throwError($error);
	}
	
	protected function toHSL($color) {
		if ($color[0] == 'hsl') return $color;
		
		$r = $color[1] / 255;
		$g = $color[2] / 255;
		$b = $color[3] / 255;
		
		$min = min($r, $g, $b);
		$max = max($r, $g, $b);
		
		$L = ($min + $max) / 2;
		if ($min == $max) {
			$S = $H = 0;
		} else {
			if ($L < 0.5)
			$S = ($max - $min)/($max + $min);
			else
			$S = ($max - $min)/(2.0 - $max - $min);
			
			if ($r == $max) $H = ($g - $b)/($max - $min);
			elseif ($g == $max) $H = 2.0 + ($b - $r)/($max - $min);
			elseif ($b == $max) $H = 4.0 + ($r - $g)/($max - $min);
			
		}
		
		$out = array('hsl',
		($H < 0 ? $H + 6 : $H)*60,
		$S*100,
		$L*100,
		);
		
		if (count($color) > 4) $out[] = $color[4]; // copy alpha
		return $out;
	}
	
	protected function toRGB_helper($comp, $temp1, $temp2) {
		if ($comp < 0) $comp += 1.0;
		elseif ($comp > 1) $comp -= 1.0;
		
		if (6 * $comp < 1) return $temp1 + ($temp2 - $temp1) * 6 * $comp;
		if (2 * $comp < 1) return $temp2;
		if (3 * $comp < 2) return $temp1 + ($temp2 - $temp1)*((2/3) - $comp) * 6;
		
		return $temp1;
	}
	
	/**
	 * Converts a hsl array into a color value in rgb.
	 * Expects H to be in range of 0 to 360, S and L in 0 to 100
	 */
	protected function toRGB($color) {
		if ($color == 'color') return $color;
		
		$H = $color[1] / 360;
		$S = $color[2] / 100;
		$L = $color[3] / 100;
		
		if ($S == 0) {
			$r = $g = $b = $L;
		} else {
			$temp2 = $L < 0.5 ?
			$L*(1.0 + $S) :
			$L + $S - $L * $S;
			
			$temp1 = 2.0 * $L - $temp2;
			
			$r = $this->toRGB_helper($H + 1/3, $temp1, $temp2);
			$g = $this->toRGB_helper($H, $temp1, $temp2);
			$b = $this->toRGB_helper($H - 1/3, $temp1, $temp2);
		}
		
		// $out = array('color', round($r*255), round($g*255), round($b*255));
		$out = array('color', $r*255, $g*255, $b*255);
		if (count($color) > 4) $out[] = $color[4]; // copy alpha
		return $out;
	}
	
	protected function clamp($v, $max = 1, $min = 0) {
		return min($max, max($min, $v));
	}
	
	/**
	 * Convert the rgb, rgba, hsl color literals of function type
	 * as returned by the parser into values of color type.
	 */
	protected function funcToColor($func) {
		$fname = $func[1];
		if ($func[2][0] != 'list') return false; // need a list of arguments
		$rawComponents = $func[2][2];
		
		if ($fname == 'hsl' || $fname == 'hsla') {
			$hsl = array('hsl');
			$i = 0;
			foreach ($rawComponents as $c) {
				$val = $this->reduce($c);
				$val = isset($val[1]) ? floatval($val[1]) : 0;
				
				if ($i == 0) $clamp = 360;
				elseif ($i < 3) $clamp = 100;
				else $clamp = 1;
				
				$hsl[] = $this->clamp($val, $clamp);
				$i++;
			}
			
			while (count($hsl) < 4) $hsl[] = 0;
			return $this->toRGB($hsl);
			
		} elseif ($fname == 'rgb' || $fname == 'rgba') {
			$components = array();
			$i = 1;
			foreach	($rawComponents as $c) {
				$c = $this->reduce($c);
				if ($i < 4) {
					if ($c[0] == "number" && $c[2] == "%") {
						$components[] = 255 * ($c[1] / 100);
					} else {
						$components[] = floatval($c[1]);
					}
				} elseif ($i == 4) {
					if ($c[0] == "number" && $c[2] == "%") {
						$components[] = 1.0 * ($c[1] / 100);
					} else {
						$components[] = floatval($c[1]);
					}
				} else break;
				
				$i++;
			}
			while (count($components) < 3) $components[] = 0;
			array_unshift($components, 'color');
			return $this->fixColor($components);
		}
		
		return false;
	}
	
	protected function reduce($value, $forExpression = false) {
		switch ($value[0]) {
			case "variable":
			$key = $value[1];
			if (is_array($key)) {
				$key = $this->reduce($key);
				$key = $this->vPrefix . $this->compileValue($this->lib_e($key));
			}
			
			$seen =& $this->env->seenNames;
			
			if (!empty($seen[$key])) {
				$this->throwError("infinite loop detected: $key");
			}
			
			$seen[$key] = true;
			$out = $this->reduce($this->get($key, self::$defaultValue));
			$seen[$key] = false;
			return $out;
			case "list":
			foreach ($value[2] as &$item) {
				$item = $this->reduce($item, $forExpression);
			}
			return $value;
			case "expression":
			return $this->evaluate($value);
			case "string":
			foreach ($value[2] as &$part) {
				if (is_array($part)) {
					$strip = $part[0] == "variable";
					$part = $this->reduce($part);
					if ($strip) $part = $this->lib_e($part);
				}
			}
			return $value;
			case "escape":
			list(,$inner) = $value;
			return $this->lib_e($this->reduce($inner));
			case "function":
			$color = $this->funcToColor($value);
			if ($color) return $color;
			
			list(, $name, $args) = $value;
			if ($name == "%") $name = "_sprintf";
			$f = isset($this->libFunctions[$name]) ?
			$this->libFunctions[$name] : array($this, 'lib_'.$name);
			
			if (is_callable($f)) {
				if ($args[0] == 'list')
				$args = self::compressList($args[2], $args[1]);
				
				$ret = call_user_func($f, $this->reduce($args, true), $this);
				
				if (is_null($ret)) {
					return array("string", "", array(
					$name, "(", $args, ")"
					));
				}
				
				// convert to a typed value if the result is a php primitive
				if (is_numeric($ret)) $ret = array('number', $ret, "");
				elseif (!is_array($ret)) $ret = array('keyword', $ret);
				
				return $ret;
			}
			
			// plain function, reduce args
			$value[2] = $this->reduce($value[2]);
			return $value;
			case "unary":
			list(, $op, $exp) = $value;
			$exp = $this->reduce($exp);
			
			if ($exp[0] == "number") {
				switch ($op) {
					case "+":
					return $exp;
					case "-":
					$exp[1] *= -1;
					return $exp;
				}
			}
			return array("string", "", array($op, $exp));
		}
		
		if ($forExpression) {
			switch ($value[0]) {
				case "keyword":
				if ($color = $this->coerceColor($value)) {
					return $color;
				}
				break;
				case "raw_color":
				return $this->coerceColor($value);
			}
		}
		
		return $value;
	}
	
	
	// coerce a value for use in color operation
	protected function coerceColor($value) {
		switch($value[0]) {
			case 'color': return $value;
			case 'raw_color':
			$c = array("color", 0, 0, 0);
			$colorStr = substr($value[1], 1);
			$num = hexdec($colorStr);
			$width = strlen($colorStr) == 3 ? 16 : 256;
			
			for ($i = 3; $i > 0; $i--) { // 3 2 1
				$t = $num % $width;
				$num /= $width;
				
				$c[$i] = $t * (256/$width) + $t * floor(16/$width);
			}
			
			return $c;
			case 'keyword':
			$name = $value[1];
			if (isset(self::$cssColors[$name])) {
				list($r, $g, $b) = explode(',', self::$cssColors[$name]);
				return array('color', $r, $g, $b);
			}
			return null;
		}
	}
	
	// make something string like into a string
	protected function coerceString($value) {
		switch ($value[0]) {
			case "string":
			return $value;
			case "keyword":
			return array("string", "", array($value[1]));
		}
		return null;
	}
	
	// turn list of length 1 into value type
	protected function flattenList($value) {
		if ($value[0] == "list" && count($value[2]) == 1) {
			return $this->flattenList($value[2][0]);
		}
		return $value;
	}
	
	protected function toBool($a) {
		if ($a) return self::$TRUE;
		else return self::$FALSE;
	}
	
	// evaluate an expression
	protected function evaluate($exp) {
		list(, $op, $left, $right, $whiteBefore, $whiteAfter) = $exp;
		
		$left = $this->reduce($left, true);
		$right = $this->reduce($right, true);
		
		if ($leftColor = $this->coerceColor($left)) {
			$left = $leftColor;
		}
		
		if ($rightColor = $this->coerceColor($right)) {
			$right = $rightColor;
		}
		
		$ltype = $left[0];
		$rtype = $right[0];
		
		// operators that work on all types
		if ($op == "and") {
			return $this->toBool($left == self::$TRUE && $right == self::$TRUE);
		}
		
		if ($op == "=") {
			return $this->toBool($this->eq($left, $right) );
		}
		
		if ($op == "+" && !is_null($str = $this->stringConcatenate($left, $right))) {
			return $str;
		}
		
		// type based operators
		$fname = "op_${ltype}_${rtype}";
		if (is_callable(array($this, $fname))) {
			$out = $this->$fname($op, $left, $right);
			if (!is_null($out)) return $out;
		}
		
		// make the expression look it did before being parsed
		$paddedOp = $op;
		if ($whiteBefore) $paddedOp = " " . $paddedOp;
		if ($whiteAfter) $paddedOp .= " ";
		
		return array("string", "", array($left, $paddedOp, $right));
	}
	
	protected function stringConcatenate($left, $right) {
		if ($strLeft = $this->coerceString($left)) {
			if ($right[0] == "string") {
				$right[1] = "";
			}
			$strLeft[2][] = $right;
			return $strLeft;
		}
		
		if ($strRight = $this->coerceString($right)) {
			array_unshift($strRight[2], $left);
			return $strRight;
		}
	}
	
	
	// make sure a color's components don't go out of bounds
	protected function fixColor($c) {
		foreach (range(1, 3) as $i) {
			if ($c[$i] < 0) $c[$i] = 0;
			if ($c[$i] > 255) $c[$i] = 255;
		}
		
		return $c;
	}
	
	protected function op_number_color($op, $lft, $rgt) {
		if ($op == '+' || $op == '*') {
			return $this->op_color_number($op, $rgt, $lft);
		}
	}
	
	protected function op_color_number($op, $lft, $rgt) {
		if ($rgt[0] == '%') $rgt[1] /= 100;
		
		return $this->op_color_color($op, $lft,
		array_fill(1, count($lft) - 1, $rgt[1]));
	}
	
	protected function op_color_color($op, $left, $right) {
		$out = array('color');
		$max = count($left) > count($right) ? count($left) : count($right);
		foreach (range(1, $max - 1) as $i) {
			$lval = isset($left[$i]) ? $left[$i] : 0;
			$rval = isset($right[$i]) ? $right[$i] : 0;
			switch ($op) {
				case '+':
				$out[] = $lval + $rval;
				break;
				case '-':
				$out[] = $lval - $rval;
				break;
				case '*':
				$out[] = $lval * $rval;
				break;
				case '%':
				$out[] = $lval % $rval;
				break;
				case '/':
				if ($rval == 0) $this->throwError("evaluate error: can't divide by zero");
				$out[] = $lval / $rval;
				break;
				default:
				$this->throwError('evaluate error: color op number failed on op '.$op);
			}
		}
		return $this->fixColor($out);
	}
	
	// operator on two numbers
	protected function op_number_number($op, $left, $right) {
		$unit = empty($left[2]) ? $right[2] : $left[2];
		
		$value = 0;
		switch ($op) {
			case '+':
			$value = $left[1] + $right[1];
			break;
			case '*':
			$value = $left[1] * $right[1];
			break;
			case '-':
			$value = $left[1] - $right[1];
			break;
			case '%':
			$value = $left[1] % $right[1];
			break;
			case '/':
			if ($right[1] == 0) $this->throwError('parse error: divide by zero');
			$value = $left[1] / $right[1];
			break;
			case '<':
			return $this->toBool($left[1] < $right[1]);
			case '>':
			return $this->toBool($left[1] > $right[1]);
			case '>=':
			return $this->toBool($left[1] >= $right[1]);
			case '=<':
			return $this->toBool($left[1] <= $right[1]);
			default:
			$this->throwError('parse error: unknown number operator: '.$op);
		}
		
		return array("number", $value, $unit);
	}
	
	
	/* environment functions */
	
	protected function makeOutputBlock($type, $selectors = null) {
		$b = new stdclass;
		$b->lines = array();
		$b->children = array();
		$b->selectors = $selectors;
		$b->type = $type;
		$b->parent = $this->scope;
		return $b;
	}
	
	// the state of execution
	protected function pushEnv($block = null) {
		$e = new stdclass;
		$e->parent = $this->env;
		$e->store = array();
		$e->block = $block;
		
		$this->env = $e;
		return $e;
	}
	
	// pop something off the stack
	protected function popEnv() {
		$old = $this->env;
		$this->env = $this->env->parent;
		return $old;
	}
	
	// set something in the current env
	protected function set($name, $value) {
		$this->env->store[$name] = $value;
	}
	
	
	// get the highest occurrence entry for a name
	protected function get($name, $default=null) {
		$current = $this->env;
		
		$isArguments = $name == $this->vPrefix . 'arguments';
		while ($current) {
			if ($isArguments && isset($current->arguments)) {
				return array('list', ' ', $current->arguments);
			}
			
			if (isset($current->store[$name]))
			return $current->store[$name];
			else {
				$current = isset($current->storeParent) ?
				$current->storeParent : $current->parent;
			}
		}
		
		return $default;
	}
	
	// inject array of unparsed strings into environment as variables
	protected function injectVariables($args) {
		$this->pushEnv();
		$parser = new lessc_parser($this, __METHOD__);
		foreach ($args as $name => $strValue) {
			if ($name{0} != '@') $name = '@'.$name;
			$parser->count = 0;
			$parser->buffer = (string)$strValue;
			if (!$parser->propertyValue($value)) {
				throw new Exception("failed to parse passed in variable $name: $strValue");
			}
			
			$this->set($name, $value);
		}
	}
	
	/**
	 * Initialize any static state, can initialize parser for a file
	 * $opts isn't used yet
	 */
	public function __construct($fname = null) {
		if ($fname !== null) {
			// used for deprecated parse method
			$this->_parseFile = $fname;
		}
	}
	
	public function compile($string, $name = null) {
		$locale = setlocale(LC_NUMERIC, 0);
		setlocale(LC_NUMERIC, "C");
		
		$this->parser = $this->makeParser($name);
		$root = $this->parser->parse($string);
		
		$this->env = null;
		$this->scope = null;
		
		$this->formatter = $this->newFormatter();
		
		if (!empty($this->registeredVars)) {
			$this->injectVariables($this->registeredVars);
		}
		
		$this->sourceParser = $this->parser; // used for error messages
		$this->compileBlock($root);
		
		ob_start();
		$this->formatter->block($this->scope);
		$out = ob_get_clean();
		setlocale(LC_NUMERIC, $locale);
		return $out;
	}
	
	public function compileFile($fname, $outFname = null) {
		if (!is_readable($fname)) {
			throw new Exception('load error: failed to find '.$fname);
		}
		
		$pi = pathinfo($fname);
		
		$oldImport = $this->importDir;
		
		$this->importDir = (array)$this->importDir;
		$this->importDir[] = $pi['dirname'].'/';
		
		$this->allParsedFiles = array();
		$this->addParsedFile($fname);
		
		$out = $this->compile(file_get_contents($fname), $fname);
		
		$this->importDir = $oldImport;
		
		if ($outFname !== null) {
			return file_put_contents($outFname, $out);
		}
		
		return $out;
	}
	
	// compile only if changed input has changed or output doesn't exist
	public function checkedCompile($in, $out) {
		if (!is_file($out) || filemtime($in) > filemtime($out)) {
			$this->compileFile($in, $out);
			return true;
		}
		return false;
	}
	
	/**
	 * Execute lessphp on a .less file or a lessphp cache structure
		*
	 * The lessphp cache structure contains information about a specific
	 * less file having been parsed. It can be used as a hint for future
	 * calls to determine whether or not a rebuild is required.
		*
	 * The cache structure contains two important keys that may be used
	 * externally:
		*
	 * compiled: The final compiled CSS
	 * updated: The time (in seconds) the CSS was last compiled
		*
	 * The cache structure is a plain-ol' PHP associative array and can
	 * be serialized and unserialized without a hitch.
		*
	 * @param mixed $in Input
	 * @param bool $force Force rebuild?
	 * @return array lessphp cache structure
	 */
	public function cachedCompile($in, $force = false) {
		// assume no root
		$root = null;
		
		if (is_string($in)) {
			$root = $in;
		} elseif (is_array($in) and isset($in['root'])) {
			if ($force or ! isset($in['files'])) {
				// If we are forcing a recompile or if for some reason the
				// structure does not contain any file information we should
				// specify the root to trigger a rebuild.
				$root = $in['root'];
			} elseif (isset($in['files']) and is_array($in['files'])) {
				foreach ($in['files'] as $fname => $ftime ) {
					if (!file_exists($fname) or filemtime($fname) > $ftime) {
						// One of the files we knew about previously has changed
						// so we should look at our incoming root again.
						$root = $in['root'];
						break;
					}
				}
			}
		} else {
			// TODO: Throw an exception? We got neither a string nor something
			// that looks like a compatible lessphp cache structure.
			return null;
		}
		
		if ($root !== null) {
			// If we have a root value which means we should rebuild.
			$out = array();
			$out['root'] = $root;
			$out['compiled'] = $this->compileFile($root);
			$out['files'] = $this->allParsedFiles();
			$out['updated'] = time();
			return $out;
		} else {
			// No changes, pass back the structure
			// we were given initially.
			return $in;
		}
		
	}
	
	// parse and compile buffer
	// This is deprecated
	public function parse($str = null, $initialVariables = null) {
		if (is_array($str)) {
			$initialVariables = $str;
			$str = null;
		}
		
		$oldVars = $this->registeredVars;
		if ($initialVariables !== null) {
			$this->setVariables($initialVariables);
		}
		
		if ($str == null) {
			if (empty($this->_parseFile)) {
				throw new exception("nothing to parse");
			}
			
			$out = $this->compileFile($this->_parseFile);
		} else {
			$out = $this->compile($str);
		}
		
		$this->registeredVars = $oldVars;
		return $out;
	}
	
	protected function makeParser($name) {
		$parser = new lessc_parser($this, $name);
		$parser->writeComments = $this->preserveComments;
		
		return $parser;
	}
	
	public function setFormatter($name) {
		$this->formatterName = $name;
	}
	
	protected function newFormatter() {
		$className = "lessc_formatter_lessjs";
		if (!empty($this->formatterName)) {
			if (!is_string($this->formatterName))
			return $this->formatterName;
			$className = "lessc_formatter_$this->formatterName";
		}
		
		return new $className;
	}
	
	public function setPreserveComments($preserve) {
		$this->preserveComments = $preserve;
	}
	
	public function registerFunction($name, $func) {
		$this->libFunctions[$name] = $func;
	}
	
	public function unregisterFunction($name) {
		unset($this->libFunctions[$name]);
	}
	
	public function setVariables($variables) {
		$this->registeredVars = array_merge($this->registeredVars, $variables);
	}
	
	public function unsetVariable($name) {
		unset($this->registeredVars[$name]);
	}
	
	public function setImportDir($dirs) {
		$this->importDir = (array)$dirs;
	}
	
	public function addImportDir($dir) {
		$this->importDir = (array)$this->importDir;
		$this->importDir[] = $dir;
	}
	
	public function allParsedFiles() {
		return $this->allParsedFiles;
	}
	
	protected function addParsedFile($file) {
		$this->allParsedFiles[realpath($file)] = filemtime($file);
	}
	
	/**
	 * Uses the current value of $this->count to show line and line number
	 */
	protected function throwError($msg = null) {
		if ($this->sourceLoc >= 0) {
			$this->sourceParser->throwError($msg, $this->sourceLoc);
		}
		throw new exception($msg);
	}
	
	// compile file $in to file $out if $in is newer than $out
	// returns true when it compiles, false otherwise
	public static function ccompile($in, $out, $less = null) {
		if ($less === null) {
			$less = new self;
		}
		return $less->checkedCompile($in, $out);
	}
	
	public static function cexecute($in, $force = false, $less = null) {
		if ($less === null) {
			$less = new self;
		}
		return $less->cachedCompile($in, $force);
	}
	
	static protected $cssColors = array(
	'aliceblue' => '240,248,255',
	'antiquewhite' => '250,235,215',
	'aqua' => '0,255,255',
	'aquamarine' => '127,255,212',
	'azure' => '240,255,255',
	'beige' => '245,245,220',
	'bisque' => '255,228,196',
	'black' => '0,0,0',
	'blanchedalmond' => '255,235,205',
	'blue' => '0,0,255',
	'blueviolet' => '138,43,226',
	'brown' => '165,42,42',
	'burlywood' => '222,184,135',
	'cadetblue' => '95,158,160',
	'chartreuse' => '127,255,0',
	'chocolate' => '210,105,30',
	'coral' => '255,127,80',
	'cornflowerblue' => '100,149,237',
	'cornsilk' => '255,248,220',
	'crimson' => '220,20,60',
	'cyan' => '0,255,255',
	'darkblue' => '0,0,139',
	'darkcyan' => '0,139,139',
	'darkgoldenrod' => '184,134,11',
	'darkgray' => '169,169,169',
	'darkgreen' => '0,100,0',
	'darkgrey' => '169,169,169',
	'darkkhaki' => '189,183,107',
	'darkmagenta' => '139,0,139',
	'darkolivegreen' => '85,107,47',
	'darkorange' => '255,140,0',
	'darkorchid' => '153,50,204',
	'darkred' => '139,0,0',
	'darksalmon' => '233,150,122',
	'darkseagreen' => '143,188,143',
	'darkslateblue' => '72,61,139',
	'darkslategray' => '47,79,79',
	'darkslategrey' => '47,79,79',
	'darkturquoise' => '0,206,209',
	'darkviolet' => '148,0,211',
	'deeppink' => '255,20,147',
	'deepskyblue' => '0,191,255',
	'dimgray' => '105,105,105',
	'dimgrey' => '105,105,105',
	'dodgerblue' => '30,144,255',
	'firebrick' => '178,34,34',
	'floralwhite' => '255,250,240',
	'forestgreen' => '34,139,34',
	'fuchsia' => '255,0,255',
	'gainsboro' => '220,220,220',
	'ghostwhite' => '248,248,255',
	'gold' => '255,215,0',
	'goldenrod' => '218,165,32',
	'gray' => '128,128,128',
	'green' => '0,128,0',
	'greenyellow' => '173,255,47',
	'grey' => '128,128,128',
	'honeydew' => '240,255,240',
	'hotpink' => '255,105,180',
	'indianred' => '205,92,92',
	'indigo' => '75,0,130',
	'ivory' => '255,255,240',
	'khaki' => '240,230,140',
	'lavender' => '230,230,250',
	'lavenderblush' => '255,240,245',
	'lawngreen' => '124,252,0',
	'lemonchiffon' => '255,250,205',
	'lightblue' => '173,216,230',
	'lightcoral' => '240,128,128',
	'lightcyan' => '224,255,255',
	'lightgoldenrodyellow' => '250,250,210',
	'lightgray' => '211,211,211',
	'lightgreen' => '144,238,144',
	'lightgrey' => '211,211,211',
	'lightpink' => '255,182,193',
	'lightsalmon' => '255,160,122',
	'lightseagreen' => '32,178,170',
	'lightskyblue' => '135,206,250',
	'lightslategray' => '119,136,153',
	'lightslategrey' => '119,136,153',
	'lightsteelblue' => '176,196,222',
	'lightyellow' => '255,255,224',
	'lime' => '0,255,0',
	'limegreen' => '50,205,50',
	'linen' => '250,240,230',
	'magenta' => '255,0,255',
	'maroon' => '128,0,0',
	'mediumaquamarine' => '102,205,170',
	'mediumblue' => '0,0,205',
	'mediumorchid' => '186,85,211',
	'mediumpurple' => '147,112,219',
	'mediumseagreen' => '60,179,113',
	'mediumslateblue' => '123,104,238',
	'mediumspringgreen' => '0,250,154',
	'mediumturquoise' => '72,209,204',
	'mediumvioletred' => '199,21,133',
	'midnightblue' => '25,25,112',
	'mintcream' => '245,255,250',
	'mistyrose' => '255,228,225',
	'moccasin' => '255,228,181',
	'navajowhite' => '255,222,173',
	'navy' => '0,0,128',
	'oldlace' => '253,245,230',
	'olive' => '128,128,0',
	'olivedrab' => '107,142,35',
	'orange' => '255,165,0',
	'orangered' => '255,69,0',
	'orchid' => '218,112,214',
	'palegoldenrod' => '238,232,170',
	'palegreen' => '152,251,152',
	'paleturquoise' => '175,238,238',
	'palevioletred' => '219,112,147',
	'papayawhip' => '255,239,213',
	'peachpuff' => '255,218,185',
	'peru' => '205,133,63',
	'pink' => '255,192,203',
	'plum' => '221,160,221',
	'powderblue' => '176,224,230',
	'purple' => '128,0,128',
	'red' => '255,0,0',
	'rosybrown' => '188,143,143',
	'royalblue' => '65,105,225',
	'saddlebrown' => '139,69,19',
	'salmon' => '250,128,114',
	'sandybrown' => '244,164,96',
	'seagreen' => '46,139,87',
	'seashell' => '255,245,238',
	'sienna' => '160,82,45',
	'silver' => '192,192,192',
	'skyblue' => '135,206,235',
	'slateblue' => '106,90,205',
	'slategray' => '112,128,144',
	'slategrey' => '112,128,144',
	'snow' => '255,250,250',
	'springgreen' => '0,255,127',
	'steelblue' => '70,130,180',
	'tan' => '210,180,140',
	'teal' => '0,128,128',
	'thistle' => '216,191,216',
	'tomato' => '255,99,71',
	'turquoise' => '64,224,208',
	'violet' => '238,130,238',
	'wheat' => '245,222,179',
	'white' => '255,255,255',
	'whitesmoke' => '245,245,245',
	'yellow' => '255,255,0',
	'yellowgreen' => '154,205,50'
	);
}

// responsible for taking a string of LESS code and converting it into a
// syntax tree
class lessc_parser {
	static protected $nextBlockId = 0; // used to uniquely identify blocks
	
	static protected $precedence = array(
	'=<' => 0,
	'>=' => 0,
	'=' => 0,
	'<' => 0,
	'>' => 0,
	
	'+' => 1,
	'-' => 1,
	'*' => 2,
	'/' => 2,
	'%' => 2,
	);
	
	static protected $whitePattern;
	static protected $commentMulti;
	
	static protected $commentSingle = "//";
	static protected $commentMultiLeft = "/*";
	static protected $commentMultiRight = "*/";
	
	// regex string to match any of the operators
	static protected $operatorString;
	
	// these properties will supress division unless it's inside parenthases
	static protected $supressDivisionProps =
	array('/border-radius$/i', '/^font$/i');
	
	protected $blockDirectives = array("font-face", "keyframes", "page", "-moz-document");
	protected $lineDirectives = array("charset");
	
	/**
	 * if we are in parens we can be more liberal with whitespace around
	 * operators because it must evaluate to a single value and thus is less
	 * ambiguous.
		*
	 * Consider:
	 *     property1: 10 -5; // is two numbers, 10 and -5
	 *     property2: (10 -5); // should evaluate to 5
	 */
	protected $inParens = false;
	
	// caches preg escaped literals
	static protected $literalCache = array();
	
	public function __construct($lessc, $sourceName = null) {
		$this->eatWhiteDefault = true;
		// reference to less needed for vPrefix, mPrefix, and parentSelector
		$this->lessc = $lessc;
		
		$this->sourceName = $sourceName; // name used for error messages
		
		$this->writeComments = false;
		
		if (!self::$operatorString) {
			self::$operatorString =
			'('.implode('|', array_map(array('lessc', 'preg_quote'),
			array_keys(self::$precedence))).')';
			
			$commentSingle = lessc::preg_quote(self::$commentSingle);
			$commentMultiLeft = lessc::preg_quote(self::$commentMultiLeft);
			$commentMultiRight = lessc::preg_quote(self::$commentMultiRight);
			
			self::$commentMulti = $commentMultiLeft.'.*?'.$commentMultiRight;
			self::$whitePattern = '/'.$commentSingle.'[^\n]*\s*|('.self::$commentMulti.')\s*|\s+/Ais';
		}
	}
	
	public function parse($buffer) {
		$this->count = 0;
		$this->line = 1;
		
		$this->env = null; // block stack
		$this->buffer = $this->writeComments ? $buffer : $this->removeComments($buffer);
		$this->pushSpecialBlock("root");
		$this->eatWhiteDefault = true;
		$this->seenComments = array();
		
		// trim whitespace on head
		// if (preg_match('/^\s+/', $this->buffer, $m)) {
		// 	$this->line += substr_count($m[0], "\n");
		// 	$this->buffer = ltrim($this->buffer);
		// }
		$this->whitespace();
		
		// parse the entire file
		$lastCount = $this->count;
		while (false !== $this->parseChunk());
		
		if ($this->count != strlen($this->buffer))
		$this->throwError();
		
		// TODO report where the block was opened
		if (!is_null($this->env->parent))
		throw new exception('parse error: unclosed block');
		
		return $this->env;
	}
	
	/**
	 * Parse a single chunk off the head of the buffer and append it to the
	 * current parse environment.
	 * Returns false when the buffer is empty, or when there is an error.
		*
	 * This function is called repeatedly until the entire document is
	 * parsed.
		*
	 * This parser is most similar to a recursive descent parser. Single
	 * functions represent discrete grammatical rules for the language, and
	 * they are able to capture the text that represents those rules.
		*
	 * Consider the function lessc::keyword(). (all parse functions are
	 * structured the same)
		*
	 * The function takes a single reference argument. When calling the
	 * function it will attempt to match a keyword on the head of the buffer.
	 * If it is successful, it will place the keyword in the referenced
	 * argument, advance the position in the buffer, and return true. If it
	 * fails then it won't advance the buffer and it will return false.
		*
	 * All of these parse functions are powered by lessc::match(), which behaves
	 * the same way, but takes a literal regular expression. Sometimes it is
	 * more convenient to use match instead of creating a new function.
		*
	 * Because of the format of the functions, to parse an entire string of
	 * grammatical rules, you can chain them together using &&.
		*
	 * But, if some of the rules in the chain succeed before one fails, then
	 * the buffer position will be left at an invalid state. In order to
	 * avoid this, lessc::seek() is used to remember and set buffer positions.
		*
	 * Before parsing a chain, use $s = $this->seek() to remember the current
	 * position into $s. Then if a chain fails, use $this->seek($s) to
	 * go back where we started.
	 */
	protected function parseChunk() {
		if (empty($this->buffer)) return false;
		$s = $this->seek();
		
		// setting a property
		if ($this->keyword($key) && $this->assign() &&
		$this->propertyValue($value, $key) && $this->end())
		{
			$this->append(array('assign', $key, $value), $s);
			return true;
		} else {
			$this->seek($s);
		}
		
		
		// look for special css blocks
		if ($this->literal('@', false)) {
			$this->count--;
			
			// media
			if ($this->literal('@media')) {
				if (($this->mediaQueryList($mediaQueries) || true)
				&& $this->literal('{'))
				{
					$media = $this->pushSpecialBlock("media");
					$media->queries = is_null($mediaQueries) ? array() : $mediaQueries;
					return true;
				} else {
					$this->seek($s);
					return false;
				}
			}
			
			if ($this->literal("@", false) && $this->keyword($dirName)) {
				if ($this->isDirective($dirName, $this->blockDirectives)) {
					if (($this->openString("{", $dirValue, null, array(";")) || true) &&
					$this->literal("{"))
					{
						$dir = $this->pushSpecialBlock("directive");
						$dir->name = $dirName;
						if (isset($dirValue)) $dir->value = $dirValue;
						return true;
					}
				} elseif ($this->isDirective($dirName, $this->lineDirectives)) {
					if ($this->propertyValue($dirValue) && $this->end()) {
						$this->append(array("directive", $dirName, $dirValue));
						return true;
					}
				}
			}
			
			$this->seek($s);
		}
		
		// setting a variable
		if ($this->variable($var) && $this->assign() &&
		$this->propertyValue($value) && $this->end())
		{
			$this->append(array('assign', $var, $value), $s);
			return true;
		} else {
			$this->seek($s);
		}
		
		if ($this->import($importValue)) {
			$this->append($importValue, $s);
			return true;
		}
		
		// opening parametric mixin
		if ($this->tag($tag, true) && $this->argumentDef($args, $isVararg) &&
		($this->guards($guards) || true) &&
		$this->literal('{'))
		{
			$block = $this->pushBlock($this->fixTags(array($tag)));
			$block->args = $args;
			$block->isVararg = $isVararg;
			if (!empty($guards)) $block->guards = $guards;
			return true;
		} else {
			$this->seek($s);
		}
		
		// opening a simple block
		if ($this->tags($tags) && $this->literal('{')) {
			$tags = $this->fixTags($tags);
			$this->pushBlock($tags);
			return true;
		} else {
			$this->seek($s);
		}
		
		// closing a block
		if ($this->literal('}', false)) {
			try {
				$block = $this->pop();
				} catch (exception $e) {
				$this->seek($s);
				$this->throwError($e->getMessage());
			}
			
			$hidden = false;
			if (is_null($block->type)) {
				$hidden = true;
				if (!isset($block->args)) {
					foreach ($block->tags as $tag) {
						if (!is_string($tag) || $tag{0} != $this->lessc->mPrefix) {
							$hidden = false;
							break;
						}
					}
				}
				
				foreach ($block->tags as $tag) {
					if (is_string($tag)) {
						$this->env->children[$tag][] = $block;
					}
				}
			}
			
			if (!$hidden) {
				$this->append(array('block', $block), $s);
			}
			
			// this is done here so comments aren't bundled into he block that
			// was just closed
			$this->whitespace();
			return true;
		}
		
		// mixin
		if ($this->mixinTags($tags) &&
		($this->argumentValues($argv) || true) &&
		($this->keyword($suffix) || true) && $this->end())
		{
			$tags = $this->fixTags($tags);
			$this->append(array('mixin', $tags, $argv, $suffix), $s);
			return true;
		} else {
			$this->seek($s);
		}
		
		// spare ;
		if ($this->literal(';')) return true;
		
		return false; // got nothing, throw error
	}
	
	protected function isDirective($dirname, $directives) {
		// TODO: cache pattern in parser
		$pattern = implode("|",
		array_map(array("lessc", "preg_quote"), $directives));
		$pattern = '/^(-[a-z-]+-)?(' . $pattern . ')$/i';
		
		return preg_match($pattern, $dirname);
	}
	
	protected function fixTags($tags) {
		// move @ tags out of variable namespace
		foreach ($tags as &$tag) {
			if ($tag{0} == $this->lessc->vPrefix)
			$tag[0] = $this->lessc->mPrefix;
		}
		return $tags;
	}
	
	// a list of expressions
	protected function expressionList(&$exps) {
		$values = array();
		
		while ($this->expression($exp)) {
			$values[] = $exp;
		}
		
		if (count($values) == 0) return false;
		
		$exps = lessc::compressList($values, ' ');
		return true;
	}
	
	/**
	 * Attempt to consume an expression.
	 * @link http://en.wikipedia.org/wiki/Operator-precedence_parser#Pseudo-code
	 */
	protected function expression(&$out) {
		if ($this->value($lhs)) {
			$out = $this->expHelper($lhs, 0);
			
			// look for / shorthand
			if (!empty($this->env->supressedDivision)) {
				unset($this->env->supressedDivision);
				$s = $this->seek();
				if ($this->literal("/") && $this->value($rhs)) {
					$out = array("list", "",
					array($out, array("keyword", "/"), $rhs));
				} else {
					$this->seek($s);
				}
			}
			
			return true;
		}
		return false;
	}
	
	/**
	 * recursively parse infix equation with $lhs at precedence $minP
	 */
	protected function expHelper($lhs, $minP) {
		$this->inExp = true;
		$ss = $this->seek();
		
		while (true) {
			$whiteBefore = isset($this->buffer[$this->count - 1]) &&
			ctype_space($this->buffer[$this->count - 1]);
			
			// If there is whitespace before the operator, then we require
			// whitespace after the operator for it to be an expression
			$needWhite = $whiteBefore && !$this->inParens;
			
			if ($this->match(self::$operatorString.($needWhite ? '\s' : ''), $m) && self::$precedence[$m[1]] >= $minP) {
				if (!$this->inParens && isset($this->env->currentProperty) && $m[1] == "/" && empty($this->env->supressedDivision)) {
					foreach (self::$supressDivisionProps as $pattern) {
						if (preg_match($pattern, $this->env->currentProperty)) {
							$this->env->supressedDivision = true;
							break 2;
						}
					}
				}
				
				
				$whiteAfter = isset($this->buffer[$this->count - 1]) &&
				ctype_space($this->buffer[$this->count - 1]);
				
				if (!$this->value($rhs)) break;
				
				// peek for next operator to see what to do with rhs
				if ($this->peek(self::$operatorString, $next) && self::$precedence[$next[1]] > self::$precedence[$m[1]]) {
					$rhs = $this->expHelper($rhs, self::$precedence[$next[1]]);
				}
				
				$lhs = array('expression', $m[1], $lhs, $rhs, $whiteBefore, $whiteAfter);
				$ss = $this->seek();
				
				continue;
			}
			
			break;
		}
		
		$this->seek($ss);
		
		return $lhs;
	}
	
	// consume a list of values for a property
	public function propertyValue(&$value, $keyName = null) {
		$values = array();
		
		if ($keyName !== null) $this->env->currentProperty = $keyName;
		
		$s = null;
		while ($this->expressionList($v)) {
			$values[] = $v;
			$s = $this->seek();
			if (!$this->literal(',')) break;
		}
		
		if ($s) $this->seek($s);
		
		if ($keyName !== null) unset($this->env->currentProperty);
		
		if (count($values) == 0) return false;
		
		$value = lessc::compressList($values, ', ');
		return true;
	}
	
	protected function parenValue(&$out) {
		$s = $this->seek();
		
		// speed shortcut
		if (isset($this->buffer[$this->count]) && $this->buffer[$this->count] != "(") {
			return false;
		}
		
		$inParens = $this->inParens;
		if ($this->literal("(") &&
		($this->inParens = true) && $this->expression($exp) &&
		$this->literal(")"))
		{
			$out = $exp;
			$this->inParens = $inParens;
			return true;
		} else {
			$this->inParens = $inParens;
			$this->seek($s);
		}
		
		return false;
	}
	
	// a single value
	protected function value(&$value) {
		$s = $this->seek();
		
		// speed shortcut
		if (isset($this->buffer[$this->count]) && $this->buffer[$this->count] == "-") {
			// negation
			if ($this->literal("-", false) &&
			(($this->variable($inner) && $inner = array("variable", $inner)) ||
			$this->unit($inner) ||
			$this->parenValue($inner)))
			{
				$value = array("unary", "-", $inner);
				return true;
			} else {
				$this->seek($s);
			}
		}
		
		if ($this->parenValue($value)) return true;
		if ($this->unit($value)) return true;
		if ($this->color($value)) return true;
		if ($this->func($value)) return true;
		if ($this->string($value)) return true;
		
		if ($this->keyword($word)) {
			$value = array('keyword', $word);
			return true;
		}
		
		// try a variable
		if ($this->variable($var)) {
			$value = array('variable', $var);
			return true;
		}
		
		// unquote string (should this work on any type?
		if ($this->literal("~") && $this->string($str)) {
			$value = array("escape", $str);
			return true;
		} else {
			$this->seek($s);
		}
		
		// css hack: \0
		if ($this->literal('\\') && $this->match('([0-9]+)', $m)) {
			$value = array('keyword', '\\'.$m[1]);
			return true;
		} else {
			$this->seek($s);
		}
		
		return false;
	}
	
	// an import statement
	protected function import(&$out) {
		$s = $this->seek();
		if (!$this->literal('@import')) return false;
		
		// @import "something.css" media;
		// @import url("something.css") media;
		// @import url(something.css) media;
		
		if ($this->propertyValue($value)) {
			$out = array("import", $value);
			return true;
		}
	}
	
	protected function mediaQueryList(&$out) {
		if ($this->genericList($list, "mediaQuery", ",", false)) {
			$out = $list[2];
			return true;
		}
		return false;
	}
	
	protected function mediaQuery(&$out) {
		$s = $this->seek();
		
		$expressions = null;
		$parts = array();
		
		if (($this->literal("only") && ($only = true) || $this->literal("not") && ($not = true) || true) && $this->keyword($mediaType)) {
			$prop = array("mediaType");
			if (isset($only)) $prop[] = "only";
			if (isset($not)) $prop[] = "not";
			$prop[] = $mediaType;
			$parts[] = $prop;
		} else {
			$this->seek($s);
		}
		
		
		if (!empty($mediaType) && !$this->literal("and")) {
			// ~
		} else {
			$this->genericList($expressions, "mediaExpression", "and", false);
			if (is_array($expressions)) $parts = array_merge($parts, $expressions[2]);
		}
		
		if (count($parts) == 0) {
			$this->seek($s);
			return false;
		}
		
		$out = $parts;
		return true;
	}
	
	protected function mediaExpression(&$out) {
		$s = $this->seek();
		$value = null;
		if ($this->literal("(") &&
		$this->keyword($feature) &&
		($this->literal(":") && $this->expression($value) || true) &&
		$this->literal(")"))
		{
			$out = array("mediaExp", $feature);
			if ($value) $out[] = $value;
			return true;
		}
		
		$this->seek($s);
		return false;
	}
	
	// an unbounded string stopped by $end
	protected function openString($end, &$out, $nestingOpen=null, $rejectStrs = null) {
		$oldWhite = $this->eatWhiteDefault;
		$this->eatWhiteDefault = false;
		
		$stop = array("'", '"', "@{", $end);
		$stop = array_map(array("lessc", "preg_quote"), $stop);
		// $stop[] = self::$commentMulti;
		
		if (!is_null($rejectStrs)) {
			$stop = array_merge($stop, $rejectStrs);
		}
		
		$patt = '(.*?)('.implode("|", $stop).')';
		
		$nestingLevel = 0;
		
		$content = array();
		while ($this->match($patt, $m, false)) {
			if (!empty($m[1])) {
				$content[] = $m[1];
				if ($nestingOpen) {
					$nestingLevel += substr_count($m[1], $nestingOpen);
				}
			}
			
			$tok = $m[2];
			
			$this->count-= strlen($tok);
			if ($tok == $end) {
				if ($nestingLevel == 0) {
					break;
				} else {
					$nestingLevel--;
				}
			}
			
			if (($tok == "'" || $tok == '"') && $this->string($str)) {
				$content[] = $str;
				continue;
			}
			
			if ($tok == "@{" && $this->interpolation($inter)) {
				$content[] = $inter;
				continue;
			}
			
			if (in_array($tok, $rejectStrs)) {
				$count = null;
				break;
			}
			
			
			$content[] = $tok;
			$this->count+= strlen($tok);
		}
		
		$this->eatWhiteDefault = $oldWhite;
		
		if (count($content) == 0) return false;
		
		// trim the end
		if (is_string(end($content))) {
			$content[count($content) - 1] = rtrim(end($content));
		}
		
		$out = array("string", "", $content);
		return true;
	}
	
	protected function string(&$out) {
		$s = $this->seek();
		if ($this->literal('"', false)) {
			$delim = '"';
		} elseif ($this->literal("'", false)) {
			$delim = "'";
		} else {
			return false;
		}
		
		$content = array();
		
		// look for either ending delim , escape, or string interpolation
		$patt = '([^\n]*?)(@\{|\\\\|' .
		lessc::preg_quote($delim).')';
		
		$oldWhite = $this->eatWhiteDefault;
		$this->eatWhiteDefault = false;
		
		while ($this->match($patt, $m, false)) {
			$content[] = $m[1];
			if ($m[2] == "@{") {
				$this->count -= strlen($m[2]);
				if ($this->interpolation($inter, false)) {
					$content[] = $inter;
				} else {
					$this->count += strlen($m[2]);
					$content[] = "@{"; // ignore it
				}
			} elseif ($m[2] == '\\') {
				$content[] = $m[2];
				if ($this->literal($delim, false)) {
					$content[] = $delim;
				}
			} else {
				$this->count -= strlen($delim);
				break; // delim
			}
		}
		
		$this->eatWhiteDefault = $oldWhite;
		
		if ($this->literal($delim)) {
			$out = array("string", $delim, $content);
			return true;
		}
		
		$this->seek($s);
		return false;
	}
	
	protected function interpolation(&$out) {
		$oldWhite = $this->eatWhiteDefault;
		$this->eatWhiteDefault = true;
		
		$s = $this->seek();
		if ($this->literal("@{") &&
		$this->keyword($var) &&
		$this->literal("}", false))
		{
			$out = array("variable", $this->lessc->vPrefix . $var);
			$this->eatWhiteDefault = $oldWhite;
			if ($this->eatWhiteDefault) $this->whitespace();
			return true;
		}
		
		$this->eatWhiteDefault = $oldWhite;
		$this->seek($s);
		return false;
	}
	
	protected function unit(&$unit) {
		// speed shortcut
		if (isset($this->buffer[$this->count])) {
			$char = $this->buffer[$this->count];
			if (!ctype_digit($char) && $char != ".") return false;
		}
		
		if ($this->match('([0-9]+(?:\.[0-9]*)?|\.[0-9]+)([%a-zA-Z]+)?', $m)) {
			$unit = array("number", $m[1], empty($m[2]) ? "" : $m[2]);
			return true;
		}
		return false;
	}
	
	// a # color
	protected function color(&$out) {
		if ($this->match('(#(?:[0-9a-f]{8}|[0-9a-f]{6}|[0-9a-f]{3}))', $m)) {
			if (strlen($m[1]) > 7) {
				$out = array("string", "", array($m[1]));
			} else {
				$out = array("raw_color", $m[1]);
			}
			return true;
		}
		
		return false;
	}
	
	// consume a list of property values delimited by ; and wrapped in ()
	protected function argumentValues(&$args, $delim = ',') {
		$s = $this->seek();
		if (!$this->literal('(')) return false;
		
		$values = array();
		while (true) {
			if ($this->expressionList($value)) $values[] = $value;
			if (!$this->literal($delim)) break;
			else {
				if ($value == null) $values[] = null;
				$value = null;
			}
		}
		
		if (!$this->literal(')')) {
			$this->seek($s);
			return false;
		}
		
		$args = $values;
		return true;
	}
	
	// consume an argument definition list surrounded by ()
	// each argument is a variable name with optional value
	// or at the end a ... or a variable named followed by ...
	protected function argumentDef(&$args, &$isVararg, $delim = ',') {
		$s = $this->seek();
		if (!$this->literal('(')) return false;
		
		$values = array();
		
		$isVararg = false;
		while (true) {
			if ($this->literal("...")) {
				$isVararg = true;
				break;
			}
			
			if ($this->variable($vname)) {
				$arg = array("arg", $vname);
				$ss = $this->seek();
				if ($this->assign() && $this->expressionList($value)) {
					$arg[] = $value;
				} else {
					$this->seek($ss);
					if ($this->literal("...")) {
						$arg[0] = "rest";
						$isVararg = true;
					}
				}
				$values[] = $arg;
				if ($isVararg) break;
				continue;
			}
			
			if ($this->value($literal)) {
				$values[] = array("lit", $literal);
			}
			
			if (!$this->literal($delim)) break;
		}
		
		if (!$this->literal(')')) {
			$this->seek($s);
			return false;
		}
		
		$args = $values;
		
		return true;
	}
	
	// consume a list of tags
	// this accepts a hanging delimiter
	protected function tags(&$tags, $simple = false, $delim = ',') {
		$tags = array();
		while ($this->tag($tt, $simple)) {
			$tags[] = $tt;
			if (!$this->literal($delim)) break;
		}
		if (count($tags) == 0) return false;
		
		return true;
	}
	
	// list of tags of specifying mixin path
	// optionally separated by > (lazy, accepts extra >)
	protected function mixinTags(&$tags) {
		$s = $this->seek();
		$tags = array();
		while ($this->tag($tt, true)) {
			$tags[] = $tt;
			$this->literal(">");
		}
		
		if (count($tags) == 0) return false;
		
		return true;
	}
	
	// a bracketed value (contained within in a tag definition)
	protected function tagBracket(&$value) {
		// speed shortcut
		if (isset($this->buffer[$this->count]) && $this->buffer[$this->count] != "[") {
			return false;
		}
		
		$s = $this->seek();
		if ($this->literal('[') && $this->to(']', $c, true) && $this->literal(']', false)) {
			$value = '['.$c.']';
			// whitespace?
			if ($this->whitespace()) $value .= " ";
			
			// escape parent selector, (yuck)
			$value = str_replace($this->lessc->parentSelector, "$&$", $value);
			return true;
		}
		
		$this->seek($s);
		return false;
	}
	
	protected function tagExpression(&$value) {
		$s = $this->seek();
		if ($this->literal("(") && $this->expression($exp) && $this->literal(")")) {
			$value = array('exp', $exp);
			return true;
		}
		
		$this->seek($s);
		return false;
	}
	
	// a single tag
	protected function tag(&$tag, $simple = false) {
		if ($simple)
		$chars = '^,:;{}\][>\(\) "\'';
		else
		$chars = '^,;{}["\'';
		
		if (!$simple && $this->tagExpression($tag)) {
			return true;
		}
		
		$tag = '';
		while ($this->tagBracket($first)) $tag .= $first;
		
		while (true) {
			if ($this->match('(['.$chars.'0-9]['.$chars.']*)', $m)) {
				$tag .= $m[1];
				if ($simple) break;
				
				while ($this->tagBracket($brack)) $tag .= $brack;
				continue;
			} elseif ($this->unit($unit)) { // for keyframes
				$tag .= $unit[1] . $unit[2];
				continue;
			}
			break;
		}
		
		
		$tag = trim($tag);
		if ($tag == '') return false;
		
		return true;
	}
	
	// a css function
	protected function func(&$func) {
		$s = $this->seek();
		
		if ($this->match('(%|[\w\-_][\w\-_:\.]+|[\w_])', $m) && $this->literal('(')) {
			$fname = $m[1];
			
			$sPreArgs = $this->seek();
			
			$args = array();
			while (true) {
				$ss = $this->seek();
				// this ugly nonsense is for ie filter properties
				if ($this->keyword($name) && $this->literal('=') && $this->expressionList($value)) {
					$args[] = array("string", "", array($name, "=", $value));
				} else {
					$this->seek($ss);
					if ($this->expressionList($value)) {
						$args[] = $value;
					}
				}
				
				if (!$this->literal(',')) break;
			}
			$args = array('list', ',', $args);
			
			if ($this->literal(')')) {
				$func = array('function', $fname, $args);
				return true;
			} elseif ($fname == 'url') {
				// couldn't parse and in url? treat as string
				$this->seek($sPreArgs);
				if ($this->openString(")", $string) && $this->literal(")")) {
					$func = array('function', $fname, $string);
					return true;
				}
			}
		}
		
		$this->seek($s);
		return false;
	}
	
	// consume a less variable
	protected function variable(&$name) {
		$s = $this->seek();
		if ($this->literal($this->lessc->vPrefix, false) &&
		($this->variable($sub) || $this->keyword($name)))
		{
			if (!empty($sub)) {
				$name = array('variable', $sub);
			} else {
				$name = $this->lessc->vPrefix.$name;
			}
			return true;
		}
		
		$name = null;
		$this->seek($s);
		return false;
	}
	
	/**
	 * Consume an assignment operator
	 * Can optionally take a name that will be set to the current property name
	 */
	protected function assign($name = null) {
		if ($name) $this->currentProperty = $name;
		return $this->literal(':') || $this->literal('=');
	}
	
	// consume a keyword
	protected function keyword(&$word) {
		if ($this->match('([\w_\-\*!"][\w\-_"]*)', $m)) {
			$word = $m[1];
			return true;
		}
		return false;
	}
	
	// consume an end of statement delimiter
	protected function end() {
		if ($this->literal(';')) {
			return true;
		} elseif ($this->count == strlen($this->buffer) || $this->buffer{$this->count} == '}') {
			// if there is end of file or a closing block next then we don't need a ;
			return true;
		}
		return false;
	}
	
	protected function guards(&$guards) {
		$s = $this->seek();
		
		if (!$this->literal("when")) {
			$this->seek($s);
			return false;
		}
		
		$guards = array();
		
		while ($this->guardGroup($g)) {
			$guards[] = $g;
			if (!$this->literal(",")) break;
		}
		
		if (count($guards) == 0) {
			$guards = null;
			$this->seek($s);
			return false;
		}
		
		return true;
	}
	
	// a bunch of guards that are and'd together
	// TODO rename to guardGroup
	protected function guardGroup(&$guardGroup) {
		$s = $this->seek();
		$guardGroup = array();
		while ($this->guard($guard)) {
			$guardGroup[] = $guard;
			if (!$this->literal("and")) break;
		}
		
		if (count($guardGroup) == 0) {
			$guardGroup = null;
			$this->seek($s);
			return false;
		}
		
		return true;
	}
	
	protected function guard(&$guard) {
		$s = $this->seek();
		$negate = $this->literal("not");
		
		if ($this->literal("(") && $this->expression($exp) && $this->literal(")")) {
			$guard = $exp;
			if ($negate) $guard = array("negate", $guard);
			return true;
		}
		
		$this->seek($s);
		return false;
	}
	
	/* raw parsing functions */
	
	protected function literal($what, $eatWhitespace = null) {
		if ($eatWhitespace === null) $eatWhitespace = $this->eatWhiteDefault;
		
		// shortcut on single letter
		if (!isset($what[1]) && isset($this->buffer[$this->count])) {
			if ($this->buffer[$this->count] == $what) {
				if (!$eatWhitespace) {
					$this->count++;
					return true;
				}
				// goes below...
			} else {
				return false;
			}
		}
		
		if (!isset(self::$literalCache[$what])) {
			self::$literalCache[$what] = lessc::preg_quote($what);
		}
		
		return $this->match(self::$literalCache[$what], $m, $eatWhitespace);
	}
	
	protected function genericList(&$out, $parseItem, $delim="", $flatten=true) {
		$s = $this->seek();
		$items = array();
		while ($this->$parseItem($value)) {
			$items[] = $value;
			if ($delim) {
				if (!$this->literal($delim)) break;
			}
		}
		
		if (count($items) == 0) {
			$this->seek($s);
			return false;
		}
		
		if ($flatten && count($items) == 1) {
			$out = $items[0];
		} else {
			$out = array("list", $delim, $items);
		}
		
		return true;
	}
	
	
	// advance counter to next occurrence of $what
	// $until - don't include $what in advance
	// $allowNewline, if string, will be used as valid char set
	protected function to($what, &$out, $until = false, $allowNewline = false) {
		if (is_string($allowNewline)) {
			$validChars = $allowNewline;
		} else {
			$validChars = $allowNewline ? "." : "[^\n]";
		}
		if (!$this->match('('.$validChars.'*?)'.lessc::preg_quote($what), $m, !$until)) return false;
		if ($until) $this->count -= strlen($what); // give back $what
		$out = $m[1];
		return true;
	}
	
	// try to match something on head of buffer
	protected function match($regex, &$out, $eatWhitespace = null) {
		if ($eatWhitespace === null) $eatWhitespace = $this->eatWhiteDefault;
		
		$r = '/'.$regex.($eatWhitespace && !$this->writeComments ? '\s*' : '').'/Ais';
		if (preg_match($r, $this->buffer, $out, null, $this->count)) {
			$this->count += strlen($out[0]);
			if ($eatWhitespace && $this->writeComments) $this->whitespace();
			return true;
		}
		return false;
	}
	
	// match some whitespace
	protected function whitespace() {
		if ($this->writeComments) {
			$gotWhite = false;
			while (preg_match(self::$whitePattern, $this->buffer, $m, null, $this->count)) {
				if (isset($m[1]) && empty($this->commentsSeen[$this->count])) {
					$this->append(array("comment", $m[1]));
					$this->commentsSeen[$this->count] = true;
				}
				$this->count += strlen($m[0]);
				$gotWhite = true;
			}
			return $gotWhite;
		} else {
			$this->match("", $m);
			return strlen($m[0]) > 0;
		}
	}
	
	// match something without consuming it
	protected function peek($regex, &$out = null, $from=null) {
		if (is_null($from)) $from = $this->count;
		$r = '/'.$regex.'/Ais';
		$result = preg_match($r, $this->buffer, $out, null, $from);
		
		return $result;
	}
	
	// seek to a spot in the buffer or return where we are on no argument
	protected function seek($where = null) {
		if ($where === null) return $this->count;
		else $this->count = $where;
		return true;
	}
	
	/* misc functions */
	
	public function throwError($msg = "parse error", $count = null) {
		$count = is_null($count) ? $this->count : $count;
		
		$line = $this->line +
		substr_count(substr($this->buffer, 0, $count), "\n");
		
		if (!empty($this->sourceName)) {
			$loc = "$this->sourceName on line $line";
		} else {
			$loc = "line: $line";
		}
		
		// TODO this depends on $this->count
		if ($this->peek("(.*?)(\n|$)", $m, $count)) {
			throw new exception("$msg: failed at `$m[1]` $loc");
		} else {
			throw new exception("$msg: $loc");
		}
	}
	
	protected function pushBlock($selectors=null, $type=null) {
		$b = new stdclass;
		$b->parent = $this->env;
		
		$b->type = $type;
		$b->id = self::$nextBlockId++;
		
		$b->isVararg = false; // TODO: kill me from here
		$b->tags = $selectors;
		
		$b->props = array();
		$b->children = array();
		
		$this->env = $b;
		return $b;
	}
	
	// push a block that doesn't multiply tags
	protected function pushSpecialBlock($type) {
		return $this->pushBlock(null, $type);
	}
	
	// append a property to the current block
	protected function append($prop, $pos = null) {
		if ($pos !== null) $prop[-1] = $pos;
		$this->env->props[] = $prop;
	}
	
	// pop something off the stack
	protected function pop() {
		$old = $this->env;
		$this->env = $this->env->parent;
		return $old;
	}
	
	// remove comments from $text
	// todo: make it work for all functions, not just url
	protected function removeComments($text) {
		$look = array(
		'url(', '//', '/*', '"', "'"
		);
		
		$out = '';
		$min = null;
		while (true) {
			// find the next item
			foreach ($look as $token) {
				$pos = strpos($text, $token);
				if ($pos !== false) {
					if (!isset($min) || $pos < $min[1]) $min = array($token, $pos);
				}
			}
			
			if (is_null($min)) break;
			
			$count = $min[1];
			$skip = 0;
			$newlines = 0;
			switch ($min[0]) {
				case 'url(':
				if (preg_match('/url\(.*?\)/', $text, $m, 0, $count))
				$count += strlen($m[0]) - strlen($min[0]);
				break;
				case '"':
				case "'":
				if (preg_match('/'.$min[0].'.*?'.$min[0].'/', $text, $m, 0, $count))
				$count += strlen($m[0]) - 1;
				break;
				case '//':
				$skip = strpos($text, "\n", $count);
				if ($skip === false) $skip = strlen($text) - $count;
				else $skip -= $count;
				break;
				case '/*':
				if (preg_match('/\/\*.*?\*\//s', $text, $m, 0, $count)) {
					$skip = strlen($m[0]);
					$newlines = substr_count($m[0], "\n");
				}
				break;
			}
			
			if ($skip == 0) $count += strlen($min[0]);
			
			$out .= substr($text, 0, $count).str_repeat("\n", $newlines);
			$text = substr($text, $count + $skip);
			
			$min = null;
		}
		
		return $out.$text;
	}
	
}

class lessc_formatter_classic {
	public $indentChar = "  ";
	
	public $break = "\n";
	public $open = " {";
	public $close = "}";
	public $selectorSeparator = ", ";
	public $assignSeparator = ":";
	
	public $openSingle = " { ";
	public $closeSingle = " }";
	
	public $disableSingle = false;
	public $breakSelectors = false;
	
	public $compressColors = false;
	
	public function __construct() {
		$this->indentLevel = 0;
	}
	
	public function indentStr($n = 0) {
		return str_repeat($this->indentChar, max($this->indentLevel + $n, 0));
	}
	
	public function property($name, $value) {
		return $name . $this->assignSeparator . $value . ";";
	}
	
	protected function isEmpty($block) {
		if (empty($block->lines)) {
			foreach ($block->children as $child) {
				if (!$this->isEmpty($child)) return false;
			}
			
			return true;
		}
		return false;
	}
	
	public function block($block) {
		if ($this->isEmpty($block)) return;
		
		$inner = $pre = $this->indentStr();
		
		$isSingle = !$this->disableSingle &&
		is_null($block->type) && count($block->lines) == 1;
		
		if (!empty($block->selectors)) {
			$this->indentLevel++;
			
			if ($this->breakSelectors) {
				$selectorSeparator = $this->selectorSeparator . $this->break . $pre;
			} else {
				$selectorSeparator = $this->selectorSeparator;
			}
			
			echo $pre .
			implode($selectorSeparator, $block->selectors);
			if ($isSingle) {
				echo $this->openSingle;
				$inner = "";
			} else {
				echo $this->open . $this->break;
				$inner = $this->indentStr();
			}
			
		}
		
		if (!empty($block->lines)) {
			$glue = $this->break.$inner;
			echo $inner . implode($glue, $block->lines);
			if (!$isSingle && !empty($block->children)) {
				echo $this->break;
			}
		}
		
		foreach ($block->children as $child) {
			$this->block($child);
		}
		
		if (!empty($block->selectors)) {
			if (!$isSingle && empty($block->children)) echo $this->break;
			
			if ($isSingle) {
				echo $this->closeSingle . $this->break;
			} else {
				echo $pre . $this->close . $this->break;
			}
			
			$this->indentLevel--;
		}
	}
}

class lessc_formatter_compressed extends lessc_formatter_classic {
	public $disableSingle = true;
	public $open = "{";
	public $selectorSeparator = ",";
	public $assignSeparator = ":";
	public $break = "";
	public $compressColors = true;
	
	public function indentStr($n = 0) {
		return "";
	}
}

class lessc_formatter_lessjs extends lessc_formatter_classic {
	public $disableSingle = true;
	public $breakSelectors = true;
	public $assignSeparator = ": ";
	public $selectorSeparator = ",";
}

class scssc {
	static public $VERSION = "v0.0.4";
	
	static protected $operatorNames = array(
	'+' => "add",
	'-' => "sub",
	'*' => "mul",
	'/' => "div",
	'%' => "mod",
	
	'==' => "eq",
	'!=' => "neq",
	'<' => "lt",
	'>' => "gt",
	
	'<=' => "lte",
	'>=' => "gte",
	);
	
	static protected $namespaces = array(
	"special" => "%",
	"mixin" => "@",
	"function" => "^",
	);
	
	static protected $numberPrecision = 3;
	static protected $unitTable = array(
	"in" => array(
	"in" => 1,
	"pt" => 72,
	"pc" => 6,
	"cm" => 2.54,
	"mm" => 25.4,
	"px" => 96,
	)
	);
	
	static public $true = array("keyword", "true");
	static public $false = array("keyword", "false");
	
	static public $defaultValue = array("keyword", "");
	static public $selfSelector = array("self");
	
	protected $importPaths = array("");
	protected $importCache = array();
	
	protected $userFunctions = array();
	
	protected $formatter = "scss_formatter_nested";
	
	function compile($code, $name=null) {
		$this->indentLevel = -1;
		$this->commentsSeen = array();
		$this->extends = array();
		$this->extendsMap = array();
		
		$locale = setlocale(LC_NUMERIC, 0);
		setlocale(LC_NUMERIC, "C");
		
		$this->parsedFiles = array();
		$this->parser = new scss_parser($name);
		$tree = $this->parser->parse($code);
		
		$this->formatter = new $this->formatter();
		
		$this->env = null;
		$this->scope = null;
		
		$this->compileRoot($tree);
		$this->flattenSelectors($this->scope);
		
		ob_start();
		$this->formatter->block($this->scope);
		$out = ob_get_clean();
		
		setlocale(LC_NUMERIC, $locale);
		return $out;
	}
	
	protected function pushExtends($target, $origin) {
		$i = count($this->extends);
		$this->extends[] = array($target, $origin);
		
		foreach ($target as $part) {
			if (isset($this->extendsMap[$part])) {
				$this->extendsMap[$part][] = $i;
			} else {
				$this->extendsMap[$part] = array($i);
			}
		}
	}
	
	protected function makeOutputBlock($type, $selectors = null) {
		$out = new stdclass;
		$out->type = $type;
		$out->lines = array();
		$out->children = array();
		$out->parent = $this->scope;
		$out->selectors = $selectors;
		$out->depth = $this->env->depth;
		
		return $out;
	}
	
	protected function matchExtendsSingle($single, &$out_origin, &$out_rem) {
		$counts = array();
		foreach ($single as $part) {
			if (!is_string($part)) return false; // hmm
			
			if (isset($this->extendsMap[$part])) {
				foreach ($this->extendsMap[$part] as $idx) {
					$counts[$idx] =
					isset($counts[$idx]) ? $counts[$idx] + 1 : 1;
				}
			}
		}
		
		foreach ($counts as $idx => $count) {
			list($target, $origin) = $this->extends[$idx];
			// check count
			if ($count != count($target)) continue;
			// check if target is subset of single
			if (array_diff(array_intersect($single, $target), $target)) continue;
			
			$out_origin = $origin;
			$out_rem = array_diff($single, $target);
			
			return true;
		}
		
		return false;
	}
	
	protected function combineSelectorSingle($base, $other) {
		$tag = null;
		$out = array();
		
		foreach (array($base, $other) as $single) {
			foreach ($single as $part) {
				if (preg_match('/^[^.#:]/', $part)) {
					$tag = $part;
				} else {
					$out[] = $part;
				}
			}
		}
		
		if ($tag) {
			array_unshift($out, $tag);
		}
		
		return $out;
	}
	
	protected function matchExtends($selector, &$out, $from = 0, $initial=true) {
		foreach ($selector as $i => $part) {
			if ($i < $from) continue;
			
			if ($this->matchExtendsSingle($part, $origin, $rem)) {
				$before = array_slice($selector, 0, $i);
				$after = array_slice($selector, $i + 1);
				
				foreach ($origin as $new) {
					$new[count($new) - 1] =
					$this->combineSelectorSingle(end($new), $rem);
					
					$k = 0;
					// remove shared parts
					if ($initial) {
						foreach ($before as $k => $val) {
							if (!isset($new[$k]) || $val != $new[$k]) {
								break;
							}
						}
					}
					
					$result = array_merge(
					$before,
					$k > 0 ? array_slice($new, $k) : $new,
					$after);
					
					
					if ($result == $selector) continue;
					$out[] = $result;
					
					// recursively check for more matches
					$this->matchExtends($result, $out, $i, false);
					
					// selector sequence merging
					if (!empty($before) && count($new) > 1) {
						$result2 = array_merge(
						array_slice($new, 0, -1),
						$k > 0 ? array_slice($before, $k) : $before,
						array_slice($new, -1),
						$after);
						
						$out[] = $result2;
					}
				}
			}
		}
	}
	
	protected function flattenSelectors($block) {
		if ($block->selectors) {
			$selectors = array();
			foreach ($block->selectors as $s) {
				$selectors[] = $s;
				if (!is_array($s)) continue;
				// check extends
				if (!empty($this->extendsMap)) {
					$this->matchExtends($s, $selectors);
				}
			}
			
			$selectors = array_map(array($this, "compileSelector"), $selectors);
			$block->selectors = $selectors;
		}
		
		foreach ($block->children as $child) {
			$this->flattenSelectors($child);
		}
	}
	
	protected function compileRoot($rootBlock) {
		$this->pushEnv($rootBlock);
		$this->scope = $this->makeOutputBlock("root");
		$this->compileChildren($rootBlock->children, $this->scope);
		$this->popEnv();
	}
	
	protected function compileMedia($media) {
		$this->pushEnv($media);
		$parentScope = $this->mediaParent($this->scope);
		
		$this->scope = $this->makeOutputBlock("media", array(
		$this->compileMediaQuery($this->multiplyMedia($this->env)))
		);
		
		$parentScope->children[] = $this->scope;
		
		$this->compileChildren($media->children, $this->scope);
		
		$this->scope = $this->scope->parent;
		$this->popEnv();
	}
	
	protected function mediaParent($scope) {
		while (!empty($scope->parent)) {
			if (!empty($scope->type) && $scope->type != "media") {
				break;
			}
			$scope = $scope->parent;
		}
		
		return $scope;
	}
	
	// TODO refactor compileNestedBlock and compileMedia into same thing
	protected function compileNestedBlock($block, $selectors) {
		$this->pushEnv($block);
		
		$this->scope = $this->makeOutputBlock($block->type, $selectors);
		$this->scope->parent->children[] = $this->scope;
		$this->compileChildren($block->children, $this->scope);
		
		$this->scope = $this->scope->parent;
		$this->popEnv();
	}
	
	protected function compileBlock($block) {
		$env = $this->pushEnv($block);
		
		$env->selectors =
		array_map(array($this, "evalSelector"), $block->selectors);
		
		$out = $this->makeOutputBlock(null, $this->multiplySelectors($env));
		$this->scope->children[] = $out;
		$this->compileChildren($block->children, $out);
		
		$this->popEnv();
	}
	
	// joins together .classes and #ids
	protected function flattenSelectorSingle($single) {
		$joined = array();
		foreach ($single as $part) {
			if (empty($joined) ||
			!is_string($part) ||
			preg_match('/[.:#]/', $part))
			{
				$joined[] = $part;
				continue;
			}
			
			if (is_array(end($joined))) {
				$joined[] = $part;
			} else {
				$joined[count($joined) - 1] .= $part;
			}
		}
		
		return $joined;
	}
	
	// replaces all the interpolates
	protected function evalSelector($selector) {
		return array_map(array($this, "evalSelectorPart"), $selector);
	}
	
	protected function evalSelectorPart($piece) {
		foreach ($piece as &$p) {
			if (!is_array($p)) continue;
			
			switch ($p[0]) {
				case "interpolate":
				$p = $this->compileValue($p);
				break;
			}
		}
		
		return $this->flattenSelectorSingle($piece);
	}
	
	// compiles to string
	// self(&) should have been replaced by now
	protected function compileSelector($selector) {
		if (!is_array($selector)) return $selector; // media and the like
		
		return implode(" ", array_map(
		array($this, "compileSelectorPart"), $selector));
	}
	
	protected function compileSelectorPart($piece) {
		foreach ($piece as &$p) {
			if (!is_array($p)) continue;
			
			switch ($p[0]) {
				case "self":
				$p = "&";
				break;
				default:
				$p = $this->compileValue($p);
				break;
			}
		}
		
		return implode($piece);
	}
	
	protected function compileChildren($stms, $out) {
		foreach ($stms as $stm) {
			$ret = $this->compileChild($stm, $out);
			if (!is_null($ret)) return $ret;
		}
	}
	
	protected function compileMediaQuery($queryList) {
		$out = "@media";
		$first = true;
		foreach ($queryList as $query){
			$parts = array();
			foreach ($query as $q) {
				switch ($q[0]) {
					case "mediaType":
					$parts[] = implode(" ", array_slice($q, 1));
					break;
					case "mediaExp":
					if (isset($q[2])) {
						$parts[] = "($q[1]" . $this->formatter->assignSeparator . $this->compileValue($q[2]) . ")";
					} else {
						$parts[] = "($q[1])";
					}
					break;
				}
			}
			if (!empty($parts)) {
				if ($first) {
					$first = false;
					$out .= " ";
				} else {
					$out .= $this->formatter->tagSeparator;
				}
				$out .= implode(" and ", $parts);
			}
		}
		return $out;
	}
	
	// returns true if the value was something that could be imported
	protected function compileImport($rawPath, $out) {
		if ($rawPath[0] == "string") {
			$path = $this->compileStringContent($rawPath);
			if ($path = $this->findImport($path)) {
				$this->importFile($path, $out);
				return true;
			}
			return false;
			} if ($rawPath[0] == "list") {
			// handle a list of strings
			if (count($rawPath[2]) == 0) return false;
			foreach ($rawPath[2] as $path) {
				if ($path[0] != "string") return false;
			}
			
			foreach ($rawPath[2] as $path) {
				$this->compileImport($path, $out);
			}
			
			return true;
		}
		
		return false;
	}
	
	// return a value to halt execution
	protected function compileChild($child, $out) {
		switch ($child[0]) {
			case "import":
			list(,$rawPath) = $child;
			$rawPath = $this->reduce($rawPath);
			if (!$this->compileImport($rawPath, $out)) {
				$out->lines[] = "@import " . $this->compileValue($rawPath) . ";";
			}
			break;
			case "directive":
			list(, $directive) = $child;
			$s = "@" . $directive->name;
			if (!empty($directive->value)) {
				$s .= " " . $this->compileValue($directive->value);
			}
			$this->compileNestedBlock($directive, array($s));
			break;
			case "media":
			$this->compileMedia($child[1]);
			break;
			case "block":
			$this->compileBlock($child[1]);
			break;
			case "charset":
			$out->lines[] = "@charset ".$this->compileValue($child[1]).";";
			break;
			case "assign":
			list(,$name, $value) = $child;
			if ($name[0] == "var") {
				$isDefault = !empty($child[3]);
				if (!$isDefault || $this->get($name[1], true) === true) {
					$this->set($name[1], $this->reduce($value));
				}
				break;
			}
			
			$out->lines[] = $this->formatter->property(
			$this->compileValue($child[1]),
			$this->compileValue($child[2]));
			break;
			case "comment":
			$out->lines[] = $child[1];
			break;
			case "mixin":
			case "function":
			list(,$block) = $child;
			$this->set(self::$namespaces[$block->type] . $block->name, $block);
			break;
			case "extend":
			list(, $selectors) = $child;
			foreach ($selectors as $sel) {
				// only use the first one
				$sel = current($this->evalSelector($sel));
				$this->pushExtends($sel, $out->selectors);
			}
			break;
			case "if":
			list(, $if) = $child;
			if ($this->reduce($if->cond, true) != self::$false) {
				return $this->compileChildren($if->children, $out);
			} else {
				foreach ($if->cases as $case) {
					if ($case->type == "else" ||
					$case->type == "elseif" && ($this->reduce($case->cond) != self::$false))
					{
						return $this->compileChildren($case->children, $out);
					}
				}
			}
			break;
			case "return":
			return $this->reduce($child[1], true);
			case "each":
			list(,$each) = $child;
			$list = $this->reduce($this->coerceList($each->list));
			foreach ($list[2] as $item) {
				$this->pushEnv();
				$this->set($each->var, $item);
				// TODO: allow return from here
				$this->compileChildren($each->children, $out);
				$this->popEnv();
			}
			break;
			case "while":
			list(,$while) = $child;
			while ($this->reduce($while->cond, true) != self::$false) {
				$ret = $this->compileChildren($while->children, $out);
				if ($ret) return $ret;
			}
			break;
			case "for":
			list(,$for) = $child;
			$start = $this->reduce($for->start, true);
			$start = $start[1];
			$end = $this->reduce($for->end, true);
			$end = $end[1];
			$d = $start < $end ? 1 : -1;
			
			while (true) {
				if ((!$for->until && $start - $d == $end) ||
				($for->until && $start == $end))
				{
					break;
				}
				
				$this->set($for->var, array("number", $start, ""));
				$start += $d;
				
				$ret = $this->compileChildren($for->children, $out);
				if ($ret) return $ret;
			}
			
			break;
			case "nestedprop":
			list(,$prop) = $child;
			$prefixed = array();
			$prefix = $this->compileValue($prop->prefix) . "-";
			foreach ($prop->children as $child) {
				if ($child[0] == "assign") {
					array_unshift($child[1][2], $prefix);
				}
				if ($child[0] == "nestedprop") {
					array_unshift($child[1]->prefix[2], $prefix);
				}
				$prefixed[] = $child;
			}
			$this->compileChildren($prefixed, $out);
			break;
			case "include": // including a mixin
			list(,$name, $argValues, $content) = $child;
			$mixin = $this->get(self::$namespaces["mixin"] . $name, false);
			if (!$mixin) break; // throw error?
			
			$callingScope = $this->env;
			
			// push scope, apply args
			$this->pushEnv();
			
			if (!is_null($content)) {
				$content->scope = $callingScope;
				$this->setRaw(self::$namespaces["special"] . "content", $content);
			}
			
			if (!is_null($mixin->args)) {
				$this->applyArguments($mixin->args, $argValues);
			}
			
			foreach ($mixin->children as $child) {
				$this->compileChild($child, $out);
			}
			
			$this->popEnv();
			
			break;
			case "mixin_content":
			$content = $this->get(self::$namespaces["special"] . "content");
			if (is_null($content)) {
				throw new \Exception("Unexpected @content inside of mixin");
			}
			
			$this->storeEnv = $content->scope;
			
			foreach ($content->children as $child) {
				$this->compileChild($child, $out);
			}
			
			unset($this->storeEnv);
			break;
			case "debug":
			list(,$value, $pos) = $child;
			$line = $this->parser->getLineNo($pos);
			$value = $this->compileValue($this->reduce($value, true));
			fwrite(STDERR, "Line $line DEBUG: $value\n");
			break;
			default:
			throw new exception("unknown child type: $child[0]");
		}
	}
	
	protected function expToString($exp) {
		list(, $op, $left, $right, $inParens, $whiteLeft, $whiteRight) = $exp;
		$content = array($left);
		if ($whiteLeft) $content[] = " ";
		$content[] = $op;
		if ($whiteRight) $content[] = " ";
		$content[] = $right;
		return array("string", "", $content);
	}
	
	// should $value cause its operand to eval
	protected function shouldEval($value) {
		switch ($value[0]) {
			case "exp":
			if ($value[1] == "/") {
				return $this->shouldEval($value[2], $value[3]);
			}
			case "var":
			case "fncall":
			return true;
		}
		return false;
	}
	
	protected function reduce($value, $inExp = false) {
		list($type) = $value;
		switch ($type) {
			case "exp":
			list(, $op, $left, $right, $inParens) = $value;
			$opName = isset(self::$operatorNames[$op]) ? self::$operatorNames[$op] : $op;
			
			$inExp = $inExp || $this->shouldEval($left) || $this->shouldEval($right);
			
			$left = $this->reduce($left, true);
			$right = $this->reduce($right, true);
			
			// only do division in special cases
			if ($opName == "div" && !$inParens && !$inExp) {
				if ($left[0] != "color" && $right[0] != "color") {
					return $this->expToString($value);
				}
			}
			
			$left = $this->coerceForExpression($left);
			$right = $this->coerceForExpression($right);
			
			$ltype = $left[0];
			$rtype = $right[0];
			
			// this tries:
			// 1. op_[op name]_[left type]_[right type]
			// 2. op_[left type]_[right type] (passing the op as first arg
			// 3. op_[op name]
			$fn = "op_${opName}_${ltype}_${rtype}";
			if (is_callable(array($this, $fn)) ||
			(($fn = "op_${ltype}_${rtype}") &&
			is_callable(array($this, $fn)) &&
			$passOp = true) ||
			(($fn = "op_${opName}") &&
			is_callable(array($this, $fn)) &&
			$genOp = true))
			{
				$unitChange = false;
				if (!isset($genOp) &&
				$left[0] == "number" && $right[0] == "number")
				{
					if ($opName == "mod" && $right[2] != "") {
						throw new \Exception(sprintf('Cannot modulo by a number with units: %s%s.', $right[1], $right[2]));
					}
					
					$unitChange = true;
					$emptyUnit = $left[2] == "" || $right[2] == "";
					$targetUnit = "" != $left[2] ? $left[2] : $right[2];
					
					if ($opName != "mul") {
						$left[2] = "" != $left[2] ? $left[2] : $targetUnit;
						$right[2] = "" != $right[2] ? $right[2] : $targetUnit;
					}
					
					if ($opName != "mod") {
						$left = $this->normalizeNumber($left);
						$right = $this->normalizeNumber($right);
					}
					
					if ($opName == "div" && !$emptyUnit && $left[2] == $right[2]) {
						$targetUnit = "";
					}
					
					if ($opName == "mul") {
						$left[2] = "" != $left[2] ? $left[2] : $right[2];
						$right[2] = "" != $right[2] ? $right[2] : $left[2];
					} elseif ($opName == "div" && $left[2] == $right[2]) {
						$left[2] = "";
						$right[2] = "";
					}
				}
				
				$shouldEval = $inParens || $inExp;
				if (isset($passOp)) {
					$out = $this->$fn($op, $left, $right, $shouldEval);
				} else {
					$out = $this->$fn($left, $right, $shouldEval);
				}
				
				if (!is_null($out)) {
					if ($unitChange && $out[0] == "number") {
						$out = $this->coerceUnit($out, $targetUnit);
					}
					return $out;
				}
			}
			
			return $this->expToString($value);
			case "unary":
			list(, $op, $exp, $inParens) = $value;
			$inExp = $inExp || $this->shouldEval($exp);
			
			$exp = $this->reduce($exp);
			if ($exp[0] == "number") {
				switch ($op) {
					case "+":
					return $exp;
					case "-":
					$exp[1] *= -1;
					return $exp;
				}
			}
			
			if ($op == "not") {
				if ($inExp || $inParens) {
					if ($exp == self::$false) {
						return self::$true;
					} else {
						return self::$false;
					}
				} else {
					$op = $op . " ";
				}
			}
			
			return array("string", "", array($op, $exp));
			case "var":
			list(, $name) = $value;
			return $this->reduce($this->get($name));
			case "list":
			foreach ($value[2] as &$item) {
				$item = $this->reduce($item);
			}
			return $value;
			case "string":
			foreach ($value[2] as &$item) {
				if (is_array($item)) {
					$item = $this->reduce($item);
				}
			}
			return $value;
			case "interpolate":
			$value[1] = $this->reduce($value[1]);
			return $value;
			case "fncall":
			list(,$name, $argValues) = $value;
			
			// user defined function?
			$func = $this->get(self::$namespaces["function"] . $name, false);
			if ($func) {
				$this->pushEnv();
				
				// set the args
				if (isset($func->args)) {
					$this->applyArguments($func->args, $argValues);
				}
				
				// throw away lines and children
				$tmp = (object)array(
				"lines" => array(),
				"children" => array()
				);
				$ret = $this->compileChildren($func->children, $tmp);
				$this->popEnv();
				
				return is_null($ret) ? self::$defaultValue : $ret;
			}
			
			// built in function
			if ($this->callBuiltin($name, $argValues, $returnValue)) {
				return $returnValue;
			}
			
			// need to flatten the arguments into a list
			$listArgs = array();
			foreach ((array)$argValues as $arg) {
				if (empty($arg[0])) {
					$listArgs[] = $this->reduce($arg[1]);
				}
			}
			return array("function", $name, array("list", ",", $listArgs));
			default:
			return $value;
		}
	}
	
	// just does physical lengths for now
	protected function normalizeNumber($number) {
		list(, $value, $unit) = $number;
		if (isset(self::$unitTable["in"][$unit])) {
			$conv = self::$unitTable["in"][$unit];
			return array("number", $value / $conv, "in");
		}
		return $number;
	}
	
	// $number should be normalized
	protected function coerceUnit($number, $unit) {
		list(, $value, $baseUnit) = $number;
		if (isset(self::$unitTable[$baseUnit][$unit])) {
			$value = $value * self::$unitTable[$baseUnit][$unit];
		}
		
		return array("number", $value, $unit);
	}
	
	protected function op_add_number_number($left, $right) {
		return array("number", $left[1] + $right[1], $left[2]);
	}
	
	protected function op_mul_number_number($left, $right) {
		return array("number", $left[1] * $right[1], $left[2]);
	}
	
	protected function op_sub_number_number($left, $right) {
		return array("number", $left[1] - $right[1], $left[2]);
	}
	
	protected function op_div_number_number($left, $right) {
		return array("number", $left[1] / $right[1], $left[2]);
	}
	
	protected function op_mod_number_number($left, $right) {
		return array("number", $left[1] % $right[1], $left[2]);
	}
	
	// adding strings
	protected function op_add($left, $right) {
		if ($strLeft = $this->coerceString($left)) {
			if ($right[0] == "string") {
				$right[1] = "";
			}
			$strLeft[2][] = $right;
			return $strLeft;
		}
		
		if ($strRight = $this->coerceString($right)) {
			if ($left[0] == "string") {
				$left[1] = "";
			}
			array_unshift($strRight[2], $left);
			return $strRight;
		}
	}
	
	protected function op_and($left, $right, $shouldEval) {
		if (!$shouldEval) return;
		if ($left != self::$false) return $right;
		return $left;
	}
	
	protected function op_or($left, $right, $shouldEval) {
		if (!$shouldEval) return;
		if ($left != self::$false) return $left;
		return $right;
	}
	
	protected function op_color_color($op, $left, $right) {
		$out = array('color');
		foreach (range(1, 3) as $i) {
			$lval = isset($left[$i]) ? $left[$i] : 0;
			$rval = isset($right[$i]) ? $right[$i] : 0;
			switch ($op) {
				case '+':
				$out[] = $lval + $rval;
				break;
				case '-':
				$out[] = $lval - $rval;
				break;
				case '*':
				$out[] = $lval * $rval;
				break;
				case '%':
				$out[] = $lval % $rval;
				break;
				case '/':
				if ($rval == 0) {
					throw new exception("color: Can't divide by zero");
				}
				$out[] = $lval / $rval;
				break;
				default:
				throw new exception("color: unknow op $op");
			}
		}
		
		if (isset($left[4])) $out[4] = $left[4];
		elseif (isset($right[4])) $out[4] = $right[4];
		
		return $this->fixColor($out);
	}
	
	protected function op_color_number($op, $left, $right) {
		$value = $right[1];
		return $this->op_color_color($op, $left,
		array("color", $value, $value, $value));
	}
	
	protected function op_number_color($op, $left, $right) {
		$value = $left[1];
		return $this->op_color_color($op,
		array("color", $value, $value, $value), $right);
	}
	
	protected function op_eq($left, $right) {
		if (($lStr = $this->coerceString($left)) && ($rStr = $this->coerceString($right))) {
			$lStr[1] = "";
			$rStr[1] = "";
			return $this->toBool($this->compileValue($lStr) == $this->compileValue($rStr));
		}
		
		return $this->toBool($left == $right);
	}
	
	protected function op_neq($left, $right) {
		return $this->toBool($left != $right);
	}
	
	protected function op_gte_number_number($left, $right) {
		return $this->toBool($left[1] >= $right[1]);
	}
	
	protected function op_gt_number_number($left, $right) {
		return $this->toBool($left[1] > $right[1]);
	}
	
	protected function op_lte_number_number($left, $right) {
		return $this->toBool($left[1] <= $right[1]);
	}
	
	protected function op_lt_number_number($left, $right) {
		return $this->toBool($left[1] < $right[1]);
	}
	
	protected function toBool($thing) {
		return $thing ? self::$true : self::$false;
	}
	
	protected function compileValue($value) {
		$value = $this->reduce($value);
		
		list($type) = $value;
		switch ($type) {
			case "keyword":
			return $value[1];
			case "color":
			// [1] - red component (either number for a %)
			// [2] - green component
			// [3] - blue component
			// [4] - optional alpha component
			list(, $r, $g, $b) = $value;
			
			$r = round($r);
			$g = round($g);
			$b = round($b);
			
			if (count($value) == 5 && $value[4] != 1) { // rgba
				return 'rgba('.$r.', '.$g.', '.$b.', '.$value[4].')';
			}
			
			$h = sprintf("#%02x%02x%02x", $r, $g, $b);
			
			// Converting hex color to short notation (e.g. #003399 to #039)
			if ($h[1] === $h[2] && $h[3] === $h[4] && $h[5] === $h[6]) {
				$h = '#' . $h[1] . $h[3] . $h[5];
			}
			
			return $h;
			case "number":
			return round($value[1], self::$numberPrecision) . $value[2];
			case "string":
			return $value[1] . $this->compileStringContent($value) . $value[1];
			case "function":
			$args = !empty($value[2]) ? $this->compileValue($value[2]) : "";
			return "$value[1]($args)";
			case "list":
			$value = $this->extractInterpolation($value);
			if ($value[0] != "list") return $this->compileValue($value);
			
			list(, $delim, $items) = $value;
			foreach ($items as &$item) {
				$item = $this->compileValue($item);
			}
			return implode("$delim ", $items);
			case "interpolated": # node created by extractInterpolation
			list(, $interpolate, $left, $right) = $value;
			list(,, $whiteLeft, $whiteRight) = $interpolate;
			
			$left = count($left[2]) > 0 ?
			$this->compileValue($left).$whiteLeft : "";
			
			$right = count($right[2]) > 0 ?
			$whiteRight.$this->compileValue($right) : "";
			
			return $left.$this->compileValue($interpolate).$right;
			
			case "interpolate": # raw parse node
			list(, $exp) = $value;
			
			// strip quotes if it's a string
			$reduced = $this->reduce($exp);
			if ($reduced[0] == "string") {
				$reduced = array("keyword",
				$this->compileStringContent($reduced));
			}
			
			return $this->compileValue($reduced);
			default:
			throw new exception("unknown value type: $type");
		}
	}
	
	protected function compileStringContent($string) {
		$parts = array();
		foreach ($string[2] as $part) {
			if (is_array($part)) {
				$parts[] = $this->compileValue($part);
			} else {
				$parts[] = $part;
			}
		}
		
		return implode($parts);
	}
	
	// doesn't need to be recursive, compileValue will handle that
	protected function extractInterpolation($list) {
		$items = $list[2];
		foreach ($items as $i => $item) {
			if ($item[0] == "interpolate") {
				$before = array("list", $list[1], array_slice($items, 0, $i));
				$after = array("list", $list[1], array_slice($items, $i + 1));
				return array("interpolated", $item, $before, $after);
			}
		}
		return $list;
	}
	
	// find the final set of selectors
	protected function multiplySelectors($env, $childSelectors = null) {
		if (is_null($env)) {
			return $childSelectors;
		}
		
		// skip env, has no selectors
		if (empty($env->selectors)) {
			return $this->multiplySelectors($env->parent, $childSelectors);
		}
		
		if (is_null($childSelectors)) {
			$selectors = $env->selectors;
		} else {
			$selectors = array();
			foreach ($env->selectors as $parent) {
				foreach ($childSelectors as $child) {
					$selectors[] = $this->joinSelectors($parent, $child);
				}
			}
		}
		
		return $this->multiplySelectors($env->parent, $selectors);
	}
	
	// looks for & to replace, or append parent before child
	protected function joinSelectors($parent, $child) {
		$setSelf = false;
		$out = array();
		foreach ($child as $part) {
			$newPart = array();
			foreach ($part as $p) {
				if ($p == self::$selfSelector) {
					$setSelf = true;
					foreach ($parent as $i => $parentPart) {
						if ($i > 0) {
							$out[] = $newPart;
							$newPart = array();
						}
						
						foreach ($parentPart as $pp) {
							$newPart[] = $pp;
						}
					}
				} else {
					$newPart[] = $p;
				}
			}
			
			$out[] = $newPart;
		}
		
		return $setSelf ? $out : array_merge($parent, $child);
	}
	
	protected function multiplyMedia($env, $childQueries = null) {
		if (is_null($env) ||
		!empty($env->block->type) && $env->block->type != "media")
		{
			return $childQueries;
		}
		
		// plain old block, skip
		if (empty($env->block->type)) {
			return $this->multiplyMedia($env->parent, $childQueries);
		}
		
		$parentQueries = $env->block->queryList;
		if ($childQueries == null) {
			$childQueries = $parentQueries;
		} else {
			$originalQueries = $childQueries;
			$childQueries = array();
			
			foreach ($parentQueries as $parentQuery){
				foreach ($originalQueries as $childQuery) {
					$childQueries []= array_merge($parentQuery, $childQuery);
				}
			}
		}
		
		return $this->multiplyMedia($env->parent, $childQueries);
	}
	
	// convert something to list
	protected function coerceList($item, $delim = ",") {
		if (!is_null($item) && $item[0] == "list") {
			return $item;
		}
		
		return array("list", $delim, is_null($item) ? array(): array($item));
	}
	
	protected function applyArguments($argDef, $argValues) {
		$argValues = (array)$argValues;
		
		$keywordArgs = array();
		$remaining = array();
		
		// assign the keyword args
		foreach ($argValues as $arg) {
			if (!empty($arg[0])) {
				$keywordArgs[$arg[0][1]] = $arg[1];
			} else {
				$remaining[] = $arg[1];
			}
		}
		
		foreach ($argDef as $i => $arg) {
			list($name, $default) = $arg;
			
			if (isset($remaining[$i])) {
				$val = $remaining[$i];
			} elseif (isset($keywordArgs[$name])) {
				$val = $keywordArgs[$name];
			} elseif (!empty($default)) {
				$val = $default;
			} else {
				$val = self::$defaultValue;
			}
			
			$this->set($name, $this->reduce($val, true), true);
		}
	}
	
	protected function pushEnv($block=null) {
		$env = new stdclass;
		$env->parent = $this->env;
		$env->store = array();
		$env->block = $block;
		$env->depth = isset($this->env->depth) ? $this->env->depth + 1 : 0;
		
		$this->env = $env;
		return $env;
	}
	
	protected function normalizeName($name) {
		return str_replace("-", "_", $name);
	}
	
	protected function getStoreEnv() {
		return isset($this->storeEnv) ? $this->storeEnv : $this->env;
	}
	
	protected function set($name, $value, $shadow=false) {
		$name = $this->normalizeName($name);
		if ($shadow) {
			$this->setRaw($name, $value);
		} else {
			$this->setExisting($name, $value);
		}
	}
	
	// todo: this is bugged?
	protected function setExisting($name, $value, $env = null) {
		if (is_null($env)) $env = $this->getStoreEnv();
		
		if (isset($env->store[$name])) {
			$env->store[$name] = $value;
		} elseif (!is_null($env->parent)) {
			$this->setExisting($name, $value, $env->parent);
		} else {
			$this->env->store[$name] = $value;
		}
	}
	
	protected function setRaw($name, $value) {
		$this->env->store[$name] = $value;
	}
	
	protected function get($name, $defaultValue = null, $env = null) {
		$name = $this->normalizeName($name);
		
		if (is_null($env)) $env = $this->getStoreEnv();
		if (is_null($defaultValue)) $defaultValue = self::$defaultValue;
		
		if (isset($env->store[$name])) {
			return $env->store[$name];
		} elseif (!is_null($env->parent)) {
			return $this->get($name, $defaultValue, $env->parent);
		}
		
		return $defaultValue; // found nothing
	}
	
	protected function popEnv() {
		$env = $this->env;
		$this->env = $this->env->parent;
		return $env;
	}
	
	public function getParsedFiles() {
		return $this->parsedFiles;
	}
	
	public function addImportPath($path) {
		$this->importPaths[] = $path;
	}
	
	public function setImportPaths($path) {
		$this->importPaths = (array)$path;
	}
	
	public function setFormatter($formatterName) {
		$this->formatter = $formatterName;
	}
	
	public function registerFunction($name, $func) {
		$this->userFunctions[$this->normalizeName($name)] = $func;
	}
	
	public function unregisterFunction($name) {
		unset($this->userFunctions[$this->normalizeName($name)]);
	}
	
	protected function importFile($path, $out) {
		// see if tree is cached
		$realPath = realpath($path);
		if (isset($this->importCache[$realPath])) {
			$tree = $this->importCache[$realPath];
		} else {
			$code = file_get_contents($path);
			$parser = new scss_parser($path);
			$tree = $parser->parse($code);
			$this->parsedFiles[] = $path;
			
			$this->importCache[$realPath] = $tree;
		}
		
		$pi = pathinfo($path);
		array_unshift($this->importPaths, $pi['dirname']);
		$this->compileChildren($tree->children, $out);
		array_shift($this->importPaths);
	}
	
	// results the file path for an import url if it exists
	protected function findImport($url) {
		$urls = array();
		
		// for "normal" scss imports (ignore vanilla css and external requests)
		if (!preg_match('/\.css|^http:\/\/$/', $url)) {
			// try both normal and the _partial filename
			$urls = array($url, preg_replace('/[^\/]+$/', '_\0', $url));
		}
		
		foreach ($this->importPaths as $dir) {
			if (is_string($dir)) {
				// check urls for normal import paths
				foreach ($urls as $full) {
					$full = $dir .
					(!empty($dir) && substr($dir, -1) != '/' ? '/' : '') .
					$full;
					
					if ($this->fileExists($file = $full.'.scss') ||
					$this->fileExists($file = $full))
					{
						return $file;
					}
				}
			} else {
				// check custom callback for import path
				$file = call_user_func($dir,$url,$this);
				if ($file !== null) {
					return $file;
				}
			}
		}
		
		return null;
	}
	
	protected function fileExists($name) {
		return is_file($name);
	}
	
	protected function callBuiltin($name, $args, &$returnValue) {
		// try a lib function
		$name = $this->normalizeName($name);
		$libName = "lib_".$name;
		$f = array($this, $libName);
		$prototype = isset(self::$$libName) ? self::$$libName : null;
		
		if (is_callable($f)) {
			$sorted = $this->sortArgs($prototype, $args);
			foreach ($sorted as &$val) {
				$val = $this->reduce($val, true);
			}
			$returnValue = call_user_func($f, $sorted, $this);
		} else if (isset($this->userFunctions[$name])) {
			// see if we can find a user function
			$fn = $this->userFunctions[$name];
			
			foreach ($args as &$val) {
				$val = $this->reduce($val[1], true);
			}
			
			$returnValue = call_user_func($fn, $args, $this);
		}
		
		if (isset($returnValue)) {
			// coerce a php value into a scss one
			if (is_numeric($returnValue)) {
				$returnValue = array('number', $returnValue, "");
			} elseif (is_bool($returnValue)) {
				$returnValue = $returnValue ? self::$true : self::$false;
			} elseif (!is_array($returnValue)) {
				$returnValue = array('keyword', $returnValue);
			}
			
			return true;
		}
		
		return false;
	}
	
	// sorts any keyword arguments
	// TODO: merge with apply arguments
	protected function sortArgs($prototype, $args) {
		$keyArgs = array();
		$posArgs = array();
		
		foreach ($args as $arg) {
			list($key, $value) = $arg;
			$key = $key[1];
			if (empty($key)) {
				$posArgs[] = $value;
			} else {
				$keyArgs[$key] = $value;
			}
		}
		
		if (is_null($prototype)) return $posArgs;
		
		$finalArgs = array();
		foreach ($prototype as $i => $names) {
			if (isset($posArgs[$i])) {
				$finalArgs[] = $posArgs[$i];
				continue;
			}
			
			$set = false;
			foreach ((array)$names as $name) {
				if (isset($keyArgs[$name])) {
					$finalArgs[] = $keyArgs[$name];
					$set = true;
					break;
				}
			}
			
			if (!$set) {
				$finalArgs[] = null;
			}
		}
		
		return $finalArgs;
	}
	
	protected function coerceForExpression($value) {
		if ($color = $this->coerceColor($value)) {
			return $color;
		}
		
		return $value;
	}
	
	protected function coerceColor($value) {
		switch ($value[0]) {
			case "color": return $value;
			case "keyword":
			$name = $value[1];
			if (isset(self::$cssColors[$name])) {
				list($r, $g, $b) = explode(',', self::$cssColors[$name]);
				return array('color', $r, $g, $b);
			}
			return null;
		}
		
		return null;
	}
	
	protected function coerceString($value) {
		switch ($value[0]) {
			case "string":
			return $value;
			case "keyword":
			return array("string", "", array($value[1]));
		}
		return null;
	}
	
	protected function assertColor($value) {
		if ($color = $this->coerceColor($value)) return $color;
		throw new exception("expecting color");
	}
	
	protected function assertNumber($value) {
		if ($value[0] != "number")
		throw new exception("expecting number");
		return $value[1];
	}
	
	protected function coercePercent($value) {
		if ($value[0] == "number") {
			if ($value[2] == "%") {
				return $value[1] / 100;
			}
			return $value[1];
		}
		return 0;
	}
	
	// make sure a color's components don't go out of bounds
	protected function fixColor($c) {
		foreach (range(1, 3) as $i) {
			if ($c[$i] < 0) $c[$i] = 0;
			if ($c[$i] > 255) $c[$i] = 255;
		}
		
		return $c;
	}
	
	function toHSL($r, $g, $b) {
		$r = $r / 255;
		$g = $g / 255;
		$b = $b / 255;
		
		$min = min($r, $g, $b);
		$max = max($r, $g, $b);
		
		$L = ($min + $max) / 2;
		if ($min == $max) {
			$S = $H = 0;
		} else {
			if ($L < 0.5)
			$S = ($max - $min)/($max + $min);
			else
			$S = ($max - $min)/(2.0 - $max - $min);
			
			if ($r == $max) $H = ($g - $b)/($max - $min);
			elseif ($g == $max) $H = 2.0 + ($b - $r)/($max - $min);
			elseif ($b == $max) $H = 4.0 + ($r - $g)/($max - $min);
			
		}
		
		return array('hsl',
		($H < 0 ? $H + 6 : $H)*60,
		$S*100,
		$L*100,
		);
	}
	
	function toRGB_helper($comp, $temp1, $temp2) {
		if ($comp < 0) $comp += 1.0;
		elseif ($comp > 1) $comp -= 1.0;
		
		if (6 * $comp < 1) return $temp1 + ($temp2 - $temp1) * 6 * $comp;
		if (2 * $comp < 1) return $temp2;
		if (3 * $comp < 2) return $temp1 + ($temp2 - $temp1)*((2/3) - $comp) * 6;
		
		return $temp1;
	}
	
	// H from 0 to 360, S and L from 0 to 100
	function toRGB($H, $S, $L) {
		$H = $H % 360;
		if ($H < 0) $H += 360;
		
		$S = min(100, max(0, $S));
		$L = min(100, max(0, $L));
		
		$H = $H / 360;
		$S = $S / 100;
		$L = $L / 100;
		
		if ($S == 0) {
			$r = $g = $b = $L;
		} else {
			$temp2 = $L < 0.5 ?
			$L*(1.0 + $S) :
			$L + $S - $L * $S;
			
			$temp1 = 2.0 * $L - $temp2;
			
			$r = $this->toRGB_helper($H + 1/3, $temp1, $temp2);
			$g = $this->toRGB_helper($H, $temp1, $temp2);
			$b = $this->toRGB_helper($H - 1/3, $temp1, $temp2);
		}
		
		$out = array('color', $r*255, $g*255, $b*255);
		return $out;
	}
	
	// Built in functions
	
	protected static $lib_if = array("condition", "if-true", "if-false");
	protected function lib_if($args) {
		list($cond,$t, $f) = $args;
		if ($cond == self::$false) return $f;
		return $t;
	}
	
	protected static $lib_rgb = array("red", "green", "blue");
	protected function lib_rgb($args) {
		list($r,$g,$b) = $args;
		return array("color", $r[1], $g[1], $b[1]);
	}
	
	protected static $lib_rgba = array(
	array("red", "color"),
	"green", "blue", "alpha");
	protected function lib_rgba($args) {
		if ($color = $this->coerceColor($args[0])) {
			$num = is_null($args[1]) ? $args[3] : $args[1];
			$alpha = $this->assertNumber($num);
			$color[4] = $alpha;
			return $color;
		}
		
		list($r,$g,$b, $a) = $args;
		return array("color", $r[1], $g[1], $b[1], $a[1]);
	}
	
	// helper function for adjust_color, change_color, and scale_color
	protected function alter_color($args, $fn) {
		$color = $this->assertColor($args[0]);
		
		foreach (array(1,2,3,7) as $i) {
			if (!is_null($args[$i])) {
				$val = $this->assertNumber($args[$i]);
				$ii = $i == 7 ? 4 : $i; // alpha
				$color[$ii] =
				$this->$fn(isset($color[$ii]) ? $color[$ii] : 0, $val, $i);
			}
		}
		
		if (!is_null($args[4]) || !is_null($args[5]) || !is_null($args[6])) {
			$hsl = $this->toHSL($color[1], $color[2], $color[3]);
			foreach (array(4,5,6) as $i) {
				if (!is_null($args[$i])) {
					$val = $this->assertNumber($args[$i]);
					$hsl[$i - 3] = $this->$fn($hsl[$i - 3], $val, $i);
				}
			}
			
			$rgb = $this->toRGB($hsl[1], $hsl[2], $hsl[3]);
			if (isset($color[4])) $rgb[4] = $color[4];
			$color = $rgb;
		}
		
		return $color;
	}
	
	protected static $lib_adjust_color = array(
	"color", "red", "green", "blue",
	"hue", "saturation", "lightness", "alpha"
	);
	protected function adjust_color_helper($base, $alter, $i) {
		return $base += $alter;
	}
	protected function lib_adjust_color($args) {
		return $this->alter_color($args, "adjust_color_helper");
	}
	
	protected static $lib_change_color = array(
	"color", "red", "green", "blue",
	"hue", "saturation", "lightness", "alpha"
	);
	protected function change_color_helper($base, $alter, $i) {
		return $alter;
	}
	protected function lib_change_color($args) {
		return $this->alter_color($args, "change_color_helper");
	}
	
	protected static $lib_scale_color = array(
	"color", "red", "green", "blue",
	"hue", "saturation", "lightness", "alpha"
	);
	protected function scale_color_helper($base, $scale, $i) {
		// 1,2,3 - rgb
		// 4, 5, 6 - hsl
		// 7 - a
		switch ($i) {
			case 1:
			case 2:
			case 3:
			$max = 255; break;
			case 4:
			$max = 360; break;
			case 7:
			$max = 1; break;
			default:
			$max = 100;
		}
		
		$scale = $scale / 100;
		if ($scale < 0) {
			return $base * $scale + $base;
		} else {
			return ($max - $base) * $scale + $base;
		}
	}
	protected function lib_scale_color($args) {
		return $this->alter_color($args, "scale_color_helper");
	}
	
	protected static $lib_ie_hex_str = array("color");
	protected function lib_ie_hex_str($args) {
		$color = $this->coerceColor($args[0]);
		$color[4] = isset($color[4]) ? round(255*$color[4]) : 255;
		
		return sprintf('#%02X%02X%02X%02X', $color[4], $color[1], $color[2], $color[3]);
	}
	
	protected static $lib_red = array("color");
	protected function lib_red($args) {
		list($color) = $args;
		return $color[1];
	}
	
	protected static $lib_green = array("color");
	protected function lib_green($args) {
		list($color) = $args;
		return $color[2];
	}
	
	protected static $lib_blue = array("color");
	protected function lib_blue($args) {
		list($color) = $args;
		return $color[3];
	}
	
	protected static $lib_alpha = array("color");
	protected function lib_alpha($args) {
		if ($color = $this->coerceColor($args[0])) {
			return isset($color[4]) ? $color[4] : 1;
		}
		
		// this might be the IE function, so return value unchanged
		return array("function", "alpha", array("list", ",", $args));
	}
	
	protected static $lib_opacity = array("color");
	protected function lib_opacity($args) {
		return $this->lib_alpha($args);
	}
	
	// mix two colors
	protected static $lib_mix = array("color-1", "color-2", "weight");
	protected function lib_mix($args) {
		list($first, $second, $weight) = $args;
		$first = $this->assertColor($first);
		$second = $this->assertColor($second);
		
		if (is_null($weight)) {
			$weight = 0.5;
		} else {
			$weight = $this->coercePercent($weight);
		}
		
		$first_a = isset($first[4]) ? $first[4] : 1;
		$second_a = isset($second[4]) ? $second[4] : 1;
		
		$w = $weight * 2 - 1;
		$a = $first_a - $second_a;
		
		$w1 = (($w * $a == -1 ? $w : ($w + $a)/(1 + $w * $a)) + 1) / 2.0;
		$w2 = 1.0 - $w1;
		
		$new = array('color',
		$w1 * $first[1] + $w2 * $second[1],
		$w1 * $first[2] + $w2 * $second[2],
		$w1 * $first[3] + $w2 * $second[3],
		);
		
		if ($first_a != 1.0 || $second_a != 1.0) {
			$new[] = $first_a * $weight + $second_a * ($weight - 1);
		}
		
		return $this->fixColor($new);
	}
	
	protected static $lib_hsl = array("hue", "saturation", "lightness");
	protected function lib_hsl($args) {
		list($h, $s, $l) = $args;
		return $this->toRGB($h[1], $s[1], $l[1]);
	}
	
	protected static $lib_hsla = array("hue", "saturation",
	"lightness", "alpha");
	protected function lib_hsla($args) {
		list($h, $s, $l, $a) = $args;
		$color = $this->toRGB($h[1], $s[1], $l[1]);
		$color[4] = $a[1];
		return $color;
	}
	
	protected static $lib_hue = array("color");
	protected function lib_hue($args) {
		$color = $this->assertColor($args[0]);
		$hsl = $this->toHSL($color[1], $color[2], $color[3]);
		return array("number", $hsl[1], "deg");
	}
	
	protected static $lib_saturation = array("color");
	protected function lib_saturation($args) {
		$color = $this->assertColor($args[0]);
		$hsl = $this->toHSL($color[1], $color[2], $color[3]);
		return array("number", $hsl[2], "%");
	}
	
	protected static $lib_lightness = array("color");
	protected function lib_lightness($args) {
		$color = $this->assertColor($args[0]);
		$hsl = $this->toHSL($color[1], $color[2], $color[3]);
		return array("number", $hsl[3], "%");
	}
	
	
	protected function adjustHsl($color, $idx, $amount) {
		$hsl = $this->toHSL($color[1], $color[2], $color[3]);
		$hsl[$idx] += $amount;
		$out = $this->toRGB($hsl[1], $hsl[2], $hsl[3]);
		if (isset($color[4])) $out[4] = $color[4];
		return $out;
	}
	
	protected static $lib_adjust_hue = array("color", "degrees");
	protected function lib_adjust_hue($args) {
		$color = $this->assertColor($args[0]);
		$degrees = $this->assertNumber($args[1]);
		return $this->adjustHsl($color, 1, $degrees);
	}
	
	protected static $lib_lighten = array("color", "amount");
	protected function lib_lighten($args) {
		$color = $this->assertColor($args[0]);
		$amount = 100*$this->coercePercent($args[1]);
		return $this->adjustHsl($color, 3, $amount);
	}
	
	protected static $lib_darken = array("color", "amount");
	protected function lib_darken($args) {
		$color = $this->assertColor($args[0]);
		$amount = 100*$this->coercePercent($args[1]);
		return $this->adjustHsl($color, 3, -$amount);
	}
	
	protected static $lib_saturate = array("color", "amount");
	protected function lib_saturate($args) {
		$color = $this->assertColor($args[0]);
		$amount = 100*$this->coercePercent($args[1]);
		return $this->adjustHsl($color, 2, $amount);
	}
	
	protected static $lib_desaturate = array("color", "amount");
	protected function lib_desaturate($args) {
		$color = $this->assertColor($args[0]);
		$amount = 100*$this->coercePercent($args[1]);
		return $this->adjustHsl($color, 2, -$amount);
	}
	
	protected static $lib_grayscale = array("color");
	protected function lib_grayscale($args) {
		return $this->adjustHsl($this->assertColor($args[0]), 2, -100);
	}
	
	protected static $lib_complement = array("color");
	protected function lib_complement($args) {
		return $this->adjustHsl($this->assertColor($args[0]), 1, 180);
	}
	
	protected static $lib_invert = array("color");
	protected function lib_invert($args) {
		$color = $this->assertColor($args[0]);
		$color[1] = 255 - $color[1];
		$color[2] = 255 - $color[2];
		$color[3] = 255 - $color[3];
		return $color;
	}
	
	
	// increases opacity by amount
	protected static $lib_opacify = array("color", "amount");
	protected function lib_opacify($args) {
		$color = $this->assertColor($args[0]);
		$amount = $this->coercePercent($args[1]);
		
		$color[4] = (isset($color[4]) ? $color[4] : 1) + $amount;
		$color[4] = min(1, max(0, $color[4]));
		return $color;
	}
	
	protected static $lib_fade_in = array("color", "amount");
	protected function lib_fade_in($args) {
		return $this->lib_opacify($args);
	}
	
	// decreases opacity by amount
	protected static $lib_transparentize = array("color", "amount");
	protected function lib_transparentize($args) {
		$color = $this->assertColor($args[0]);
		$amount = $this->coercePercent($args[1]);
		
		$color[4] = (isset($color[4]) ? $color[4] : 1) - $amount;
		$color[4] = min(1, max(0, $color[4]));
		return $color;
	}
	
	protected static $lib_fade_out = array("color", "amount");
	protected function lib_fade_out($args) {
		return $this->lib_transparentize($args);
	}
	
	protected static $lib_unquote = array("string");
	protected function lib_unquote($args) {
		$str = $args[0];
		if ($str[0] == "string") $str[1] = "";
		return $str;
	}
	
	protected static $lib_quote = array("string");
	protected function lib_quote($args) {
		$value = $args[0];
		if ($value[0] == "string" && !empty($value[1]))
		return $value;
		return array("string", '"', array($value));
	}
	
	protected static $lib_percentage = array("value");
	protected function lib_percentage($args) {
		return array("number",
		$this->coercePercent($args[0]) * 100,
		"%");
	}
	
	protected static $lib_round = array("value");
	protected function lib_round($args) {
		$num = $args[0];
		$num[1] = round($num[1]);
		return $num;
	}
	
	protected static $lib_floor = array("value");
	protected function lib_floor($args) {
		$num = $args[0];
		$num[1] = floor($num[1]);
		return $num;
	}
	
	protected static $lib_ceil = array("value");
	protected function lib_ceil($args) {
		$num = $args[0];
		$num[1] = ceil($num[1]);
		return $num;
	}
	
	protected static $lib_abs = array("value");
	protected function lib_abs($args) {
		$num = $args[0];
		$num[1] = abs($num[1]);
		return $num;
	}
	
	protected function lib_min($args) {
		$numbers = $this->getNormalizedNumbers($args);
		$min = null;
		foreach ($numbers as $key => $number) {
			if (null === $min || $number <= $min[1]) {
				$min = array($key, $number);
			}
		}
		
		return $args[$min[0]];
	}
	
	protected function lib_max($args) {
		$numbers = $this->getNormalizedNumbers($args);
		$max = null;
		foreach ($numbers as $key => $number) {
			if (null === $max || $number >= $max[1]) {
				$max = array($key, $number);
			}
		}
		
		return $args[$max[0]];
	}
	
	protected function getNormalizedNumbers($args) {
		$unit = null;
		$originalUnit = null;
		$numbers = array();
		foreach ($args as $key => $item) {
			if ('number' != $item[0]) {
				throw new Exception(sprintf('%s is not a number', $item[0]));
			}
			$number = $this->normalizeNumber($item);
			
			if (null === $unit) {
				$unit = $number[2];
			} elseif ($unit !== $number[2]) {
				throw new \Exception(sprintf('Incompatible units: "%s" and "%s".', $originalUnit, $item[2]));
			}
			
			$originalUnit = $item[2];
			$numbers[$key] = $number[1];
		}
		
		return $numbers;
	}
	
	protected static $lib_length = array("list");
	protected function lib_length($args) {
		$list = $this->coerceList($args[0]);
		return count($list[2]);
	}
	
	protected static $lib_nth = array("list", "n");
	protected function lib_nth($args) {
		$list = $this->coerceList($args[0]);
		$n = $this->assertNumber($args[1]) - 1;
		return isset($list[2][$n]) ? $list[2][$n] : self::$defaultValue;
	}
	
	
	protected function listSeparatorForJoin($list1, $sep) {
		if (is_null($sep)) return $list1[1];
		switch ($this->compileValue($sep)) {
			case "comma":
			return ",";
			case "space":
			return "";
			default:
			return $list1[1];
		}
	}
	
	protected static $lib_join = array("list1", "list2", "separator");
	protected function lib_join($args) {
		list($list1, $list2, $sep) = $args;
		$list1 = $this->coerceList($list1, " ");
		$list2 = $this->coerceList($list2, " ");
		$sep = $this->listSeparatorForJoin($list1, $sep);
		return array("list", $sep, array_merge($list1[2], $list2[2]));
	}
	
	protected static $lib_append = array("list", "val", "separator");
	protected function lib_append($args) {
		list($list1, $value, $sep) = $args;
		$list1 = $this->coerceList($list1, " ");
		$sep = $this->listSeparatorForJoin($list1, $sep);
		return array("list", $sep, array_merge($list1[2], array($value)));
	}
	
	
	protected static $lib_type_of = array("value");
	protected function lib_type_of($args) {
		$value = $args[0];
		switch ($value[0]) {
			case "keyword":
			if ($value == self::$true || $value == self::$false) {
				return "bool";
			}
			
			if ($this->coerceColor($value)) {
				return "color";
			}
			
			return "string";
			default:
			return $value[0];
		}
	}
	
	protected static $lib_unit = array("number");
	protected function lib_unit($args) {
		$num = $args[0];
		if ($num[0] == "number") {
			return array("string", '"', array($num[2]));
		}
		return "";
	}
	
	protected static $lib_unitless = array("number");
	protected function lib_unitless($args) {
		$value = $args[0];
		return $value[0] == "number" && empty($value[2]);
	}
	
	
	protected static $lib_comparable = array("number-1", "number-2");
	protected function lib_comparable($args) {
		return true; // TODO: THIS
	}
	
	static protected $cssColors = array(
	'aliceblue' => '240,248,255',
	'antiquewhite' => '250,235,215',
	'aqua' => '0,255,255',
	'aquamarine' => '127,255,212',
	'azure' => '240,255,255',
	'beige' => '245,245,220',
	'bisque' => '255,228,196',
	'black' => '0,0,0',
	'blanchedalmond' => '255,235,205',
	'blue' => '0,0,255',
	'blueviolet' => '138,43,226',
	'brown' => '165,42,42',
	'burlywood' => '222,184,135',
	'cadetblue' => '95,158,160',
	'chartreuse' => '127,255,0',
	'chocolate' => '210,105,30',
	'coral' => '255,127,80',
	'cornflowerblue' => '100,149,237',
	'cornsilk' => '255,248,220',
	'crimson' => '220,20,60',
	'cyan' => '0,255,255',
	'darkblue' => '0,0,139',
	'darkcyan' => '0,139,139',
	'darkgoldenrod' => '184,134,11',
	'darkgray' => '169,169,169',
	'darkgreen' => '0,100,0',
	'darkgrey' => '169,169,169',
	'darkkhaki' => '189,183,107',
	'darkmagenta' => '139,0,139',
	'darkolivegreen' => '85,107,47',
	'darkorange' => '255,140,0',
	'darkorchid' => '153,50,204',
	'darkred' => '139,0,0',
	'darksalmon' => '233,150,122',
	'darkseagreen' => '143,188,143',
	'darkslateblue' => '72,61,139',
	'darkslategray' => '47,79,79',
	'darkslategrey' => '47,79,79',
	'darkturquoise' => '0,206,209',
	'darkviolet' => '148,0,211',
	'deeppink' => '255,20,147',
	'deepskyblue' => '0,191,255',
	'dimgray' => '105,105,105',
	'dimgrey' => '105,105,105',
	'dodgerblue' => '30,144,255',
	'firebrick' => '178,34,34',
	'floralwhite' => '255,250,240',
	'forestgreen' => '34,139,34',
	'fuchsia' => '255,0,255',
	'gainsboro' => '220,220,220',
	'ghostwhite' => '248,248,255',
	'gold' => '255,215,0',
	'goldenrod' => '218,165,32',
	'gray' => '128,128,128',
	'green' => '0,128,0',
	'greenyellow' => '173,255,47',
	'grey' => '128,128,128',
	'honeydew' => '240,255,240',
	'hotpink' => '255,105,180',
	'indianred' => '205,92,92',
	'indigo' => '75,0,130',
	'ivory' => '255,255,240',
	'khaki' => '240,230,140',
	'lavender' => '230,230,250',
	'lavenderblush' => '255,240,245',
	'lawngreen' => '124,252,0',
	'lemonchiffon' => '255,250,205',
	'lightblue' => '173,216,230',
	'lightcoral' => '240,128,128',
	'lightcyan' => '224,255,255',
	'lightgoldenrodyellow' => '250,250,210',
	'lightgray' => '211,211,211',
	'lightgreen' => '144,238,144',
	'lightgrey' => '211,211,211',
	'lightpink' => '255,182,193',
	'lightsalmon' => '255,160,122',
	'lightseagreen' => '32,178,170',
	'lightskyblue' => '135,206,250',
	'lightslategray' => '119,136,153',
	'lightslategrey' => '119,136,153',
	'lightsteelblue' => '176,196,222',
	'lightyellow' => '255,255,224',
	'lime' => '0,255,0',
	'limegreen' => '50,205,50',
	'linen' => '250,240,230',
	'magenta' => '255,0,255',
	'maroon' => '128,0,0',
	'mediumaquamarine' => '102,205,170',
	'mediumblue' => '0,0,205',
	'mediumorchid' => '186,85,211',
	'mediumpurple' => '147,112,219',
	'mediumseagreen' => '60,179,113',
	'mediumslateblue' => '123,104,238',
	'mediumspringgreen' => '0,250,154',
	'mediumturquoise' => '72,209,204',
	'mediumvioletred' => '199,21,133',
	'midnightblue' => '25,25,112',
	'mintcream' => '245,255,250',
	'mistyrose' => '255,228,225',
	'moccasin' => '255,228,181',
	'navajowhite' => '255,222,173',
	'navy' => '0,0,128',
	'oldlace' => '253,245,230',
	'olive' => '128,128,0',
	'olivedrab' => '107,142,35',
	'orange' => '255,165,0',
	'orangered' => '255,69,0',
	'orchid' => '218,112,214',
	'palegoldenrod' => '238,232,170',
	'palegreen' => '152,251,152',
	'paleturquoise' => '175,238,238',
	'palevioletred' => '219,112,147',
	'papayawhip' => '255,239,213',
	'peachpuff' => '255,218,185',
	'peru' => '205,133,63',
	'pink' => '255,192,203',
	'plum' => '221,160,221',
	'powderblue' => '176,224,230',
	'purple' => '128,0,128',
	'red' => '255,0,0',
	'rosybrown' => '188,143,143',
	'royalblue' => '65,105,225',
	'saddlebrown' => '139,69,19',
	'salmon' => '250,128,114',
	'sandybrown' => '244,164,96',
	'seagreen' => '46,139,87',
	'seashell' => '255,245,238',
	'sienna' => '160,82,45',
	'silver' => '192,192,192',
	'skyblue' => '135,206,235',
	'slateblue' => '106,90,205',
	'slategray' => '112,128,144',
	'slategrey' => '112,128,144',
	'snow' => '255,250,250',
	'springgreen' => '0,255,127',
	'steelblue' => '70,130,180',
	'tan' => '210,180,140',
	'teal' => '0,128,128',
	'thistle' => '216,191,216',
	'tomato' => '255,99,71',
	'turquoise' => '64,224,208',
	'violet' => '238,130,238',
	'wheat' => '245,222,179',
	'white' => '255,255,255',
	'whitesmoke' => '245,245,245',
	'yellow' => '255,255,0',
	'yellowgreen' => '154,205,50'
	);
}

class scss_parser {
	static protected $precedence = array(
	"or" => 0,
	"and" => 1,
	
	'==' => 2,
	'!=' => 2,
	'<=' => 2,
	'>=' => 2,
	'=' => 2,
	'<' => 3,
	'>' => 2,
	
	'+' => 3,
	'-' => 3,
	'*' => 4,
	'/' => 4,
	'%' => 4,
	);
	
	static protected $operators = array("+", "-", "*", "/", "%",
	"==", "!=", "<=", ">=", "<", ">", "and", "or");
	
	static protected $operatorStr;
	static protected $whitePattern;
	static protected $commentMulti;
	
	static protected $commentSingle = "//";
	static protected $commentMultiLeft = "/*";
	static protected $commentMultiRight = "*/";
	
	function __construct($sourceName = null) {
		$this->sourceName = $sourceName;
		
		if (empty(self::$operatorStr)) {
			self::$operatorStr = $this->makeOperatorStr(self::$operators);
			
			$commentSingle = $this->preg_quote(self::$commentSingle);
			$commentMultiLeft = $this->preg_quote(self::$commentMultiLeft);
			$commentMultiRight = $this->preg_quote(self::$commentMultiRight);
			self::$commentMulti = $commentMultiLeft.'.*?'.$commentMultiRight;
			self::$whitePattern = '/'.$commentSingle.'[^\n]*\s*|('.self::$commentMulti.')\s*|\s+/Ais';
		}
	}
	
	static protected function makeOperatorStr($operators) {
		return '('.implode('|', array_map(array('scss_parser','preg_quote'),
		$operators)).')';
	}
	
	function parse($buffer) {
		$this->count = 0;
		$this->env = null;
		$this->inParens = false;
		$this->pushBlock(null); // root block
		$this->eatWhiteDefault = true;
		$this->insertComments = true;
		
		$this->buffer = $buffer;
		
		$this->whitespace();
		while (false !== $this->parseChunk());
		
		if ($this->count != strlen($this->buffer))
		$this->throwParseError();
		
		if (!empty($this->env->parent)) {
			$this->throwParseError("unclosed block");
		}
		
		$this->env->isRoot = true;
		return $this->env;
	}
	
	protected function parseChunk() {
		$s = $this->seek();
		
		// the directives
		if (isset($this->buffer[$this->count]) && $this->buffer[$this->count] == "@") {
			if ($this->literal("@media") && $this->mediaQueryList($mediaQueryList) && $this->literal("{")) {
				$media = $this->pushSpecialBlock("media");
				$media->queryList = $mediaQueryList[2];
				return true;
			} else {
				$this->seek($s);
			}
			
			if ($this->literal("@mixin") &&
			$this->keyword($mixinName) &&
			($this->argumentDef($args) || true) &&
			$this->literal("{"))
			{
				$mixin = $this->pushSpecialBlock("mixin");
				$mixin->name = $mixinName;
				$mixin->args = $args;
				return true;
			} else {
				$this->seek($s);
			}
			
			if ($this->literal("@include") &&
			$this->keyword($mixinName) &&
			($this->literal("(") &&
			($this->argValues($argValues) || true) &&
			$this->literal(")") || true) &&
			($this->end() ||
			$this->literal("{") && $hasBlock = true))
			{
				$child = array("include",
				$mixinName, isset($argValues) ? $argValues : null, null);
				
				if (!empty($hasBlock)) {
					$include = $this->pushSpecialBlock("include");
					$include->child = $child;
				} else {
					$this->append($child);
				}
				
				return true;
			} else {
				$this->seek($s);
			}
			
			if ($this->literal("@import") &&
			$this->valueList($importPath) &&
			$this->end())
			{
				$this->append(array("import", $importPath));
				return true;
			} else {
				$this->seek($s);
			}
			
			if ($this->literal("@extend") &&
			$this->selectors($selector) &&
			$this->end())
			{
				$this->append(array("extend", $selector));
				return true;
			} else {
				$this->seek($s);
			}
			
			if ($this->literal("@function") &&
			$this->keyword($fn_name) &&
			$this->argumentDef($args) &&
			$this->literal("{"))
			{
				$func = $this->pushSpecialBlock("function");
				$func->name = $fn_name;
				$func->args = $args;
				return true;
			} else {
				$this->seek($s);
			}
			
			if ($this->literal("@return") && $this->valueList($retVal) && $this->end()) {
				$this->append(array("return", $retVal));
				return true;
			} else {
				$this->seek($s);
			}
			
			if ($this->literal("@each") &&
			$this->variable($varName) &&
			$this->literal("in") &&
			$this->valueList($list) &&
			$this->literal("{"))
			{
				$each = $this->pushSpecialBlock("each");
				$each->var = $varName[1];
				$each->list = $list;
				return true;
			} else {
				$this->seek($s);
			}
			
			if ($this->literal("@while") &&
			$this->expression($cond) &&
			$this->literal("{"))
			{
				$while = $this->pushSpecialBlock("while");
				$while->cond = $cond;
				return true;
			} else {
				$this->seek($s);
			}
			
			if ($this->literal("@for") &&
			$this->variable($varName) &&
			$this->literal("from") &&
			$this->expression($start) &&
			($this->literal("through") ||
			($forUntil = true && $this->literal("to"))) &&
			$this->expression($end) &&
			$this->literal("{"))
			{
				$for = $this->pushSpecialBlock("for");
				$for->var = $varName[1];
				$for->start = $start;
				$for->end = $end;
				$for->until = isset($forUntil);
				return true;
			} else {
				$this->seek($s);
			}
			
			if ($this->literal("@if") && $this->valueList($cond) && $this->literal("{")) {
				$if = $this->pushSpecialBlock("if");
				$if->cond = $cond;
				$if->cases = array();
				return true;
			} else {
				$this->seek($s);
			}
			
			if (($this->literal("@debug") || $this->literal("@warn")) &&
			$this->valueList($value) &&
			$this->end()) {
				$this->append(array("debug", $value, $s));
				return true;
			} else {
				$this->seek($s);
			}
			
			if ($this->literal("@content") && $this->end()) {
				$this->append(array("mixin_content"));
				return true;
			} else {
				$this->seek($s);
			}
			
			$last = $this->last();
			if (!is_null($last) && $last[0] == "if") {
				list(, $if) = $last;
				if ($this->literal("@else")) {
					if ($this->literal("{")) {
						$else = $this->pushSpecialBlock("else");
					} elseif ($this->literal("if") && $this->valueList($cond) && $this->literal("{")) {
						$else = $this->pushSpecialBlock("elseif");
						$else->cond = $cond;
					}
					
					if (isset($else)) {
						$else->dontAppend = true;
						$if->cases[] = $else;
						return true;
					}
				}
				
				$this->seek($s);
			}
			
			if ($this->literal("@charset") &&
			$this->valueList($charset) && $this->end())
			{
				$this->append(array("charset", $charset));
				return true;
			} else {
				$this->seek($s);
			}
			
			// doesn't match built in directive, do generic one
			if ($this->literal("@", false) && $this->keyword($dirName) &&
			($this->openString("{", $dirValue) || true) &&
			$this->literal("{"))
			{
				$directive = $this->pushSpecialBlock("directive");
				$directive->name = $dirName;
				if (isset($dirValue)) $directive->value = $dirValue;
				return true;
			}
			
			$this->seek($s);
			return false;
		}
		
		// property shortcut
		// captures most properties before having to parse a selector
		if ($this->keyword($name, false) &&
		$this->literal(": ") &&
		$this->valueList($value) &&
		$this->end())
		{
			$name = array("string", "", array($name));
			$this->append(array("assign", $name, $value));
			return true;
		} else {
			$this->seek($s);
		}
		
		// variable assigns
		if ($this->variable($name) &&
		$this->literal(":") &&
		$this->valueList($value) && $this->end())
		{
			$defaultVar = false;
			// check for !default
			if ($value[0] == "list") {
				$def = end($value[2]);
				if ($def[0] == "keyword" && $def[1] == "!default") {
					array_pop($value[2]);
					$value = $this->flattenList($value);
					$defaultVar = true;
				}
			}
			$this->append(array("assign", $name, $value, $defaultVar));
			return true;
		} else {
			$this->seek($s);
		}
		
		// misc
		if ($this->literal("-->")) {
			return true;
		}
		
		// opening css block
		$oldComments = $this->insertComments;
		$this->insertComments = false;
		if ($this->selectors($selectors) && $this->literal("{")) {
			$this->pushBlock($selectors);
			$this->insertComments = $oldComments;
			return true;
		} else {
			$this->seek($s);
		}
		$this->insertComments = $oldComments;
		
		// property assign, or nested assign
		if ($this->propertyName($name) && $this->literal(":")) {
			$foundSomething = false;
			if ($this->valueList($value)) {
				$this->append(array("assign", $name, $value));
				$foundSomething = true;
			}
			
			if ($this->literal("{")) {
				$propBlock = $this->pushSpecialBlock("nestedprop");
				$propBlock->prefix = $name;
				$foundSomething = true;
			} elseif ($foundSomething) {
				$foundSomething = $this->end();
			}
			
			if ($foundSomething) {
				return true;
			}
			
			$this->seek($s);
		} else {
			$this->seek($s);
		}
		
		// closing a block
		if ($this->literal("}")) {
			$block = $this->popBlock();
			if (isset($block->type) && $block->type == "include") {
				$include = $block->child;
				unset($block->child);
				$include[3] = $block;
				$this->append($include);
			} else if (empty($block->dontAppend)) {
				$type = isset($block->type) ? $block->type : "block";
				$this->append(array($type, $block));
			}
			return true;
		}
		
		// extra stuff
		if ($this->literal(";") ||
		$this->literal("<!--"))
		{
			return true;
		}
		
		return false;
	}
	
	protected function literal($what, $eatWhitespace = null) {
		if (is_null($eatWhitespace)) $eatWhitespace = $this->eatWhiteDefault;
		
		// this is here mainly prevent notice from { } string accessor
		if ($this->count >= strlen($this->buffer)) return false;
		
		// shortcut on single letter
		if (!$eatWhitespace && strlen($what) == 1) {
			if ($this->buffer{$this->count} == $what) {
				$this->count++;
				return true;
			}
			else return false;
		}
		
		return $this->match($this->preg_quote($what), $m, $eatWhitespace);
	}
	
	// tree builders
	
	protected function pushBlock($selectors) {
		$b = new stdclass;
		$b->parent = $this->env; // not sure if we need this yet
		
		$b->selectors = $selectors;
		$b->children = array();
		
		$this->env = $b;
		return $b;
	}
	
	protected function pushSpecialBlock($type) {
		$block = $this->pushBlock(null);
		$block->type = $type;
		return $block;
	}
	
	protected function popBlock() {
		if (empty($this->env->parent)) {
			$this->throwParseError("unexpected }");
		}
		
		$old = $this->env;
		$this->env = $this->env->parent;
		unset($old->parent);
		return $old;
	}
	
	protected function append($statement) {
		$this->env->children[] = $statement;
	}
	
	// last child that was appended
	protected function last() {
		$i = count($this->env->children) - 1;
		if (isset($this->env->children[$i]))
		return $this->env->children[$i];
	}
	
	// high level parsers (they return parts of ast)
	
	protected function mediaQueryList(&$out) {
		return $this->genericList($out, "mediaQuery", ",", false);
	}
	
	protected function mediaQuery(&$out) {
		$s = $this->seek();
		
		$expressions = null;
		$parts = array();
		
		if (($this->literal("only") && ($only = true) || $this->literal("not") && ($not = true) || true) && $this->keyword($mediaType)) {
			$prop = array("mediaType");
			if (isset($only)) $prop[] = "only";
			if (isset($not)) $prop[] = "not";
			$prop[] = $mediaType;
			$parts[] = $prop;
		} else {
			$this->seek($s);
		}
		
		
		if (!empty($mediaType) && !$this->literal("and")) {
			// ~
		} else {
			$this->genericList($expressions, "mediaExpression", "and", false);
			if (is_array($expressions)) $parts = array_merge($parts, $expressions[2]);
		}
		
		$out = $parts;
		return true;
	}
	
	protected function mediaExpression(&$out) {
		$s = $this->seek();
		$value = null;
		if ($this->literal("(") &&
		$this->keyword($feature) &&
		($this->literal(":") && $this->expression($value) || true) &&
		$this->literal(")"))
		{
			$out = array("mediaExp", $feature);
			if ($value) $out[] = $value;
			return true;
		}
		
		$this->seek($s);
		return false;
	}
	
	protected function argValues(&$out) {
		if ($this->genericList($list, "argValue", ",", false)) {
			$out = $list[2];
			return true;
		}
		return false;
	}
	
	protected function argValue(&$out) {
		$s = $this->seek();
		
		$keyword = null;
		if (!$this->variable($keyword) || !$this->literal(":")) {
			$this->seek($s);
			$keyword = null;
		}
		
		if ($this->genericList($value, "expression")) {
			$out = array($keyword, $value);
			return true;
		}
		
		return false;
	}
	
	
	protected function valueList(&$out) {
		return $this->genericList($out, "commaList");
	}
	
	protected function commaList(&$out) {
		return $this->genericList($out, "expression", ",");
	}
	
	protected function genericList(&$out, $parseItem, $delim="", $flatten=true) {
		$s = $this->seek();
		$items = array();
		while ($this->$parseItem($value)) {
			$items[] = $value;
			if ($delim) {
				if (!$this->literal($delim)) break;
			}
		}
		
		if (count($items) == 0) {
			$this->seek($s);
			return false;
		}
		
		if ($flatten && count($items) == 1) {
			$out = $items[0];
		} else {
			$out = array("list", $delim, $items);
		}
		
		return true;
	}
	
	protected function expression(&$out) {
		$s = $this->seek();
		
		if ($this->literal("(")) {
			if ($this->literal(")")) {
				$out = array("list", "", array());
				return true;
			}
			
			if ($this->valueList($out) && $this->literal(')') && $out[0] == "list") {
				return true;
			}
			
			$this->seek($s);
		}
		
		if ($this->value($lhs)) {
			$out = $this->expHelper($lhs, 0);
			return true;
		}
		
		return false;
	}
	
	protected function expHelper($lhs, $minP) {
		$opstr = self::$operatorStr;
		
		$ss = $this->seek();
		$whiteBefore = isset($this->buffer[$this->count - 1]) &&
		ctype_space($this->buffer[$this->count - 1]);
		while ($this->match($opstr, $m) && self::$precedence[$m[1]] >= $minP) {
			$whiteAfter = isset($this->buffer[$this->count - 1]) &&
			ctype_space($this->buffer[$this->count - 1]);
			
			$op = $m[1];
			
			// don't turn negative numbers into expressions
			if ($op == "-" && $whiteBefore) {
				if (!$whiteAfter) break;
			}
			
			if (!$this->value($rhs)) break;
			
			// peek and see if rhs belongs to next operator
			if ($this->peek($opstr, $next) && self::$precedence[$next[1]] > self::$precedence[$op]) {
				$rhs = $this->expHelper($rhs, self::$precedence[$next[1]]);
			}
			
			$lhs = array("exp", $op, $lhs, $rhs, $this->inParens, $whiteBefore, $whiteAfter);
			$ss = $this->seek();
			$whiteBefore = isset($this->buffer[$this->count - 1]) &&
			ctype_space($this->buffer[$this->count - 1]);
		}
		
		$this->seek($ss);
		return $lhs;
	}
	
	protected function value(&$out) {
		$s = $this->seek();
		
		if ($this->literal("not", false) && $this->whitespace() && $this->value($inner)) {
			$out = array("unary", "not", $inner, $this->inParens);
			return true;
		} else {
			$this->seek($s);
		}
		
		if ($this->literal("+") && $this->value($inner)) {
			$out = array("unary", "+", $inner, $this->inParens);
			return true;
		} else {
			$this->seek($s);
		}
		
		// negation
		if ($this->literal("-", false) &&
		($this->variable($inner) ||
		$this->unit($inner) ||
		$this->parenValue($inner)))
		{
			$out = array("unary", "-", $inner, $this->inParens);
			return true;
		} else {
			$this->seek($s);
		}
		
		if ($this->parenValue($out)) return true;
		if ($this->interpolation($out)) return true;
		if ($this->variable($out)) return true;
		if ($this->color($out)) return true;
		if ($this->unit($out)) return true;
		if ($this->string($out)) return true;
		if ($this->func($out)) return true;
		if ($this->progid($out)) return true;
		
		if ($this->keyword($keyword)) {
			$out = array("keyword", $keyword);
			return true;
		}
		
		return false;
	}
	
	// value wrappen in parentheses
	protected function parenValue(&$out) {
		$s = $this->seek();
		
		$inParens = $this->inParens;
		if ($this->literal("(") &&
		($this->inParens = true) && $this->expression($exp) &&
		$this->literal(")"))
		{
			$out = $exp;
			$this->inParens = $inParens;
			return true;
		} else {
			$this->inParens = $inParens;
			$this->seek($s);
		}
		
		return false;
	}
	
	protected function progid(&$out) {
		$s = $this->seek();
		if ($this->literal("progid:", false) &&
		$this->openString("(", $fn) &&
		$this->literal("("))
		{
			$this->openString(")", $args, "(");
			if ($this->literal(")")) {
				$out = array("string", "", array(
				"progid:", $fn, "(", $args, ")"
				));
				return true;
			}
		}
		
		$this->seek($s);
		return false;
	}
	
	protected function func(&$func) {
		$s = $this->seek();
		
		if ($this->keyword($name, false) &&
		$this->literal("("))
		{
			if ($name != "expression" && false == preg_match("/^(-[a-z]+-)?calc$/", $name)) {
				$ss = $this->seek();
				if ($this->argValues($args) && $this->literal(")")) {
					$func = array("fncall", $name, $args);
					return true;
				}
				$this->seek($ss);
			}
			
			if (($this->openString(")", $str, "(") || true ) &&
			$this->literal(")"))
			{
				$args = array();
				if (!empty($str)) {
					$args[] = array(null, array("string", "", array($str)));
				}
				
				$func = array("fncall", $name, $args);
				return true;
			}
		}
		
		$this->seek($s);
		return false;
	}
	
	protected function argumentDef(&$out) {
		$s = $this->seek();
		$this->literal("(");
		
		$args = array();
		while ($this->variable($var)) {
			$arg = array($var[1], null);
			
			$ss = $this->seek();
			if ($this->literal(":") && $this->expression($defaultVal)) {
				$arg[1] = $defaultVal;
			} else {
				$this->seek($ss);
			}
			
			$args[] = $arg;
			if (!$this->literal(",")) break;
		}
		
		if (!$this->literal(")")) {
			$this->seek($s);
			return false;
		}
		
		$out = $args;
		return true;
	}
	
	protected function color(&$out) {
		$color = array('color');
		
		if ($this->match('(#([0-9a-f]{6})|#([0-9a-f]{3}))', $m)) {
			if (isset($m[3])) {
				$num = $m[3];
				$width = 16;
			} else {
				$num = $m[2];
				$width = 256;
			}
			
			$num = hexdec($num);
			foreach (array(3,2,1) as $i) {
				$t = $num % $width;
				$num /= $width;
				
				$color[$i] = $t * (256/$width) + $t * floor(16/$width);
			}
			
			$out = $color;
			return true;
		}
		
		return false;
	}
	
	protected function unit(&$unit) {
		if ($this->match('([0-9]*(\.)?[0-9]+)([%a-zA-Z]+)?', $m)) {
			$unit = array("number", $m[1], empty($m[3]) ? "" : $m[3]);
			return true;
		}
		return false;
	}
	
	protected function string(&$out) {
		$s = $this->seek();
		if ($this->literal('"', false)) {
			$delim = '"';
		} elseif ($this->literal("'", false)) {
			$delim = "'";
		} else {
			return false;
		}
		
		$content = array();
		
		// look for either ending delim , escape, or string interpolation
		$patt = '([^\n]*?)(#\{|\\\\|' .
		$this->preg_quote($delim).')';
		
		$oldWhite = $this->eatWhiteDefault;
		$this->eatWhiteDefault = false;
		
		while ($this->match($patt, $m, false)) {
			$content[] = $m[1];
			if ($m[2] == "#{") {
				$this->count -= strlen($m[2]);
				if ($this->interpolation($inter, false)) {
					$content[] = $inter;
				} else {
					$this->count += strlen($m[2]);
					$content[] = "#{"; // ignore it
				}
			} elseif ($m[2] == '\\') {
				$content[] = $m[2];
				if ($this->literal($delim, false)) {
					$content[] = $delim;
				}
			} else {
				$this->count -= strlen($delim);
				break; // delim
			}
		}
		
		$this->eatWhiteDefault = $oldWhite;
		
		if ($this->literal($delim)) {
			$out = array("string", $delim, $content);
			return true;
		}
		
		$this->seek($s);
		return false;
	}
	
	protected function mixedKeyword(&$out) {
		$s = $this->seek();
		
		$parts = array();
		
		$oldWhite = $this->eatWhiteDefault;
		$this->eatWhiteDefault = false;
		
		while (true) {
			if ($this->keyword($key)) {
				$parts[] = $key;
				continue;
			}
			
			if ($this->interpolation($inter)) {
				$parts[] = $inter;
				continue;
			}
			
			break;
		}
		
		$this->eatWhiteDefault = $oldWhite;
		
		if (count($parts) == 0) return false;
		
		$out = $parts;
		return true;
	}
	
	// an unbounded string stopped by $end
	protected function openString($end, &$out, $nestingOpen=null) {
		$oldWhite = $this->eatWhiteDefault;
		$this->eatWhiteDefault = false;
		
		$stop = array("'", '"', "#{", $end);
		$stop = array_map(array($this, "preg_quote"), $stop);
		$stop[] = self::$commentMulti;
		
		$patt = '(.*?)('.implode("|", $stop).')';
		
		$nestingLevel = 0;
		
		$content = array();
		while ($this->match($patt, $m, false)) {
			if (!empty($m[1])) {
				$content[] = $m[1];
				if ($nestingOpen) {
					$nestingLevel += substr_count($m[1], $nestingOpen);
				}
			}
			
			$tok = $m[2];
			
			$this->count-= strlen($tok);
			if ($tok == $end) {
				if ($nestingLevel == 0) {
					break;
				} else {
					$nestingLevel--;
				}
			}
			
			if (($tok == "'" || $tok == '"') && $this->string($str)) {
				$content[] = $str;
				continue;
			}
			
			if ($tok == "#{" && $this->interpolation($inter)) {
				$content[] = $inter;
				continue;
			}
			
			$content[] = $tok;
			$this->count+= strlen($tok);
		}
		
		$this->eatWhiteDefault = $oldWhite;
		
		if (count($content) == 0) return false;
		
		// trim the end
		if (is_string(end($content))) {
			$content[count($content) - 1] = rtrim(end($content));
		}
		
		$out = array("string", "", $content);
		return true;
	}
	
	// $lookWhite: save information about whitespace before and after
	protected function interpolation(&$out, $lookWhite=true) {
		$oldWhite = $this->eatWhiteDefault;
		$this->eatWhiteDefault = true;
		
		$s = $this->seek();
		if ($this->literal("#{") && $this->valueList($value) && $this->literal("}", false)) {
			
			// TODO: don't error if out of bounds
			
			if ($lookWhite) {
				$left = preg_match('/\s/', $this->buffer[$s - 1]) ? " " : "";
				$right = preg_match('/\s/', $this->buffer[$this->count]) ? " ": "";
			} else {
				$left = $right = false;
			}
			
			$out = array("interpolate", $value, $left, $right);
			$this->eatWhiteDefault = $oldWhite;
			if ($this->eatWhiteDefault) $this->whitespace();
			return true;
		}
		
		$this->seek($s);
		$this->eatWhiteDefault = $oldWhite;
		return false;
	}
	
	// low level parsers
	
	// returns an array of parts or a string
	protected function propertyName(&$out) {
		$s = $this->seek();
		$parts = array();
		
		$oldWhite = $this->eatWhiteDefault;
		$this->eatWhiteDefault = false;
		
		while (true) {
			if ($this->interpolation($inter)) {
				$parts[] = $inter;
			} elseif ($this->keyword($text)) {
				$parts[] = $text;
			} elseif (count($parts) == 0 && $this->match('[:.#]', $m, false)) {
				// css hacks
				$parts[] = $m[0];
			} else {
				break;
			}
		}
		
		$this->eatWhiteDefault = $oldWhite;
		if (count($parts) == 0) return false;
		
		// match comment hack
		if (preg_match(self::$whitePattern,
		$this->buffer, $m, null, $this->count))
		{
			if (!empty($m[0])) {
				$parts[] = $m[0];
				$this->count += strlen($m[0]);
			}
		}
		
		$this->whitespace(); // get any extra whitespace
		
		$out = array("string", "", $parts);
		return true;
	}
	
	// comma separated list of selectors
	protected function selectors(&$out) {
		$s = $this->seek();
		$selectors = array();
		while ($this->selector($sel)) {
			$selectors[] = $sel;
			if (!$this->literal(",")) break;
			while ($this->literal(",")); // ignore extra
		}
		
		if (count($selectors) == 0) {
			$this->seek($s);
			return false;
		}
		
		$out = $selectors;
		return true;
	}
	
	// whitepsace separated list of selectorSingle
	protected function selector(&$out) {
		$selector = array();
		
		while (true) {
			if ($this->match('[>+~]+', $m)) {
				$selector[] = array($m[0]);
			} elseif ($this->selectorSingle($part)) {
				$selector[] = $part;
				$this->whitespace();
			} else {
				break;
			}
			
		}
		
		if (count($selector) == 0) {
			return false;
		}
		
		$out = $selector;
		return true;
	}
	
	// the parts that make up
	// div[yes=no]#something.hello.world:nth-child(-2n+1)
	protected function selectorSingle(&$out) {
		$oldWhite = $this->eatWhiteDefault;
		$this->eatWhiteDefault = false;
		
		$parts = array();
		
		if ($this->literal("*", false)) {
			$parts[] = "*";
		}
		
		while (true) {
			// see if we can stop early
			if ($this->match("\s*[{,]", $m)) {
				$this->count--;
				break;
			}
			
			$s = $this->seek();
			// self
			if ($this->literal("&", false)) {
				$parts[] = scssc::$selfSelector;
				continue;
			}
			
			if ($this->literal(".", false)) {
				$parts[] = ".";
				continue;
			}
			
			if ($this->literal("|", false)) {
				$parts[] = "|";
				continue;
			}
			
			// for keyframes
			if ($this->unit($unit)) {
				$parts[] = $unit;
				continue;
			}
			
			if ($this->keyword($name)) {
				$parts[] = $name;
				continue;
			}
			
			if ($this->interpolation($inter)) {
				$parts[] = $inter;
				continue;
			}
			
			if ($this->literal("#", false)) {
				$parts[] = "#";
				continue;
			}
			
			// a pseudo selector
			if ($this->match("::?", $m) && $this->mixedKeyword($nameParts)) {
				$parts[] = $m[0];
				foreach ($nameParts as $sub) {
					$parts[] = $sub;
				}
				
				$ss = $this->seek();
				if ($this->literal("(") &&
				($this->openString(")", $str, "(") || true ) &&
				$this->literal(")"))
				{
					$parts[] = "(";
					if (!empty($str)) $parts[] = $str;
					$parts[] = ")";
				} else {
					$this->seek($ss);
				}
				
				continue;
			} else {
				$this->seek($s);
			}
			
			// attribute selector
			// TODO: replace with open string?
			if ($this->literal("[", false)) {
				$attrParts = array("[");
				// keyword, string, operator
				while (true) {
					if ($this->literal("]", false)) {
						$this->count--;
						break; // get out early
					}
					
					if ($this->match('\s+', $m)) {
						$attrParts[] = " ";
						continue;
					}
					if ($this->string($str)) {
						$attrParts[] = $str;
						continue;
					}
					
					if ($this->keyword($word)) {
						$attrParts[] = $word;
						continue;
					}
					
					if ($this->interpolation($inter, false)) {
						$attrParts[] = $inter;
						continue;
					}
					
					// operator, handles attr namespace too
					if ($this->match('[|-~\$\*\^=]+', $m)) {
						$attrParts[] = $m[0];
						continue;
					}
					
					break;
				}
				
				if ($this->literal("]", false)) {
					$attrParts[] = "]";
					foreach ($attrParts as $part) {
						$parts[] = $part;
					}
					continue;
				}
				$this->seek($s);
				// should just break here?
			}
			
			break;
		}
		
		$this->eatWhiteDefault = $oldWhite;
		
		if (count($parts) == 0) return false;
		
		$out = $parts;
		return true;
	}
	
	protected function variable(&$out) {
		$s = $this->seek();
		if ($this->literal("$", false) && $this->keyword($name)) {
			$out = array("var", $name);
			return true;
		}
		$this->seek($s);
		return false;
	}
	
	protected function keyword(&$word, $eatWhitespace = null) {
		if ($this->match('([\w_\-\*!"\'\\\\][\w\-_"\'\\\\]*)',
		$m, $eatWhitespace))
		{
			$word = $m[1];
			return true;
		}
		return false;
	}
	
	// consume an end of statement delimiter
	protected function end() {
		if ($this->literal(';')) {
			return true;
		} elseif ($this->count == strlen($this->buffer) || $this->buffer{$this->count} == '}') {
			// if there is end of file or a closing block next then we don't need a ;
			return true;
		}
		return false;
	}
	
	// advance counter to next occurrence of $what
	// $until - don't include $what in advance
	// $allowNewline, if string, will be used as valid char set
	protected function to($what, &$out, $until = false, $allowNewline = false) {
		if (is_string($allowNewline)) {
			$validChars = $allowNewline;
		} else {
			$validChars = $allowNewline ? "." : "[^\n]";
		}
		if (!$this->match('('.$validChars.'*?)'.$this->preg_quote($what), $m, !$until)) return false;
		if ($until) $this->count -= strlen($what); // give back $what
		$out = $m[1];
		return true;
	}
	
	protected function throwParseError($msg = "parse error", $count = null) {
		$count = is_null($count) ? $this->count : $count;
		
		$line = $this->getLineNo($count);
		
		if (!empty($this->sourceName)) {
			$loc = "$this->sourceName on line $line";
		} else {
			$loc = "line: $line";
		}
		
		if ($this->peek("(.*?)(\n|$)", $m, $count)) {
			throw new exception("$msg: failed at `$m[1]` $loc");
		} else {
			throw new exception("$msg: $loc");
		}
	}
	
	public function getLineNo($pos) {
		return 1 + substr_count(substr($this->buffer, 0, $pos), "\n");
	}
	
	// try to match something on head of buffer
	protected function match($regex, &$out, $eatWhitespace = null) {
		if (is_null($eatWhitespace)) $eatWhitespace = $this->eatWhiteDefault;
		
		$r = '/'.$regex.'/Ais';
		if (preg_match($r, $this->buffer, $out, null, $this->count)) {
			$this->count += strlen($out[0]);
			if ($eatWhitespace) $this->whitespace();
			return true;
		}
		return false;
	}
	
	// match some whitespace
	protected function whitespace() {
		$gotWhite = false;
		while (preg_match(self::$whitePattern, $this->buffer, $m, null, $this->count)) {
			if ($this->insertComments) {
				if (isset($m[1]) && empty($this->commentsSeen[$this->count])) {
					$this->append(array("comment", $m[1]));
					$this->commentsSeen[$this->count] = true;
				}
			}
			$this->count += strlen($m[0]);
			$gotWhite = true;
		}
		return $gotWhite;
	}
	
	protected function peek($regex, &$out, $from=null) {
		if (is_null($from)) $from = $this->count;
		
		$r = '/'.$regex.'/Ais';
		$result = preg_match($r, $this->buffer, $out, null, $from);
		
		return $result;
	}
	
	protected function seek($where = null) {
		if ($where === null) return $this->count;
		else $this->count = $where;
		return true;
	}
	
	static function preg_quote($what) {
		return preg_quote($what, '/');
	}
	
	protected function show() {
		if ($this->peek("(.*?)(\n|$)", $m, $this->count)) {
			return $m[1];
		}
		return "";
	}
	
	// turn list of length 1 into value type
	protected function flattenList($value) {
		if ($value[0] == "list" && count($value[2]) == 1) {
			return $this->flattenList($value[2][0]);
		}
		return $value;
	}
}

class scss_formatter {
	public $indentChar = "  ";
	
	public $break = "\n";
	public $open = " {";
	public $close = "}";
	public $tagSeparator = ", ";
	public $assignSeparator = ": ";
	
	public function __construct() {
		$this->indentLevel = 0;
	}
	
	public function indentStr($n = 0) {
		return str_repeat($this->indentChar, max($this->indentLevel + $n, 0));
	}
	
	public function property($name, $value) {
		return $name . $this->assignSeparator . $value . ";";
	}
	
	public function block($block) {
		if (empty($block->lines) && empty($block->children)) return;
		
		$inner = $pre = $this->indentStr();
		
		if (!empty($block->selectors)) {
			echo $pre .
			implode($this->tagSeparator, $block->selectors) .
			$this->open . $this->break;
			$this->indentLevel++;
			$inner = $this->indentStr();
		}
		
		if (!empty($block->lines)) {
			$glue = $this->break.$inner;
			echo $inner . implode($glue, $block->lines);
			if (!empty($block->children)) {
				echo $this->break;
			}
		}
		
		foreach ($block->children as $child) {
			$this->block($child);
		}
		
		if (!empty($block->selectors)) {
			$this->indentLevel--;
			if (empty($block->children)) echo $this->break;
			echo $pre . $this->close . $this->break;
		}
	}
}


class scss_formatter_nested extends scss_formatter {
	public $close = " }";
	
	// adjust the depths of all children, depth first
	public function adjustAllChildren($block) {
		// flatten empty nested blocks
		$children = array();
		foreach ($block->children as $i => $child) {
			if (empty($child->lines) && empty($child->children)) {
				if (isset($block->children[$i + 1])) {
					$block->children[$i + 1]->depth = $child->depth;
				}
				continue;
			}
			$children[] = $child;
		}
		$block->children = $children;
		
		// make relative to parent
		foreach ($block->children as $child) {
			$this->adjustAllChildren($child);
			$child->depth = $child->depth - $block->depth;
		}
	}
	
	public function block($block) {
		if ($block->type == "root") {
			$this->adjustAllChildren($block);
		}
		
		$inner = $pre = $this->indentStr($block->depth - 1);
		if (!empty($block->selectors)) {
			echo $pre .
			implode($this->tagSeparator, $block->selectors) .
			$this->open . $this->break;
			$this->indentLevel++;
			$inner = $this->indentStr($block->depth - 1);
		}
		
		if (!empty($block->lines)) {
			$glue = $this->break.$inner;
			echo $inner . implode($glue, $block->lines);
			if (!empty($block->children)) echo $this->break;
		}
		
		foreach ($block->children as $i => $child) {
			// echo "*** block: ".$block->depth." child: ".$child->depth."\n";
			$this->block($child);
			if ($i < count($block->children) - 1) {
				echo $this->break;
				
				if (isset($block->children[$i + 1])) {
					$next = $block->children[$i + 1];
					if ($next->depth == max($block->depth, 1) && $child->depth >= $next->depth) {
						echo $this->break;
					}
				}
			}
		}
		
		if (!empty($block->selectors)) {
			$this->indentLevel--;
			echo $this->close;
		}
		
		if ($block->type == "root") {
			echo $this->break;
		}
	}
}

class scss_formatter_compressed extends scss_formatter {
	public $open = "{";
	public $tagSeparator = ",";
	public $assignSeparator = ":";
	public $break = "";
	
	public function indentStr($n = 0) {
		return "";
	}
}

class scss_server {
	
	protected function join($left, $right) {
		return rtrim($left, "/") . "/" . ltrim($right, "/");
	}
	
	protected function inputName() {
		if (isset($_GET["p"])) return $_GET["p"];
		
		if (isset($_SERVER["PATH_INFO"])) return $_SERVER["PATH_INFO"];
		if (isset($_SERVER["DOCUMENT_URI"])) {
			return substr($_SERVER["DOCUMENT_URI"], strlen($_SERVER["SCRIPT_NAME"]));
		}
	}
	
	protected function findInput() {
		if ($input = $this->inputName()) {
			$name = $this->join($this->dir, $input);
			if (is_readable($name)) return $name;
		}
		return false;
	}
	
	protected function cacheName($fname) {
		return $this->join($this->cacheDir, md5($fname) . ".css");
	}
	
	protected function importsCacheName($out) {
		return $out . ".imports";
	}
	
	protected function needsCompile($in, $out) {
		if (!is_file($out)) return true;
		
		$mtime = filemtime($out);
		if (filemtime($in) > $mtime) return true;
		
		// look for modified imports
		$icache = $this->importsCacheName($out);
		if (is_readable($icache)) {
			$imports = unserialize(file_get_contents($icache));
			foreach ($imports as $import) {
				if (filemtime($import) > $mtime) return true;
			}
		}
		return false;
	}
	
	protected function compile($in, $out) {
		$start = microtime(true);
		$css = $this->scss->compile(file_get_contents($in), $in);
		$elapsed = round((microtime(true) - $start), 4);
		
		$v = scssc::$VERSION;
		$t = date("r");
		$css = "/* compiled by scssphp $v on $t (${elapsed}s) */\n\n" . $css;
		
		file_put_contents($out, $css);
		file_put_contents($this->importsCacheName($out),
		serialize($this->scss->getParsedFiles()));
		return $css;
	}
	
	public function serve() {
		if ($input = $this->findInput()) {
			$output = $this->cacheName($input);
			header("Content-type: text/css");
			
			if ($this->needsCompile($input, $output)) {
				try {
					echo $this->compile($input, $output);
					} catch (exception $e) {
					header('HTTP/1.1 500 Internal Server Error');
					echo "Parse error: " . $e->getMessage() . "\n";
				}
			} else {
				header('X-SCSS-Cache: true');
				echo file_get_contents($output);
			}
			
			return;
		}
		
		header('HTTP/1.0 404 Not Found');
		header("Content-type: text");
		$v = scssc::$VERSION;
		echo "/* INPUT NOT FOUND scss $v */\n";
	}
	
	public function __construct($dir, $cacheDir=null, $scss=null) {
		$this->dir = $dir;
		
		if (is_null($cacheDir)) {
			$cacheDir = $this->join($dir, "scss_cache");
		}
		
		$this->cacheDir = $cacheDir;
		if (!is_dir($this->cacheDir)) mkdir($this->cacheDir);
		
		if (is_null($scss)) {
			$scss = new scssc();
			$scss->setImportPaths($this->dir);
		}
		$this->scss = $scss;
	}
	
	static public function serveFrom($path) {
		$server = new self($path);
		$server->serve();
	}
}