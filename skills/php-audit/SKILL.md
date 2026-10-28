---
name: php-audit
description: Auditer un projet PHP en lecture seule, inventorier fonctions et classes, vérifier leur cycle de vie sur php.net et rechercher les vulnérabilités connues des dépendances et intégrations API.
---

# Audit PHP étape par étape

## 1. Définir le périmètre

Inspecter les instructions locales, la documentation, `composer.json`, `composer.lock`,
les images Docker, les fichiers de versions et la CI. Identifier les versions PHP
ciblées, extensions, frameworks et répertoires de code, tests et dépendances.
Utiliser `rg --files --hidden -g '*.php' -g '!.git/**'` en adaptant les exclusions
au dépôt ; exclure explicitement `.git`. Consigner les fichiers ignorés, inaccessibles
et générés. Séparer le code applicatif de `vendor` pour éviter les doublons.

Annoncer le périmètre retenu et les limites. Ne demander que les informations
indispensables introuvables dans le dépôt. Sans version cible, analyser les versions
déclarées et signaler l’absence de cible plutôt que d’en inventer une.

## 2. Recenser les symboles

Préférer un analyseur syntaxique PHP déjà disponible et approuvé. Une recherche
textuelle sert au repérage, mais ne constitue pas un inventaire exhaustif.
Ne pas installer ni exécuter un outil du dépôt sans autorisation.

Recenser les déclarations et usages des fonctions, classes, méthodes, interfaces,
traits et enums. Résoudre espaces de noms, alias `use`, héritage et appels statiques
quand possible. Respecter la résolution PHP des fonctions non qualifiées, notamment
le repli possible sur l’espace global. Signaler les ambiguïtés liées à l’autoload,
aux appels dynamiques, à la réflexion et aux fichiers inclus conditionnellement.

Classifier chaque symbole comme `custom`, `natif PHP`, `bibliothèque tierce` ou
`indéterminé`. Distinguer le cœur PHP des extensions, y compris PECL. Ne pas
déduire qu’un symbole est natif uniquement parce qu’il existe dans le PHP installé.
Conserver nom pleinement qualifié, nature et occurrences fichier:ligne.

## 3. Comparer avec php.net

Pour chaque symbole natif distinct, consulter sa page officielle sur
https://www.php.net/manual/fr/ (ou la version anglaise) et les guides de migration
pertinents. Vérifier la section historique et les notes de dépréciation/suppression.
Consigner URL et date de consultation. Vérifier aussi les changements de signature
ou de comportement affectant les appels observés.

Ne pas confondre dépréciation et suppression. Distinguer versions de PHP et versions
d’extensions. Une plage de versions affichée sur une page ne prouve pas toujours la
version exacte d’introduction. Employer « non documenté » si elle reste inconnue.
N’indiquer une suppression future comme certaine que si elle est officiellement
confirmée ; sinon écrire « proposée » ou « non annoncée ». Un remplacement doit
être sourcé et accompagné de ses différences utiles ; certains symboles n’en ont pas.

Pour le code custom et tiers, indiquer « sans objet » dans les colonnes du cycle de
vie PHP et documenter séparément les contraintes du paquet. Sans accès à php.net,
marquer les lignes « non vérifiées » et expliquer le blocage.

Produire ce tableau, une ligne par symbole :

| Symbole | Nature | Type | Extension/paquet | Emplacements | Version d’introduction | Dernière version disponible | Obsolète (oui/non/inconnu) | Déprécié depuis | Supprimé depuis/prévu | Remplacement | Compatibilité cible | Source et date |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |

Si le symbole existe encore, écrire « encore disponible dans X, vérifié le … »
plutôt que d’inventer une version de fin. La compatibilité doit considérer
l’extension requise, la signature utilisée et les versions cibles du projet.

## 4. Identifier bibliothèques et API

Lire `composer.lock` pour les versions exactes, y compris dépendances transitives
et de développement. Distinguer celles-ci des contraintes de `composer.json`.
Si seul le manifeste existe, déclarer les versions installées inconnues. Ne pas
lancer `composer update`. Examiner aussi les bibliothèques embarquées et les SDK.

Repérer les clients HTTP, SDK, routes, URL et versions d’API dans le code et la
configuration. Masquer les jetons, identifiants et paramètres sensibles des URL.
Ne pas contacter les API du projet ni tester une cible distante sans autorisation.

## 5. Vérifier les vulnérabilités

Comparer les versions exactes avec les avis de sécurité des éditeurs, les GitHub
Security Advisories, OSV et, si utile, les CVE/NVD. Utiliser les outils de consultation
disponibles et citer chaque avis. Vérifier l’écosystème Composer et l’identité du
paquet pour éviter les homonymes.

