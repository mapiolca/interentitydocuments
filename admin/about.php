<?php
/* About page for the Documents inter-entités module.
 * Copyright (C) 2013 ATM Consulting <support@atm-consulting.fr>
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		admin/about.php
 * 	\ingroup	interentitydocuments
 * 	\brief		Module about page
 */
// Dolibarr environment
if (is_file('../../main.inc.php')) require('../../main.inc.php');
elseif (is_file('../../../main.inc.php')) require('../../../main.inc.php');
else die('Include of main fails');

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/interentitydocuments.lib.php';
dol_include_once('/interentitydocuments/core/modules/modinterentitydocuments.class.php');

//require_once "../class/myclass.class.php";
// Translations
$langs->load("interentitydocuments@interentitydocuments");

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */

/*
 * View
 */
$page_name = "interentitydocumentsAbout";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?search_keyword=' . urlencode('interentitydocuments') . '">'
    . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = interentitydocumentsAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans("Module450020Name"), -1, 'interentitydocuments@interentitydocuments');
print dol_get_fiche_end();

$module = class_exists('modinterentitydocuments') ? new modinterentitydocuments($db) : null;
$version = is_object($module) ? $module->version : '';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('ModuleInformation').'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Name').'</td><td>'.$langs->trans('Module450020Name').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Version').'</td><td>'.dol_escape_htmltag($version).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Publisher').'</td><td>Pierre Ardoin &lt;developpeur@lesmetiersdubatiment.fr&gt;</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Description').'</td><td>'.$langs->trans('Module450020Desc').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Compatibility').'</td><td>'.$langs->trans('CompatibilitySummary').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Dependencies').'</td><td>'.$langs->trans('Multicompany').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('License').'</td><td>GPL-3.0-or-later</td></tr>';
print '</table>';

print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('MainFeatures').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('AboutFeatureDocumentCloning').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('AboutFeaturePdfSync').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('AboutFeaturePaymentMirroring').'</td></tr>';
print '</table>';

print '<br>';

$readmefile = dol_buildpath('/interentitydocuments/README.md', 0);
if (is_readable($readmefile)) {
	$buffer = file_get_contents($readmefile);
	echo '<div class="fichecenter"><pre class="opacitymedium" style="white-space: pre-wrap;">';
	echo htmlspecialchars($buffer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	echo '</pre></div>';
} else {
	echo '<span class="opacitymedium">'.$langs->trans('interentitydocumentsReadmeMissing').'</span>';
}

echo '<br>',
'<a href="' . dol_buildpath('/interentitydocuments/LICENSE', 1) . '">',
'<img src="' . dol_buildpath('/interentitydocuments/img/gplv3.png', 1) . '"/>',
'</a>';

llxFooter();

$db->close();
