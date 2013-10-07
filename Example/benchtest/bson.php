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

// Include our tester class
require('Tester.php');

// Start a new test
new Tester
([
	'name' => 'NoSqLite - BSON',
	'iterations' => 10,
	'test' => function()
	{
		// Initate a new database
		$db = new GravIT\NoSqLite\Db('../data', new GravIT\NoSqLite\Backends\Bson());
		
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