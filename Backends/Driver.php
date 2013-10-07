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

namespace GravIT\NoSqLite\Backends;

/**
 * Abtract Class: GravIT\NoSqLite\Backends\Driver
 * =============================================================================
 * This provides some generic code that each backend class will use.
 * It also defines the abstract methods that the backend class must provide.
 */
abstract class Driver
{
	/**
	 * Property: path
	 * =========================================================================
	 * This is where the location to the database data is stored.
	 */
	protected $path;
	
	/**
	 * Property: idfile
	 * =========================================================================
	 * This is where the location to the ids file is stored.
	 */
	protected $idfile;
	
	/**
	 * Property: machineid
	 * =========================================================================
	 * This is where the servers machineid is stored.
	 */
	protected $machineid;
	
	/**
	 * Property: specialchar
	 * =========================================================================
	 * This is the special charcters used for any of the special read syntax.
	 */
	protected $specialchar = '';
	public function setSpecialChar($value) { $this->specialchar = $value; }
	
	/**
	 * Method: ext
	 * =========================================================================
	 * This must be defined by the backend class.
	 * This will set the extention.
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
	 * This method should return the file extenion the database uses.
	 */
	abstract protected function ext();
	
	/**
	 * Method: encode
	 * =========================================================================
	 * This must be defined by the backend class.
	 * This is what will actually do the final encoding / serialization
	 * before the data is written to file.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * - $data array 
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * This method should return the data encoded into a string form.
	 */
	abstract protected function encode($data);
	
	/**
	 * Method: decode
	 * =========================================================================
	 * This must be defined by the backend class.
	 * This is the exact opposite of above.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * - $data array
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * This method should return the data decoded from a string into an array.
	 */
	abstract protected function decode($data);
	
	/**
	 * Method: setPath
	 * =========================================================================
	 * This is our setter for the path property.
	 * The path passed to this method must exist and be writeable.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * - $value string This is the path to be checked.
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * - If the database path is not writeable.
	 * - If the database path does not exist.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function setPath($value)
	{
		// Does the path exist
		if (!is_dir($value))
		{
			throw new \Exception('Database path does not exist!');
		}
		
		// Can we write to it
		if (!is_writable($value))
		{
			throw new \Exception('Database path is not writeable!');
		}
		
		// Okay we are happy the database path is valid
		$this->path = $value;
		
		// Also set the path to the idfile
		$this->setIdFile();
	}
	
	/**
	 * Method: setIdFile
	 * =========================================================================
	 * This method will create / configure the id file.
	 * It also checks for any stale id files and removes them.
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
	 * n/a
	 */
	private function setIdFile()
	{
		// Create the .counters dir if it doesn't exist
		if (!is_dir($this->path.'/.counters'))
		{
			mkdir($this->path.'/.counters');
		}
		
		// Lets get some info about ourselves
		$ip = str_replace('.', '', $_SERVER['SERVER_ADDR']);
		$pid = getmypid();
		
		// Are there any old stale counters
		foreach (scandir($this->path.'/.counters') as $file)
		{
			// We only care for the ones that we created.
			if (strpos($file, $ip) !== false)
			{
				// Get the full path to the file
				$filepath = $this->path.'/.counters/'.$file;
				
				// How old is the file
				$age = time() - filemtime($filepath);
				
				// If it is older than one hour we will remove it
				if ($age > 3600)
				{
					unlink($filepath);
				}
			}
		}
		
		// Set the servers machineid
		$this->machineid = $ip.'-'.$pid;
		
		// Set the path to the id file
		$this->idfile = $this->path.'/.counters/'.$this->machineid;
	}
	
	/**
	 * Method: genId
	 * =========================================================================
	 * This method will generate a unique id we can use for a new write.
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
	 * A unique id.
	 */
	protected function genId()
	{
		// Get the current time
		$current_time = time();
		
		// Does the ids file exist
		if (file_exists($this->idfile))
		{
			// Get and parse the file content
			list($previous_time, $previous_counter) = explode
			(
				'-',
				file_get_contents($this->idfile)
			);
			
			// Check to see if the time is still valid
			if ($current_time == $previous_time)
			{
				$current_counter = $previous_counter+1;
			}
			else
			{
				$current_counter = 0;
			}
		}
		else
		{
			$current_counter = 0;
		}
		
		// Create the id
		$id = $this->machineid.'-'.$current_time.'-'.$current_counter;
		
		// Save the id
		file_put_contents($this->idfile, $current_time.'-'.$current_counter, LOCK_EX);
		
		// Return the id
		return $id;
	}
	
