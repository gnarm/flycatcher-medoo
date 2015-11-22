<?php

namespace Flycatcher\Medoo;
use Medoo;
use Exception;
use PDO;

/**
 * @author gnarm <gnarm>
 * Flycatcher - Medoo-extended interoperable database toolset and auto-generator.
 * @package Flycatcher
 */
class Flycatcher_Medoo extends Medoo {

	/**
	 * Convert a Flycatcher_Medoo-specific option array into a proper SQL syntax.
	 * @param array $options - The Flycatcher option array.
	 * @return string
	 */
	private function _mapOptions(array $options) {

		/*
		 * ["type" => "VARCHAR",
		 *  "length" => 255]
		 */

		$this->_demandOptions($options);

		/* Start an empty option query.*/
		$option_query = "";

		/* Add ( and ) brackets around the length number, if specified. */
		$length_with_brackets = isset($options["length"]) ? "(" . $options["length"] . ")" : null;

		/* Construct part of the options query for type and length. */
		$option_query .= $options["type"] . $length_with_brackets;

		/* Now add other option values into $option_query, in this order. */
		$option_query .= (isset($options["unsigned"]) && $options["unsigned"] === true) ? " UNSIGNED" : null;
		$option_query .= (isset($options["primary_key"]) && $options["primary_key"] === true) ? " PRIMARY KEY" : null;
		$option_query .= (isset($options["auto_increment"]) && $options["auto_increment"] === true) ? " AUTO_INCREMENT" : null;

		/* Return the constructed query part. */
		return $option_query;

	}

	/**
	 * Ensure that certain options are set in the Flycatcher_Medoo array.
	 * @param array $options - The $options array processed by the above _mapOptions() function.
	 * @throws Exception
	 * @return void
	 */
	private function _demandOptions(array $options) {

		/* Make sure that "type" is set. */
		if (!isset($options["type"])) {

			throw new Exception("You must specify the \"type\" for the column! E.g. VARCHAR, INT...");

		}

		/* Make sure that "length" is int if set. If "length" is set and if it's not an int, throw an exception. */
		if (isset($options["length"]) && !is_int($options["length"])) {

			throw new Exception("\"length\" must be an integer!");

		}

	}

	/**
	 * Check if table exists from INFORMATION_SCHEMA.
	 * @param $table_name
	 * @return boolean
	 */
	public function exists($table_name) {

		/* Perform an if exists SQL query, and make it print "TRUE" (anything, really) if it matches. If not, nothing
		will be returned and it would be seen as FALSE. Check from INFORMATION_SCHEMA.TABLES. */
		$query = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . $this->database_name
		. "' AND TABLE_NAME = '" . $table_name . "'";

		/* Memo: $this->exec returns the number of rows affected. $this->query returns what SQL (PDO) is meant to
		return. */

		/* As Medoo doesn't directly support a fetchAll,specific function, we'll use one here. Get fetchAll and store
		that as $result. */
		$result = $this->query($query)->fetchAll();

		/* The number of rows affected is in index [0][0], store that as $rows. It's returned as a string, so
		convert it as integer. */
		$rows = (int) $result[0][0];

		/* If $rows evaluates to TRUE, return TRUE. Otherwise FALSE. */
		if ($rows) {
			return TRUE;
		} else {
			return FALSE;
		}

	}

	/**
	 * Create a table.
	 * @param string $table_name
	 * @param array $columns
	 * @param array $table_options
	 * @return PDO
	 */
	public function create($table_name, array $columns, array $table_options = null) {

		/*
		 * ["id" => [
		 *      "type" => "VARCHAR"
		 *  ]
		 */

		/* CREATE TABLE IF NOT EXISTS exampletable ( Column1 int, FirstName VARCHAR(255), LastName VARCHAR(255)) */
		$query = "CREATE TABLE IF NOT EXISTS " . $table_name . " (";

		/* Columns are jagged arrays. */
		foreach ($columns as $column_name => $options) {

			/* We need to construct the proper query string after ( to include all the desired rows as supplied in
			$columns. $this->_mapOptions requires an array, so type cast an object into an array. */
			$query .= $column_name . " " . $this->_mapOptions((array) $options) . ", ";

		}

		/* Remove the last comma and space as no further entries appear after that. */
		$query = substr($query, 0, -2);

		/* Close the rest of the query.*/
		$query .= "); ";

		/* Perform the query as exec and return the affected rows number. */
		return $this->exec($query);

	}

	/**
	 * Improved insert() function to also allow arrays in $data values.
	 * @param string $table
	 * @param array $data
	 * @return void
	 */
	public function insert($table, array $data) {

		/* We are going to check if $data values have at least one array. Set this to FALSE by default. */
		$has_array = FALSE;

		/* Check if $data values has at least one array. */
		foreach ($data as $column_name => $value) {

			/* Flags with (JSON) and (SERIALIZE) mean that the original Medoo can handle these arrays by itself. JSON is parsed separately, and SERIALIZE just informs the new insert() to not to be processed by this foreach. strpos can help us determine whether the desired string actually exists in $column_name. Not FALSE = string exists. */
			if (strpos($column_name, "(JSON)") !== FALSE || strpos($column_name, "(SERIALIZE)") !== FALSE) {

				/* Make no difference to the current row - continue. */
				continue;

			}

			/* Now if $value is an array, perform a special operation: insert to the database in a relational method. */
			if (is_array($value)) {

				/* Toggle $has_array to TRUE to avoid executing the normal Medoo insert() - to avoid duplicates, we need not to use it in this case. */
				$has_array = TRUE;

				/* Now let's start constructing new data arrays which are exactly the same, except that the value array members are different. */
				foreach ($value as $value_member) {

					/* The $relational_array is copied from $data. */
					$relational_array = $data;

					/* But for where we found the $value array, replace that with the current single $value_member value (even this could be a new array!). Point to it by specifying the current $column_name to find it. */
					$relational_array[$column_name] = $value_member;

					/* Perform this insert() function again. This goes on as many times as there are no more value arrays. */
					$this->insert($table, $relational_array);

				}

			}

		}

		/* If no arrays were found, perform the original Medoo insert operation. Eventually the script should get here - this is the one which inserts data to the database. */
		if (!$has_array) {

			/* Since the original Medoo doesn't support (SERIALIZE), remove those before igniting the original Medoo. */
			foreach ($data as $column_name => $value) {

				/* If (SERIALIZE) is in the current $column_name...*/
				if (strpos($column_name, "(SERIALIZE)") !== FALSE) {

					/* Replace it with null, and store it into $data. */
					$new_column_name = str_replace("(SERIALIZE)", null, $column_name);
					$data[$new_column_name] = $value;
					unset($data[$column_name]);

				}

			}

			/* Now the $table is perfect for the original Medoo. */
			parent::insert($table, $data);

		}

	}

}
