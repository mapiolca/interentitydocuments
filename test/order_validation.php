<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
/**
 * Isolated regression scenarios with simulated objects and SQL responses.
 * Usage: php test/order_validation.php /path/to/dolibarr/htdocs
 * No Dolibarr bootstrap, database connection or business operation is executed.
 */

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

$coreRoot = isset($argv[1]) ? realpath($argv[1]) : false;
if (!$coreRoot || !is_file($coreRoot.'/core/lib/files.lib.php')) {
	fwrite(STDERR, "Pass the Dolibarr htdocs directory as the first argument.\n");
	exit(2);
}
define('DOL_DOCUMENT_ROOT', $coreRoot);
define('MAIN_DB_PREFIX', 'ied_test_');
define('DOL_VERSION', '20.0.0');
foreach (array('LOG_ERR' => 3, 'LOG_WARNING' => 4, 'LOG_INFO' => 6, 'LOG_DEBUG' => 7) as $name => $value) {
	if (!defined($name)) {
		define($name, $value);
	}
}

/** Capture logs without writing them to disk. */
function dol_syslog($message, $level = LOG_DEBUG)
{
	$GLOBALS['iedTestLogs'][] = array('message' => $message, 'level' => $level);
}

function dol_include_once($path)
{
	return true;
}

function getDolGlobalInt($key, $default = 0)
{
	return isset($GLOBALS['conf']->global->{$key}) ? (int) $GLOBALS['conf']->global->{$key} : $default;
}

function getDolGlobalString($key, $default = '')
{
	return isset($GLOBALS['conf']->global->{$key}) ? (string) $GLOBALS['conf']->global->{$key} : $default;
}

function isModEnabled($module)
{
	return !in_array($module, $GLOBALS['db']->disabledModules, true);
}

/** Native configuration/user contracts are simulated; no account is changed. */
class IedTestConf extends stdClass
{
	public function setValues($db)
	{
		$this->global = clone $this->global;
		$this->global->IED_SYNC_USER_ID = $db->syncActorId;
		$this->global->IED_CUSTOMER_PAYMENT_BANK_ACCOUNT_ID = $db->bankAccountId ?? 0;
		$this->global->IED_SUPPLIER_PAYMENT_BANK_ACCOUNT_ID = $db->bankAccountId ?? 0;
		$GLOBALS['mc'] = new IedTestMc();
		$GLOBALS['mc']->setValues($this);
		if ($db->changeTimezone) { date_default_timezone_set('UTC'); }
		return $db->configResult;
	}
}

class IedTestMc
{
	public $entity = 1;
	public function setValues($conf) { $this->entity = $conf->entity; }
}
class ExtraFields
{
	public function __construct($db) {}
}

class IedTestCompany
{
	public $entity = 1;
	public function setMysoc($conf) { $this->entity = $conf->entity; }
}

class User extends stdClass
{
	public $id = 7;
	public $statut = 1;
	public $socid = 0;
	private $db;
	private $entity = 1;

	public function __construct($db) { $this->db = $db; }
	public function fetch($id, $login = '', $sid = '', $personal = 0, $entity = -1)
	{
		$this->id = $id;
		$this->entity = $entity < 0 ? $GLOBALS['conf']->entity : $entity;
		$this->statut = $this->db->actorActive ? 1 : 0;
		$this->socid = $this->db->actorExternal ? 10 : 0;
		return $this->db->actorExists && ($id !== 7 || !$this->db->sourceRestricted) ? 1 : 0;
	}
	public function loadRights($tag = '', $reload = 0) { $this->db->rightsLoads++; }
	public function hasRight(...$permission)
	{
		if ($this->db->denyDeleteRight && in_array('supprimer', $permission, true)) { return false; }
		return !($this->entity === 2 && ($this->db->denyActorRights || ($this->id === 7 && $this->db->sourceRestricted)));
	}
}

/** Only the error contract of CommonObject is simulated. */
class CommonObject extends stdClass
{
	public $error = '';
	public $errors = array();
	public $id = 0;
	public $entity = 1;
	public $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function liste_contact($status, $source) { return array(); }

	public function errorsToString()
	{
		return $this->error.($this->errors ? ($this->error !== '' ? ', ' : '').implode(', ', $this->errors) : '');
	}
}

class Commande extends CommonObject
{
	public function create($user)
	{
		$this->db->creationActors[] = array($user->id, $GLOBALS['user']->id, $GLOBALS['conf']->entity);
		$this->id = 99;
		return 99;
	}

	public function generateDocument($model, $outputlangs)
	{
		$this->db->pdfGenerations++;
		return 1;
	}

