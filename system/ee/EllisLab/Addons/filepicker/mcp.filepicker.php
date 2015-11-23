<?php

use EllisLab\ExpressionEngine\Model\File\UploadDestination;
use EllisLab\Addons\FilePicker\FilePicker as Picker;

class Filepicker_mcp {

	private $images = FALSE;

	public function __construct()
	{
		$this->picker = new Picker();
		$this->base_url = 'addons/settings/filepicker';
		$this->access = FALSE;

		if (ee()->cp->allowed_group('can_access_files'))
		{
			$this->access = TRUE;
		}

		ee()->lang->loadfile('filemanager');
	}

	public function index()
	{
		// check if we have a request for a specific file id
		$file = ee()->input->get('file');
		if ( ! empty($file))
		{
			$this->fileInfo($file);
		}

		if ($this->access === FALSE)
		{
			show_error(lang('unauthorized_access'));
		}

		$dirs = ee()->api->get('UploadDestination')
			->with('Files')
			->filter('site_id', ee()->config->item('site_id'))
			->filter('module_id', 0);

		$member_group = ee()->session->userdata['group_id'];
		$dirs = $dirs->all()->filter(function($dir) use ($member_group){
			return $dir->memberGroupHasAccess($member_group);
		});

		$input_directory = ($dirs->count() == 1)
			? $dirs->first()->id
			: ee()->input->get('directory');

		if ( ! empty($input_directory))
		{
			$id = $input_directory;
		}

		// Restrict things if we're in a file field that enforces a directory
		if (isset($id)
			&& (ctype_digit($id) || is_int($id))
			&& ee()->input->get('restrict') == TRUE)
		{
			$dirs = $dirs->filter('id', (int) $id);
		}

		$directories = $dirs->indexBy('id');

		if (empty($id) || $id == 'all')
		{
			$id = 'all';
			$files = ee('Model')->get('File')
				->filter('upload_location_id', 'IN', $dirs->getIds())
				->filter('site_id', ee()->config->item('site_id'))
				->all();

			$type = ee()->input->get('type') ?: 'list';
		}
		else
		{
			if (empty($directories[$id]))
			{
				$id = 1;
				//show_error(lang('invalid_upload_destination'));
			}

			$dir = $directories[$id];
			$files = $dir->Files;

			// Only show member directories if it's avatars
			if ($dir->Module && $dir->Module->module_name == 'Member')
			{
				if ($dir->server_path == ee()->config->item('avatar_path'))
				{
					$files = $dir->server_path->files;

					// Only show system avatars
					$files = $files->filter(function($file) use($dir) {
						$path = $dir->server_path . $file->file_name;
						return ! is_writable($path);
					});
				}
				else
				{
					show_error(lang('invalid_upload_destination'));
				}
			}

			$type = ee()->input->get('type') ?: $dir->default_modal_view;
		}

		// Filter out any files that are no longer on disk
		$files = $files->filter(function($file) { return $file->exists(); });

		$base_url = ee('CP/URL', $this->base_url);
		$base_url->setQueryStringVariable('directory', $id);
		$base_url->setQueryStringVariable('type', $type);

		// Continue restricting by directory
		if (ee()->input->get('restrict', FALSE))
		{
			$base_url->setQueryStringVariable('restrict', TRUE);
		}

		if (ee()->input->get('hasFilters') !== '0')
		{
			$vars['type'] = $type;
			$filters = ee('CP/Filter');

			$directories = array_filter($directories, function($dir) {return $dir->module_id == 0;});
			if (count($directories) > 1)
			{
				$directories = array_map(function($dir) {return $dir->name;}, $directories);
				$directories = array('all' => lang('all')) + $directories;

				$dirFilter = ee('CP/Filter')->make('directory', lang('directory'), $directories)
					->disableCustomValue();

				$filters = ee('CP/Filter')->add($dirFilter);
			}

			$filters = $filters->add('Perpage', $files->count(), 'show_all_files');

			$imgOptions = array(
				'thumb' => 'thumbnails',
				'list' => 'list'
			);
			$imgFilter = ee('CP/Filter')->make('type', lang('picker_type'), $imgOptions)
				->disableCustomValue()
				->setDefaultValue($type);
			$filters = $filters->add($imgFilter);

			$perpage = $filters->values();
			$perpage = $perpage['perpage'];
			$vars['filters'] = $filters->render($base_url);
		}

		if ($this->images || $type == 'thumb')
		{
			$vars['type'] = 'thumb';
			$vars['files'] = $files;
			$vars['form_url'] = $base_url;
			$vars['data_url_base'] = $this->base_url;
		}
		else
		{
			$table = $this->picker->buildTableFromFileCollection($files, $perpage, ee()->input->get_post('selected'));

			$base_url->setQueryStringVariable('sort_col', $table->sort_col);
			$base_url->setQueryStringVariable('sort_dir', $table->sort_dir);

			$vars['table'] = $table->viewData($base_url);
			$vars['form_url'] = $vars['table']['base_url'];
		}

		if (ee()->input->get('hasUpload') !== '0')
		{
			$vars['upload'] = ee('CP/URL', $this->picker->base_url."upload");
			$vars['upload']->setQueryStringVariable('directory', $id);
		}

		$vars['dir'] = $id;

		if ( ! empty($vars['table']['data']))
		{
			// Paginate!
			$vars['pagination'] = ee('CP/Pagination', $vars['table']['total_rows'])
				->perPage($vars['table']['limit'])
				->currentPage($vars['table']['page'])
				->render($base_url);
		}

		$vars['cp_heading'] = $id == 'all' ? lang('all_files') : sprintf(lang('files_in_directory'), $dir->name);

		return ee('View')->make('filepicker:ModalView')->render($vars);
	}

