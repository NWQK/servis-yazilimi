<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function(Blueprint $t) {
            $t->timestamp('demo_started_at')->nullable();
            $t->timestamp('demo_expires_at')->nullable();
            $t->timestamp('last_business_login_at')->nullable();
            $t->timestamp('paid_subscription_at')->nullable();
            $t->index(['type','paid_subscription_at','last_business_login_at'], 'demo_cleanup_candidates');
        });
        Schema::table('vehicle_capacity_requests', fn(Blueprint $t) => $t->timestamp('expires_at')->nullable()->index());
        foreach ([50=>0, 500=>800, 1000=>1400, 2000=>2200, 3000=>3000] as $limit=>$price) {
            DB::table('subscriptions')->where('vehicle_limit',$limit)->update([
                'package_amount'=>$price, 'interval'=>'Monthly',
                'vehicle_block_amount'=>$limit===3000 ? 500 : null, 'updated_at'=>now(),
            ]);
        }
        $demoIds=DB::table('subscriptions')->where('vehicle_limit',50)->pluck('id');
        // Never delete formerly paying businesses, even if they have since returned to Demo.
        DB::table('users')->where('type','owner')->whereNotIn('subscription',$demoIds)->update(['paid_subscription_at'=>now()]);
        $paidIds=DB::table('package_transactions')->where('amount','>',0)
            ->whereIn('payment_status',['Success','success','succeeded'])->pluck('user_id');
        DB::table('users')->where('type','owner')->whereIn('id',$paidIds)->update(['paid_subscription_at'=>now()]);
        DB::table('users')->where('type','owner')->whereIn('subscription',$demoIds)->update([
            'demo_started_at'=>now(), 'demo_expires_at'=>now()->addDays(14),
            'last_business_login_at'=>now(), 'subscription_expire_date'=>now()->addDays(14)->toDateString(),
        ]);
        // Append the new retention policy without overwriting administrator-written terms.
        foreach (DB::table('pages')->where('slug','terms_conditions')->get() as $page) {
            if (!str_contains($page->content ?? '', 'Son işletme girişinden itibaren 30 gün')) {
                $policy='<h2 id="demo-retention-policy">Demo süresi ve kayıtların temizlenmesi</h2><p>Ücretsiz demo 14 gün ve 50 araç ile sınırlıdır. Son işletme girişinden itibaren 30 gün giriş yapılmayan ve ücretli pakete hiç geçmemiş demo hesapları ve işletmeye bağlı müşteri, araç, servis, temsili fatura ve diğer kayıtlar kalıcı olarak silinir. Ücretli paket geçmişi olan işletmeler bu otomatik temizliğe dahil edilmez. Ek 500 araç kapasitesi aylık ücretlendirilir, bir ay geçerlidir ve otomatik tahsilat yapılmaz.</p>';
                DB::table('pages')->where('id',$page->id)->update(['content'=>$page->content.$policy,'updated_at'=>now()]);
            }
        }
        DB::table('vehicle_capacity_requests')->where('status','approved')->whereNull('expires_at')
            ->update(['expires_at'=>now()->addMonthNoOverflow()]);
    }
    public function down(): void
    {
        // Commercial prices and existing expiry dates are deliberately preserved.
        Schema::table('vehicle_capacity_requests', function(Blueprint $t){$t->dropIndex(['expires_at']);$t->dropColumn('expires_at');});
        Schema::table('users', function(Blueprint $t){$t->dropIndex('demo_cleanup_candidates');$t->dropColumn(['demo_started_at','demo_expires_at','last_business_login_at','paid_subscription_at']);});
    }
};
