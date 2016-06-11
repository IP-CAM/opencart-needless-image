<?php
class ControllerModuleNeedlessimage extends Controller {
	private $error = array();
	var $version = 1.1;
	
	public function index() {   
		$this->load->language('module/needlessimage');
		$this->load->model('module/needlessimage');
		
		if ($this->config->get('needlessimage_version') < $this->version) {
			$this->model_module_needlessimage->install($this->version);
		}
				
		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('setting/setting');
		
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			if ( isset($this->request->post['directory']) && is_array($this->request->post['directory']) ) {
				$directories = array();
				
				foreach ($this->request->post['directory'] as $directory) {
					$directories[] = array('path' => str_replace('../', '', $directory['path']), 'recursive' => $directory['recursive'] ? 1 : 0);
				}
				
				$this->model_module_needlessimage->setDirectoriesDb($directories);
			}
			
			$this->session->data['success'] = $this->language->get('text_success');
			$this->redirect($this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL'));
		}
		
		$data['heading_title'] = $this->language->get('heading_title');
		
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_select_dir'] = $this->language->get('text_select_dir');
		$data['text_module'] = $this->language->get('text_module');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');
		$data['text_no_files_to_delete'] = $this->language->get('text_no_files_to_delete');
		
		$data['entry_directory'] = $this->language->get('entry_directory');
		$data['entry_recursive'] = $this->language->get('entry_recursive');
		
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_add'] = $this->language->get('button_add');
		$data['button_remove'] = $this->language->get('button_remove');
		$data['button_analyze'] = $this->language->get('button_analyze');
		$data['button_delete_selected'] = $this->language->get('button_delete_selected');
		$data['button_select_all'] = $this->language->get('button_select_all');
		$data['button_unselect_all'] = $this->language->get('button_unselect_all');
		
		$data['error_directory'] = $this->language->get('error_directory');
		$data['error_error'] = $this->language->get('error_error');
		
		$data['token'] = $this->session->data['token'];
		
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
		
		$data['breadcrumbs'] = array();
		
		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
			'separator' => false
		);
		
		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('text_module'),
			'href'      => $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL'),
			'separator' => ' :: '
		);
		
		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('module/needlessimage', 'token=' . $this->session->data['token'], 'SSL'),
			'separator' => ' :: '
		);
		
		$data['action'] = $this->url->link('module/needlessimage', 'token=' . $this->session->data['token'], 'SSL');
		$data['action_delete'] = $this->url->link('module/needlessimage/delete', 'token=' . $this->session->data['token'], 'SSL');
		
		$data['cancel'] = $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL');
		
		$data['directories'] = $this->model_module_needlessimage->getDirectoriesDb();
		$data['directories_fs'] = $this->model_module_needlessimage->getDirectoriesFs();
		
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		
		$this->response->setOutput($this->load->view('module/needlessimage.tpl', $data));
		
	}
	
	public function analyze() {
		$files_to_delete = array();
		
		if ( isset($this->request->post['directory']) && is_array($this->request->post['directory']) ) {
			foreach ($this->request->post['directory'] as $key => $directory) {
				if ($directory['path']) {
					$files_to_delete[$key] = $this->getNeedlessImages($directory['path'], $directory['recursive'] ? true : false);
				} else {
					$files_to_delete[$key] = array();
				}
			}
		}
		
		$this->response->setOutput(json_encode($files_to_delete));
	}
	
	public function delete() {
		$this->load->language('module/needlessimage');
		$this->load->model('module/needlessimage');
		$json = array(
			'message' => '',
			'data' => array(),
		);
		
		if ( $this->checkPermission() ) {
			if (isset($this->request->post['path']) && $this->request->post['path']) {
				$recursive = isset($this->request->post['recursive']) && $this->request->post['recursive'] ? true : false;
				
				$deleted_files = 0;
				if ( isset($this->request->post['delete']) && is_array($this->request->post['delete']) ) {
					$files_temp = $this->getNeedlessImages($this->request->post['path'], $recursive);
					$files_to_delete_all = array();
					
					foreach ($files_temp as $file) {
						$files_to_delete_all[] = $file['path'];
					}
					
					foreach ($this->request->post['delete'] as $file) {
						if ( in_array($file, $files_to_delete_all) ) {
							if ( unlink(rtrim(DIR_IMAGE . str_replace('../', '', $file), '/')) ) {
								$deleted_files++;
							}
						}
					}
				}
				
				$json['message'] = '<div class="' . ($deleted_files ? 'success' : 'attention') . '">' . sprintf($this->language->get('text_deleted'), $deleted_files) . '</div>';
				$json['data'] = $this->getNeedlessImages($this->request->post['path'], $recursive);
			} else {
				$json['message'] = '<div class="warning">' . $this->language->get('error_directory') . '</div>';
			}
		} else {
			$json['message'] = '<div class="warning">' . $this->language->get('error_permission') . '</div>';
		}
		
		$this->response->setOutput(json_encode($json));
	}
	
	private function getNeedlessImages($directory, $recursive) {
		$http_image = defined('HTTP_IMAGE') ? HTTP_IMAGE : HTTPS_CATALOG . 'image/';
		$this->load->model('module/needlessimage');
		
		$used_files = $this->model_module_needlessimage->getImagesDb();
		$files = $this->model_module_needlessimage->getImagesFs($directory, '*', $recursive);
		$files_to_delete = array();
		
		foreach ($files as $file) {
			if ( !in_array($file, $used_files) ) {
				$files_to_delete[] = array(
					'name' => '<a href="' . $http_image . $file . '" target="_blank">' . $file . '</a>', 
					'path' => $file,
				);
				
				$file_info = pathinfo($file);
				
				$cached_files = $this->model_module_needlessimage->getImagesFsCached($file);
				foreach ($cached_files as $cached_file) {
					$cached_file_info = pathinfo($cached_file);
					$cached_file_size = str_replace($file_info['filename'].'-', '', $cached_file_info['filename']);
					
					$files_to_delete[] = array(
						'name' => '<a href="' . $http_image . $cached_file . '" target="_blank">' . $file . '</a> <b>(cache) [' . $cached_file_size . ']</b>',
						'path' => $cached_file,
					);
				}
			}
		}
		
		return $files_to_delete;
	}
	
	private function validate() {
		if ( !$this->checkPermission() ) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		
		return $this->error ? false : true;
	}
	
	private function checkPermission() {
		return $this->user->hasPermission('modify', 'module/needlessimage') ? true : false;
	}
	
	public function install() {
		$this->load->model('module/needlessimage');
		$this->model_module_needlessimage->install($this->version);
	}
	
	public function uninstall() {
		$this->load->model('module/needlessimage');
		$this->model_module_needlessimage->uninstall();
	}
}
?>