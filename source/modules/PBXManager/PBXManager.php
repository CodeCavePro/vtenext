<?php
/*+********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ********************************************************************************/

require_once('data/CRMEntity.php');
require_once('data/Tracker.php');

class PBXManager extends CRMEntity {
	var $db, $log; // Used in class functions like CRMEntity

	var $table_name;
	var $table_index= 'pbxmanagerid';
	var $column_fields = Array();

	// Mandatory for function getGroupName
	// Array(groupTableName, groupColumnId)
	// groupTableName should have (groupname column)
	//var $groupTable = Array('vtiger_pbxmanagergrouprel','pbxmanagerid');

	// Mandatory table for supporting custom fields
	var $customFieldTable = Array();

	// Mandatory for Saving, Include tables related to this module.
	var $tab_name = Array();
	// Mandatory for Saving, Include the table name and index column mapping here.
	var $tab_name_index = Array();

	// Mandatory for Listing
	var $list_fields = Array (
		// Field Label=> Array(tablename, columnname)
		'Call To'=> Array('pbxmanager', 'callto'),
		'Call From'=>Array('pbxmanager', 'callfrom'),
	);
	var $list_fields_name = Array(
		// Field Label=>columnname
		'Call To'=> 'callto',
		'Call From' => 'callfrom'
	);
	var $sortby_fields = Array('callto', 'callfrom', 'callid', 'timeofcall', 'status');
	// Should contain field labels
	var $detailview_links = Array();

	// For alphabetical search
	var $def_basicsearch_col = 'callid';

	// Column value to use on detail view record text display.
	var $def_detailview_recname = '';

	// Required information for enabling Import feature
	var $required_fields = Array();

	// Callback function list during Importing
	var $special_functions =  array();

	var $default_order_by = 'timeofcall';
	var $default_sort_order='DESC';

	function PBXManager() {
		global $log;
		global $table_prefix;
		parent::__construct(); // crmv@37004
		$this->table_name = $table_prefix.'_pbxmanager';
	    $this->tab_name = Array($table_prefix.'_crmentity', $table_prefix.'_pbxmanager');
		$this->tab_name_index = Array(
			$table_prefix.'_crmentity' => 'crmid',
			$table_prefix.'_pbxmanager' => 'pbxmanagerid',
	    );
		
		$this->column_fields = getColumnFields('PBXManager');
		$this->db = PearDatabase::getInstance();
		$this->log = $log;
	}

	function save_module($module) {
	}

	/**
	 * Get list view query.
	 */
	function getListQuery($module) {
		global $table_prefix;
		$query = "SELECT $this->table_name.*, ".$table_prefix."_crmentity.*";
		$query .= " FROM $this->table_name";

		$query .= "	INNER JOIN ".$table_prefix."_crmentity
						ON ".$table_prefix."_crmentity.crmid = $this->table_name.$this->table_index
					 LEFT JOIN ".$table_prefix."_groups
						ON ".$table_prefix."_groups.groupid = ".$table_prefix."_crmentity.smownerid";

		// Consider custom table join as well.
		if(!empty($this->customFieldTable)) {
			$query .= " INNER JOIN ".$this->customFieldTable[0]." ON ".$this->customFieldTable[0].'.'.$this->customFieldTable[1] .
				      " = $this->table_name.$this->table_index"; 
		}
		$query .= " LEFT JOIN ".$table_prefix."_users ON ".$table_prefix."_users.id = ".$table_prefix."_crmentity.smownerid ";
		global $current_user;
		//crmv@31775
	    $reportFilterJoin = '';
		$viewId = getLVS($module,'viewname');
		if (isset($_REQUEST['viewname']) && $_REQUEST['viewname'] != '') {
			$viewId = $_REQUEST['viewname'];
		}
		if ($viewId != '') {
		    $oCustomView = new CustomView($module);
			$reportFilter = $oCustomView->getReportFilter($viewId);
			if ($reportFilter) {
				$tableNameTmp = $oCustomView->getReportFilterTableName($reportFilter,$current_user->id);
				$query .= " INNER JOIN $tableNameTmp ON $tableNameTmp.id = {$table_prefix}_crmentity.crmid";
			}
		}
		//crmv@31775e
		$query .= $this->getNonAdminAccessControlQuery($module,$current_user);
		$query .= "WHERE ".$table_prefix."_crmentity.deleted = 0 ".$where;
		$query = $this->listQueryNonAdminChange($query, $module);
		return $query;
	}

