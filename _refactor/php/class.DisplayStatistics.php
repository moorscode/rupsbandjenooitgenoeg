<?php

require_once( "../../php/class.Database.php" );
require_once( "../../php/class.XMLDocument.php" );

class DisplayStatistics {
	protected $db;

	protected $xml;
	protected $values = array();

	protected $searchTable;

	protected $startTime;
	protected $endTime;
	protected $timeStep;

	protected $datasets = array();
	protected $dataMin = 0;
	protected $dataMax = 0;
	protected $dataSteps = 5;

	protected $width;
	protected $height;

	protected $axisMin;
	protected $axisMax;
	protected $_axisType;
	protected $_compareType;
	protected $suffix = '';

	protected $chartType;
	protected $category_labels = 20;

	protected $where = array();

	protected $chart_data;

	const SECOND = 1;
	const MINUTE = 60;
	const HOUR = 3600;         // 60 minutes :p
	const DAY = 86400;     // 24 hours
	const WEEK = 604800;     // 7 days
	const MONTH = 2678400;     // 31 days
	const YEAR = 31536000; // yes, yes, 29th of feb.. whatever == 365 days

	const COMPARE_COUNT = 'count';
	const COMPARE_AVERAGE = 'average';

	const DISPLAY_SECONDS = 'seconds';
	const DISPLAY_NUMBER = 'number';

	public function DisplayStatistics( $table, $prefix = 'stats__' ) {
		/* Get a database instance */
		$this->db = &Database::getInstance();

		/* Initialize new XML Document */
		$this->xml = new XMLDocument();
		$this->xml->root( $this->xml->item( "chart" ) );

		$this->item( null, 'chart_rect', null, array( 'x' => 50, 'y' => 50, 'width' => 700, 'height' => 150 ) );
		$this->item( null, 'chart_pref', null, array( 'rotation_x' => 1, 'rotation_y' => 1 ) );
		$this->item( null, 'chart_label', null, array( 'position' => 'cursor' ) );

		//$this->item(null, 'tooltip', null, array('color'=>'FFFFFF', 'alpha'=>75, 'size'=>11, 'background_color_1'=>'000000', 'background_color_2'=>'333333', 'background_alpha'=>90, 'shadow'=>'low'));

		$this->item( null, 'context_menu', null, array(
			'full_screen' => 'true',
			'about'       => 'false',
			'print'       => 'false'
		) );

		$sc = $this->item( null, 'series_color' );
		$this->item( $sc, 'color', '003366' );
		$this->item( $sc, 'color', 'C6BE8C' );
		$this->item( $sc, 'color', '990000' );
		$this->item( $sc, 'color', '520000' );
		$this->item( $sc, 'color', 'CC6600' );
		$this->item( $sc, 'color', 'BFBFC1' );
		$this->item( $sc, 'color', '808080' );

		$this->item( null, 'legend', null, array( 'font' => 'arial', 'size' => 12, 'bold' => 'true' ) );

		$this->item( null, 'chart_guide', null, array(
			'horizontal'       => 'true',
			'vertical'         => 'false',
			'thickness'        => 1,
			'color'            => 'ff4400',
			'alpha'            => 75,
			'type'             => 'dashed',
			'radius'           => 8,
			'fill_alpha'       => 100,
			'line_color'       => 'ff4400',
			'line_alpha'       => 75,
			'line_thickness'   => 4,
			'font'             => 'arial',
			'size'             => 10,
			'text_color'       => 'ffffff',
			'background_color' => 'ff4400',
			'text_h_alpha'     => 90,
			'text_v_alpha'     => 90
		) );

		$this->item( null, 'axis_value', null, array(
			'prefix'       => '',
			'suffix'       => '',
			'decimals'     => 0,
			'decimal_char' => '.',
			'seperator'    => '',
			'show_min'     => 'true',
			'font'         => 'arial',
			'size'         => 10,
			'bold'         => 'true',
			'color'        => '000000',
			'alpha'        => 90,
			'orientation'  => ''
		) );

		$this->item( null, 'axis_category', null, array(
			'font'        => 'arial',
			'size'        => 9,
			'bold'        => 'true',
			'color'       => '000000',
			'alpha'       => 90,
			'orientation' => 'vertical_down'
		) );

		/* Place chat_data holder */
		$this->chart_data = $this->item( null, "chart_data" );

		/* Set default time range */
		$this->timeRange( 0, time() );

		/* Set table */
		$this->table( $table, $prefix );

		/* Set defaults */
		$this->compareType();
		$this->maxCategoryLabels();
	}