	public function fetch($id)
	{
		foreach (array('id' => $id, 'entity' => 1, 'socid' => 10, 'ref' => 'CO-TEST', 'date' => 1,
			'lines' => array(), 'date_commande' => 1, 'date_livraison' => null) as $key => $value) {
			$this->{$key} = $value;
		}
		$this->entity = $this->db->sourceEntity;
		return $this->db->sourceFetchResult;
	}

	public function fetch_lines()
	{
		return $this->db->sourceLinesResult;
	}
}

class CommandeFournisseur extends CommonObject
{
	public function fetch($id)
	{
		$this->id = $id;
		$this->ref = 'SO-TEST';
		$this->socid = 10;
		$this->entity = 1;
		$this->fk_project = 0;
		$this->lines = array();
		return 1;
	}

	public function delete($user)
	{
		$this->db->deletes++;
		if ($this->db->throwOnDelete) {
			throw new RuntimeException('Sensitive exception content');
		}
		return $this->db->deleteResult;
	}

	public function create($user)
	{
		$this->db->creates++;
		$this->db->creationActors[] = array($user->id, $GLOBALS['user']->id, $GLOBALS['conf']->entity);
		$this->db->createdCompanyEntity = $GLOBALS['mysoc']->entity;
		$this->db->createdMcEntity = $GLOBALS['mc']->entity;
		if ($this->db->throwOnCreate) {
			throw new RuntimeException('Sensitive exception content');
		}
		$this->id = 99;
		$this->errors = $this->db->createErrors;
		return $this->db->createResult;
	}
}

class CommandeFournisseurLigne extends CommonObject
{
}

class IedTestDb
{
	public $syncActorId = 0;
	public $sourceRestricted = false;
	public $actorExists = true;
	public $actorActive = true;
	public $actorExternal = false;
	public $denyActorRights = false;
	public $denyDeleteRight = false;
	public $configResult = 0;
	public $changeTimezone = false;
	public $createdCompanyEntity = 0;
	public $createdMcEntity = 0;
	public $rightsLoads = 0;
	public $creationActors = array();
	public $disabledModules = array();
	public $failStage = '';
	public $reverseMapping = true;
	public $targetMapping = true;
	public $hasOrigin = false;
	public $existingSupplierOrder = 0;
	public $sourceEntity = 1;
	public $sourceFetchResult = 1;
	public $sourceLinesResult = 0;
	public $createResult = 99;
	public $createErrors = array();
	public $throwOnCreate = false;
	public $throwOnDelete = false;
	public $deleteResult = 1;
	public $creates = 0;
	public $deletes = 0;
	public $writes = 0;
	public $pdfGenerations = 0;
	public $errno = '';

	public function query($sql)
	{
		$stage = '';
		$rows = array();
		if (strpos($sql, 'SELECT fk_entity') === 0) {
			$stage = 'target_mapping';
			$rows = $this->targetMapping ? array((object) array('fk_entity' => 2)) : array();
		} elseif (strpos($sql, 'SELECT fk_soc') === 0) {
			$stage = 'reverse_mapping';
			$rows = $this->reverseMapping ? array((object) array('fk_soc' => 20)) : array();
		} elseif (strpos($sql, "AND sourcetype = 'commandefourn'") !== false) {
			$stage = 'origin_lookup';
			$rows = $this->hasOrigin ? array((object) array('rowid' => 1)) : array();
		} elseif (strpos($sql, 'SELECT rowid FROM '.MAIN_DB_PREFIX.'commande_fournisseur') === 0) {
			$stage = 'existing_order_lookup';
			$rows = $this->existingSupplierOrder ? array((object) array('rowid' => $this->existingSupplierOrder)) : array();
		} elseif (strpos($sql, 'UPDATE') === 0) {
			$stage = 'assign_target_entity';
			$this->writes++;
		} elseif (strpos($sql, 'INSERT') === 0) {
			$stage = 'link_orders';
			$this->writes++;
		} else {
			throw new RuntimeException('Unexpected test query: '.$sql);
		}
		$this->errno = $stage === $this->failStage ? 'DB_ERROR_TEST' : '';
		return $this->errno ? false : (object) array('rows' => $rows);
	}

	public function num_rows($result)
	{
		return count($result->rows);
	}

	public function fetch_object($result)
	{
		return array_shift($result->rows) ?: false;
	}

	public function escape($value)
	{
		return str_replace("'", "''", $value);
	}

	public function lasterror()
	{
		return "SQL error containing 'private@example.invalid'";
	}

	public function lasterrno()
	{
		return $this->errno;
	}
}

class IedTestTranslate
{
	public $tab_translate = array();