	/**
	 * Create query to export the records.
	 */
	function create_export_query($where,$oCustomView,$viewId)	//crmv@31775
	{
		global $current_user;
		global $table_prefix;
		$thismodule = $_REQUEST['module'];
		
		include_once("include/utils/ExportUtils.php");

		//To get the Permitted fields query and the permitted fields list
		$sql = getPermittedFieldsQuery($thismodule, "detail_view");
		
		$fields_list = getFieldsListFromQuery($sql);

		$query = "SELECT $fields_list, ".$table_prefix."_users.user_name AS user_name 
					FROM ".$table_prefix."_crmentity INNER JOIN $this->table_name ON ".$table_prefix."_crmentity.crmid=$this->table_name.$this->table_index";

		if(!empty($this->customFieldTable)) {
			$query .= " INNER JOIN ".$this->customFieldTable[0]." ON ".$this->customFieldTable[0].'.'.$this->customFieldTable[1] .
				      " = $this->table_name.$this->table_index"; 
		}

		$query .= " LEFT JOIN ".$table_prefix."_groups ON ".$table_prefix."_groups.groupid = ".$table_prefix."_crmentity.smownerid";
		$query .= " LEFT JOIN ".$table_prefix."_users ON ".$table_prefix."_crmentity.smownerid = ".$table_prefix."_users.id and ".$table_prefix."_users.status='Active'";
		$query .= $this->getNonAdminAccessControlQuery($thismodule,$current_user);
		
		//crmv@31775
		$reportFilter = $oCustomView->getReportFilter($viewId);
		if ($reportFilter) {
			$tableNameTmp = $oCustomView->getReportFilterTableName($reportFilter,$current_user->id);
			$query .= " INNER JOIN $tableNameTmp ON $tableNameTmp.id = {$table_prefix}_crmentity.crmid";
		}
		//crmv@31775e
		
		$where_auto = " ".$table_prefix."_crmentity.deleted=0";

		if($where != '') $query .= " WHERE ($where) AND $where_auto";
		else $query .= " WHERE $where_auto";

		$query = $this->listQueryNonAdminChange($query, $thismodule);
		return $query;
	}

	/**
	 * Initialize this instance for importing.
	 */
	function initImport($module) {
		$this->db = PearDatabase::getInstance();
		$this->initImportableFields($module);
	}

	/**
	 * Create list query to be shown at the last step of the import.
	 * Called From: modules/Import/UserLastImport.php
	 */
	function create_import_query($module) {
		global $current_user;
		global $table_prefix;
		$query = "SELECT ".$table_prefix."_crmentity.crmid, ".$table_prefix."_users.user_name, $this->table_name.* FROM $this->table_name
			INNER JOIN ".$table_prefix."_crmentity ON ".$table_prefix."_crmentity.crmid = $this->table_name.$this->table_index
			LEFT JOIN ".$table_prefix."_users_last_import ON ".$table_prefix."_users_last_import.bean_id=".$table_prefix."_crmentity.crmid
			LEFT JOIN ".$table_prefix."_users ON ".$table_prefix."_users.id = ".$table_prefix."_crmentity.smownerid
			WHERE ".$table_prefix."_users_last_import.assigned_user_id='$current_user->id'
			AND ".$table_prefix."_users_last_import.bean_type='$module'
			AND ".$table_prefix."_users_last_import.deleted=0
			AND ".$table_prefix."_users.status = 'Active'";
		return $query;
	}

	/**
	 * Delete the last imported records.
	 */
	function undo_import($module, $user_id) {
		global $adb;
		global $table_prefix;
		$count = 0;
		$query1 = "select bean_id from ".$table_prefix."_users_last_import where assigned_user_id=? AND bean_type='$module' AND deleted=0";
		$result1 = $adb->pquery($query1, array($user_id)) or die("Error getting last import for undo: ".mysql_error()); 
		while ( $row1 = $adb->fetchByAssoc($result1))
		{
			$query2 = "update ".$table_prefix."_crmentity set deleted=1 where crmid=?";
			$result2 = $adb->pquery($query2, array($row1['bean_id'])) or die("Error undoing last import: ".mysql_error()); 
			$count++;			
		}
		return $count;
	}