	/* Set search parameters for the SQL statement */
	public function table( $table, $prefix = 'stats__' ) {
		$this->searchTable = $prefix . $table;
	}

	/* Set custom -where- parameters */
	public function parameters( $column, $value ) {
		$this->where[ $column ] = $value;
	}

	/* Force-set chart_type */
	public function type( $type ) {
		$this->chartType = $type;
	}

	public function width( $value ) {
		$this->_width = $value;
	}

	public function height( $value ) {
		$this->_height = $value;
	}

	public function size( $width, $height ) {
		$this->width( $width );
		$this->height( $height );
	}

	public function axisRange( $min, $max ) {
		$this->axisMin = floor( $min );
		$this->axisMax = ceil( $max );
	}

	public function axisType( $type ) {
		$this->_axisType = $type;
		$this->suffix    = ( $this->suffix == null ) ? ( ( $type == DisplayStatistics::DISPLAY_SECONDS ) ? ' sec' : '' ) : $this->suffix;
	}

	public function axisSuffix( $suffix ) {
		$this->suffix = $suffix;
	}

	public function maxCategoryLabels( $value = 20 ) {
		$this->category_labels = $value;
	}

	public function rotation3d( $rotationX, $rotationY ) {
		$index = $this->get( null, "chart_pref" );
		if ( $index > -1 ) {
			$chart_pref                             = &$this->values[ $index ];
			$chart_pref['attributes']['rotation_x'] = $rotationX;
			$chart_pref['attributes']['rotation_y'] = $rotationY;
		}
	}

	/* Set the time range to analyze - timestamp format */
	public function timeRange( $start, $end, $step = DisplayStatistics::DAY ) {
		$this->startTime = $start;
		$this->endTime   = $end;
		$this->timeStep  = $step;
	}

	/* Add a new item */
	public function item( $target, $name, $value = null, $attributes = null ) {
		$index = array_push( $this->values, array(
				"target"     => $target,
				"name"       => $name,
				"value"      => $value,
				"attributes" => $attributes
			) ) - 1;

		return $index;
	}

	/* Get the index of an added item */
	public function get( $target, $name ) {
		foreach ( $this->values as $index => $value ) {
			if ( $value['target'] == $target && $value['name'] == $name ) {
				return $index;
			}
		}

		return -1;
	}

	/* Set comparison type for this statistic */
	public function compareType( $type = DisplayStatistics::COMPARE_COUNT ) {
		$this->_compareType = $type;
	}

	/* Create dataset */
	public function dataset( $title, $parameter = null, $customHandleFunction = null ) {
		// compareTypes, average / count
		array_push( $this->datasets, array(
			"title"                => $title,
			"parameter"            => $parameter,
			"customHandleFunction" => $customHandleFunction,
			"results"              => array(),
			"data"                 => ''
		) );
	}

	protected function readibleTime( $value ) {
		$periods        = array( "seconde", "minuut", "uur", "dag", "week", "maand", "jaar", "decennium", "eeuw" );
		$periods_plural = array(
			"seconden",
			"minuten",
			"uren",
			"dagen",
			"weken",
			"maanden",
			"jaren",
			"decennia",
			"eeuwen"
		);
		$lengths        = array( "60", "60", "24", "7", "4.35", "12", "10", "10" );

		$difference = $value;

		for ( $j = 0; $difference >= $lengths[ $j ] && $j < count( $lengths ); $j++ ) {
			$difference /= $lengths[ $j ];
		}

		$difference = round( $difference );

		if ( $difference != 1 ) {
			$period = $periods_plural[ $j ];
		}
		else {
			$period = $periods[ $j ];
		}

		return "$difference $period";
	}

