<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Execute configured inter-entity automation with a real destination-side actor. */
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
	 * @param callable(): int $operation Native destination operation
	 * @return int Native result, negative on configuration or actor failure
	 */
	public function run(int $targetEntity, callable $operation): int
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
		if ($targetEntity <= 0 || (int) $originalUser->id <= 0) {
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
			$actorId = getDolGlobalInt('IED_SYNC_USER_ID');
			if ($actorId <= 0) {
				// Legacy behavior is retained only when the original actor exists in the destination.
				$actorId = (int) $originalUser->id;
			}
			$actor = new User($this->db);
			if ($actor->fetch($actorId) <= 0
				|| (int) $actor->statut !== 1 || (int) $actor->socid > 0) {
				$this->error = $langs->trans('InterentitySyncActorUnavailable', $targetEntity);
				return -1;
			}
			$actor->loadRights('', 1);
			$user = $actor;
			if (is_object($mc)) {
				$mc->setValues($conf);
			}
			dol_syslog(__METHOD__.' source_user_id='.(int) $originalUser->id.' execution_user_id='.(int) $actor->id.' source_entity='.(int) $originalConf->entity.' target_entity='.$targetEntity, LOG_INFO);
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
