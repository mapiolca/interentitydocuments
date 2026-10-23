# Change Log

## 1.0.2 - 2026-10-23

- Correction du blocage de validation d'une commande client disposant déjà d'un PDF de brouillon : la copie inter-entités attend désormais la génération native et le hook `afterPDFCreation`, sans créer de doublon `uk_ecm_files` avant le renommage ECM. Si la génération automatique est désactivée, la copie intervient à la prochaine génération native du PDF.
- Ajout de journaux de diagnostic pour `ORDER_VALIDATE` précisant l'étape, les identifiants de commande, les entités, le résultat natif et le code d'erreur de base de données, sans exposer de requête SQL ni de contenu client sensible.
- Restitution des erreurs natives complètes à Dolibarr et message explicite lorsqu'une correspondance inverse entre tiers et entité manque.
- Arrêt du clonage après une erreur de recherche ou un résultat de création invalide, contrôle de l'entité propriétaire et restauration du contexte d'entité après les appels natifs, y compris en cas d'exception.

## 1.0.1 - 2026-08-25

- Suppression des propriétés dynamiques obsolètes du descripteur sous PHP 8.2.

## 1.0.0 - 2026-04-29

- Version initiale.
- Ajout du flux optionnel de création d'une commande fournisseur depuis une commande client validée dans une autre entité, avec le réglage `IED_AUTO_CREATE_SUPPLIER_ORDER_FROM_CUSTOMER_ORDER`.
- Génération et recopie des PDF sources dans les dossiers documentaires natifs de l'entité propriétaire de l'objet créé ; correction des chemins Multicompany et de la résolution `last_main_doc`.
- Synchronisation des copies PDF après validation, paiement et régénération manuelle avec la référence courante ; réutilisation d'un PDF source existant sans régénération ni réindexation ECM.
- Ajout des flux optionnels de synchronisation des règlements fournisseur vers les factures client liées et des paiements client vers les règlements fournisseur liés.
- Renommage strict des constantes de configuration vers `IED_*`, sans migration des anciens réglages, et de la page principale de réglages en `admin/setup.php`.
- Ajout d'un onglet interne `Compatibilité`, d'une classe centralisée de vérification d'environnement et des métadonnées Dolibarr v20+ et PHP 8.0+.
- Utilisation de `GETPOST()` dans les réglages et d'une action dédiée protégée par token pour supprimer les correspondances tiers-entité.
- Conservation de l'entité courante en cas d'échec de suppression d'une commande client cible et correction de l'extrafield d'entrepôt de réception automatique des commandes fournisseur.
- Correction de la préparation des onglets admin et de l'onglet À propos sans dépendance à `Markdown()` ; déclaration des propriétés du trigger pour éviter les warnings PHP 8.2.
- Adoption des logos Dolibarr 32x32 et 16x16 et de l'icône du module dans le descripteur ; ajout du diagnostic `AGENT_DIAGNOSTIC.md`.
