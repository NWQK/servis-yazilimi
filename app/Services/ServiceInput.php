<?php
namespace App\Services;
use Illuminate\Validation\Rule;
class ServiceInput
{
    public static function client(): array
    {
        // Keep legacy self-customer records valid, while excluding every other business.
        return ['required','integer',Rule::exists('users','id')->where(fn($q)=>
            $q->where(fn($q)=>$q->where('parent_id',parentId())->where('type','client'))
                ->orWhere(fn($q)=>$q->where('id',parentId())->where('type','owner')))];
    }
    public static function assignee(): array
    {
        return ['required','integer',Rule::exists('users','id')->where(fn($q)=>
            $q->where(fn($q)=>$q->where('parent_id',parentId())->orWhere('id',parentId()))->whereNotIn('type',['client','super admin']))];
    }
    public static function type(): array
    {
        return ['required','integer',Rule::exists('service_types','id')->where('parent_id',parentId())];
    }
    public static function tax(): array
    {
        return ['integer',Rule::exists('taxes','id')->where('parent_id',parentId())];
    }
}