	public function load($catalog)
	{
		foreach (file(dirname(__DIR__).'/langs/en_US/interentitydocuments.lang', FILE_IGNORE_NEW_LINES) as $line) {
			if (strpos($line, '=') !== false) {
				list($key, $value) = explode('=', $line, 2);
				$this->tab_translate[trim($key)] = trim($value);
			}
		}
	}

	public function trans($key, ...$args)
	{
		$text = isset($this->tab_translate[$key]) ? $this->tab_translate[$key] : $key;
		return $args ? vsprintf($text, $args) : $text;
	}
}

require dirname(__DIR__).'/class/telink.class.php';
require dirname(__DIR__).'/core/triggers/interface_98_modinterentitydocuments_Interentitydocumentstrigger.class.php';

function check($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function resetScenario()
{
	$GLOBALS['iedTestLogs'] = array();
	$GLOBALS['db'] = new IedTestDb();
	$GLOBALS['conf'] = new IedTestConf();
	$GLOBALS['conf']->entity = 1;
	$GLOBALS['conf']->global = (object) array('IED_AUTO_CREATE_SUPPLIER_ORDER_FROM_CUSTOMER_ORDER' => 1);
	$GLOBALS['user'] = new User($GLOBALS['db']);
	$GLOBALS['mysoc'] = new IedTestCompany();
	$GLOBALS['mc'] = new IedTestMc();
	$GLOBALS['langs'] = new IedTestTranslate();
	$GLOBALS['langs']->load('interentitydocuments@interentitydocuments');
}

function runValidationTrigger()
{
	global $db, $conf, $user, $langs;
	$order = new Commande($db);
	$order->fetch(42);
	$trigger = new Interfaceinterentitydocumentstrigger($db);
	$result = $trigger->runTrigger('ORDER_VALIDATE', $order, $user, $langs, $conf);
	return array($result, $trigger);
}

$scenarios = array(
	'restricted source actor uses the configured destination actor' => static function () {
		$db = $GLOBALS['db'];
		$db->sourceRestricted = true;
		$db->syncActorId = 70;
		$originalUser = $GLOBALS['user'];
		$originalConf = $GLOBALS['conf'];
		list($result) = runValidationTrigger();
		check($result === 99, 'An authorized automatic creation failed');
		check($db->creationActors === array(array(70, 70, 2)), 'The native call did not receive the destination actor/context');
		check($GLOBALS['user'] === $originalUser && $GLOBALS['conf'] === $originalConf, 'Caller identity/configuration changed');
		check($db->rightsLoads === 1, 'Destination rights were not loaded');
	},
	'restricted source without a configured actor fails before writing' => static function () {
		$GLOBALS['db']->sourceRestricted = true;
		list($result) = runValidationTrigger();
		check($result < 0 && $GLOBALS['db']->creates === 0, 'An unavailable actor caused a write');
	},
	'destination rights are never fabricated' => static function () {
		$GLOBALS['db']->syncActorId = 70;
		$GLOBALS['db']->denyActorRights = true;
		list($result) = runValidationTrigger();
		check($result < 0 && $GLOBALS['db']->creates === 0, 'A missing destination right was bypassed');
	},
	'disabled destination module prevents automatic creation' => static function () {
		$GLOBALS['db']->disabledModules = array('supplier_order', 'fournisseur');
		list($result) = runValidationTrigger();
		check($result < 0 && $GLOBALS['db']->creates === 0, 'Disabled destination modules were bypassed');
	},
	'recreating a counterpart requires the execution user deletion right' => static function () {
		$GLOBALS['db']->syncActorId = 70;
		$GLOBALS['db']->existingSupplierOrder = 88;
		$GLOBALS['db']->denyDeleteRight = true;
		list($result) = runValidationTrigger();
		check($result < 0 && $GLOBALS['db']->deletes === 0 && $GLOBALS['db']->creates === 0, 'A missing deletion right was bypassed');
	},

	'destination configuration failure restores original objects' => static function () {
		$GLOBALS['db']->configResult = -1;
		$originalConf = $GLOBALS['conf'];
		$originalUser = $GLOBALS['user'];
		list($result) = runValidationTrigger();
		check($result < 0 && $GLOBALS['db']->creates === 0, 'Configuration failure caused a write');
		check($GLOBALS['conf'] === $originalConf && $GLOBALS['user'] === $originalUser, 'Configuration failure leaked context');
	},

	'destination company and Multicompany context are used then restored' => static function () {
		$originalMc = $GLOBALS['mc'];
		$originalCompany = $GLOBALS['mysoc'];
		list($result) = runValidationTrigger();
		check($result === 99 && $GLOBALS['db']->createdCompanyEntity === 2 && $GLOBALS['db']->createdMcEntity === 2, 'Native target context was not loaded');
		check($GLOBALS['mc'] === $originalMc && $GLOBALS['mc']->entity === 1 && $GLOBALS['mysoc'] === $originalCompany, 'Source company/Multicompany context was changed');
	},
	'native exception restores timezone and extrafield cache' => static function () {
		$initialTimezone = date_default_timezone_get();
		date_default_timezone_set('Europe/Paris');
		$GLOBALS['extrafields'] = new ExtraFields($GLOBALS['db']);
		$originalExtrafields = $GLOBALS['extrafields'];
		$GLOBALS['db']->changeTimezone = true;
		$GLOBALS['db']->throwOnCreate = true;
		try {
			try { runValidationTrigger(); } catch (RuntimeException $exception) {}
			check(date_default_timezone_get() === 'Europe/Paris' && $GLOBALS['extrafields'] === $originalExtrafields, 'Native target configuration leaked');
		} finally {
			date_default_timezone_set($initialTimezone);
			unset($GLOBALS['extrafields']);
		}
	},

	'failed lookup must stop before creating or deleting' => static function () {
		$GLOBALS['db']->failStage = 'existing_order_lookup';
		list($result) = runValidationTrigger();
		check($result < 0, 'A failed existing-order query was treated as an absent order');
		check($GLOBALS['db']->creates === 0 && $GLOBALS['db']->deletes === 0, 'A lookup failure caused a mutation');
	},
	'native trigger error arrays must reach the validation caller' => static function () {
		$GLOBALS['db']->createResult = -1;
		$GLOBALS['db']->createErrors = array('Native trigger rejected supplier order', 'Restricted private@example.invalid detail');
		list($result, $trigger) = runValidationTrigger();
		check($result < 0 && strpos(implode(' ', $trigger->errors), 'Native trigger rejected supplier order') !== false, 'Native errors were lost');
		check(strpos(implode(' ', $trigger->errors), 'Restricted private@example.invalid detail') !== false, 'Additional native errors were lost');
		check(strpos(implode(' ', array_column($GLOBALS['iedTestLogs'], 'message')), 'private@example.invalid') === false, 'Native error details leaked to logs');
		check($GLOBALS['conf']->entity === 1, 'The entity context was not restored');
	},
	'missing reverse mapping must explain both entities' => static function () {
		$GLOBALS['db']->reverseMapping = false;
		list($result, $trigger) = runValidationTrigger();
		check($result < 0 && $GLOBALS['db']->creates === 0, 'Missing mapping did not stop creation');
		check(strpos(implode(' ', $trigger->errors), 'source entity 1') !== false, 'Missing mapping lacks source entity context');
		check(strpos(implode(' ', array_column($GLOBALS['iedTestLogs'], 'message')), 'reason=missing_reverse_mapping') !== false, 'Missing mapping lacks an explicit log reason');
	},
	'entity must be restored when native creation throws' => static function () {
		$GLOBALS['db']->throwOnCreate = true;
		$thrown = false;
		try {
			runValidationTrigger();
		} catch (RuntimeException $exception) {
			$thrown = true;
			check($exception->getMessage() === 'Sensitive exception content', 'The native exception was replaced');
		}
		check($thrown, 'The native exception was swallowed');
		check($GLOBALS['conf']->entity === 1, 'A native exception leaked the target entity');
	},
	'entity must be restored when native deletion throws' => static function () {
		$GLOBALS['db']->existingSupplierOrder = 88;
		$GLOBALS['db']->throwOnDelete = true;
		$thrown = false;
		try {
			runValidationTrigger();
		} catch (RuntimeException $exception) {
			$thrown = true;
			check($exception->getMessage() === 'Sensitive exception content', 'The native exception was replaced');
		}
		check($thrown, 'The native exception was swallowed');
		check($GLOBALS['conf']->entity === 1 && $GLOBALS['db']->creates === 0, 'A deletion exception leaked context or continued creation');
		check(strpos(implode(' ', array_column($GLOBALS['iedTestLogs'], 'message')), 'stage=delete_existing_order_exception') !== false, 'The deletion exception was not logged');
	},
	'missing source order must have a nonempty error' => static function () {
		$GLOBALS['db']->sourceFetchResult = 0;
		list($result, $trigger) = runValidationTrigger();
		check($result < 0 && trim(implode(' ', $trigger->errors)) !== '', 'Missing source order has an empty error');
		check($GLOBALS['db']->creates === 0, 'Creation continued after a missing source');
	},
	'failed source line loading must stop creation' => static function () {
		$GLOBALS['db']->sourceLinesResult = -1;
		list($result) = runValidationTrigger();
		check($result < 0 && $GLOBALS['db']->creates === 0, 'Failed source line loading did not stop creation');
	},
	'missing source entity must not fall back to the actor entity' => static function () {
		$GLOBALS['db']->sourceEntity = 0;
		list($result) = runValidationTrigger();
		check($result < 0 && $GLOBALS['db']->creates === 0, 'Missing owner entity was silently replaced');
	},
	'zero native creation result must not create an object link' => static function () {
		$GLOBALS['db']->createResult = 0;
		list($result) = runValidationTrigger();
		check($result < 0 && $GLOBALS['db']->writes === 0, 'Zero creation result was treated as success');
	},
	'deletion failure must stop recreation and restore the entity' => static function () {
		$GLOBALS['db']->existingSupplierOrder = 88;
		$GLOBALS['db']->deleteResult = -1;
		list($result) = runValidationTrigger();
		check($result < 0 && $GLOBALS['db']->creates === 0 && $GLOBALS['conf']->entity === 1, 'Deletion failure was ignored or leaked context');
	},
	'success must retain the linked creation workflow' => static function () {
		list($result, $trigger) = runValidationTrigger();
		check($result === 99 && $trigger->errors === array(), 'Successful creation reported an error');
		check($GLOBALS['db']->creates === 1 && $GLOBALS['db']->writes === 2, 'The native creation/link workflow changed');
	},
	'validation must leave PDF generation and ECM renaming to the native caller' => static function () {
		list($result) = runValidationTrigger();
		check($result === 99, 'The business clone failed before native finalization');
		check($GLOBALS['db']->pdfGenerations === 0, 'A definitive PDF was generated before the native draft rename');
		check($GLOBALS['db']->writes === 2, 'The validation trigger changed the document index');
	},
	'customer orders originating from supplier orders must be skipped' => static function () {
		$GLOBALS['db']->hasOrigin = true;
		list($result) = runValidationTrigger();
		check($result === 0 && $GLOBALS['db']->creates === 0, 'A reverse synchronization loop was introduced');
	},
	'third parties without an entity mapping must remain neutral' => static function () {
		$GLOBALS['db']->targetMapping = false;
		list($result) = runValidationTrigger();
		check($result === 0 && $GLOBALS['db']->creates === 0, 'An ordinary customer order triggered inter-entity creation');
	},
	'disabled automation must remain neutral' => static function () {
		$GLOBALS['conf']->global->IED_AUTO_CREATE_SUPPLIER_ORDER_FROM_CUSTOMER_ORDER = 0;
		list($result) = runValidationTrigger();
		check($result >= 0 && $GLOBALS['db']->creates === 0, 'Disabled automation executed');
	},
);
foreach (array('target_mapping', 'origin_lookup', 'reverse_mapping', 'assign_target_entity', 'link_orders') as $stage) {
	$scenarios['SQL failure: '.$stage] = static function () use ($stage) {
		$GLOBALS['db']->failStage = $stage;
		list($result, $trigger) = runValidationTrigger();
		check($result < 0 && count($trigger->errors) > 0, 'SQL failure was not propagated');
		$logs = implode("\n", array_column($GLOBALS['iedTestLogs'], 'message'));
		check(strpos($logs, 'stage='.$stage) !== false, 'The failing stage was not logged');
		check(strpos($logs, 'DB_ERROR_TEST') !== false, 'The database error code was not logged');
		check(strpos($logs, 'private@example.invalid') === false, 'Sensitive SQL content leaked to logs');
	};
}

foreach (array('actorExists', 'actorActive', 'actorExternal') as $property) {
	$scenarios['invalid destination actor: '.$property] = static function () use ($property) {
		$GLOBALS['db']->syncActorId = 70;
		$GLOBALS['db']->{$property} = $property === 'actorExternal';
		list($result) = runValidationTrigger();
		check($result < 0 && $GLOBALS['db']->creates === 0, 'An invalid destination actor was used');
	};
}

$failures = 0;
foreach ($scenarios as $name => $scenario) {
	resetScenario();
	try {
		$scenario();
		fwrite(STDOUT, 'PASS '.$name."\n");
	} catch (Throwable $exception) {
		$failures++;
		fwrite(STDERR, 'FAIL '.$name.': '.$exception->getMessage()."\n");
	}
}
fwrite(STDOUT, count($scenarios).' scenarios, '.$failures." failure(s).\n");
if (!defined('IED_TEST_CONTINUE')) {
	exit($failures ? 1 : 0);
}
