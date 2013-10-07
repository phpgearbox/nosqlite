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
 * Class: GravIT\NoSqLite\Backends\JsonComp
 * =============================================================================
 * Json Compressed Backend
 * 
 * This is exactly the same as the Json driver except that we compress as well.
 */
class JsonComp extends Driver
{
	protected function ext() { return 'json.gz'; }
	protected function encode($data) { return gzencode(json_encode($data)); }
	protected function decode($data) { return json_decode(gzdecode($data), true); }
}