<?php
////////////////////////////////////////////////////////////////////////////////
// __________ __             ________                   __________              
// \______   \  |__ ______  /  _____/  ____ _____ ______\______   \ _______  ___
//  |     ___/  |  \\____ \/   \  ____/ __ \\__  \\_  __ \    |  _//  _ \  \/  /
//  |    |   |   Y  \  |_> >    \_\  \  ___/ / __ \|  | \/    |   (  <_> >    < 
//  |____|   |___|  /   __/ \______  /\___  >____  /__|  |______  /\____/__/\_ \
//                \/|__|           \/     \/     \/             \/            \/
// -----------------------------------------------------------------------------
//          Designed and Developed by Brad Jones <brad @="bjc.id.au" />         
// -----------------------------------------------------------------------------
////////////////////////////////////////////////////////////////////////////////

namespace Gears\NoSqLite\Backends;

/**
 * Class: Gears\NoSqLite\Backends\Serialize
 * =============================================================================
 * PHP Serialize Backend
 * 
 * This uses the built in PHP serialize and unserialize functions.
 * The cool thing with this is that you could potentially store not only
 * arrays but objects as well. I'll leave you to ponder the possibilities...
 */
class Serialize extends Driver
{
	protected function ext() { return 'pcereal'; }
	protected function encode($data) { return serialize($data); }
	protected function decode($data) { return unserialize($data); }
}
