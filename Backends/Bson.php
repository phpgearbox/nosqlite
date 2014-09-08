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
 * Class: Gears\NoSqLite\Backends\Bson
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
