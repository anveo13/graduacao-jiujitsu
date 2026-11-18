<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
// Uso: php bin/create-user.php [usuario] [nome]. Cria ou atualiza um usuário Master (pode haver mais de um).
$username=trim($argv[1]??'gbocidental');$name=trim($argv[2]??$username);
if(preg_match('/^[A-Za-z0-9._-]{3,60}$/',$username)!==1){fwrite(STDERR,"Invalid username (3-60 letters, numbers, dot, dash or underscore).\n");exit(1);}
if($name===''||mb_strlen($name,'UTF-8')>100){fwrite(STDERR,"Invalid name.\n");exit(1);}
fwrite(STDOUT,'Password (at least 8 characters): ');
if(PHP_OS_FAMILY!=='Windows')shell_exec('stty -echo');
$password=rtrim((string)fgets(STDIN),"\r\n");
if(PHP_OS_FAMILY!=='Windows')shell_exec('stty echo');
fwrite(STDOUT,"\n");
if(strlen($password)<8){fwrite(STDERR,"Password must contain at least 8 characters.\n");exit(1);}
$stmt=db()->prepare("INSERT INTO users(name,username,password_hash,role,active) VALUES(?,?,?,'master',1) ON DUPLICATE KEY UPDATE name=VALUES(name),password_hash=VALUES(password_hash),role='master',active=1");
$stmt->execute([$name,$username,password_hash($password,PASSWORD_DEFAULT)]);
fwrite(STDOUT,"Master user {$username} created/updated.\n");
