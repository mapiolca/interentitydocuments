<?php

/**
 * Hooks for the Interentity Documents module.
 */
class ActionsInterentitydocuments
{
	/** @var DoliDB Database handler */
	public $db;

	/** @var string Last error */
	public $error = '';

	/** @var array Error list */
	public $errors = array();

	/** @var array Hook results */
	public $results = array();

	/** @var string Hook output */
	public $resprints = '';

	/** @var bool Prevent recursive synchronizations */
	private static $syncInProgress = false;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Synchronize linked PDF copies once a Dolibarr PDF model has written its file.
	 *
	 * @param array       $parameters Hook parameters
	 * @param CommonDocGenerator &$object PDF model object
	 * @param string      &$action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int 0 to keep the native PDF generation flow
	 */
	public function afterPDFCreation($parameters, &$object, &$action, $hookmanager)
	{
		if (self::$syncInProgress || !empty($GLOBALS['INTERENTITYDOCUMENTS_SYNC_GENERATING_SOURCE_PDF'])) {
			return 0;
		}
		if (empty($parameters['object']) || !is_object($parameters['object'])) {
			return 0;
		}

		$sourceObject = $parameters['object'];
		$sourceFile = !empty($parameters['file']) ? $parameters['file'] : '';

		dol_include_once('/interentitydocuments/class/telink.class.php');
		$telink = new TTELink();

		self::$syncInProgress = true;
		try {
			$telink->syncLinkedTargetPdfs($sourceObject, false, $sourceFile);
		} catch (Throwable $e) {
			dol_syslog('interentitydocuments: linked PDF synchronization after PDF generation failed: '.$e->getMessage(), LOG_WARNING);
		} finally {
			self::$syncInProgress = false;
		}

		return 0;
	}
}
