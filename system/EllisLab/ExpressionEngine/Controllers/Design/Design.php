<?php

namespace EllisLab\ExpressionEngine\Controllers\Design;

use ZipArchive;
use CP_Controller;
use EllisLab\ExpressionEngine\Library\CP\Pagination;
use EllisLab\ExpressionEngine\Library\CP\Table;
use EllisLab\ExpressionEngine\Library\CP\URL;
use EllisLab\ExpressionEngine\Service\Model\Collection;
/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2015, EllisLab, Inc.
 * @license		http://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 3.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine CP Design Class
 *
 * @package		ExpressionEngine
 * @subpackage	Control Panel
 * @category	Control Panel
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
class Design extends CP_Controller {

	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct();

		if ( ! $this->cp->allowed_group('can_access_design'))
		{
			show_error(lang('unauthorized_access'));
		}

		ee()->lang->loadfile('design');
	}

	protected function sidebarMenu($active_group_id = NULL)
	{
		// Register our menu
		$vars = array(
			'template_groups' => array(),
			'system_templates' => array(
				array(
					'name' => lang('messages'),
					'url' => cp_url(''),
					'edit_url' => cp_url('')
				),
				array(
					'name' => lang('email'),
					'url' => cp_url(''),
					'edit_url' => cp_url('')
				)
			)
		);

		// Template Groups
		$is_admin = ee()->session->userdata['group_id'] == 1;
		$assigned_template_groups = ee()->session->userdata['assigned_template_groups'];

		foreach (ee('Model')->get('TemplateGroup')->all() as $group)
		{
			if ($is_admin OR array_key_exists($group->group_id, $assigned_template_groups))
			{
				$class = ($active_group_id == $group->group_id) ? 'act' : '';

				$data = array(
					'name' => $group->group_name,
					'url' => cp_url('design/manager/' . $group->group_name),
					'edit_url' => cp_url('design/group/edit/' . $group->group_name),
				);

				if ($group->is_site_default)
				{
					$class .= ' default';
					$data['name'] = '<b>' . $group->group_name . '</b>';
				}

				if ( ! empty($class))
				{
					$data['class'] = $class;
				}

				$vars['template_groups'][] = $data;
			}
		}

		// System Templates
		if (ee('Model')->get('Module')->filter('module_name', 'Member')->first())
		{
			$vars['system_templates'][] = array(
				'name' => lang('members'),
				'url' => cp_url(''),
				'edit_url' => cp_url('')
			);
		}

		if (ee()->config->item('forum_is_installed') == "y")
		{
			$vars['system_templates'][] = array(
				'name' => lang('forums'),
				'url' => cp_url(''),
				'edit_url' => cp_url('')
			);
		}

		ee()->view->left_nav = ee('View')->make('design/menu')->render($vars);
		ee()->cp->add_js_script(array(
			'file' => array('cp/design/menu'),
		));
	}

	protected function stdHeader()
	{
		ee()->view->header = array(
			'title' => lang('template_manager'),
			'form_url' => cp_url('design/template/search'),
			'toolbar_items' => array(
				'settings' => array(
					'href' => cp_url('settings/template'),
					'title' => lang('settings')
				),
				'sync' => array(
					'href' => cp_url('design/sync'),
					'title' => lang('sync_all_templates')
				),
				'download' => array(
					'href' => cp_url('design/export'),
					'title' => lang('export_all')
				)
			),
			'search_button_value' => lang('search_templates')
		);
	}

	/**
	 * Determines if the logged in user has edit privileges for a given template
	 * group. We need either a group's unique id or a template's unique id to
	 * determine access.
	 *
	 * @param  int  $group_id    The id of the template group in question (optional)
	 * @param  int  $template_id The id of the templete in question (optional)
	 * @return bool TRUE if the user has edit privileges, FALSE if not
	 */
	protected function hasEditTemplatePrivileges($group_id = NULL, $template_id = NULL)
	{
		// If the user is a Super Admin, return true
		if (ee()->session->userdata['group_id'] == 1)
		{
			return TRUE;
		}

		if ( ! $group_id)
		{
			if ( ! $template_id)
			{
				return FALSE;
			}
			else
			{
				$group_id = ee('Model')->get('Template', $template_id)
					->fields('group_id')
					->first()
					->group_id;
			}
		}

		return array_key_exists($group_id, ee()->session->userdata['assigned_template_groups']);
	}

	protected function buildTableFromTemplateCollection(Collection $templates, $include_group_name = FALSE)
	{
		$table = Table::create();
		$table->setColumns(
			array(
				'template',
				'hits',
				'manage' => array(
					'type'	=> Table::COL_TOOLBAR
				),
				array(
					'type'	=> Table::COL_CHECKBOX
				)
			)
		);

		$data = array();

		$template_id = ee()->session->flashdata('template_id');

		$hidden_indicator = ($this->config->item('hidden_template_indicator') != '') ? $this->config->item('hidden_template_indicator') : '_';
		$hidden_indicator_length = strlen($hidden_indicator);

		foreach ($templates as $template)
		{
			$group = $template->getTemplateGroup();
			$template_name = htmlentities($template->template_name, ENT_QUOTES);

			if ($include_group_name)
			{
				$template_name = $group->group_name . '/' . $template_name;
			}

			if (strncmp($template->template_name, $hidden_indicator, $hidden_indicator_length) == 0)
			{
				$template_name = '<span class="hidden">' . $template_name . '</span>';
			}

			if ($template->template_name == 'index')
			{
				$template_name = '<span class="index">' . $template_name . '</span>';
			}

			$view_url = ee()->functions->fetch_site_index();
			$view_url = rtrim($view_url, '/').'/';

			if ($template->template_type == 'css')
			{
				$view_url .= QUERY_MARKER.'css='.$group->group_name.'/'.$template->template_name;
			}
			else
			{
				$view_url .= $group->group_name.(($template->template_name == 'index') ? '' : '/'.$template->template_name);
			}

			$column = array(
				$template_name,
				$template->hits,
				array('toolbar_items' => array(
					'view' => array(
						'href' => ee()->cp->masked_url($view_url),
						'title' => lang('view')
					),
					'edit' => array(
						'href' => cp_url('design/template/edit/' . $template->template_id),
						'title' => lang('edit')
					),
					'settings' => array(
						'href' => '',
						'rel' => 'modal-template-settings',
						'class' => 'm-link',
						'title' => lang('settings'),
						'data-template-id' => $template->template_id
					),
					'sync' => array(
						'href' => cp_url('design/template/sync/' . $template->template_id),
						'title' => lang('sync')
					),
				)),
				array(
					'name' => 'selection[]',
					'value' => $template->template_id,
					'data' => array(
						'confirm' => lang('temlate') . ': <b>' . htmlentities($template->template_name, ENT_QUOTES) . '</b>'
					)
				)
			);

			$attrs = array();

			if ($template_id && $template->template_id == $template_id)
			{
				$attrs = array('class' => 'selected');
			}

			$data[] = array(
				'attrs'		=> $attrs,
				'columns'	=> $column
			);
		}

		$table->setData($data);

		return $table;
	}

	public function index()
	{
		$this->manager();
	}

	public function manager($group_name = NULL)
	{
		if (is_null($group_name))
		{
			$group = ee('Model')->get('TemplateGroup')
				->filter('is_site_default', 'y')
				->first();
		}
		else
		{
			$group = ee('Model')->get('TemplateGroup')
				->filter('group_name', $group_name)
				->first();

			if ( ! $group)
			{
				show_error(sprintf(lang('error_no_template_group'), $group_name));
			}
		}

		if (ee()->input->post('bulk_action') == 'remove')
		{
			if ($this->hasEditTemplatePrivileges($group->group_id))
			{
				$this->remove(ee()->input->post('selection'));
			}
			else
			{
				show_error(lang('unauthorized_access'));
			}
		}
		elseif (ee()->input->post('bulk_action') == 'export')
		{
			$this->export(ee()->input->post('selection'));
		}

		$vars = array();

		$vars['show_new_template_button'] = TRUE;
		$vars['group_id'] = $group->group_name;

		$base_url = new URL('design/manager/' . $group->group_name, ee()->session->session_id());

		$table = $this->buildTableFromTemplateCollection($group->getTemplates());

		$vars['table'] = $table->viewData($base_url);
		$vars['form_url'] = $vars['table']['base_url'];

		if ( ! empty($vars['table']['data']))
		{
			// Paginate!
			$pagination = new Pagination(
				$vars['table']['limit'],
				$vars['table']['total_rows'],
				$vars['table']['page']
			);
			$vars['pagination'] = $pagination->cp_links($base_url);
		}

		// Set search results heading
		if ( ! empty($vars['table']['search']))
		{
			ee()->view->cp_heading = sprintf(
				lang('search_results_heading'),
				$vars['table']['total_rows'],
				$vars['table']['search']
			);
		}

		ee()->javascript->set_global('template_settings_url', cp_url('design/template/settings'));
		ee()->javascript->set_global('lang.remove_confirm', lang('template') . ': <b>### ' . lang('templates') . '</b>');
		ee()->cp->add_js_script(array(
			'file' => array(
				'cp/v3/confirm_remove',
				'cp/manager'
			),
		));

		$this->sidebarMenu($group->group_id);
		$this->stdHeader();
		ee()->view->cp_page_title = lang('template_manager');
		ee()->view->cp_heading = sprintf(lang('templates_in_group'), $group->group_name);

		ee()->cp->render('design/index', $vars);
	}

	private function remove($template_ids)
	{
		if ( ! is_array($template_ids))
		{
			$template_ids = array($template_ids);
		}

		$template_names = array();
		$templates = ee('Model')->get('Template', $template_ids)->all();

		foreach ($templates as $template)
		{
			$template_names[] = $template->getTemplateGroup()->group_name . '/' . $template->template_name;
		}

		$templates->delete();

		ee('Alert')->makeInline('settings-form')
			->asSuccess()
			->withTitle(lang('success'))
			->addToBody(lang('templates_removed_desc'))
			->addToBody($template_names);
	}

	/**
	 * Export templates
	 *
	 * @param  int|array $template_ids The ids of templates to export
	 * @return void
	 */
	protected function export($template_ids)
	{
		if ( ! is_array($template_ids))
		{
			$template_ids = array($template_ids);
		}

		// Create the Zip Archive
		$zipfilename = tempnam(sys_get_temp_dir(), '');
		$zip = new ZipArchive();
		if ($zip->open($zipfilename, ZipArchive::CREATE) !== TRUE)
		{
			ee('Alert')->makeInline('settings-form')
				->asIssue()
				->withTitle(lang('error_export'))
				->addToBody(lang('error_cannot_create_zip'));
			return;
		}

		// Loop through templates and add them to the zip
		$templates = ee('Model')->get('Template', $template_ids)
			->all()
			->each(function($template) use($zip) {
				$filename = $template->getTemplateGroup()->group_name . '/' . $template->template_name . '.html';
				$zip->addFromString($filename, $template->template_data);
			});

		$zip->close();

		$data = file_get_contents($zipfilename);
		unlink($zipfilename);

		ee()->load->helper('download');
		force_download('ExpressionEngine-templates.zip', $data);
		exit;
	}

}
// EOF