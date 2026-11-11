# Changelog

## [2.1.2] - 2026-11-11

### Corrigé
- **Une API injoignable pouvait ralentir toute la boutique.** Chaque bloc attendait le délai de connexion deux fois (stratégie principale, puis stratégie de repli) avant de s'afficher vide. Comme l'échec n'était pas mémorisé, chaque visiteur attendait de nouveau, en occupant un processus PHP : avec quelques visiteurs simultanés, toutes les pages devenaient lentes, surtout sur un hébergement mutualisé.
- Désormais, après une erreur réseau, un délai dépassé ou une API surchargée (HTTP 429, 502, 503, 504), la boutique n'appelle plus l'API pendant 60 secondes :
  - les pages ne contiennent plus d'emplacement de bloc, donc aucune requête n'est lancée ;
  - les événements restent en file, sans perdre de tentative, et partent au retour de l'API ;
  - à la fin de la pause, une seule requête réessaie, pendant que les autres continuent à ne pas attendre.
- Le back-office et la ligne de commande appellent toujours l'API, pour afficher l'erreur réelle ; un appel réussi lève la pause aussitôt.

## [2.1.1] - 2026-11-10

### Corrigé
- **Ajout au panier impossible avec PHP-FPM** : la popup et le panier restaient vides. Le module libérait la réponse (`fastcgi_finish_request`) avant que PrestaShop écrive son cookie : le visiteur perdait son panier, sa connexion et son identifiant de visite. Le cookie et la session sont désormais écrits avant, et l'envoi des événements passe après tous les autres traitements de fin de requête.
- Sans PHP-FPM (Apache mod_php), les événements ne sont plus envoyés pendant la requête du visiteur : le navigateur demande leur envoi juste après (`sendBeacon`), et une API lente ne ralentit plus aucune page.

### Modifié
- Tous les hooks sont protégés : une erreur du module est consignée dans les journaux et n'interrompt jamais la page, le panier ou la commande.

### Sécurité
- Clics : une requête sans cookie de visite (script) n'enregistre plus de clic ni d'événement. Seuls les produits actifs et visibles sont acceptés, et le jeton est comparé en temps constant.
- Blocs : le rendu n'accepte que des requêtes POST. Les stratégies personnalisées et le comptage des affichages sont réservés aux visiteurs connus, ce qui empêche de multiplier les appels à l'API et les fichiers de cache en rejouant un bloc.
- Les produits des catégories réservées à d'autres groupes de clients (B2B, ventes privées) ne sont plus recommandés.
- Back-office :
  - l'URL de l'API doit être en `https://` (`http://` reste accepté en mode debug) ;
  - la clé de licence et le mot de passe enregistrés ne sont plus jamais envoyés à une autre URL que la leur, et changer l'URL efface ces secrets ;
  - les actions exigent la permission de modification ;
  - les messages d'erreur renvoyés par l'API sont affichés comme du texte.
- Cron : le jeton peut être passé dans l'en-tête `X-Injellik-Token`, pour ne pas apparaître dans les journaux d'accès.

## [2.1.0] - 2026-11-05

Injellik est désormais vendu sur abonnement sur [web-nest.fr](https://web-nest.fr/#tarifs). Les installations existantes (identifiant et mot de passe) continuent de fonctionner sans action.

### Ajouté
- Import de l'historique de commandes : chaque achat est envoyé avec sa date réelle (`occurred_at`) et marqué `source: import`, donc non compté dans les sessions facturées. Les commandes de plus de 3 ans sont ignorées (refusées par l'API).
- Connexion par **clé de licence** (`ijk_lic_…`, une par boutique), mode par défaut : en-tête `X-Api-Key`, sans appel à `/api/login`. Le format de la clé est vérifié avant l'enregistrement.
- Assistant et onglet Connexion : la clé de licence seule, avec les liens « Obtenir une clé » et « Retrouver ma clé ». L'identifiant et le mot de passe restent accessibles par « Utiliser un identifiant API ».
- Abonnement suspendu (403 `tenant_suspended`) ou clé invalide (401) :
  - les blocs de recommandations sont masqués et la boutique n'appelle plus l'API, avec un nouvel essai au plus toutes les 15 minutes et à chaque enregistrement de la configuration ;
  - les événements restent dans la file locale, sans consommer leurs essais, et partent dès que l'API accepte de nouveau la boutique ;
  - bandeau sur les pages du module et rappel sobre sur les autres pages du back-office (hook `displayDashboardTop`), avec un lien vers l'abonnement ou vers les boutiques du compte web-nest.fr.
- URL de l'API dans l'onglet Avancé.

### Modifié
- L'URL de l'API est préremplie avec `https://injellik.web-nest.fr` (le script de mise à jour la renseigne si elle est vide).
- Le test de connexion appelle `GET /api/usage` dans les deux modes.
- Une commande validée hors de la navigation du client (notification de paiement, commande créée au back-office) reçoit la session `order-<id>` au lieu d'une session aléatoire. Les sessions servant désormais de base à la facturation, aucune n'est créée pour une requête qui n'est pas une visite.
- Types nullables explicites : plus d'avertissement de dépréciation sous PHP 8.4.

## [2.0.0] - 2026-11-03

Réécriture complète du module.

### Ajouté
- Compatibilité PrestaShop 8.0 à 9.x (PHP 7.2.5 à 8.4).
- Assistant d'installation : connexion, catalogue, historique de commandes, blocs.
- Page de suivi :
  - état de l'API, activité sur 30 jours, file d'événements ;
  - affichages, clics et taux de clic par bloc ;
  - classements et derniers événements.
- Page de configuration : connexion, blocs, données, intégration, réglages avancés.
- Six emplacements (produit, panier, confirmation, accueil, catégorie, widget) et sept stratégies, avec stratégie de repli.
- Widget `{widget name='injellikrecommendations'}` paramétrable, utilisable sur n'importe quel hook.
- Chargement asynchrone des blocs, compatible avec les caches de page complète, et événement JS `injellik:loaded`.
- File d'événements locale avec envoi après la réponse et réessais.
- Tâche cron (envoi, purge, synchronisation complète du catalogue).
- Suivi des clics sur les recommandations (`CLICK`).
- Synchronisation automatique des produits, y compris leur profil (catégories, marque, prix) pour les produits similaires.
- Import de l'historique de commandes par lots, avec reprise.
- Identifiants pseudonymes : aucune donnée personnelle envoyée.
- Traduction française.

### Modifié
- Le jeton JWT est mis en cache jusqu'à son expiration au lieu d'une authentification à chaque requête.
- Les appels à l'API ne peuvent plus bloquer ni faire échouer l'affichage d'une page ou la validation d'une commande.

### Supprimé
- Action `REMOVECART`, qui n'existe pas dans l'API.

## [1.0.3]

Version initiale.
