# Documents inter-entités

Module externe Dolibarr permettant de créer des documents liés entre entités Multicompany.

Fonctionnalités principales :

- création d'une commande client dans l'entité fournisseur depuis une commande fournisseur validée ;
- création d'une facture fournisseur dans l'entité de destination depuis une facture client validée ;
- recopie du PDF du document source dans le dossier documentaire de l'objet créé dans l'entité de destination ;
- synchronisation optionnelle de lignes, statuts et réceptions selon la configuration du module.

Compatibilité cible : Dolibarr v20+ et PHP 8.0+.