	public function modal()
	{
		$this->base_url = $this->picker->controller;
		ee()->output->_display($this->index());
		exit();
	}

	public function images()
	{
		$this->images = TRUE;
		$this->base_url = $this->picker->base_url . 'images';
		ee()->output->_display($this->index());
		exit();
	}

	/**
	 * Return an AJAX response for a particular file ID
	 *
	 * @param mixed $id
	 * @access private
	 * @return void
	 */
	private function fileInfo($id)
	{
		$file = ee('Model')->get('File', $id)
			->filter('site_id', ee()->config->item('site_id'))
			->first();

		if ( ! $file || ! $file->exists())
		{
			ee()->output->send_ajax_response(lang('file_not_found'), TRUE);
		}

		$member_group = ee()->session->userdata['group_id'];

		if ($file->memberGroupHasAccess($member_group) === FALSE || $this->access === FALSE)
		{
			ee()->output->send_ajax_response(lang('unauthorized_access'), TRUE);
		}

		$result = $file->getValues();

		$result['path'] = $file->getAbsoluteURL();
		$result['thumb_path'] = ee('Thumbnail')->get($file)->url;
		$result['isImage'] = $file->isImage();

		ee()->output->send_ajax_response($result);
	}

	public function upload()
	{
		$dir_id = ee()->input->get('directory');

		if (empty($dir_id))
		{
			show_404();
		}

		$dir = ee('Model')->get('UploadDestination', $dir_id)
			->filter('site_id', ee()->config->item('site_id'))
			->first();

		if ( ! $dir)
		{
			show_error(lang('no_upload_destination'));
		}

		if ( ! $dir->memberGroupHasAccess(ee()->session->userdata['group_id']))
		{
			show_error(lang('unauthorized_access'));
		}

		if ( ! $dir->exists())
		{
			$upload_edit_url = ee('CP/URL', 'files/uploads/edit/' . $dir->id);
			ee('CP/Alert')->makeStandard()
				->asIssue()
				->withTitle(lang('file_not_found'))
				->addToBody(sprintf(lang('directory_not_found'), $dir->server_path))
				->addToBody(sprintf(lang('check_upload_settings'), $upload_edit_url))
				->now();

			show_404();
		}

		// Check permissions on the directory
		if ( ! $dir->isWritable())
		{
			ee('CP/Alert')->makeInline('shared-form')
				->asIssue()
				->withTitle(lang('dir_not_writable'))
				->addToBody(sprintf(lang('dir_not_writable_desc'), $dir->server_path))
				->now();
		}

		$vars = array(
			'ajax_validate' => TRUE,
			'has_file_input' => TRUE,
			'base_url' => ee('CP/URL')->make($this->picker->base_url . 'upload', array('directory' => $dir_id)),
			'save_btn_text' => 'btn_upload_file',
			'save_btn_text_working' => 'btn_saving',
			'sections' => array(
				array(
					array(
						'title' => 'file',
						'fields' => array(
							'file' => array(
								'type' => 'file',
								'required' => TRUE
							)
						)
					),
					array(
						'title' => 'title',
						'fields' => array(
							'title' => array(
								'type' => 'text',
							)
						)
					),
					array(
						'title' => 'description',
						'fields' => array(
							'description' => array(
								'type' => 'textarea',
							)
						)
					),
					array(
						'title' => 'credit',
						'fields' => array(
							'credit' => array(
								'type' => 'text',
							)
						)
					),
					array(
						'title' => 'location',
						'fields' => array(
							'location' => array(
								'type' => 'text',
							)
						)
					),
				)
			)
		);

		ee()->load->library('form_validation');
		ee()->form_validation->set_rules(array(
			array(
				'field' => 'title',
				'label' => 'lang:title',
				'rules' => 'strip_tags|trim|valid_xss_check'
			),
			array(
				'field' => 'description',
				'label' => 'lang:description',
				'rules' => 'strip_tags|trim|valid_xss_check'
			),
			array(
				'field' => 'credit',
				'label' => 'lang:credit',
				'rules' => 'strip_tags|trim|valid_xss_check'
			),
			array(
				'field' => 'location',
				'label' => 'lang:location',
				'rules' => 'strip_tags|trim|valid_xss_check'
			),
		));

		if (AJAX_REQUEST && ! empty($_POST))
		{
			ee()->form_validation->run_ajax();
			exit;
		}
		elseif (ee()->form_validation->run() !== FALSE)
		{
			// PUNT! @TODO Break away from the old Filemanger Library
			ee()->load->library('filemanager');
			$upload_response = ee()->filemanager->upload_file($dir_id, 'file');
			if (isset($upload_response['error']))
			{
				ee('CP/Alert')->makeInline('shared-form')
					->asIssue()
					->withTitle(lang('upload_filedata_error'))
					->addToBody($upload_response['error'])
					->now();
			}
			else
			{
				$file = ee('Model')->get('File', $upload_response['file_id'])->first();
				$file->upload_location_id = $dir_id;
				$file->site_id = ee()->config->item('site_id');

				$file->title = (ee()->input->post('title')) ?: $file->file_name;
				$file->description = ee()->input->post('description');
				$file->credit = ee()->input->post('credit');
				$file->location = ee()->input->post('location');

				$file->uploaded_by_member_id = ee()->session->userdata('member_id');
				$file->upload_date = ee()->localize->now;
				$file->modified_by_member_id = ee()->session->userdata('member_id');
				$file->modified_date = ee()->localize->now;

				$file->save();
				ee()->session->set_flashdata('file_id', $upload_response['file_id']);

				return $this->fileInfo($file->getId());
			}
		}
		elseif (ee()->form_validation->errors_exist())
		{
			ee('CP/Alert')->makeInline('shared-form')
				->asIssue()
				->withTitle(lang('upload_filedata_error'))
				->addToBody(lang('upload_filedata_error_desc'))
				->now();
		}

		$vars['cp_page_title'] = lang('file_upload');
		$out = ee()->cp->render('_shared/form', $vars, TRUE);
		$out = ee()->cp->render('filepicker:UploadView', array('content' => $out));
		ee()->output->_display($out);
		exit();
	}

	protected function ajaxValidation(ValidationResult $result)
	{
		if (ee()->input->is_ajax_request())
		{
			$field = ee()->input->post('ee_fv_field');

			if ($result->hasErrors($field))
			{
				return array('error' => $result->renderError($field));
			}
			else
			{
				return array('success');
			}
		}

		return NULL;
	}
}
?>
