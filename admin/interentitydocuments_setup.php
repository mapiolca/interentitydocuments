<?php
/* Copyright (C) 2013 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file      admin/interentitydocuments_setup.php
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
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */

if ($action == 'setconststatus') {
	dolibarr_set_const($db, 'OFSOM_STATUS', GETPOST('OFSOM_STATUS'), 'chaine', 1, '', $conf->entity);
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'save') {
	if (!empty($_REQUEST['TLine'])) {
		foreach ($_REQUEST['TLine'] as $id => $TValues) {
			$TValues['fk_entity'] = GETPOST('TLine_' . $TValues['rowid'] . '_fk_entity', 'int');
			$TValues['fk_soc']    = GETPOST('TLine_' . $TValues['rowid'] . '_fk_soc', 'int');

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

			if (isset($TValues['delete'])) {
				$o->delete();
			} else {
				$o->save();
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
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">' . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head   = interentitydocumentsAdminPrepareHead();
$notab  = -1;
print dol_get_fiche_head($head, 'settings', $langs->trans("Module104200Name"), $notab, "interentitydocuments@interentitydocuments");
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
		<td><?php echo $langs->trans('Delete'); ?> ?</td>
	</tr>
<?php

foreach ($TLink as $link) {
	?>
	<tr>
		<td><?php print $html->select_company($link->fk_soc, 'TLine_' . $link->rowid . '_fk_soc', '', 1); ?></td>
		<td><?php print $m->select_entities($link->fk_entity, 'TLine_' . $link->rowid . '_fk_entity'); ?></td>
		<td>
			<input type="hidden" name="TLine[<?php echo $link->rowid; ?>][rowid]" value="<?php echo $link->rowid; ?>"/>
			<input type="checkbox" value="1" name="TLine[<?php echo $link->rowid; ?>][delete]"/>
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
$TTriggers = array("ORDER_SUPPLIER_VALIDATE" => "Valider", "ORDER_SUPPLIER_SUBMIT" => "Commander");

print '<table class="liste">';

// Section header
print '<tr class="liste_titre">';
print '<td class="titlefield">' . $langs->trans('ParamOFSOMStatus') . '</td>';
print '<td width="20">&nbsp;</td>';
print '<td>' . $langs->trans('Value') . '</td>';
print '</tr>';

print '<form method="post" action="' . $_SERVER['PHP_SELF'] . '" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="setconststatus">';
print '<tr class="oddeven"><td>' . $langs->trans("OFSOMStatusConf") . '</td>';
print '<td width="20">&nbsp;</td>';
print '<td align="left">';
print $form->selectarray('OFSOM_STATUS', $TTriggers, $conf->global->OFSOM_STATUS, 0, '');
print ' <input type="submit" class="button" value="' . $langs->trans("Save") . '">';
print '</td></tr>';
print '</form>';

// Toggle switches using Dolibarr native ajax_constantonoff
$TOptions = array(
	'OFSOM_LINK_STATUSSUPPLIERORDER_ORDERCHILD' => $langs->trans('OFSOMLinkStatusSupplierOrderOrderChild'),
	'OFSOM_UPDATE_LINE_SOURCE'                  => $langs->trans('OFSOMUpdateLineSource'),
	'OFSOM_UPDATE_ORDER_SOURCE'                 => $langs->trans('OFSOMUpdateOrderSource'),
	'OFSOM_SET_SUPPLIER_ORDER_RECEIVED_ON_SUPPLIER_SHIPMENT_CLOSED' => $langs->trans('OFSOM_SET_SUPPLIER_ORDER_RECEIVED_ON_SUPPLIER_SHIPMENT_CLOSED'),
	'OFSOM_AUTO_CREATE_SUPPLIER_INVOICE'        => $langs->trans('OFSOM_AUTO_CREATE_SUPPLIER_INVOICE'),
);

foreach ($TOptions as $confkey => $label) {
	print '<tr class="oddeven">';
	print '<td>' . $label . '</td>';
	print '<td width="20">&nbsp;</td>';
	print '<td>' . ajax_constantonoff($confkey) . '</td>';
	print '</tr>';
}

print '</table>';

if (empty($conf->global->OFSOM_STATUS)) {
	dolibarr_set_const($db, 'OFSOM_STATUS', 'ORDER_SUPPLIER_VALIDATE', 'chaine', 0, '', $conf->entity);
}

llxFooter();

$db->close();
