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

// This page is sprinkled with some html just to make it look nice.
?>
<!DOCTYPE html>
<!--[if lt IE 7 ]><html class="no-js ie6"><![endif]--> 
<!--[if IE 7 ]><html class="no-js ie7"><![endif]--> 
<!--[if IE 8 ]><html class="no-js ie8"><![endif]--> 
<!--[if IE 9 ]><html class="no-js ie9"><![endif]--> 
<!--[if (gt IE 9)|!(IE)]><!--><html class="no-js"><!--<![endif]--> 
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <title>NoSqLite</title>
		<meta name="robots" content="index, follow">
		<meta name="keywords" content="nosqlite, nosql, sql, db, database, json, bson, xml, document">
        <meta name="description" content="A NoSQL Database, with the portability of SQLite">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
		<?php require('assets/min.php'); ?>
		<?php AssetMini::$debug = false; ?>
		<?php AssetMini::css(['bootstrap','responsive','customisations']); ?>
		<?php AssetMini::js(['modernizr']); ?>
    </head>
    <body>
        <!--[if lt IE 9]>
            <p class="chromeframe">
				You are using an <strong>outdated</strong> browser.
				Please <a href="http://browsehappy.com/">upgrade your browser</a>
				or <a href="http://www.google.com/chromeframe/?redirect=true">activate Google Chrome Frame</a>
				to improve your experience.
			</p>
        <![endif]-->

        <!-- Add your site or application content here -->
		<div class="container">
			<div class="masthead clearfix">
				<img style="width:50px;" class="pull-right img-polaroid" src="/assets/img/nosql-logo.png">
				<h3 class="muted">NoSqLite</h3>
			</div>
			<hr>
			<div class="jumbotron">
				<h1>A NoSQL Database, with the portability of SQLite!</h1>
				<p class="lead">
					I recently found MongoDB and fell in love with it, but I wanted something like
					SqLite, something where I didn't have to worry about database servers.
					This is what this project is all about.
				</p>
				<a class="btn btn-large btn-primary" href="https://github.com/GravITPtyLtd/nosqlite/archive/v1.0.zip">Download Now</a>
				<a class="btn btn-large btn-inverse" href="https://github.com/GravITPtyLtd/nosqlite">GitHub Project</a>
			</div>
			<hr>
			<div class="row-fluid">
				<div class="span6">
					<h4>What is this?</h4>
					<p>	
						The NoSQL database engine is built with PHP code. Thus the server and client are
						one just like SqLite. You simply give it a path to your database folder and
						away you go, no server setup, no user accounts, no passwords.
					</p>
					<p>	
						Originally the project just used plain old JSON to store the data.
						However it now contains many diffrent backends, so your database can be XML
						based or it might use the built in PHP serialize functionality for example. 
					</p>
					<p>	
						Perhaps one day someone much clever than I may build a PHP extension.
						Also in the documentation there is (will be) a specification document outlining
						every detail so that other programmers can build their own drivers to read the
						database format much the same way MongoDB works.
					</p>
				</div>
				<div class="span6">
					<h4>Making Contributions</h4>
					<p>
						This project is first and foremost a tool to help our team of developers create
						awsome websites. Thus naturally we are going to tailor it for our use. We are
						just really kind people that have decided to share our code so we feel warm
						and fuzzy inside. Thats what Open Source is all about, right :)
					</p>
					<p>
						If you feel like you have some awsome new feature, or have found a bug we
						overlooked we would be more than happy to hear from you. Simply create a new
						issue on the github project, including a link to a patch file if you have some
						changes already developed and we will consider your request.
					</p>
					<ul>
						<li>If it does not impede on our use of the software.</li>
						<li>If we feel it will benefit us and/or the greater community.</li>
						<li>If you make it easy for us to implement - ie: provide a patch file.</li>
					</ul>
					<p>
						Then the chances are we will include your code.
					</p>
				</div>
			</div>
			<div class="row-fluid">
				<h4>How do I use it?</h4>
				<p>
					Well you can either download the zip file above or fork/clone the project on
					GitHub. Composer should also work soonish too. Once you have the src included
					in your project you can use it like this (see line 120 onwards in this very file).
				</p>
				<p><em>What you are seeing below is the output from all the examples.</em></p>
				<hr>
