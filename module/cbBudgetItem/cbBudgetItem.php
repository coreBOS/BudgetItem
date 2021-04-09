<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once 'data/CRMEntity.php';
require_once 'data/Tracker.php';

class cbBudgetItem extends CRMEntity {
	public $table_name = 'vtiger_cbbudgetitem';
	public $table_index= 'cbbudgetitemid';
	public $column_fields = array();

	/** Indicator if this is a custom module or standard module */
	public $IsCustomModule = true;
	public $HasDirectImageField = false;
	public $moduleIcon = array('library' => 'standard', 'containerClass' => 'slds-icon_container slds-icon-standard-account', 'class' => 'slds-icon', 'icon'=>'partner_marketing_budget');

	/**
	 * Mandatory table for supporting custom fields.
	 */
	public $customFieldTable = array('vtiger_cbbudgetitemcf', 'cbbudgetitemid');
	// related_tables variable should define the association (relation) between dependent tables
	// FORMAT: related_tablename => array(related_tablename_column[, base_tablename, base_tablename_column[, related_module]] )
	// Here base_tablename_column should establish relation with related_tablename_column
	// NOTE: If base_tablename and base_tablename_column are not specified, it will default to modules (table_name, related_tablename_column)
	// Uncomment the line below to support custom field columns on related lists
	// public $related_tables = array('vtiger_MODULE_NAME_LOWERCASEcf' => array('MODULE_NAME_LOWERCASEid', 'vtiger_MODULE_NAME_LOWERCASE', 'MODULE_NAME_LOWERCASEid', 'MODULE_NAME_LOWERCASE'));

	/**
	 * Mandatory for Saving, Include tables related to this module.
	 */
	public $tab_name = array('vtiger_crmentity', 'vtiger_cbbudgetitem', 'vtiger_cbbudgetitemcf');

