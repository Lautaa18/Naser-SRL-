<?php
/** Bootstrap regression: local QA MariaDB only; every case uses a disposable database. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1]??'')!=='--case') putenv('APP_ENV=dev');
require __DIR__.'/../../php/config/app.php';
require __DIR__.'/../../php/config/bootstrap.php';
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
$server=new PDO('mysql:host=127.0.0.1;port=13379;charset=utf8mb4','root','',$opts);
$mode=$argv[1]??'';
if ($mode==='--case') {
    $name=$argv[2]??'';
    if (!preg_match('/^naser_qa_boot_[a-f0-9]{10}$/',$name)) throw new RuntimeException('Only disposable QA databases.');
    $pdo=new PDO('mysql:host=127.0.0.1;port=13379;dbname='.$name.';charset=utf8mb4','root','',$opts);
    naser_bootstrap($pdo);
    exit;
}
$total=0;
function check(bool $ok,string $label):void { global $total; if (!$ok) throw new RuntimeException('FAIL: '.$label); echo 'OK: '.$label.PHP_EOL; $total++; }
foreach (['cli','configured','invalid'] as $case) {
    $name='naser_qa_boot_'.bin2hex(random_bytes(5));
    $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    try {
        $env=getenv();$env['APP_ENV']='production';$env['INITIAL_ADMIN_EMAIL']=$case==='cli'?'':'initial@example.test';
        $env['INITIAL_ADMIN_PASSWORD']=$case==='configured'?'QA-bootstrap-password-42795':($case==='invalid'?'short':'');
        $p=proc_open([PHP_BINARY,__FILE__,'--case',$name],[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
        if (!is_resource($p)) throw new RuntimeException('Could not start QA process.');
        $output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);
        $db=new PDO('mysql:host=127.0.0.1;port=13379;dbname='.$name.';charset=utf8mb4','root','',$opts);
        check((int)$db->query("SELECT COUNT(*) FROM usuarios WHERE email='admin@naser.test'")->fetchColumn()===0,$case.': production never seeds known demo credentials');
        if ($case==='invalid') {
            check($code!==0,'Invalid initial credentials stop bootstrap');
            try { $marked=(int)$db->query("SELECT COUNT(*) FROM sistema_config WHERE clave='schema_version'")->fetchColumn(); }
            catch (PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1146) throw $e; $marked=0; }
            check($marked===0,'A failed bootstrap does not mark migration complete');
        } else {
            if ($code!==0) throw new RuntimeException($output);
            check($db->query("SELECT valor FROM sistema_config WHERE clave='schema_version'")->fetchColumn()===NASER_SCHEMA_VERSION,$case.': schema completes');
            $admins=$db->query("SELECT email,password,debe_cambiar_password FROM usuarios WHERE rol='admin'")->fetchAll();
            if ($case==='cli') check($admins===[],'CLI provisioning remains available without optional initial credentials');
            else check(count($admins)===1&&$admins[0]['email']==='initial@example.test'&&password_verify($env['INITIAL_ADMIN_PASSWORD'],$admins[0]['password'])&&(int)$admins[0]['debe_cambiar_password']===1,'Explicit first administrator uses own password and must change it');
        }
    } finally { $db=null;$server->exec("DROP DATABASE `$name`"); }
}
echo 'TOTAL: '.$total.' production bootstrap checks passed.'.PHP_EOL;