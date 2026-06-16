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

## Diagnostic AGENT.md

Le fichier `AGENT_DIAGNOSTIC.md` liste les écarts restant à traiter pour une mise en conformité Dolibarr complète.
