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
 * Class: GravIT\NoSqLite\Backends\Php
 * =============================================================================
 * Php Backend
 * 
 * Okay now this one is probably a little controversial,
 * yes it uses the evil eval. If you don't like it don't use it pretty simple.
 * I really only created this as something to compare against in my benchmarking.
 */
class Php extends Driver
{
	protected function ext() { return 'php'; }
	protected function encode($data) { return '<?php return '.var_export((array)$data, true).';'; }
	protected function decode($data) { return eval(substr($data, 6)); }
}