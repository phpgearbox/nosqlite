NoSqLite - A NoSQL Database, with the portability of SQLite
================================================================================
**To see this in action checkout: http://nosqlite.playground.gravit.com.au/**

What is this?
--------------------------------------------------------------------------------
I recently found MongoDB and fell in love with it, but I wanted something like
SqLite, something where I didn’t have to worry about database servers.
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
					
How do I use it?
--------------------------------------------------------------------------------
Well you can either download the zip file above or fork/clone the project on
GitHub. Composer should also work soonish too. Once you have the src included
in your project you can use it like this:

```
$db = new GravIT\NoSqLite\Db('./data');
$db->staff->create(['name' => 'Brad Jones','email' => 'bj@gravit.com.au','office' => 'Highton']);
$db->staff->read([]);
$db->staff->update(['name' => 'Brad Jones'], ['office' => 'Melbourne']);
$db->staff->delete(['name' => 'Brad Jones']);
```

This is just the most basic example, for more examples please see the index.php
file included in the root of this project. Again I promise more comprehensive
documentation will follow in the coming weeks.

Making Contributions
--------------------------------------------------------------------------------
This project is first and foremost a tool to help our team of developers create
awsome websites. Thus naturally we are going to tailor it for our use. We are
just really kind people that have decided to share our code so we feel warm
and fuzzy inside. Thats what Open Source is all about, right :)

If you feel like you have some awsome new feature, or have found a bug we
overlooked we would be more than happy to hear from you. Simply create a new
issue on the github project, including a link to a patch file if you have some
changes already developed and we will consider your request.

  - If it does not impede on our use of the software.
  - If we feel it will benefit us and/or the greater community.
  - If you make it easy for us to implement - ie: provide a patch file.
  
Then the chances are we will include your code.

--------------------------------------------------------------------------------
GravIT Pty Ltd - Developed by Brad Jones - bj@gravit.com.au