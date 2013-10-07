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

class Result extends \ArrayObject
{
	private $total_size;
	
	public function __construct($input)
	{
		$this->total_size = count($input);
		parent::__construct($input);
	}
	
	public function count()
	{
		return $this->total_size;
	}
	
	public function size()
	{
		return parent::count();
	}
	
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