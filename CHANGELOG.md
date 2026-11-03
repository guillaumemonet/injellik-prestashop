# Changelog

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