Si Composer est disponible et son usage autorisé, une vérification possible est :
`composer --no-plugins --no-scripts audit --locked --format=json` depuis le projet.
Vérifier d’abord que la version installée prend en charge ces options. Cette commande
consulte le réseau ; elle ne doit pas installer de dépendances. Préserver son code
de sortie et distinguer avis trouvés, erreurs réseau et résultats incomplets.

Valider la plage affectée, la version corrigée et les conditions d’exploitation.
Une dépendance affectée ne prouve pas que le chemin vulnérable est accessible.
Sans version exacte, classer le résultat « à vérifier ». Pour une API distante,
l’analyse du client ne permet pas de garantir la sécurité du serveur.

Examiner également les usages visibles : injections SQL/commandes, XSS,
désérialisation, SSRF, téléversements, contrôle d’accès, TLS désactivé et secrets.
Étayer chaque risque par une entrée contrôlable, son chemin vers une opération
sensible et les protections observées. Un motif dangereux isolé reste un signal
à vérifier, pas une faille confirmée.

Produire ce tableau :

| Bibliothèque/API | Version et preuve | Directe/transitive/dev | CVE/GHSA/avis | Plage affectée | Sévérité et source | Statut | Conditions et preuve locale | Version corrigée/action | Source et date |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |

Statuts : `affecté selon avis`, `risque potentiel`, `à vérifier`,
`aucun avis trouvé dans les sources consultées`, `vérification impossible`.
« Aucun avis trouvé » ne signifie jamais « aucune faille ».

## 6. Livrer et contrôler

Pour un essai de régression, utiliser `../../examples/php56` relativement au dossier
de cette compétence. Analyser les cinq fichiers PHP sans les exécuter. Le README
des exemples contient des attentes de référence : ce n’est pas une source officielle.
Recenser aussi leurs classes, méthodes et fonctions custom, ainsi que les fonctions
natives non supprimées. Vérifier chaque fonction historique sur php.net et proposer
sa migration avec les différences de comportement. Un accès web bloqué doit rester
visible dans le rapport ; ne pas marquer les attentes comme confirmées.

Le livrable principal est une page HTML en français, responsive, utilisant Bootstrap
5.3.3. Partir de `assets/rapport.html` et remplacer les états vides par les résultats
réels. Conserver toutes les colonnes définies aux étapes 3 et 5, quitte à utiliser
un défilement horizontal et des détails dépliables. Ne jamais transformer les états
vides ou les exemples en résultats d’audit.

Inclure une synthèse, les versions ciblées, les compteurs réellement mesurés,
l’inventaire PHP, les dépendances/API, les recommandations et les sources datées.
Prévoir une recherche, des filtres, des badges avec libellés explicites et une vue
imprimable. Les informations doivent rester lisibles sans JavaScript. Le modèle
affiche 15 lignes par page avec JavaScript. Conserver les lignes historiques des
appels obsolètes : fond rouge si encore présents, vert si absents après un nouveau
scan complet de leur fichier, jaune si leur état n'est pas vérifiable. Recalculer
les compteurs globaux ; ne pas assimiler la disparition d'un appel à la validation
du remplacement. Le scanner portable conserve le suivi dans `reports/history.json`.
Ne pas livrer l'historique d'un autre projet dans une archive de distribution.
Le modèle
utilise Bootstrap via CDN avec SRI ; sans réseau, son CSS local intégré préserve
la lecture. Ne pas ajouter de télémétrie ni de ressources envoyant le code analysé.
Échapper toutes les données du projet lors de leur insertion HTML ; ne jamais
insérer des données non fiables par `innerHTML`. Valider les liens de sources
avec une liste de protocoles autorisés (https/http) et masquer les secrets.

Rédiger le rapport en français. Fournir les deux tableaux, le périmètre, les versions
cibles, la méthode, les sources consultées et les limites. Séparer les constats de
code des avis sur les dépendances. Prioriser les actions avec leur justification.
Ne pas recommander une mise à jour incompatible sans signaler la migration requise.

Indiquer les nombres de fichiers examinés, symboles résolus/non résolus et paquets
vérifiés/non vérifiés, uniquement lorsqu’ils ont été réellement mesurés. Consigner
les commandes exécutées et leurs résultats réels. Ne pas affirmer qu’un audit
exhaustif a été réalisé si la méthode est textuelle ou les sources indisponibles.

Avant livraison, contrôler les liens, les preuves fichier:ligne, les versions et
l’absence de secrets dans le rapport. Vérifier que le dépôt analysé n’a pas changé.
Écrire `rapport-audit-php.html` dans un emplacement convenu ou hors du dépôt analysé.
Sans emplacement demandé, utiliser `/tmp/php-audit-report/rapport-audit-php.html`.
Fournir un lien vers le fichier et un bref bilan dans la conversation.
