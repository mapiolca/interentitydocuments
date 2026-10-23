<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
/** Simulated synchronization entrypoints; creates no ERP data and changes no account. */

define('IED_TEST_CONTINUE', true);
require __DIR__.'/order_validation.php';

function price2num($value, $mode = '') { return (float) $value; }

class IedFlowDb extends IedTestDb
{
	public $bankAccountId = 0;
	public $bankResult = 1;
	public $bankActors = array();
	public function query($sql)
	{
		$rows = array();
		if (strpos($sql, 'SELECT rowid FROM '.MAIN_DB_PREFIX.'facture_fourn') === 0
			|| strpos($sql, 'SELECT rowid FROM '.MAIN_DB_PREFIX.'commande ') === 0
			|| strpos($sql, 'SELECT rowid FROM '.MAIN_DB_PREFIX.'element_element') === 0) {
			$rows = array();
		} elseif (strpos($sql, 'SELECT fk_target') === 0) {
			$rows = array((object) array('fk_target' => 62));
		} elseif (strpos($sql, 'SELECT fk_source') === 0) {
			$rows = array((object) array('fk_source' => 63));
		} elseif (strpos($sql, 'SELECT value FROM') === 0) {
			$rows = array((object) array('value' => $this->bankAccountId));
		} else {
			return parent::query($sql);
		}
		return (object) array('rows' => $rows);
	}
}

class Facture extends CommonObject
{
	const TYPE_STANDARD = 0;
	public function fetch($id)
	{
		$this->id = $id;
		$this->entity = in_array($id, array(62, 63), true) ? 2 : 1;
		$this->socid = 20;
		$this->ref = 'INV-TEST';
		$this->type = 0;
		$this->date = 1;
		$this->total_ttc = 100;
		$this->fk_project = 0;
		$this->note_public = $this->note_private = '';
		$this->cond_reglement_id = $this->mode_reglement_id = 0;
		$this->multicurrency_code = 'EUR';
		$this->multicurrency_tx = 1;
		$this->lines = array();
		return 1;
	}
	public function fetch_lines() { return 0; }
	public function getSommePaiement() { return 0; }
	public function create($user)
	{
		$this->db->creationActors[] = array($user->id, $GLOBALS['user']->id, $GLOBALS['conf']->entity);
		$this->id = 99;
		return 99;
	}
}
class FactureFournisseur extends Facture {}
class SupplierInvoiceLine extends CommonObject {}
class Paiement extends CommonObject
{
	public function create($user, $close = 0)
	{
		$this->db->creationActors[] = array($user->id, $GLOBALS['user']->id, $GLOBALS['conf']->entity);
		if ($this->db->throwOnCreate) { throw new RuntimeException('Native payment exception'); }
		$this->id = 99;
		return $this->db->createResult;
	}
	public function addPaymentToBank($user, ...$args)
	{
		$this->db->bankActors[] = array($user->id, $GLOBALS['user']->id, $GLOBALS['conf']->entity);
		$this->error = 'Native bank failure';
		return $this->db->bankResult;
	}
}
class PaiementFourn extends Paiement {}

function resetFlow()
{
	resetScenario();
	$GLOBALS['db'] = new IedFlowDb();
	$GLOBALS['db']->sourceRestricted = true;
	$GLOBALS['db']->syncActorId = 70;
	$GLOBALS['conf']->currency = 'EUR';
	$GLOBALS['user'] = new User($GLOBALS['db']);
}
function invokeFlow($flow, $supplierAction = 'ORDER_SUPPLIER_VALIDATE')
{
	global $db, $conf, $user, $langs;
	if ($flow === 'supplier_order' || $flow === 'customer_invoice') {
		$source = $flow === 'supplier_order' ? new CommandeFournisseur($db) : new Facture($db);
		$source->fetch(42);
		$conf->global->IED_AUTO_CREATE_SUPPLIER_INVOICE = 1;
		if ($flow === 'supplier_order') { $conf->global->IED_STATUS = $supplierAction; }
		$trigger = new Interfaceinterentitydocumentstrigger($db);
		$result = $trigger->runTrigger($flow === 'supplier_order' ? $supplierAction : 'BILL_VALIDATE', $source, $user, $langs, $conf);
		if ($result > 0) { check($trigger->errors === array(), 'Successful creation reported an error'); }
		return $result;
	}

	$source = new Paiement($db);
	$source->id = 42;
	$source->amounts = array(42 => 20);
	$source->datepaye = 1;
	$source->paiementid = 2;
	$source->num_payment = 'TEST';
	$source->ref = 'PAY-TEST';
	$trigger = new Interfaceinterentitydocumentstrigger($db);
	// Exercise the native-trigger payment workflow, including grouping, guard and link creation.
	$conf->global->IED_AUTO_CREATE_SUPPLIER_PAYMENT_FROM_CUSTOMER_PAYMENT = 1;
	$conf->global->IED_AUTO_CREATE_CUSTOMER_PAYMENT_FROM_SUPPLIER_PAYMENT = 1;
	return $trigger->runTrigger($flow === 'customer_payment' ? 'PAYMENT_CUSTOMER_CREATE' : 'PAYMENT_SUPPLIER_CREATE', $source, $user, $langs, $conf);
}

