<?php
namespace App\Services;
use App\Models\{User,Client};
class CustomerContact {
    public function update(User $user,array $data): void {
        $user->update(['name'=>$data['name'],'email'=>($data['email']??null)?:null,'phone_number'=>$data['phone_number']]);
        $profile=Client::where('parent_id',$user->parent_id)->where('user_id',$user->id)->first();
        if($profile)$profile->update(array_intersect_key($data,array_flip(['city','state','zip_code','address','notes'])));
    }
}
