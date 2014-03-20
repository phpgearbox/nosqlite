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

/*
 * This script is used as an example of how to use the NoSqLite component.
 * Where appropriate the following examples have the SQL that you may have
 * written to do the same thing.
 */

// Load our classes - note we not actually using composer
// for this example, hence these manual require statements.
require('../Db.php');
require('../Collection.php');
require('../Result.php');
require('../Backends/Driver.php');
require('../Backends/Json.php');

// To create or connect to an existing database.
$db = new Gears\NoSqLite\Db('/tmp/gears-nosqlite-test');

/**
 * Section: CREATE
 * =============================================================================
 * The first method is "create", this is how you add stuff to your database.
 * Note that the creation of collections or tables in sql speak is implicit.
 * Collections are really just folders on the filesystem in our case.
 * 
 * Example of SQL:
 * -----------------------------------------------------------------------------
 * INSERT INTO staff (name, email, office)
 * VALUES (Brad Jones, bj@gravit.com.au, Highton)
 * 
 * Returns:
 * -----------------------------------------------------------------------------
 * The unique id of the new document you just created, this is in fact the
 * file name of the JSON, XML, BSON, etc document. If it could not create the
 * file for whatever reason it will either throw an exception or return false.
 */
echo "Create some documents:\n";
echo "================================================================================\n";
echo $db->staff->create(['name' => 'Brad Jones','email' => 'bj@gravit.com.au','office' => 'Highton'])."\n";
echo $db->staff->create(['name' => 'Daniel Strumpel','email' => 'ds@gravit.com.au','office' => 'Highton'])."\n";
echo $db->staff->create(['name' => 'Kath Strumpel','email' => 'ks@gravit.com.au','office' => 'Highton'])."\n";
echo $db->staff->create(['name' => 'Benjamin Beshara','email' => 'bb@gravit.com.au','office' => 'Highton'])."\n";
echo "\n";

/**
 * Section: READ
 * =============================================================================
 * Once we have created some documents or rows in sql speak. We usually want to
 * search for and read that data at some point in the future. This next function
 * is the eqilivant of the sql SELECT keyword.
 * 
 * Note anywhere you see an empty array you can omit it but it is shown here
 * for completeness sake, just so you have a better idea on what is happening.
 * 
 * The first array provided to the read function is your search query.
 * The second array is the values you want out of the matched documents.
 * 
 * Example of SQL:
 * -----------------------------------------------------------------------------
 * SELECT * FROM staff
 * SELECT name FROM staff
 * SELECT * FROM staff WHERE office = Highton
 * SELECT email FROM staff WHERE office = Highton
 * 
 * Returns:
 * -----------------------------------------------------------------------------
 * A multi-dimensional array - ie: exactly what you created above.
 * Well not exactly the same, we also return the id of the document in case
 * you want that as well.
 */
echo "Read some documents:\n";
echo "================================================================================\n";
print_r($db->staff->read([]));
print_r($db->staff->read([], ['name']));
print_r($db->staff->read(['office' => 'Highton']));
print_r($db->staff->read(['office' => 'Highton'], ['email']));
echo "\n";

/**
 * Section: UPDATE
 * =============================================================================
 * Next up is the "update" function. Basically this just does a read and then a
 * create automatically for you. The first array provided to the update function
 * is your search query, this is passed directly to the read function.
 * 
 * The second array is the values you want to update or add to the documents
 * that were matched by the read function. The data is modified and then written
 * back to their original files.
 * 
 * Example of SQL:
 * -----------------------------------------------------------------------------
 * UPDATE staff SET office = Melbourne WHERE name = Brad Jones
 * 
 * Returns:
 * -----------------------------------------------------------------------------
 * This will return the updated data that was successfully
 * written back to the filesystem.
 */
echo "Update some documents:\n";
echo "================================================================================\n";
print_r($db->staff->update(['name' => 'Brad Jones'], ['office' => 'Melbourne']));
echo "\n";

/**
 * Section: DELETE
 * =============================================================================
 * Finally this is how we delete something in our database.
 * 
 * Example of SQL:
 * -----------------------------------------------------------------------------
 * DELETE FROM staff WHERE name = Brad Jones
 * DROP TABLE staff
 * DROP DATABASE dbname
 * 
 * Returns:
 * -----------------------------------------------------------------------------
 * This will return the updated data that was successfully
 * written back to the filesystem.
 */
