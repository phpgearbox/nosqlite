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

// Get composer
require('../vendor/autoload.php');

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
		echo '<h1>'.$this->name.'</h1><hr>';
		echo 'Iterations: '.$this->iterations.'<br>';
		echo 'Average Time Per Test: '.$this->avgtime.' seconds<br>';
		echo 'Total Time Taken: '.$this->overalltime.' seconds<br>';
		echo 'Peak Memory Usage: '.memory_get_peak_usage(true).' bytes<br>';
		echo '<h2>Individual Test Times</h2><hr>';
		foreach ($this->times as $x => $time)
		{
			echo 'Test '.$x.': '.$time.' seconds<br>';
		}
	}
}