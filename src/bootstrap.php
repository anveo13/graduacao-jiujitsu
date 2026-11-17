<?php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');
$root=dirname(__DIR__);$envFile=$root.DIRECTORY_SEPARATOR.'.env';
if(is_file($envFile))foreach(file($envFile,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){$line=trim($line);if($line===''||str_starts_with($line,'#')||!str_contains($line,'='))continue;[$key,$value]=explode('=',$line,2);$_ENV[trim($key)]=trim(trim($value)," \t\n\r\0\x0B\"'");}
date_default_timezone_set($_ENV['APP_TIMEZONE']??'America/Sao_Paulo');$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';session_name('graduacao_session');session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']);if(session_status()!==PHP_SESSION_ACTIVE)session_start();
function db():PDO{static $pdo;if($pdo instanceof PDO)return $pdo;$host=$_ENV['DB_HOST']??'127.0.0.1';$port=$_ENV['DB_PORT']??'3306';$name=$_ENV['DB_NAME']??'graduacao';return $pdo=new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",$_ENV['DB_USER']??'',$_ENV['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}
function h(string $value):string{return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function redirect(string $path):never{header('Location: '.$path,true,303);exit;}
