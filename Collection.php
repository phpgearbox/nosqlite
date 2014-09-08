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

namespace Gears\NoSqLite;

/**
 * Class: \Gears\NoSqLite\Collection
 * =============================================================================
 * This class represents a collection. Used by the Db class.
 */
class Collection
{
	/**
	 * Property: db
	 * =========================================================================
	 * This is where we keep our own copy of the database class, as a reference.
	 */
	private $db;
	
	/**
	 * Property: path
	 * =========================================================================
	 * This is where we keep track of the path to the collection.
	 */
	private $path = '';
	
	/**
	 * Method: __construct
	 * =========================================================================
	 * This sets the db and root for the collection.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $db
	 * $root
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function __construct(&$db, $root)
	{
		$this->db = &$db;
		$this->path = '/'.$root;
	}
	
	/**
	 * Method: __get
	 * =========================================================================
	 * This allows us to implicitly set the collection path.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $value - The next folder.
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * self for chaining
	 */
	public function __get($value)
	{
		$this->path = $this->path.'/'.$value;
		return $this;
	}
	
	/**
	 * Method: create
	 * =========================================================================
	 * This is how you add data to the database
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $data - This must be an array, the data you would like to store.
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * boolean
	 */
	public function create($data)
	{
		// Pass on the request to the driver
		return $this->db->getBackend()->create($this->path, $data);
	}
	
	// Alias method
	public function insert($data)
	{
		return $this->create($data);
	}
	
	/**
	 * Method: read
	 * =========================================================================
	 * This is how you read data from the database
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $query - This must be an array, an empty array means "everything".
	 * $values - This must be an array
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * array
	 */
	public function read($query = array(), $values = array())
	{
		// Pass on the request to the driver
		return $this->db->getBackend()->read($this->path, $query, $values);
	}
	
	// Alias method
	public function find($query = array(), $values = array())
	{
		return $this->read($query, $values);
	}
	
	// Alias method
	// TODO: WTF Brad shouldn't this be an alias for create???
	public function save($query = array(), $values = array())
	{
		return $this->read($query, $values);
	}
	
	/**
	 * Method: update
	 * =========================================================================
	 * This is how you update data in the database
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $what - This is passed on to the read function
	 * $with - This is the new values
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * array
	 */
	public function update($what = array(), $with)
	{
		// Pass on the request to the driver
		return $this->db->getBackend()->update($this->path, $what, $with);
	}
	
	/**
	 * Method: delete
	 * =========================================================================
	 * This is how you delete data in the database
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $what - This is passed on to the read function
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * array
	 */
	public function delete($what = array())
	{
		// Pass on the request to the driver
		return $this->db->getBackend()->delete($this->path, $what);
	}
	
	// Alias method
	public function remove($what = array())
	{
		return $this->delete($what);
	}
	
	/**
	 * Method: exists
	 * =========================================================================
	 * Does the collection currently exist?
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
	 * boolean
	 */
	public function exists()
	{
		return is_dir($this->db->getRootPath().$this->path);
	}
}