	/**
	 * Function which will set the assigned user id for import record.
	 */
	function set_import_assigned_user()
	{
		global $current_user, $adb;
		global $table_prefix;
		$record_user = $this->column_fields["assigned_user_id"];
		
		if($record_user != $current_user->id){
			$sqlresult = $adb->pquery("select id from ".$table_prefix."_users where id = ?", array($ass_user));
			if($this->db->num_rows($sqlresult)!= 1) {
				$this->column_fields["assigned_user_id"] = $current_user->id;
			} else {			
				$row = $adb->fetchByAssoc($sqlresult, -1, false);
				if (!empty($row['id']) && $row['id'] != -1) {
					$this->column_fields["assigned_user_id"] = $row['id'];
				} else {
					$this->column_fields["assigned_user_id"] = $current_user->id;
				}
			}
		}
	}

	/**
	 * Handle getting related list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//function get_related_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }

	/** 
	 * Handle saving related module information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	// function save_related_module($module, $crmid, $with_module, $with_crmid) { }

 	/**
	* Invoked when special actions are performed on the module.
	* @param String Module name
	* @param String Event Type
	*/	
	function vtlib_handler($moduleName, $eventType) {
		require_once('include/utils/utils.php');			
		global $adb;
		global $table_prefix;
 		$tabid = getTabid("Users");
 		if($eventType == 'module.postinstall') {		
			// Add a block and 2 fields for Users module
			// crmv@104975
			$usersInstance = Vtiger_Module::getInstance('Users');
			$panelInstance = Vtecrm_Panel::getFirstForModule($usersInstance);
			$panelid = $panelInstance ? $panelInstance->id : 0;
			$blockid = $adb->getUniqueID($table_prefix.'_blocks');
			$adb->query("insert into ".$table_prefix."_blocks(blockid,tabid,panelid,blocklabel,sequence,show_title,visible,create_view,edit_view,detail_view,display_status)" .
					" values ($blockid,$tabid,$panelid,'Asterisk Configuration',7,0,0,0,0,0,1)");	//crmv@20047
			// crmv@104975e
			$adb->query("insert into ".$table_prefix."_field(tabid,fieldid,columnname,tablename,generatedtype,uitype,fieldname,fieldlabel,readonly," .
					" presence,selected,maximumlength,sequence,block,displaytype,typeofdata,quickcreate,quickcreatesequence,info_type) " .
					" values ($tabid,".$adb->getUniqueID($table_prefix.'_field').",'asterisk_extension','".$table_prefix."_asteriskextensions',1,1,'asterisk_extension'," .
					" 'Asterisk Extension',1,0,0,30,1,$blockid,1,'V~O',1,NULL,'BAS')");
			
			$adb->query("insert into ".$table_prefix."_field(tabid,fieldid,columnname,tablename,generatedtype,uitype,fieldname,fieldlabel,readonly," .
					" presence,selected,maximumlength,sequence,block,displaytype,typeofdata,quickcreate,quickcreatesequence,info_type) " .
					" values ($tabid,".$adb->getUniqueID($table_prefix.'_field').",'use_asterisk','".$table_prefix."_asteriskextensions',1,56,'use_asterisk'," .
					"' Receive Incoming Calls',1,0,0,30,2,$blockid,1,'C~O',1,NULL,'BAS')");
			
			// Mark the module as Standard module
			$adb->pquery('UPDATE '.$table_prefix.'_tab SET customized=0 WHERE name=?', array($moduleName));

			// crmv@38798
			$moduleInstance = Vtiger_Module::getInstance($moduleName);
			$moduleInstance->hide(array('hide_report'=>1));
			// crmv@38798e
			
		} else if($eventType == 'module.disabled') {
		// TODO Handle actions when this module is disabled.
		} else if($eventType == 'module.enabled') {
		// TODO Handle actions when this module is enabled.
		} else if($eventType == 'module.preuninstall') {
		// TODO Handle actions when this module is about to be deleted.
		} else if($eventType == 'module.preupdate') {
		// TODO Handle actions before this module is updated.
		} else if($eventType == 'module.postupdate') {
			$tmp_dir = 'packages/vte/mandatory/tmp1';
			mkdir($tmp_dir);
			$unzip = new Vtiger_Unzip("packages/vte/mandatory/$moduleName.zip");
			$unzip->unzipAllEx($tmp_dir);
			if($unzip) $unzip->close();
			copy("$tmp_dir/cron/AsteriskClient.php","cron/modules/$moduleName/AsteriskClient.php");
			if ($handle = opendir($tmp_dir)) {
				folderDetete($tmp_dir);
			}
		}
 	}
}
?>