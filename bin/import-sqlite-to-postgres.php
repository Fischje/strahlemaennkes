<?php
/** One-time importer from the legacy SQLite database into the clean PostgreSQL schema. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR,"CLI only.\n"); exit(1); }
$source=$argv[1] ?? (getenv('SQLITE_PATH') ?: __DIR__.'/../data/strahlemaennkes.sqlite');
if(!in_array('--confirm-import',$argv,true)){fwrite(STDERR,"Safety stop: this replaces all app data in PostgreSQL. Re-run with --confirm-import after creating backups.\n");exit(2);}
if(!is_file($source)){fwrite(STDERR,"SQLite source not found: {$source}\n");exit(1);}
$config=require __DIR__.'/../config.php'; $db=$config['db'];
$sqliteDsn = 'sqlite:file:' . $source . '?mode=ro&immutable=1';
$src = new PDO($sqliteDsn, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$dst=new PDO("pgsql:host={$db['host']};port={$db['port']};dbname={$db['name']};sslmode={$db['sslmode']}",$db['user'],$db['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$dst->exec("SET TIME ZONE 'UTC'");
$tables=['sektbar_activated_drinks','sektbar_state','guest_drink_orders','user_drink_favorites','user_drink_preferences','drink_reminders','drink_status_resets','fines','push_subscriptions','password_reset_tokens','remember_tokens','cash_balance_history','cash_settings','chronicle_years','spiess_delegations','assembly_status','feature_settings','app_settings','drinks','users'];
$insertOrder=array_reverse($tables);
function srcTableExists(PDO $db,string $table):bool{$s=$db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$s->execute([$table]);return(bool)$s->fetchColumn();}
function dstColumns(PDO $db,string $table):array{$s=$db->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name=? ORDER BY ordinal_position");$s->execute([$table]);return $s->fetchAll(PDO::FETCH_COLUMN);}
$dst->beginTransaction();
try{
  $dst->exec('TRUNCATE TABLE '.implode(',',$tables).' RESTART IDENTITY CASCADE');
  foreach($insertOrder as $table){
    if(!srcTableExists($src,$table)) continue;
    $rows=$src->query('SELECT * FROM "'.$table.'"')->fetchAll();
    if(!$rows) continue;
    $allowed=array_flip(dstColumns($dst,$table));
    foreach($rows as $row){
    // Legacy round dispatch timestamps were historically stored in
    // last_dispatched_at. Preserve them in the clean PostgreSQL
    // dispatched_at column when dispatched_at itself is empty.
    if (
        $table === 'fines'
        && empty($row['dispatched_at'])
        && !empty($row['last_dispatched_at'])
    ) {
        $row['dispatched_at'] = $row['last_dispatched_at'];
    }

    $row=array_intersect_key($row,$allowed); // drops obsolete SQLite columns such as fines.amount/rounds_count/rounds_given/last_dispatched_at
      if(!$row) continue;
      $cols=array_keys($row); $q=implode(',',array_fill(0,count($cols),'?'));
      $sql='INSERT INTO "'.$table.'" ('.implode(',',array_map(fn($c)=>'"'.$c.'"',$cols)).') VALUES ('.$q.')';
      $dst->prepare($sql)->execute(array_values($row));
    }
    echo $table.': '.count($rows)." row(s)\n";
  }
  // Restore mandatory singleton/default rows if an old installation did not contain them.
  $dst->exec("INSERT INTO cash_settings(id,monthly_fee) VALUES(1,0) ON CONFLICT(id) DO NOTHING");
  $dst->exec("INSERT INTO app_settings(setting_key,setting_value) VALUES('registration_enabled','0') ON CONFLICT(setting_key) DO NOTHING");
  $dst->exec("INSERT INTO feature_settings(feature_key,is_enabled) VALUES('sektbar',0) ON CONFLICT(feature_key) DO NOTHING");
  $dst->exec("INSERT INTO sektbar_state(id,is_active) VALUES(1,0) ON CONFLICT(id) DO NOTHING");
  if((int)$dst->query("SELECT COUNT(*) FROM drinks")->fetchColumn()===0){$dst->exec("INSERT INTO drinks(name,emoji,is_active,is_approved,sort_order) VALUES ('Alt','🍺',1,1,10),('Pils','🍻',1,1,20),('Radler','🍋',1,1,30),('Cola','🥤',1,1,40),('Wasser','💧',1,1,50)");}
  foreach(['users','drinks','drink_status_resets','drink_reminders','fines','remember_tokens','password_reset_tokens','push_subscriptions','chronicle_years','cash_balance_history','spiess_delegations','guest_drink_orders'] as $table){
    $seq=$dst->query("SELECT pg_get_serial_sequence('{$table}','id')")->fetchColumn();
    if($seq){$max=(int)$dst->query('SELECT COALESCE(MAX(id),0) FROM "'.$table.'"')->fetchColumn(); if($max>0)$dst->exec("SELECT setval(".$dst->quote($seq).",{$max},true)");}
  }
  $dst->commit(); echo "Import complete.\n";
}catch(Throwable $e){if($dst->inTransaction())$dst->rollBack();fwrite(STDERR,"Import failed: ".$e->getMessage()."\n");exit(1);}
