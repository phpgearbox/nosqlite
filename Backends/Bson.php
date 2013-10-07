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
 * Class: GravIT\NoSqLite\Backends\Bson
 * =============================================================================
 * Bson Backend
 * 
 * It goes without saying you will need to install the mongo php driver to get
 * access to bson_encode and bson_decode. I am sure you will work it out
 * when you get an error complaining these functions don't exist.
 */
class Bson extends Driver
{
	protected function ext() { return 'bson'; }
	protected function encode($data) { return bson_encode($data); }
	protected function decode($data) { return bson_decode($data); }
}