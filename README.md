# Documents inter-entités

Module externe Dolibarr permettant de créer des documents liés entre entités Multicompany.

Fonctionnalités principales :

- création d'une commande client dans l'entité fournisseur depuis une commande fournisseur validée ;
- création d'une facture fournisseur dans l'entité de destination depuis une facture client validée ;
- création optionnelle d'une commande fournisseur dans l'entité de destination depuis une commande client validée ;
- recopie du PDF du document source dans le dossier documentaire `DOL_DATA_ROOT[/entity]/facture|commande/{ref}` de l'objet créé dans l'entité de destination ;
- synchronisation optionnelle de lignes, statuts et réceptions selon la configuration du module.

Compatibilité cible : Dolibarr v20+ et PHP 8.0+.

## Configuration

Le point d'entrée des réglages est `admin/setup.php`, déclaré comme unique page de configuration du module.

Les constantes de configuration utilisent le préfixe `IED_*`. Le renommage est strict : les anciennes constantes techniques ne sont pas migrées et les options doivent être reconfigurées après mise à jour si elles existaient déjà.

L'onglet `Compatibilité` affiche la version PHP, la version Dolibarr et les fonctionnalités disponibles dans l'environnement courant.

## Diagnostic de la validation des commandes

Le parcours `ORDER_VALIDATE` écrit des traces `interentitydocuments` dans le journal natif Dolibarr. Les échecs sont au niveau `ERROR`, l'entrée et le retour de synchronisation au niveau `INFO`, les étapes intermédiaires et abstentions au niveau `DEBUG`.

Les traces identifient la commande source, les entités source/cible, le tiers cible, l'utilisateur, la commande fournisseur connue, le code de retour et le code d'erreur de base de données lorsqu'ils sont disponibles. Elles ne recopient ni requête SQL ni texte d'erreur natif susceptible de contenir des données client ; les erreurs natives complètes sont conservées dans les messages retournés à Dolibarr.

| Valeur de `stage` | Contrôle ou opération en échec |
| --- | --- |
| `target_mapping` | Recherche de l'entité correspondant au tiers de la commande |
| `fetch_source_order`, `fetch_source_lines`, `check_entities` | Chargement de la commande source, de ses lignes ou contrôle des entités |
| `origin_lookup` | Recherche d'une commande fournisseur à l'origine de la commande client ; une liaison existante évite une boucle |
| `reverse_mapping` | Recherche, dans l'entité cible, du tiers représentant l'entité source |
| `existing_order_lookup` | Recherche de la commande fournisseur déjà créée ; une erreur SQL arrête le traitement avant toute mutation |
| `fetch_existing_order`, `delete_existing_order` et leurs variantes `_exception` | Chargement ou remplacement d'une commande fournisseur existante |
| `create_supplier_order`, `create_supplier_order_exception` | Création native de la commande fournisseur, y compris ses lignes et triggers |
| `assign_target_entity`, `link_orders` | Affectation de l'entité propriétaire ou création du lien entre commandes |
| `clone_returned` | Retour de la synchronisation, avec le résultat facultatif de la copie PDF |

`reason=missing_reverse_mapping` signifie que les réglages de l'entité cible doivent comporter un tiers représentant l'entité source. Cette configuration n'est pas créée automatiquement. Les échecs de lecture SQL restent bloquants et les erreurs natives de création restent remontées, même lorsque le core les place uniquement dans son tableau `errors`.

Le contexte d'entité est restauré après les appels natifs, y compris en cas d'exception. `transaction=caller` indique que la validation de commande reste responsable du commit ou du rollback final : le retour du clonage ne prouve pas encore la persistance de la transaction complète.

Tests de régression isolés, avec objets et réponses SQL simulés (aucune connexion à une instance) :

```sh
php test/order_validation.php /chemin/vers/dolibarr/htdocs
```

Ces scénarios vérifient la propagation des erreurs, l'arrêt après échec de recherche, la restauration de l'entité, les abstentions et le parcours réussi. Ils ne remplacent pas un essai sur une instance Dolibarr/Multicompany avec deux entités et vérification du commit ou rollback final.

## Diagnostic AGENT.md

Le fichier `AGENT_DIAGNOSTIC.md` liste les écarts restant à traiter pour une mise en conformité Dolibarr complète.
