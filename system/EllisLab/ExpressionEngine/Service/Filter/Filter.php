<?php
namespace EllisLab\ExpressionEngine\Service\Filter;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use EllisLab\ExpressionEngine\Library\CP\URL;
use EllisLab\ExpressionEngine\Service\View\ViewFactory;

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2014, EllisLab, Inc.
 * @license		http://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 3.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine abstract Filter Class
 *
 * @package		ExpressionEngine
 * @category	Service
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
abstract class Filter {

	public $name;
	protected $label;
	protected $default_value;
	protected $display_value;
	protected $selected_value;
	protected $options = array();
	protected $placeholder;
	protected $has_custom_value = TRUE;

	protected $view = 'filter';

	public function value()
	{
		if (isset($this->selected_value))
		{
			return $this->selected_value;
		}

		$value = $this->default_value;

		if (isset($_POST[$this->name]) && ! empty($_POST[$this->name]))
		{
			$value = $_POST[$this->name];
		}
		elseif (isset($_GET[$this->name]))
		{
			$value = $_GET[$this->name];
		}

		return $value;
	}

	public function isValid()
	{
		return TRUE;
	}

	public function render(ViewFactory $view, URL $url)
	{
		$value = $this->display_value;
		if (is_null($value))
		{
			$value = (array_key_exists($this->value(), $this->options)) ?
				$this->options[$this->value()] :
				$this->value();
		}

		$filter = array(
			'label'            => $this->label,
			'name'             => $this->name,
			'value'            => $value,
			'has_custom_value' => $this->has_custom_value,
			'custom_value'     => ee()->input->post($this->name),
			'placeholder'      => $this->placeholder,
			'options'          => $this->prepareOptions($url),
		);
		return $view->make('filter')->render($filter);
	}

	/**
	 * Compiles URLs for all the options
	 *
	 * @param obj	$base_url A CP/URL object that serves as the base of the URLs
	 * @return array	An associative array of the options where the key is a
	 *               	URL and the value is the label. i.e.
	 * 		'http://index/admin.php?cp/foo&filter_by_bar=2' => 'Baz'
	 */
	protected function prepareOptions(URL $base_url)
	{
		$options = array();
		foreach ($this->options as $show => $label)
		{
			$url = clone $base_url;
			$url->setQueryStringVariable($this->name, $show);
			$options[$url->compile()] = $label;
		}
		return $options;
	}

}
// END CLASS

/* End of file Filter.php */
/* Location: ./system/EllisLab/ExpressionEngine/Service/Filter/Filter.php */