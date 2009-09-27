<?php 

// Table class

	/**
	 * Table Class
	 * 
	 * Build HTML tables automatically from arrays or query results via a simple but powerful API
	 *
	 * Easily filter column output and order
	 * Delegate rendering of any element to callback functions
	 * Add custom data that can be referenced at any point in table generation
	 * Adheres to HTML Table specifications with head, body, colgroups, captions and footer
	 * Add custom HTML at any point of the build process
	 * 
	 * @author Dave Stewart
	 * @version 1.0
	 * @access public
	 */

	class Table
	{
		
	// -----------------------------------------------------------------------------------------------
	// VARIABLES
	// -----------------------------------------------------------------------------------------------
		
		// data (all these are arrays)
			protected $body_data						= array();			// array
			protected $user_data						= NULL;				// array
			protected $column_data						= NULL;				// array: data that is associated with all columns
			protected $row_data							= NULL;				// array: data that is associated with all rows
			
		// attributes
			protected $table_attributes					= array
														(
															'cellspacing' => 0,
															'cellpadding' => 3
														);					// array
			protected $column_attributes				= NULL;				// array: width, class, align and style
			
		// row and column titles
			protected $row_titles						= NULL;				// array: of strings, or null
			protected $column_titles					= NULL;				// array: of strings
			
		// column filters
			protected $column_filter					= NULL;				// array: only displays columns for these keys
			protected $auto_filter_titles				= FALSE;			// boolean: a flag to let the update() method know if column titles should be auto-filtered
			
		// user columns
			protected $user_columns						= array();			// array: temporary store for extra column info
			
		// callbacks (these should be function name references)
			protected $get_heading_cell_callback		= NULL;				// string
			protected $get_row_title_cell_callback		= NULL;				// string
			protected $get_row_callback					= NULL;				// string
			protected $get_body_cell_callback			= NULL;				// string
			protected $get_column_cell_callback			= array();			// array of strings
			
		// html
			protected $caption							= '';				// string
			protected $head_html						= '';				// string
			protected $foot_html						= '';				// string
			protected $body_html						= '';				// string
			protected $rows_html						= array();			// array
			
		// content flags
			protected $has_row_title					= FALSE;			// boolean
			protected $is_updating						= FALSE;			// boolean
			
		// markup (partially unused, and code not completely implemented)
			protected $trTab							= "\t";				// string
			protected $tdTab							= "\t\t";			// string
			protected $tdNewline						= "\n";				// string
			protected $trNewline						= "\n";				// string
			protected $newline							= "\n";				// string
			protected $missing_title_warning			= '';
			
		// consts
			const AUTO									= 'Table::AUTO';

			
	// -----------------------------------------------------------------------------------------------
	// Constructor
	// -----------------------------------------------------------------------------------------------
		
		/**
		 * Class constructor
		 * 
		 * @param mixed $attributes
		 * @return
		 */
		public function __construct($arg1 = NULL, $arg2 = NULL)
		{
			// attributes
				if(is_string($arg1))
				{
					$this->set_attributes($arg1);
				}
				elseif(is_array($arg1) OR is_object($arg1))
				{
					$this->set_body_data($arg1);
				}
				
			// body data
				if(is_string($arg2))
				{
					$this->set_attributes($arg2);
				}
				elseif(is_array($arg2) OR is_object($arg2))
				{
					$this->set_body_data($arg2);
				}
		}
		
		/**
		 * Create a chainable instance of the Table class
		 * 
		 * @param mixed $attributes
		 * @return
		 */
		public static function factory($arg1 = NULL, $arg2 = NULL, $arg3 = NULL)
		{
			// test for class as the first argument
				if(is_string($arg1) && strstr($arg1, '=') === FALSE)
				{
					$class = 'Table_' . $arg1;
					if(class_exists($class))
					{
						return new $class($arg2, $arg3);
					}
					throw(new Kohana_User_Exception('Table instantiation error', "The class '$class' doesn't exist"));
				}
				
			// if not, pass along data / attributes
				return new self($arg1, $arg2);
		}
			
	// -----------------------------------------------------------------------------------------------
	// Data Setter methods
	// -----------------------------------------------------------------------------------------------
		
		/**
		 * Set the main data of the table
		 *
		 * Either a database result, ORM Iterator, or a 2D array of other arrays or objects should be passed as the main argument
		 * Passing any object with keys (as opposed to numeric indices) will allow column filtering based on the keys of the child objects
		 * 
		 * @param	array/object/ORM_Iterator $data
		 * @param	bool $append
		 * @return
		 */
		public function set_body_data($data, $append = FALSE)
		{
			// if data is NULL, reset body data
				if($data === NULL)
				{
					$this->body_data = array();
					return $this;
				}
				
			// if there is already body data, update the internal HTML cache
				if(count($this->body_data) > 0 && $append == FALSE)
				{
					$this->update();
				}
			
			// check for ORM
				if($data instanceof ORM_Iterator)
				{
					foreach($data as $orm)
					{
						$obj	= array();
						$keys	= $orm->as_array();
						foreach($keys as $key => $value)
						{
							$obj[$key] = $orm->{$key};
						}
						array_push($this->body_data, $obj);
					}
				}

			// add data
				else
				{
					foreach($data as $row)
					{
						array_push($this->body_data, (array) $row);
					}
				}
				
			// return
				return $this;
		}
		
		
		/**
		 * Set custom user data that can later be directly accessed by any callbacks
		 * 
		 * @param string $key
		 * @param mixed $data
		 * @return
		 */
		public function set_user_data($key, $data)
		{
			$this->user_data[$key] = $data;
			return $this;
		}
		

		/**
		 * set data that can be used to build matrix-style tables that derive their
		 * content from the product of row and column data
		 *
		 * $row data is set on the vertical axis, and is retrieved via $row_data[$index]
		 * $column data is set on th ehorizontal axis and is retrieved via $column_data[$key]
		 * 
		 * @param array/object $data
		 * @param array/object $data
		 * @return
		 */
		public function set_matrix_data($row_data, $column_data)
		{
			// call internal function
				$this->set_row_data($row_data);
				$this->set_column_data($column_data);
				
			// create null body data so that _render_body works as expected
				$this->body_data = array();
				for($y = 0; $y < count($this->row_data); $y++)
				{
					array_push($this->body_data, array_fill(0, count($this->column_data), NULL));
				}
				
			// return
				return $this;
		}
		
		/**
		 * Set data that is associated with the columns of the table
		 * 
		 * @param array/object $data
		 * @return
		 */
		public function set_column_data($data)
		{
			// cast data to correct type
				if(is_array($data))
				{
					// do nothing
				}
				else if(is_object($data))
				{
					$data = array_keys( (array) $data );
				}
				else
				{
					$data = func_generate_args();
				}
				
			// apply data
				$this->column_data = $data;
				
			// return
				return $this;
		}
		
		/**
		 * Set data that is associated with the rows of the table
		 * 
		 * This should be used in conjuction with set_column_data() and table cell callbacks 
		 * in order to build matrix-style tables that derive their data from x and y values
		 * 
		 * @param array/object $data
		 * @param bool $append
		 * @return
		 */
		public function set_row_data($data, $append = FALSE)
		{
			// cast data to correct type
				if(is_array($data))
				{
					// do nothing
				}
				else if(is_object($data))
				{
					$data = array_keys( (array) $data );
				}
				else
				{
					$data = func_generate_args();
					$append = FALSE;
				}
				
			// apply data
				if($append)
				{
					if($this->row_data == NULL)
					{
						$this->row_data = $data;
					}
					else
					{
						$this->row_data = array_merge($this->row_data, $data);
					}
				}
				else
				{
					$this->row_data = $data;
				}
				
			// return
				return $this;
		}
		
	// -----------------------------------------------------------------------------------------------
	// column and row methods
	// -----------------------------------------------------------------------------------------------
		
		/**
		 * Set row titles
		 *
		 * Creates an additional column on the left of the table that is separate from the 
		 * body data, to display row labels
		 *
		 * Pass NULL to render an empty first column, an array of strings to fill the rows 
		 * sequentially or the name of a column to promote that column to row titles status.
		 *
		 * Optionally set the attributes for the row title cell as well, such as a CSS class
		 * 
		 * @param	mixed	$titles
		 * @param	string	$attributes
		 * @return
		 */
		public function set_row_titles($titles = NULL)
		{
			// cast data to correct type
				if(is_array($titles))
				{
					// do nothing
				}
				else if(is_object($titles))
				{
					$data = array_keys( (array) $titles );
				}
				else if(is_string($titles))
				{
					$titles = $titles;	// the string is left as is. row titles will be set in the update() method
				}
				else
				{
					$titles = func_get_args();
					$append = FALSE;
				}
				
				$this->row_titles = $titles;
				
			// update
				$this->has_row_title = TRUE;
				
			// return
				return $this;
		}
		
		
		/**
		 * Set column titles in an HTML <thead> element
		 *
		 * Pass in an associative array that matches the keys being used by each row to take advantage 
		 * of column filtering, ie array('name' => 'User's name', 'email' => 'User's email')
		 *
		 * Passing in Table::AUTO will automatically title the table
		 * 
		 * @param mixed $titles
		 * @return
		 */
		public function set_column_titles($titles)
		{
			// auto titles
				if($titles == Table::AUTO)
				{
					$this->column_titles = $titles;
					$this->auto_filter_titles = TRUE;
				}
			
			// array passed
				elseif(is_array($titles))
				{
					$this->column_titles = $titles;
					$this->auto_filter_titles = !is_numeric(implode('', array_keys($titles)));
				}

			// return
				return $this;
		}
		
		/**
		 * Set column attributes that will be used in colgroup tags
		 *
		 * Allowable $types are 'width', 'align', 'style'
		 * $data should be an array of the values you want set for each column
		 * 
		 * @param	string	$type
		 * @param	array	$data
		 * @return
		 */
		public function set_column_attributes($type, $data)
		{
			// _initialize attributes if none previously set
				if($this->column_attributes == NULL)
				{
					$this->column_attributes = array();
				}
				
			// get attributes by argument if an array isn't passed
				if(!is_array($data))
				{
					$data = func_get_args();
					array_shift($data);
				}
				
			// set attributes in the column_attributes property
				for($i = 0; $i < count($data); $i++)
				{
					$this->column_attributes[$i][$type] = $data[$i];
				}
				
			// return
				return $this;
		}
		
		/**
		 * Allow only certain columns to be displayed
		 *
		 * Pass in an array of keys that match the keys on each row, e.g. array('name', 'email')
		 * Filtering doesn't remove the data in the underlying table, so it is still accessible in callbacks,
		 * it just enables you to show specific columns only, and change you mind at will
		 *
		 * @param array $keys
		 * @return
		 */
		public function set_column_filter(array $keys)
		{
			$this->column_filter = array_combine($keys, $keys);
			return $this;
		}
		
	// -----------------------------------------------------------------------------------------------
	// STANDARD TABLE PROPERTIES: Caption and Footer
	// -----------------------------------------------------------------------------------------------
		
		/**
		 * set the caption of the table
		 * 
		 * @param mixed $html
		 * @return
		 */
		public function set_caption($html)
		{
			$this->caption = $html;
			return $this;
		}
		

		/**
		 * set the footer of the table.
		 *
		 * Passing a table row (<tr>) fragment will splice in the HTML verbatim. Passing
		 * anything else will generate a footer that conveniently spans the entire table width.
		 * 
		 * @param mixed $html
		 * @return
		 */
		public function set_footer($html)
		{
			$this->foot_html = $html;
			return $this;
		}
		

		/**
		 * set the attributes of the table.
		 *
		 * Pass a string of HTML attributes or the name and value of a single attribute.
		 * Passing a NULL value deletes a property
		 * 
		 * @param	mixed	attributes string or single attribute name
		 * @param	string	attribute value
		 * @return
		 */
		public function set_attributes($arg1, $arg2)
		{
			preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $arg1, $matches);
			if(count($matches[0]) > 0)
			{
				for($i = 0; $i < count($matches[1]); $i++)
				{
					$this->table_attributes[$matches[1][$i]] = $matches[2][$i];
				}
			}
			else
			{
				if($arg2 !== NULL)
				{
					$this->table_attributes[$arg1] = $arg2;
				}
				else
				{
					unset($this->table_attributes[$arg1]);
				}	
			}
			return $this;
		}

	// -----------------------------------------------------------------------------------------------
	// DATA OUT: Cell rendering callback setter
	// -----------------------------------------------------------------------------------------------
		
		/**
		 * Table::set_callback()
		 * 
		 * Set callbacks for cell rendering etc
		 * Callback types must be one of 'body', 'heading', 'column', 'row', or 'row_title'. Only 'column' takes an additional
		 * argument, that of the column key (or keys) to apply it to
		 *
		 * Use the following method signatures in the callbacks
		 *
		 * 		body cells:			function body_cell_callback($value, $index, $key, $body_data, $user_data, $row_data, $column_data, $table)
		 * 		heading cells:		heading_cell_callback($value, $key, $user_data, $column_data, $table)
		 * 		column cells:		column_cell_callback($value, $index, $key, $body_data, $user_data, $row_data, $column_data, $table)
		 * 		row-title cells:	row_title_cell_callback( $value, $body_data, $user_data,  $row_data, $table)
		 * 		rows:				row_callback($row, $index, $body_data, $user_data, $row_data, $table)
		 * 
		 * @param	string		$function_name	The function to call
		 * @param	string		$type			The type of callback
		 * @param	mixed		$keys			A single key or array of keys (column names)
		 * @return
		 */
		public function set_callback($function_name, $type = 'body', $keys = NULL)
		{
			if(function_exists($function_name))
			{
				// setup
					$type = str_replace(' ', '_', $type);
					if($type == 'column_title')
					{
						$type = 'heading';
					}
					
				// set
					switch($type)
					{
						case 'body':
						case 'heading':
						case 'row_title':
							$this->{'get_' . $type . '_cell_callback'} = $function_name;
						break;
						
						case 'column':
							if(!is_array($keys))
							{
								$keys = array($keys);
							}
							foreach($keys as $key)
							{
								$this->{'get_column_cell_callback'}[$key] = $function_name;
							}
						break;
							
						case 'row':
							$this->{'get_row_callback'} = $function_name;
						break;
						
						default:
							trigger_error("Callback types must be one of 'body', 'heading' (or 'column_title'), 'row', 'row_title', or 'column' (see class for method signatures) .", E_USER_WARNING);
					}
			}
			else
			{
				trigger_error("Callback function '$function_name' doesn't exist!", E_USER_WARNING);
			}
			
			return $this;
		}
			
	// -----------------------------------------------------------------------------------------------
	// Table manipulation
	// -----------------------------------------------------------------------------------------------
		
		/**
		 * adds raw html to the table's body
		 * 
		 * @param mixed $html
		 * @return
		 */
		public function add_html($html)
		{
			$this->update();
			$this->body_html .= $html;
			return $this;
		}
		
		
		/**
		 * adds a row that spans all columns to the current table's html
		 * 
		 * @param	mixed	$content, the content to go inside the span
		 * @param	mixed	$extra, a CSS class (or classes) or an HTML attributes fragment
		 * @return
		 */
		public function add_span($content = NULL, $extra = NULL)
		{
			// error if no body data
				if(count($this->body_data) == 0)
				{
					trigger_error('Cannot automatically add a table span until body data has been set!');
					return $this;
				}
				
			// columns
				$cols = $this->get_num_cols();
				
			// content
				if($content == NULL)
				{
					$content = '&nbsp;';
				}
				
			// attributes
				preg_match('%["\']%', $extra, $matches);
				$attributes = $matches == NULL ? 'class="' . $extra . '"' : $extra;
				
			// html
				$html = '	<tr ' . $attributes . '><td colspan="' .$cols. '">' . $content . '</td></tr>'."\n";
				
			// update
				$this->update();
				$this->body_html .= $html;
				
			// return
				return $this;
		}
		
		/**
		 * Add an empty user-defined column
		 *
		 * To populate the column, use callbacks
		 *
		 * @param	string		new key name of the column
		 * @param	string		optionally state in front of which existing column you want to make the insertion
		 * @return
		 */
		public function add_column($key, $insert_before = NULL)
		{
			// variables
				$obj->key			= $key;
				$obj->insert_before	= $insert_before;
				array_push($this->user_columns, $obj);

			// return
				return $this;
		}
		
		
		/**
		 * Transpose (swap rows for columns) the data in the table
		 * 
		 * @return
		 */
		public function transpose()
		{
			// variables
				$input_rows			= $this->body_data;
				$output_rows		= array();
	
			// loop left-to-right through the columns (by colIndex)
				$col_index = 0;
				do
				{
					// new array to grab current column values
						$cur_col = array();
						
					// loop top-to-bottom thorugh rows of current column (by rowName)
						foreach($input_rows as $row_name => $row_values)
						{
							@ $col_value			= $row_values[$col_index];
							$cur_col[$row_name]		= $col_value;
						}
	
					// add curCol to outputRows if values existed in current column
						if($col_value !== NULL)
						{
							array_push($output_rows, $cur_col);
							$col_index++;
						}
				}
				while($col_value !== NULL);
	
			// body
				$this->body_data			= $output_rows;
			
			// header and left column
				$column_data				= $this->column_data;
				$row_data					= $this->row_data;
				
				$this->column_data			= $row_data;
				$this->row_data				= $column_data;
				
			// return
				return $this;
		}
		

	// -----------------------------------------------------------------------------------------------
	// Public getters
	// -----------------------------------------------------------------------------------------------
		
		/**
		 * Get user data according to key
		 * 
		 * @param	string		$key
		 * @return	mixed
		 */
		public function get_user_data($key)
		{
			return array_key_exists($key, $this->user_data) ? $this->user_data[$key] : NULL;
		}
	
		/**
		 * Return a single row of HTML
		 * 
		 * @param	int			$index
		 * @return	string
		 */
		public function get_row_html($index)
		{
			return $this->rows_html[$index];
		}
		
		/**
		 * Utility function to get the number of columns
		 * 
		 * @return	int
		 */
		public function get_num_cols()
		{
			if($this->column_filter != NULL)
			{
				$cols = count($this->column_filter);
			}
			else
			{
				$cols = count(array_keys($this->body_data[0]));
			}
			$cols += $this->has_row_title ? 1 : 0;
			return $cols;
		}
		
	// -----------------------------------------------------------------------------------------------
	// Core cell rendering methods, can be overriden in an extending class, or by setting external callbacks
	// -----------------------------------------------------------------------------------------------
		
		/**
		 * Internal method to render table caption
		 * 
		 * @return html
		 */
		protected function _generate_caption()
		{
			return $this->caption != NULL ? '<caption>' .$this->caption. '</caption>' . $this->newline: '';
		}
		

		/**
		 * Internal method to render table heading
		 * 
		 * @return html
		 */
		protected function _generate_heading()
		{
			
			// start html
				$html	 = '';
				$html	.= '<thead>' . $this->newline;
				$html	.= '	<tr>' . $this->trNewline;
				
			// add in empty cell if there's a row_title
				if($this->has_row_title)
				{
					$html .= '		<th>&nbsp;</th>' . $this->tdNewline;
				}
				
			// build the heading cells
			
				// if there are titles, just render the titles, and filter if needs be
					if(is_array($this->column_titles))
					{
						// render the filtered titles
							if($this->auto_filter_titles)
							{
								foreach($this->column_filter as $key)
								{
									if(in_array($key, $this->column_filter))
									{
										$html .= '		<th>' . (in_array($key, array_keys($this->column_titles)) ? $this->column_titles[$key] : $this->missing_title_warning) . '</th>' . $this->tdNewline;
									}
								}
							}
						// render all titles
							else
							{
								foreach($this->column_titles as $value)
								{
									$html .= '		<th>' . $value . '</th>' . $this->tdNewline;
								}
							}
					}
					
				// if there's data and callbacks, do the callback thing instead
					else
					{
						foreach($this->column_filter as $key)
						{
							$html .= '		' . $this->_generate_heading_cell($key) . $this->tdNewline;
						}
					}
				
					
			// close html
				$html .= '	</tr>' . $this->trNewline;
				$html .= '</thead>' . $this->newline;
				$this->head_html = $html;
				
			// return
				return $html;
	
		}
		
		
		/**
		 * Generate each individual heading cell
		 * 
		 * @param mixed $key
		 * @return html
		 */
		protected function _generate_heading_cell($key)	// 0-based. Ignores any row_title data
		{
			// variables
				$content = $key;
				
			// if there's a callback, call it
				if($this->get_heading_cell_callback != NULL)
				{
					$content = call_user_func($this->get_heading_cell_callback, $content, $key, $this->user_data, $this->column_data, $this);
				}
				
			// render the cell
				if($content instanceof HTML_Element)
				{
					return $content->html();
				}
				else
				{
					return '<th>' . $content . '</th>';
				}
		}
		
		
		/**
		 * Internal method to render table colgroups
		 *
		 * Renders information regarding column widths, alignments and styles.
		 * Note: colgroups do not render correctly in webkit browsers where a table has a thead section
		 * 
		 * @return html
		 */
		protected function _generate_colgroup()
		{
			// start html
				$html	 = '';

			// generate html
				foreach($this->column_attributes as $column)
				{
					$class = '';
					$style = '';
					$align = '';
					$width = '';
					
					foreach($column as $attribute => $value)
					{
						switch($attribute)
						{
							case 'style':
								$style .= $value;
							break;
							
							case 'width':
								if(is_numeric($value))
								{
									$width = ' width="' . $value . '"';
								}
								else
								{
									$style .= ';' . $value . 'px; ';
								}
							break;
							
							case 'align':
								$align = ' align="' . $value . '"';
							break;
							
							case 'class':
								$class = ' class="' . $value . '"';
							break;
							
							default:
							
						}
					}
					$html .= '<colgroup' . $width . $align . $class . ($style == '' ? '' : ' style="' . $style . '"') . '></colgroup>' . $this->tdNewline;
				}
				
			// return
				return $html;
		}
		
		
		/**
		 * Internal method to generate table body (and subsequently row titles)
		 * 
		 * @return - none (update the internal HTML cache)
		 */
		protected function _generate_body()
		{
			for($index = 0; $index < count($this->body_data); $index++)
			{
				// ---------------------------------------------------------------------------------------------
				// TABLE ROW : start with empty html
				
					// variables
						$row_html = '';
						
				// ---------------------------------------------------------------------------------------------
				// TABLE ROW : grab the row as an object
				
					// open the row via a method if it exists
						$row = $this->_generate_row($index);
						if($row instanceof HTML_Element)
						{
							$row_html	.= '	' . $row->open() . $this->trNewline;
						}
						else
						{
							$row_html	.= '	<tr>' . $this->trNewline;
						}
						
				// ---------------------------------------------------------------------------------------------
				// TABLE CELLS : loop through cell data, according to columns
				
					// ---------------------------------------------------------------------------------------------
					// ROW TITLE (FIRST) CELL
					
						// insert first column if it exists
							if($this->row_titles != NULL)
							{
								$row_html .= '		' . $this->_generate_row_title_cell($index) . $this->newline;
							}
					
					// ---------------------------------------------------------------------------------------------
					// REMAINING CELLS
					
						// render cells
							foreach($this->column_filter as $key)
							{
								$row_html .= '		' . $this->_generate_body_cell($index, $key) . $this->newline;
							}
					
				// ---------------------------------------------------------------------------------------------
				// CLOSE ROW
				
					// close the row via a method if the row is an object 
						if($row instanceof HTML_Element)
						{
							$row_html	.= '	' . $row->close() . $this->trNewline;
						}
						else
						{
							$row_html	.= '	</tr>' . $this->trNewline;
						}
	
				// ---------------------------------------------------------------------------------------------
				// HTML
				
					// update body html
						array_push($this->rows_html, $row_html);
						$this->body_html .= $row_html;
			}
		}
	
		/**
		 * Generates the object to open an individual row
		 *
		 * This function must return a Tr instance, or nothing at all
		 * 
		 * @param int $index
		 * @return
		 */
		protected function _generate_row($index)
		{
			$row = NULL;
			if($this->get_row_callback != NULL)
			{
				$row = call_user_func($this->get_row_callback, $this->body_data[$index], $index, $this->body_data, $this->user_data, $this->row_data, $this);
			}
			return $row;
		}

		
		/**
		 * Return html for each individual row title (left-most column cell)
		 * 
		 * @param int $index
		 * @return
		 */
		protected function _generate_row_title_cell($index)
		{
			// variables
				$content = $this->row_titles != NULL && array_key_exists($index, $this->row_titles) ? $this->row_titles[$index] : '';
				
			// if there's a callback, call it
				if($this->get_row_title_cell_callback != NULL)
				{
					$content = call_user_func($this->get_row_title_cell_callback, $content, $this->body_data, $this->user_data, $this->row_data, $this);
				}
				
			// render the cell
				if($content instanceof HTML_Element)
				{
					return $content->html();
				}
				elseif($content == NULL)
				{
					$content = '&nbsp;';
				}
				
				return '<th>' . $content . '</th>';
		}
		
		/**
		 * Internal method to generate each table body cell
		 * 
		 * @param int $index
		 * @param string $key
		 * @return html
		 */
		protected function _generate_body_cell($index, $key)
		{
			// variables
				$content = @$this->body_data[$index][$key];
				
			// if there's a callback, call it
				if(array_key_exists($key, $this->get_column_cell_callback))
				{
					$content = call_user_func($this->get_column_cell_callback[$key], $content, $index, $key, $this->body_data, $this->user_data, $this->row_data, $this->column_data, $this);
				}
				elseif($this->get_body_cell_callback != NULL)
				{
					$content = call_user_func($this->get_body_cell_callback, $content, $index, $key, $this->body_data, $this->user_data, $this->row_data, $this->column_data, $this);
				}
				
			// render the cell
				if($content instanceof HTML_Element)
				{
					return $content->html();
				}
				elseif($content !== NULL)
				{
					return '<td>' . $content . '</td>';
				}
				else
				{
					return '<td>&nbsp;</td>';
				}
		}
		
		/**
		 * Internal method to render table footer
		 *
		 * @return html
		 */
		protected function _generate_footer()
		{
			// start html
				$html = $this->foot_html;
				
			// generate a span, or insert existing html
				preg_match('%^<tr.+</tr>$%', $html, $matches);
				if($matches == NULL)
				{
					$cols = $this->get_num_cols();
					$html = '	<tr><td colspan="' .$cols. '">' . $html . '</td></tr>'."\n";
				}
				
			// update the html
				$html = '<tfoot>' . $this->newline . $html . '</tfoot>' . $this->newline;
				
			// return
				return $html;
		}
		
	// -----------------------------------------------------------------------------------------------
	// Initialization and Rendering
	// -----------------------------------------------------------------------------------------------
		
		/**
		 * Update internal body HTML cache before calling another operation that directly adds to it
		 * 
		 * @param bool $reset
		 * @return
		 */
		public function update($reset = TRUE)
		{
			// exit if already already updating, or else recursion will crash the server
				if($this->is_updating)
				{
					return FALSE;
				}
				$this->is_updating = TRUE;
			
			// keys
				$column_keys = array_keys($this->body_data[0]);
				
			// user columns - add their keys into the column_keys array
				for($i = 0; $i < count($this->user_columns); $i++)
				{
					// variables
						$index			= FALSE;
						$column			= $this->user_columns[$i];
						$insert_before	= $column->insert_before;
						if($insert_before != NULL)
						{
							$index =  array_search($insert_before, $column_keys);
						}
						
					// insert or append the new column key
						if($index !== FALSE)
						{
							array_splice($column_keys, $index, 0, $column->key);
						}
						else
						{
							array_push($column_keys, $column->key);
						}
				}
			
			// column filters
				if($this->column_filter == NULL)
				{
					$this->set_column_filter($column_keys);
				}
				
			// auto row titles - move one of the body columns to become the row titles column
				$row_titles_key = NULL;
				if(is_string($this->row_titles) && array_key_exists($this->row_titles, $this->body_data[0]))
				{
					$row_titles_key = $this->row_titles;
					$titles = array();
					for($i = 0; $i < count($this->body_data); $i++)
					{
						array_push($titles, $this->body_data[$i][$row_titles_key]);
						unset($this->body_data[$i][$row_titles_key]);
					}
					$this->row_titles = $titles;
				}
				
			// auto column titles - convert array keys to Sentence Case
				if($this->column_titles == Table::AUTO && count($this->body_data) > 0)
				{
					// column titles
						$titles = array_combine($column_keys, $column_keys);
						foreach($titles as $key => $value)
						{
							$titles[$key] = ucwords(str_replace('_', ' ', $key));
						}
						$this->column_titles = $titles;
						
					// now titles are set, can we unset any if row-titles are also set
						if($row_titles_key != NULL)
						{
							unset($this->column_titles[$row_titles_key]);
							unset($this->column_filter[$row_titles_key]);
						}
				}
				
			// render body
				if($this->body_data != NULL)
				{
					$this->_generate_body();
				}
				
			// reset data
				if($reset)
				{
					$this->body_data = array();
					$this->row_data = NULL;
					if($this->row_titles != NULL)
					{
						$this->set_row_titles(NULL);
					}
				}
				
			// return
				$this->is_updating = FALSE;
				return $this;
		}
		
	// -----------------------------------------------------------------------------------------------
	// Public table generation code
	// -----------------------------------------------------------------------------------------------
		
		/**
		 * Render all table output as HTML
		 * 
		 * @param bool $echo
		 * @return html
		 */
		public function render($echo = FALSE)
		{
			// update existing data
				$this->update(FALSE);
				
			// has structure
				$has_structure = $this->foot_html != NULL || $this->head_html != NULL;
			
			// attributes
				$attributes = '';
				foreach($this->table_attributes as $key => $value)
				{
					$attributes .= $key .'="' . $value .'" ';
				}
				
			// open the table
				$html = '';
				$html .= '<table ' . $attributes . '>' . $this->newline;
				
			// caption
				$html .= $this->_generate_caption();

			// heading
				if($this->column_titles != NULL || $this->get_heading_cell_callback != NULL)
				{
					$html .= $this->_generate_heading();
				}
			
			// colgroup
				if($this->column_attributes != NULL)
				{
					$html .= $this->_generate_colgroup();
				}
				
			// footer
				if($this->foot_html != NULL)
				{
					$html .= $this->_generate_footer();
				}
				
			// body
				$html .= ($has_structure ? '<tbody>' : '') . $this->newline;
				$html .= $this->body_html;
				$html .= ($has_structure ? '</tbody>' : '') . $this->newline;
			
			// close table
				$html .= '</table>' . $this->newline;
			
			// echo
				if($echo)
				{
					echo $html;
				}
				
			// return
				return $html;
		}
	}
	
	
