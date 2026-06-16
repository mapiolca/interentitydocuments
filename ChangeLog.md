# Change Log

## Non publié - 2026-06-16

- Remplacement du logo du module par les variantes Dolibarr 32x32 et 16x16 fournies.
- Passage du picto du descripteur sur l'icône du module.
- Renommage strict du préfixe technique de configuration vers `IED_*`, sans migration des anciens réglages.
- Renommage de la page de configuration principale en `admin/setup.php`.
- Ajout d'un onglet interne `Compatibilité` et d'une classe centralisée de vérification d'environnement.
- Mise à jour des métadonnées du descripteur pour Dolibarr v20+ et PHP 8.0+.
- Remplacement de la suppression par checkbox des mappings par une action dédiée protégée par token.
- Ajout du diagnostic `AGENT_DIAGNOSTIC.md`.

## Non publié - 2026-06-15

- Génération du PDF du document source et recopie dans le dossier documentaire de l'objet créé dans l'entité de destination.
- Correction du chemin de recopie PDF Multicompany vers `DOL_DATA_ROOT[/entity]/facture|commande/{ref}`.
- Correction du doublon ECM lors de la recopie PDF : un PDF source existant est recopié sans régénération.
- Correction de la résolution `last_main_doc` pour les PDF source des entités Multicompany.
- Ajout du flux optionnel commande client validée vers commande fournisseur dans l'entité de destination.
- Ajout du réglage `IED_AUTO_CREATE_SUPPLIER_ORDER_FROM_CUSTOMER_ORDER`.
- Correction du warning `$object` dans la préparation des onglets admin.
- Correction de l'onglet À propos sans dépendance à une fonction `Markdown()`.
- Remplacement d'un accès direct à `$_REQUEST` par `GETPOST()` dans la page de réglages.
- Conservation de l'entité courante lors d'un échec de suppression d'une commande client cible existante.
- Correction de l'extrafield d'entrepôt de réception automatique des commandes fournisseur.
- Alignement de la recopie PDF sur les dossiers documentaires natifs des objets de destination.
- Déclaration explicite des propriétés du trigger pour éviter les warnings PHP 8.2.
- Synchronisation des copies PDF après validation, paiement et régénération manuelle avec la référence courante.
- Correction de la génération forcée du PDF source : un PDF existant est toujours recopié sans réindexation ECM.
- Ajout du flux optionnel de synchronisation des règlements fournisseur vers les factures client liées.
- Ajout du flux optionnel de synchronisation des paiements client vers les règlements fournisseur liés.

## 1.0 29/04/2026

- Version initiale / Init changelog
