<?php
namespace App\Services;
use App\Models\{BranchReferral,BusinessGroup,User,Client,Vehicle,Service,ServiceItem,ServiceType,Invoice,InvoiceService};
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Validation\ValidationException;
class BranchReferrals {
    public const SHARE=['customer_name'=>'Müşteri adı','phone'=>'Telefon','email'=>'E-posta','mileage'=>'Kilometre','make_model'=>'Marka / model açıklaması'];
    public function group(int $id): BusinessGroup {
        abort_unless(in_array(auth()->user()?->type,['owner','branch_manager','super admin'],true),403);
        return app(BranchTransfers::class)->group($id);
    }
    public function access(BusinessGroup $group,int $owner): bool {
        $actor=auth()->user();return DB::table('business_branches')->where('group_id',$group->id)->where('owner_id',$owner)->exists()
            && ($actor->type==='super admin' || ($actor->type==='owner' && $actor->id===$owner && $actor->can('create service'))
                || ($actor->type==='branch_manager' && $group->is_active && $group->manager_user_id===$actor->id));
    }
    public function send(int $groupId,array $data): BranchReferral {
        $group=$this->group($groupId);abort_unless($group->is_active && $this->access($group,(int)$data['source_owner_id']),403);
        abort_unless(DB::table('business_branches')->where('group_id',$groupId)->where('owner_id',$data['target_owner_id'])->exists(),403);
        abort_if((int)$data['source_owner_id']===(int)$data['target_owner_id'],422);
        return app(BranchTransfers::class)->locked($groupId,[$data['source_owner_id'],$data['target_owner_id']],function($group)use($data){
            abort_unless($group->is_active && $this->access($group,(int)$data['source_owner_id']),403);
            $fields=array_values(array_unique($data['share']??[]));sort($fields);abort_unless(!array_diff($fields,array_keys(self::SHARE)),422);
            $hash=hash('sha256',json_encode([(int)$data['source_owner_id'],(int)$data['target_owner_id'],(int)$data['vehicle_id'],$fields,$data['note']??'']));
            $prior=BranchReferral::where('request_key',$data['request_key'])->lockForUpdate()->first();
            if($prior){abort_unless($prior->group_id===$group->id && $prior->created_by===auth()->id() && hash_equals($prior->request_hash,$hash),409);return $prior;}
            $vehicle=Vehicle::where('parent_id',$data['source_owner_id'])->lockForUpdate()->findOrFail($data['vehicle_id']);
            if(!ChassisNumber::valid($vehicle->chassis_no))throw ValidationException::withMessages(['vehicle_id'=>'Yönlendirme için aracın şasi numarasını önce doğrulayın.']);
            $customer=User::where('parent_id',$vehicle->parent_id)->where('type','client')->whereNull('client_archived_at')->findOrFail($vehicle->client);
            $optional=['customer_name'=>$customer->name,'phone'=>$customer->phone_number,'email'=>$customer->email,'mileage'=>$vehicle->mileage,'make_model'=>$vehicle->display_name];
            $snapshot=['chassis_no'=>$vehicle->chassis_no,'license_plate'=>$vehicle->license_plate,'note'=>$data['note']??'','share'=>$fields]+array_intersect_key($optional,array_flip($fields));
            $referral=BranchReferral::create(['group_id'=>$group->id,'source_owner_id'=>$vehicle->parent_id,'target_owner_id'=>$data['target_owner_id'],'source_vehicle_id'=>$vehicle->id,
                'created_by'=>auth()->id(),'source_name'=>User::findOrFail($vehicle->parent_id)->name,'target_name'=>User::findOrFail($data['target_owner_id'])->name,
                'shared_data'=>$snapshot,'request_key'=>$data['request_key'],'request_hash'=>$hash,'status'=>'pending','version'=>1]);
            BranchActivity::record($group->id,$vehicle->parent_id,'referral.sent',['record_id'=>$referral->id,'target_owner_id'=>(int)$data['target_owner_id']]);return $referral;
        },true);
    }
    public function resolve(int $id,array $data): BranchReferral {
        $row=BranchReferral::findOrFail($id);$group=$this->group($row->group_id);$action=$data['action'];
        abort_unless($this->access($group,$action==='cancel'?$row->source_owner_id:$row->target_owner_id),403);
        return app(BranchTransfers::class)->locked($group->id,[$row->source_owner_id,$row->target_owner_id],function($group)use($id,$data,$action){
            $row=BranchReferral::lockForUpdate()->findOrFail($id);abort_unless($this->access($group,$action==='cancel'?$row->source_owner_id:$row->target_owner_id),403);
            $hash=hash('sha256',json_encode(\Illuminate\Support\Arr::only($data,['action','customer_id','vehicle_id','customer_name','assign','service_type'])));
            $status=['accept'=>'accepted','reject'=>'rejected','cancel'=>'cancelled'][$action];
            if($row->status!=='pending'){abort_unless($row->status===$status && ($action!=='accept'||hash_equals((string)$row->accept_hash,$hash)),409,'Yönlendirme daha önce farklı bir işlemle sonuçlandı.');return $row;}
            abort_unless((int)$data['version']===$row->version,409);
            if($action==='accept'){
                abort_unless($group->is_active,403);$owner=User::findOrFail($row->target_owner_id);
                abort_unless($owner->is_active && !$owner->subscription_suspended_at && !app(DemoLifecycle::class)->expired($owner),403);
                $snapshot=$row->shared_data;
                if(!empty($data['customer_id']))$customer=User::where('parent_id',$owner->id)->where('type','client')->whereNull('client_archived_at')->findOrFail($data['customer_id']);
                else {
                    if(empty($data['customer_name']))throw ValidationException::withMessages(['customer_name'=>'Yeni müşteri için ad soyad girin veya mevcut müşteriyi seçin.']);
                    $customer=User::create(['name'=>$data['customer_name'],'email'=>$snapshot['email']??null,'phone_number'=>$snapshot['phone']??null,'password'=>Hash::make(bin2hex(random_bytes(32))),'type'=>'client','parent_id'=>$owner->id,'is_active'=>0]);
                    Client::create(['user_id'=>$customer->id,'parent_id'=>$owner->id,'client_id'=>(int)Client::where('parent_id',$owner->id)->max('client_id')+1]);
                }
                if(!empty($data['vehicle_id'])){
                    $vehicle=Vehicle::where('parent_id',$owner->id)->where('client',$customer->id)->lockForUpdate()->findOrFail($data['vehicle_id']);
                    if(ChassisNumber::normalize($vehicle->chassis_no)!==ChassisNumber::normalize($snapshot['chassis_no']))throw ValidationException::withMessages(['vehicle_id'=>'Seçilen aracın şasi numarası yönlendirmeyle uyuşmuyor.']);
                } else {
                    $vehicle=app(VehicleQrPool::class)->createVehicle($owner->id,null,fn()=>Vehicle::create(['parent_id'=>$owner->id,'client'=>$customer->id,
                        'vehicle_id'=>(int)Vehicle::withTrashed()->where('parent_id',$owner->id)->max('vehicle_id')+1,'license_plate'=>$snapshot['license_plate'],'chassis_no'=>$snapshot['chassis_no'],
                        'model'=>$snapshot['make_model']??null,'mileage'=>$snapshot['mileage']??0]));
                }
                $assignee=User::whereKey($data['assign'])->where('is_active',1)->where(fn($q)=>$q->where(fn($q)=>$q->where('parent_id',$owner->id)->where('type','employee'))->orWhere(fn($q)=>$q->where('id',$owner->id)->where('type','owner')))->lockForUpdate()->firstOrFail();
                $service=Service::create(['parent_id'=>$owner->id,'client'=>$customer->id,'vehicle'=>$vehicle->id,'assign'=>$assignee->id,'status'=>'scheduled',
                    'service_id'=>(int)Service::where('parent_id',$owner->id)->max('service_id')+1,'service_date'=>now()->toDateString(),'service_time'=>now()->format('H:i:s'),'notes'=>$snapshot['note']]);
                $invoice=Invoice::create(['parent_id'=>$owner->id,'client'=>$customer->id,'service'=>$service->id,'invoice_id'=>(int)Invoice::where('parent_id',$owner->id)->max('invoice_id')+1,'invoice_date'=>now()->toDateString(),'status'=>0]);
                if(!empty($data['service_type'])){
                    $type=ServiceType::where('parent_id',$owner->id)->findOrFail($data['service_type']);
                    $serviceItem=new ServiceItem(['parent_id'=>$owner->id,'service_id'=>$service->id,'type_id'=>$type->id,'rate'=>$type->rate,'note'=>'']);$serviceItem->tax='';$serviceItem->save();
                    $line=new InvoiceService();$line->parent_id=$owner->id;$line->invoice_id=$invoice->id;$line->service_type=$type->id;$line->rate=$type->rate;$line->save();
                }
                $row->target_service_id=$service->id;$row->accept_hash=$hash;
            }
            $row->status=$status;$row->version++;$row->resolved_by=auth()->id();$row->resolved_at=now();$row->save();
            BranchActivity::record($group->id,$action==='cancel'?$row->source_owner_id:$row->target_owner_id,'referral.'.$status,['record_id'=>$row->id]);return $row;
        });
    }
}
