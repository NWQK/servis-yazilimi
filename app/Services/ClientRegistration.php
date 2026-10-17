<?php
namespace App\Services;
use App\Models\{Client, Subscription, User};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
class ClientRegistration
{
    public function create(int $ownerId, array $data): User
    {
        return DB::transaction(function () use ($data, $ownerId) {
            $owner=User::where('type','owner')->lockForUpdate()->findOrFail($ownerId);
            $plan=Subscription::find($owner->subscription);
            if (getSettingsValByIdName(1,'pricing_feature') === 'on' && $plan && $plan->client_limit > 0 && $owner->totalCLient() >= $plan->client_limit) {
                throw \Illuminate\Validation\ValidationException::withMessages(['name'=>'Paketinizin müşteri kayıt sınırına ulaştınız.']);
            }
            $customer=User::create(['name'=>$data['name'],'email'=>$data['email'] ?? null,'phone_number'=>$data['phone_number'],
                'password'=>Hash::make(Str::random(40)), 'type'=>'client','lang'=>'tr','parent_id'=>$owner->id]);
            $role=Role::firstOrCreate(['name'=>'client','parent_id'=>$owner->id,'guard_name'=>'web']);
            $customer->assignRole($role);
            Client::create(['user_id'=>$customer->id,'parent_id'=>$owner->id,
                'client_id'=>(int)Client::where('parent_id',$owner->id)->max('client_id')+1]
                + array_intersect_key($data,array_flip(['city','state','zip_code','address','notes'])));
            return $customer;
        });
    }
}