	/* Build dataset(s) data */
	protected function generate() {
		// create SQL statement

		$step  = 0;
		$steps = array();
		$prevousStepStamp;

		$where = '';
		if ( count( $this->where ) > 0 ) {
			foreach ( $this->where as $column => $value ) {
				$where .= "AND `$column`='$value'";
			}
		}

		$sql   = "SELECT * FROM `" . $this->searchTable . "` WHERE `timestamp` >= '" . $this->startTime . "' AND `timestamp` <= '" . $this->endTime . "' $where ORDER BY `timestamp` DESC";
		$query = $this->db->query( $sql );

		while ( $row = $this->db->assoc( $query ) ) {

			if ( $prevousStepStamp == null || $prevousStepStamp - intval( $row['timestamp'] ) >= $this->timeStep ) {
				$prevousStepStamp = $row['timestamp'];

				$step += 1;
				array_push( $steps, array( "step" => $step, "time" => $row['timestamp'] ) );
			}

			foreach ( $this->datasets as &$set ) {

				// check if the parameter option is used to flip search parameter
				if ( null != $set['parameter'] ) {

					$not = ( isset( $set['parameter']['operand'] ) && $set['parameter']['operand'] == "NOT" );

					$valueFound = ( $row[ $set['parameter']['column'] ] == $set['parameter']['value'] );
					// skip this result if we're not looking for a positive or we are not looking for a negative
					if ( ( $not && $valueFound ) || ( ! $not && ! $valueFound ) ) {
						continue;
					}
				}

				// call the custom handler if provided - if the function is not found throw an error
				if ( $set['customHandleFunction'] != null ) {
					if ( is_callable( $set['customHandleFunction'], true ) ) {
						call_user_func( $set['customHandleFunction'], $row, &$set['results'][ $step ] );
					}
					else {
						throw new Exception( "Function {$set['customHandleFunction']} not found." );
					}
				}
				else {
					switch ( $this->_compareType ) {
						case DisplayStatistics::COMPARE_COUNT:
							$set['results'][ $step ] = intval( $set['results'][ $step ] ) + 1;
							break;
						case DisplayStatistics::COMPARE_AVERAGE:
							if ( ! is_array( $set['results'][ $step ] ) ) {
								$set['results'][ $step ] = array();
							}
							array_push( $set['results'][ $step ], $row[ $set['column'] ] );
							break;
					}
				}
			}

		}

		/* Set empty top row */
		$axis_row = $this->item( $this->chart_data, "row" );
		$this->item( $axis_row, "null" );

		// calculate averages and reverse the array
		foreach ( $this->datasets as &$set ) {
			switch ( $this->_compareType ) {
				case DisplayStatistics::COMPARE_AVERAGE:
					foreach ( $set['results'] as &$resultStep ) {
						$count = count( $resultStep );
						if ( $count == 0 ) {
							$resultStep = 0;
						}
						else {
							$sum        = array_sum( $resultStep );
							$resultStep = floor( $sum / $count );
						}
					}

					break;

				case DisplayStatistics::COMPARE_COUNT:
					foreach ( $set['results'] as &$resultStep ) {
						if ( is_array( $resultStep ) ) {
							$resultStep = array_sum( $resultStep );
						}
					}
					break;
			}

			$item = $this->item( $this->chart_data, "row" );
			$this->item( $item, "string", $set['title'] );

			$set['data'] = $item;
		}


		/* Set time-format according to steps */
		if ( $this->timeStep < DisplayStatistics::MINUTE ) {
			$time_format = '%H:%M:%S';
		}
		else if ( $this->timeStep < DisplayStatistics::HOUR ) {
			$time_format = '%H:%M';
		}
		else if ( $this->timeStep < DisplayStatistics::DAY ) {
			$time_format = "%e %b\n%H:00";
		}
		else if ( $this->timeStep < DisplayStatistics::WEEK ) {
			$time_format = '%e %b';
		}
		else if ( $this->timeStep < DisplayStatistics::MONTH ) {
			$time_format = '%e %b';
		}
		else if ( $this->timeStep < DisplayStatistics::YEAR ) {
			$time_format = '%e %b %Y';
		}
		else {
			$time_format = '%Y';
		}

		/* Reverse it so the newest dates will be displayed on the right-hand side */
		$steps = array_reverse( $steps, true );

		$max_value = 0;

		// start looking from lowest possible value
		$displayPeriodFrom = 0;
		for ( $displayPeriodTill = $this->startTime; $displayPeriodTill <= $this->endTime; $displayPeriodTill += $this->timeStep ) {
			$value = 0;

			/* if data exists */
			$this->item( $axis_row, "string", strftime( $time_format, $displayPeriodTill ) ); // $this->startTime - ($step * $this->timeStep)));

			foreach ( $this->datasets as &$set ) {
				$value = 0;

				foreach ( $steps as $step ) {
					/* Check for data in this time-segment */
					if ( $step["time"] > $displayPeriodFrom && $step["time"] <= $displayPeriodTill ) {
						$value += intval( $set['results'][ $step["step"] ] );
					}
				}

				/* If we are working with seconds, convert the label to a readible time format */
				$labelValue = ( $this->_axisType == DisplayStatistics::DISPLAY_SECONDS ) ? $this->readibleTime( $value ) : $value;

				/* Add the data to the XML */
				$this->item( $set['data'], "number", "$value", array( "label" => "$labelValue" ) );
				$max_value = max( $max_value, $value );
			}

			// shift the period to the next one
			$displayPeriodFrom = $displayPeriodTill;
		}

		/* Set graph ranges */
		$this->dataMin   = ( ! isset( $this->axisMin ) ) ? 0 : $this->axisMin;
		$this->dataMax   = ( ! isset( $this->axisMax ) ) ? ceil( $max_value * 1.2 ) : $this->axisMax;
		$this->dataSteps = min( $max_value, 5 );
	}