	/**
	 * Method: create
	 * =========================================================================
	 * This is the base create method. This uses the "encode" method provided
	 * by the backend class to save the data. If your backend requires further
	 * customisation feel free to overload this function in your class.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * - $collection string The collection path
	 * - $data array This will be encoded and saved to the file
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * - If a document already exists, this is a just in case precaution.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * Either the id of the created document or false if it failed.
	 */
	public function create($collection, $data)
	{
		// We must have at least one collection deep
		if ($collection == '')
		{
			throw new \Exception('You must define at least one collection!');
		}
		
		// Create the folder structure
		$folder = $this->path.$collection;
		if (!is_dir($folder)) mkdir($folder, 0777, true);
		
		// Get an id for the new document
		$id = $this->genId();
		
		// Build the document path
		$document = $folder.'/'.$id.'.'.$this->ext();
		
		// Lets do a final check to see if the document already exists
		if (file_exists($document))
		{
			throw new \Exception('Document already exists, check "genId()".');
		}
		else
		{
			// Save the data
			if (file_put_contents($document, $this->encode($data), LOCK_EX) !== false)
			{
				return $id;
			}
			else
			{
				return false;
			}
		}
	}
	
	/**
	 * Method: read
	 * =========================================================================
	 * This is the base read method. This uses the "decode" method provided
	 * by the backend class to read the data. If your backend requires further
	 * customisation feel free to overload this function in your class.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * - $collection string The collection path
	 * - $query array This is what we are looking for
	 * - $values array This is an array of values we want
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * An array of results
	 */
	public function read($collection, $query, $values)
	{
		// We must have at least one collection deep
		if ($collection == '')
		{
			throw new \Exception('You must define at least one collection!');
		}
		else
		{
			// Check that it actually exists
			if (!is_dir($this->path.$collection))
			{
				throw new \Exception('The collection you have requested does not exist! path:'.$this->path.$collection);
			}
		}
		
		// Start off with an empty result set
		$results = array();
		
		// Firstly lets just check if we have selected a single document
		if (isset($query['_id']))
		{
			// Built the file path
			$file = $this->path.$collection.'/'.$query['_id'].'.'.$this->ext();
			
			// Check if the file exists
			if (file_exists($file))
			{
				// Read in the document
				$data = $this->decode(file_get_contents($file));
				
				// Return the result
				$results[$query['_id']] = $data;
			}
		}
		else
		{
			// Open up the directory
			if ($handle = opendir($this->path.$collection))
			{
				// Loop through the files in the directory
				while (false !== ($entry = readdir($handle)))
				{
					// Strip out any hidden files and folders
					if (substr($entry, 0, 1) != '.' AND !is_dir($this->path.$collection.'/'.$entry)) 
					{
						// Lock the file for reading - so no one can update - mid read.
						// If we can't get a lock on the file - ie: its being written to, just skip it.
						$fh = fopen($this->path.$collection.'/'.$entry, 'r'); 
						if (flock($fh, LOCK_SH | LOCK_NB))
						{ 
							// Read in the document
							$data = $this->decode(file_get_contents($this->path.$collection.'/'.$entry));
							
							// Unlock the file
							flock($fh, LOCK_UN);
							
							// Do we actually have a query
							if (count($query) == 0)
							{
								// No so lets just return everything
								$result = $data;
							}
							else
							{
								// We do so lets do some searching
								$search = function($query) use (&$search, &$data)
								{
									// Loop through the query
									foreach ($query as $key => $value)
									{
										// We also use this a fair bit so,
										// instead of creating it each time I
										// called recursiveArraySearch we will
										// create it here.
										$it = new \RecursiveIteratorIterator
										(
										new \RecursiveArrayIterator($data),
										\RecursiveIteratorIterator::SELF_FIRST
										);
										
										// We use this a few times so I wrapped it up here
										$recursiveArraySearch = function($key, $value, $opertator = 'eq') use (&$it)
										{
											// Make sure we are at the start
											$it->rewind();
											
											// Start the loop
											while($it->valid()) 
											{
												// Make sure we are looking at the right key
												if ($it->key() == $key)
												{
													if (is_array($it->current()))
													{
														// This provides the multikey semantics
														if (in_array($value, $it->current()))
														{
															return true;
														}
														else
														{
															// Last chance, search by regular expression
															if (@preg_match($value, '') === 0)
															{
																foreach ($it->current() as $haystack)
																{
																	if (preg_match($value, $haystack) === 1) return true;
																}
															}
														}
													}
													else
													{
														$possible_find = $it->current();
													} 
												}
												elseif (strpos($key, '.') && is_array($it->current()))
												{
													// This supports dot notation for reaching inside objects
													$dot_value = $it->current();
													$dot_keys = explode('.', $key);
													foreach ($dot_keys as $dot_key)
													{
														if (isset($dot_value[$dot_key]))
														{
															$dot_value = $dot_value[$dot_key];
														}
													}
													
													$possible_find = $dot_value;
												}
												
												// What operator will we use
												if (isset($possible_find))
												{
													switch($opertator)
													{
														case 'eq': if ($possible_find == $value) return true; break;
														case 'neq': if ($possible_find != $value) return true; break;
														case 'gt': if ($possible_find > $value) return true; break;
														case 'gte': if ($possible_find >= $value) return true; break;
														case 'lt': if ($possible_find < $value) return true; break;
														case 'lte': if ($possible_find <= $value) return true; break;
													}
													
													// Last chance, search by regular expression
													if (@preg_match($value, $possible_find) === 1) return true;
												}
												
												// Bump forward one
												$it->next(); 
											}
											
											// We couldn't find anything
											return false; 
										};
										
										// What sort of query is it?
										switch ($key)
										{
											// These are your standard operators
											// NOTE: eq is implicit and the default
											case $this->specialchar.'neq':
											case $this->specialchar.'lt':
											case $this->specialchar.'lte':
											case $this->specialchar.'gt':
											case $this->specialchar.'gte':
											foreach ($value as $op_key => $op_value)
											{
												if (!$recursiveArraySearch($op_key, $op_value, $key))
												{
													return false;
												}
												break;
											}
											break;
											
											// The all operator is similar to in,
											// but instead of matching any value
											// in the specified array all values
											// in the array must be matched. 
											case $this->specialchar.'all':
												$all_found = true;
												foreach ($value as $all_key => $all_query)
												{
													foreach ($all_query as $all_value)
													{
														if (isset($all_value[$all_key])) $all_value = $all_value[$all_key];
														if (!$recursiveArraySearch($all_key, $all_value))
														{
															$all_found = false; break;
														}
													}
													break;
												}
												if (!$all_found) return false;
											break;
											
											// This provides the "in" style query
											// ie: is x in y
											case $this->specialchar.'in':
												$in_found = false;
												foreach ($value as $in_key => $in_query)
												{
													foreach ($in_query as $in_value)
													{
														if (isset($in_value[$in_key])) $in_value = $in_value[$in_key];
														if ($recursiveArraySearch($in_key, $in_value))
														{
															$in_found = true; break;
														}
													}
													break;
												}
												if (!$in_found) return false;
											break;
											
											// This provides the "nin" style query
											// ie: is x not in y
											case $this->specialchar.'nin':
												$nin_found = true;
												foreach ($value as $nin_key => $nin_query)
												{
													foreach ($nin_query as $nin_value)
													{
														if (isset($nin_value[$nin_key])) $nin_value = $nin_value[$nin_key];
														if ($recursiveArraySearch($nin_key, $nin_value))
														{
															$nin_found = false; break;
														}
													}
													break;
												}
												if (!$nin_found) return false;
											break;
											
											// This provides the "and" style query
											case $this->specialchar.'and':
												$and_found = true;
												foreach ($value as $and_query)
												{
													if(!$search($and_query))
													{
														$and_found = false; break;
													}
												}
												if (!$and_found) return false;
											break;
											
											// This provides the "or" style query
											case $this->specialchar.'or':
												$or_found = false;
												foreach ($value as $or_query)
												{
													if ($search($or_query))
													{
														$or_found = true; break;
													}
												}
												if (!$or_found) return false;
											break;
											
											// Opposite of above
											case $this->specialchar.'nor':
												$nor_found = true;
												foreach ($value as $nor_query)
												{
													if ($search($nor_query))
													{
														$nor_found = false; break;
													}
												}
												if (!$nor_found) return false;
											break;
											
											// This will match the search query exactly
											default:
												if (!$recursiveArraySearch($key, $value))
												{
													return false;
												}
										}
									}
									
									// If we get to here it means that the query must match
									return true;
								};
								
								// Kick off the search
								if ($search($query))
								{
									// The query matched
									$result = $data;
								}
								else
								{
									// The query didn't match, return nothing
									$result = array();
								}
							}
							
							// Do we have a result
							if (count($result) != 0)
							{
								// Extract the id from the filename
								$id = substr($entry, 0, strpos($entry, '.'));
								
								// Do we want all the values or just some of them
								if (count($values) == 0)
								{
									// We want all the values
									$results[$id] = $result;
								}
								else
								{
									// We only want a selection of the values
									$selection = array();
									foreach ($values as $value)
									{
										if (isset($result[$value]))
										{
											$selection[$value] = $result[$value];
										}
									}
									$results[$id] = $selection;
								}
							}
						}
						
						// Close our file handle
						fclose($fh);
					}
				}
				closedir($handle);
			}
		}
		
		// Finally return the results
		return new \GravIT\NoSqLite\Result($results);
	}
	