echo "Delete some documents:\n";
echo "================================================================================\n";
var_dump($db->staff->delete(['name' => 'Brad Jones']));
var_dump($db->staff->delete([]));
var_dump($db->delete());
echo "\n";

/**
 * Section: Advanced Examples
 * =============================================================================
 * The above is easy stuff, below are some more examples that you might not use
 * as often as the above but still just as valid.
 * 
 * Example of SQL:
 * -----------------------------------------------------------------------------
 * This time some of the examples don't really have any sort of equivalent in
 * SQL thus I won't be providing any. At this point I am assuming your smart
 * enough to work it out for your self :)
 */

echo "Advanced Examples:\n";
echo "================================================================================\n";

// First up you will note we need to create a new database
// because we just deleted the one from above.
$db = new Gears\NoSqLite\Db('/tmp/gears-nosqlite-test');

// First lets create some data to work with - yep you have seen this before... easy right?
$db->staff->create(['name' => 'Brad Jones','email' => 'bj@gravit.com.au','office' => 'Highton','postcode' => '3220']);
$db->staff->create(['name' => 'Daniel Strumpel','email' => 'ds@gravit.com.au','office' => 'Highton','postcode' => '3220']);
$db->staff->create(['name' => 'Kath Strumpel','email' => 'ks@gravit.com.au','office' => 'Highton','postcode' => '3221']);
$db->staff->create(['name' => 'Benjamin Beshara','email' => 'bb@gravit.com.au','office' => 'Highton','postcode' => '3221']);

// We also use these down the road too.
$db->articles->create(['name' => 'NoSQL Release','author' => 'Brad Jones','tags' => ['something','cool','nosql']]);
$db->articles->create(['name' => 'Server 2012','author' => 'Daniel Strumpel','tags' => ['something','cool','server-2012']]);
$db->articles->create(['name' => 'My Apple Mac','author' => 'Benjamin Beshara','tags' => ['something','cool','apple-mac']]);

// We need something with numbers
$db->counter->create(['x' => 1]);
$db->counter->create(['x' => 2]);
$db->counter->create(['x' => 3]);
$db->counter->create(['x' => 4]);
$db->counter->create(['x' => 5]);
$db->counter->create(['x' => 6]);
$db->counter->create(['x' => 7]);
$db->counter->create(['x' => 8]);
$db->counter->create(['x' => 9]);
$db->counter->create(['x' => 10]);

/*
 * Now just to make it very clear this is not a relational database.
 * There are no primary keys, there is no "JOIN" keyword or anything of
 * the sort. But we can still achieve the same sort of functionality.
 */

$db->staff->managers->create(['name' => 'Daniel Strumpel']);

/*
 * All I have done is create a new collection with one document.
 * This new collection is in no way linked to the parent staff collection.
 * Now watch and learn...
 */

echo "JOIN Example\n";
echo "================================================================================\n";
$managers = $db->staff->managers->read([], ['name']);
$staff_that_are_managers = $db->staff->read(['in' => ['name' => $managers]]);
print_r($staff_that_are_managers);

/*
 * And for those that like one liners. Also note that I don't have to actually
 * specify the exact value in the first query as the "in" query and most other
 * querys are smart enough to read a returned array from another read call.
 */

print_r($db->staff->read(['in' => ['name' => $db->staff->managers->read()]]));
echo "\n";

/*
 * Next up are the diffrent operators, you have already seen "in".
 * I know the syntax of these aren't the same as how you would read it,
 * eg: normally you would say "is x in y" but in our case we say "is in y x".
 * One day it may change but for now this is how it is.
 */

echo "IN Example\n";
echo "================================================================================\n";
print_r($db->staff->read(['in' => ['name' => ['Daniel Strumpel', 'Brad Jones']]]));
echo "\n";

echo "NOT IN Example\n";
echo "================================================================================\n";
print_r($db->staff->read(['nin' => ['name' => ['Daniel Strumpel', 'Brad Jones']]]));
echo "\n";

echo "OR Example\n";
echo "================================================================================\n";
print_r($db->staff->read(['or' => [['name' => 'Daniel Strumpel'],['name' => 'Brad Jones']]]));
echo "\n";

