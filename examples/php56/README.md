# Cas d’essai PHP 5.6

Ces cinq fichiers contiennent des classes et fonctions custom, des méthodes custom,
des fonctions natives encore disponibles et dix fonctions natives historiques
présentes en PHP 5.6 puis supprimées en PHP 7.0 ou 8.0. Les classes custom ne sont
pas des classes natives supprimées. Aucun fichier ne lance son exemple au chargement.
Ce code sert à l’analyse statique ; il ne doit pas être déployé ou exécuté.

PHP 5.6 ne suffit pas à garantir la disponibilité : `mysql` et POSIX regex nécessitent
leurs extensions historiques ; `money_format` dépend de la plateforme et de la locale.

| Fichier | Fonction native | Dépréciation attendue | Suppression attendue | Migration proposée |
| --- | --- | --- | --- | --- |
| 01_mysql.php | mysql_connect | 5.5 | 7.0 | PDO::__construct ou mysqli_connect ; configurer le pilote et les erreurs |
| 01_mysql.php | mysql_query | 5.5 | 7.0 | PDO::query pour SQL constant ; PDO::prepare/execute pour valeurs variables, ou mysqli_query ; migrer aussi les résultats et transactions |
| 02_regex.php | ereg | 5.3 | 7.0 | preg_match ; adapter les expressions POSIX vers PCRE, ajouter les délimiteurs et vérifier les retours |
| 02_regex.php | split | 5.3 | 7.0 | preg_split avec motif PCRE ; explode uniquement pour séparateur littéral |
| 03_iteration.php | create_function | 7.2 | 8.0 | Fonction anonyme (closure) ; vérifier capture et portée des variables |
| 03_iteration.php | each | 7.2 | 8.0 | foreach ; attention au pointeur interne, aux références et aux mutations |
| 04_magic_quotes.php | get_magic_quotes_gpc | 7.4 | 8.0 | Supprimer le branchement historique : renvoie false depuis PHP 5.4 ; pas de remplacement direct |
| 04_magic_quotes.php | get_magic_quotes_runtime | 7.4 | 8.0 | Supprimer le branchement historique ; ne pas introduire de déséchappement systématique |
| 05_locale.php | money_format | 7.4 | 8.0 | NumberFormatter::formatCurrency (extension intl), avec locale et devise explicites ; sortie à comparer |
| 05_locale.php | convert_cyr_string | 7.4 | 8.0 | mb_convert_encoding($text, 'KOI8-R', 'Windows-1251') ou iconv ; gérer les erreurs d’encodage |

Ces valeurs sont des attentes de référence, pas des consultations web certifiées.
La tentative d’accès à php.net du 28 octobre 2026 a reçu une erreur 403 du proxy.
L’agent doit vérifier chaque ligne dans le manuel avant de la marquer confirmée.

Sources à consulter :

- https://www.php.net/manual/en/migration70.incompatible.php
- https://www.php.net/manual/en/migration80.incompatible.php
- Pages `https://www.php.net/manual/en/function.NOM.php` (remplacer `_` par `-`).

Demande de test :

> Analyse examples/php56 avec la compétence php-audit. Recense tous les symboles,
> y compris custom et natifs encore disponibles. Compare les fonctions historiques
> avec php.net, propose leurs remplacements et produis le rapport HTML Bootstrap.
> N’exécute pas ce code historique. Distingue les attentes des faits vérifiés.
