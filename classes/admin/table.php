<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Class table
 *
 * @author devolonter
 */
class Admin_Table extends Table {

	protected $_resource = '';
	protected $_edit_col = FALSE;
	protected $_del_col  = FALSE;
	protected $_translate_titles = FALSE;	

	protected $_edit_caption   = array('image' => '', 'title' => '');
	protected $_delete_caption = array('image' => '', 'title' => '');
	protected $_add_caption    = array('image' => '', 'title' => '');
	protected $_view_caption   = array('image' => '', 'title' => '');

	protected $_primary = 'id';
	protected $_route   = 'admin';
	protected $_crud    = array(
							'create' => 'add',
							'reade'  => 'view',
							'update' => 'edit',
							'delete' => 'delete',
	);

	public function __construct($data)
	{
		parent::__construct($data);

		$this->_edit_caption['image'] = Html::image(
												'admin_i/icons/edit.png',
												array('class' => 'ico')
										);
		$this->_edit_caption['title'] = __('Редактировать');

		$this->_delete_caption['image'] = Html::image(
												'admin_i/icons/trash.png',
												array('class' => 'ico')
										);
		$this->_delete_caption['title'] = __('Удалить');

		$this->_add_caption['title'] = __('Добавить');

		$this->_view_caption['title'] = __('Посмотреть');

	}

	public function set_add_caption($caption, $image = '')
	{
		$this->_set_caption('add', $caption, $image);
		return $this;
	}

	public function set_edit_caption($caption, $image = '')
	{
		$this->_set_caption('edit', $caption, $image);
		return $this;
	}

	public function set_delete_caption($caption, $image = '')
	{
		$this->_set_caption('delete', $caption, $image);
		return $this;
	}

	public function set_view_caption($caption, $image = '')
	{
		$this->_set_caption('view', $caption, $image);
		return $this;
	}

	public function set_crud($crud)
	{
		$this->_crud = $crud;
		return $this;
	}

	public function add_edit_column()
	{
		if (empty($this->_resource) OR empty($this->_primary) OR empty($this->_route))
		{
			return $this;
		}
		
		$this->add_column('edit');
		$this->_edit_col = TRUE;
		return $this;
	}

	public function add_delete_column()
	{
		if (empty($this->_resource) OR empty($this->_primary) OR empty($this->_route))
		{
			return $this;
		}
		
		$this->add_column('delete');
		$this->_del_col = TRUE;
		return $this;
	}

	public function add_create_button($position = 'right')
	{
		$html = '<div style="text-align: '.$position.'">';
		$html .= Html::anchor(
							Route::get('admin')
										->uri(array(
												'controller' => $this->_resource,
												'action' => $this->_crud['create']
							 )),
							$this->_add_caption['image'].$this->_add_caption['title'],
							array('title' => $this->_add_caption['title'], 'class' => 'button')
		);
		$html .= '</div>';
		$this->set_footer($html);
		return $this;
	}

	public function set_resource($resource)
	{
		$this->_resource = $resource;
		return $this;
	}

	public function set_route($route)
	{
		$this->_resource = $route;
		return $this;
	}

	public function set_primary($primary)
	{
		$this->_primary = $primary;
		return $this;
	}

	public function set_column_titles($titles, $translate = TRUE)
	{
		parent::set_column_titles($titles);
		$this->_translate_titles = $translate;
		return $this;
	}

	protected function _set_additional_data()
	{
		if (count($this->body_data))
		{
			foreach($this->body_data as $num => $row){
				if ($this->_edit_col AND ! empty($this->_crud['update']))
				{
					$this->body_data[$num]['edit'] = Html::anchor(
																Route::get('admin')
																			->uri(array(
																					'controller' => $this->_resource,
																					'action' => $this->_crud['update'],
																					'id' => $row[$this->_primary]
																 )),
																$this->_edit_caption['image'].$this->_edit_caption['title'],
																array('title' => $this->_edit_caption['title'])
					);														
				}
				else {
					$this->_edit_col = FALSE;
				}
				if ($this->_del_col AND ! empty($this->_crud['delete']))
				{
					$this->body_data[$num]['delete'] = Html::anchor(
																Route::get('admin')
																			->uri(array(
																					'controller' => $this->_resource,
																					'action' => $this->_crud['delete'],
																					'id' => $row[$this->_primary]
																 )),
																$this->_delete_caption['image'].$this->_delete_caption['title'],
																array('title' => $this->_delete_caption['title'])
					);
				}
				else {
					$this->_del_col = FALSE;
				}
			}
		}

		$num_cols = $this->get_num_cols();
		for($i =0; $i < $num_cols; $i++)
		{
			if (!isset($this->column_attributes[$i]))
			{
				$this->column_attributes[$i] = array();
			}	
		}
		
		if ($this->_edit_col AND $this->_del_col)
		{
			$this->column_attributes[$num_cols - 2]['align'] = 'center';
			$this->column_attributes[$num_cols - 1]['align'] = 'center';
		}
		elseif($this->_edit_col OR $this->_del_col)
		{
			$this->column_attributes[$num_cols - 1]['align'] = 'center';
		}
	}

	protected function _set_caption($type, $caption, $image = '')
	{
		$type = '_'.$type.'_caption';		
		$this->{$type}['title'] = $caption;

		if (!empty($image))
		{
			$this->{$type}['image'] = $image;
		}
	}

	protected function _generate_body()
	{
		if ($this->_edit_col OR $this->_del_col)
		{
			$this->_set_additional_data();
		}	
		
		parent::_generate_body();

		if ($this->_translate_titles)
		{
			foreach($this->column_titles as $key => $title){
				$this->column_titles[$key] = __($title);
			}
		}
	}
} // End Class table