	/**
	 * Mandatory for Saving, Include tablename and tablekey columnname here.
	 */
	public $tab_name_index = array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_cbbudgetitem'   => 'cbbudgetitemid',
		'vtiger_cbbudgetitemcf' => 'cbbudgetitemid',
	);

	/**
	 * Mandatory for Listing (Related listview)
	 */
	public $list_fields = array(
		/* Format: Field Label => array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'Name'=> array('cbbudgetitem' => 'itemname'),
		'Item Type'=> array('cbbudgetitem' => 'budgetitemtype'),
		'Item Status'=> array('cbbudgetitem' => 'budgetitemstatus'),
		'Estimate Income'=> array('cbbudgetitem' => 'eincome'),
		'Real Income'=> array('cbbudgetitem' => 'rincome'),
		'Estimate Expense'=> array('cbbudgetitem' => 'eexpense'),
		'Real Expense'=> array('cbbudgetitem' => 'rexpense'),
		'Estimate Total'=> array('cbbudgetitem' => 'etotal'),
		'Real Total'=> array('cbbudgetitem' => 'rtotal'),
		'Assigned To' => array('crmentity' => 'smownerid')
	);
	public $list_fields_name = array(
		/* Format: Field Label => fieldname */
		'Name'=> 'itemname',
		'Item Type'=> 'budgetitemtype',
		'Item Status'=> 'budgetitemstatus',
		'Estimate Income'=> 'eincome',
		'Real Income'=> 'rincome',
		'Estimate Expense'=> 'eexpense',
		'Real Expense'=> 'rexpense',
		'Estimate Total'=> 'etotal',
		'Real Total'=> 'rtotal',
		'Assigned To' => 'assigned_user_id'
	);

	// Make the field link to detail view from list view (Fieldname)
	public $list_link_field = 'itemname';

	// For Popup listview and UI type support
	public $search_fields = array(
		/* Format: Field Label => array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'Name'=> array('cbbudgetitem' => 'itemname'),
		'Item Type'=> array('cbbudgetitem' => 'budgetitemtype'),
		'Item Status'=> array('cbbudgetitem' => 'budgetitemstatus'),
		'Estimate Income'=> array('cbbudgetitem' => 'eincome'),
		'Real Income'=> array('cbbudgetitem' => 'rincome'),
		'Estimate Expense'=> array('cbbudgetitem' => 'eexpense'),
		'Real Expense'=> array('cbbudgetitem' => 'rexpense'),
		'Estimate Total'=> array('cbbudgetitem' => 'etotal'),
		'Real Total'=> array('cbbudgetitem' => 'rtotal')
	);
	public $search_fields_name = array(
		/* Format: Field Label => fieldname */
		'Name'=> 'itemname',
		'Item Type'=> 'budgetitemtype',
		'Item Status'=> 'budgetitemstatus',
		'Estimate Income'=> 'eincome',
		'Real Income'=> 'rincome',
		'Estimate Expense'=> 'eexpense',
		'Real Expense'=> 'rexpense',
		'Estimate Total'=> 'etotal',
		'Real Total'=> 'rtotal'
	);

	// For Popup window record selection
	public $popup_fields = array('itemname');

	// Placeholder for sort fields - All the fields will be initialized for Sorting through initSortFields
	public $sortby_fields = array();

	// For Alphabetical search
	public $def_basicsearch_col = 'itemname';

	// Column value to use on detail view record text display
	public $def_detailview_recname = 'itemname';

	// Required Information for enabling Import feature
	public $required_fields = array('itemname'=>1);

	// Callback function list during Importing
	public $special_functions = array('set_import_assigned_user');

	public $default_order_by = 'itemname';
	public $default_sort_order='ASC';
	// Used when enabling/disabling the mandatory fields for the module.
	// Refers to vtiger_field.fieldname values.
	public $mandatory_fields = array('createdtime', 'modifiedtime', 'itemname');

	public function save_module($module) {
		global $adb;
		if ($this->HasDirectImageField) {
			$this->insertIntoAttachment($this->id, $module);
		}
			// Calculate fields
			$budiid = $this->id;
			$this->calculateFields($budiid);
	}

	public function calculateFields($budiid) {
		global $adb;
		$data = $this->column_fields;
			//sum(Transaction Invoice)+sum(Payment Income)=rincome
			$transinvo = $adb->pquery(
				'SELECT sum(pbamount) as sumamount from vtiger_cbtransactionbudgetallocation
			INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_cbtransactionbudgetallocation.cbtransactionbudgetallocationid
			INNER JOIN vtiger_cbbudgetitem ON (vtiger_cbbudgetitem.cbbudgetitemid = vtiger_cbtransactionbudgetallocation.budgetitem)
			INNER JOIN vtiger_invoice ON (vtiger_invoice.invoiceid = vtiger_cbtransactionbudgetallocation.relto) 
			WHERE vtiger_crmentity.deleted=0 AND vtiger_cbbudgetitem.cbbudgetitemid = ?',
				array($this->id)
			);
			$sumatransactioninvoice = $adb->query_result($transinvo, 0, 0);

			$payincome = $adb->pquery(
				'SELECT sum(pbamount) as sumamount from vtiger_cbpaymentbudgetallocation
				INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_cbpaymentbudgetallocation.cbpaymentbudgetallocationid
				INNER JOIN vtiger_cbbudgetitem ON (vtiger_cbbudgetitem.cbbudgetitemid = vtiger_cbpaymentbudgetallocation.budgetitem)
				INNER JOIN vtiger_cobropago ON (vtiger_cobropago.cobropagoid = vtiger_cbpaymentbudgetallocation.cyp) 
				WHERE vtiger_crmentity.deleted=0 AND vtiger_cobropago.credit=1 AND vtiger_cbbudgetitem.cbbudgetitemid = ?',
				array($this->id)
			);
			$paycredit = $adb->query_result($payincome, 0, 0);
			$rincome=$sumatransactioninvoice+$paycredit;
			$adb->pquery('update vtiger_cbbudgetitem set rincome=? where cbbudgetitemid=?', array($rincome,$budiid));

			//sum(Transaction Purchase Order)+sum(Payment Expense)=rexpense
			$transpo = $adb->pquery(
				'SELECT sum(pbamount) as sumamount from vtiger_cbtransactionbudgetallocation
				INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_cbtransactionbudgetallocation.cbtransactionbudgetallocationid
				INNER JOIN vtiger_cbbudgetitem ON (vtiger_cbbudgetitem.cbbudgetitemid = vtiger_cbtransactionbudgetallocation.budgetitem)
				INNER JOIN vtiger_purchaseorder ON (vtiger_purchaseorder.purchaseorderid = vtiger_cbtransactionbudgetallocation.relto) 
				WHERE vtiger_crmentity.deleted=0 AND vtiger_cbbudgetitem.cbbudgetitemid = ?',
				array($this->id)
			);
			$sumatransactionpo = $adb->query_result($transpo, 0, 0);

			$payegoingout = $adb->pquery(
				'SELECT sum(pbamount) as sumamount from vtiger_cbpaymentbudgetallocation
				INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_cbpaymentbudgetallocation.cbpaymentbudgetallocationid
				INNER JOIN vtiger_cbbudgetitem ON (vtiger_cbbudgetitem.cbbudgetitemid = vtiger_cbpaymentbudgetallocation.budgetitem)
				INNER JOIN vtiger_cobropago ON (vtiger_cobropago.cobropagoid = vtiger_cbpaymentbudgetallocation.cyp) 
				WHERE vtiger_crmentity.deleted=0 AND vtiger_cobropago.credit=0 AND vtiger_cbbudgetitem.cbbudgetitemid = ?',
				array($this->id)
			);
			$payexpense = $adb->query_result($payegoingout, 0, 0);

			$rexpense=$sumatransactionpo+$payexpense;
			$adb->pquery('update vtiger_cbbudgetitem set rexpense=? where cbbudgetitemid=?', array($rexpense,$budiid));

			//	eincome-eexpense(etotal)
		if (isset($data['eincome']) && isset($data['eexpense'])) {
			$eincome = CurrencyField::convertToDBFormat($data['eincome']);
			$eexpense = CurrencyField::convertToDBFormat($data['eexpense']);
			$etotal = $eincome-$eexpense;
		}
			$adb->pquery('update vtiger_cbbudgetitem set etotal=? where cbbudgetitemid=?', array($etotal, $budiid));

			//rincome-rexpense(rtotal)
			$rtotal=$rincome-$rexpense;
			$adb->pquery('update vtiger_cbbudgetitem set rtotal=? where cbbudgetitemid=?', array($rtotal,$budiid));

			//rtotal-etotal(deviationamount)
			$deviationamount=$rtotal-$etotal;
			$adb->pquery('update vtiger_cbbudgetitem set deviationamount=? where cbbudgetitemid=?', array($deviationamount, $budiid));

			//etotal*100/rtotal=deviationpercentage
			$deviationpercentage=($etotal *100/$rtotal);
			$adb->pquery('update vtiger_cbbudgetitem set deviationpercentage=? where cbbudgetitemid=?', array($deviationpercentage,$budiid));
	}
	/**
	 * Invoked when special actions are performed on the module.
	 * @param String Module name
	 * @param String Event Type (module.postinstall, module.disabled, module.enabled, module.preuninstall)
	 */
	public function vtlib_handler($modulename, $event_type) {
		if ($event_type == 'module.postinstall') {
			// Handle post installation actions
			$this->setModuleSeqNumber('configure', $modulename, 'BI-', '0000001');
		} elseif ($event_type == 'module.disabled') {
			// Handle actions when this module is disabled.
		} elseif ($event_type == 'module.enabled') {
			// Handle actions when this module is enabled.
		} elseif ($event_type == 'module.preuninstall') {
			// Handle actions when this module is about to be deleted.
		} elseif ($event_type == 'module.preupdate') {
			// Handle actions before this module is updated.
		} elseif ($event_type == 'module.postupdate') {
			// Handle actions after this module is updated.
		}
	}

	/**
	 * Handle saving related module information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	// public function save_related_module($module, $crmid, $with_module, $with_crmid) { }

	/**
	 * Handle deleting related module information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function delete_related_module($module, $crmid, $with_module, $with_crmid) { }

	/**
	 * Handle getting related list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function get_related_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }

	/**
	 * Handle getting dependents list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function get_dependents_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }
}
?>
