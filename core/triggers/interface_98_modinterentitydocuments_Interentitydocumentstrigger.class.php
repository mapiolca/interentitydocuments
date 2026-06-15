<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2013 ATM Consulting <support@atm-consulting.fr>
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
 *    \file        core/triggers/interface_99_modMyodule_Mytrigger.class.php
 *    \ingroup    interentitydocuments
 *    \brief        Sample trigger
 *    \remarks    You can create other triggers by copying this one
 *                - File name should be either:
 *                    interface_99_modMymodule_Mytrigger.class.php
 *                    interface_99_all_Mytrigger.class.php
 *                - The file must stay in core/triggers
 *                - The class name must be InterfaceMytrigger
 *                - The constructor method must be named InterfaceMytrigger
 *                - The name property name must be Mytrigger
 */

/**
 * Trigger class
 */
class Interfaceinterentitydocumentstrigger
{

	private $db;
	public $name;
	public $family;
	public $description;
	public $version;
	public $picto;
	public $error = '';
	public $errors = array();

	private $OrderLineCache = array();
	private $CommandeCache = array();
	private $CommandeFournisseurCache = array();

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "demo";
		$this->description = "Triggers of this module are empty functions."
			. "They have no effect."
			. "They are provided for tutorial purpose only.";
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = 'development';
		$this->picto = 'interentitydocuments@interentitydocuments';
		$this->errors=array();
	}

	/**
	 * Trigger name
	 *
	 * @return        string    Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return        string    Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}

	/**
	 * Trigger version
	 *
	 * @return        string    Version of trigger file
	 */
	public function getVersion()
	{
		global $langs;
		$langs->load("admin");

		if ($this->version == 'development') {
			return $langs->trans("Development");
		} elseif ($this->version == 'experimental')

			return $langs->trans("Experimental");
		elseif ($this->version == 'dolibarr')
			return DOL_VERSION;
		elseif ($this->version)
			return $this->version;
		else {
			return $langs->trans("Unknown");
		}
	}

	public function runTrigger($action, $object, $user, $langs, $conf)
	{
		return $this->run_trigger($action, $object, $user, $langs, $conf);
	}

	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "run_trigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string    $action Event action code
	 * @param Object    $object Object
	 * @param User      $user   Object user
	 * @param Translate $langs  Object langs
	 * @param conf      $conf   Object conf
	 * @return        int                        <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function run_trigger($action, $object, $user, $langs, $conf)
	{
		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action
		// Users

		if ($action === 'ORDER_SUPPLIER_VALIDATE'){
			/** @var CommandeFournisseur $object */
			// Si l'option de réception auto est active, une commande fournisseur liée
			// à une entité Dolibarr doit porter son entrepôt de réception.
			$receptionWarehouseId = $this->getSupplierOrderReceptionWarehouseId($object);
			if(!empty($conf->global->OFSOM_SET_SUPPLIER_ORDER_RECEIVED_ON_SUPPLIER_SHIPMENT_CLOSED) && $receptionWarehouseId <= 0) {
				dol_include_once('/interentitydocuments/class/telink.class.php');
				$telink = new TTELink();
				$res = $telink->getSocEntityFromSocId($object->socid);
				if($res > 0){
					$this->setError('ReceptionWarehouseNotDefined');
					return -1;
				}
				elseif ($res < 0){
					$this->setError('ReceptionWarehouseCheckError');
					return -1;
				}
				else{
					// Pas d'entité liée à la société alors tout est ok
					// Le traitement peut continuer
				}
			}
		}


		if (($action === 'ORDER_SUPPLIER_VALIDATE' && empty($conf->global->OFSOM_STATUS))
			|| $action === $conf->global->OFSOM_STATUS)
		{
			// Transmission de la commande fournisseur vers l'entité fournisseur pour création commande client
			return $this->_cloneOrder($object);
		}
		elseif ($action === 'BILL_VALIDATE' && !empty($conf->global->OFSOM_AUTO_CREATE_SUPPLIER_INVOICE)) {
			/** @var Facture $object */
			// Création automatique de la facture fournisseur dans l'entité de destination
			return $this->_cloneInvoice($object);
		}
		elseif ($action === 'ORDER_VALIDATE' && !empty($conf->global->OFSOM_AUTO_CREATE_SUPPLIER_ORDER_FROM_CUSTOMER_ORDER)) {
			/** @var Commande $object */
			// Création automatique de la commande fournisseur dans l'entité de destination.
			return $this->_cloneSupplierOrderFromCustomerOrder($object);
		}
		elseif (in_array($action, array('BILL_PAYED', 'BILL_UNPAYED', 'BILL_CANCEL', 'BILL_SUPPLIER_PAYED', 'BILL_SUPPLIER_UNPAYED', 'BILL_SUPPLIER_CANCEL'), true)) {
			$this->syncLinkedTargetPdfs($object, true);
			return 0;
		}
		elseif ($action === 'PAYMENT_CUSTOMER_CREATE') {
			if (!empty($conf->global->OFSOM_AUTO_CREATE_SUPPLIER_PAYMENT_FROM_CUSTOMER_PAYMENT)) {
				$result = $this->syncSupplierPaymentFromCustomerPayment($object, $user);
				if ($result < 0) {
					return -1;
				}
			}
			$this->syncCustomerPaymentInvoicePdfs($object);
			return 0;
		}
		elseif ($action === 'PAYMENT_SUPPLIER_CREATE') {
			if (!empty($conf->global->OFSOM_AUTO_CREATE_CUSTOMER_PAYMENT_FROM_SUPPLIER_PAYMENT)) {
				$result = $this->syncCustomerPaymentFromSupplierPayment($object, $user);
				if ($result < 0) {
					return -1;
				}
			}
			$this->syncSupplierPaymentInvoicePdfs($object);
			return 0;
		}
		elseif ($action === 'ORDER_SUPPLIER_RECEIVE') {

			require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';

			if (!empty($conf->global->OFSOM_LINK_STATUSSUPPLIERORDER_ORDERCHILD)) {
				$sql = "SELECT fk_target FROM " . MAIN_DB_PREFIX . "element_element WHERE fk_source ='" . $object->id . "' AND targettype = 'commande' AND sourcetype ='commandefourn'";
				$resql = $this->db->query($sql);

				if ($resql) {
					if ($this->db->num_rows($resql) > 0) {
						$obj = $this->db->fetch_object($resql);
						$id_ordertarget = $obj->fk_target;

						$commande = new Commande($this->db);
						$res = $commande->fetch($id_ordertarget);

						if ($res > 0) {
							if ($object->statut == CommandeFournisseur::STATUS_RECEIVED_PARTIALLY) {
								$commande->setStatut(Commande::STATUS_SHIPMENTONPROCESS);
							} else {
								$commande->setStatut(Commande::STATUS_CLOSED);
							}
							$res = $commande->update($user);
							if ($res > 0) {
								return 1;
							} else {
								return -1;
							}
						} else {
							return -1;
						}
					} else {
						return 0;
					}
				} else {
					return -1;
				}
			}
		}
		elseif ($action === 'LINEORDER_SUPPLIER_DISPATCH') {

			global $conf, $user;

			require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
			require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.commande.dispatch.class.php';

			if (!empty($conf->global->OFSOM_LINK_STATUSSUPPLIERORDER_ORDERCHILD)) {
				$error = 0;

				$langs->load('interentitydocuments@interentitydocuments');

				//récup réception créée
				$sql = "SELECT MAX(rowid) as id FROM " . MAIN_DB_PREFIX . "commande_fournisseur_dispatch";
				$resql = $this->db->query($sql);

				if ($resql) {
					$obj = $this->db->fetch_object($resql);

					//récup toutes les infos de la réception créé
					$supplierorderdispatch = new CommandeFournisseurDispatch($this->db);
					$res = $supplierorderdispatch->fetch($obj->id);

					if ($res < 0) {
						$this->errors[]=$supplierorderdispatch->error;
						$error++;
					} else {
						//on enregistré la quantité réceptionnée
						$qty = $supplierorderdispatch->qty;
					}
				} else {
					$this->errors[]=$this->db->lasterror;
					$error++;
				}

				if ($supplierorderdispatch->qty > 0) {
					//récup commande client liée à la commande fourn
					if (!$error) {
						$sql = "SELECT fk_target FROM " . MAIN_DB_PREFIX . "element_element WHERE fk_source ='" . $object->id . "' AND targettype = 'commande' AND sourcetype ='commandefourn'";
						$resql = $this->db->query($sql);

						if ($resql) {
							if ($this->db->num_rows($resql) > 0) {
								$obj = $this->db->fetch_object($resql);
								$id_ordertarget = $obj->fk_target;

								$commande = new Commande($this->db);
								$res = $commande->fetch($id_ordertarget);
								if ($res < 0) {
									$this->errors[]=$commande->error;
									$error++;
								}
							}
						} else {
							$this->errors[]=$this->db->lasterror;
							$error++;
						}
					}

					if (!$error) {
						//récup commandes fourn enfant de la commande client
						if (!empty($commande)) {
							$res = $commande->fetchObjectLinked();
							if ($res<0) {
								$this->errors[]=$commande->error;
								$error++;
							}
						}

						if (!empty($commande->linkedObjects['order_supplier'])) {
							//pour chaque commande fourn enfant
							foreach ($commande->linkedObjectsIds['order_supplier'] as $key => $commandeFournChildId) {
								$commandeFournChild = new CommandeFournisseur($this->db);
								$res = $commandeFournChild->fetch($commandeFournChildId);
								if ($res < 0) {
									$error++;
									$this->errors[]=$commandeFournChild->error;
								}


								if (!$error) {
									$res= $commandeFournChild->fetch_lines();
									if ($res < 0) {
										$error++;
										$this->errors[]=$commandeFournChild->error;
									}

									//pour chaque ligne de la commande fourn enfant
									foreach ($commandeFournChild->lines as $line) {
										//si le produit de la ligne correspond au produit réceptionné par la commande fournisseur d'origine alors on traite
										if ($line->fk_product == $supplierorderdispatch->fk_product) {
											//on vérifie ce qui a déjà été réceptionné dans la commande fourn enfant
											$sql = "SELECT SUM(qty) as qty FROM " . MAIN_DB_PREFIX . "commande_fournisseur_dispatch WHERE fk_commande = '" . $commandeFournChild->id . "' AND fk_product = '" . $supplierorderdispatch->fk_product . "'";
											$resql = $this->db->query($sql);
											if ($resql) {
												$obj = $this->db->fetch_object($resql);
												$qtydispatched = $obj->qty;      //quantité déjà réceptionnée dans la commande fourn enfant
												$maxqtytodispatch = $line->qty;  //qunatité maximum que l'on peut receptionner

												$qtytodispatch = $maxqtytodispatch - $qtydispatched;     //quantité qu'il reste à receptionner
												if ($qtytodispatch > $supplierorderdispatch->qty)
													$qtytodispatch = $supplierorderdispatch->qty;

												if ($qtytodispatch <= 0)
													continue;                        //si il n'y a plus rien à réceptionner pour ce produit et cette commande, alors on passe à la commande suivante
											} else {
												$error++;
												$this->errors[] = $this->db->lasterror;
                                           }

											if (!$error) {

												//on modifie la conf provisoirement pour pas qu'il y ai de mouvement de stock
												if ($conf->global->STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER)
													$conf->global->STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER = 0;

												//on réceptionne le produit pour la commande fourn enfant
												$res = $commandeFournChild->dispatchProduct($user, $supplierorderdispatch->fk_product, $qtytodispatch, $supplierorderdispatch->fk_entrepot, '', '', '', '', '', $line->id, 1);

												if (empty($conf->global->STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER))
													$conf->global->STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER = 1;

												if ($res==-2) {
													$this->errors[]=$langs->trans('OFSOMErrorBadStatusOrder',$commandeFournChild->ref);
													$error++;
												} elseif ($res < 0) {
													$this->errors[] =$commandeFournChild->error;
													$error++;
												} else {
													//on change le statut de la commande fourn enfant suivant ce qui a été receptionné
													$res = $commandeFournChild->calcAndSetStatusDispatch($user);
													if ($res < 0) {
														$this->errors[] =$commandeFournChild->error;
														$error++;
													} else {
														$qty = $qty - $qtytodispatch;
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}

				if (!$error)
					return 1;
				else return -1;
			}

			return 0;
		}
		else if ($action === 'LINEORDER_UPDATE' && !empty($conf->global->OFSOM_UPDATE_LINE_SOURCE)) {
			if ($object->oldline->qty != $object->qty || $object->oldline->subprice != $object->subprice) {
				$conf->supplierorderdet->enabled = 1;
                dol_include_once('/fourn/class/fournisseur.commande.class.php');
                $commande = new Commande($object->db);
                $commande->fetch($object->fk_commande);
                $res = $object->db->query("SELECT fk_entity FROM " . MAIN_DB_PREFIX . "thirdparty_entity WHERE entity=" . $conf->entity . " AND fk_soc=" . $commande->socid . ' AND fk_entity <> ' . $conf->entity);
                $obj = $object->db->fetch_object($res);
                if (!empty($obj->fk_entity)) {
                    $error = 0;
                    if (!empty($object->array_options['options_supplier_order_det_source'])) {
                        $supplierOrderLineId = (int) $object->array_options['options_supplier_order_det_source'];
                        $supplierOrderLine = new CommandeFournisseurLigne($object->db);
                        $res = $supplierOrderLine->fetch($supplierOrderLineId);
                        if ($res <= 0) {
                            $error++;
                            $this->errors[] = $supplierOrderLine->errorsToString();
                        }

                        if (!$error) {
                            $tabprice = calcul_price_total($object->qty, $object->subprice, $supplierOrderLine->remise_percent, $supplierOrderLine->tva_tx, $supplierOrderLine->localtax1_tx, $supplierOrderLine->localtax2_tx, 0, 'HT', $supplierOrderLine->info_bits, $supplierOrderLine->product_type, $supplierOrderLine->thirdparty, array(), 100, $supplierOrderLine->multicurrency_tx, $supplierOrderLine->pu_ht_devise);

                            $supplierOrderLine->qty = $object->qty;
                            $supplierOrderLine->subprice = $object->subprice;
                            $supplierOrderLine->total_ht = $tabprice[0];
                            $supplierOrderLine->total_tva = $tabprice[1];
                            $supplierOrderLine->total_ttc = $tabprice[2];
                            $res = $supplierOrderLine->update();
                            if ($res < 0) {
                                $error++;
                                $this->errors[] = $supplierOrderLine->errorsToString();
                            }

                            if (!$error) {
                                //MAJ des totaux
                                $tmpentity = $conf->entity;
                                $conf->entity = $obj->fk_entity;
                                $supplierOrder = new CommandeFournisseur($object->db);
                                $supplierOrder->fetch($supplierOrderLine->fk_commande);
                                $res = $supplierOrder->update_price('', 'auto');
                                if ($res < 0) {
                                    $error++;
                                    $this->errors[] = $supplierOrder->errorsToString();
                                }

                                $conf->entity = $tmpentity;
                            }
                        }
                    }

                    if ($error) {
                        return -1;
                    } else {
                        return 1;
                    }
                }
			}
		}
		else if ($action === 'LINEORDER_INSERT') {
			if (!empty($object->origin_id)) $object->add_object_linked($object->origin, $object->origin_id);

			if (!empty($conf->global->OFSOM_UPDATE_ORDER_SOURCE) && $object->origin != 'supplierorderdet') {
				$conf->commandefourn = new stdClass();
				$conf->commandefourn->enabled = 1;

				$commande = new Commande($object->db);
				$res = $commande->fetch($object->fk_commande);
				if($res > 0) {
					$commande->fetchObjectLinked();
					if(!empty($commande->linkedObjectsIds['commandefourn'])) {
						dol_include_once('/fourn/class/fournisseur.commande.class.php');

						$supplierOrderId = array_shift($commande->linkedObjectsIds['commandefourn']);
						$supplierOrder = new CommandeFournisseur($object->db);
						$res = $supplierOrder->fetch($supplierOrderId);
						if($res > 0) {
							$supplierOrder->statut = CommandeFournisseur::STATUS_DRAFT;
							$fk_newline = $supplierOrder->addline($object->desc, $object->subprice, $object->qty, $object->tva_tx, $object->localtax1_tx, $object->localtax2_tx, $object->fk_product, 0, '', $object->remise_percent, 'HT', $object->total_ht, $object->product_type, $object->info_bits, false, $object->date_start, $object->date_end, $object->array_options, $object->fk_unit,0, '',0);
							if($fk_newline > 0) $object->add_object_linked('supplierorderdet', $fk_newline);
						}
					}
				}

				unset($conf->commandefourn);
			}
		}
		else if ($action === 'LINEORDER_DELETE') {
			if (!empty($conf->global->OFSOM_UPDATE_ORDER_SOURCE)) {
				$conf->supplierorderdet = new stdClass();
				$conf->supplierorderdet->enabled = 1;

				$object->fetchObjectLinked(null, null, $object->id, $object->element, 'OR', 1, 'sourcetype', 0);
				if (!empty($object->linkedObjectsIds['supplierorderdet'])) {
					dol_include_once('/fourn/class/fournisseur.commande.class.php');

					$supplierOrderLineId = array_shift($object->linkedObjectsIds['supplierorderdet']);
					$supplierOrderLine = new CommandeFournisseurLigne($object->db);
					if ($supplierOrderLine->fetch($supplierOrderLineId) > 0) $supplierOrderLine->delete();
				}

				unset($conf->supplierorderdet);
			}
			$object->deleteObjectLinked();
		}
		else if ($action === 'ORDER_MODIFY' && !empty($object->oldcopy) && $object->oldcopy->date_livraison != $object->date_livraison) {
			//Maj auto date de livraison
			$conf->commandefourn = new stdClass;
			$conf->commandefourn->enabled = 1;
			$object->fetchObjectLinked(null, 'commandefourn', $object->id, $object->element, 'OR', 1, 'sourcetype', 0);
			if (!empty($object->linkedObjectsIds['commandefourn'])) {
				dol_include_once('/fourn/class/fournisseur.commande.class.php');
				$fkSupplierOrder = array_shift($object->linkedObjectsIds['commandefourn']);
				$supplierOrder = new CommandeFournisseur($object->db);
				$supplierOrder->fetch($fkSupplierOrder);
				if (is_callable(array($supplierOrder, 'setDeliveryDate'))) {
					$supplierOrder->setDeliveryDate($user, $object->date_livraison);
				} else {
					// For Dolibarr < V14
					$supplierOrder->set_date_livraison($user, $object->date_livraison);
				}

			}
		}
		else if ($action == 'SHIPPING_CLOSED' && !empty($conf->global->OFSOM_SET_SUPPLIER_ORDER_RECEIVED_ON_SUPPLIER_SHIPMENT_CLOSED))
		{
			/** @var Expedition $object */
			//  Passe la commande fournisseur à reçut (entité A) lors de la cloture de l'expedition (Entité courrante)
			if(!empty($conf->global->OFSOM_SET_SUPPLIER_ORDER_RECEIVED_ON_SUPPLIER_SHIPMENT_CLOSED)){
				return $this->receiveSupplierOrderFromShipment($object);
			}
		}
		return 0;
	}

	/**
	 * Synchronize source PDF copies to linked target document folders.
	 *
	 * @param CommonObject $object Source object
	 * @param bool         $forceGenerateSourcePdf Legacy flag; generation is only attempted when no readable source PDF exists
	 * @return int Number of copied files
	 */
	private function syncLinkedTargetPdfs($object, $forceGenerateSourcePdf = false)
	{
		dol_include_once('/interentitydocuments/class/telink.class.php');

		$telink = new TTELink();
		$result = $telink->syncLinkedTargetPdfs($object, $forceGenerateSourcePdf);
		if ($result < 0) {
			dol_syslog('interentitydocuments: linked PDF synchronization failed for trigger object', LOG_WARNING);
		}

		return $result;
	}

	/**
	 * Synchronize linked customer invoice PDFs after a payment is recorded.
	 *
	 * @param Paiement $payment Customer payment
	 * @return int Number of synchronized invoices
	 */
	private function syncCustomerPaymentInvoicePdfs($payment)
	{
		dol_include_once('/compta/facture/class/facture.class.php');

		$amounts = $this->getPaymentAmounts($payment);
		$count = 0;
		foreach ($amounts as $invoiceId => $amount) {
			if ((float) $amount == 0) {
				continue;
			}

			$invoice = new Facture($this->db);
			if ($invoice->fetch((int) $invoiceId) <= 0) {
				continue;
			}
			if (method_exists($invoice, 'fetch_lines')) {
				$invoice->fetch_lines();
			}

			$this->syncLinkedTargetPdfs($invoice, true);
			$count++;
		}

		return $count;
	}

	/**
	 * Synchronize linked supplier invoice PDFs after a supplier payment is recorded.
	 *
	 * @param PaiementFourn $payment Supplier payment
	 * @return int Number of synchronized invoices
	 */
	private function syncSupplierPaymentInvoicePdfs($payment)
	{
		dol_include_once('/fourn/class/fournisseur.facture.class.php');

		$amounts = $this->getPaymentAmounts($payment);
		$count = 0;
		foreach ($amounts as $invoiceId => $amount) {
			if ((float) $amount == 0) {
				continue;
			}

			$invoice = new FactureFournisseur($this->db);
			if ($invoice->fetch((int) $invoiceId) <= 0) {
				continue;
			}
			if (method_exists($invoice, 'fetch_lines')) {
				$invoice->fetch_lines();
			}

			$this->syncLinkedTargetPdfs($invoice, true);
			$count++;
		}

		return $count;
	}

	/**
	 * Return payment amounts indexed by invoice id.
	 *
	 * @param CommonObject $payment Payment object
	 * @return array
	 */
	private function getPaymentAmounts($payment)
	{
		if (!empty($payment->amounts) && is_array($payment->amounts)) {
			return $payment->amounts;
		}
		if (method_exists($payment, 'getAmounts')) {
			$amounts = $payment->getAmounts();
			if (is_array($amounts)) {
				return $amounts;
			}
		}

		return array();
	}

	/**
	 * Create mirrored supplier payments from a customer payment on linked customer invoices.
	 *
	 * @param Paiement $payment Customer payment
	 * @param User     $user    User
	 * @return int <0 if KO, number of created supplier payments otherwise
	 */
	private function syncSupplierPaymentFromCustomerPayment($payment, $user)
	{
		global $conf;

		if (!empty($GLOBALS['INTERENTITYDOCUMENTS_PAYMENT_SYNC_IN_PROGRESS'])) {
			return 0;
		}
		if (empty($payment->id)) {
			return 0;
		}
		$mirrorExists = $this->hasSupplierPaymentMirrorForCustomerPayment((int) $payment->id);
		if ($mirrorExists < 0) {
			return -1;
		}
		if ($mirrorExists > 0) {
			dol_syslog('interentitydocuments: supplier payment mirror already exists for customer payment '.$payment->id, LOG_DEBUG);
			return 0;
		}

		$amounts = $this->getPaymentAmounts($payment);
		if (empty($amounts)) {
			return 0;
		}

		dol_include_once('/fourn/class/fournisseur.facture.class.php');

		$groups = array();
		$linkedInvoiceFound = false;

		foreach ($amounts as $customerInvoiceId => $amount) {
			$customerInvoiceId = (int) $customerInvoiceId;
			$amount = price2num((float) $amount, 'MT');
			if ($customerInvoiceId <= 0 || $amount <= 0) {
				if ($amount < 0) {
					dol_syslog('interentitydocuments: negative customer payment amount ignored for invoice '.$customerInvoiceId, LOG_WARNING);
				}
				continue;
			}

			$supplierInvoiceId = $this->getSupplierInvoiceIdFromCustomerInvoiceId($customerInvoiceId);
			if ($supplierInvoiceId < 0) {
				return -1;
			}
			if ($supplierInvoiceId === 0) {
				continue;
			}

			$linkedInvoiceFound = true;

			$supplierInvoice = new FactureFournisseur($this->db);
			if ($supplierInvoice->fetch($supplierInvoiceId) <= 0) {
				$this->setError('InterentitySupplierPaymentMirrorMissingInvoice');
				return -1;
			}

			$remainingAmount = $this->getSupplierInvoiceRemainingAmount($supplierInvoice);
			if ($remainingAmount <= 0) {
				dol_syslog('interentitydocuments: linked supplier invoice '.$supplierInvoice->id.' has no remaining amount to pay', LOG_WARNING);
				continue;
			}

			$mirroredAmount = $amount;
			if ($mirroredAmount > $remainingAmount) {
				dol_syslog('interentitydocuments: customer payment amount capped from '.$mirroredAmount.' to '.$remainingAmount.' for supplier invoice '.$supplierInvoice->id, LOG_WARNING);
				$mirroredAmount = $remainingAmount;
			}
			$mirroredAmount = price2num($mirroredAmount, 'MT');
			if ($mirroredAmount <= 0) {
				continue;
			}

			$targetEntity = !empty($supplierInvoice->entity) ? (int) $supplierInvoice->entity : (int) $conf->entity;
			$targetSocid = !empty($supplierInvoice->socid) ? (int) $supplierInvoice->socid : 0;
			if ($targetSocid <= 0) {
				$this->setError('InterentitySupplierPaymentMirrorMissingThirdparty');
				return -1;
			}

			$groupKey = $targetEntity.'-'.$targetSocid;
			if (empty($groups[$groupKey])) {
				$groups[$groupKey] = array(
					'entity' => $targetEntity,
					'socid' => $targetSocid,
					'amounts' => array(),
					'multicurrency_amounts' => array(),
					'multicurrency_code' => array(),
					'multicurrency_tx' => array(),
				);
			}

			$invoiceId = (int) $supplierInvoice->id;
			if (empty($groups[$groupKey]['amounts'][$invoiceId])) {
				$groups[$groupKey]['amounts'][$invoiceId] = 0;
				$groups[$groupKey]['multicurrency_amounts'][$invoiceId] = 0;
			}

			$groups[$groupKey]['amounts'][$invoiceId] = price2num($groups[$groupKey]['amounts'][$invoiceId] + $mirroredAmount, 'MT');
			$groups[$groupKey]['multicurrency_amounts'][$invoiceId] = price2num($groups[$groupKey]['multicurrency_amounts'][$invoiceId] + $mirroredAmount, 'MT');
			$groups[$groupKey]['multicurrency_code'][$invoiceId] = !empty($supplierInvoice->multicurrency_code) ? $supplierInvoice->multicurrency_code : $conf->currency;
			$groups[$groupKey]['multicurrency_tx'][$invoiceId] = !empty($supplierInvoice->multicurrency_tx) ? (float) $supplierInvoice->multicurrency_tx : 1;
		}

		if (!$linkedInvoiceFound || empty($groups)) {
			return 0;
		}

		$created = 0;
		$previousGuard = !empty($GLOBALS['INTERENTITYDOCUMENTS_PAYMENT_SYNC_IN_PROGRESS']);
		$GLOBALS['INTERENTITYDOCUMENTS_PAYMENT_SYNC_IN_PROGRESS'] = 1;

		try {
			foreach ($groups as $group) {
				$result = $this->createSupplierPaymentMirrorGroup($payment, $group, $user);
				if ($result < 0) {
					return -1;
				}
				$created++;
			}
		} finally {
			if ($previousGuard) {
				$GLOBALS['INTERENTITYDOCUMENTS_PAYMENT_SYNC_IN_PROGRESS'] = 1;
			} else {
				unset($GLOBALS['INTERENTITYDOCUMENTS_PAYMENT_SYNC_IN_PROGRESS']);
			}
		}

		return $created;
	}

	/**
	 * Return linked supplier invoice id for a customer invoice.
	 *
	 * @param int $customerInvoiceId Customer invoice id
	 * @return int <0 on SQL error, 0 if no link, supplier invoice id otherwise
	 */
	private function getSupplierInvoiceIdFromCustomerInvoiceId($customerInvoiceId)
	{
		$sourceTypes = array('facture', 'invoice');
		$targetTypes = array('invoice_supplier', 'supplier_invoice', 'facture_fournisseur', 'facture_fourn');

		$sql = 'SELECT fk_target';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'element_element';
		$sql .= ' WHERE fk_source = '.((int) $customerInvoiceId);
		$sql .= ' AND sourcetype IN ('.$this->getSqlQuotedList($sourceTypes).')';
		$sql .= ' AND targettype IN ('.$this->getSqlQuotedList($targetTypes).')';
		$sql .= ' ORDER BY rowid DESC';
		$sql .= ' LIMIT 1';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}
		if ($this->db->num_rows($resql) <= 0) {
			return 0;
		}

		$obj = $this->db->fetch_object($resql);
		return !empty($obj->fk_target) ? (int) $obj->fk_target : 0;
	}

	/**
	 * Create a supplier payment for one target entity / supplier third party group.
	 *
	 * @param Paiement $customerPayment Customer payment
	 * @param array    $group           Payment group
	 * @param User     $user            User
	 * @return int <0 if KO, >0 if OK
	 */
	private function createSupplierPaymentMirrorGroup($customerPayment, array $group, $user)
	{
		global $conf, $langs;

		dol_include_once('/fourn/class/paiementfourn.class.php');

		$langs->load('interentitydocuments@interentitydocuments');

		$previousEntity = (int) $conf->entity;
		$targetEntity = (int) $group['entity'];
		$conf->entity = $targetEntity;

		try {
			$bankAccountId = $this->getSupplierPaymentBankAccountIdForEntity($targetEntity);

			$supplierPayment = new PaiementFourn($this->db);
			$supplierPayment->datepaye = $this->getPaymentDate($customerPayment);
			$supplierPayment->paiementid = $this->getPaymentModeId($customerPayment);
			$supplierPayment->fk_paiement = $supplierPayment->paiementid;
			$supplierPayment->num_payment = $this->getPaymentNumber($customerPayment);
			$supplierPayment->num_paiement = $supplierPayment->num_payment;
			$supplierPayment->amounts = $group['amounts'];
			$supplierPayment->multicurrency_amounts = $group['multicurrency_amounts'];
			$supplierPayment->multicurrency_code = $group['multicurrency_code'];
			$supplierPayment->multicurrency_tx = $group['multicurrency_tx'];
			$supplierPayment->note_private = $langs->trans('InterentitySupplierPaymentMirrorNote', $this->getPaymentDisplayRef($customerPayment));
			if ($bankAccountId > 0) {
				$supplierPayment->fk_account = $bankAccountId;
			}

			$result = $supplierPayment->create($user, 1);
			if ($result <= 0) {
				$this->setError(!empty($supplierPayment->error) ? $supplierPayment->error : 'InterentitySupplierPaymentMirrorFailed');
				if (!empty($supplierPayment->errors) && is_array($supplierPayment->errors)) {
					$this->errors = array_merge($this->errors, $supplierPayment->errors);
				}
				return -1;
			}

			$linkResult = $this->linkSupplierPaymentMirror((int) $customerPayment->id, (int) $supplierPayment->id);
			if ($linkResult < 0) {
				return -1;
			}

			$bankEnabled = function_exists('isModEnabled') ? isModEnabled('bank') : !empty($conf->bank->enabled);
			if ($bankAccountId > 0 && $bankEnabled) {
				$bankResult = $supplierPayment->addPaymentToBank($user, 'payment_supplier', '(SupplierInvoicePayment)', $bankAccountId, '', '');
				if ($bankResult < 0) {
					dol_syslog('interentitydocuments: failed to add mirrored supplier payment '.$supplierPayment->id.' to bank account '.$bankAccountId.': '.$supplierPayment->error, LOG_WARNING);
				}
			}
		} finally {
			$conf->entity = $previousEntity;
		}

		return 1;
	}

	/**
	 * Return remaining amount to pay on a supplier invoice.
	 *
	 * @param FactureFournisseur $invoice Supplier invoice
	 * @return float
	 */
	private function getSupplierInvoiceRemainingAmount($invoice)
	{
		$paid = method_exists($invoice, 'getSommePaiement') ? (float) $invoice->getSommePaiement() : 0;
		$creditNotes = method_exists($invoice, 'getSumCreditNotesUsed') ? (float) $invoice->getSumCreditNotesUsed() : 0;
		$deposits = method_exists($invoice, 'getSumDepositsUsed') ? (float) $invoice->getSumDepositsUsed() : 0;
		$remaining = price2num((float) $invoice->total_ttc - $paid - $creditNotes - $deposits, 'MT');

		return $remaining > 0 ? $remaining : 0;
	}

	/**
	 * Check if a customer payment already has a mirrored supplier payment link.
	 *
	 * @param int $customerPaymentId Customer payment id
	 * @return int <0 on SQL error, 0 if no mirror, >0 if mirror exists
	 */
	private function hasSupplierPaymentMirrorForCustomerPayment($customerPaymentId)
	{
		$sql = 'SELECT rowid';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'element_element';
		$sql .= ' WHERE fk_source = '.((int) $customerPaymentId);
		$sql .= " AND sourcetype = 'payment'";
		$sql .= " AND targettype = 'payment_supplier'";
		$sql .= ' LIMIT 1';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		return $this->db->num_rows($resql) > 0 ? 1 : 0;
	}

	/**
	 * Link a customer payment to its mirrored supplier payment.
	 *
	 * @param int $customerPaymentId Customer payment id
	 * @param int $supplierPaymentId Supplier payment id
	 * @return int <0 if KO, >0 if OK
	 */
	private function linkSupplierPaymentMirror($customerPaymentId, $supplierPaymentId)
	{
		$sql = 'SELECT rowid';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'element_element';
		$sql .= ' WHERE fk_source = '.((int) $customerPaymentId);
		$sql .= " AND sourcetype = 'payment'";
		$sql .= ' AND fk_target = '.((int) $supplierPaymentId);
		$sql .= " AND targettype = 'payment_supplier'";
		$sql .= ' LIMIT 1';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}
		if ($this->db->num_rows($resql) > 0) {
			return 1;
		}

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'element_element (fk_source, sourcetype, fk_target, targettype)';
		$sql .= ' VALUES ('.((int) $customerPaymentId).", 'payment', ".((int) $supplierPaymentId).", 'payment_supplier')";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		return 1;
	}

	/**
	 * Create mirrored customer payments from a supplier payment on linked supplier invoices.
	 *
	 * @param PaiementFourn $payment Supplier payment
	 * @param User          $user    User
	 * @return int <0 if KO, number of created customer payments otherwise
	 */
	private function syncCustomerPaymentFromSupplierPayment($payment, $user)
	{
		global $conf;

		if (!empty($GLOBALS['INTERENTITYDOCUMENTS_PAYMENT_SYNC_IN_PROGRESS'])) {
			return 0;
		}
		if (empty($payment->id)) {
			return 0;
		}
		$mirrorExists = $this->hasCustomerPaymentMirrorForSupplierPayment((int) $payment->id);
		if ($mirrorExists < 0) {
			return -1;
		}
		if ($mirrorExists > 0) {
			dol_syslog('interentitydocuments: customer payment mirror already exists for supplier payment '.$payment->id, LOG_DEBUG);
			return 0;
		}

		$amounts = $this->getPaymentAmounts($payment);
		if (empty($amounts)) {
			return 0;
		}

		dol_include_once('/compta/facture/class/facture.class.php');

		$groups = array();
		$linkedInvoiceFound = false;

		foreach ($amounts as $supplierInvoiceId => $amount) {
			$supplierInvoiceId = (int) $supplierInvoiceId;
			$amount = price2num((float) $amount, 'MT');
			if ($supplierInvoiceId <= 0 || $amount <= 0) {
				if ($amount < 0) {
					dol_syslog('interentitydocuments: negative supplier payment amount ignored for invoice '.$supplierInvoiceId, LOG_WARNING);
				}
				continue;
			}

			$customerInvoiceId = $this->getCustomerInvoiceIdFromSupplierInvoiceId($supplierInvoiceId);
			if ($customerInvoiceId < 0) {
				return -1;
			}
			if ($customerInvoiceId === 0) {
				continue;
			}

			$linkedInvoiceFound = true;

			$customerInvoice = new Facture($this->db);
			if ($customerInvoice->fetch($customerInvoiceId) <= 0) {
				$this->setError('InterentityCustomerPaymentMirrorMissingInvoice');
				return -1;
			}

			$remainingAmount = $this->getCustomerInvoiceRemainingAmount($customerInvoice);
			if ($remainingAmount <= 0) {
				dol_syslog('interentitydocuments: linked customer invoice '.$customerInvoice->id.' has no remaining amount to pay', LOG_WARNING);
				continue;
			}

			$mirroredAmount = $amount;
			if ($mirroredAmount > $remainingAmount) {
				dol_syslog('interentitydocuments: supplier payment amount capped from '.$mirroredAmount.' to '.$remainingAmount.' for customer invoice '.$customerInvoice->id, LOG_WARNING);
				$mirroredAmount = $remainingAmount;
			}
			$mirroredAmount = price2num($mirroredAmount, 'MT');
			if ($mirroredAmount <= 0) {
				continue;
			}

			$targetEntity = !empty($customerInvoice->entity) ? (int) $customerInvoice->entity : (int) $conf->entity;
			$targetSocid = !empty($customerInvoice->socid) ? (int) $customerInvoice->socid : 0;
			if ($targetSocid <= 0) {
				$this->setError('InterentityCustomerPaymentMirrorMissingThirdparty');
				return -1;
			}

			$groupKey = $targetEntity.'-'.$targetSocid;
			if (empty($groups[$groupKey])) {
				$groups[$groupKey] = array(
					'entity' => $targetEntity,
					'socid' => $targetSocid,
					'amounts' => array(),
					'multicurrency_amounts' => array(),
					'multicurrency_code' => array(),
					'multicurrency_tx' => array(),
				);
			}

			$invoiceId = (int) $customerInvoice->id;
			if (empty($groups[$groupKey]['amounts'][$invoiceId])) {
				$groups[$groupKey]['amounts'][$invoiceId] = 0;
				$groups[$groupKey]['multicurrency_amounts'][$invoiceId] = 0;
			}

			$groups[$groupKey]['amounts'][$invoiceId] = price2num($groups[$groupKey]['amounts'][$invoiceId] + $mirroredAmount, 'MT');
			$groups[$groupKey]['multicurrency_amounts'][$invoiceId] = price2num($groups[$groupKey]['multicurrency_amounts'][$invoiceId] + $mirroredAmount, 'MT');
			$groups[$groupKey]['multicurrency_code'][$invoiceId] = !empty($customerInvoice->multicurrency_code) ? $customerInvoice->multicurrency_code : $conf->currency;
			$groups[$groupKey]['multicurrency_tx'][$invoiceId] = !empty($customerInvoice->multicurrency_tx) ? (float) $customerInvoice->multicurrency_tx : 1;
		}

		if (!$linkedInvoiceFound || empty($groups)) {
			return 0;
		}

		$created = 0;
		$previousGuard = !empty($GLOBALS['INTERENTITYDOCUMENTS_PAYMENT_SYNC_IN_PROGRESS']);
		$GLOBALS['INTERENTITYDOCUMENTS_PAYMENT_SYNC_IN_PROGRESS'] = 1;

		try {
			foreach ($groups as $group) {
				$result = $this->createCustomerPaymentMirrorGroup($payment, $group, $user);
				if ($result < 0) {
					return -1;
				}
				$created++;
			}
		} finally {
			if ($previousGuard) {
				$GLOBALS['INTERENTITYDOCUMENTS_PAYMENT_SYNC_IN_PROGRESS'] = 1;
			} else {
				unset($GLOBALS['INTERENTITYDOCUMENTS_PAYMENT_SYNC_IN_PROGRESS']);
			}
		}

		return $created;
	}

	/**
	 * Return linked source customer invoice id for a supplier invoice.
	 *
	 * @param int $supplierInvoiceId Supplier invoice id
	 * @return int <0 on SQL error, 0 if no link, customer invoice id otherwise
	 */
	private function getCustomerInvoiceIdFromSupplierInvoiceId($supplierInvoiceId)
	{
		$sourceTypes = array('facture', 'invoice');
		$targetTypes = array('invoice_supplier', 'supplier_invoice', 'facture_fournisseur', 'facture_fourn');

		$sql = 'SELECT fk_source';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'element_element';
		$sql .= ' WHERE fk_target = '.((int) $supplierInvoiceId);
		$sql .= ' AND sourcetype IN ('.$this->getSqlQuotedList($sourceTypes).')';
		$sql .= ' AND targettype IN ('.$this->getSqlQuotedList($targetTypes).')';
		$sql .= ' ORDER BY rowid DESC';
		$sql .= ' LIMIT 1';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}
		if ($this->db->num_rows($resql) <= 0) {
			return 0;
		}

		$obj = $this->db->fetch_object($resql);
		return !empty($obj->fk_source) ? (int) $obj->fk_source : 0;
	}

	/**
	 * Create a customer payment for one source entity / third party group.
	 *
	 * @param PaiementFourn $supplierPayment Supplier payment
	 * @param array         $group           Payment group
	 * @param User          $user            User
	 * @return int <0 if KO, >0 if OK
	 */
	private function createCustomerPaymentMirrorGroup($supplierPayment, array $group, $user)
	{
		global $conf, $langs;

		dol_include_once('/compta/paiement/class/paiement.class.php');

		$langs->load('interentitydocuments@interentitydocuments');

		$previousEntity = (int) $conf->entity;
		$targetEntity = (int) $group['entity'];
		$conf->entity = $targetEntity;

		try {
			$bankAccountId = $this->getCustomerPaymentBankAccountIdForEntity($targetEntity);

			$customerPayment = new Paiement($this->db);
			$customerPayment->datepaye = $this->getPaymentDate($supplierPayment);
			$customerPayment->paiementid = $this->getPaymentModeId($supplierPayment);
			$customerPayment->num_payment = $this->getPaymentNumber($supplierPayment);
			$customerPayment->num_paiement = $customerPayment->num_payment;
			$customerPayment->amounts = $group['amounts'];
			$customerPayment->multicurrency_amounts = $group['multicurrency_amounts'];
			$customerPayment->multicurrency_code = $group['multicurrency_code'];
			$customerPayment->multicurrency_tx = $group['multicurrency_tx'];
			$customerPayment->note_private = $langs->trans('InterentityCustomerPaymentMirrorNote', $this->getPaymentDisplayRef($supplierPayment));
			if ($bankAccountId > 0) {
				$customerPayment->fk_account = $bankAccountId;
			}

			$result = $customerPayment->create($user, 1);
			if ($result <= 0) {
				$this->setError(!empty($customerPayment->error) ? $customerPayment->error : 'InterentityCustomerPaymentMirrorFailed');
				if (!empty($customerPayment->errors) && is_array($customerPayment->errors)) {
					$this->errors = array_merge($this->errors, $customerPayment->errors);
				}
				return -1;
			}

			$linkResult = $this->linkCustomerPaymentMirror((int) $supplierPayment->id, (int) $customerPayment->id);
			if ($linkResult < 0) {
				return -1;
			}

			$bankEnabled = function_exists('isModEnabled') ? isModEnabled('bank') : !empty($conf->bank->enabled);
			if ($bankAccountId > 0 && $bankEnabled) {
				$bankResult = $customerPayment->addPaymentToBank($user, 'payment', '(CustomerInvoicePayment)', $bankAccountId, '', '');
				if ($bankResult < 0) {
					dol_syslog('interentitydocuments: failed to add mirrored customer payment '.$customerPayment->id.' to bank account '.$bankAccountId.': '.$customerPayment->error, LOG_WARNING);
				}
			}
		} finally {
			$conf->entity = $previousEntity;
		}

		return 1;
	}

	/**
	 * Return remaining amount to pay on a customer invoice.
	 *
	 * @param Facture $invoice Customer invoice
	 * @return float
	 */
	private function getCustomerInvoiceRemainingAmount($invoice)
	{
		$paid = method_exists($invoice, 'getSommePaiement') ? (float) $invoice->getSommePaiement() : 0;
		$creditNotes = method_exists($invoice, 'getSumCreditNotesUsed') ? (float) $invoice->getSumCreditNotesUsed() : 0;
		$deposits = method_exists($invoice, 'getSumDepositsUsed') ? (float) $invoice->getSumDepositsUsed() : 0;
		$remaining = price2num((float) $invoice->total_ttc - $paid - $creditNotes - $deposits, 'MT');

		return $remaining > 0 ? $remaining : 0;
	}

	/**
	 * Check if a supplier payment already has a mirrored customer payment link.
	 *
	 * @param int $supplierPaymentId Supplier payment id
	 * @return int <0 on SQL error, 0 if no mirror, >0 if mirror exists
	 */
	private function hasCustomerPaymentMirrorForSupplierPayment($supplierPaymentId)
	{
		$sql = 'SELECT rowid';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'element_element';
		$sql .= ' WHERE fk_source = '.((int) $supplierPaymentId);
		$sql .= " AND sourcetype = 'payment_supplier'";
		$sql .= " AND targettype = 'payment'";
		$sql .= ' LIMIT 1';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		return $this->db->num_rows($resql) > 0 ? 1 : 0;
	}

	/**
	 * Link a supplier payment to its mirrored customer payment.
	 *
	 * @param int $supplierPaymentId Supplier payment id
	 * @param int $customerPaymentId Customer payment id
	 * @return int <0 if KO, >0 if OK
	 */
	private function linkCustomerPaymentMirror($supplierPaymentId, $customerPaymentId)
	{
		$sql = 'SELECT rowid';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'element_element';
		$sql .= ' WHERE fk_source = '.((int) $supplierPaymentId);
		$sql .= " AND sourcetype = 'payment_supplier'";
		$sql .= ' AND fk_target = '.((int) $customerPaymentId);
		$sql .= " AND targettype = 'payment'";
		$sql .= ' LIMIT 1';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}
		if ($this->db->num_rows($resql) > 0) {
			return 1;
		}

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'element_element (fk_source, sourcetype, fk_target, targettype)';
		$sql .= ' VALUES ('.((int) $supplierPaymentId).", 'payment_supplier', ".((int) $customerPaymentId).", 'payment')";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		return 1;
	}

	/**
	 * Return configured customer payment bank account for an entity.
	 *
	 * @param int $entity Entity id
	 * @return int
	 */
	private function getCustomerPaymentBankAccountIdForEntity($entity)
	{
		return $this->getIntegerConstantForEntity('OFSOM_CUSTOMER_PAYMENT_BANK_ACCOUNT_ID', $entity);
	}

	/**
	 * Return configured supplier payment bank account for an entity.
	 *
	 * @param int $entity Entity id
	 * @return int
	 */
	private function getSupplierPaymentBankAccountIdForEntity($entity)
	{
		return $this->getIntegerConstantForEntity('OFSOM_SUPPLIER_PAYMENT_BANK_ACCOUNT_ID', $entity);
	}

	/**
	 * Return an integer constant value for an entity.
	 *
	 * @param string $name   Constant name
	 * @param int    $entity Entity id
	 * @return int
	 */
	private function getIntegerConstantForEntity($name, $entity)
	{
		$sql = 'SELECT value';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'const';
		$sql .= " WHERE name = '".$this->db->escape($name)."'";
		$sql .= ' AND entity = '.((int) $entity);
		$sql .= ' LIMIT 1';

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog('interentitydocuments: unable to read constant '.$name.' for entity '.$entity.': '.$this->db->lasterror(), LOG_WARNING);
			return 0;
		}
		if ($this->db->num_rows($resql) <= 0) {
			return 0;
		}

		$obj = $this->db->fetch_object($resql);
		return !empty($obj->value) ? (int) $obj->value : 0;
	}

	/**
	 * Return payment date from a payment object.
	 *
	 * @param CommonObject $payment Payment
	 * @return int
	 */
	private function getPaymentDate($payment)
	{
		if (!empty($payment->datepaye)) {
			return $payment->datepaye;
		}
		if (!empty($payment->date_payment)) {
			return $payment->date_payment;
		}
		if (!empty($payment->date)) {
			return $payment->date;
		}

		return dol_now();
	}

	/**
	 * Return payment mode id from a payment object.
	 *
	 * @param CommonObject $payment Payment
	 * @return int
	 */
	private function getPaymentModeId($payment)
	{
		if (!empty($payment->paiementid)) {
			return (int) $payment->paiementid;
		}
		if (!empty($payment->fk_paiement)) {
			return (int) $payment->fk_paiement;
		}

		return 0;
	}

	/**
	 * Return payment number from a payment object.
	 *
	 * @param CommonObject $payment Payment
	 * @return string
	 */
	private function getPaymentNumber($payment)
	{
		if (!empty($payment->num_payment)) {
			return $payment->num_payment;
		}
		if (!empty($payment->num_paiement)) {
			return $payment->num_paiement;
		}
		if (!empty($payment->ref)) {
			return $payment->ref;
		}

		return !empty($payment->id) ? 'PAY-'.$payment->id : '';
	}

	/**
	 * Return a readable payment reference for logs and notes.
	 *
	 * @param CommonObject $payment Payment
	 * @return string
	 */
	private function getPaymentDisplayRef($payment)
	{
		$ref = $this->getPaymentNumber($payment);
		if (!empty($ref)) {
			return $ref;
		}

		return !empty($payment->id) ? '#'.$payment->id : '';
	}

	/**
	 * Quote a string list for a SQL IN condition.
	 *
	 * @param array $values Values
	 * @return string
	 */
	private function getSqlQuotedList(array $values)
	{
		$quoted = array();
		foreach ($values as $value) {
			$quoted[] = "'".$this->db->escape($value)."'";
		}

		return implode(',', $quoted);
	}

	/**
	 * Crée une commande fournisseur dans l'entité de destination à partir d'une commande client
	 * @param Commande $object
	 * @return int <0 if KO, 0 if no action, >0 if OK
	 */
	private function _cloneSupplierOrderFromCustomerOrder($object)
	{
		global $conf;

		dol_include_once('/interentitydocuments/class/telink.class.php');

		$db =& $this->db;
		$sourceEntity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;

		$res = $db->query("SELECT fk_entity FROM " . MAIN_DB_PREFIX . "thirdparty_entity"
		                  . " WHERE entity=" . intval($sourceEntity)
		                  . " AND fk_soc=" . intval($object->socid)
		                  . ' AND fk_entity <> ' . intval($sourceEntity));
		if (!$res) {
			$this->setError('ErrorSQL');
			return -1;
		} elseif ($db->num_rows($res) === 0) {
			// Pas d'entité liée au tiers → rien à faire
			return 0;
		}
		$obj = $db->fetch_object($res);

		if ($obj->fk_entity > 0) {
			$TTELink = new TTELink();
			$res = $TTELink->cloneSupplierOrderFromCustomerOrder($object->id, $obj->fk_entity);
			if ($res < 0) {
				$this->setError($TTELink->error);
			}
			return $res;
		} else {
			$this->setError('MissingEntityLink');
			return -1;
		}
	}

	private function _cloneOrder($object)
	{

		global $conf, $langs;

		dol_include_once('/interentitydocuments/class/telink.class.php');

		$db =& $this->db;

		$res = $db->query("SELECT fk_entity FROM " . MAIN_DB_PREFIX . "thirdparty_entity"
		                  . " WHERE entity=" . $conf->entity
		                  . " AND fk_soc=" . $object->socid
		                  . ' AND fk_entity <> ' . $conf->entity);
		if (!$res) {
			// SQL ERROR, should not happen.
			$this->setError('ErrorSQL');
		} elseif ($db->num_rows($res) === 0) {
			// No entity linked to the third party → do nothing
			return 0;
		}
		$obj = $db->fetch_object($res);

		if ($obj->fk_entity > 0) {
			$TTELink = new TTELink();
			$res = $TTELink->cloneOrder($object->id, $obj->fk_entity);
			$this->setError($TTELink->error);
			return $res;
		} else {
			$this->setError('MissingEntityLink');
			return -1;
		}
	}

	/**
	 * Crée une facture fournisseur dans l'entité de destination à partir d'une facture client
	 * @param Facture $object
	 * @return int <0 if KO, 0 if no action, >0 if OK
	 */
	private function _cloneInvoice($object)
	{
		global $conf, $langs;

		dol_include_once('/interentitydocuments/class/telink.class.php');

		$db =& $this->db;

		$res = $db->query("SELECT fk_entity FROM " . MAIN_DB_PREFIX . "thirdparty_entity"
		                  . " WHERE entity=" . intval($conf->entity)
		                  . " AND fk_soc=" . intval($object->socid)
		                  . ' AND fk_entity <> ' . intval($conf->entity));
		if (!$res) {
			$this->setError('ErrorSQL');
			return -1;
		} elseif ($db->num_rows($res) === 0) {
			// Pas d'entité liée au tiers → rien à faire
			return 0;
		}
		$obj = $db->fetch_object($res);

		if ($obj->fk_entity > 0) {
			$TTELink = new TTELink();
			$res = $TTELink->cloneInvoice($object->id, $obj->fk_entity);
			if ($res < 0) {
				$this->setError($TTELink->error);
			}
			return $res;
		} else {
			$this->setError('MissingEntityLink');
			return -1;
		}
	}

	/**
	 * Passe la commande fournisseur à reçue (entité A) lors de la clôture de l'expédition (Entité courante)
	 * @param Expedition $shipment
	 * @return int|void
	 */
	public function receiveSupplierOrderFromShipment($shipment){
		global $user, $langs;
		$langs->load('interentitydocuments@interentitydocuments');
		dol_include_once('/interentitydocuments/class/telink.class.php');

		$TTELink = new TTELink();
		// Récupération de l'entité correspondant au tiers client de l'expédition,
		// si il y a bien un lien alors la reception de la commande fournisseur peut commencer
		// si ce n'est pas configuré alors on fait rien de particulier c'est juste qu'il n'y a pas de lien entre société et entité
		// et si il y a une erreur ben on la traite
		$customerId = $TTELink->getSocIdForEntityCustomerFromSupplierEntitySocId($shipment->socid, $shipment->entity);
		if($customerId > 0) {


			$shipment->fetchObjectLinked(null, '', null, '', 'OR', 1, 'sourcetype', 0);

			// la liste des commandes fournisseur pour lesquelles il faut mettre à jour le status
			$SupplierOrderToCheck = array();


			foreach ($shipment->lines as $line) {
				/** @var ExpeditionLigne $line */

				if(empty($line->fk_product)){
					// si pas de fk_product alors pas de stock géré
					// si pas de stock géré alors pas de stock géré...
					continue;
				}

				// Vérifier les elements elements pour voir si il y a un lien entre la ligne d'expedition et une commande fournisseur
				$line->fetchObjectLinked(null, 'order_supplier');
				$supplierOrderLinesLinked = $this->getLinkedSupplierOrderDetFromExpeditionDet($line);
				if($supplierOrderLinesLinked === false){
					$this->setError($this->db->error());
					return -1;
				}

				if(!empty($supplierOrderLinesLinked)){
					//  Si tel est le cas alors ne pas traiter la ligne car déjà traitée
					continue;
				}

				// les lignes d'expeditions sont liées à une ligne de commande donc il faut charger la ligne de commande
				$orderLine = $this->getOrderLineFromCache($line->fk_origin_line);
				if ($orderLine) {
					// maintenant il est possible d'avoir la commande liée
					$order = $this->getOrderFromCache($orderLine->fk_commande);
					if ($order) {
						// et enfin il devient possible de retrouver la commande fournisseur correspondante
						$supplierOrderId = $TTELink->getSupplierOrderIdFromOrder($order);
						if($supplierOrderId>0){
							$supplierOrder = $this->getSupplierOrderFromCache($supplierOrderId);
							if($supplierOrder) {

								// Si la commande est déja receptionné ou annulée alors il n'y a rien a faire
								if(in_array($supplierOrder->statut, array($supplierOrder::STATUS_RECEIVED_COMPLETELY,$supplierOrder::STATUS_CANCELED,$supplierOrder::STATUS_CANCELED_AFTER_ORDER))){
									continue;
								}

								$receptionWarehouseId = $this->getSupplierOrderReceptionWarehouseId($supplierOrder);
								if($receptionWarehouseId <= 0){
									$this->setError('ReceptionWarehouseNotDefined');
									return -1;
								}

//								// Mis en commentaire car au final je le fait ligne à ligne, mais ça peut être intéressant pour plus tard
//								// si l'expedition est lié à la commande fourn alors on part du principe qu'elle est receptionné car le lien element elements ne se fait que (si rien n'a changé) à la fin de la reception
//								if(!empty($shipment->linkedObjectsIds['order_supplier']) && in_array($supplierOrder->id, $shipment->linkedObjectsIds['order_supplier'])){
//									continue;
//								}

								// Marque cet commande fournisseur pour une mise à jour du statut
								$SupplierOrderToCheck[] = $supplierOrder->id;

								// Maintenant il faut faire les receptions des lignes sur la commande fournisseur
								if(empty($orderLine->array_options)){
									$orderLine->fetch_optionals();
								}

								if(!empty($orderLine->array_options['options_supplier_order_det_source'])){
									$fk_commandefourndet = intval($orderLine->array_options['options_supplier_order_det_source']);

									// TODO vérifier $fk_commandefourndet fait bien partie des lignes de $supplierOrder
								}
								else{
									$this->setError('LinkMissingBetweenSupplierOrderAndCustomerOrder');
									return -1;
								}


								if(!empty($line->detail_batch)){

									foreach ($line->detail_batch as $detail_batch) {
										/** @var ExpeditionLineBatch $detail_batch */

										$dDLC = $detail_batch->eatby;
										$dDLUO = $detail_batch->sellby;
										$lot = $detail_batch->batch;

										$movementComment = $langs->trans('ReceiveFromAutoWorkflowForSupplierShipment', $shipment->ref);

										$result = $supplierOrder->dispatchProduct($user, $line->fk_product, $line->qty, $receptionWarehouseId, $orderLine->subprice, $movementComment, $dDLC, $dDLUO, $lot, $fk_commandefourndet);
										if ($result < 0) {
											$this->setError($supplierOrder->error);
											return -1;
										}
									}
								}
								else{
									$movementComment = $langs->trans('ReceiveFromAutoWorkflowForSupplierShipment', $shipment->ref);

									$dDLC = $dDLUO = $lot = '';
									$result = $supplierOrder->dispatchProduct($user, $line->fk_product, $line->qty, $receptionWarehouseId, $orderLine->subprice, $movementComment, $dDLC, $dDLUO, $lot, $fk_commandefourndet);
									if ($result < 0) {
										$this->setError($supplierOrder->error);
										return -1;
									}
								}

								// créé un lien element element entre la ligne d'expedition et la ligne de commande fourn pour avoir une trace de la reception
								$line->add_object_linked('order_supplierdet', $fk_commandefourndet);

							}
							else{
								return -1;
							}
						}
						else{
							$this->setError('SupplierOrderNotFoundFromOrder');
							return -1;
						}
					} else {
						return -1;
					}
				} else {
					return -1;
				}
			}

			// Changement de status pour les commandes
			if(!empty($SupplierOrderToCheck)){
				$SupplierOrderToCheck = array_unique($SupplierOrderToCheck);
				foreach ($SupplierOrderToCheck as $supplierOrderId) {
					$supplierOrder = $this->getSupplierOrderFromCache($supplierOrderId);


					// Si la commande est déja receptionné ou annulée alors il n'y a rien a faire
					// ya normalement pas besoin de ce test car déjà fait plus haut mais je préfère un peu de prudence
					if(in_array($supplierOrder->statut, array($supplierOrder::STATUS_RECEIVED_COMPLETELY,$supplierOrder::STATUS_CANCELED,$supplierOrder::STATUS_CANCELED_AFTER_ORDER))){
						continue;
					}

					$supplierOrder->loadReceptions();

					// if some receptions done, then it must be partially received
					$receivePartially = !empty($supplierOrder->receptions);

					// test if completely received
					$receiveCompete = false;
					if($receivePartially) {
						$receiveCompete = true;
						foreach ($supplierOrder->lines as $line) {
							if (doubleval($line->qty) > doubleval($supplierOrder->receptions[$line->id])) {
								$receiveCompete = false;
								break;
							}
						}
					}

					if($receiveCompete) {
						$supplierOrder->setStatus($user, $supplierOrder::STATUS_RECEIVED_COMPLETELY);
					} elseif($receivePartially) {
						$supplierOrder->setStatus($user, $supplierOrder::STATUS_RECEIVED_PARTIALLY);
					}
				}
			}

			$this->clearOrderLineCache();
			$this->clearOrderCache();
		}
		elseif($customerId<0)
		{
			// quelque soit le problème il faut stopper et si possible gérer l'erreur
			$this->setError($TTELink->error);
			return -1;
		}
		else{
			// pas de correspondance d'entité pour ce client donc il ne faut rien faire
			return 0;
		}
	}


	/**
	 * @param $id
	 * @return OrderLine object
	 */
	public function getOrderLineFromCache($id){
		global $db;

		$res = $this->getElementFromCache('OrderLine', $id);
		if($res){
			return $res;
		}

		$this->setError('OrderLineNotFetched');
		return false;

	}

	/**
	 * @param $id
	 * @return CommandeFournisseur object
	 */
	public function getSupplierOrderFromCache($id){
		global $db;

		$res = $this->getElementFromCache('CommandeFournisseur', $id);
		if($res){
			return $res;
		}

		$this->setError('SupplierOrderNotFetched');
		return false;

	}

	/**
	 * clear Order Lines Cache
	 */
	function clearOrderLineCache(){
		$this->OrderLineCache = array();
	}

	/**
	 * @param $id
	 * @return Commande object
	 */
	public function getOrderFromCache($id){

		$res = $this->getElementFromCache('Commande', $id);
		if($res){
			return $res;
		}

		$this->setError('OrderNotFetched');
		return false;
	}

	/**
	 * clear Order Cache
	 */
	function clearOrderCache(){
		$this->CommandeCache = array();
		$this->CommandeFournisseurCache = array();
	}

	/**
	 * Charge et retourne l'entrepôt de réception auto d'une commande fournisseur.
	 *
	 * @param CommandeFournisseur $supplierOrder
	 * @return int
	 */
	private function getSupplierOrderReceptionWarehouseId($supplierOrder)
	{
		if (!is_object($supplierOrder)) {
			return 0;
		}

		if (method_exists($supplierOrder, 'fetch_optionals')
			&& (empty($supplierOrder->array_options) || !is_array($supplierOrder->array_options) || !array_key_exists('options_reception_warehouse', $supplierOrder->array_options))) {
			$supplierOrder->fetch_optionals();
		}

		if (empty($supplierOrder->array_options) || !is_array($supplierOrder->array_options)) {
			return 0;
		}

		return empty($supplierOrder->array_options['options_reception_warehouse']) ? 0 : (int) $supplierOrder->array_options['options_reception_warehouse'];
	}

	public function setError($error) {
		global $langs;

		if(empty($langs->tab_translate[$error])){
			$langs->load('interentitydocuments@interentitydocuments');
			$error = $langs->trans($error);
		}

		$this->error = $error;
		$this->errors[] = $this->error;
	}


	/**
	 * @param int $id
	 * @return object
	 */
	public function getElementFromCache($element, $id){
		global $db;

		$id = intval($id);

		$cacheVarName = $element.'Cache';


		if(empty($this->{$cacheVarName})){
			$this->{$cacheVarName} = array();
		}

		if(!empty($this->{$cacheVarName}[$id])){
			return $this->{$cacheVarName}[$id];
		}

		if($element == 'OrderLine') {
			if (!class_exists('OrderLine')) {
				require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
			}
			$object = new OrderLine($db);
		}
		elseif($element == 'Commande') {
			if (!class_exists('Commande')) {
				require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
			}
			$object = new Commande($db);
		}
		elseif($element == 'CommandeFournisseur') {
			if (!class_exists('CommandeFournisseur')) {
				require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.commande.class.php';
			}
			$object = new CommandeFournisseur($db);
		}
		else{
			$this->setError('IncompatibleElement');
			return false;
		}

		/** @var commonobject $object */

		if($object->fetch($id)>0){
			$this->{$cacheVarName}[$id] = $object;
			return $this->{$cacheVarName}[$id];
		}

		return false;
	}

	/**
	 * @param ExpeditionLigne $expeditionLigne
	 */
	public function getLinkedSupplierOrderDetFromExpeditionDet($expeditionLigne){

		// Links between objects are stored in table element_element
		$sql = 'SELECT rowid, fk_source, sourcetype, fk_target, targettype';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'element_element';
		$sql .= " WHERE  sourcetype = 'order_supplierdet'";
		$sql .= " AND fk_target = ".$expeditionLigne->id." AND targettype = '".$this->db->escape($expeditionLigne->element)."'";

		// TODO : return $this->db->getRows($sql); when this module will be min 12.0 compatible

		$res = $this->db->query($sql);
		if ($res)
		{
			$results = array();
			if ($this->db->num_rows($res) > 0) {
				while ($obj = $this->db->fetch_object($res)) {
					$results[] = $obj;
				}
			}
			return $results;
		}

		return false;
	}

}
