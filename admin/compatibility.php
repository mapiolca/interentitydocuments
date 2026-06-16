<?php
/* Compatibility setup page for the Documents inter-entités module.
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file      admin/compatibility.php
 * \ingroup   interentitydocuments
 * \brief     Module compatibility page
 */

// Dolibarr environment
if (is_file('../../main.inc.php')) require('../../main.inc.php');
elseif (is_file('../../../main.inc.php')) require('../../../main.inc.php');
else die('Include of main fails');

// Libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once '../lib/interentitydocuments.lib.php';
dol_include_once('/interentitydocuments/class/interentitydocumentscompatibility.class.php');

// Translations
$langs->load("interentitydocuments@interentitydocuments");

// Access control
if (!$user->admin) {
	accessforbidden();
}

/*
 * View
 */

$page_name = "interentitydocumentsCompatibility";
llxHeader('', $langs->trans($page_name));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('interentitydocuments').'">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

$head = interentitydocumentsAdminPrepareHead();
print dol_get_fiche_head($head, 'compatibility', $langs->trans("Module450020Name"), -1, "interentitydocuments@interentitydocuments");
print dol_get_fiche_end();

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('Environment').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('DetectedPhpVersion').'</td><td>'.dol_escape_htmltag(PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DetectedDolibarrVersion').'</td><td>'.dol_escape_htmltag(InterentitydocumentsCompatibility::getDetectedDolibarrVersion()).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MinimumPhpVersion').'</td><td>'.dol_escape_htmltag(InterentitydocumentsCompatibility::MIN_PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MinimumDolibarrVersion').'</td><td>'.dol_escape_htmltag(InterentitydocumentsCompatibility::MIN_DOLIBARR_VERSION).'</td></tr>';
print '</table>';

print '<br>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Code').'</td>';
print '<td>'.$langs->trans('Feature').'</td>';
print '<td>'.$langs->trans('Description').'</td>';
print '<td>'.$langs->trans('MinimumDolibarrVersion').'</td>';
print '<td>'.$langs->trans('MinimumPhpVersion').'</td>';
print '<td>'.$langs->trans('Status').'</td>';
print '<td>'.$langs->trans('Reason').'</td>';
print '</tr>';

$features = InterentitydocumentsCompatibility::getFeatures();
if (empty($features)) {
	print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}

foreach ($features as $feature) {
	$status = !empty($feature['available']) ? '<span class="badge badge-status4">'.$langs->trans('Available').'</span>' : '<span class="badge badge-status8">'.$langs->trans('Unavailable').'</span>';
	$reason = !empty($feature['reason']) ? $langs->trans($feature['reason']) : $langs->trans('None');

	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($feature['code']).'</td>';
	print '<td>'.$langs->trans($feature['label']).'</td>';
	print '<td>'.$langs->trans($feature['description']).'</td>';
	print '<td>'.dol_escape_htmltag($feature['min_dolibarr']).'</td>';
	print '<td>'.dol_escape_htmltag($feature['min_php']).'</td>';
	print '<td>'.$status.'</td>';
	print '<td>'.$reason.'</td>';
	print '</tr>';
}
print '</table>';

llxFooter();

$db->close();
