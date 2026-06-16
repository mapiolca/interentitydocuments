<?php
/* Compatibility checks for the Documents inter-entités module.
 * Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Centralized compatibility checks.
 */
class InterentitydocumentsCompatibility
{
	const MIN_DOLIBARR_VERSION = '20.0.0';
	const MIN_PHP_VERSION = '8.0.0';

	/**
	 * Test current Dolibarr version.
	 *
	 * @param string $version Minimal version
	 * @return bool
	 */
	public static function isDolibarrVersionAtLeast($version)
	{
		return defined('DOL_VERSION') && version_compare(DOL_VERSION, $version, '>=');
	}

	/**
	 * Test current PHP version.
	 *
	 * @param string $version Minimal version
	 * @return bool
	 */
	public static function isPhpVersionAtLeast($version)
	{
		return version_compare(PHP_VERSION, $version, '>=');
	}

	/**
	 * Return detected Dolibarr version.
	 *
	 * @return string
	 */
	public static function getDetectedDolibarrVersion()
	{
		return defined('DOL_VERSION') ? DOL_VERSION : 'Unknown';
	}

	/**
	 * Return compatibility feature list.
	 *
	 * @return array
	 */
	public static function getFeatures()
	{
		return array(
			'module_baseline' => self::buildFeature(
				'module_baseline',
				'CompatibilityFeatureModuleBaseline',
				'CompatibilityFeatureModuleBaselineDesc',
				self::MIN_DOLIBARR_VERSION,
				self::MIN_PHP_VERSION
			),
			'linked_documents_pdf_sync' => self::buildFeature(
				'linked_documents_pdf_sync',
				'CompatibilityFeaturePdfSync',
				'CompatibilityFeaturePdfSyncDesc',
				self::MIN_DOLIBARR_VERSION,
				self::MIN_PHP_VERSION,
				function_exists('dol_copy') || defined('DOL_DOCUMENT_ROOT'),
				'RequiresDolibarrFilesHelpers'
			),
			'pdfgeneration_hook' => self::buildFeature(
				'pdfgeneration_hook',
				'CompatibilityFeaturePdfGenerationHook',
				'CompatibilityFeaturePdfGenerationHookDesc',
				self::MIN_DOLIBARR_VERSION,
				self::MIN_PHP_VERSION
			),
			'payment_mirroring' => self::buildFeature(
				'payment_mirroring',
				'CompatibilityFeaturePaymentMirroring',
				'CompatibilityFeaturePaymentMirroringDesc',
				self::MIN_DOLIBARR_VERSION,
				self::MIN_PHP_VERSION
			),
			'multicompany_dependency' => self::buildFeature(
				'multicompany_dependency',
				'CompatibilityFeatureMulticompany',
				'CompatibilityFeatureMulticompanyDesc',
				self::MIN_DOLIBARR_VERSION,
				self::MIN_PHP_VERSION,
				self::isMulticompanyEnabled(),
				'RequiresMulticompany'
			),
		);
	}

	/**
	 * Test one feature availability.
	 *
	 * @param string $code Feature code
	 * @return bool
	 */
	public static function isFeatureAvailable($code)
	{
		$features = self::getFeatures();

		return !empty($features[$code]['available']);
	}

	/**
	 * Return unavailable features.
	 *
	 * @return array
	 */
	public static function getUnavailableFeatures()
	{
		$unavailable = array();
		foreach (self::getFeatures() as $code => $feature) {
			if (empty($feature['available'])) {
				$unavailable[$code] = $feature;
			}
		}

		return $unavailable;
	}

	/**
	 * Build one feature descriptor.
	 *
	 * @param string $code Feature code
	 * @param string $label Translation key
	 * @param string $description Translation key
	 * @param string $minDolibarr Minimal Dolibarr version
	 * @param string $minPhp Minimal PHP version
	 * @param bool   $extraAvailable Additional availability condition
	 * @param string $extraReason Translation key for additional condition failure
	 * @return array
	 */
	private static function buildFeature($code, $label, $description, $minDolibarr, $minPhp, $extraAvailable = true, $extraReason = '')
	{
		$available = true;
		$reason = '';

		if (!self::isDolibarrVersionAtLeast($minDolibarr)) {
			$available = false;
			$reason = 'RequiresDolibarrVersion';
		} elseif (!self::isPhpVersionAtLeast($minPhp)) {
			$available = false;
			$reason = 'RequiresPhpVersion';
		} elseif (!$extraAvailable) {
			$available = false;
			$reason = $extraReason;
		}

		return array(
			'code' => $code,
			'label' => $label,
			'description' => $description,
			'min_dolibarr' => $minDolibarr,
			'min_php' => $minPhp,
			'available' => $available,
			'reason' => $reason,
		);
	}

	/**
	 * Test Multicompany availability when the helper is loaded.
	 *
	 * @return bool
	 */
	private static function isMulticompanyEnabled()
	{
		global $conf;

		if (function_exists('isModEnabled') && isModEnabled('multicompany')) {
			return true;
		}

		return !empty($conf->multicompany->enabled) || !empty($conf->global->MAIN_MODULE_MULTICOMPANY);
	}
}