	/**
	 * Method: update
	 * =========================================================================
	 * This is the base update method. This uses the "encode" method provided
	 * by the backend class to update the data. If your backend requires further
	 * customisation feel free to overload this function in your class.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * - $collection string The collection path
	 * - $what array This is what we are looking for
	 * - $with array This is an array of values we want
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * An array of results
	 */
	public function update($collection, $what, $with)
	{
		// We must have at least one collection deep
		if ($collection == '')
		{
			throw new \Exception('You must define at least one collection!');
		}
		else
		{
			// Check that it actually exists
			if (!is_dir($this->path.$collection))
			{
				throw new \Exception('The collection you have requested does not exist!');
			}
		}
		
		// Start off with an empty result set
		$results = array();
		
		// Loop through each document we need to update
		foreach ($this->read($collection, $what, array()) as $id => $document)
		{
			// Loop through each change we need to make to the document
			foreach ($with as $key => $value)
			{
				// This supports dot notation
				if (strpos($key, '.'))
				{
					// This guy helps us edit the document
					// I must admit I am not entirely sure how it works but it
					// does so hence why I put it inside this function.
					$arrayset = function (&$a, $path, $value)
					{
						if(!is_array($path))
						{
							$path = explode($path[0], substr($path, 1));
						}
						$key = array_pop($path);
						foreach($path as $k)
						{
							if(!isset($a[$k])) $a[$k] = array();
							$a = &$a[$k];
						}
						$a[$key] = $value;
					};
					
					// Use the function
					$arrayset($document, explode('.', $key), $value);
				}
				else
				{
					// Make the change
					$document[$key] = $value;
				}
			}
			
			// Build the file path
			$file = $this->path.$collection.'/'.$id.'.'.$this->ext();
			
			// Save the changes - make sure we get an exclusive lock
			// so no one can read while we are updating.
			if (file_put_contents($file, $this->encode($document), LOCK_EX) !== false)
			{
				$results[$id] = $document;
			}
			else
			{
				throw new \Exception('The update failed to write!');
			}
		}
		
		// Finally return the results
		return $results;
	}
	
