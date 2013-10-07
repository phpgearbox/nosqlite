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

namespace GravIT\NoSqLite;

/**
 * Class: GravIT\NoSqLite\Db
 * =============================================================================
 * Takeaway: A NoSQL Database, with the portability of SQLite
 * 
 * I recently found MongoDB and fell in love with it, but I wanted something
 * like SqLite, something where I didn't have to worry about database servers.
 * This is what this class is all about.
 */
class Db
{
	/**
	 * Property: backend
	 * =========================================================================
	 * This stores the backend driver class for the
	 * particular backend that we are going to use.
	 */
	private $backend;
	public function getBackend() { return $this->backend; }
	
	/**
	 * Method: __construct
	 * =========================================================================
	 * Creates a new connection to the database
	 * 
	 * Paramaters:
	 * -------------------------------------------------------------------------
	 * $path string The path to the database.
	 * 
	 * $backend The backend we wish to use, this defaults to
	 * \GravIT\NoSqLite\Backends\Json or which ever one we find is
	 * fastest in our benchmarking.
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * - If the provided backend is not of the correct type.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function __construct($path, $backend = null)
	{
		// Do we have a custom backend
		if ($backend == null)
		{
			// Lets just provide a default backend
			$this->backend = new Backends\Json();
		}
		else
		{
			// Okay so they gave us their own backend, lets check we can use it.
			if ($backend instanceof Backends\Driver)
			{
				// Cool lets just use the one that has been given to us.
				$this->backend = $backend;
			}
			else
			{
				// The one that was given is not of the correct type.
				throw new \Exception('Backend class does not extend \GravIT\NoSqLite\Backends\Driver');
			}
		}
		
		// Okay we have a backend, lets set the path to the data.
		$this->backend->setPath($path);
	}
	
	/**
	 * Method: setSpecialChar
	 * =========================================================================
	 * This sets the special character used in the read syntax.
	 * This defaults to nothing - eg: ''
	 * 
	 * Paramaters:
	 * -------------------------------------------------------------------------
	 * $value - the new value for the special characters
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function setSpecialChar($value)
	{
		// Pass on the request to the driver
		$this->backend->setSpecialChar($value);
	}
	
	/**
	 * Method: __get
	 * =========================================================================
	 * This provides the implicit interface for creating collections.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $name - This is the name of property that does not exist
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * New Collection
	 */
	public function __get($name)
	{
		return new Collection($this, $name);
	}
	
	/**
	 * Method: c
	 * =========================================================================
	 * This provides the explicit interface for creating collections.
	 * Handy if you need to supply the name of the collection as a string or array.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $name - This is the name of property that does not exist
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * New Collection
	 */
	public function c($name)
	{
		if (is_array($name))
		{
			$path = '';
			foreach ($name as $folder) { $path = $path.'/'.$folder; }
			return new Collection($this, substr($path, 1));
		}
		else
		{
			return new Collection($this, $name);
		}
	}
	
	// These are here just so we can return a more useful error for anyone learning... 
	public function create($data){ throw new \Exception('You must define at least one collection!'); }
	public function save($data){ throw new \Exception('You must define at least one collection!'); }
	public function insert($data){ throw new \Exception('You must define at least one collection!'); }
	public function read($data, $values){ throw new \Exception('You must define at least one collection!'); }
	public function find($data, $values){ throw new \Exception('You must define at least one collection!'); }
	public function update($what, $with) { throw new \Exception('You must define at least one collection!'); }
	
	/**
	 * Method: delete
	 * =========================================================================
	 * This will delete the entire database - yes the lot!!!
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * array
	 */
	public function delete()
	{
		// Pass on the request to the driver
		return $this->backend->delete('', array());
	}
	
	// Alias method
	public function remove()
	{
		// Pass on the request to the driver
		return $this->delete();
	}
}