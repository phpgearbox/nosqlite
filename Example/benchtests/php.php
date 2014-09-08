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

// Include our tester class
require('Tester.php');

// Start a new test
new Tester
([
	'name' => 'NoSqLite - PHP',
	'iterations' => 10,
	'test' => function()
	{
		// Initate a new database
		$db = new Gears\NoSqLite\Db('/tmp/gears-nosqlite-test', new GravIT\NoSqLite\Backends\Php());
		
		// Create some records
		for ($x = 1; $x <= 100; $x++)
		{
			$db->test->create([0,1,2,3,4,5,6,7,8,9]);
			$db->test->create(['a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z']);
			$db->test->create(['~','!','@','#','$','%','^','&','*','(',')','_','+','-','=','[',']','{','}']);
			$db->test->create(['name' => 'Brad Jones', 'email' => 'bj@gravit.com.au', 'office' => ['Highton', 'Melbourne']]);
		}
		
		// Read the entire database
		$db->test->read();
		
		// Remove the test database
		$db->delete();
	}
]);
