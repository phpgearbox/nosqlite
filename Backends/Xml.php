<?php
////////////////////////////////////////////////////////////////////////////////
//           _______          _________       ____     __  __                   
//           \      \   ____ /   _____/ _____|    |   |__|/  |_  ____           
//           /   |   \ /  _ \\_____  \ / ____/    |   |  \   __\/ __ \          
//          /    |    (  <_> )        < <_|  |    |___|  ||  | \  ___/          
//          \____|__  /\____/_______  /\__   |_______ \__||__|  \___  >         
//                  \/              \/    |__|       \/             \/          
// =============================================================================
//         Designed and Developed by Brad Jones <bj @="gravit.com.au" />        
// =============================================================================
////////////////////////////////////////////////////////////////////////////////

namespace GravIT\NoSqLite\Backends;

/**
 * Class: GravIT\NoSqLite\Backends\Xml
 * =============================================================================
 * Xml Backend
 * 
 * Okay so it's not really XML, i cheated and just wrapped everything up in
 * CDATA tags. So it's really just some simple regular expressions and string
 * manipulation. If you used simplexml or similar I am sure the overheads would
 * greater but someone may decide thats what they want, if so just write a
 * new driver. 
 */
class Xml extends Driver
{
	protected function ext() { return 'xml'; }
	
	protected function encode($data)
	{
		// Recursively Traverse a Multi-Dimensional Array
		$traverseArray = function($array) use (&$traverseArray)
		{
			$xml = '';
			
			foreach($array as $key => $value)
			{ 
				if(is_array($value))
				{ 
					$xml .= '<'.$key.'>';
					$xml .= $traverseArray($value);
					$xml .= '</'.$key.'>';
				}
				else
				{
					// To be on the safe side we will wrap the value in CDATA tags
					$xml .= '<'.$key.'><![CDATA['.$value.']]></'.$key.'>'; 
				} 
			}
			
			return $xml;
		};
		
		// Return XML string
		return $traverseArray($data);
	}
	
	protected function decode($data)
	{
		// Recursively Traverse an XML string
		$traverseXml = function($xml) use (&$traverseXml)
		{
			$array = array();
			
			preg_match_all("/(<([\w]+)[^>]*>)(.*?)(<\/\\2>)/", $xml, $matches, PREG_SET_ORDER);
			foreach ($matches as $match)
			{
				// Do we have any more xml benith us
				if (substr($match[3], 0, 9) != '<![CDATA[')
				{
					$array[$match[2]] = $traverseXml($match[3]);
				}
				else
				{
					// Add the value and remove the cdata tags
					$array[$match[2]] = substr($match[3], 9, -3);
				}
			}
			
			return $array;
		};
		
		// Return the array
		return $traverseXml($data);
	}
}