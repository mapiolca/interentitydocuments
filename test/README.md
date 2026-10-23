# Vérification des synchronisations inter-entités

Vérification du 23 octobre 2026, PHP CLI 8.5.7. Les scénarios isolés remplacent les objets Dolibarr, la configuration, les utilisateurs, les permissions et les réponses SQL par des doublures. Ils n’ouvrent aucune connexion et ne créent ni objet ERP, ni compte, ni paiement réel.

```bash
php test/interentity_synchronization.php /chemin/vers/dolibarr/htdocs
```

Les 58 scénarios couvrent les entrées `ORDER_VALIDATE`, `ORDER_SUPPLIER_VALIDATE`, `ORDER_SUPPLIER_SUBMIT`, `BILL_VALIDATE`, `PAYMENT_CUSTOMER_CREATE` et `PAYMENT_SUPPLIER_CREATE` : initiateur sans accès ni droits de création manuelle dans la destination, ancien réglage d’acteur ignoré, identité du paramètre de trigger conservée même si l’utilisateur global diffère, droits source ordinaires/avancés, utilisateur absent/inactif/externe, module désactivé, refus natifs d’approbation et de suppression, erreurs SQL/natives/bancaires, garde anti-boucle et restauration du contexte. Le chargement des droits et le contexte de destination sont simulés ; ces tests ne valident pas le gestionnaire de triggers ou les transactions d’une instance complète.

Avant modification du code de production (`1a50c7ef673ba631d0d2195b5a38f73c21bc8ba1`), les 49 premiers scénarios adaptés au comportement demandé produisaient 34 échecs, notamment l’identité de l’auteur et la création sans compte de destination. Les neuf scénarios supplémentaires vérifient les droits avancés, l’envoi fournisseur et l’identité du paramètre de trigger. Tous passent après correction.

La régression de validation des commandes reste exécutable séparément :

```bash
php test/order_validation.php /chemin/vers/dolibarr/htdocs
```

## Analyse statique

La configuration `test/phpstan.php` cible PHP 8.0 et le niveau 10. Elle analyse les fichiers de production touchés en découvrant les classes et bibliothèques d’un checkout natif distinct. `DOL_VERSION` reste dynamique pour analyser les branches de compatibilité. Le bootstrap définit uniquement des constantes d’analyse ; il ne charge pas l’ERP. Les déclarations de types Multicompany fournies par le core ne prouvent pas la compatibilité avec une installation du module tiers.

```bash
DOLIBARR_DOCUMENT_ROOT=/chemin/vers/dolibarr/htdocs php /chemin/vers/phpstan.phar analyse -c test/phpstan.php --memory-limit=1G
```

Exécution avec PHPStan 2.3.1 et les sources Dolibarr `029a92fb538e90db684dec8b6ba80ee5a6638efd` (révision dérivée de 24.0.1). L’analyse complète reste en échec sur 685 diagnostics antérieurs (687 sur l’état précédent) ; aucun baseline, ignore ou abaissement de niveau n’est ajouté. La comparaison avec les mêmes sources natives et paramètres, contre l’état précédent de la PR (`1a50c7ef673ba631d0d2195b5a38f73c21bc8ba1`), ne relève aucun diagnostic supplémentaire. Aucun diagnostic dans la classe de contexte. Ces nombres ne sont pas directement comparables aux comptes rendus utilisant une autre configuration.

## Lecture des contrats natifs

Les vérifications ci-dessous portent sur la présence et les contrats de `Conf::setValues()`, `User::clearrights()`, `User::loadRights()`, `User::hasRight()` et `Societe::setMysoc()`, ainsi que les permissions source des fiches natives commandes/factures/règlements. Elles ne constituent pas des essais métier.

| Dolibarr | Sources examinées | Nature | Essai d’instance |
|---|---|---|---|
| 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` | Lecture des sources | Non exécuté |
| 21.0.0 | `fd970b582a4d8c5779a2958a4e9f4fce225cf085` | Lecture des sources | Non exécuté |
| 22.0.0 | `49b9a6d19f3deb6d410c0e9b3310e95be4ea7710` | Lecture des sources | Non exécuté |
| 23.0.0 | `57a1f05d490a7a80944a8232e9c613e6556d2704` | Lecture des sources | Non exécuté |
| 24.0.0 | `769c7db907099643558e77d7002c109cfda919e5` | Lecture des sources | Non exécuté |
| 25.0.0-alpha | `31627071c3d2dd890322088ebba9917515de7ac4` (`develop`) | Lecture des sources, préversion | Non exécuté |

`User::loadRights('', 1)` recharge les attributions mais ne supprime pas les droits déjà présents en mémoire. La copie de l’initiateur appelle donc d’abord `User::clearrights()`. L’identité n’est pas rechargée par `User::fetch()` depuis la destination : l’absence d’appartenance à cette entité n’empêche pas de conserver l’auteur réel. Cette lecture des sources ne prouve pas les comportements de modules tiers installés.

Sources primaires : [configuration native v20](https://github.com/Dolibarr/dolibarr/blob/697bf01970740a3339cd99cf055b4428fc5e051c/htdocs/core/class/conf.class.php), [permissions utilisateur natives v20](https://github.com/Dolibarr/dolibarr/blob/697bf01970740a3339cd99cf055b4428fc5e051c/htdocs/user/class/user.class.php), [validation des commandes v20](https://github.com/Dolibarr/dolibarr/blob/697bf01970740a3339cd99cf055b4428fc5e051c/htdocs/commande/card.php), [commande fournisseur v24](https://github.com/Dolibarr/dolibarr/blob/769c7db907099643558e77d7002c109cfda919e5/htdocs/fourn/commande/card.php), [changement de contexte dans le cron natif](https://github.com/Dolibarr/dolibarr/blob/029a92fb538e90db684dec8b6ba80ee5a6638efd/scripts/cron/cron_run_jobs.php).

## Recette sur une instance de test

- Configurer les correspondances de tiers et les options des flux existants. Aucun utilisateur d’exécution à choisir. Vérifier l’absence de modification des droits du compte source et l’absence d’accès manuel à la destination.
- Avec un utilisateur limité à A, valider les commandes clients/fournisseurs et les factures clients concernées ; vérifier l’auteur, l’entité, les lignes, les liens et la finalisation des PDF/ECM. Aucune création de facture client depuis une facture fournisseur n’est ajoutée.
- Enregistrer des règlements dans les deux sens, y compris partiels et répartis entre plusieurs destinations. Contrôler les montants, les factures soldées, le journal bancaire et l’absence de doublons après rejeu.
- Refuser l’opération source sans ses droits natifs (ordinaires et avancés), puis vérifier une création automatique autorisée sans droit de création manuelle dans B. Un refus d’approbation ou de suppression doit rester bloquant. Provoquer une erreur bancaire et contrôler le rollback final de l’opération source ; les simulations ne prouvent pas ce rollback réel.
- Conserver les règles d’approbation des autres modules. Le message d’approbation de la capture correspond à la clé `LmdbSupplierOrderLimitNativePermissionMissing` du module `lmdbsupplierorderlimit` ; son déclenchement exact sur l’instance reste à reproduire.
- Vérifier les partages Multicompany réels, les autres modules actifs et la conservation de la protection CSRF sur les parcours natifs. Confirmer que le serveur sert le nouveau commit avant de conclure.

Aucun déploiement, test navigateur, email ou règlement réel n’a été effectué pendant cette intervention. La matrice d’exécution Dolibarr v20–v25/PHP et la compatibilité avec la version Multicompany installée restent à valider. Aucun succès CI n’est revendiqué.
