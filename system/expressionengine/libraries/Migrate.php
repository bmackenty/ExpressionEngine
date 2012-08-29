<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2012, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine Update Migrate Class
 *
 * @package		ExpressionEngine
 * @subpackage	Core
 * @category	Core
 * @author		EllisLab Dev Team
 * @link		http://expressionengine.com
 */
class Migrate {
	
	function __construct()
	{
		$this->EE =& get_instance();
		$this->EE->load->dbforge();
	}

	// --------------------------------------------------------------------
	
	/**
	 * Rename table
	 *
	 * This will check to make sure the existing table name actually
	 * exists and the new table name doesn't.
	 *
	 * @access	public
	 * @param	string	the old table name
	 * @param	string	the new table name
	 */
	function rename_table($table_name, $new_table_name)
	{
		if ($this->EE->db->table_exists($table_name) AND ! $this->EE->db->table_exists($new_table_name))
		{
			return $this->EE->dbforge->rename_table($table_name, $new_table_name);
		}

		return FALSE;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Column Add
	 *
	 * Run through each column in the array to be added. For each, check 
	 * to see if column already exists in the DB. If so, skip adding that 
	 * column.
	 *
	 * @access	public
	 * @param	string	the table name
	 * @param	array	the column name with an array defining the column
	 * @param	string	the column name after which the new column will be added
	 * @return	bool
	 */
	function add_column($table = '', $field = array(), $after_field = '')
	{
		$result = FALSE;

		foreach ($field as $k => $v)
		{
			if ( ! $this->EE->db->field_exists($k, $table))
			{
				if ($this->EE->dbforge->add_column($table, array($k => $field[$k]), $after_field))
				{
					$result = TRUE;
				}

			}			
		}

		return $result;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Column Drop
	 *
	 * Drop a column in the given database table if it already exists.
	 *
	 * @access	public
	 * @param	string	the table name
	 * @param	string	the column name
	 * @return	bool
	 */
	function drop_column($table = '', $column_name = '')
	{
		if ($this->EE->db->field_exists($column_name, $table))
		{
			return $this->EE->dbforge->drop_column($table, $column_name);
		}

		return FALSE;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Column Modify
	 *
	 * Modify a database column (if it exists) with the added check that 
	 * if the column is being renamed and both current column (A) and 
	 * proposed column (B) names exist, drop column A and leave column B.
	 * 
	 * If both columns exist, it's likely this update is being run again 
	 * from a version further back than the point the DB is actually at 
	 * (an overlay, if you will). Therefore, column B is probably the one 
	 * with all the data in it, and column A has only very recently 
	 * (as in, within seconds) been created.
	 *
	 * @access	public
	 * @param	string	the table name
	 * @param	string	the column name
	 * @param	string	the column definition
	 * @return	bool
	 */
	function modify_column($table = '', $field = array())
	{
		$result = FALSE;

		foreach ($field as $k => $v)
		{
			// Check to make sure the column A exists.
			if ($this->EE->db->field_exists($k, $table))
			{
				// Check to see if the column is being renamed
				// and if column B exists, too.
				if ($k !== $v['name'] AND $this->EE->db->field_exists($v['name'], $table))
				{
					// Drop column A.
					$this->EE->dbforge->drop_column($table, $k);
				}
				else
				{
					// Rename column A -> B.
					if ($this->EE->dbforge->modify_column($table, array($k => $field[$k])))
					{
						$result = TRUE;
					}
				}

			}
		}

		return $result;

	}

	// --------------------------------------------------------------------
	
	/**
	 * Insert Set
	 *
	 * Insert values into the database, with optional unique
	 * column name/values in a given column(s).
	 *
	 * @param	string	table name
	 * @param	array	associative array of column names => row values
	 * @param	array	check for uniqueness, associative array of column
	 *                  names => row values (can only include key/value pairs from $values)
	 * @return	bool
	 */
	function insert_set($table = '', $values = array(), $unique = array())
	{
		// Check to make sure table exists
		if ($this->EE->db->table_exists($table))
		{
			// Check to make sure $unique, if present, doesn't already exist in table
			if (! empty($unique))
			{
				foreach ($unique as $k => $v)
				{
					if (array_key_exists($k, $values))
					{
						$query = $this->EE->db
										->where($k, $v)
										->get($table);

						if ($query->num_rows() > 0)
						{
							// If the unique field content already exists in this column
							// in the DB, return FALSE since this set of values cannot
							// be inserted.
							return FALSE;
						}	
					}
				}
			}
			
			$this->EE->db->set($values);			
			$this->EE->db->insert($table);			
		}

		return FALSE;

	}

	// --------------------------------------------------------------------
	
	/**
	 * Create Index
	 *
	 * Add a new index to the given database table if it doesn't already exist.
	 *
	 * @param	string	table name
	 * @param	string	column to index
	 * @param	string	index name (optional)
	 * @return	bool
	 */
	function create_index($table = '', $index_col_name = '', $index_name = '')
	{
		if ($this->EE->db->table_exists($table))
		{
			if ($index_name == '')
			{
				$index_name = $index_col_name;
			}

			// Check to make sure this index doesn't already exist.
			$query = $this->EE->db->query("SHOW INDEX FROM ".$this->EE->db->dbprefix.$table." WHERE Key_name = '".$index_name."'");

			if ($query->num_rows() == 0)
			{
				// Create index
				$sql = "CREATE INDEX ".$index_name." on ".$this->EE->db->dbprefix.$table."(".$index_col_name.")";

				if ($this->EE->db->query($sql) === TRUE)
				{
					return TRUE;
				}
			}
		}

		return FALSE;

	}

	// --------------------------------------------------------------------
	
	/**
	 * Drop Index
	 *
	 * Drop an index in the given database table if it exists.
	 *
	 * @param	string	table name
	 * @param	string	index name
	 * @return	bool
	 */
	function drop_index($table = '', $index_name = '')
	{
		if ($this->EE->db->table_exists($table))
		{
			// Check to make sure this index exists.
			$query = $this->EE->db->query("SHOW INDEX FROM ".$this->EE->db->dbprefix.$table." WHERE Key_name = '".$index_name."'");

			if ($query->num_rows() !== 0)
			{
				// Create index
				$sql = "DROP INDEX ".$index_name." on ".$this->EE->db->dbprefix.$table;

				if ($this->EE->db->query($sql) === TRUE)
				{
					return TRUE;
				}
			}
		}

		return FALSE;
	}

}

// END Migrate class


/* End of file Migrate.php */
/* Location: ./system/expressionengine/libraries/Migrate.php */