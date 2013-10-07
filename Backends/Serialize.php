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
 * Class: GravIT\NoSqLite\Backends\Serialize
 * =============================================================================
 * PHP Serialize Backend
 * 
 * This uses the built in PHP serialize and unserialize functions.
 * The cool thing with this is that you could potentioaly store not only
 * arrays but objects as well. I'll leave you to ponder the possibilities...
 */
class Serialize extends Driver
{
	protected function ext() { return 'pcereal'; }
	protected function encode($data) { return serialize($data); }
	protected function decode($data) { return unserialize($data); }
}