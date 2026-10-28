# PHP Insight — Scanner PHP portable

## Utilisation

1. Décompresser le ZIP dans un dossier local, hors d'un répertoire web public.
2. Vider les cinq exemples du dossier **sources** et y copier votre projet PHP extrait, avec ses sous-dossiers et ses fichiers `composer.lock`.
3. Ouvrir un terminal dans le dossier contenant `scan.php` et lancer :

```sh
php scan.php
```

4. Ouvrir **reports/rapport-audit-php.html** dans le navigateur.

Chaque scan remplace le rapport. Le code du projet n'est jamais exécuté.
Les tableaux affichent **15 lignes par page**, avec recherche et filtres.
Les appels obsolètes ont un fond **rouge**. Après modification de vos sources,
relancez `php scan.php` puis rechargez le rapport : un appel ancien qui a disparu
d'un fichier toujours analysé passe au **vert**, en gardant sa ligne et ses anciens
emplacements. Cela constate son absence, sans garantir la validité de la migration.
Les compteurs obsolètes/corrigés sont recalculés sur toutes les pages.

Le suivi est conservé dans `reports/history.json` : gardez ce fichier entre les scans.
Un fichier supprimé ou illisible, ou un scan incomplet, reste **à revérifier** (jaune)
au lieu d'être déclaré corrigé. Si un appel réapparaît, sa ligne repasse au rouge.
Les numéros de ligne peuvent changer sans perdre le suivi ; un renommage de fichier
demande une revue manuelle. Pour changer de projet et effacer volontairement le suivi :
`php scan.php --reset-history`. Ne lancez pas deux scans simultanément.

Pour une démonstration, garder les cinq exemples dans `sources` puis lancer la commande.
Les originaux restent dans `examples/php56`.

Windows : lancer `scanner.bat` si PHP est dans le PATH. Linux/macOS : `sh scanner.sh`.
Aucun serveur web, Composer ou Python nécessaire.

## Prérequis

PHP CLI 5.6 ou supérieur avec tokenizer et JSON, lecture de `sources` et écriture de `reports`.
Utiliser de préférence une version maintenue de PHP pour le scanner, même pour examiner du PHP 5.6.
La version du scanner n'est pas la version cible du projet.
Bootstrap utilise un CDN avec SRI ; sans Internet, le CSS intégré conserve un rapport lisible.

## Consultation php.net facultative

```sh
php scan.php --verify
```

Cette option tente une consultation HTTPS des pages officielles des fonctions historiques reconnues.
Elle nécessite cURL ou allow_url_fopen, des certificats valides et l'accès à www.php.net.
Elle affiche le statut HTTP mais **ne valide pas automatiquement les versions du catalogue**.
Les historiques et remplacements restent à relire. Les requêtes ne contiennent que les noms des fonctions du catalogue, jamais votre code.
Sans cette option, le scan est hors ligne.

## Fonctionnement et limites

- Parcours récursif des fichiers `.php` ; exclusion de `.git`, `vendor`, `node_modules` et liens symboliques.
- Lecture par tokenizer PHP : déclarations custom et appels directs, sans exécuter les sources.
- Fonctions natives reconnues à partir du PHP du scanner et d'un catalogue historique avec migrations proposées.
- Paquets recensés dans `composer.lock` et hôtes HTTP(S) littéraux repérés.
- Rapport HTML Bootstrap, recherche, filtre, impression PDF et limites visibles.

Ce premier scanner n'est pas exhaustif : résolution incomplète des méthodes, types, alias, namespaces, classes anonymes et appels dynamiques. Un appel non résolu reste « indéterminé ». Les constructions de langage ne sont pas des fonctions. Les classes natives et les symboles absents du runtime n'ont pas encore de catalogue complet. Les versions cibles et signatures ne sont pas testées.

Les dates de dépréciation/suppression sont des références locales à confirmer sur php.net.
**Aucune base CVE/GHSA/OSV n'est interrogée automatiquement** : un paquet recensé n'est ni déclaré sûr ni déclaré vulnérable. La compétence d'agent explique comment compléter ces vérifications.

Les rapports contiennent des chemins et noms de symboles : les conserver localement.
L'outil ne publie rien et ne contacte pas les API détectées. Ne pas exposer ce dossier sur un serveur web public : les exemples sont historiques.

Codes de sortie : 0 = rapport créé sans erreur de lecture (pas une garantie de compatibilité ou de sécurité), 1 = erreur/lecture partielle, 2 = aucun fichier PHP.

## Agent et compétence

`AGENTS.md` et `skills/php-audit/SKILL.md` décrivent l'analyse approfondie par un assistant avec les accès nécessaires. Ils ne sont pas exécutés automatiquement par `scan.php`.
