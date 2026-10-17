<?php
namespace App\Services\Recovery;
use Illuminate\Support\Facades\{DB,Schema,Hash};

class RecoveryPlan
{
    public function build(RecoveryStore $source,RecoveryStore $live,int $owner,string $mode): array
    {
        \App\Services\BranchTransfers::guardRecovery($owner);
        $original=$source->get('users',$owner);$present=$live->get('users',$owner);
        if(!$original||!$present||($original['type']??'')!=='owner'||($present['type']??'')!=='owner'
            ||(!empty($original['created_at'])&&!empty($present['created_at'])&&$original['created_at']!==$present['created_at']))
            throw new RecoveryFailure('Yedekteki işletme kimliği güncel işletmeyle uyuşmuyor.');
        foreach(['users','clients','vehicles','services','invoices','items'] as $required)
            if(!$source->has($required))throw new RecoveryFailure('Yedek eksik: '.$required.' tablo tanımı bulunmuyor.');
        $summary=['tables'=>[],'conflicts'=>[],'insert'=>0,'update'=>0,'delete'=>0,'revive'=>0,'files'=>0];$financialMissing=false;
        foreach(RecoveryTables::existing() as $table){
            $counts=['backup'=>0,'current'=>0,'insert'=>0,'update'=>0,'delete'=>0,'revive'=>0];
            foreach($live->rows($table) as $row){
                if($table==='users'&&(int)$row['id']===$owner)continue;
                $counts['current']++;
                if($mode!=='missing'&&!$source->get($table,$row[RecoveryTables::key($table)])
                    &&!($table==='users'&&($row['type']??'')!=='client'))$counts['delete']++;
            }
            foreach($source->rows($table) as $row){
                if($table==='users'&&(int)$row['id']===$owner)continue;
                $counts['backup']++;$id=$row[RecoveryTables::key($table)];$current=$live->get($table,$id);
                $any=DB::table($table)->where(RecoveryTables::key($table),$id)->first();
                if($any&&!$current){$this->conflict($summary,$table.' #'.$id.': kayıt kimliği başka işletmeye ait.');continue;}
                if($table==='users'&&$current&&($current['type']??'')!==($row['type']??''))$this->conflict($summary,'Hesap türü değişmiş: users #'.$id.'. Hesap güvenliği otomatik geçmişe döndürülemez.');
                if($table==='users'&&$current&&($current['type']??'')!=='client')continue;
                if(!$current){$counts['insert']++;if(in_array($table,RecoveryTables::FINANCIAL,true))$financialMissing=true;}
                elseif($mode==='missing'){if($this->revivable($table,$row,$current))$counts['revive']++;}
                elseif(!$this->same($row,$current))$counts['update']++;
                if(!$current||$mode!=='missing')$this->unique($table,$row,$live,$owner,$mode,$summary);
                $this->references($table,$row,$source,$live,$owner,$mode,$summary);
            }
            foreach(['insert','update','delete','revive'] as $key)$summary[$key]+=$counts[$key];
            if(array_sum($counts)>0)$summary['tables'][$table]=$counts;
        }
        if($mode==='missing'&&$financialMissing){
            foreach(RecoveryTables::FINANCIAL as $table)foreach($live->rows($table) as $row)
                if(!$source->get($table,$row[RecoveryTables::key($table)])){
                    $this->conflict($summary,'Yedekten sonra yeni mali hareket var: '.$table.'. Silinen mali kayıtlar mevcut hareketlere karıştırılmadan otomatik kurtarılamaz.');
                    break;
                }
            foreach(array_merge(RecoveryTables::FINANCIAL,['taxes','service_types']) as $table)
                foreach($source->rows($table) as $row){
                    $current=$live->get($table,$row[RecoveryTables::key($table)]);
                    if($current&&!$this->same($row,$current)){
                        $this->conflict($summary,'Stok/finans uyuşmazlığı: '.$table.' #'.$row[RecoveryTables::key($table)].'. Mevcut mali kayıtlar ezilmeden otomatik birleştirme yapılamaz.');
                        break;
                    }
                }
        }
        $this->chassisConflicts($source,$live,$mode,$summary);
        if($mode!=='missing')$this->incomingForeignKeys($owner,$summary);
        return $summary;
    }
    private function chassisConflicts(RecoveryStore $source,RecoveryStore $live,string $mode,array &$summary): void
    {
        $seen=[];
        $check=function(array $row)use(&$seen,&$summary){
            if(!empty($row['deleted_at']))return;
            $vin=\App\Services\ChassisNumber::normalize($row['chassis_no']??null);
            if(!\App\Services\ChassisNumber::valid($vin))return;
            if(isset($seen[$vin]))$this->conflict($summary,'Aynı işletmede mükerrer şasi oluşacak: araç #'.$seen[$vin].' ve #'.$row['id'].'. Önce araç kayıtlarını düzeltin.');
            $seen[$vin]=$row['id'];
        };
        if($mode==='missing'){
            foreach($live->rows('vehicles') as $row){
                $saved=$source->get('vehicles',$row['id']);
                if($saved&&$this->revivable('vehicles',$saved,$row))$row['deleted_at']=null;
                $check($row);
            }
            foreach($source->rows('vehicles') as $row)if(!$live->get('vehicles',$row['id']))$check($row);
        }else foreach($source->rows('vehicles') as $row)$check($row);
    }
    private function incomingForeignKeys(int $owner,array &$summary): void
    {
        // DELETE/INSERT replacement must not SET NULL or CASCADE a foreign business's rows.
        foreach(['items'=>['category_id'=>'item_categories'],'vehicle_qr_codes'=>['vehicle_id'=>'vehicles']] as $child=>$relations){
            foreach($relations as $field=>$parent){
                if(!Schema::hasTable($child)||!Schema::hasTable($parent))continue;
                if(DB::table($child)->where('parent_id','!=',$owner)
                    ->whereIn($field,RecoveryTables::live($parent,$owner)->select(RecoveryTables::key($parent)))->exists())
                    $this->conflict($summary,'İşletmeler arası hatalı ilişki var: '.$child.'.'.$field.'. Başka işletmenin kaydını korumak için kurtarma durduruldu.');
            }
        }
        if(DB::table('appointment_profiles')->whereIn('owner_id',DB::table('users')->where('parent_id',$owner)->where('type','client')->select('id'))->exists())
            $this->conflict($summary,'Müşteri hesabına bağlı işletme profili bulundu. Hesap ilişkisi düzeltilmeden kurtarma yapılamaz.');
    }
    private function conflict(array &$summary,string $text): void
    {
        if(count($summary['conflicts'])<30&&!in_array($text,$summary['conflicts'],true))$summary['conflicts'][]=$text;
    }
    private function same(array $a,array $b): bool
    {
        foreach($a as $key=>$value){
            if(in_array($key,['created_at','updated_at','revision','customer_email_sent_at','customer_email_claimed_at','customer_email_error'],true))continue;
            if(!array_key_exists($key,$b))continue;
            $other=$b[$key];
            if($value===null||$other===null){if($value!==$other)return false;continue;}
            if(is_numeric($value)&&is_numeric($other)&&(preg_match('/(?:_id|amount|quantity|price|rate|mileage|limit)\z/',$key)
                ||in_array($key,['id','client','vehicle','service','type','brand','unit','assign','status','is_active'],true))){
                if($this->number((string)$value)!==$this->number((string)$other))return false;
            }
            elseif((string)$value!==(string)$other)return false;
        }
        return true;
    }
    private function number(string $value): string
    {
        preg_match('/\A([+-]?)([0-9]*)(?:\.([0-9]*))?(?:[eE]([+-]?[0-9]+))?\z/',$value,$m);
        $int=$m[2]??'';$fraction=$m[3]??'';$exponent=(int)($m[4]??0);
        if(abs($exponent)>1000)throw new RecoveryFailure('Sayısal yedek değeri desteklenen aralığın dışında.');
        $digits=$int.$fraction;$point=strlen($int)+$exponent;
        if($point<0){$digits=str_repeat('0',-$point).$digits;$point=0;}
        if($point>strlen($digits))$digits.=str_repeat('0',$point-strlen($digits));
        $whole=ltrim(substr($digits,0,$point),'0')?:'0';$decimal=rtrim(substr($digits,$point),'0');
        return (($m[1]??'')==='-'&&($whole!=='0'||$decimal!=='')?'-':'').$whole.($decimal!==''?'.'.$decimal:'');
    }
    public function revivable(string $table,array $source,array $current): bool
    {
        return ($table==='vehicles'&&empty($source['deleted_at'])&&!empty($current['deleted_at']))
            ||($table==='users'&&($source['type']??'')==='client'&&empty($source['client_archived_at'])&&!empty($current['client_archived_at']));
    }
    private function references(string $table,array $row,RecoveryStore $source,RecoveryStore $live,int $owner,string $mode,array &$summary): void
    {
        $map=[
            'clients'=>['user_id'=>['users',true]],'employees'=>['user_id'=>['users',true]],
            'vehicle_brands'=>['type'=>['vehicle_types',true]],
            'vehicles'=>['client'=>['users',true],'type'=>['vehicle_types',false],'brand'=>['vehicle_brands',false]],
            'vehicle_qr_codes'=>['vehicle_id'=>['vehicles',false]],
            'items'=>['category_id'=>['item_categories',false],'supplier_id'=>['suppliers',false],'unit'=>['units',false]],
            'services'=>['vehicle'=>['vehicles',true],'client'=>['users',true],'assign'=>['users',false]],
            'service_items'=>['service_id'=>['services',true],'type_id'=>['service_types',true]],
            'service_photos'=>['service_id'=>['services',true]],
            'invoices'=>['service'=>['services',true],'client'=>['users',true]],
            'invoice_items'=>['invoice_id'=>['invoices',true],'item'=>['items',false]],
            'invoice_services'=>['invoice_id'=>['invoices',true],'service_type'=>['service_types',true]],
            'invoice_payments'=>['invoice_id'=>['invoices',true]],
            'quotations'=>['vehicle_id'=>['vehicles',true],'client_id'=>['users',true]],
            'quotation_items'=>['quotation_id'=>['quotations',true],'item'=>['items',false]],
            'quotation_services'=>['quotation_id'=>['quotations',true],'service_type'=>['service_types',false]],
            'appointments'=>['appointment_profile_id'=>['appointment_profiles',true]],
            'booking_offerings'=>['appointment_profile_id'=>['appointment_profiles',true]],
            'business_reviews'=>['customer_id'=>['users',true]],
            'stock_movements'=>['invoice_item_id'=>['invoice_items',false],'invoice_id'=>['invoices',false]],
            'counter_sales'=>['item_id'=>['items',false],'stock_movement_id'=>['stock_movements',false]],
            'item_purchases'=>['item_id'=>['items',false],'supplier_id'=>['suppliers',false],'stock_movement_id'=>['stock_movements',false]],
            'expenses'=>['stock_movement_id'=>['stock_movements',false]],
        ];
        foreach($map[$table]??[] as $field=>[$target,$required]){
            $id=$row[$field]??null;if(!$id)continue;
            if($target==='users'&&(int)$id===$owner)continue;
            $saved=$source->get($target,$id);$current=$live->get($target,$id);
            if(DB::table($target)->where(RecoveryTables::key($target),$id)->exists()&&!$current){
                $this->conflict($summary,$table.' #'.$row[RecoveryTables::key($table)].': '.$field.' ilişkisi başka işletmeye ait.');continue;
            }
            $retained=$mode==='missing'||($target==='users'&&$current&&($current['type']??'')!=='client');
            if($required&&!$saved&&!($retained&&$current))$this->conflict($summary,$table.' #'.$row[RecoveryTables::key($table)].': '.$field.' ilişkisi yedekte eksik.');
        }
        if($table==='booking_offerings'&&!DB::table('booking_services')->where('id',$row['booking_service_id'])->exists())
            $this->conflict($summary,'Randevu hizmet kataloğunda yedekte kullanılan hizmet bulunamadı.');
    }
    private function unique(string $table,array $row,RecoveryStore $live,int $owner,string $mode,array &$summary): void
    {
        $sets=[
            'users'=>[['email']],'items'=>[['inventory_key']],'vehicle_qr_codes'=>[['token'],['vehicle_id']],
            'item_categories'=>[['parent_id','name']],'settings'=>[['parent_id','name']],
            'appointment_profiles'=>[['public_id']],'appointments'=>[['public_token'],['appointment_profile_id','request_key'],['appointment_profile_id','occupied_at']],
            'booking_offerings'=>[['appointment_profile_id','booking_service_id','vehicle_type']],
            'expenses'=>[['stock_movement_id']],'counter_sales'=>[['parent_id','request_key']],
            'item_purchases'=>[['stock_movement_id'],['parent_id','request_key']],
            'inventory_checkpoints'=>[['parent_id','inventory_key']],'cash_closings'=>[['parent_id','business_date']],
            'business_reviews'=>[['owner_id','identity_hash'],['owner_id','customer_id']],
        ];
        if($table==='users'&&(($row['type']??'')==='client'||$live->get('users',$row['id'])))return;
        foreach($sets[$table]??[] as $fields){
            $query=DB::table($table)->where(RecoveryTables::key($table),'!=',$row[RecoveryTables::key($table)]);$skip=false;
            foreach($fields as $field){if(!isset($row[$field])||$row[$field]===''){$skip=true;break;}$query->where($field,$row[$field]);}
            if($skip)continue;
            foreach($query->cursor() as $candidate){
                $old=$live->get($table,$candidate->{RecoveryTables::key($table)});
                if($mode!=='missing'&&$old)continue;
                $this->conflict($summary,$table.' #'.$row[RecoveryTables::key($table)].': benzersiz kayıt alanı çakışıyor.');break;
            }
        }
    }
    public function selectedFiles(RecoveryStore $source,RecoveryStore $live,string $mode,string $path): RecoveryStore
    {
        $wanted=new RecoveryStore($path);$wanted->pdo->beginTransaction();
        foreach(RecoveryTables::all() as $table){
            if(!$source->has($table))continue;$wanted->schema($table,$source->columns($table));
            foreach($source->rows($table) as $row){
                $current=$live->get($table,$row[RecoveryTables::key($table)]);
                if($table==='users'&&$current&&($current['type']??'')!=='client')continue;
                if($mode!=='missing'||!$current||$this->revivable($table,$row,$current))$wanted->put($table,$row);
            }
        }
        $wanted->pdo->commit();return $wanted;
    }
    public function apply(RecoveryStore $source,RecoveryStore $live,int $owner,string $mode): void
    {
        \App\Services\BranchTransfers::guardRecovery($owner);
        \App\Services\PhotoRetention::lockPolicy();
        \App\Services\PhotoRetention::protectRestore($source,$live,$owner);
        if($mode!=='missing'){
            // Delete children first; existing staff accounts and their access policy are retained.
            foreach(array_reverse(RecoveryTables::existing()) as $table){
                if($table==='users')continue;
                foreach($live->rows($table) as $row)DB::table($table)->where(RecoveryTables::key($table),$row[RecoveryTables::key($table)])->delete();
            }
            foreach($live->rows('users') as $row){
                if(($row['type']??'')!=='client'||(int)$row['id']===$owner||$source->get('users',$row['id']))continue;
                foreach(['model_has_roles','model_has_permissions'] as $pivot)DB::table($pivot)->where('model_type',\App\Models\User::class)->where('model_id',$row['id'])->delete();
                DB::table('users')->where('id',$row['id'])->where('parent_id',$owner)->delete();
            }
        }
        foreach(RecoveryTables::existing() as $table){
            $columns=array_flip(Schema::getColumnListing($table));
            foreach($source->rows($table) as $row){
                if($table==='users'&&(int)$row['id']===$owner)continue;
                $key=RecoveryTables::key($table);$id=$row[$key];$current=$live->get($table,$id);
                if($mode==='missing'&&$current){
                    if($this->revivable($table,$row,$current)){
                        $changes=$table==='vehicles'?['deleted_at'=>null]:['client_archived_at'=>null,'client_archive_was_active'=>null,
                            'is_active'=>$current['client_archive_was_active']??$row['is_active']??1];
                        DB::table($table)->where($key,$id)->update(array_intersect_key($changes,$columns));
                    }
                    continue;
                }
                $row=array_intersect_key($row,$columns);
                if($table==='users'){
                    if($current&&($current['type']??'')!=='client')continue;
                    if(($row['type']??'')==='client'){
                        $row['contact_email']=$row['contact_email']??$row['email']??null;$row['email']=null;
                    }elseif(!$current){
                        $row['password']=Hash::make(bin2hex(random_bytes(32)));$row['is_active']=0;
                        foreach(RecoveryTables::PROTECTED_USER as $field)if(!in_array($field,['email','password'],true))unset($row[$field]);
                        foreach(['twofa_required','twofa_last_used_at'] as $field)if(isset($columns[$field]))$row[$field]=0;
                    }
                    if($current)foreach(RecoveryTables::PROTECTED_USER as $field)if(array_key_exists($field,$current))$row[$field]=$current[$field];
                }
                if($table==='invoices'){
                    $row['revision']=max((int)($row['revision']??0),(int)($current['revision']??0))+1;
                    // Never send a second invoice email as a side effect of restoration.
                    if(isset($columns['customer_email_claimed_at']))$row['customer_email_claimed_at']=null;
                    if(isset($columns['customer_email_sent_at']))$row['customer_email_sent_at']=$current['customer_email_sent_at']??$row['customer_email_sent_at']??now()->toDateTimeString();
                }
                if($table==='services' && isset($columns['completed_at']) && ($row['status']??null)==='completed' && empty($row['completed_at']))
                    $row['completed_at']=$current['completed_at']??now()->toDateTimeString();
                if($table==='business_reviews'){
                    $row['sms_status']='restored';$row['email_status']='restored';
                    if(!$current)$row['token']=bin2hex(random_bytes(32));
                }
                if($table==='users'&&$current)DB::table($table)->where($key,$id)->update($row);
                else DB::table($table)->insert($row);
                if($table==='users'&&!$current){
                    $role=DB::table('roles')->where('name',$row['type'])->where('parent_id',$owner)->first();
                    if($role)DB::table('model_has_roles')->insertOrIgnore(['role_id'=>$role->id,'model_type'=>\App\Models\User::class,'model_id'=>$id]);
                }
            }
        }
        // Rebuild the public search index without republishing old sharing permissions.
        if(Schema::hasTable('vehicle_history_index')){
            if($mode!=='missing')DB::table('vehicle_history_index')->where('parent_id',$owner)->delete();
            foreach(\App\Models\Vehicle::where('parent_id',$owner)->cursor() as $vehicle){
                $before=$live->get('vehicles',$vehicle->id);
                if($mode!=='missing'||!$before||!empty($before['deleted_at']))app(\App\Services\PublicVehicleHistory::class)->sync($vehicle);
            }
        }
    }
}
