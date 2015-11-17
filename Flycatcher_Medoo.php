<?php

use Medoo;

/**
 * @author gnarm <gnarm>
 * Flycatcher_Medoo - Medoo extension to add admin functions and relational array management
 * @package Flycatcher
 */
class Flycatcher_Medoo extends Medoo {

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