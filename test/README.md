# Vérification des synchronisations inter-entités

Vérification du 23 octobre 2026, PHP CLI 8.5.7. Les scénarios isolés remplacent les objets Dolibarr, la configuration, les utilisateurs, les permissions et les réponses SQL par des doublures. Ils n’ouvrent aucune connexion et ne créent ni objet ERP, ni compte, ni paiement réel.

```bash
php test/interentity_synchronization.php /chemin/vers/dolibarr/htdocs
```

Les 43 scénarios couvrent les entrées `ORDER_VALIDATE`, `ORDER_SUPPLIER_VALIDATE`, `BILL_VALIDATE`, `PAYMENT_CUSTOMER_CREATE` et `PAYMENT_SUPPLIER_CREATE` : acteur source sans accès à la destination, utilisateur d’exécution autorisé, droits absents, compte absent/inactif/externe, module désactivé, refus de suppression d’une contrepartie existante, erreurs SQL et natives, banque, garde anti-boucle et restauration du contexte après exception. Le chargement des droits et le contexte de destination sont simulés ; ces tests ne valident pas le gestionnaire de triggers ou les transactions d’une instance complète.

La régression de validation des commandes reste exécutable séparément :

```bash
php test/order_validation.php /chemin/vers/dolibarr/htdocs
```

## Analyse statique

La configuration `test/phpstan.php` cible PHP 8.0 et le niveau 10. Elle analyse les fichiers de production touchés en découvrant les classes et bibliothèques d’un checkout natif distinct. `DOL_VERSION` reste dynamique pour analyser les branches de compatibilité. Le bootstrap définit uniquement des constantes d’analyse ; il ne charge pas l’ERP. Les déclarations de types Multicompany fournies par le core ne prouvent pas la compatibilité avec une installation du module tiers.

```bash
DOLIBARR_DOCUMENT_ROOT=/chemin/vers/dolibarr/htdocs php /chemin/vers/phpstan.phar analyse -c test/phpstan.php --memory-limit=1G
```

Exécution avec PHPStan 2.3.1 et les sources Dolibarr `029a92fb538e90db684dec8b6ba80ee5a6638efd` (révision dérivée de 24.0.1). L’analyse complète reste en échec sur des diagnostics antérieurs ; aucun baseline, ignore ou abaissement de niveau n’est ajouté. La comparaison est réalisée avec les mêmes sources natives et paramètres, contre les fichiers de la PR au commit `5dcf750cef863b9eb1abfc5d8566a284ed0ac3f4`. Résultat sur le patch final : 687 diagnostics antérieurs subsistent, contre 942 sur la base analysée ; aucun diagnostic supplémentaire dans la comparaison, aucun diagnostic dans la nouvelle classe de contexte. Ces nombres ne sont pas directement comparables aux anciens comptes rendus utilisant une autre configuration.

## Lecture des contrats natifs

Les vérifications ci-dessous portent sur la présence et les contrats de `Conf::setValues()`, `User::fetch()`, `User::loadRights()`, `Societe::setMysoc()` et `Form::select_dolusers()`. Elles ne constituent pas des essais métier.

| Dolibarr | Sources examinées | Nature | Essai d’instance |
|---|---|---|---|
| 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` | Lecture des sources | Non exécuté |
| 21.0.0 | `fd970b582a4d8c5779a2958a4e9f4fce225cf085` | Lecture des sources | Non exécuté |
| 22.0.0 | `49b9a6d19f3deb6d410c0e9b3310e95be4ea7710` | Lecture des sources | Non exécuté |
| 23.0.0 | `57a1f05d490a7a80944a8232e9c613e6556d2704` | Lecture des sources | Non exécuté |
| 24.0.0 | `769c7db907099643558e77d7002c109cfda919e5` | Lecture des sources | Non exécuté |
| 25.0.0-alpha | `31627071c3d2dd890322088ebba9917515de7ac4` (`develop`) | Lecture des sources, préversion | Non exécuté |

Le filtre `morefilter` du sélecteur d’utilisateurs est SQL en v20 et USF à partir de v21. Le filtre fixe `(u.fk_soc:is:NULL)` a également été exécuté avec le compilateur USF natif de la révision locale ci-dessus, sous PHP 8.5.7, avec une doublure de l’échappement DB : résultat `AND ((u.fk_soc IS NULL))`. Aucun SQL n’a été exécuté.

Sources primaires : [configuration native v20](https://github.com/Dolibarr/dolibarr/blob/697bf01970740a3339cd99cf055b4428fc5e051c/htdocs/core/class/conf.class.php), [utilisateur natif v20](https://github.com/Dolibarr/dolibarr/blob/697bf01970740a3339cd99cf055b4428fc5e051c/htdocs/user/class/user.class.php), [sélecteurs natifs v21](https://github.com/Dolibarr/dolibarr/blob/fd970b582a4d8c5779a2958a4e9f4fce225cf085/htdocs/core/class/html.form.class.php), [changement de contexte dans le cron natif](https://github.com/Dolibarr/dolibarr/blob/029a92fb538e90db684dec8b6ba80ee5a6638efd/scripts/cron/cron_run_jobs.php).

## Recette sur une instance de test

- Configurer les correspondances de tiers et un utilisateur d’exécution dans chaque destination ; vérifier l’absence de changement des droits du compte source et la conservation du réglage après réactivation du module.
- Avec un utilisateur limité à A, valider les commandes clients/fournisseurs et les factures clients concernées ; vérifier l’auteur, l’entité, les lignes, les liens et la finalisation des PDF/ECM. Aucune création de facture client depuis une facture fournisseur n’est ajoutée.
- Enregistrer des règlements dans les deux sens, y compris partiels et répartis entre plusieurs destinations. Contrôler les montants, les factures soldées, le journal bancaire et l’absence de doublons après rejeu.
- Retirer un droit à l’utilisateur d’exécution, le désactiver ou provoquer une erreur bancaire : vérifier le refus, le retour complet et le rollback final de l’opération source. Les simulations ne prouvent pas ce rollback réel.
- Conserver les règles d’approbation des autres modules. Le message d’approbation de la capture correspond à la clé `LmdbSupplierOrderLimitNativePermissionMissing` du module `lmdbsupplierorderlimit` ; son déclenchement exact sur l’instance reste à reproduire.
- Vérifier le sélecteur et son enregistrement avec/sans token sur les versions concernées, les partages Multicompany réels et les autres modules actifs. Confirmer que le serveur sert le nouveau commit avant de conclure.

Aucun déploiement, test navigateur, email ou règlement réel n’a été effectué pendant cette intervention. La matrice d’exécution Dolibarr v20–v25/PHP et la compatibilité avec la version Multicompany installée restent à valider. Aucun succès CI n’est revendiqué.
