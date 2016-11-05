<?php

/**
 * Class: Statistics.
 * Manages the saving / updating of statistics to the database.
 */
class Statistics {
	/**
	 * @var Reference to the database
	 */
	var $db;

	/**
	 * @var Data to enter to the statistic
	 */
	var $data = array();

	/**
	 * @var Update the data, instead of insert
	 */
	var $update = array();

	/**
	 * @var Timestamp of the statistic
	 */
	var $timestamp;

	/**
	 * Constructor: Fetch reference to the database
	 */
	function Statistics() {
		$this->db = &Database::getInstance();
	}

	/**
	 * Save the statistic to the database
	 *
	 * @param  String $type Type of the statistic
	 */
	function save( $type ) {
		$now = time();

		// force set timestamp to a different date
		if ( isset( $this->timestamp ) ) {
			$now = $this->timestamp;
			unset( $this->timestamp );
		}

		$queryUpdateId = 0;

		if ( count( $this->update ) > 0 ) {
			$where = "";
			foreach ( $this->update as $prereq ) {
				$where .= ( $where == "" ) ? "" : " AND ";
				$where .= "`" . $prereq['column'] . "`='" . $prereq['value'] . "'";
			}

			$check = $this->db->query( "SELECT `id` FROM `stats__" . $type . "` WHERE " . $where );
			if ( $row = $this->db->assoc( $check ) ) {
				$queryUpdateId = $row['id'];
			}
		}

		if ( $queryUpdateId == 0 ) {
			$query = "INSERT INTO `stats__" . $type . "`";

			$columns = array();
			$data    = array();

			foreach ( $this->data as $value ) {
				array_push( $columns, "`" . $value['column'] . "`" );
				array_push( $data, "'" . $this->db->prepare( $value['data'] ) . "'" );
			}

			$query .= " (" . implode( ", ", $columns ) . ", `timestamp`)";
			$query .= " VALUES (" . implode( ", ", $data ) . ", '$now')";

		}
		else {
			$query = "UPDATE `stats__" . $type . "` SET";

			$set = "";
			foreach ( $this->data as $value ) {
				$set .= ( $set == "" ) ? "" : ", ";
				$set .= "`" . $value['column'] . "`='" . $value['data'] . "'";
			}

			$set .= ", `timestamp`='$now'";

			$query .= " " . $set . " WHERE `id`='" . $queryUpdateId . "'";
		}

		$this->db->query( $query );
		$insert_id = $this->db->insert_id();

		// clear for next save action
		$this->data   = array();
		$this->update = array();

		return $insert_id;
	}

	/**
	 * Change from INSERT to UPDATE if conditions are met
	 *
	 * @param  String $column Column to apply check on
	 * @param  String $value  Value that has to be met before updating
	 */
	function updateIf( $column, $value ) {
		array_push( $this->update, array( "column" => $column, "value" => $value ) );
	}

	/**
	 * Add a parameter to the statistic currently working on
	 *
	 * @param   String $colum Column to add
	 * @param   String $data  Data to apply to the column
	 */
	function add( $column, $data ) {
		if ( $column == "timestamp" ) {
			return;
		}
		if ( $column == "id" ) {
			return;
		}

		foreach ( $this->data as &$row ) {
			if ( $row['column'] == $column ) {
				$row['data'] = $data;

				return;
			}
		}

		array_push( $this->data, array( 'column' => $column, 'data' => $data ) );
	}

	/**
	 * Force a different timestamp then NOW onto the statistic
	 *
	 * @param   int $value Timestamp of the required time to enforce
	 */
	function forceTimestamp( $value ) {
		$this->timestamp = $value;
	}
}