<?php
/*
 * This script is used as an example of how to use the NoSqLite component.
 * Where appropriate the following examples have the SQL that you may have
 * written to do the same thing.
 */

// Load our autoloader - i love composer :)
require('./vendor/autoload.php');

// To create or connect to an existing database.
$db = new GravIT\NoSqLite\Db('./data');

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
echo '<h5>Create some documents:</h5>';
echo $db->staff->create(['name' => 'Brad Jones','email' => 'bj@gravit.com.au','office' => 'Highton']).'<br>';
echo $db->staff->create(['name' => 'Daniel Strumpel','email' => 'ds@gravit.com.au','office' => 'Highton']).'<br>';
echo $db->staff->create(['name' => 'Kath Strumpel','email' => 'ks@gravit.com.au','office' => 'Highton']).'<br>';
echo $db->staff->create(['name' => 'Benjamin Beshara','email' => 'bb@gravit.com.au','office' => 'Highton']).'<br>';

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
echo '<h5>Read some documents:</h5><pre>';
print_r($db->staff->read([]));
print_r($db->staff->read([], ['name']));
print_r($db->staff->read(['office' => 'Highton']));
print_r($db->staff->read(['office' => 'Highton'], ['email']));
echo '</pre>';

/**
 * Section: UPDATE
 * =============================================================================
 * Next up is the "update" function. Basically this just does a read and then a
 * create automaically for you. The first array provided to the update function
 * is your search query, this is passed directly to the read function.
 * 
 * The second array is the values you want to update or add to the documents
 * that were matched by the read function. The data is modified and then written
 * back to their orignal files.
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
echo '<h5>Update some documents:</h5><pre>';
print_r($db->staff->update(['name' => 'Brad Jones'], ['office' => 'Melbourne']));
echo '</pre>';

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
echo '<h5>Delete some documents:</h5><pre>';
var_dump($db->staff->delete(['name' => 'Brad Jones']));
var_dump($db->staff->delete([]));
var_dump($db->delete());
echo '</pre>';

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

echo '<h5>Advanced Examples:</h5>';

// First up you will note we need to create a new database
// because we just deleted the one from above.
$db = new GravIT\NoSqLite\Db('./data');

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

echo '<p>JOIN Example</p><pre>'; 
$managers = $db->staff->managers->read([], ['name']);
$staff_that_are_managers = $db->staff->read(['in' => ['name' => $managers]]);
print_r($staff_that_are_managers);

/*
 * And for those that like one liners. Also note that I don't have to actually
 * specify the exact value in the first query as the "in" query and most other
 * querys are smart enough to read a returned array from another read call.
 */

print_r($db->staff->read(['in' => ['name' => $db->staff->managers->read()]]));
echo '</pre>';

/*
 * Next up are the diffrent operators, you have already seen "in".
 * I know the syntax of these aren't the same as how you would read it,
 * eg: normally you would say "is x in y" but in our case we say "is in y x".
 * One day it may change but for now this is how it is.
 */

echo '<p>IN Example</p><pre>';
print_r($db->staff->read(['in' => ['name' => ['Daniel Strumpel', 'Brad Jones']]]));
echo '</pre>';

echo '<p>NOT IN Example</p><pre>';
print_r($db->staff->read(['nin' => ['name' => ['Daniel Strumpel', 'Brad Jones']]]));
echo '</pre>';

echo '<p>OR Example</p><pre>';
print_r($db->staff->read(['or' => [['name' => 'Daniel Strumpel'],['name' => 'Brad Jones']]]));
echo '</pre>';

