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
	'name' => 'MySql',
	'iterations' => 10,
	'test' => function()
	{
		// Initate a new database
		$db = new mysqli('localhost', 'root', 'password');
		
		// Create our database
		$db->query('CREATE DATABASE testdb');
		$db->select_db("testdb");
		
		// Create our table
		$db->query('CREATE TABLE test (a varchar(255),b varchar(255),c varchar(255),d varchar(255),e varchar(255),f varchar(255),g varchar(255),h varchar(255),i varchar(255),j varchar(255),k varchar(255),l varchar(255),m varchar(255),n varchar(255),o varchar(255),p varchar(255),q varchar(255),r varchar(255),s varchar(255),t varchar(255),u varchar(255),v varchar(255),w varchar(255),x varchar(255),y varchar(255),z varchar(255))');
		
		// Create some records
		for ($x = 1; $x <= 100; $x++)
		{
			$db->query("INSERT INTO test (a,b,c,d,e,f,g,h,i,j) VALUES (0,1,2,3,4,5,6,7,8,9)");
			$db->query("INSERT INTO test VALUES ('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z')");
			$db->query("INSERT INTO test (a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s) VALUES ('~','!','@','#','$','%','^','&','*','(',')','_','+','-','=','[',']','{','}')");
			$db->query("INSERT INTO test (a,b,c) VALUES ('Brad Jones', 'bj@gravit.com.au', 'Highton')");
		}
		
		// Read the entire database
		$result = $db->query('SELECT * FROM test');
		$result->fetch_array(MYSQLI_ASSOC);
		
		// Remove the test database
		$db->query('DROP DATABASE testdb');
	}
]);
