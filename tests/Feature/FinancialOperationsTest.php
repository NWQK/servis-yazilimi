<?php
namespace Tests\Feature;
use App\Models\{Client,Expense,Invoice,InvoicePayment,Item,Service,User};
use App\Services\{InventoryAccounting,InvoiceJournal,SystemHealth};
use Illuminate\Support\Facades\{DB,Gate,Mail,Schema};
use Tests\TestCase;
class FinancialOperationsTest extends TestCase
{
    private User $owner;
    private User $staff;
    private User $customer;
    private Service $service;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']); DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        Schema::table('invoice_payments',function($t){foreach(['transaction_id','receipt','payment_type','payment_status'] as $c)$t->string($c)->nullable();});
        $this->owner=User::create(['name'=>'Shop','email'=>'financial-owner@example.test','password'=>'x','type'=>'owner']);
        $this->staff=User::create(['name'=>'Second staff','email'=>'financial-staff@example.test','password'=>'x','type'=>'employee','parent_id'=>$this->owner->id]);
        $this->customer=User::create(['name'=>'Customer','password'=>'x','type'=>'client','parent_id'=>$this->owner->id]);
        Client::create(['parent_id'=>$this->owner->id,'user_id'=>$this->customer->id,'client_id'=>1]);
        $this->service=Service::create(['parent_id'=>$this->owner->id,'client'=>$this->customer->id,'external_labor_amount'=>1000]);
        Gate::before(fn($u)=>in_array($u->type,['owner','employee','super admin']) ? true : null);
        DB::table('settings')->insert(['parent_id'=>$this->owner->id,'name'=>'pricing_feature','value'=>'off']);
        $this->actingAs($this->owner)->withoutMiddleware(\App\Http\Middleware\XSS::class); Mail::fake();
    }
    private function invoice(): Invoice {return Invoice::create(['parent_id'=>$this->owner->id,'client'=>$this->customer->id,'service'=>$this->service->id,'external_labor_amount'=>1000,'invoice_id'=>1,'status'=>0]);}
    private function form(?Invoice $invoice=null): array {return ['operation_token'=>InvoiceJournal::token(),'invoice_revision'=>$invoice?->fresh()->revision ?? 0];}
    private function edit(): array {return ['client'=>$this->customer->id,'service'=>$this->service->id,'invoice_date'=>'2026-10-13','discount_amount'=>10];}

    public function test_duplicate_payment_is_applied_once_and_changed_payload_is_rejected(): void
    {
        $invoice=$this->invoice(); $data=$this->form($invoice)+['amount'=>100,'payment_date'=>'2026-10-13'];
        $url=route('invoice.payment.store',$invoice->id);
        $this->post($url,$data)->assertSessionHasNoErrors();
        $this->post($url,$data)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(1,$invoice->payments()->count()); $this->assertEquals(100,$invoice->payments()->sum('amount'));
        $this->post($url,array_replace($data,['amount'=>200]))->assertSessionHasErrors('operation_token');
        $this->assertEquals(100,$invoice->payments()->sum('amount'));
        $this->assertSame(1,DB::table('invoice_changes')->where('action','invoice.payment.store')->count());
    }
    public function test_two_staff_members_cannot_overwrite_stale_invoice_or_delete_after_payment(): void
    {
        $invoice=$this->invoice(); $first=$this->form($invoice);
        $this->actingAs($this->staff); $second=$this->form($invoice); $delete=$this->form($invoice);
        $this->actingAs($this->owner)->put(route('invoice.update',encrypt($invoice->id)),$first+$this->edit())->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->put(route('invoice.update',encrypt($invoice->id)),$second+array_replace($this->edit(),['discount_amount'=>50]))->assertSessionHasErrors('invoice_revision');
        $this->assertEquals(10,$invoice->fresh()->discount_amount);
        $this->delete(route('invoice.destroy',$invoice->id),$delete+['return_stock'=>1])->assertSessionHasErrors('invoice_revision');
        $this->assertNotNull($invoice->fresh());
    }
    public function test_stale_payment_cannot_overpay_and_payment_deletion_is_scoped(): void
    {
        $invoice=$this->invoice(); $old=$this->form($invoice);
        $this->post(route('invoice.payment.store',$invoice->id),$this->form($invoice)+['amount'=>700,'payment_date'=>'2026-10-13'])->assertSessionHasNoErrors();
        $this->post(route('invoice.payment.store',$invoice->id),$old+['amount'=>700,'payment_date'=>'2026-10-13'])->assertSessionHasErrors('invoice_revision');
        $this->post(route('invoice.payment.store',$invoice->id),$this->form($invoice)+['amount'=>400,'payment_date'=>'2026-10-13'])->assertSessionHasErrors('amount');
        $this->assertEquals(700,$invoice->payments()->sum('amount'));
        $other=$this->invoice();
        $payment=$invoice->payments()->first();
        $this->post(route('invoice.payment.destroy',[$other->id,$payment->id]),$this->form($other))->assertNotFound();
        $this->assertEquals(700,$invoice->payments()->sum('amount'));
        $this->post(route('invoice.payment.destroy',[$invoice->id,$payment->id]),$this->form($invoice))->assertSessionHasNoErrors();
        $this->assertSame(0,$invoice->payments()->count());
        $this->assertSame(0,(int)$invoice->fresh()->status);
    }
    public function test_duplicate_product_addition_and_invoice_deletion_do_not_repeat_stock(): void
    {
        $item=app(InventoryAccounting::class)->savePurchase($this->owner->id,['title'=>'Oil','quantity'=>10,'purchase_price'=>20,'sales_price'=>50]);
        $invoice=$this->invoice(); $data=$this->form($invoice)+['item'=>$item->id,'quantity'=>2];
        $this->post(route('invoice.item.store',$invoice->id),$data)->assertSessionHasNoErrors();
        $this->post(route('invoice.item.store',$invoice->id),$data)->assertSessionHasNoErrors();
        $this->assertSame(8,(int)$item->fresh()->quantity);
        $this->assertSame(1,$invoice->items()->count());
        $delete=$this->form($invoice)+['return_stock'=>1];
        $this->delete(route('invoice.destroy',$invoice->id),$delete)->assertSessionHasNoErrors();
        $response=$this->delete(route('invoice.destroy',$invoice->id),$delete);
        $this->assertContains($response->status(),[302,404]);
        $this->assertSame(10,(int)$item->fresh()->quantity);
        $this->assertSame(1,DB::table('stock_movements')->where('kind','return')->count());
        $this->get(route('history.invoices',['invoice'=>$invoice->id]))->assertOk()->assertSee('Fatura silindi');
    }
    public function test_duplicate_invoice_creation_missing_token_and_actor_token_reuse(): void
    {
        $data=$this->form()+$this->edit();
        $this->post(route('invoice.store'),$data)->assertSessionHasNoErrors();
        $this->post(route('invoice.store'),$data)->assertSessionHasNoErrors();
        $this->assertSame(1,Invoice::count());
        $this->post(route('invoice.store'),$this->edit())->assertSessionHasErrors('operation_token');
        $this->actingAs($this->staff)->post(route('invoice.store'),$data)->assertSessionHasErrors('operation_token');
        $this->assertSame(1,Invoice::count());
    }
    public function test_history_retention_and_health_buckets_do_not_remove_financial_sources(): void
    {
        $invoice=$this->invoice();
        $item=app(InventoryAccounting::class)->savePurchase($this->owner->id,['title'=>'Oil','quantity'=>10,'purchase_price'=>20,'sales_price'=>50]);
        $expense=Expense::firstOrFail();
        DB::table('stock_movements')->update(['created_at'=>now()->subYears(3)]);
        DB::table('invoice_changes')->update(['created_at'=>now()->subYears(3)]);
        for($i=0;$i<20;$i++) SystemHealth::record('sms',false,'connection_error');
        $this->assertSame(1,DB::table('system_health_errors')->count());
        $this->assertSame(20,(int)DB::table('system_health_errors')->value('count'));
        DB::table('system_health_errors')->update(['day'=>now()->subDays(40)->toDateString()]);
        app(\App\Services\IntegrityCheck::class)->run($this->owner->id);
        $this->artisan('platform:purge-history')->assertExitCode(0);
        $this->assertSame(0,DB::table('stock_movements')->count());
        $this->assertSame(0,DB::table('invoice_changes')->count());
        $this->assertSame(0,DB::table('system_health_errors')->count());
        $this->assertNotNull($invoice->fresh()); $this->assertNotNull($expense->fresh()); $this->assertSame(10,(int)$item->fresh()->quantity);
        $this->artisan('platform:heartbeat')->assertExitCode(0);
        $this->assertSame('ok',DB::table('system_health')->where('component','scheduler')->value('status'));
    }
    public function test_health_is_admin_only_and_history_is_tenant_scoped(): void
    {
        $this->invoice(); $this->get(route('history.invoices'))->assertOk();
        $this->get(route('system-health.index'))->assertForbidden();
        $other=User::create(['name'=>'Other','email'=>'financial-other@example.test','password'=>'x','type'=>'owner']);
        $this->actingAs($other)->get(route('history.invoices'))->assertOk()->assertSee('Henüz işlem geçmişi yok.');
        $admin=User::create(['name'=>'Admin','email'=>'financial-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->actingAs($admin)->get(route('system-health.index'))->assertOk()->assertSee('Sistem sağlığı');
    }

    public function test_empty_save_does_not_create_history_and_indirect_changes_invalidate_old_form(): void
    {
        $invoice=$this->invoice();
        $this->put(route('invoice.update',encrypt($invoice->id)),$this->form($invoice)+$this->edit())->assertSessionHasNoErrors();
        $count=DB::table('invoice_changes')->count(); $revision=$invoice->fresh()->revision;
        $this->put(route('invoice.update',encrypt($invoice->id)),$this->form($invoice)+$this->edit())->assertSessionHasNoErrors();
        $this->assertSame($count,DB::table('invoice_changes')->count());
        $this->assertSame($revision,$invoice->fresh()->revision);
        $old=$this->form($invoice);
        $invoice=$invoice->fresh(); $invoice->external_labor_amount=900; $invoice->save();
        $this->assertGreaterThan($revision,$invoice->fresh()->revision);
        $this->put(route('invoice.update',encrypt($invoice->id)),$old+$this->edit())->assertSessionHasErrors('invoice_revision');
        $this->assertEquals(900,$invoice->fresh()->external_labor_amount);
    }

    public function test_expired_forms_cannot_be_reused_after_history_retention(): void
    {
        $data=$this->form()+$this->edit();
        try {
            $this->travel(8)->days();
            $this->post(route('invoice.store'),$data)->assertSessionHasErrors('operation_token');
            $this->assertSame(0,Invoice::count());
        } finally { $this->travelBack(); }
    }

    public function test_pending_payment_is_not_income_and_approval_uses_current_balance(): void
    {
        $invoice=$this->invoice();
        $payment=new InvoicePayment(); $payment->parent_id=$this->owner->id; $payment->invoice_id=$invoice->id;
        $payment->amount=700; $payment->payment_status='pending'; $payment->payment_date=now()->toDateString(); $payment->save();
        $this->assertEquals(1000,$invoice->fresh()->getInvoiceTotalDueAmount());
        $this->assertEquals(0,$invoice->payments()->accounted()->sum('amount'));
        $this->post(route('invoice.payment.store',$invoice->id),$this->form($invoice)+['amount'=>500,'payment_date'=>now()->toDateString()])->assertSessionHasNoErrors();
        $this->post(route('invoice.bank.transfer.action',[$payment->id,'accept']),$this->form($invoice))->assertSessionHasErrors('amount');
        $this->assertSame('pending',$payment->fresh()->payment_status);
        $this->assertEquals(500,$invoice->payments()->accounted()->sum('amount'));
        $this->post(route('invoice.bank.transfer.action',[$payment->id,'reject']),$this->form($invoice))->assertSessionHasNoErrors();
        $this->assertSame('reject',$payment->fresh()->payment_status);
        $this->assertEquals(500,$invoice->fresh()->getInvoiceTotalDueAmount());
    }

    public function test_smtp_failures_are_counted_without_persisting_message_or_credentials(): void
    {
        $transport=\Mockery::mock(\Symfony\Component\Mailer\Transport\TransportInterface::class);
        $transport->shouldReceive('send')->once()->andThrow(new \Symfony\Component\Mailer\Exception\TransportException('secret-password-and-recipient@example.test'));
        $tracked=new \App\Services\HealthSmtpTransport($transport);
        $email=(new \Symfony\Component\Mime\Email())->from('sender@example.test')->to('recipient@example.test')->text('private message');
        try {$tracked->send($email);$this->fail('Transport error expected.');}catch(\Symfony\Component\Mailer\Exception\TransportException $e){}
        $this->assertSame('error',DB::table('system_health')->where('component','smtp')->value('status'));
        $this->assertSame(1,(int)DB::table('system_health_errors')->value('count'));
        $this->assertStringNotContainsString('secret-password',DB::table('system_health_errors')->get()->toJson());
        $this->assertStringNotContainsString('recipient@example.test',DB::table('system_health_errors')->get()->toJson());
    }

    public function test_two_invoices_cannot_consume_the_same_last_stock_unit(): void
    {
        $item=app(InventoryAccounting::class)->savePurchase($this->owner->id,['title'=>'Last part','quantity'=>1,'purchase_price'=>20,'sales_price'=>50]);
        $first=$this->invoice(); $second=$this->invoice();
        $firstForm=$this->form($first);
        $this->actingAs($this->staff); $secondForm=$this->form($second);
        $this->actingAs($this->owner)->post(route('invoice.item.store',$first->id),$firstForm+['item'=>$item->id,'quantity'=>1])->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->post(route('invoice.item.store',$second->id),$secondForm+['item'=>$item->id,'quantity'=>1])->assertSessionHasErrors('item');
        $this->assertSame(0,(int)$item->fresh()->quantity);
        $this->assertSame(1,$first->items()->count()); $this->assertSame(0,$second->items()->count());
        $this->assertSame(1,DB::table('stock_movements')->where('kind','sale')->count());
    }

    public function test_failed_response_rolls_back_payment_and_history_together(): void
    {
        $invoice=$this->invoice(); $count=DB::table('invoice_changes')->count();
        \Illuminate\Support\Facades\Route::post('/financial-failure/{id}',function($id){
            $payment=new InvoicePayment(); $payment->parent_id=parentId();$payment->invoice_id=$id;
            $payment->amount=100;$payment->payment_status='success';$payment->save();
            return response('Unexpected failure',500);
        })->middleware('web')->name('invoice.payment.store');
        $this->post('/financial-failure/'.$invoice->id,$this->form($invoice))->assertStatus(500);
        $this->assertSame(0,$invoice->payments()->count());
        $this->assertSame($count,DB::table('invoice_changes')->count());
        $this->assertSame(0,(int)$invoice->fresh()->revision);
    }
}
