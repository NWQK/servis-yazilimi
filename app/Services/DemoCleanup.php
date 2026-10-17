<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\{DB, Schema};

class DemoCleanup
{
    public function candidates()
    {
        return User::where('type','owner')->whereNull('paid_subscription_at')
            ->when(Schema::hasTable('business_branches'),fn($q)=>$q->whereNotExists(fn($q)=>$q->selectRaw('1')->from('business_branches')->whereColumn('owner_id','users.id')))
            ->when(Schema::hasTable('business_recovery_locks'),fn($q)=>$q->whereNotExists(fn($q)=>$q->selectRaw('1')->from('business_recovery_locks')->whereColumn('owner_id','users.id')))
            ->whereIn('subscription',DB::table('subscriptions')->where('vehicle_limit',50)->select('id'))
            ->whereNotNull('demo_expires_at')->where('demo_expires_at','<=',now())
            ->whereNotNull('last_business_login_at')->where('last_business_login_at','<=',now()->subDays(30))
            ->whereNotExists(function($q){$q->selectRaw('1')->from('package_transactions')
                ->whereColumn('user_id','users.id')->where('amount','>',0)
                ->whereIn('payment_status',['Success','success','succeeded','Pending','pending']);});
    }

    public function delete(int $ownerId): bool
    {
        return DB::transaction(function() use($ownerId) {
            // Re-evaluate after locking: a login or upgrade may have happened since selection.
            $owner=User::whereKey($ownerId)->lockForUpdate()->first();
            if (!$owner || !$this->candidates()->whereKey($ownerId)->exists()) return false;
            if (User::where('parent_id',$ownerId)->whereIn('type',['owner','super admin'])->exists()) return false;
            $userIds=DB::table('users')->where('parent_id',$ownerId)->where('type','!=','super admin')->pluck('id')->push($ownerId);
            $profileIds=DB::table('appointment_profiles')->where('owner_id',$ownerId)->pluck('id');
            $appointmentIds=DB::table('appointments')->whereIn('appointment_profile_id',$profileIds)->pluck('id');
            DB::table('appointment_sms_messages')->whereIn('appointment_id',$appointmentIds)->delete();
            DB::table('appointment_sms_challenges')->whereIn('appointment_profile_id',$profileIds)->delete();
            DB::table('booking_offerings')->whereIn('appointment_profile_id',$profileIds)->delete();
            DB::table('appointments')->whereIn('appointment_profile_id',$profileIds)->delete();
            DB::table('appointment_profiles')->whereIn('id',$profileIds)->delete();
            $tickets=DB::table('support_tickets')->where('owner_id',$ownerId)->pluck('id');
            DB::table('support_ticket_messages')->whereIn('ticket_id',$tickets)->delete();
            // Explicit tenant table list, children first. Global catalogs/settings are never truncated.
            $photoFiles=DB::table('service_photos')->where('parent_id',$ownerId)->pluck('filename');
            DB::afterCommit(function()use($photoFiles){
                foreach($photoFiles as $filename)if(preg_match('/\A[0-9]+_[0-9]+_[a-f0-9-]{36}\.jpg\z/',$filename))
                    \Illuminate\Support\Facades\Storage::disk('service_photos')->delete('photo/'.$filename);
            });
            foreach (['vehicle_history_index','invoice_changes','invoice_payments','invoice_services','invoice_items','invoices',
                'service_photos','service_items','services','quotation_items','quotation_services','quotations',
                'vehicle_sms_messages','vehicle_qr_codes','vehicles','item_purchases','stock_movements','counter_sales',
                'items','item_categories','suppliers','expenses','clients','employees','vehicle_catalog_imports',
                'vehicle_brands','vehicle_types','service_types','taxes','units','contacts','notice_boards',
                'logged_histories','notifications','invoice_business_profiles','settings','n8ns','ai_templates',
                'f_a_q_s','pages','home_pages','auth_pages'] as $table) {
                if (Schema::hasTable($table)) DB::table($table)->where('parent_id',$ownerId)->delete();
            }
            foreach (['support_tickets','vehicle_capacity_requests','subscription_changes','two_factor_admin_actions',
                'business_reviews','business_locations','announcement_recipients','admin_audit_logs'] as $table) {
                if (Schema::hasTable($table)) DB::table($table)->where('owner_id',$ownerId)->delete();
            }
            foreach (['model_has_roles','model_has_permissions'] as $table) {
                DB::table($table)->where('model_type',User::class)->whereIn('model_id',$userIds)->delete();
            }
            $roles=DB::table('roles')->where('parent_id',$ownerId)->pluck('id');
            DB::table('model_has_roles')->whereIn('role_id',$roles)->delete();
            DB::table('role_has_permissions')->whereIn('role_id',$roles)->delete();
            DB::table('roles')->whereIn('id',$roles)->delete();
            foreach (['package_transactions','coupon_histories','logged_histories'] as $table) {
                DB::table($table)->whereIn('user_id',$userIds)->delete();
            }
            $emails=DB::table('users')->whereIn('id',$userIds)->whereNotNull('email')->pluck('email');
            DB::table('password_resets')->whereIn('email',$emails)->delete();
            if (Schema::hasTable('personal_access_tokens')) DB::table('personal_access_tokens')
                ->where('tokenable_type',User::class)->whereIn('tokenable_id',$userIds)->delete();
            if (Schema::hasTable('sessions') && Schema::hasColumn('sessions','user_id')) DB::table('sessions')->whereIn('user_id',$userIds)->delete();
            DB::table('users')->whereIn('id',$userIds)->delete();
            return true;
        },5);
    }
}