echo '<p>NOR Example</p><pre>';
print_r($db->staff->read(['nor' => [['name' => 'Daniel Strumpel'],['name' => 'Brad Jones']]]));
echo '</pre>';

echo '<p>All Example</p><pre>';
print_r($db->articles->read(['all' => ['tags' => ['something','cool']]]));
print_r($db->articles->read(['all' => ['tags' => ['something','cool','nosql']]]));
print_r($db->articles->read(['all' => ['tags' => ['something','cool','somethingelse']]]));
echo '</pre>';

echo '<p>AND Example</p><pre>';
print_r($db->articles->read(['and' => [['tags' => 'something'],['tags' => 'cool']]]));
echo '</pre>';

echo '<p>> Example</p><pre>';
print_r($db->counter->read(['gt' => ['x' => 5]]));
echo '</pre>';

echo '<p>< Example</p><pre>';
print_r($db->counter->read(['lt' => ['x' => 5]]));
echo '</pre>';

echo '<p>>= Example</p><pre>';
print_r($db->counter->read(['gte' => ['x' => 5]]));
echo '</pre>';

echo '<p><= Example</p><pre>';
print_r($db->counter->read(['lte' => ['x' => 5]]));
echo '</pre>';

echo '<p>!= Example</p><pre>';
print_r($db->counter->read(['neq' => ['x' => 5]]));
echo '</pre>';

/*
 * Now lets say you know exactly what document you want.
 * Eg: you already know in advance what the generated id of the document is.
 */
echo '<p>_id Example</p><pre>';
$id = $db->staff->create(['name' => 'Bill Brown']);
print_r($db->staff->read(['_id' => $id]));

// The same works if you want to update it
print_r($db->staff->update(['_id' => $id], ['name' => 'Fred Green']));

// And we can delete him to
print_r($db->staff->delete(['_id' => $id]));
echo '</pre>';

/*
 * Okay now we are going to show you how dot notation works.
 * Unlike an SQL database we can have a document with many levels.
 * This is how we access those levels.
 */
echo '<p>dot Notation Example</p><pre>';
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
echo '</pre>';

// Regular expressions
echo '<p>Regular Expression Example</p><pre>';
print_r($db->staff->read(['email' => '/^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/']));
echo '</pre>';

/*
 * Alternative Collection Syntax
 * Maybe sometimes you may like to provide the path to the collection directly
 * as a string or maybe an array. This is how you can do it.
 */
echo '<p>Alternative Collection Syntax</p><pre>';
print_r($db->c('staff/managers')->read());
print_r($db->c(['staff','managers'])->read());
echo '</pre>';

/*
 * Skip, Limit, Count and Size
 */
echo '<p>Limit and Skip Examples</p><pre>';
print_r($db->staff->read()->limit(4));
print_r($db->staff->read()->skip(2)->limit(4));
echo $db->staff->read()->skip(2)->limit(4)->size().'<br>';
echo $db->staff->read()->skip(2)->limit(4)->count().'<br>';
echo '</pre>';

/*
 * Distinct
 */
echo '<p>Distinct Example</p><pre>';
print_r($db->staff->read()->distinct('postcode'));
echo '</pre>';

/*
 * Sorting examples.
 */
echo '<p>Sorting Example</p><pre>';
print_r($db->staff->read()->sort(['name' => 1]));
echo '</pre>';

// Delete everything again
$db->delete();
?>
			</div>
			<hr>
			<div class="footer">
				<p>
					&copy GravIT Pty Ltd <?php echo date('Y'); ?>
					- Developed by Brad Jones
					- <a href="mailto:bj@gravit.com.au">bj@gravit.com.au</a>
				</p>
			</div>
			
		</div>
		
		<!-- Load our Javascript -->
		<?php AssetMini::js(['jquery','bootstrap','main']); ?>
    </body>
</html>