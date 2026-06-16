<?php
/* Copyright (C) 2013 ATM Consulting <support@atm-consulting.fr>
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file      admin/setup.php
 * \ingroup   interentitydocuments
 * \brief     Module setup page
 */

// Dolibarr environment
if (is_file('../../main.inc.php')) require('../../main.inc.php');
elseif (is_file('../../../main.inc.php')) require('../../../main.inc.php');
else die('Include of main fails');

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/interentitydocuments.lib.php';
dol_include_once('/interentitydocuments/class/telink.class.php');

// Translations
$langs->load("interentitydocuments@interentitydocuments");

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$id = GETPOST('id', 'int');

/*
 * Actions
 */

if ($action == 'setconststatus') {
	dolibarr_set_const($db, 'IED_STATUS', GETPOST('IED_STATUS', 'aZ09'), 'chaine', 1, '', $conf->entity);
}

if ($action == 'setcustomerpaymentbankaccount') {
	dolibarr_set_const($db, 'IED_CUSTOMER_PAYMENT_BANK_ACCOUNT_ID', GETPOST('IED_CUSTOMER_PAYMENT_BANK_ACCOUNT_ID', 'int'), 'chaine', 0, '', $conf->entity);
}

if ($action == 'setsupplierpaymentbankaccount') {
	dolibarr_set_const($db, 'IED_SUPPLIER_PAYMENT_BANK_ACCOUNT_ID', GETPOST('IED_SUPPLIER_PAYMENT_BANK_ACCOUNT_ID', 'int'), 'chaine', 0, '', $conf->entity);
}

if ($action == 'delete_mapping') {
	$o = new TTELink();
	if ($id > 0 && $o->load($id) > 0 && (int) $o->entity === (int) $conf->entity) {
		$result = $o->delete();
		if ($result < 0) {
			setEventMessages($o->error, $o->errors, 'errors');
		} else {
			setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		}
	} else {
		setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
	}

	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action == 'save') {
	$TLine = GETPOST('TLine', 'array');
	if (!empty($TLine)) {
		foreach ($TLine as $id => $TValues) {
			if (!is_array($TValues)) {
				continue;
			}
			$id = (int) $id;
			$rowid = isset($TValues['rowid']) ? (int) $TValues['rowid'] : 0;
			$TValues['rowid'] = $rowid;
			$TValues['fk_entity'] = GETPOST('TLine_' . $rowid . '_fk_entity', 'int');
			$TValues['fk_soc']    = GETPOST('TLine_' . $rowid . '_fk_soc', 'int');

			$o = new TTELink();
			if ($id > 0) {
				$o->load($id);
			} else {
				if (!($TValues['fk_soc'] > 0 && $TValues['fk_entity'] > 0)) {
					continue;
				}
			}

			$o->set_values($TValues);
			$o->entity = $conf->entity;

			$result = $o->save();
			if ($result < 0) {
				setEventMessages($o->error, $o->errors, 'errors');
			}
		}
	}
}

/*
 * View
 */

$page_name = "interentitydocumentsSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?search_keyword=' . urlencode('interentitydocuments') . '">' . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head   = interentitydocumentsAdminPrepareHead();
$notab  = -1;
print dol_get_fiche_head($head, 'settings', $langs->trans("Module450020Name"), $notab, "interentitydocuments@interentitydocuments");
print dol_get_fiche_end($notab);

echo '<h3>' . $langs->trans("interentitydocumentsSetupPage") . '</h3>';
print '<div class="warning">' . $langs->trans('ThisEntityMappingNeedToBeDoneOnEachEntityListed') . '</div>';

$TLink = TTELink::getList();

$html = new Form($db);
$m    = new ActionsMulticompany($db);

echo '<form name="form1" method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
echo '<input type="hidden" name="token" value="' . newToken() . '">';
echo '<input type="hidden" name="action" value="save">';

?>
<table class="liste">
	<tr class="liste_titre">
		<td><?php echo $langs->trans('Company'); ?></td>
		<td><?php echo $langs->trans('Entity'); ?></td>
		<td class="center"><?php echo $langs->trans('Actions'); ?></td>
	</tr>
<?php

if (empty($TLink)) {
	?>
	<tr class="oddeven">
		<td colspan="3"><span class="opacitymedium"><?php echo $langs->trans('NoRecordFound'); ?></span></td>
	</tr>
	<?php
}