	/**
	 * Method: delete
	 * =========================================================================
	 * This is the base delete method. If your backend requires further
	 * customisation feel free to overload this function in your class.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * - $collection string The collection path
	 * - $what array This is what we are looking for
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * An array of results
	 */
	public function delete($collection, $what)
	{
		// Check that we actually have something to delete
		if (!is_dir($this->path.$collection))
		{
			throw new \Exception('The collection you have requested does not exist!');
		}
		
		// Do we have a query
		if (count($what) == 0)
		{
			// Okay so now all we do is recursively delete a folder
			$files = new \RecursiveIteratorIterator
			(
				new \RecursiveDirectoryIterator($this->path.$collection),
				\RecursiveIteratorIterator::CHILD_FIRST
			);
			
			// Delete everything
			foreach($files as $file)
			{
				if (substr($file, -1) != '.')
				{
					if ($file->isDir())
					{
						if (!rmdir($file)) return false;
					}
					else
					{
						if (!unlink($file)) return false;
					}
				}
			}
			
			// Delete the parent folder only if it's not the database root
			if ($collection != '')
			{
				if (!rmdir($this->path.$collection)) return false;
			}
		}
		else
		{
			// We must have at least one collection deep
			if ($collection == '')
			{
				throw new \Exception('You must define at least one collection!');
			}
			
			// We can not be lazy this time
			// we must delete a particular set of files.
			foreach ($this->read($collection, $what, array()) as $id => $document)
			{
				if (!unlink($this->path.$collection.'/'.$id.'.'.$this->ext())) return false;
			}
		}
		
		// If we get to here it all worked as expected
		return true;
	}
}