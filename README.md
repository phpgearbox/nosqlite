The NoSqLite Gear - A NoSQL Database, with the portability of SQLite
================================================================================
I recently found MongoDB and fell in love with it, but I wanted something like
SqLite, something where I didn't have to worry about database servers.
This is what this project is all about.

The NoSQL database engine is built with PHP code. Thus the server and client are
one just like SqLite. You simply give it a path to your database folder and
away you go, no server setup, no user accounts, no passwords.

Originally the project just used plain old JSON to store the data.
However it now contains many diffrent backends, so your database can be XML
based or it might use the built in PHP serialize functionality for example. 

Perhaps one day someone much clever than I may build a PHP extension.
Also in the documentation there is (will be) a specification document outlining
every detail so that other programmers can build their own drivers to read the
database format much the same way MongoDB works.

How to Install
--------------------------------------------------------------------------------
Installation via composer is easy:

	composer require gears/nosqlite:x

How do I use it?
--------------------------------------------------------------------------------
Once you have the src included in your project you can use it like this:

```
$db = new Gears\NoSqLite\Db('./data');
$db->staff->create(['name' => 'Brad Jones','email' => 'bj@gravit.com.au','office' => 'Highton']);
$db->staff->read([]);
$db->staff->update(['name' => 'Brad Jones'], ['office' => 'Melbourne']);
$db->staff->delete(['name' => 'Brad Jones']);
```

This is just the most basic example, for more examples please
see the examples folder included in the root of this project.
