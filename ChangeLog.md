# Change Log

## Non publié - 2026-06-15

- Génération du PDF du document source et recopie dans le dossier documentaire de l'objet créé dans l'entité de destination.
- Correction du chemin de recopie PDF Multicompany vers `DOL_DATA_ROOT[/entity]/facture|commande/{ref}`.
- Correction du doublon ECM lors de la recopie PDF : un PDF source existant est recopié sans régénération.
- Correction de la résolution `last_main_doc` pour les PDF source des entités Multicompany.
- Ajout du flux optionnel commande client validée vers commande fournisseur dans l'entité de destination.
- Ajout du réglage `OFSOM_AUTO_CREATE_SUPPLIER_ORDER_FROM_CUSTOMER_ORDER`.
- Correction du warning `$object` dans la préparation des onglets admin.
- Correction de l'onglet À propos sans dépendance à une fonction `Markdown()`.
- Remplacement d'un accès direct à `$_REQUEST` par `GETPOST()` dans la page de réglages.
- Conservation de l'entité courante lors d'un échec de suppression d'une commande client cible existante.
- Correction de l'extrafield d'entrepôt de réception automatique des commandes fournisseur.
- Alignement de la recopie PDF sur les dossiers documentaires natifs des objets de destination.
- Déclaration explicite des propriétés du trigger pour éviter les warnings PHP 8.2.

## 1.0 29/04/2026

- Version initiale / Init changelog
