<?php
/** Scanner statique autonome. PHP 5.6+ avec tokenizer et JSON. Ne charge jamais le code cible. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Lancez ce scanner en ligne de commande : php scan.php\n");
}
if (!function_exists('token_get_all') || !function_exists('json_decode')) {
    fwrite(STDERR, "Les extensions tokenizer et JSON sont requises.\n"); exit(1);
}
$root = __DIR__;
$source = realpath($root . '/sources');
$verify = in_array('--verify', $argv, true);
$catalog = array(
    'mysql_connect' => array('5.5', '7.0', 'PDO::__construct ou mysqli_connect ; configurer pilote, encodage et erreurs.', 'mysql'),
    'mysql_query' => array('5.5', '7.0', 'PDO::query pour SQL constant ; PDO::prepare puis execute pour les valeurs variables. Migrer aussi les résultats.', 'mysql'),
    'ereg' => array('5.3', '7.0', 'preg_match ; adapter le motif POSIX vers PCRE, ajouter des délimiteurs et vérifier les retours.', 'POSIX regex'),
    'eregi' => array('5.3', '7.0', 'preg_match avec modificateur i ; adapter POSIX vers PCRE.', 'POSIX regex'),
    'ereg_replace' => array('5.3', '7.0', 'preg_replace ; adapter motif et références de capture.', 'POSIX regex'),
    'eregi_replace' => array('5.3', '7.0', 'preg_replace avec modificateur i ; adapter motif et captures.', 'POSIX regex'),
    'split' => array('5.3', '7.0', 'preg_split avec motif PCRE ; explode uniquement pour un séparateur littéral.', 'POSIX regex'),
    'spliti' => array('5.3', '7.0', 'preg_split avec modificateur i ; vérifier les expressions.', 'POSIX regex'),
    'create_function' => array('7.2', '8.0', 'Fonction anonyme (closure) ; vérifier portée et capture des variables.', 'standard'),
    'each' => array('7.2', '8.0', 'foreach ; vérifier le pointeur interne, les références et les mutations.', 'standard'),
    'get_magic_quotes_gpc' => array('7.4', '8.0', 'Supprimer le branchement historique : renvoie false depuis PHP 5.4. Pas de remplacement direct.', 'standard'),
    'get_magic_quotes_runtime' => array('7.4', '8.0', 'Supprimer le branchement historique. Ne pas déséchapper systématiquement les entrées.', 'standard'),
    'money_format' => array('7.4', '8.0', 'NumberFormatter::formatCurrency (intl), locale et devise explicites ; comparer les sorties.', 'standard'),
    'convert_cyr_string' => array('7.4', '8.0', 'mb_convert_encoding ou iconv ; préciser les encodages source/cible et gérer les erreurs.', 'standard'),
    '__autoload' => array('7.2', '8.0', 'spl_autoload_register avec une fonction de chargement ; vérifier ordre et exceptions.', 'standard')
);
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function significant($tokens) {
    $out = array(); $line = 1;
    foreach ($tokens as $t) {
        if (is_array($t)) {
            $line = $t[2];
            if (!in_array($t[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML), true)) {
                $out[] = array($t[0], $t[1], $line);
            }
            $line += substr_count($t[1], "\n");
        } else { $out[] = array(0, $t, $line); }
    }
    return $out;
}
function declaration_kind($id) {
    foreach (array('T_CLASS'=>'classe', 'T_INTERFACE'=>'interface', 'T_TRAIT'=>'trait', 'T_ENUM'=>'enum') as $token=>$label) {
        if (defined($token) && $id === constant($token)) { return $label; }
    }
    return null;
}
function remote_page($url) {
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, array(CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>10, CURLOPT_USERAGENT=>'PHPInsight/1.0', CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2));
        $body = curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
        return array($code, $body === false ? '' : $body);
    }
    $ctx = stream_context_create(array('http'=>array('timeout'=>10, 'ignore_errors'=>true, 'header'=>"User-Agent: PHPInsight/1.0\r\n"), 'ssl'=>array('verify_peer'=>true, 'verify_peer_name'=>true)));
    $body = @file_get_contents($url, false, $ctx); $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) { $code = (int)$m[1]; }
    return array($code, $body === false ? '' : $body);
}
function html_row($cells, $type, $status = '') {
    $s = '<tr data-record data-type="'.h($type).'" data-status="'.h($status).'" class="status-'.h($status).'">';
    foreach ($cells as $cell) { $s .= '<td>'.h($cell).'</td>'; }
    return $s.'</tr>';
}
if (!$source || !is_dir($source)) { fwrite(STDERR, "Dossier sources introuvable.\n"); exit(1); }
$historyPath = $root.'/reports/history.json';
$history = array();
if (is_file($historyPath) && !in_array('--reset-history', $argv, true)) {
    $saved = json_decode(@file_get_contents($historyPath), true);
    if (!is_array($saved) || !isset($saved['version'], $saved['records']) || $saved['version'] !== 1 || !is_array($saved['records'])) {
        fwrite(STDERR, "Historique illisible : sauvegardez reports/history.json puis utilisez --reset-history si nécessaire.\n"); exit(1);
    }
    $history = $saved['records'];
    foreach ($history as $record) {
        if (!is_array($record) || !isset($record['file'], $record['cells']) || !is_string($record['file']) || !is_array($record['cells']) || count($record['cells']) !== 13) {
            fwrite(STDERR, "Historique invalide ; aucun fichier écrasé.\n"); exit(1);
        }
        foreach ($record['cells'] as $value) {
            if (!is_string($value)) { fwrite(STDERR, "Valeur d'historique invalide.\n"); exit(1); }
        }
    }
}
$files = array(); $locks = array(); $errors = array(); $excluded = 0; $readFiles = array();
try {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $f) {
        $relative = substr($f->getPathname(), strlen($source)+1);
        if ($f->isLink() || preg_match('~(^|[\\\\/])(vendor|node_modules|\.git)([\\\\/]|$)~', $relative)) { $excluded++; continue; }
        if (!$f->isFile()) { continue; }
        if (strtolower($f->getExtension()) === 'php') { $files[] = $f->getPathname(); }
        if ($f->getFilename() === 'composer.lock') { $locks[] = $f->getPathname(); }
    }
} catch (Exception $ex) { $errors[] = 'Parcours incomplet : répertoire inaccessible.'; }
sort($files); sort($locks);
$declarations = array(); $calls = array(); $custom = array(); $apiHosts = array(); $dynamic = 0;
foreach ($files as $file) {
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($source)+1));
    $text = @file_get_contents($file);
    if ($text === false) { $errors[] = 'Lecture impossible : '.$relative; continue; }
    $readFiles[$relative] = true;
    $tokens = significant(token_get_all($text)); $namespace = ''; $depth = 0; $class = ''; $classDepth = null; $pendingClass = null;
    for ($i=0, $n=count($tokens); $i<$n; $i++) {
        $t=$tokens[$i]; $prev=$i ? $tokens[$i-1] : array(0,'',0); $next=$i+1<$n ? $tokens[$i+1] : array(0,'',0);
        if ($t[0] === T_NAMESPACE) {
            $namespace=''; for ($j=$i+1; $j<$n && !in_array($tokens[$j][1], array(';','{'),true); $j++) { $namespace.=$tokens[$j][1]; }
        }
        $kind=declaration_kind($t[0]);
        if ($kind && $next[0] === T_STRING && $prev[0] !== T_DOUBLE_COLON) {
            $class=ltrim($namespace.'\\'.$next[1], '\\'); $pendingClass=$class;
            $declarations[]=array($class,$kind,'custom',$relative.':'.$next[2]);
            $custom[strtolower($next[1])]=true;
        }
        if ($t[0] === T_FUNCTION) {
            $j=$i+1; if ($j<$n && $tokens[$j][1]==='&') { $j++; }
            if ($j<$n && $tokens[$j][0] === T_STRING) {
                $name=$tokens[$j][1]; $kind=$classDepth !== null ? 'méthode' : 'fonction';
                $full=$kind==='méthode' ? $class.'::'.$name : ltrim($namespace.'\\'.$name,'\\');
                $declarations[]=array($full,$kind,'custom',$relative.':'.$tokens[$j][2]);
                if ($kind==='fonction') { $custom[strtolower($full)]=true; }
            }
        }
        if ($t[1]==='{') { $depth++; if ($pendingClass!==null) { $classDepth=$depth; $pendingClass=null; } }
        if ($t[1]==='}') { if ($classDepth===$depth) { $classDepth=null; $class=''; } $depth--; }
        if ($next[1]==='(' && $t[0]===T_VARIABLE) { $dynamic++; }
        $nameToken = $t[0]===T_STRING || (defined('T_NAME_FULLY_QUALIFIED') && $t[0]===constant('T_NAME_FULLY_QUALIFIED')) || (defined('T_NAME_QUALIFIED') && $t[0]===constant('T_NAME_QUALIFIED'));
        if ($nameToken && $next[1]==='(' && !in_array($prev[0], array(T_FUNCTION,T_NEW),true) && $prev[1]!=='&') {
            $isMethod=in_array($prev[0],array(T_OBJECT_OPERATOR,T_DOUBLE_COLON),true) || (defined('T_NULLSAFE_OBJECT_OPERATOR') && $prev[0]===constant('T_NULLSAFE_OBJECT_OPERATOR'));
            $calls[]=array('name'=>ltrim($t[1],'\\'), 'namespace'=>$namespace, 'global'=>substr($t[1],0,1)==='\\', 'method'=>$isMethod, 'file'=>$relative, 'location'=>$relative.':'.$t[2]);
        }
    }
    // Ne conserver que les hôtes publics littéraux ; aucune URL complète ou clé.
    if (preg_match_all('~https?://([A-Za-z0-9.-]+)(?=[:/\s\'"?#]|$)~', $text, $matches)) {
        foreach ($matches[1] as $host) { $apiHosts[strtolower($host)]=true; }
    }
}
$builtins=array(); $defined=get_defined_functions();
foreach ($defined['internal'] as $name) { $builtins[strtolower($name)]=true; }
$rows=''; $historical=array(); $unresolved=0; $count=count($declarations); $methodCount=0;
foreach ($declarations as $d) {
    $rows.=html_row(array($d[0],$d[1],$d[2],'code applicatif',$d[3],'sans objet','sans objet','sans objet','sans objet','sans objet','sans objet','Non évaluée ; déclaration recensée','Code local'), 'custom');
}
$grouped=array();
foreach ($calls as $c) {
    $name=strtolower($c['name']); $key=$c['file'].'|'.($c['method']?'method:':'function:').($c['global']?'global:':$c['namespace'].':').$name;
    if (!isset($grouped[$key])) { $grouped[$key]=array($c,array()); }
    $grouped[$key][1][]=$c['location'];
}
$seenHistory = array(); $observedNames = array();
foreach ($grouped as $groupKey => $group) {
    $c=$group[0]; $name=strtolower($c['name']); $where=implode(', ',array_unique($group[1]));
    $observedNames[$c['file'].'|'.$name] = true;
    $namespaced=strtolower(ltrim($c['namespace'].'\\'.$c['name'],'\\'));
    if ($c['method']) { $type='indéterminé'; $methodCount++; }
    elseif (isset($custom[$c['global']?$name:$namespaced])) { $type='custom'; }
    elseif (!$c['global'] && $c['namespace']!=='') { $type='indéterminé'; }
    elseif (isset($catalog[$name]) || isset($builtins[$name])) { $type='natif PHP'; }
    else { $type='indéterminé'; }
    if ($type==='indéterminé') { $unresolved++; }
    $cells=array($c['name'],$c['method']?'appel méthode (cible non résolue)':'appel fonction',$type,'non déterminé',$where,'inconnue','non vérifiée','inconnu','non vérifié','non vérifié','à déterminer','non évaluée','Analyse statique locale');
    if ($type==='custom') { $cells[5]='sans objet'; $cells[6]='sans objet'; $cells[7]='sans objet'; $cells[8]='sans objet'; $cells[9]='sans objet'; $cells[10]='sans objet'; }
    if ($type==='natif PHP') {
        $cells[3]='Disponible sur le PHP du scanner ou catalogue historique';
        $cells[12]='https://www.php.net/manual/en/function.'.str_replace('_','-',$name).'.php';
        if (isset($catalog[$name])) {
            $d=$catalog[$name]; $historical[$name]=true;
            $cells[3]=$d[3]; $cells[5]='Présente en 5.6 selon catalogue ; début exact non vérifié';
            $cells[6]=$d[1]==='7.0'?'5.6.x selon catalogue':'7.4.x selon catalogue';
            $cells[7]='Oui selon catalogue ; à confirmer'; $cells[8]=$d[0].' (catalogue)'; $cells[9]=$d[1].' (catalogue)'; $cells[10]=$d[2];
            $cells[11]='Suppression attendue en '.$d[1].' ; vérifier extension et plateforme';
            if ($verify) {
                $response=remote_page($cells[12]);
                $cells[12].=' — '.date('c').' — HTTP '.$response[0];
                $cells[12].= $response[0]===200 && strpos($response[1], $name)!==false ? ' ; page consultée, historique à confirmer manuellement' : ' ; vérification impossible';
            } else { $cells[12].=' — non consultée (utiliser --verify)'; }
        }
    }
    if ($type === 'natif PHP' && isset($catalog[$name])) {
        $id = hash('sha256', $groupKey);
        $history[$id] = array('file'=>$c['file'], 'cells'=>$cells);
        $seenHistory[$id] = true;
    } else { $rows.=html_row($cells,$type); $count++; }
}
$activeCount = 0; $correctedCount = 0; $unverifiedCount = 0;
foreach ($history as $id => $record) {
    $cells = $record['cells'];
    if (isset($seenHistory[$id])) {
        $status = 'obsolete'; $activeCount++;
        $cells[7] = 'Obsolète — appel toujours présent (catalogue)';
    } elseif (isset($readFiles[$record['file']]) && !isset($observedNames[$record['file'].'|'.strtolower($cells[0])]) && !count($errors) && count($files)) {
        $status = 'corrected'; $correctedCount++;
        $cells[7] = 'Corrigé — appel ancien absent du fichier au dernier scan';
        $cells[11] = 'Historique conservé ; disparition de cet appel observée, remplacement et comportement à valider.';
    } else {
        $status = 'unverified'; $unverifiedCount++;
        $cells[7] = 'À revérifier — fichier absent, illisible ou scan incomplet';
    }
    $rows .= html_row($cells, 'natif PHP', $status); $count++;
}
$security=''; $packages=0;
foreach ($locks as $lock) {
    $data=json_decode(@file_get_contents($lock),true);
    if (!is_array($data)) { $errors[]='composer.lock illisible ou JSON invalide.'; continue; }
    foreach (array('packages','packages-dev') as $section) {
        if (empty($data[$section])) { continue; }
        foreach ($data[$section] as $p) {
            if (!isset($p['name'],$p['version'])) { continue; }
            $packages++;
            $security.=html_row(array($p['name'],$p['version'].' — composer.lock',$section==='packages-dev'?'dev ; direct/transitif non résolu':'direct/transitif non résolu','non recherché','inconnue','non évaluée','à vérifier','Aucune interrogation de base de vulnérabilités','Consulter les avis éditeur/OSV ou lancer composer audit séparément','Fichier lock local ; aucune garantie de sécurité'),'bibliothèque tierce');
        }
    }
}
foreach ($apiHosts as $host=>$unused) { $security.=html_row(array($host,'inconnue','hôte littéral détecté','non recherché','inconnue','non évaluée','à vérifier','Présence textuelle ; intégration et serveur non vérifiés','Vérifier SDK, version API et usages locaux','Code local ; aucun appel API exécuté'),'indéterminé'); }
$template=@file_get_contents($root.'/skills/php-audit/assets/rapport.html');
if ($template===false) { fwrite(STDERR,"Modèle HTML manquant.\n"); exit(1); }
$date=date('c');
$template=str_replace(array('Modèle — audit non exécuté','Projet : non renseigné','PHP cible : non renseigné','Date d’audit : non renseignée'),array('Analyse statique — vérifications partielles','Projet : sources','PHP cible : non évaluée · scanner : '.h(PHP_VERSION),'Date : '.h($date)),$template);
$values=array(count($files),$count,$activeCount.'*','?');
foreach ($values as $v) { $pos=strpos($template,'<span class="value">—</span>'); if ($pos!==false) { $template=substr_replace($template,'<span class="value">'.h($v).'</span>',$pos,strlen('<span class="value">—</span>')); } }
$template=str_replace('À mesurer lors de l’analyse','Fichiers PHP trouvés',$template);
$template=str_replace('À vérifier sur php.net','* Selon catalogue historique',$template);
$template=str_replace('Selon les avis consultés','Avis de sécurité non interrogés',$template);
$template=str_replace(array('{{ACTIVE}}','{{CORRECTED}}','{{UNVERIFIED}}'), array($activeCount,$correctedCount,$unverifiedCount), $template);
if ($rows!=='') { $template=str_replace('<tr><td colspan="13" class="empty">L’inventaire apparaîtra ici après l’analyse d’un projet PHP.</td></tr>',$rows,$template); }
if ($security!=='') { $template=str_replace('<tr><td colspan="10" class="empty">Aucune dépendance vérifiée : l’audit n’a pas encore été exécuté.</td></tr>',$security,$template); }
else { $template=str_replace('Aucune dépendance vérifiée : l’audit n’a pas encore été exécuté.','Aucun paquet Composer ni hôte API littéral trouvé ; aucune conclusion de sécurité possible.',$template); }
$template=str_replace('Aucune recommandation établie pour le moment.','Examiner les fonctions historiques et leurs remplacements ; confirmer chaque version sur php.net. Examiner les symboles indéterminés avant toute conclusion.',$template);
$method='Lecture par tokenizer PHP, sans exécution du projet. '.count($files).' fichiers trouvés ; '.count($declarations).' déclarations custom ; '.count($grouped).' groupes d’appels ; '.$unresolved.' groupes non résolus, dont '.$methodCount.' appels méthode ; '.$dynamic.' appels variables repérés ; '.$packages.' entrées de paquets ; '.$excluded.' entrées exclues. Erreurs : '.count($errors).'. '.implode(' ',$errors);
$template=str_replace('Périmètre, exclusions, commandes et résultats : à renseigner.',h($method),$template);
$template=str_replace('Sources prévues : php.net, avis éditeurs, GitHub Security Advisories et OSV. Aucune consultation réalisée dans ce modèle.',h($verify?'Option --verify : tentatives HTTPS sur les pages des fonctions historiques, état HTTP affiché par ligne. Les dates du catalogue ne sont pas validées automatiquement.':'Mode hors ligne : références php.net fournies, aucune consultation réalisée. Utiliser --verify pour tenter leur accès.'). ' Aucune base CVE/GHSA/OSV interrogée.',$template);
$template=str_replace('Documenter les symboles dynamiques non résolus, les versions inconnues et les sources inaccessibles.','Analyse partielle : pas de résolution complète de types, héritage, alias use, dépendances ou appels dynamiques. Une fonction absente du runtime et du catalogue reste indéterminée. Les branches conditionnelles, namespaces et classes anonymes peuvent nécessiter une revue manuelle. Les extensions installées sur le scanner influencent les fonctions natives reconnues. Les versions cibles et signatures ne sont pas testées. Les chemins et noms de symboles restent visibles dans ce rapport local.',$template);
$out=$root.'/reports';
if (!is_dir($out) && !@mkdir($out,0700,true)) { fwrite(STDERR,"Impossible de créer reports.\n"); exit(1); }
if (@file_put_contents($out.'/rapport-audit-php.html',$template)===false) { fwrite(STDERR,"Écriture du rapport impossible.\n"); exit(1); }
$historyJson = json_encode(array('version'=>1, 'records'=>$history), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($historyJson === false || @file_put_contents($historyPath.'.tmp', $historyJson, LOCK_EX) === false || !@rename($historyPath.'.tmp', $historyPath)) {
    fwrite(STDERR, "Rapport créé mais sauvegarde de l'historique impossible.\n"); exit(1);
}
echo "Rapport : ".$out."/rapport-audit-php.html\n";
echo count($files)." fichiers PHP ; ".count($historical)." fonctions historiques ; ".$unresolved." groupes d'appels non résolus.\n";
echo "Analyse partielle, sans exécution PHP ni audit CVE automatique.\n";
echo $activeCount." obsolètes ; ".$correctedCount." corrigés (appels absents) ; ".$unverifiedCount." à revérifier.\n";
if (!count($files)) { fwrite(STDERR,"Aucun fichier PHP dans sources.\n"); exit(2); }
exit(count($errors)?1:0);
