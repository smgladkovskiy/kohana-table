<?php	

	/**
	 * Editable Table Class
	 * 
	 * Demonstration table class to display editable data
	 *
	 * Check the Advanced and Extending demos here: http://kohana.keyframesandcode.com/demos/table/
	 * Feel free to change or amend this class as you see fit!
	 * 
	 * @author Dave Stewart
	 * @version 1.0
	 * @access public
	 */

	class Editable_Table extends Table
	{
		
		protected $post_id			= 'data';
		protected $data_id			= 'id';
		
		protected $default_type		= NULL;
		protected $control_types	= array();
		
		public function __construct($data)
		{
			parent::__construct($data);
		}
		
		public function set_control_type($key, $type, $data, $compare_by = 'value')
		{
			$this->control_types[$key] = array($type, $data, $compare_by);
			return $this;
		}
		
		public function set_default_type($type)
		{
			$this->default_type = $type;
			return $this;
		}

		protected function _generate_body_cell($index, $key)
		{
			// variables
				$body	= $this->body_data;
				$value	= $body[$index][$key];
				$name	= $this->post_id . '[' .$body[$index][$this->data_id]. '][' .$key. ']';
				
			// data
				$type	= $this->default_type;
				$data	= NULL;
				if(array_key_exists($key, $this->control_types))
				{
					$type	= $this->control_types[$key][0];
					$data	= $this->control_types[$key][1];
				}
			
			// html
				$str	= '';
				switch($type)
				{
					case 'text':
						$str = form::input($name, $value);
						break;
						
					case 'dropdown':
						$str = form::dropdown($name, $data, $value);
						break;
						
					case 'radio':
					case 'checkbox':
						foreach($data as $d)
						{
							$str .= form::$type($name, $d, $value == $d, 'style="width:auto"') . ' ' . ucwords($d) . ' ';
							if($type == 'checkbox')
							{
								$str .= '<br />';
							}
						}
						break;
						
					default:
						$str = $value;
				}
				
				return '<td>' . $str . '</td>';
		}
	}
?>