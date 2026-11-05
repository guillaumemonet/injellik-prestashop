# Changelog

## [2.1.0] - 2026-11-05

Injellik est désormais vendu sur abonnement sur [web-nest.fr](https://web-nest.fr/#tarifs). Les installations existantes (identifiant et mot de passe) continuent de fonctionner sans action.

### Ajouté
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