$flowScenarios = array();
foreach (array(
	'supplier_order' => array('fournisseur.commande.creer', 'supplier_order.creer', 'fournisseur.supplier_order_advance.validate'),
	'customer_invoice' => array('facture.creer', 'facture.invoice_advance.validate'),
) as $flow => $permissions) {
	$flowScenarios[$flow.' uses the advanced validation permission in the source'] = static function () use ($flow, $permissions) {
		$GLOBALS['conf']->global->MAIN_USE_ADVANCED_PERMS = 1;
		$GLOBALS['db']->deniedSourcePermissions = array_slice($permissions, 0, -1);
		check(invokeFlow($flow) > 0, 'Advanced validation required manual creation rights');
	};
	$flowScenarios[$flow.' refuses missing advanced source validation permission'] = static function () use ($flow, $permissions) {
		$GLOBALS['conf']->global->MAIN_USE_ADVANCED_PERMS = 1;
		$GLOBALS['db']->deniedSourcePermissions = array(end($permissions));
		check(invokeFlow($flow) < 0 && !$GLOBALS['db']->creationActors, 'Missing advanced source validation was bypassed');
	};
}
$flowScenarios['supplier submit requires ordering permission, not validation permission'] = static function () {
	$GLOBALS['db']->deniedSourcePermissions = array('fournisseur.commande.creer', 'supplier_order.creer', 'fournisseur.supplier_order_advance.validate');
	check(invokeFlow('supplier_order', 'ORDER_SUPPLIER_SUBMIT') > 0, 'Authorized supplier submit was rejected');
};
$flowScenarios['supplier submit refuses missing ordering permission'] = static function () {
	$GLOBALS['db']->deniedSourcePermissions = array('fournisseur.commande.commander');
	check(invokeFlow('supplier_order', 'ORDER_SUPPLIER_SUBMIT') < 0 && !$GLOBALS['db']->creationActors, 'Missing source ordering permission was bypassed');
};
foreach (array('supplier_order', 'customer_invoice', 'customer_payment', 'supplier_payment') as $flow) {
	$flowScenarios[$flow.' retains the restricted initiator and restores caller'] = static function () use ($flow) {
		$originalConf = $GLOBALS['conf'];
		$originalUser = $GLOBALS['user'];
		$originalCompany = $GLOBALS['mysoc'];
		$result = invokeFlow($flow);
		check($result >= 0, 'The automatic flow failed');
		check($GLOBALS['db']->creationActors === array(array(7, 7, 2)), 'Destination native creation used the wrong actor/entity');
		check($GLOBALS['conf'] === $originalConf && $GLOBALS['user'] === $originalUser && $GLOBALS['mysoc'] === $originalCompany, 'Original context was not restored');
	};
	$flowScenarios[$flow.' needs no manual destination creation rights'] = static function () use ($flow) {
		$GLOBALS['db']->denyActorRights = true;
		check(invokeFlow($flow) >= 0 && $GLOBALS['db']->creationActors === array(array(7, 7, 2)), 'Destination rights blocked automatic creation');
	};
	$flowScenarios[$flow.' refuses missing source authorization'] = static function () use ($flow) {
		$GLOBALS['db']->denySourceRights = true;
		check(invokeFlow($flow) < 0 && !$GLOBALS['db']->creationActors, 'Missing source authorization caused a write');
	};
}

foreach (array('customer_payment', 'supplier_payment') as $flow) {
	$flowScenarios[$flow.' bank error is propagated and context restored'] = static function () use ($flow) {
		$GLOBALS['db']->bankAccountId = 5;
		$GLOBALS['db']->bankResult = -1;
		check(invokeFlow($flow) < 0, 'Native bank failure was swallowed');
		check($GLOBALS['db']->bankActors === array(array(7, 7, 2)), 'Bank call used the wrong actor/entity');
		check($GLOBALS['conf']->entity === 1 && $GLOBALS['user']->id === 7, 'Bank failure leaked context');
		check(empty($GLOBALS['INTERENTITYDOCUMENTS_PAYMENT_SYNC_IN_PROGRESS']), 'Payment recursion guard leaked');
	};
	$flowScenarios[$flow.' native exception restores actor and recursion guard'] = static function () use ($flow) {
		$GLOBALS['db']->throwOnCreate = true;
		$thrown = false;
		try { invokeFlow($flow); } catch (RuntimeException $exception) { $thrown = true; }
		check($thrown && $GLOBALS['conf']->entity === 1 && $GLOBALS['user']->id === 7, 'Native exception lost or leaked context');
		check(empty($GLOBALS['INTERENTITYDOCUMENTS_PAYMENT_SYNC_IN_PROGRESS']), 'Payment recursion guard leaked');
	};
}
foreach ($flowScenarios as $name => $scenario) {
	resetFlow();
	try { $scenario(); fwrite(STDOUT, 'PASS '.$name."\n"); }
	catch (Throwable $exception) { $failures++; fwrite(STDERR, 'FAIL '.$name.': '.$exception->getMessage()."\n"); }
}
fwrite(STDOUT, count($scenarios) + count($flowScenarios).' total scenarios, '.$failures." failure(s).\n");
exit($failures ? 1 : 0);
