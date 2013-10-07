<?php
////////////////////////////////////////////////////////////////////////////////
// __________ __             ________                   __________              
// \______   \  |__ ______  /  _____/  ____ _____ ______\______   \ _______  ___
//  |     ___/  |  \\____ \/   \  ____/ __ \\__  \\_  __ \    |  _//  _ \  \/  /
//  |    |   |   Y  \  |_> >    \_\  \  ___/ / __ \|  | \/    |   (  <_> >    < 
//  |____|   |___|  /   __/ \______  /\___  >____  /__|  |______  /\____/__/\_ \
//                \/|__|           \/     \/     \/             \/            \/
// =============================================================================
//         Designed and Developed by Brad Jones <bj @="gravit.com.au" />        
// =============================================================================
////////////////////////////////////////////////////////////////////////////////

namespace Gears\NoSqLite\Backends;

/**
 * Class: Gears\NoSqLite\Backends\JsonComp
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
