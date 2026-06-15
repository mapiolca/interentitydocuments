# Change Log

## Non publié - 2026-06-15

- Génération du PDF du document source et recopie dans le dossier documentaire de l'objet créé dans l'entité de destination.
- Correction du chemin de recopie PDF Multicompany vers `DOL_DATA_ROOT[/entity]/facture|commande/{ref}`.
- Ajout du flux optionnel commande client validée vers commande fournisseur dans l'entité de destination.
- Ajout du réglage `OFSOM_AUTO_CREATE_SUPPLIER_ORDER_FROM_CUSTOMER_ORDER`.
- Remplacement d'un accès direct à `$_REQUEST` par `GETPOST()` dans la page de réglages.
- Conservation de l'entité courante lors d'un échec de suppression d'une commande client cible existante.

## 1.0 29/04/2026

- Version initiale / Init changelog
