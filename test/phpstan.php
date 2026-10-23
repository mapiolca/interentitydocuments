<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
/** PHPStan configuration; requires a separate native Dolibarr checkout. */
$core = getenv('DOLIBARR_DOCUMENT_ROOT');
if (!is_string($core) || !is_file($core.'/core/class/commonobject.class.php')) {
	throw new RuntimeException('Set DOLIBARR_DOCUMENT_ROOT to the native Dolibarr htdocs directory.');
}
$scanDirectories = array();
foreach (array('core/class', 'core/db', 'user/class', 'societe/class', 'commande/class', 'fourn/class', 'compta/facture/class', 'compta/paiement/class') as $directory) {
	$scanDirectories[] = $core.'/'.$directory;
}
$scanFiles = array();
foreach (array('functions', 'admin', 'ajax', 'files') as $library) {
	$scanFiles[] = $core.'/core/lib/'.$library.'.lib.php';
}
$multicompanyTypes = dirname($core).'/dev/tools/phan/stubs/multicompany.php';
if (is_file($multicompanyTypes)) {
	// Native type declarations only; this does not simulate an installed Multicompany module.
	$scanFiles[] = $multicompanyTypes;
}
return array('parameters' => array(
	'level' => 10,
	'phpVersion' => 80000,
	'dynamicConstantNames' => array('DOL_VERSION'),
	'tmpDir' => __DIR__.'/.phpstan-cache',
	'paths' => array(
		__DIR__.'/../class/interentitydocumentssynccontext.class.php',
		__DIR__.'/../class/telink.class.php',
		__DIR__.'/../admin/setup.php',
		__DIR__.'/../core/triggers/interface_98_modinterentitydocuments_Interentitydocumentstrigger.class.php',
	),
	'bootstrapFiles' => array(__DIR__.'/phpstan_bootstrap.php'),
	'scanDirectories' => $scanDirectories,
	'scanFiles' => $scanFiles,
));
