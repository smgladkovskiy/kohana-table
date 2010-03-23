<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Class Table Type Admin Content
 *
 * @author devolonter
 */
class Table_Type_Admin_Content extends Table_Type_Admin {

	protected $_content_type = 'folder';
	protected $_type_column = FALSE;

	public function __construct($data)
	{
		parent::__construct($data);
		$this->set_attributes('class', 'content-table');
	}

	public function set_content_type($type)
	{
		$this->_content_type = $type;		
		return $this;
	}

	public function add_type_icon($before)
	{
		$this->add_column('type', $before);
		$this->_type_column = TRUE;
		return $this;
	}

	protected function _generate_body()
	{
		if ($this->_type_column)
		{
			if (count($this->body_data))
			{
				$image = '';
				
				switch($this->_content_type)
				{
					case 'folder':
						$image = Html::image(
							'admin_i/icons/folder.png',
							array('class' => 'ico i24x24')
						);
						break;
				}

				foreach($this->body_data as $num => $row)
				{
					$this->body_data[$num]['type'] = $image;
				}
			}
		}

		parent::_generate_body();
	}
	
} // End Table_Type_Admin_Content
