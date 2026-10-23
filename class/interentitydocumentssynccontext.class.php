<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Execute configured inter-entity automation while retaining the source initiator. */
class InterentitydocumentsSyncContext
{
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * The native source operation owns the enclosing transaction. Never grant rights here.
	 *
	 * @param int $targetEntity Destination selected by the configured mapping/link
	 * @param User $initiator User authorized for the source operation
	 * @param callable(): int $operation Native destination operation
	 * @return int Native result, negative on configuration or actor failure
	 */
	public function run(int $targetEntity, User $initiator, callable $operation): int
	{
		global $conf, $user, $langs, $mysoc, $mc;
		/** @var Conf $conf */
		/** @var User $user */
		/** @var Translate $langs */
		/** @var Societe $mysoc */
		/** @var ActionsMulticompany|null $mc */

		$this->error = '';
		$originalConf = $conf;
		$originalUser = $user;
		$originalMysoc = $mysoc;
		$originalMc = $mc;
		$originalTimezone = date_default_timezone_get();
		$originalExtrafields = $GLOBALS['extrafields'] ?? null;
		$hadExtrafields = array_key_exists('extrafields', $GLOBALS);
		$langs->load('interentitydocuments@interentitydocuments');
		if ($targetEntity <= 0 || (int) $initiator->id <= 0
			|| (int) $initiator->statut !== 1 || (int) $initiator->socid > 0) {
			$this->error = $langs->trans('InterentitySyncActorUnavailable', $targetEntity);
			return -1;
		}

		try {
			// Conf::setValues reloads constants, enabled modules and native document directories.
			$conf = clone $originalConf;
			$mc = is_object($originalMc) ? clone $originalMc : $originalMc;
			$conf->entity = $targetEntity;
			if ($conf->setValues($this->db) < 0) {
				$this->error = $langs->trans('InterentitySyncConfigurationUnavailable', $targetEntity);
				return -1;
			}
			$mysoc = clone $originalMysoc;
			$mysoc->setMysoc($conf);
			if (is_object($originalExtrafields)) {
				$GLOBALS['extrafields'] = new ExtraFields($this->db);
			}
			// Keep the real author without requiring membership in the destination.
			// Clear source permissions before loading real destination rights for native checks.
			$actor = clone $initiator;
			$actor->clearrights();
			$actor->loadRights('', 1);
			$user = $actor;
			if (is_object($mc)) {
				$mc->setValues($conf);
			}
			dol_syslog(__METHOD__.' initiator_user_id='.(int) $initiator->id.' source_entity='.(int) $originalConf->entity.' target_entity='.$targetEntity, LOG_INFO);
			return $operation();
		} finally {
			$mc = $originalMc;
			date_default_timezone_set($originalTimezone);
			if ($hadExtrafields) {
				$GLOBALS['extrafields'] = $originalExtrafields;
			} else {
				unset($GLOBALS['extrafields']);
			}
			$mysoc = $originalMysoc;
			$user = $originalUser;
			$conf = $originalConf;
		}
	}
}
