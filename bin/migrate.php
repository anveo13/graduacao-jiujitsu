<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$pdo=db();$pdo->exec("CREATE TABLE IF NOT EXISTS migrations(name VARCHAR(190) PRIMARY KEY,applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");$applied=$pdo->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);$files=glob(dirname(__DIR__).'/migrations/*.sql')?:[];sort($files,SORT_STRING);
foreach($files as $file){$name=basename($file);if(in_array($name,$applied,true))continue;echo "Aplicando {$name}... ";$sql=file_get_contents($file);if($sql===false)throw new RuntimeException("Falha ao ler {$name}");$pdo->beginTransaction();try{foreach(array_filter(array_map('trim',explode(';',$sql))) as $statement)$pdo->exec($statement);$stmt=$pdo->prepare('INSERT INTO migrations(name) VALUES(?)');$stmt->execute([$name]);$pdo->commit();echo "ok\n";}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}
echo "Migrations concluídas.\n";
