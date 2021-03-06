<?php

/**
 * @class  moduleAdminController
 * @author NAVER (developers@xpressengine.com)
 * @brief  admin controller class of the module module
 **/
class moduleAdminController extends module {

	/**
	 * @brief Initialization
	 **/
	function init(){
	}

	/**
	 * @brief Add the module category
	 **/
	function procModuleAdminInsertCategory(){
		$args = new stdClass();
		$args->title = Context::get('title');
		$output = executeQuery('module.insertModuleCategory', $args);
		if(!$output->toBool()) return $output;

		$this->setMessage("success_registed");

		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispModuleAdminCategory');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief Update category
	 **/
	function procModuleAdminUpdateCategory(){
		$output = $this->doUpdateModuleCategory();
		if(!$output->toBool()) return $output;

		$this->setMessage('success_updated');

		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispModuleAdminCategory');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief Delete category
	 **/
	function procModuleAdminDeleteCategory(){
		$output = $this->doDeleteModuleCategory();
		if(!$output->toBool()) return $output;

		$this->setMessage('success_deleted');

		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispModuleAdminCategory');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief Change the title of the module category
	 **/
	function doUpdateModuleCategory(){
		$args = new stdClass();
		$args->title = Context::get('title');
		$args->module_category_srl = Context::get('module_category_srl');
		return executeQuery('module.updateModuleCategory', $args);
	}

	/**
	 * @brief Delete the module category
	 **/
	function doDeleteModuleCategory(){
		$args = new stdClass();
		$args->module_category_srl = Context::get('module_category_srl');
		return executeQuery('module.deleteModuleCategory', $args);
	}

	/**
	 * @brief Copy Module
	 **/
	function procModuleAdminCopyModule(){
		// Get information of the target module to copy
		$module_srl = Context::get('module_srl');
		if(!$module_srl) return;
		// Get module name to create and browser title
		$clones = array();
		$args = Context::getAll();
		for($i = 1; $i <= 10; $i++){
			$mid = trim($args->{"mid_" . $i});
			if(!$mid) continue;
			if(!preg_match("/^[a-zA-Z]([a-zA-Z0-9_]*)$/i", $mid)) return new BaseObject(-1, 'msg_limit_mid');
			$browser_title = $args->{"browser_title_" . $i};
			if(!$mid) continue;
			if($mid && !$browser_title) $browser_title = $mid;
			$clones[$mid] = $browser_title;
		}
		if(!count($clones)) return;

		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		// Get module information
		$columnList = array('module', 'module_category_srl', 'layout_srl', 'use_mobile', 'mlayout_srl', 'menu_srl', 'site_srl', 'skin', 'mskin', 'description', 'mcontent', 'open_rss', 'header_text', 'footer_text', 'regdate');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl, $columnList);
		// Get permission information
		$module_args = new stdClass();
		$module_args->module_srl = $module_srl;
		$output = executeQueryArray('module.getModuleGrants', $module_args);
		$grant = array();
		if($output->data){
			foreach($output->data as $key => $val) $grant[$val->name][] = $val->group_srl;
		}

		// get Extra Vars
		$extra_args = new stdClass();
		$extra_args->module_srl = $module_srl;
		$extra_output = executeQueryArray('module.getModuleExtraVars', $extra_args);
		$extra_vars = new stdClass();
		if($extra_output->toBool() && is_array($extra_output->data)){
			foreach($extra_output->data as $info){
				$extra_vars->{$info->name} = $info->value;
			}
		}

		$tmpModuleSkinVars = $oModuleModel->getModuleSkinVars($module_srl);
		$tmpModuleMobileSkinVars = $oModuleModel->getModuleMobileSkinVars($module_srl);

		if($tmpModuleSkinVars){
			foreach($tmpModuleSkinVars as $key => $value){
				$moduleSkinVars->{$key} = $value->value;
			}
		}

		if($tmpModuleMobileSkinVars){
			foreach($tmpModuleMobileSkinVars as $key => $value){
				$moduleMobileSkinVars->{$key} = $value->value;
			}
		}

		$oDB = &DB::getInstance();
		$oDB->begin();
		// Copy a module
		$triggerObj = new stdClass();
		$triggerObj->originModuleSrl = $module_srl;
		$triggerObj->moduleSrlList = array();

		$errorLog = array();
		foreach($clones as $mid => $browser_title){
			$clone_args = new stdClass();
			$clone_args = clone($module_info);
			$clone_args->module_srl = null;
			$clone_args->content = null;
			$clone_args->mid = $mid;
			$clone_args->browser_title = $browser_title;
			$clone_args->is_default = 'N';
			// Create a module
			$output = $oModuleController->insertModule($clone_args);

			if(!$output->toBool()){
				$errorLog[] = $mid . ' : ' . $output->message;
				continue;
			}
			$module_srl = $output->get('module_srl');

			if($module_info->module == 'page' && $extra_vars->page_type == 'ARTICLE'){
				// copy document
				$oDocumentAdminController = getAdminController('document');
				$copyOutput = $oDocumentAdminController->copyDocumentModule(array($extra_vars->document_srl), $module_srl, $module_info->category_srl);
				$document_srls = $copyOutput->get('copied_srls');
				if($document_srls && count($document_srls) > 0){
					$extra_vars->document_srl = array_pop($document_srls);
				}

				if($extra_vars->mdocument_srl){
					$copyOutput = $oDocumentAdminController->copyDocumentModule(array($extra_vars->mdocument_srl), $module_srl, $module_info->category_srl);
					$copiedSrls = $copyOutput->get('copied_srls');
					if($copiedSrls && count($copiedSrls) > 0){
						$extra_vars->mdocument_srl = array_pop($copiedSrls);
					}
				}
			}

			// Grant module permissions
			if(count($grant)) $oModuleController->insertModuleGrants($module_srl, $grant);
			if($extra_vars) $oModuleController->insertModuleExtraVars($module_srl, $extra_vars);

			if($moduleSkinVars) $oModuleController->insertModuleSkinVars($module_srl, $moduleSkinVars);
			if($moduleMobileSkinVars) $oModuleController->insertModuleMobileSkinVars($module_srl, $moduleMobileSkinVars);

			array_push($triggerObj->moduleSrlList, $module_srl);
		}

		$output = ModuleHandler::triggerCall('module.procModuleAdminCopyModule', 'after', $triggerObj);

		$oDB->commit();

		if(count($errorLog) > 0){
			$message = implode('\n', $errorLog);
			$this->setMessage($message);
		}
		else{
			$mseeage = $lang->success_registed;
			$this->setMessage('success_registed');
		}

		if(!in_array(Context::getRequestMethod(), array('XMLRPC', 'JSON'))){
			global $lang;
			htmlHeader();
			alertScript($message);
			reload(true);
			closePopupScript();
			htmlFooter();
			Context::close();
			exit;
		}
	}

	/**
	 * @brief Save the module permissions
	 **/
	function procModuleAdminInsertGrant(){
		$oModuleController = getController('module');
		$oModuleModel = getModel('module');
		// Get module_srl
		$module_srl = Context::get('module_srl');
		// Get information of the module
		$columnList = array('module_srl', 'module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl, $columnList);
		if(!$module_info) return new BaseObject(-1, 'msg_invalid_request');
		// Register Admin ID
		$oModuleController->deleteAdminId($module_srl);
		$admin_member = Context::get('admin_member');
		if($admin_member){
			$admin_members = explode(',', $admin_member);
			for($i = 0; $i < count($admin_members); $i++){
				$admin_id = trim($admin_members[$i]);
				if(!$admin_id) continue;
				$oModuleController->insertAdminId($module_srl, $admin_id);

			}
		}
		// List permissions
		$xml_info = $oModuleModel->getModuleActionXML($module_info->module);

		$grant_list = $xml_info->grant;

		$grant_list->access = new stdClass();
		$grant_list->access->default = 'guest';
		$grant_list->manager = new stdClass();
		$grant_list->manager->default = 'manager';

		$grant = new stdClass();
		foreach($grant_list as $grant_name => $grant_info){
			// Get the default value
			$default = Context::get($grant_name . '_default');
			// -1 = Log-in user only, -2 = site members only, 0 = all users
			$grant->{$grant_name} = array();
			if(strlen($default)){
				$grant->{$grant_name}[] = $default;
				continue;
				// users in a particular group
			}
			else{
				$group_srls = Context::get($grant_name);
				if($group_srls){
					if(strpos($group_srls, '|@|') !== false) $group_srls = explode('|@|', $group_srls);
					elseif(strpos($group_srls, ',') !== false) $group_srls = explode(',', $group_srls);
					else $group_srls = array($group_srls);
					$grant->{$grant_name} = $group_srls;
				}
				continue;
			}
			$grant->{$group_srls} = array(); // dead code????
		}

		// Stored in the DB
		$args = new stdClass();
		$args->module_srl = $module_srl;
		$output = executeQuery('module.deleteModuleGrants', $args);
		if(!$output->toBool()) return $output;
		// Permissions stored in the DB
		foreach($grant as $grant_name => $group_srls){
			foreach($group_srls as $val){
				$args = new stdClass();
				$args->module_srl = $module_srl;
				$args->name = $grant_name;
				$args->group_srl = $val;
				$output = executeQuery('module.insertModuleGrant', $args);
				if(!$output->toBool()) return $output;
			}
		}
		$this->setMessage('success_registed');
	}

	/**
	 * @brief Updating Skins
	 **/
	function procModuleAdminUpdateSkinInfo(){
		// Get information of the module_srl
		$module_srl = Context::get('module_srl');
		$mode = Context::get('_mode');
		$mode = $mode === 'P' ? 'P' : 'M';

		$oModuleModel = getModel('module');
		$columnList = array('module_srl', 'module', 'skin', 'mskin');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl, $columnList);
		if($module_info->module_srl){
			if($mode === 'M'){
				$skin = $module_info->mskin;
			}
			else{
				$skin = $module_info->skin;
			}

			// Get skin information (to check extra_vars)
			$module_path = _DAOL_PATH_ . 'modules/' . $module_info->module;

			if($mode === 'M'){
				$skin_info = $oModuleModel->loadSkinInfo($module_path, $skin, 'm.skins');
				$skin_vars = $oModuleModel->getModuleMobileSkinVars($module_srl);
			}
			else{
				$skin_info = $oModuleModel->loadSkinInfo($module_path, $skin);
				$skin_vars = $oModuleModel->getModuleSkinVars($module_srl);
			}

			// Check received variables (unset such variables as act, module_srl, page, mid, module)
			$obj = Context::getRequestVars();
			unset($obj->act);
			unset($obj->error_return_url);
			unset($obj->module_srl);
			unset($obj->page);
			unset($obj->mid);
			unset($obj->module);
			unset($obj->_mode);
			// Separately handle if a type of extra_vars is an image in the original skin_info
			if($skin_info->extra_vars){
				foreach($skin_info->extra_vars as $vars){
					if($vars->type != 'image') continue;

					$image_obj = $obj->{$vars->name};
					// Get a variable to delete
					$del_var = $obj->{"del_" . $vars->name};
					unset($obj->{"del_" . $vars->name});
					if($del_var == 'Y'){
						FileHandler::removeFile($skin_vars[$vars->name]->value);
						continue;
					}
					// Use the previous data if not uploaded
					if(!$image_obj['tmp_name']){
						$obj->{$vars->name} = $skin_vars[$vars->name]->value;
						continue;
					}
					// Ignore if the file is not successfully uploaded
					if(!is_uploaded_file($image_obj['tmp_name']) || !checkUploadedFile($image_obj['tmp_name'])){
						unset($obj->{$vars->name});
						continue;
					}
					// Ignore if the file is not an image
					if(!preg_match("/\.(jpg|jpeg|gif|png)$/i", $image_obj['name'])){
						unset($obj->{$vars->name});
						continue;
					}
					// Upload the file to a path
					$path = sprintf("./files/attach/images/%s/", $module_srl);
					// Create a directory
					if(!FileHandler::makeDir($path)) return false;

					$filename = $path . $image_obj['name'];
					// Move the file
					if(!move_uploaded_file($image_obj['tmp_name'], $filename)){
						unset($obj->{$vars->name});
						continue;
					}
					// Upload the file
					FileHandler::removeFile($skin_vars[$vars->name]->value);
					// Change a variable
					unset($obj->{$vars->name});
					$obj->{$vars->name} = $filename;
				}
			}
			// Load the entire skin of the module and then remove the image
			/*
			if($skin_info->extra_vars){
				foreach($skin_info->extra_vars as $vars){
					if($vars->type!='image') continue;
					$value = $skin_vars[$vars->name];
					if(file_exists($value)) @unlink($value);
				}
			}
			*/
			$oModuleController = getController('module');

			if($mode === 'M'){
				$output = $oModuleController->insertModuleMobileSkinVars($module_srl, $obj);
			}
			else{
				$output = $oModuleController->insertModuleSkinVars($module_srl, $obj);
			}
			if(!$output->toBool()){
				return $output;
			}

		}

		$this->setMessage('success_saved');
		$this->setRedirectUrl(Context::get('error_return_url'));
	}

	/**
	 * @brief List module information
	 **/
	function procModuleAdminModuleSetup(){
		$vars = Context::getRequestVars();

		if(!$vars->module_srls) return new BaseObject(-1, 'msg_invalid_request');

		$module_srls = explode(',', $vars->module_srls);
		if(!count($module_srls)) return new BaseObject(-1, 'msg_invalid_request');

		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		$columnList = array('module_srl', 'module', 'menu_srl', 'site_srl', 'mid', 'browser_title', 'is_default', 'content', 'mcontent', 'open_rss', 'regdate');
		$updateList = array('module_category_srl', 'layout_srl', 'skin', 'mlayout_srl', 'mskin', 'description', 'header_text', 'footer_text'); //use_mobile
		foreach($updateList as $key => $val){
			if(!strlen($vars->{$val})){
				unset($updateList[$key]);
				$columnList[] = $val;
			}
		}

		foreach($module_srls as $module_srl){
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl, $columnList);

			foreach($updateList as $val){
				$module_info->{$val} = $vars->{$val};
			}
			$output = $oModuleController->updateModule($module_info);
		}

		$this->setMessage('success_registed');
		if(!in_array(Context::getRequestMethod(), array('XMLRPC', 'JSON'))){
			if(Context::get('success_return_url')){
				$this->setRedirectUrl(Context::get('success_return_url'));
			}
			else{
				global $lang;
				htmlHeader();
				alertScript($lang->success_registed);
				closePopupScript();
				htmlFooter();
				Context::close();
				exit;
			}
		}
	}

	/**
	 * @brief List permissions of the module
	 **/
	function procModuleAdminModuleGrantSetup(){
		$module_srls = Context::get('module_srls');
		if(!$module_srls) return new BaseObject(-1, 'msg_invalid_request');

		$modules = explode(',', $module_srls);
		if(!count($modules)) return new BaseObject(-1, 'msg_invalid_request');

		$oModuleController = getController('module');
		$oModuleModel = getModel('module');

		$columnList = array('module_srl', 'module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($modules[0], $columnList);
		$xml_info = $oModuleModel->getModuleActionXml($module_info->module);
		$grant_list = $xml_info->grant;

		$grant_list->access = new stdClass();
		$grant_list->access->default = 'guest';
		$grant_list->manager = new stdClass();
		$grant_list->manager->default = 'manager';

		$grant = new stdClass();

		foreach($grant_list as $grant_name => $grant_info){
			// Get the default value
			$default = Context::get($grant_name . '_default');
			// -1 = Sign only, 0 = all users
			if(strlen($default)){
				$grant->{$grant_name}[] = $default;
				continue;
				// Users in a particular group
			}
			else{
				$group_srls = Context::get($grant_name);
				if($group_srls){
					if(!is_array($group_srls)){
						if(strpos($group_srls, '|@|') !== false) $group_srls = explode('|@|', $group_srls);
						elseif(strpos($group_srls, ',') !== false) $group_srls = explode(',', $group_srls);
						else $group_srls = array($group_srls);
					}
					$grant->{$grant_name} = $group_srls;
				}
				continue;
			}
			$grant->{$group_srls} = array();
		}


		// Stored in the DB
		foreach($modules as $module_srl){
			$args = new stdClass();
			$args->module_srl = $module_srl;
			$output = executeQuery('module.deleteModuleGrants', $args);
			if(!$output->toBool()) continue;
			// Permissions stored in the DB
			foreach($grant as $grant_name => $group_srls){
				foreach($group_srls as $val){
					$args = new stdClass();
					$args->module_srl = $module_srl;
					$args->name = $grant_name;
					$args->group_srl = $val;
					$output = executeQuery('module.insertModuleGrant', $args);
					if(!$output->toBool()) return $output;
				}
			}
		}
		$this->setMessage('success_registed');
		if(!in_array(Context::getRequestMethod(), array('XMLRPC', 'JSON'))){
			if(Context::get('success_return_url')){
				$this->setRedirectUrl(Context::get('success_return_url'));
			}
			else{
				global $lang;
				htmlHeader();
				alertScript($lang->success_registed);
				closePopupScript();
				htmlFooter();
				Context::close();
				exit;
			}
		}
	}

	/**
	 * @brief Add/Update language
	 **/
	function procModuleAdminInsertLang(){
		// Get language code
		$site_module_info = Context::get('site_module_info');
		$target = Context::get('target');
		$module = Context::get('module');
		$args = new stdClass();
		$args->site_srl = (int)$site_module_info->site_srl;
		$args->name = str_replace(' ', '_', Context::get('lang_code'));
		$args->lang_name = str_replace(' ', '_', Context::get('lang_name'));
		if(!empty($args->lang_name)) $args->name = $args->lang_name;

		// if args->name is empty, random generate for user define language
		if(empty($args->name)) $args->name = 'userLang' . date('YmdHis') . '' . sprintf('%03d', mt_rand(0, 100));

		if(!$args->name) return new BaseObject(-1, 'msg_invalid_request');
		// Check whether a language code exists
		$output = executeQueryArray('module.getLang', $args);
		if(!$output->toBool()) return $output;
		// If exists, clear the old values for updating
		if($output->data) $output = executeQuery('module.deleteLang', $args);
		if(!$output->toBool()) return $output;
		// Enter
		$lang_supported = Context::get('lang_supported');
		foreach($lang_supported as $key => $val){
			$args->lang_code = $key;
			$args->value = trim(Context::get($key));

			// if request method is json, strip slashes
			if(Context::getRequestMethod() == 'JSON' && version_compare(PHP_VERSION, "5.4.0", "<") && get_magic_quotes_gpc()){
				$args->value = stripslashes($args->value);
			}

			if($args->value){
				$output = executeQuery('module.insertLang', $args);
				if(!$output->toBool()) return $output;
			}
		}
		$this->makeCacheDefinedLangCode($args->site_srl);

		$this->add('name', $args->name);
		$this->setMessage("success_saved", 'info');

		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', $module, 'target', $target, 'act', 'dispModuleAdminLangcode');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief Remove language
	 **/
	function procModuleAdminDeleteLang(){
		// Get language code
		$site_module_info = Context::get('site_module_info');
		$args = new stdClass();
		$args->site_srl = (int)$site_module_info->site_srl;
		$args->name = str_replace(' ', '_', Context::get('name'));
		$args->lang_name = str_replace(' ', '_', Context::get('lang_name'));
		if(!empty($args->lang_name)) $args->name = $args->lang_name;
		if(!$args->name) return new BaseObject(-1, 'msg_invalid_request');

		$output = executeQuery('module.deleteLang', $args);
		if(!$output->toBool()) return $output;
		$this->makeCacheDefinedLangCode($args->site_srl);

		$this->setMessage("success_deleted", 'info');

		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispModuleAdminLangcode');
		$this->setRedirectUrl($returnUrl);
	}

	function procModuleAdminGetList(){
		if(!Context::get('is_logged')) return new BaseObject(-1, 'msg_not_permitted');

		$oModuleController = getController('module');
		$oModuleModel = getModel('module');
		// Variable setting for site keyword
		$site_keyword = Context::get('site_keyword');
		$site_srl = Context::get('site_srl');
		$vid = Context::get('vid');
		// If there is no site keyword, use as information of the current virtual site
		$args = new stdClass();
		$logged_info = Context::get('logged_info');
		$site_module_info = Context::get('site_module_info');
		if($site_keyword) $args->site_keyword = $site_keyword;

		if(!$site_srl){
			if($logged_info->is_admin == 'Y' && !$site_keyword && !$vid) $args->site_srl = 0;
			else $args->site_srl = (int)$site_module_info->site_srl;
		}
		else $args->site_srl = $site_srl;

		$args->sort_index1 = 'sites.domain';

		$moduleCategorySrl = array();
		// Get a list of modules at the site
		$output = executeQueryArray('module.getSiteModules', $args);
		$mid_list = array();
		if(count($output->data)){
			foreach($output->data as $val){
				$module = trim($val->module);
				if(!$module) continue;

				// replace user defined lang.
				$oModuleController->replaceDefinedLangCode($val->browser_title);

				$obj = new stdClass();
				$obj->module_srl = $val->module_srl;
				$obj->layout_srl = $val->layout_srl;
				$obj->browser_title = $val->browser_title;
				$obj->mid = $val->mid;
				$obj->module_category_srl = $val->module_category_srl;
				if($val->module_category_srl > 0){
					array_push($moduleCategorySrl, $val->module_category_srl);
				}
				$mid_list[$module]->list[$val->mid] = $obj;
			}
		}

		// Get module category name
		$moduleCategorySrl = array_unique($moduleCategorySrl);
		$output = $oModuleModel->getModuleCategories($moduleCategorySrl);
		$categoryNameList = array();
		if(is_array($output)){
			foreach($output as $value){
				$categoryNameList[$value->module_category_srl] = $value->title;
			}
		}

		$selected_module = Context::get('selected_module');
		if(count($mid_list)){
			foreach($mid_list as $module => $val){
				if(!$selected_module) $selected_module = $module;
				$xml_info = $oModuleModel->getModuleInfoXml($module);

				if(!$xml_info){
					unset($mid_list[$module]);
					continue;
				}

				$mid_list[$module]->title = $xml_info->title;

				// change module category srl to title
				if(is_array($val->list)){
					foreach($val->list as $key => $value){
						if($value->module_category_srl > 0){
							$categorySrl = $mid_list[$module]->list[$key]->module_category_srl;
							if(isset($categoryNameList[$categorySrl])){
								$mid_list[$module]->list[$key]->module_category_srl = $categoryNameList[$categorySrl];
							}
						}
						else{
							$mid_list[$module]->list[$key]->module_category_srl = Context::getLang('none_category');
						}
					}
				}
			}
		}

		$security = new Security($mid_list);
		$security->encodeHTML('....browser_title');

		$this->add('module_list', $mid_list);
	}

	/**
	 * @brief Save the file of user-defined language code
	 **/
	function makeCacheDefinedLangCode($site_srl = 0){
		// Get the language file of the current site
		if(!$site_srl){
			$site_module_info = Context::get('site_module_info');
			$args->site_srl = (int)$site_module_info->site_srl;
		}
		else{
			$args->site_srl = $site_srl;
		}
		$output = executeQueryArray('module.getLang', $args);
		if(!$output->toBool() || !$output->data) return;

		$langMap = array();
		foreach($output->data as $lang){
			$langMap[$lang->lang_code][$lang->name] = $lang->value;
		}

		$lang_supported = Context::get('lang_supported');
		$dbInfo = Context::getDBInfo();
		$defaultLang = $dbInfo->lang_type;

		if(!is_array($langMap[$defaultLang])){
			$langMap[$defaultLang] = array();
		}

		$oCacheHandler = CacheHandler::getInstance('object', null, true);

		foreach($lang_supported as $langCode => $langName){
			if(!is_array($langMap[$langCode])){
				$langMap[$langCode] = array();
			}

			$langMap[$langCode] += $langMap[$defaultLang];
			foreach($lang_supported as $targetLangCode => $targetLangName){
				if($langCode == $targetLangCode || $langCode == $defaultLang){
					continue;
				}

				if(!is_array($langMap[$targetLangCode])){
					$langMap[$targetLangCode] = array();
				}

				$langMap[$langCode] += $langMap[$targetLangCode];
			}

			if($oCacheHandler->isSupport()){
				$object_key = 'user_defined_langs:' . $args->site_srl . ':' . $langCode;
				$cache_key = $oCacheHandler->getGroupKey('site_and_module', $object_key);
				$oCacheHandler->put($cache_key, $langMap[$langCode]);
			}
		}

		return $langMap[Context::getLangType()];
	}
}
