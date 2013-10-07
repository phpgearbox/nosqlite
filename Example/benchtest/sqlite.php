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
	'name' => 'Sqlite3',
	'iterations' => 10,
	'test' => function()
	{
		// Initate a new database
		$db = new SQLite3('../data/sqlite3.db');
		
		// Create our table
		$db->exec('CREATE TABLE test (a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w,x,y,z)');
		
		// Create some records
		for ($x = 1; $x <= 100; $x++)
		{
			$db->exec("INSERT INTO test (a,b,c,d,e,f,g,h,i,j) VALUES (0,1,2,3,4,5,6,7,8,9)");
			$db->exec("INSERT INTO test VALUES ('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z')");
			$db->exec("INSERT INTO test (a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s) VALUES ('~','!','@','#','$','%','^','&','*','(',')','_','+','-','=','[',']','{','}')");
			$db->exec("INSERT INTO test (a,b,c) VALUES ('Brad Jones', 'bj@gravit.com.au', 'Highton')");
		}
		
		// Read the entire database
		$db->query('SELECT * FROM test')->fetchArray();
		
		// Remove the test database
		unlink('../data/sqlite3.db');
	}
]);