	/* Output the created XML */
	public function createXML() {
		// build rows
		$this->generate();

		// set width for chart
		if ( isset( $this->_width ) ) {
			$index = $this->get( null, "chart_rect" );
			if ( $index > -1 ) {
				$chart_rect                        = &$this->values[ $index ];
				$chart_rect['attributes']['width'] = intval( $this->_width );
			}
		}

		if ( isset( $this->_height ) ) {
			$index = $this->get( null, "chart_rect" );
			if ( $index > -1 ) {
				$chart_rect                         = &$this->values[ $index ];
				$chart_rect['attributes']['height'] = intval( $this->_height );
			}
		}

		// set the min-max-step values for the axis
		$index = $this->get( null, "axis_value" );
		if ( $index > -1 ) {
			$axis_value                         = &$this->values[ $index ];
			$axis_value['attributes']['min']    = $this->dataMin;
			$axis_value['attributes']['max']    = $this->dataMax;
			$axis_value['attributes']['steps']  = $this->dataSteps;
			$axis_value['attributes']['suffix'] = $this->suffix;
		}

		$index = $this->get( null, "axis_category" );
		if ( $index > -1 ) {

			$parts = ( $this->endTime - $this->startTime ) / $this->timeStep;

			$skip = 0;
			if ( $parts > intval( $this->category_labels ) ) {
				$skip = 1;
				while ( $parts * ( 1 / ( $skip ) ) > $this->category_labels ) {
					$skip++;
				}
			}


			$axis_category                       = &$this->values[ $index ];
			$axis_category['attributes']['skip'] = $skip;
		}

		if ( $this->chartType ) {
			$chartType = $this->chartType;
		}
		else {
			$chartType = ( count( $this->datasets ) > 1 ) ? '3d area' : '3d column';
		}
		$this->item( null, 'chart_type', $chartType );

		$this->buildXML();

		// write the data to the file
		echo $this->xml->parse();
	}

	protected function buildXML() {
		// build XML from root up

		$done = false;
		while ( ! $done ) {
			$done = true;

			for ( $i = 0; $i < count( $this->values ); $i++ ) {
				$item         = &$this->values[ $i ];
				$target       = $item['target'];
				$targetObject = $this->values[ $item['target'] ];

				if ( $target == null || isset( $targetObject['xmlObject'] ) ) {
					if ( $target == null ) {
						$this->xml->target();
					}
					else {
						$this->xml->target( $targetObject['xmlObject'] );
					}

					$item['xmlObject'] = $this->xml->item( $item['name'], $item['value'], $item['attributes'] );
				}

				if ( $target != null && ! isset( $targetObject['xmlObject'] ) ) {
					$done = false;
				}
			}
		}
	}
}

