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

namespace Gears\NoSqLite;

/**
 * Class: \Gears\NoSqLite\Result
 * =============================================================================
 * This class represents a result set, returned by the read functionality.
 */
class Result extends \ArrayObject
{
	// TODO: Find out if this is really needed...
	private $total_size;
	
	/**
	 * Method: __construct
	 * =========================================================================
	 * This only seems to be here for the extra count functionality I seem to
	 * have added. However I can not for the life of me remember why I added
	 * this seemingly redundant code. That will teach for not documenting
	 * this class.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $input - An array
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function __construct($input)
	{
		$this->total_size = count($input);
		parent::__construct($input);
	}
	
	// TODO: Find out if this is really needed...
	public function count()
	{
		return $this->total_size;
	}
	
	// TODO: Find out if this is really needed...
	public function size()
	{
		return parent::count();
	}
	
	/**
	 * Method: skip
	 * =========================================================================
	 * This skips over records in the result set.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $value - How many records to skip.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * self
	 */
	public function skip($value)
	{
		if ($value > 0)
		{
			$counter = 0; $results = array();
			foreach ($this->getArrayCopy() as $key => $result)
			{
				if ($counter >= $value)
				{
					$results[$key] = $result;
				}
				else
				{
					$counter++;
				}
			}
			$this->exchangeArray($results);
		}
		return $this;
	}
	
	/**
	 * Method: limit
	 * =========================================================================
	 * This limits how many records we return in the result set.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $value - How many records to return.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * self
	 */
	public function limit($value)
	{
		if ($value > 0)
		{
			$counter = 0; $results = array();
			foreach ($this->getArrayCopy() as $key => $result)
			{
				if ($counter < $value)
				{
					$results[$key] = $result;
				}
				else
				{
					break;
				}
				$counter++;
			}
			$this->exchangeArray($results);
		}
		return $this;
	}
	
	/**
	 * Method: distinct
	 * =========================================================================
	 * Finds the distinct values for a specified field across a single
	 * collection. distinct returns a document that contains an array
	 * of the distinct values.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $distinct_value - The field to collect distinct values from.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * The distinct values
	 */
	public function distinct($distinct_value)
	{
		$distinct_values = array();
		foreach ($this->getArrayCopy() as $result)
		{
			if (isset($result[$distinct_value]) && !in_array($result[$distinct_value], $distinct_values))
			{
				$distinct_values[] = $result[$distinct_value];
			}
			elseif (strpos($distinct_value, '.'))
			{
				$dot_value = $result;
				$dot_keys = explode('.', $distinct_value);
				foreach ($dot_keys as $dot_key)
				{
					if (isset($dot_value[$dot_key]))
					{
						$dot_value = $dot_value[$dot_key];
					}
					else
					{
						$dot_value = null; break;
					}
				}
				
				if (!empty($dot_value) && !in_array($dot_value, $distinct_values))
				{
					$distinct_values[] = $dot_value;
				}
			}
		}
		return $distinct_values;
	}
	
	/**
	 * Method: sort
	 * =========================================================================
	 * Controls the order that the query returns matching documents.
	 * For each field in the sort document, if the field’s corresponding
	 * value is positive, then sort() returns query results in ascending order
	 * for that attribute. If the field’s corresponding value is negative,
	 * then sort() returns query results in descending order.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $value - The sort query
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * self
	 */
	public function sort($value)
	{
		$args = array();
		$data = $this->getArrayCopy();
		foreach ($value as $sort_key => $sort_order)
		{
			$tmp = array();
			foreach ($data as $key => $row)
			{
				if (isset($row[$sort_key]))
				{
					$tmp[$key] = $row[$sort_key];
				}
				elseif (strpos($sort_key, '.'))
				{
					$dot_value = $row;
					$dot_keys = explode('.', $sort_key);
					foreach ($dot_keys as $dot_key)
					{
						if (isset($dot_value[$dot_key]))
						{
							$dot_value = $dot_value[$dot_key];
						}
						else
						{
							$dot_value = null; break;
						}
					}
					$tmp[$key] = $dot_value;
				}
				else
				{
					$tmp[$key] = null;
				}
			}
			$args[] = $tmp;
			if ($sort_order == 1) $args[] = SORT_ASC;
			elseif ($sort_order == -1) $args[] = SORT_DESC;
		}
		$args[] = &$data;
		call_user_func_array('array_multisort', $args);
		$this->exchangeArray(array_pop($args));
		return $this;
	}
}
