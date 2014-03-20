<?php
////////////////////////////////////////////////////////////////////////////////
// __________ __             ________                   __________              
// \______   \  |__ ______  /  _____/  ____ _____ ______\______   \ _______  ___
//  |     ___/  |  \\____ \/   \  ____/ __ \\__  \\_  __ \    |  _//  _ \  \/  /
//  |    |   |   Y  \  |_> >    \_\  \  ___/ / __ \|  | \/    |   (  <_> >    < 
//  |____|   |___|  /   __/ \______  /\___  >____  /__|  |______  /\____/__/\_ \
//                \/|__|           \/     \/     \/             \/            \/
// =============================================================================
//          Designed and Developed by Brad Jones <brad @="bjc.id.au" />         
// =============================================================================
////////////////////////////////////////////////////////////////////////////////

namespace Gears\NoSqLite\Backends;

/**
 * Class: Gears\NoSqLite\Backends\Json
 * =============================================================================
 * Json Backend
 * 
 * This is the default backend, it's fast, it's easy to read and it's JSON yay!
 */
class Json extends Driver
{
	protected function ext() { return 'json'; }
	protected function encode($data) { return json_encode($data); }
	protected function decode($data) { return json_decode($data, true); }
}
