<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
/** Static-analysis constants only: never bootstrap main.inc.php or connect to a database. */
$core = getenv('DOLIBARR_DOCUMENT_ROOT');
if (!is_string($core) || $core === '') {
	throw new RuntimeException('Set DOLIBARR_DOCUMENT_ROOT before running PHPStan.');
}
define('DOL_DOCUMENT_ROOT', $core);
define('MAIN_DB_PREFIX', 'llx_');
// Dynamic in the PHPStan configuration: each supported version remains a possible value.
define('DOL_VERSION', '20.0.0');
define('DOL_URL_ROOT', '');
define('DOL_DATA_ROOT', '/unused-by-static-analysis');
