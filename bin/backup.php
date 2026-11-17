<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';

$config=$_ENV;
foreach(['DB_NAME','DB_USER','DB_PASSWORD'] as $key){if(!isset($config[$key])||$config[$key]===''){fwrite(STDERR,"Configuração ausente: {$key}\n");exit(1);}}
$host=$config['DB_HOST']??'127.0.0.1';$port=$config['DB_PORT']??'3306';
$dump=trim((string)shell_exec('command -v mariadb-dump 2>/dev/null'));
if($dump==='')$dump=trim((string)shell_exec('command -v mysqldump 2>/dev/null'));
if($dump===''){fwrite(STDERR,"mariadb-dump não encontrado.\n");exit(1);}
$directory=is_dir('/var/backups/graduacao')?'/var/backups/graduacao':dirname(__DIR__).'/storage/backups';
if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory)){fwrite(STDERR,"Não foi possível criar a pasta de backup.\n");exit(1);}
$stamp=(new DateTimeImmutable('now'))->format('Y-m-d_H-i-s');$tmpConfig=tempnam(sys_get_temp_dir(),'graduacao-db-');$tmpSql=$directory.'/graduacao-'.$stamp.'.sql';$tmpGz=$tmpSql.'.gz';
if($tmpConfig===false){fwrite(STDERR,"Não foi possível preparar as credenciais do backup.\n");exit(1);}
chmod($tmpConfig,0600);
$quote=static fn(string $value):string=>'"'.str_replace(['\\','"'],['\\\\','\\"'],$value).'"';
$contents="[client]\nhost={$quote($host)}\nport={$quote($port)}\nuser={$quote($config['DB_USER'])}\npassword={$quote($config['DB_PASSWORD'])}\n";
file_put_contents($tmpConfig,$contents,LOCK_EX);
$command=escapeshellarg($dump).' --defaults-extra-file='.escapeshellarg($tmpConfig).' --single-transaction --routines --triggers --databases '.escapeshellarg($config['DB_NAME']).' > '.escapeshellarg($tmpSql).' 2>&1';
exec($command,$output,$status);@unlink($tmpConfig);
if($status!==0){@unlink($tmpSql);fwrite(STDERR,"Falha ao gerar o dump do banco.\n");exit(1);}
$gzip=trim((string)shell_exec('command -v gzip 2>/dev/null'));
if($gzip===''){@unlink($tmpSql);fwrite(STDERR,"gzip não encontrado.\n");exit(1);}
exec(escapeshellarg($gzip).' -c '.escapeshellarg($tmpSql).' > '.escapeshellarg($tmpGz).' 2>&1',$output,$status);@unlink($tmpSql);
if($status!==0){@unlink($tmpGz);fwrite(STDERR,"Falha ao compactar o backup.\n");exit(1);}
chmod($tmpGz,0600);
$cutoff=time()-(7*86400);
foreach(glob($directory.'/graduacao-*.sql.gz')?:[] as $oldBackup){if(is_file($oldBackup)&&(filemtime($oldBackup)?:0)<$cutoff)@unlink($oldBackup);}
echo "Backup criado: {$tmpGz}\n";
