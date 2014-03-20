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
 * Class: Gears\NoSqLite\Backends\Php
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
