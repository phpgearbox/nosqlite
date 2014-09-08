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
	'name' => 'MongoDB',
	'iterations' => 10,
	'test' => function()
	{
		// Initate a new database
		$db = new Mongo();
		
		// Create some records
		for ($x = 1; $x <= 100; $x++)
		{
			$db->testdb->testcollection->insert([0,1,2,3,4,5,6,7,8,9]);
			$db->testdb->testcollection->insert(['a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z']);
			$db->testdb->testcollection->insert(['~','!','@','#','$','%','^','&','*','(',')','_','+','-','=','[',']','{','}']);
			$db->testdb->testcollection->insert(['name' => 'Brad Jones', 'email' => 'bj@gravit.com.au', 'office' => ['Highton', 'Melbourne']]);
		}
		
		// Read the entire database
		$db->testdb->testcollection->find();
		
		// Remove the test database
		$db->testdb->drop();
	}
]);
