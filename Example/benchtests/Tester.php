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

// Load our classes - note we not actually using composer
// for this example, hence these manual require statements.
require('../../Db.php');
require('../../Collection.php');
require('../../Result.php');
require('../../Backends/Driver.php');
require('../../Backends/Json.php');
require('../../Backends/Bson.php');
require('../../Backends/JsonComp.php');
require('../../Backends/Php.php');
require('../../Backends/Serialize.php');
require('../../Backends/Xml.php');

class Tester
{
	private $name;
	private $iterations;
	private $test;
	private $overalltime;
	private $avgtime;
	private $times = array();
	
	public function __construct($config)
	{
		// Set some values
		$this->name = $config['name'];
		$this->iterations = $config['iterations'];
		$this->test = $config['test'];
		
		// Start the overall timer
		$start = microtime(true);
		
		// Run the tests
		$this->runTests();
		
		// How long did it take for all tests
		$this->overalltime = microtime(true) - $start;
		
		// Calulate the average for all tests
		$total = 0; foreach ($this->times as $time) { $total = $total + $time; }
		$this->avgtime = $total / $this->iterations;
		
		// Output the report
		$this->outputReport();
	}
	
	private function runTests()
	{
		// Loop over the provided test a number of times.
		for ($i = 1; $i <= $this->iterations; $i++)
		{
			// Start the timer for this test
			$test_start = microtime(true);
			
			// Hide any output from the actual test
			ob_start();
			call_user_func($this->test);
			ob_end_clean();
			
			// How long to do just this one test.
			$this->times[] = microtime(true) - $test_start;
		}
	}
	
	private function outputReport()
	{
		echo $this->name."\n";
		echo "================================================================================\n";
		echo 'Iterations: '.$this->iterations."\n";
		echo 'Average Time Per Test: '.$this->avgtime." seconds\n";
		echo 'Total Time Taken: '.$this->overalltime." seconds\n";
		echo 'Peak Memory Usage: '.memory_get_peak_usage(true)." bytes\n";
		echo "Individual Test Times\n";
		echo "================================================================================\n";
		foreach ($this->times as $x => $time)
		{
			echo 'Test '.$x.': '.$time." seconds\n";
		}
	}
}
