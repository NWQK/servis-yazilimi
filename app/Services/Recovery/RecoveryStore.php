<?php
namespace App\Services\Recovery;
use Illuminate\Support\Facades\{DB,Schema};
use PDO;

/** Private, disk-backed staging. SQL from the archive is never sent to this connection. */
class RecoveryStore
{
    public PDO $pdo;
    private array $columns=[];
    public function __construct(public string $path)
    {
        if(!in_array('sqlite',PDO::getAvailableDrivers(),true))throw new RecoveryFailure('Sunucuda PHP PDO SQLite eklentisi kurulmalıdır.');
        $new=!is_file($path);$this->pdo=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        if($new){
            chmod($path,0600);
            $this->pdo->exec('CREATE TABLE entries (t TEXT NOT NULL,k TEXT NOT NULL,parent TEXT,owner TEXT,ref TEXT,data TEXT NOT NULL,PRIMARY KEY(t,k))');
            $this->pdo->exec('CREATE INDEX entry_parent ON entries(t,parent)');
            $this->pdo->exec('CREATE INDEX entry_owner ON entries(t,owner)');
            $this->pdo->exec('CREATE TABLE meta (t TEXT PRIMARY KEY,columns_json TEXT NOT NULL)');
        }
        foreach($this->pdo->query('SELECT * FROM meta') as $row)$this->columns[$row['t']]=json_decode($row['columns_json'],true,512,JSON_THROW_ON_ERROR);
    }
    public function schema(string $table,array $columns): void
    {
        if(!in_array($table,RecoveryTables::all(),true))throw new RecoveryFailure('Geçersiz hazırlama tablosu.');
        foreach($columns as $column)if(!preg_match('/\A[a-zA-Z0-9_]+\z/',$column))throw new RecoveryFailure('Yedekte geçersiz sütun adı.');
        $this->columns[$table]=$columns;$s=$this->pdo->prepare('INSERT OR REPLACE INTO meta VALUES (?,?)');$s->execute([$table,json_encode($columns,JSON_THROW_ON_ERROR)]);
    }
    public function has(string $table): bool{return isset($this->columns[$table]);}
    public function columns(string $table): array{return $this->columns[$table]??[];}
    public function put(string $table,array $row): void
    {
        $key=$row[RecoveryTables::key($table)]??null;
        if($key===null||!preg_match('/\A[0-9]+\z/',(string)$key))throw new RecoveryFailure('Yedekte geçersiz kayıt kimliği.');
        $s=$this->pdo->prepare('INSERT INTO entries(t,k,parent,owner,ref,data) VALUES (?,?,?,?,?,?)');
        $s->execute([$table,(string)$key,$row['parent_id']??null,$row['owner_id']??null,$row['appointment_profile_id']??null,json_encode($row,JSON_THROW_ON_ERROR)]);
    }
    public function rows(string $table,?int $owner=null): \Generator
    {
        $where='t=?';$args=[$table];
        if($owner!==null){
            if($table==='users'){$where.=' AND (k=? OR parent=?)';$args[]=(string)$owner;$args[]=(string)$owner;}
            elseif(in_array($table,RecoveryTables::PARENT,true)){$where.=' AND parent=?';$args[]=(string)$owner;}
            elseif(in_array($table,RecoveryTables::OWNER,true)){$where.=' AND owner=?';$args[]=(string)$owner;}
            else {$where.=' AND ref IN (SELECT k FROM entries WHERE t=? AND owner=?)';$args[]='appointment_profiles';$args[]=(string)$owner;}
        }
        $s=$this->pdo->prepare('SELECT data FROM entries WHERE '.$where.' ORDER BY CAST(k AS INTEGER)');$s->execute($args);
        while(($data=$s->fetchColumn())!==false)yield json_decode($data,true,512,JSON_THROW_ON_ERROR);
    }
    public function get(string $table,$id): ?array
    {
        $s=$this->pdo->prepare('SELECT data FROM entries WHERE t=? AND k=?');$s->execute([$table,(string)$id]);$data=$s->fetchColumn();
        return $data===false?null:json_decode($data,true,512,JSON_THROW_ON_ERROR);
    }
    public function import(string $sql): void
    {
        $reader=new SqlDumpReader();$this->pdo->beginTransaction();$count=0;
        try {
            foreach($reader->statements($sql) as $statement) {
                if(preg_match('/\ACREATE TABLE\s+`([a-zA-Z0-9_]+)`\s*\(/i',$statement,$m)){
                    if(!in_array($m[1],RecoveryTables::all(),true))continue;
                    preg_match_all('/^\s*`([a-zA-Z0-9_]+)`\s+/m',$statement,$columns);
                    if(!$columns[1])throw new RecoveryFailure('Yedekteki tablo sütunları okunamadı.');
                    $this->schema($m[1],$columns[1]);
                } elseif(preg_match('/\AINSERT INTO\s+`([a-zA-Z0-9_]+)`\s+VALUES\s*(.*)\z/is',$statement,$m)){
                    if(!in_array($m[1],RecoveryTables::all(),true))continue;
                    if(!$this->has($m[1]))throw new RecoveryFailure('Yedekte tablo tanımı eksik.');
                    foreach($reader->rows($m[2]) as $values){
                        if(count($values)!==count($this->columns($m[1])))throw new RecoveryFailure('Yedekte sütun sayısı uyuşmuyor.');
                        $this->put($m[1],array_combine($this->columns($m[1]),$values));
                        if(++$count%1000===0){$this->pdo->commit();$this->pdo->beginTransaction();}
                    }
                } elseif(preg_match('/\A(?:INSERT|REPLACE|UPDATE|DELETE)\b/i',$statement)){
                    // Never mistake an unsupported data statement for an empty table.
                    throw new RecoveryFailure('Yedek SQL veri biçimi desteklenmiyor.');
                }
            }
            $this->pdo->commit();
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function tenant(int $owner,string $path): self
    {
        $target=new self($path);$profiles=[];
        foreach($this->rows('appointment_profiles',$owner) as $profile)$profiles[]=(string)$profile['id'];
        $target->pdo->beginTransaction();
        foreach(RecoveryTables::all() as $table) {
            if(!$this->has($table))continue;$target->schema($table,$this->columns($table));
            foreach($this->rows($table,$owner) as $row)if(RecoveryTables::belongs($table,$row,$owner,$profiles))$target->put($table,$row);
        }
        $target->pdo->commit();return $target;
    }
    public static function live(int $owner,string $path): self
    {
        $store=new self($path);$store->pdo->beginTransaction();
        foreach(RecoveryTables::existing() as $table) {
            $store->schema($table,Schema::getColumnListing($table));
            foreach(RecoveryTables::live($table,$owner)->orderBy(RecoveryTables::key($table))->cursor() as $value){
                $row=(array)$value;
                if($table==='settings'&&!RecoveryTables::setting($row['name']))continue;
                $store->put($table,$row);
            }
        }
        $store->pdo->commit();return $store;
    }
    public function fingerprint(int $owner): string
    {
        $hash=hash_init('sha256');
        foreach(RecoveryTables::all() as $table)foreach($this->rows($table) as $row){
            if($table==='users'&&(int)$row['id']===$owner)$row=array_intersect_key($row,array_flip(['id','type','created_at']));
            ksort($row);foreach($row as &$value)if($value!==null)$value=(string)$value;unset($value);
            hash_update($hash,$table.json_encode($row,JSON_THROW_ON_ERROR));
        }
        return hash_final($hash);
    }
}
