<?php
namespace Tests\Feature;
use App\Models\{User,Client,Item,Invoice,InvoicePayment,Expense,AppointmentSmsSetting};
use App\Services\{CashRegister,IntegrityCheck,InventoryAccounting,InvoiceJournal,CommunicationOutbox,AppointmentSmsGateway,TenantMailSettings};
use Illuminate\Support\Facades\{DB,Gate,Schema,Mail,Http};
use Illuminate\Support\Str;
use Tests\TestCase;

class CashIntegrityAndOutboxTest extends TestCase
{
    private User $owner,$other,$admin,$customer;
    protected function setUp(): void {
        parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array','communications.async'=>false,
            'recovery.write_lock_path'=>storage_path('framework/testing/cash-'.Str::uuid())]);DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        Schema::table('invoice_payments',function($t){foreach(['transaction_id','receipt','payment_type','payment_status'] as $c)$t->string($c)->nullable();});
        $this->admin=User::create(['name'=>'Admin','email'=>'admin@cash.test','password'=>'x','type'=>'super admin','is_active'=>1]);
        $this->owner=User::create(['name'=>'Shop','email'=>'owner@cash.test','password'=>'x','type'=>'owner','is_active'=>1]);
        $this->other=User::create(['name'=>'Other','email'=>'other@cash.test','password'=>'x','type'=>'owner','is_active'=>1]);
        $this->customer=User::create(['name'=>'Buyer','email'=>'buyer@cash.test','password'=>'x','type'=>'client','parent_id'=>$this->owner->id]);
        Client::create(['parent_id'=>$this->owner->id,'user_id'=>$this->customer->id,'client_id'=>1]);
        Gate::before(fn($u)=>in_array($u->type,['owner','super admin'])?true:null);
        $this->actingAs($this->owner)->withoutMiddleware(\App\Http\Middleware\XSS::class);Mail::fake();Http::preventStrayRequests();
    }
    private function item(): Item {return app(InventoryAccounting::class)->savePurchase($this->owner->id,['title'=>'Oil','quantity'=>10,'purchase_price'=>20,'sales_price'=>50]);}
    private function invoice(): Invoice {return Invoice::create(['parent_id'=>$this->owner->id,'client'=>$this->customer->id,'invoice_id'=>1,'external_labor_amount'=>1000]);}
    private function payment(Invoice $invoice,float $amount,string $channel='cash',string $status='success',?int $owner=null): void {
        $p=new InvoicePayment();$p->invoice_id=$invoice->id;$p->parent_id=$owner??$this->owner->id;$p->payment_date=now()->toDateString();$p->amount=$amount;$p->cash_channel=$channel;$p->payment_status=$status;$p->save();
    }
    private function closing(array $extra=[]): array {$s=app(CashRegister::class)->summary($this->owner->id,now()->toDateString());return $extra+['business_date'=>now()->toDateString(),'opening'=>100,'counted'=>150,'source_hash'=>$s['hash'],'confirm'=>1];}
    public function test_cash_card_transfer_and_legacy_are_separate_and_foreign_pending_payments_excluded(): void {
        $invoice=$this->invoice();$this->payment($invoice,100);$this->payment($invoice,200,'card');$this->payment($invoice,300,'transfer');$this->payment($invoice,40,'unclassified');
        $this->payment($invoice,900,'cash','pending');$this->payment($invoice,800,'cash','success',$this->other->id);
        Expense::create(['parent_id'=>$this->owner->id,'title'=>'Cash expense','date'=>now()->toDateString(),'amount'=>50,'cash_channel'=>'cash']);$this->item();
        $s=app(CashRegister::class)->summary($this->owner->id,now()->toDateString());
        $this->assertSame(['cash'=>10000,'card'=>20000,'transfer'=>30000,'unclassified'=>4000],$s['income']);
        $this->assertSame(5000,$s['expense']['cash']);$this->assertSame(20000,$s['expense']['unclassified']);
        $this->get(route('report.cash'))->assertOk()->assertSee('Belirtilmemiş')->assertSee('Gün sonu kasa');
    }
    public function test_closing_is_immutable_idempotent_and_late_edits_are_visible(): void {
        $invoice=$this->invoice();$this->payment($invoice,50);$data=$this->closing();
        $this->post(route('report.cash.close'),$data)->assertSessionHasNoErrors();$this->post(route('report.cash.close'),$data)->assertSessionHasNoErrors();
        $row=DB::table('cash_closings')->first();$this->assertEquals(150,$row->expected);$this->assertEquals(0,$row->difference);$this->assertSame(1,DB::table('cash_closings')->count());
        $this->post(route('report.cash.close'),array_replace($data,['counted'=>200]))->assertSessionHasErrors('closing');
        $this->payment($invoice,20);$this->get(route('report.cash'))->assertOk()->assertSee('Kapanıştan sonra');
        $this->assertSame(0,Expense::count());
    }
    public function test_stale_closing_future_date_and_other_shop_history_are_rejected_or_hidden(): void {
        $invoice=$this->invoice();$data=$this->closing();$this->payment($invoice,10);
        $this->post(route('report.cash.close'),$data)->assertSessionHasErrors('closing');
        $this->post(route('report.cash.close'),$this->closing(['business_date'=>now()->addDay()->toDateString()]))->assertSessionHasErrors('business_date');
        $this->post(route('report.cash.close'),$this->closing())->assertSessionHasNoErrors();
        $this->actingAs($this->other)->get(route('report.cash'))->assertOk()->assertDontSee('kapanışı kayıtlı');
    }
    public function test_payment_channel_is_saved_and_duplicate_form_is_not_double_counted(): void {
        $invoice=$this->invoice();$data=['operation_token'=>InvoiceJournal::token(),'invoice_revision'=>$invoice->revision??0,'amount'=>100,'payment_date'=>now()->toDateString(),'cash_channel'=>'card'];
        $this->post(route('invoice.payment.store',$invoice->id),$data)->assertSessionHasNoErrors();$this->post(route('invoice.payment.store',$invoice->id),$data)->assertSessionHasNoErrors();
        $this->assertSame('card',$invoice->payments()->first()->cash_channel);$this->assertSame(1,$invoice->payments()->count());
        $this->post(route('invoice.payment.store',$invoice->id),array_replace($data,['cash_channel'=>'cash']))->assertSessionHasErrors('operation_token');
    }
    public function test_product_sale_creates_invoice_without_service_and_deducts_stock_once(): void {
        $item=$this->item();$data=['operation_token'=>InvoiceJournal::token(),'invoice_kind'=>'product','client'=>$this->customer->id,'invoice_date'=>now()->toDateString(),'item'=>[$item->id],'quantity'=>[2]];
        $this->get(route('invoice.product-sale'))->assertOk()->assertSee('Ürün satışı faturası')->assertDontSee('name="service"',false);
        $this->post(route('invoice.store'),$data)->assertSessionHasNoErrors();$this->post(route('invoice.store'),$data)->assertSessionHasNoErrors();
        $invoice=Invoice::firstOrFail();$this->assertSame('product',$invoice->invoice_kind);$this->assertEquals(0,$invoice->service);$this->assertEquals(100,$invoice->getInvoiceAllTotalAmount());
        $this->assertSame(8,(int)$item->fresh()->quantity);$this->assertSame(0,DB::table('counter_sales')->count());$this->assertSame(0,$invoice->payments()->count());
        $this->get(route('invoice.show',encrypt($invoice->id)))->assertOk();$this->get(route('invoice.edit',encrypt($invoice->id)))->assertOk()->assertDontSee('name="service"',false);
        $this->put(route('invoice.update',encrypt($invoice->id)),['operation_token'=>InvoiceJournal::token(),'invoice_revision'=>$invoice->fresh()->revision,'client'=>$this->customer->id,
            'invoice_date'=>now()->toDateString(),'item'=>[$item->id],'item_id'=>[$invoice->items->first()->id],'quantity'=>[1]])->assertSessionHasNoErrors();
        $this->assertSame(9,(int)$item->fresh()->quantity);
    }
    public function test_product_sale_rejects_foreign_customer_item_empty_items_and_service_injection(): void {
        $item=$this->item();$foreign=User::create(['name'=>'Foreign','password'=>'x','type'=>'client','parent_id'=>$this->other->id]);
        $base=['invoice_kind'=>'product','invoice_date'=>now()->toDateString(),'client'=>$this->customer->id,'item'=>[$item->id],'quantity'=>[1]];
        foreach([['client'=>$foreign->id],['item'=>[]],['service'=>1],['quantity'=>[0]]] as $extra){$this->post(route('invoice.store'),array_replace($base,$extra,['operation_token'=>InvoiceJournal::token()]))->assertStatus(302);}
        $this->assertSame(0,Invoice::count());$this->assertSame(10,(int)$item->fresh()->quantity);
    }
    public function test_product_sale_cannot_use_another_shops_tax_or_price(): void {
        $item=$this->item();$tax=DB::table('taxes')->insertGetId(['parent_id'=>$this->other->id,'title'=>'Foreign','rate'=>90]);
        $data=['invoice_kind'=>'product','invoice_date'=>now()->toDateString(),'client'=>$this->customer->id,'item'=>[$item->id],'quantity'=>[1],'tax'=>[[$tax]],'amount'=>[1]];
        $this->post(route('invoice.store'),$data+['operation_token'=>InvoiceJournal::token()])->assertSessionHasErrors('item');
        $this->assertSame(0,Invoice::count());$this->assertSame(10,(int)$item->fresh()->quantity);
        unset($data['tax']);$this->post(route('invoice.store'),$data+['operation_token'=>InvoiceJournal::token()])->assertSessionHasNoErrors();
        $this->assertEquals(50,Invoice::firstOrFail()->getInvoiceAllTotalAmount());
    }
    public function test_integrity_reports_tampering_without_repair_and_keeps_baseline_on_failure(): void {
        $item=$this->item();$this->assertSame('ok',app(IntegrityCheck::class)->run($this->owner->id)['status']);
        app(InventoryAccounting::class)->counterSale($this->owner->id,$this->owner->id,$item->id,2,(string)Str::uuid(),50,'cash');
        $this->assertSame('ok',app(IntegrityCheck::class)->run($this->owner->id)['status']);DB::table('items')->where('id',$item->id)->update(['quantity'=>7]);
        $first=app(IntegrityCheck::class)->run($this->owner->id);$second=app(IntegrityCheck::class)->run($this->owner->id);
        $this->assertSame(1,$first['issue_count']);$this->assertSame(1,$second['issue_count']);$this->assertSame(7,(int)$item->fresh()->quantity);
        $this->assertSame(1,DB::table('integrity_checks')->count());
    }
    public function test_integrity_finds_orphan_payments_and_overpayment_and_is_admin_only(): void {
        $invoice=$this->invoice();$this->payment($invoice,1200);$this->payment($invoice,5,'cash','success',$this->other->id);
        $a=app(IntegrityCheck::class)->run($this->owner->id);$b=app(IntegrityCheck::class)->run($this->other->id);
        $this->assertStringContainsString('overpayment',$a['issues']);$this->assertStringContainsString('invalid_invoice_payments',$b['issues']);
        $this->get(route('integrity.index'))->assertForbidden();$this->actingAs($this->admin)->get(route('integrity.index'))->assertOk()->assertSee('Tutarlılık');
    }
    private function smsReady(): void {$s=AppointmentSmsSetting::central();$s->update(['enabled'=>true,'api_key'=>'key','api_hash'=>'hash','sender'=>'SANAYI']);config(['communications.async'=>true]);}
    public function test_sms_is_durable_encrypted_and_provider_called_only_once_by_worker(): void {
        $this->smsReady();Http::fake(['api.iletimerkezi.com/*'=>Http::response(['response'=>['status'=>['code'=>200],'order'=>['id'=>123]]])]);
        $result=app(AppointmentSmsGateway::class)->send('+905551234567','SECRET_MESSAGE');$this->assertSame('queued',$result['status']);Http::assertNothingSent();
        $row=DB::table('communication_outbox')->first();$this->assertStringNotContainsString('SECRET_MESSAGE',$row->payload);$this->assertStringNotContainsString('5551234567',$row->payload);
        app(CommunicationOutbox::class)->deliver($row->id);app(CommunicationOutbox::class)->deliver($row->id);Http::assertSentCount(1);
        $row=DB::table('communication_outbox')->first();$this->assertSame('accepted',$row->status);$this->assertNull($row->payload);
    }
    public function test_unknown_delivery_is_never_automatically_repeated(): void {
        $this->smsReady();Http::fake(fn()=>throw new \Illuminate\Http\Client\ConnectionException('secret detail'));
        app(AppointmentSmsGateway::class)->send('+905551234567','Test');app(CommunicationOutbox::class)->work();app(CommunicationOutbox::class)->work();
        $row=DB::table('communication_outbox')->first();$this->assertSame('unknown',$row->status);$this->assertSame(1,(int)$row->attempts);$this->assertNull($row->payload);
    }
    public function test_preflight_retries_are_limited_and_expired_or_disabled_sms_cancelled(): void {
        $this->smsReady();AppointmentSmsSetting::central()->update(['api_key'=>null]);app(AppointmentSmsGateway::class)->send('+905551234567','Test');
        for($i=0;$i<3;$i++){DB::table('communication_outbox')->update(['available_at'=>now()]);app(CommunicationOutbox::class)->work();}
        $this->assertSame('failed',DB::table('communication_outbox')->value('status'));$this->assertEquals(3,DB::table('communication_outbox')->value('attempts'));Http::assertNothingSent();
        $row=app(CommunicationOutbox::class)->enqueue('sms',['to'=>'+905551234567','body'=>'Expired'],null,null,now()->subSecond());app(CommunicationOutbox::class)->deliver($row->id);
        $this->assertSame('cancelled',DB::table('communication_outbox')->find($row->id)->status);
    }
    public function test_email_enqueues_after_transaction_without_smtp_secrets_and_rolls_back_with_business_write(): void {
        config(['communications.async'=>true]);$key=(string)Str::uuid();$outbox=app(CommunicationOutbox::class);
        DB::beginTransaction();$outbox->email('a@example.test',['module'=>'send_email','subject'=>'Test','message'=>'PRIVATE','settings'=>['SERVER_PASSWORD'=>'SUPER_SECRET']],'common',$this->owner->id,$key);DB::rollBack();
        $this->assertSame(0,DB::table('communication_outbox')->count());
        $row=$outbox->email('a@example.test',['module'=>'send_email','subject'=>'Test','message'=>'PRIVATE'],'common',$this->owner->id,$key);
        $outbox->email('a@example.test',['module'=>'send_email','subject'=>'Test','message'=>'PRIVATE'],'common',$this->owner->id,$key);
        $this->assertSame(1,DB::table('communication_outbox')->count());Mail::assertNothingSent();
        $this->mock(TenantMailSettings::class,fn($mock)=>$mock->shouldReceive('apply')->once()->andReturn(['FROM_EMAIL'=>'info@example.test','FROM_NAME'=>'Sanayi','company_name'=>'Sanayi']));
        $outbox->deliver($row->id);$outbox->deliver($row->id);Mail::assertSent(\App\Mail\Common::class,1);
        $this->assertSame('accepted',DB::table('communication_outbox')->find($row->id)->status);
    }
    public function test_recovery_epoch_cancels_stale_queued_business_message(): void {
        $this->smsReady();$row=app(CommunicationOutbox::class)->enqueue('sms',['to'=>'+905551234567','body'=>'Old link'],$this->owner->id);
        DB::table('users')->where('id',$this->owner->id)->increment('recovery_version');app(CommunicationOutbox::class)->deliver($row->id);
        $this->assertSame('cancelled',DB::table('communication_outbox')->find($row->id)->status);Http::assertNothingSent();
    }
    public function test_obsolete_verification_code_is_not_sent_and_current_code_updates_its_source(): void {
        $this->smsReady();Http::fake(['api.iletimerkezi.com/*'=>Http::response(['response'=>['status'=>['code'=>200],'order'=>['id'=>123]]])]);
        $profile=app(\App\Services\AppointmentBooking::class)->profileForOwner($this->owner->id);
        $challenge=\App\Models\AppointmentSmsChallenge::create(['appointment_profile_id'=>$profile->id,'token'=>bin2hex(random_bytes(32)),
            'request_key'=>(string)Str::uuid(),'phone_hash'=>str_repeat('a',64),'ip_hash'=>str_repeat('b',64),'payload'=>['phone'=>'+905551234567'],
            'code_hash'=>'old-hash','expires_at'=>now()->addMinutes(5),'last_sent_at'=>now(),'send_status'=>'queued']);
        app(AppointmentSmsGateway::class)->send('+905551234567','OLD',['kind'=>'challenge','id'=>$challenge->id,'code_hash'=>'old-hash','expires'=>now()->addMinutes(5)->toDateTimeString()]);
        $challenge->update(['code_hash'=>'new-hash']);app(CommunicationOutbox::class)->work();Http::assertNothingSent();
        $this->assertSame('queued',$challenge->fresh()->send_status);
        app(AppointmentSmsGateway::class)->send('+905551234567','NEW',['kind'=>'challenge','id'=>$challenge->id,'code_hash'=>'new-hash','expires'=>now()->addMinutes(5)->toDateTimeString()]);
        app(CommunicationOutbox::class)->work();Http::assertSentCount(1);$this->assertSame('accepted',$challenge->fresh()->send_status);
    }
    public function test_deleted_then_returned_product_has_correct_new_checkpoint(): void {
        $item=$this->item();$invoice=$this->invoice();$accounting=app(InventoryAccounting::class);
        $line=$accounting->add($invoice,['item'=>$item->id,'quantity'=>2]);$item->fresh()->delete();
        $accounting->sync($invoice,[]);$restored=Item::firstOrFail();$this->assertSame(2,(int)$restored->quantity);
        $result=app(IntegrityCheck::class)->run($this->owner->id);$this->assertStringNotContainsString('stock_mismatch',$result['issues']);
    }
    public function test_admin_notifications_include_integrity_and_failed_message_alerts(): void {
        $item=$this->item();DB::table('items')->where('id',$item->id)->update(['quantity'=>7]);app(IntegrityCheck::class)->run($this->owner->id);
        DB::table('communication_outbox')->insert(['event_key'=>(string)Str::uuid(),'channel'=>'email','status'=>'unknown','created_at'=>now(),'updated_at'=>now()]);
        $this->actingAs($this->admin)->get(route('appointments.notifications'))->assertOk()->assertJsonFragment(['type'=>'integrity'])->assertJsonFragment(['type'=>'communication']);
        $this->actingAs($this->other)->get(route('appointments.notifications'))->assertOk()->assertJsonMissing(['type'=>'integrity']);
    }
    public function test_password_reset_and_verification_use_priority_queue_without_smtp_connection(): void {
        config(['communications.async'=>true]);$this->owner->sendPasswordResetNotification('synthetic-reset-token');$this->owner->sendEmailVerificationNotification();
        Mail::assertNothingSent();$this->assertSame(2,DB::table('communication_outbox')->where('priority',0)->count());
        $this->assertSame(2,DB::table('communication_outbox')->where('status','pending')->count());
    }
    public function test_new_urgent_message_takes_priority_over_existing_regular_batch(): void {
        $this->smsReady();$outbox=app(CommunicationOutbox::class);$sent=[];
        $outbox->enqueue('sms',['to'=>'+905551234567','body'=>'regular-1']);$outbox->enqueue('sms',['to'=>'+905551234567','body'=>'regular-2']);
        Http::fake(function($request)use(&$sent,$outbox){$sent[]=$request['request']['order']['message']['text'];
            if(count($sent)===1)$outbox->enqueue('sms',['to'=>'+905551234567','body'=>'urgent'],null,null,now()->addMinutes(5),0);
            return Http::response(['response'=>['status'=>['code'=>200],'order'=>['id'=>123]]]);
        });
        $this->assertSame(3,$outbox->work());$this->assertSame(['regular-1','urgent','regular-2'],$sent);
    }
    public function test_invoice_notification_remains_claimed_after_queue_history_is_purged(): void {
        config(['communications.async'=>true]);
        $vehicle=\App\Models\Vehicle::create(['parent_id'=>$this->owner->id,'client'=>$this->customer->id,'license_plate'=>'54A123','chassis_no'=>'WVWZZZ1JZXW000001']);
        \App\Models\VehicleQrCode::create(['parent_id'=>$this->owner->id,'vehicle_id'=>$vehicle->id,'assigned_at'=>now(),'token'=>bin2hex(random_bytes(32))]);
        $service=\App\Models\Service::create(['parent_id'=>$this->owner->id,'client'=>$this->customer->id,'vehicle'=>$vehicle->id]);
        $invoice=$this->invoice();$invoice->service=$service->id;$invoice->save();
        $sender=app(\App\Services\InvoiceCustomerEmail::class);$sender->send($invoice);$sender->send($invoice);
        $this->assertSame(1,DB::table('communication_outbox')->count());$this->assertNotNull($invoice->fresh()->customer_email_queued_at);$this->assertNull($invoice->fresh()->customer_email_sent_at);
        DB::table('communication_outbox')->delete();$sender->send($invoice);$this->assertSame(0,DB::table('communication_outbox')->count());Mail::assertNothingSent();
    }
}