echo "NOR Example\n";
echo "================================================================================\n";
print_r($db->staff->read(['nor' => [['name' => 'Daniel Strumpel'],['name' => 'Brad Jones']]]));
echo "\n";

echo "All Example\n";
echo "================================================================================\n";
print_r($db->articles->read(['all' => ['tags' => ['something','cool']]]));
print_r($db->articles->read(['all' => ['tags' => ['something','cool','nosql']]]));
print_r($db->articles->read(['all' => ['tags' => ['something','cool','somethingelse']]]));
echo "\n";

echo "AND Example\n";
echo "================================================================================\n";
print_r($db->articles->read(['and' => [['tags' => 'something'],['tags' => 'cool']]]));
echo "\n";

echo "> Example\n";
echo "================================================================================\n";
print_r($db->counter->read(['gt' => ['x' => 5]]));
echo "\n";

echo "< Example\n";
echo "================================================================================\n";
print_r($db->counter->read(['lt' => ['x' => 5]]));
echo "\n";

echo ">= Example\n";
echo "================================================================================\n";
print_r($db->counter->read(['gte' => ['x' => 5]]));
echo "\n";

echo "<= Example\n";
echo "================================================================================\n";
print_r($db->counter->read(['lte' => ['x' => 5]]));
echo "\n";

echo "!= Example\n";
echo "================================================================================\n";
print_r($db->counter->read(['neq' => ['x' => 5]]));
echo "\n";

/*
 * Now lets say you know exactly what document you want.
 * Eg: you already know in advance what the generated id of the document is.
 */
echo "_id Example\n";
echo "================================================================================\n";
$id = $db->staff->create(['name' => 'Bill Brown']);
print_r($db->staff->read(['_id' => $id]));

// The same works if you want to update it
print_r($db->staff->update(['_id' => $id], ['name' => 'Fred Green']));

// And we can delete him to
print_r($db->staff->delete(['_id' => $id]));
echo "\n";

/*
 * Okay now we are going to show you how dot notation works.
 * Unlike an SQL database we can have a document with many levels.
 * This is how we access those levels.
 */
echo "dot Notation Example\n";
echo "================================================================================\n";
$db->staff->create
([
	'name' => 'Jim Brown',
	'email' => 'jb@gravit.com.au',
	'office' => 'Melbourne',
	'address' =>
	[
		'street' =>
		[
			'no' => '567',
			'name' => 'Bourke',
			'type' => 'ST'
		]
	]
]);

// Now you can't do that with SQL, pretty neat huh!
// Now how do we say get all staff that work on Bourke street.
// Easy right something like this...
print_r($db->staff->read(['address' => ['street' => ['name' => 'Bourke']]]));

// Unfortantly the above doesn't work, what the above is
// asking for is a document that looks like this exactly:
[
	'address' =>
	[
		'street' =>
		[
			'name' => 'Bourke'
		]
	]
];

// What we really need to do is this.
print_r($db->staff->read(['address.street.name' => 'Bourke']));
echo "\n";

// Regular expressions
echo "Regular Expression Example\n";
echo "================================================================================\n";
print_r($db->staff->read(['email' => '/^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/']));
echo "\n";

/*
 * Alternative Collection Syntax
 * Maybe sometimes you may like to provide the path to the collection directly
 * as a string or maybe an array. This is how you can do it.
 */
echo "Alternative Collection Syntax\n";
echo "================================================================================\n";
print_r($db->c('staff/managers')->read());
print_r($db->c(['staff','managers'])->read());
echo "\n";

/*
 * Skip, Limit, Count and Size
 */
echo "Limit and Skip Examples\n";
echo "================================================================================\n";
print_r($db->staff->read()->limit(4));
print_r($db->staff->read()->skip(2)->limit(4));
echo $db->staff->read()->skip(2)->limit(4)->size()."\n";
echo $db->staff->read()->skip(2)->limit(4)->count()."\n";
echo "\n";

/*
 * Distinct
 */
echo "Distinct Example\n";
echo "================================================================================\n";
print_r($db->staff->read()->distinct('postcode'));
echo "\n";

/*
 * Sorting examples.
 */
echo "Sorting Example\n";
echo "================================================================================\n";
print_r($db->staff->read()->sort(['name' => 1]));
echo "\n";

// Delete everything again
$db->delete();
