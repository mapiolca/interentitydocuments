# Diagnostic AGENT.md - Documents inter-entités

Date : 2026-06-16

## Corrections ciblées réalisées

- Logo du module remplacé par les variantes Dolibarr `img/interentitydocuments.png` et `img/object_interentitydocuments.png`.
- Descripteur ajusté sur le picto du module, PHP 8.0 minimum et Dolibarr v20 minimum.
- Point d'entrée de configuration aligné sur `admin/setup.php` uniquement.
- Onglet interne `Compatibilité` ajouté avec une classe centralisée `InterentitydocumentsCompatibility`.
- Préfixe technique historique remplacé strictement par `IED` dans le code, les traductions, les constantes et la documentation.
- Fichiers de langue courts des extrafields renommés vers `ied.lang`.
- Suppression des mappings d'entités remplacée par une action dédiée protégée par token.
- Onglet À propos complété avec les informations utiles du module.

## Écarts AGENT.md encore présents

- Droits granulaires non déclarés dans le descripteur ; les pages admin utilisent seulement le contrôle administrateur.
- Aucun helper centralisé de droits pour garantir le comportement administrateur et super-administrateur Multicompany.
- `TTELink` n'hérite pas de `CommonObject` et ne reprend pas les champs, méthodes, statuts et triggers d'un objet métier Dolibarr standard.
- Plusieurs opérations multi-écritures ne sont pas transactionnelles, notamment les créations de documents liés suivies d'updates SQL et de liens `element_element`.
- Plusieurs requêtes SQL restent directes et certaines manipulent temporairement `$conf->entity`.
- L'intégration Multicompany n'expose pas encore les objets ou réglages partageables dans l'administration native du module Multicompany.
- Les documents sont copiés via une résolution manuelle de `DOL_DATA_ROOT` et non via `getMultidirOutput()` pour tous les cas.
- Aucune intégration native Agenda ou Notifications dédiée n'est déclarée pour les événements métier du module.
- Les flux de création automatique sont dans un trigger global Dolibarr et pas dans des méthodes d'objet métier propres au module.
- Les modèles de document, modèles de numérotation, imports/exports, API REST et cron natifs ne sont pas structurés comme un module Dolibarr complet.
- Les scripts SQL utilisent une table métier minimale sans migration idempotente avancée ni contraintes uniques pour éviter certains doublons métier.

## Risques principaux

- La suppression volontaire de compatibilité avec les anciennes constantes impose une reconfiguration des options après mise à jour.
- Les changements d'entité et écritures multi-objets sans transaction peuvent laisser des liens partiels en cas d'erreur.
- La recopie documentaire doit être revue avant usage étendu en Multicompany partagé afin de garantir l'entité propriétaire de chaque fichier.
- Les permissions actuelles conviennent aux réglages administrateur, mais pas à une délégation fine à des utilisateurs standards.

## Recommandations de suite

- Refondre `TTELink` en objet `CommonObject` ou isoler les mappings dans un objet métier conforme.
- Ajouter des droits `read`, `write`, `delete`, `configure` et un helper centralisé de contrôle.
- Encadrer les créations liées dans des transactions et rendre les liens `element_element` idempotents.
- Remplacer la résolution documentaire manuelle par le helper natif Multicompany lorsque le contexte objet le permet.
- Déclarer proprement les intégrations Multicompany, Agenda et Notifications si ces événements doivent être administrables nativement.