foreach ($TLink as $link) {
	$deleteUrl = $_SERVER['PHP_SELF'].'?action=delete_mapping&id='.(int) $link->rowid.'&token='.newToken();
	?>
	<tr>
		<td><?php print $html->select_company($link->fk_soc, 'TLine_' . $link->rowid . '_fk_soc', '', 1); ?></td>
		<td><?php print $m->select_entities($link->fk_entity, 'TLine_' . $link->rowid . '_fk_entity'); ?></td>
		<td class="center">
			<input type="hidden" name="TLine[<?php echo $link->rowid; ?>][rowid]" value="<?php echo $link->rowid; ?>"/>
			<a href="<?php echo $deleteUrl; ?>"><?php print img_delete($langs->trans('Delete')); ?></a>
		</td>
	</tr>
	<?php
}
?>
	<tr class="liste_titre">
		<td><?php print $html->select_company(-1, 'TLine_0_fk_soc', '', 1); ?></td>
		<td><?php print $m->select_entities(-1, 'TLine_0_fk_entity'); ?></td>
		<td><input type="hidden" name="TLine[0][rowid]" value="0"/></td>
	</tr>
</table>

<div class="tabsAction">
	<input type="submit" class="button" value="<?php echo $langs->trans('Save'); ?>">
</div>

</form>

<?php

$form = new Form($db);
$TTriggers = array(
	"ORDER_SUPPLIER_VALIDATE" => $langs->trans("SupplierOrderValidate"),
	"ORDER_SUPPLIER_SUBMIT" => $langs->trans("SupplierOrderSubmit"),
);
$selectedStatus = function_exists('getDolGlobalString') ? getDolGlobalString('IED_STATUS') : (!empty($conf->global->IED_STATUS) ? $conf->global->IED_STATUS : 'ORDER_SUPPLIER_VALIDATE');

print '<table class="liste">';

// Section header
print '<tr class="liste_titre">';
print '<td class="titlefield">' . $langs->trans('ParamIEDStatus') . '</td>';
print '<td width="20">&nbsp;</td>';
print '<td>' . $langs->trans('Value') . '</td>';
print '</tr>';

print '<form method="post" action="' . $_SERVER['PHP_SELF'] . '" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="setconststatus">';
print '<tr class="oddeven"><td>' . $langs->trans("IEDStatusConf") . '</td>';
print '<td width="20">&nbsp;</td>';
print '<td align="left">';
print $form->selectarray('IED_STATUS', $TTriggers, $selectedStatus, 0, '');
print ' <input type="submit" class="button" value="' . $langs->trans("Save") . '">';
print '</td></tr>';
print '</form>';

// Toggle switches using Dolibarr native ajax_constantonoff
$TOptions = array(
	'IED_LINK_STATUSSUPPLIERORDER_ORDERCHILD' => $langs->trans('IEDLinkStatusSupplierOrderOrderChild'),
	'IED_UPDATE_LINE_SOURCE'                  => $langs->trans('IEDUpdateLineSource'),
	'IED_UPDATE_ORDER_SOURCE'                 => $langs->trans('IEDUpdateOrderSource'),
	'IED_SET_SUPPLIER_ORDER_RECEIVED_ON_SUPPLIER_SHIPMENT_CLOSED' => $langs->trans('IED_SET_SUPPLIER_ORDER_RECEIVED_ON_SUPPLIER_SHIPMENT_CLOSED'),
	'IED_AUTO_CREATE_SUPPLIER_INVOICE'        => $langs->trans('IED_AUTO_CREATE_SUPPLIER_INVOICE'),
	'IED_AUTO_CREATE_SUPPLIER_ORDER_FROM_CUSTOMER_ORDER' => $langs->trans('IED_AUTO_CREATE_SUPPLIER_ORDER_FROM_CUSTOMER_ORDER'),
	'IED_AUTO_CREATE_CUSTOMER_PAYMENT_FROM_SUPPLIER_PAYMENT' => $langs->trans('IED_AUTO_CREATE_CUSTOMER_PAYMENT_FROM_SUPPLIER_PAYMENT'),
	'IED_AUTO_CREATE_SUPPLIER_PAYMENT_FROM_CUSTOMER_PAYMENT' => $langs->trans('IED_AUTO_CREATE_SUPPLIER_PAYMENT_FROM_CUSTOMER_PAYMENT'),
);