// helper class, Cell

	/**
	 * HTML_Element
	 * 
	 * @package   
	 * @author Dave Stewart
	 * @copyright 
	 * @version 2009
	 * @access public
	 */
	abstract class HTML_Element
	{
		public $tag					= '';
		public $content				= '';
		public $class				= '';
		public $style				= '';
		public $attributes			= '';
		
		/**
		 * Constructor
		 * 
		 * @param string $content
		 * @param string $class
		 * @param string $style
		 * @param string $attributes
		 * @return
		 */
		public function __construct($content = '', $class = '', $style = '', $attributes = '')
		{
			$this->content		= $content;
			$this->class		= $class;
			$this->style		= $style;
			$this->attributes	= $attributes;
		}
		
		/**
		 * Return cell HTML
		 * 
		 * @return
		 */
		public function html()
		{
			return $this->open() . $this->content . $this->close();
		}
		
		/**
		 * Return start tag HTML
		 * 
		 * @return
		 */
		public function open()
		{
			$atts = '';
			
			if($this->class !== '')
			{
				$atts .= ' class="' .$this->class. '"';
			}
			if($this->style !== '')
			{
				$atts .= ' style="' .$this->style. '"';
			}
			if($this->attributes !== '')
			{
				$atts .= ' ' .$this->attributes;
			}
			return '<' .$this->tag.$atts. '>';
		}
		
		/**
		 * Return end tag HTML
		 * 
		 * @return
		 */
		public function close()
		{
			return '</' .$this->tag. '>';
		}
	};
	
	class Tr extends HTML_Element
	{
		public $tag = 'tr';
	}
	
	class Th extends HTML_Element
	{
		public $tag = 'th';
	}
	
	class Td extends HTML_Element
	{
		public $tag = 'td';
	}
	
?>