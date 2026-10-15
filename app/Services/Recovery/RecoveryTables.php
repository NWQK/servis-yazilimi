<?php
namespace App\Services\Recovery;
use Illuminate\Support\Facades\{DB,Schema};

/** Explicit business-data boundary. Authentication, subscriptions and global tables are excluded. */
class RecoveryTables
{
    public const PARENT=[
        'vehicle_types','vehicle_brands','taxes','units','service_types','suppliers','item_categories',
        'users','clients','employees','items','vehicles','vehicle_qr_codes','services','service_items','service_photos',
        'quotations','quotation_items','quotation_services','invoices','invoice_items','invoice_services','invoice_payments',
        'stock_movements','expenses','counter_sales','item_purchases','invoice_business_profiles','settings',
        'vehicle_catalog_imports','contacts','notice_boards',
    ];
    public const OWNER=['business_locations','appointment_profiles','business_reviews'];
    public const CHILD=['appointments'=>'appointment_profile_id','booking_offerings'=>'appointment_profile_id'];
    public const FINANCIAL=['items','invoices','invoice_items','invoice_services','invoice_payments','stock_movements','expenses','counter_sales','item_purchases'];
    public const PROTECTED_USER=['email','password','remember_token','email_verified_at','email_verification_token',
        'twofa_secret','twofa_recovery_codes','twofa_last_used_at','twofa_required','subscription','subscription_expire_date',
        'subscription_suspended_at','subscription_suspension_reason','paid_subscription_at','demo_started_at','demo_expires_at','last_business_login_at','recovery_version'];
    public static function all(): array{return array_merge(self::PARENT,self::OWNER,array_keys(self::CHILD));}
    public static function key(string $table): string{return $table==='invoice_business_profiles'?'parent_id':'id';}
    public static function setting(string $name): bool
    {
        return (bool)preg_match('/\A(?:company_|invoice_|service_|vehicle_|client_|employee_|quotation_|item_|expense_|supplier_|bank_)/',$name);
    }
    public static function live(string $table,int $owner)
    {
        $q=DB::table($table);
        if($table==='users')return $q->where(fn($q)=>$q->where('id',$owner)->orWhere(fn($q)=>$q->where('parent_id',$owner)->whereNotIn('type',['owner','super admin'])));
        if(in_array($table,self::PARENT,true))$q->where('parent_id',$owner);
        elseif(in_array($table,self::OWNER,true))$q->where('owner_id',$owner);
        elseif(isset(self::CHILD[$table]))$q->whereIn(self::CHILD[$table],DB::table('appointment_profiles')->where('owner_id',$owner)->select('id'));
        else throw new RecoveryFailure('Kurtarma tablo sınırı geçersiz.');
        return $q;
    }
    public static function belongs(string $table,array $row,int $owner,array $profileIds=[]): bool
    {
        if($table==='users')return (int)($row['id']??0)===$owner
            || ((int)($row['parent_id']??0)===$owner&&!in_array($row['type']??'',['owner','super admin'],true));
        if($table==='settings'&&!self::setting($row['name']??''))return false;
        if(in_array($table,self::PARENT,true))return (int)($row['parent_id']??0)===$owner;
        if(in_array($table,self::OWNER,true))return (int)($row['owner_id']??0)===$owner;
        return isset(self::CHILD[$table])&&in_array((string)($row[self::CHILD[$table]]??''),$profileIds,true);
    }
    public static function existing(): array{return array_values(array_filter(self::all(),fn($t)=>Schema::hasTable($t)));}
}