foreach ($TOptions as $confkey => $label) {
	print '<tr class="oddeven">';
	print '<td>' . $label . '</td>';
	print '<td width="20">&nbsp;</td>';
	print '<td>' . ajax_constantonoff($confkey) . '</td>';
	print '</tr>';
}

$selectedCustomerPaymentBankAccountId = function_exists('getDolGlobalInt') ? getDolGlobalInt('IED_CUSTOMER_PAYMENT_BANK_ACCOUNT_ID') : (!empty($conf->global->IED_CUSTOMER_PAYMENT_BANK_ACCOUNT_ID) ? (int) $conf->global->IED_CUSTOMER_PAYMENT_BANK_ACCOUNT_ID : 0);
$selectedSupplierPaymentBankAccountId = function_exists('getDolGlobalInt') ? getDolGlobalInt('IED_SUPPLIER_PAYMENT_BANK_ACCOUNT_ID') : (!empty($conf->global->IED_SUPPLIER_PAYMENT_BANK_ACCOUNT_ID) ? (int) $conf->global->IED_SUPPLIER_PAYMENT_BANK_ACCOUNT_ID : 0);
$bankAccountOptions = array(0 => $langs->trans('None'));
$bankEnabled = function_exists('isModEnabled') ? isModEnabled('bank') : !empty($conf->bank->enabled);
if ($bankEnabled) {
	$entityFilter = function_exists('getEntity') ? getEntity('bank_account') : (string) ((int) $conf->entity);
	$sql = 'SELECT rowid, ref, label';
	$sql .= ' FROM ' . MAIN_DB_PREFIX . 'bank_account';
	$sql .= ' WHERE entity IN (' . $entityFilter . ')';
	$sql .= ' AND clos = 0';
	$sql .= ' ORDER BY label, ref';
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$label = trim((!empty($obj->ref) ? $obj->ref . ' - ' : '') . $obj->label);
			$bankAccountOptions[(int) $obj->rowid] = $label;
		}
	} else {
		dol_syslog('interentitydocuments: unable to load bank accounts for setup: ' . $db->lasterror(), LOG_WARNING);
	}
}

print '<form method="post" action="' . $_SERVER['PHP_SELF'] . '" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="setcustomerpaymentbankaccount">';
print '<tr class="oddeven"><td>' . $langs->trans('IED_CUSTOMER_PAYMENT_BANK_ACCOUNT_ID') . '</td>';
print '<td width="20">&nbsp;</td>';
print '<td align="left">';
print $form->selectarray('IED_CUSTOMER_PAYMENT_BANK_ACCOUNT_ID', $bankAccountOptions, $selectedCustomerPaymentBankAccountId, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300');
if (function_exists('ajax_combobox')) {
	print ajax_combobox('IED_CUSTOMER_PAYMENT_BANK_ACCOUNT_ID');
}
print ' <input type="submit" class="button" value="' . $langs->trans('Save') . '">';
print '</td></tr>';
print '</form>';

print '<form method="post" action="' . $_SERVER['PHP_SELF'] . '" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="setsupplierpaymentbankaccount">';
print '<tr class="oddeven"><td>' . $langs->trans('IED_SUPPLIER_PAYMENT_BANK_ACCOUNT_ID') . '</td>';
print '<td width="20">&nbsp;</td>';
print '<td align="left">';
print $form->selectarray('IED_SUPPLIER_PAYMENT_BANK_ACCOUNT_ID', $bankAccountOptions, $selectedSupplierPaymentBankAccountId, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300');
if (function_exists('ajax_combobox')) {
	print ajax_combobox('IED_SUPPLIER_PAYMENT_BANK_ACCOUNT_ID');
}
print ' <input type="submit" class="button" value="' . $langs->trans('Save') . '">';
print '</td></tr>';
print '</form>';

print '</table>';

if (empty($conf->global->IED_STATUS)) {
	dolibarr_set_const($db, 'IED_STATUS', 'ORDER_SUPPLIER_VALIDATE', 'chaine', 0, '', $conf->entity);
}

llxFooter();

$db->close();
