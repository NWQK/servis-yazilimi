<?php
namespace App\Services;
use App\Models\{BusinessGroup,BranchStockTransfer,Item,Unit,User};
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\Recovery\RecoveryWriteLock;

class BranchTransfers
{
    public function group(int $id): BusinessGroup
    {
        $actor=auth()->user(); abort_unless($actor && $actor->is_active,403);
        $group=BusinessGroup::findOrFail($id);
        if ($actor->type==='super admin') return $group;
        if ($actor->type==='branch_manager') {
            abort_unless($group->manager_user_id===$actor->id && $group->is_active,403);
        } else {
            abort_unless(in_array($actor->type,['owner','employee'],true),403);
            $home=$actor->type==='owner'?$actor->id:$actor->parent_id;
            abort_unless(DB::table('business_branches')->where('group_id',$id)->where('owner_id',$home)->exists(),403);
        }
        return $group;
    }
    public function access(BusinessGroup $group, int $ownerId, bool $write=false): bool
    {
        $actor=auth()->user();
        if($actor?->type==='employee' && !User::whereKey($actor->id)->where('is_active',1)->exists())return false;
        if (!$actor || !$actor->is_active || !DB::table('business_branches')->where('group_id',$group->id)->where('owner_id',$ownerId)->exists()) return false;
        if ($actor->type==='super admin' || ($actor->type==='branch_manager' && $group->manager_user_id===$actor->id && $group->is_active)) return true;
        if (($actor->type==='owner' && $actor->id===$ownerId) || ($actor->type==='employee' && (int)$actor->parent_id===$ownerId))
            return $actor->can($write?'edit item':'manage item');
        $owner=User::where('type','owner')->find($ownerId);
        $grant=$owner ? BranchPermissions::grant($group,$owner,$actor) : null;
        return $group->is_active && $grant && BranchPermissions::ability($grant,$write?'edit item':'manage item');
    }
    public function send(int $groupId, array $data): BranchStockTransfer
    {
        $group=$this->group($groupId);
        abort_unless($group->is_active && $this->access($group,(int)$data['source_owner_id'],true),403);
        abort_unless(DB::table('business_branches')->where('group_id',$groupId)->where('owner_id',$data['target_owner_id'])->exists(),403);
        abort_if((int)$data['source_owner_id']===(int)$data['target_owner_id'],422,'Gönderen ve alan şube farklı olmalıdır.');
        return $this->locked($groupId,[(int)$data['source_owner_id'],(int)$data['target_owner_id']],function($group)use($data){
            abort_unless($group->is_active && $this->access($group,(int)$data['source_owner_id'],true),403);
            $hash=hash('sha256',json_encode([(int)$data['source_owner_id'],(int)$data['target_owner_id'],(int)$data['source_item_id'],(int)$data['quantity'],$data['note'] ?? null]));
            $prior=BranchStockTransfer::where('group_id',$group->id)->where('request_key',$data['request_key'])->lockForUpdate()->first();
            if ($prior) {
                abort_unless($prior->created_by===auth()->id() && hash_equals($prior->request_hash,$hash),409,'Bu gönderim isteği farklı bilgilerle daha önce kullanıldı.');
                return $prior;
            }
            $item=Item::where('parent_id',$data['source_owner_id'])->lockForUpdate()->findOrFail($data['source_item_id']);
            $quantity=(int)$data['quantity'];
            if ($quantity<1 || $quantity>100000000 || $quantity>(int)$item->quantity) $this->invalid('Gönderilecek miktar geçersiz veya stok yetersiz.');
            $unit=Unit::where('parent_id',$item->parent_id)->lockForUpdate()->find($item->units);
            if (!$unit) $this->invalid('Ürünün birimini önce gönderen şubede tanımlayın.');
            $item->inventory_key=$item->inventory_key ?: (string)Str::uuid();
            if($item->isDirty('inventory_key')) $item->save();
            IntegrityCheck::baseline($item);
            $snapshot=$item->only(['title','item_code','purchase_price','sales_price','manufacturer_by']);
            $snapshot['unit_name']=$unit->unit;
            $snapshot['source_owner_name']=User::whereKey($data['source_owner_id'])->value('name');
            $snapshot['target_owner_name']=User::whereKey($data['target_owner_id'])->value('name');
            $transfer=BranchStockTransfer::create(['group_id'=>$group->id,'source_owner_id'=>$data['source_owner_id'],
                'target_owner_id'=>$data['target_owner_id'],'source_item_id'=>$item->id,'source_inventory_key'=>$item->inventory_key,
                'snapshot'=>$snapshot,'quantity'=>$quantity,'request_key'=>$data['request_key'],'request_hash'=>$hash,
                'created_by'=>auth()->id(),'status'=>'pending','version'=>1,'note'=>$data['note'] ?? null]);
            $item->quantity-=$quantity; $item->save();
            $this->movement($item,'transfer_out',-$quantity);
            BranchActivity::record($group->id,$item->parent_id,'transfer.sent',['target_owner_id'=>$transfer->target_owner_id,'transfer_id'=>$transfer->id,'quantity'=>$quantity]);
            return $transfer;
        },true);
    }
    public function resolve(int $id, array $data, bool $receive): BranchStockTransfer
    {
        $transfer=BranchStockTransfer::findOrFail($id);
        $group=$this->group($transfer->group_id);
        $this->authorizeResolution($group,$transfer,$receive);
        return $this->locked($group->id,[$transfer->source_owner_id,$transfer->target_owner_id],function($group)use($id,$data,$receive){
            $transfer=BranchStockTransfer::where('group_id',$group->id)->lockForUpdate()->findOrFail($id);
            $this->authorizeResolution($group,$transfer,$receive);
            $receipt=hash('sha256',json_encode([(int)($data['target_item_id'] ?? 0),(int)($data['target_unit_id'] ?? 0)]));
            if ($transfer->status!=='pending') {
                abort_unless(($receive && $transfer->status==='received' && hash_equals((string)$transfer->receipt_hash,$receipt))
                    || (!$receive && $transfer->status==='cancelled'),409,'Transfer başka bir işlemde sonuçlandırıldı.');
                return $transfer;
            }
            abort_unless($transfer->version===(int)$data['version'],409,'Transfer bilgileri değişti. Sayfayı yenileyin.');
            $snapshot=$transfer->snapshot;
            if ($receive) {
                if (!empty($data['target_item_id'])) {
                    $item=Item::where('parent_id',$transfer->target_owner_id)->lockForUpdate()->findOrFail($data['target_item_id']);
                    $unit=Unit::where('parent_id',$transfer->target_owner_id)->lockForUpdate()->find($item->units);
                } else {
                    $unit=Unit::where('parent_id',$transfer->target_owner_id)->lockForUpdate()->find($data['target_unit_id'] ?? 0);
                    $item=new Item(array_intersect_key($snapshot,array_flip(['title','item_code','purchase_price','sales_price','manufacturer_by'])));
                    $item->parent_id=$transfer->target_owner_id; $item->quantity=0; $item->units=$unit?->id;
                }
                if (!$unit || $this->unit($unit->unit)!==$this->unit($snapshot['unit_name'])) $this->invalid('Alan şubede aynı ürün birimi seçilmelidir. Birimleri karıştırarak stok eklenemez.');
                if ((int)$item->quantity + $transfer->quantity > 100000000) $this->invalid('Alan şubenin stok miktarı üst sınıra ulaşıyor.');
                $item->inventory_key=$item->inventory_key ?: (string)Str::uuid();
                IntegrityCheck::baseline($item);
                $item->quantity+=$transfer->quantity; $item->save();
                $this->movement($item,'transfer_in',$transfer->quantity);
                $transfer->target_item_id=$item->id; $transfer->target_inventory_key=$item->inventory_key;
                $transfer->receipt_hash=$receipt;
            } else {
                $item=Item::where('parent_id',$transfer->source_owner_id)->where('inventory_key',$transfer->source_inventory_key)->lockForUpdate()->firstOrFail();
                if ((int)$item->quantity + $transfer->quantity > 100000000) $this->invalid('İade sonrası stok miktarı üst sınırı aşar.');
                IntegrityCheck::baseline($item);
                $item->quantity+=$transfer->quantity; $item->save();
                $this->movement($item,'transfer_return',$transfer->quantity);
            }
            $transfer->status=$receive?'received':'cancelled'; $transfer->version++;
            $transfer->resolved_at=now(); $transfer->resolved_by=auth()->id(); $transfer->save();
            BranchActivity::record($group->id,$receive?$transfer->target_owner_id:$transfer->source_owner_id,
                $receive?'transfer.received':'transfer.cancelled',['transfer_id'=>$id,'quantity'=>$transfer->quantity,'status'=>$transfer->status]);
            return $transfer;
        });
    }
    private function authorizeResolution(BusinessGroup $group, BranchStockTransfer $transfer, bool $receive): void
    {
        abort_unless($receive ? $this->access($group,$transfer->target_owner_id,true)
            : ($this->access($group,$transfer->source_owner_id,true) || $this->access($group,$transfer->target_owner_id,true)),403);
    }
    public function locked(int $groupId, array $owners, callable $work, bool $active=false)
    {
        $owners=array_values(array_unique(array_map('intval',$owners))); sort($owners);
        $lock=function($offset)use(&$lock,$owners,$groupId,$work,$active){
            if (isset($owners[$offset])) return app(RecoveryWriteLock::class)->shared($owners[$offset],fn()=>$lock($offset+1));
            return BranchTransactions::run(function()use($owners,$groupId,$work,$active){
                $group=BusinessGroup::whereKey($groupId)->sharedLock()->firstOrFail();
                $this->group($groupId);
                $locked=User::where('type','owner')->whereIn('id',$owners)->orderBy('id')->lockForUpdate()->get();
                abort_unless($locked->count()===count($owners) && DB::table('business_branches')->where('group_id',$groupId)->whereIn('owner_id',$owners)->count()===count($owners),409,'Şube bağlantısı değişmiş.');
                abort_if(DB::table('business_recovery_locks')->whereIn('owner_id',$owners)->exists(),409,'Şubelerden birinde veri kurtarma çalışıyor.');
                if($active) foreach($locked as $owner) abort_unless($owner->is_active && !$owner->subscription_suspended_at && !app(DemoLifecycle::class)->expired($owner),403,'Şubelerden birinin hesabı veya aboneliği işlem yapmaya uygun değil.');
                return $work($group);
            },3);
        };
        return $lock(0);
    }
    private function movement(Item $item, string $kind, int $quantity): void
    {
        DB::table('stock_movements')->insert(['parent_id'=>$item->parent_id,'inventory_key'=>$item->inventory_key,
            'item_title'=>$item->title,'actor_id'=>auth()->id(),'actor_name'=>auth()->user()->name,'kind'=>$kind,'quantity'=>$quantity,
            'unit_price'=>number_format((float)$item->purchase_price,2,'.',''),'created_at'=>now(),'updated_at'=>now()]);
    }
    private function unit(string $value): string
    {
        $value=strtolower(preg_replace('/[\s.]+/u','',Str::ascii(trim($value))));
        return ['l'=>'litre','lt'=>'litre','liter'=>'litre','kg'=>'kilogram','kilo'=>'kilogram','ml'=>'mililitre','ad'=>'adet','pcs'=>'adet'][$value] ?? $value;
    }
    private function invalid(string $message): void { throw ValidationException::withMessages(['transfer'=>$message]); }

    public static function guardRecovery(int $owner): void
    {
        if (Schema::hasTable('branch_stock_transfers') && DB::table('branch_stock_transfers')->where(fn($q)=>$q->where('source_owner_id',$owner)->orWhere('target_owner_id',$owner))->exists())
            throw new \App\Services\Recovery\RecoveryFailure('Bu işletmede şubeler arası stok hareketi var. Tek şubenin stoklarını eskiye döndürmek diğer şubeyle tutarlılığı bozabilir. Grup tutarlılığı sağlanan tam yedekle kurtarma gerekir; tek şube kurtarma durduruldu.');
    }
}
