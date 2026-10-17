<?php
namespace Tests\Feature;
use App\Models\{User,Client,Vehicle,Service,Item,Invoice,InvoicePayment,BranchReferral,BusinessGroup,ServiceType};
use App\Services\{BusinessGroups,BranchReferrals,EmployeeDeparture,InventoryAccounting,InvoiceJournal};
use Illuminate\Support\Facades\{DB,Schema,Mail,Storage,Hash};
use Illuminate\Support\Str;
use Tests\TestCase;
class ReferralsDeparturesPreviewTest extends TestCase {
    private User $admin,$a,$b,$c,$manager,$staff,$replacement,$customer;
    private BusinessGroup $group;private Vehicle $vehicle;
    protected function setUp(): void {
        parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','session.driver'=>'array','cache.default'=>'array','communications.async'=>false,'invoice_changes.require_preview'=>true,
            'recovery.write_lock_path'=>storage_path('framework/testing/new-flows-'.Str::uuid())]);DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));$this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        Schema::table('invoice_payments',function($t){foreach(['transaction_id','receipt','payment_type','payment_status'] as $c)$t->string($c)->nullable();});Mail::fake();Storage::fake('local');
        $this->admin=User::create(['name'=>'Admin','email'=>'admin@flows.test','password'=>'x','type'=>'super admin','is_active'=>1]);
        $this->a=$this->owner('A','a@flows.test');$this->b=$this->owner('B','b@flows.test');$this->c=$this->owner('Other','c@flows.test');
        $role=\Spatie\Permission\Models\Role::firstOrCreate(['name'=>'owner','guard_name'=>'web','parent_id'=>1]);
        foreach(['create service','manage service','show service','edit service','delete service','create employee','delete employee','manage employee','edit employee','show employee',
            'create invoice','edit invoice','show invoice','manage invoice','delete invoice','create invoice payment','delete invoice payment','manage item','edit item','create tax','manage tax','edit tax'] as $ability)
            $role->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name'=>$ability,'guard_name'=>'web']));
        foreach([$this->a,$this->b,$this->c] as $owner)$owner->assignRole($role);
        $this->staff=User::create(['name'=>'Departing','email'=>'staff@flows.test','password'=>Hash::make('old-password'),'type'=>'employee','parent_id'=>$this->a->id,'is_active'=>1]);$this->staff->assignRole($role);
        $this->replacement=User::create(['name'=>'Replacement','email'=>'replacement@flows.test','password'=>'x','type'=>'employee','parent_id'=>$this->a->id,'is_active'=>1]);
        $this->actingAs($this->admin)->withoutMiddleware(\App\Http\Middleware\XSS::class);
        $this->group=app(BusinessGroups::class)->create(['name'=>'Group','manager_name'=>'Manager','email'=>'manager@flows.test','password'=>'long-password-123']);
        foreach([$this->a,$this->b] as $owner)app(BusinessGroups::class)->membership($this->group->id,$owner->id,$this->group->fresh()->version,true);$this->manager=$this->group->manager;
        $this->customer=User::create(['name'=>'Source customer','email'=>'customer@flows.test','phone_number'=>'5551234567','password'=>'x','type'=>'client','parent_id'=>$this->a->id]);Client::create(['parent_id'=>$this->a->id,'user_id'=>$this->customer->id,'client_id'=>1]);
        $this->vehicle=Vehicle::create(['parent_id'=>$this->a->id,'client'=>$this->customer->id,'license_plate'=>'54ABC123','chassis_no'=>'WVWZZZ1JZXW000001','mileage'=>100000,'notes'=>'PRIVATE_NOTE']);
        $this->actingAs($this->a);
    }
    private function owner($name,$email): User {return User::create(['name'=>$name,'email'=>$email,'password'=>'x','type'=>'owner','is_active'=>1]);}
    private function referral(array $extra=[]): BranchReferral {
        $data=$extra+['group'=>$this->group->id,'source_owner_id'=>$this->a->id,'target_owner_id'=>$this->b->id,'vehicle_id'=>$this->vehicle->id,'share'=>['customer_name'],'request_key'=>(string)Str::uuid(),'confirm'=>1];
        $this->actingAs($this->a)->post(route('branch-referrals.send'),$data)->assertSessionHasNoErrors();return BranchReferral::latest('id')->firstOrFail();
    }
    private function accept(BranchReferral $row,array $extra=[]): array {return $extra+['action'=>'accept','version'=>$row->version,'customer_name'=>'Target customer','assign'=>$this->b->id,'confirm'=>1];}
    private function open(string $status='scheduled'): Service {return Service::create(['parent_id'=>$this->a->id,'client'=>$this->customer->id,'vehicle'=>$this->vehicle->id,'assign'=>$this->staff->id,'status'=>$status,'service_id'=>1]);}
    public function test_referral_shares_only_selected_fields_and_acceptance_is_atomic_idempotent(): void {
        $row=$this->referral();$this->assertArrayNotHasKey('phone',$row->shared_data);$this->assertArrayNotHasKey('email',$row->shared_data);$this->assertArrayNotHasKey('notes',$row->shared_data);
        $this->assertStringNotContainsString('Source customer',DB::table('branch_referrals')->value('shared_data'));
        $data=$this->accept($row);$this->actingAs($this->b)->post(route('branch-referrals.resolve',$row->id),$data)->assertSessionHasNoErrors();$this->post(route('branch-referrals.resolve',$row->id),$data)->assertSessionHasNoErrors();
        $service=Service::where('parent_id',$this->b->id)->firstOrFail();$this->assertNotEquals($this->vehicle->id,$service->vehicle);$this->assertNotEquals($this->customer->id,$service->client);
        $this->assertSame(1,Service::where('parent_id',$this->b->id)->count());$this->assertSame(1,Invoice::where('parent_id',$this->b->id)->count());
        $this->assertSame('54ABC123',$this->vehicle->fresh()->license_plate);$this->assertSame(0,Invoice::where('parent_id',$this->a->id)->count());
        $this->assertNull(User::find($service->client)->phone_number);$this->assertNull(User::find($service->client)->email);
    }
    public function test_referral_rejects_foreign_actors_reject_accept_races_and_pending_detach(): void {
        $row=$this->referral();$this->actingAs($this->c)->get(route('branch-referrals.show',$row->id))->assertForbidden();
        $this->actingAs($this->a)->post(route('branch-referrals.resolve',$row->id),$this->accept($row))->assertForbidden();
        $this->actingAs($this->admin)->post(route('branches.membership',$this->group->id),['owner_id'=>$this->b->id,'version'=>$this->group->fresh()->version,'action'=>'detach','confirm'=>1])->assertStatus(409);
        $this->actingAs($this->b)->post(route('branch-referrals.resolve',$row->id),['action'=>'reject','version'=>1,'confirm'=>1])->assertSessionHasNoErrors();
        $this->post(route('branch-referrals.resolve',$row->id),$this->accept($row))->assertStatus(409);$this->assertSame(0,Service::count());
    }
    public function test_existing_vehicle_requires_same_chassis_and_customer_and_no_data_overwrite(): void {
        $target=User::create(['name'=>'Existing','password'=>'x','type'=>'client','parent_id'=>$this->b->id]);
        $vehicle=Vehicle::create(['parent_id'=>$this->b->id,'client'=>$target->id,'license_plate'=>'OLDPLATE','chassis_no'=>$this->vehicle->chassis_no,'mileage'=>123]);
        $row=$this->referral(['share'=>['mileage','phone']]);$this->actingAs($this->b);
        $this->post(route('branch-referrals.resolve',$row->id),$this->accept($row))->assertSessionHasErrors('chassis_no');
        $this->assertSame(1,User::where('parent_id',$this->b->id)->where('type','client')->count());
        $this->post(route('branch-referrals.resolve',$row->id),$this->accept($row,['customer_id'=>$target->id,'vehicle_id'=>$vehicle->id]))->assertSessionHasNoErrors();
        $this->assertSame(123,(int)$vehicle->fresh()->mileage);$this->assertSame('OLDPLATE',$vehicle->fresh()->license_plate);$this->assertNull($target->fresh()->phone_number);
    }
    public function test_failed_referral_acceptance_rolls_back_customer_vehicle_invoice_and_status(): void {
        $row=$this->referral();$before=User::count();$this->actingAs($this->b)->post(route('branch-referrals.resolve',$row->id),$this->accept($row,['assign'=>$this->staff->id]))->assertNotFound();
        $this->assertSame($before,User::count());$this->assertSame(0,Vehicle::where('parent_id',$this->b->id)->count());$this->assertSame(0,Invoice::count());$this->assertSame('pending',$row->fresh()->status);
    }
    public function test_departure_reassigns_open_work_preserves_history_and_revokes_access(): void {
        $open=$this->open();$done=$this->open('completed');$cancelled=$this->open('cancelled');
        DB::table('branch_staff_access')->insert(['group_id'=>$this->group->id,'user_id'=>$this->staff->id,'owner_id'=>$this->b->id,'modules'=>'["services"]','can_write'=>1]);
        $this->post(route('employee-departures.store',$this->staff->id),['assignments'=>[$this->a->id=>$this->replacement->id],'confirm'=>1])->assertSessionHasNoErrors();
        $this->assertSame($this->replacement->id,(int)$open->fresh()->assign);$this->assertSame($this->staff->id,(int)$done->fresh()->assign);$this->assertSame($this->staff->id,(int)$cancelled->fresh()->assign);
        $this->assertSame(0,(int)$this->staff->fresh()->is_active);$this->assertSame(1,(int)$this->staff->fresh()->session_epoch);$this->assertSame('Departing',$done->fresh()->assigns->name);
        $this->assertSame(0,DB::table('branch_staff_access')->where('user_id',$this->staff->id)->count());
        $this->post(route('employee-departures.store',$this->staff->id),['confirm'=>1])->assertSessionHasNoErrors();$this->assertSame(1,DB::table('employee_departures')->count());
    }
    public function test_departure_missing_or_foreign_replacement_leaves_everything_unchanged(): void {
        $service=$this->open();$this->post(route('employee-departures.store',$this->staff->id),['assignments'=>[$this->a->id=>$this->b->id],'confirm'=>1])->assertSessionHasErrors('assignments');
        $this->assertSame(1,(int)$this->staff->fresh()->is_active);$this->assertSame($this->staff->id,(int)$service->fresh()->assign);
        $this->actingAs($this->b)->post(route('employee-departures.store',$this->staff->id),['confirm'=>1])->assertForbidden();
    }
    public function test_departed_employee_cannot_login_and_old_session_stays_invalid_after_reactivation(): void {
        app(EmployeeDeparture::class)->depart($this->staff->id,[]);$provider=app('auth')->createUserProvider('users');
        $this->assertFalse($provider->validateCredentials($this->staff->fresh(),['password'=>'old-password']));
        $this->actingAs($this->staff->fresh())->withSession(['employee_epoch'=>0])->get(route('dashboard'))->assertRedirect(route('login'));$this->assertGuest();
        DB::table('users')->where('id',$this->staff->id)->update(['is_active'=>1]);$this->actingAs($this->staff->fresh())->withSession(['employee_epoch'=>0])->get(route('dashboard'))->assertRedirect(route('login'));$this->assertGuest();
    }
    private function productInvoice(): array {
        $item=app(InventoryAccounting::class)->savePurchase($this->a->id,['title'=>'Oil','quantity'=>10,'purchase_price'=>20,'sales_price'=>50]);
        $invoice=Invoice::create(['parent_id'=>$this->a->id,'client'=>$this->customer->id,'invoice_id'=>1]);$invoice->invoice_kind='product';$invoice->save();app(InventoryAccounting::class)->add($invoice,['item'=>$item->id,'quantity'=>2]);
        $p=new InvoicePayment();$p->parent_id=$this->a->id;$p->invoice_id=$invoice->id;$p->amount=100;$p->payment_status='success';$p->payment_date=now()->toDateString();$p->save();return [$invoice->fresh(),$item];
    }
    private function editData(Invoice $invoice,Item $item): array {return ['invoice_revision'=>$invoice->revision,'client'=>$this->customer->id,'invoice_date'=>now()->toDateString(),'item'=>[$item->id],'item_id'=>[$invoice->items->first()->id],'quantity'=>[1]];}
    public function test_invoice_preview_has_no_side_effects_and_confirmation_matches_actual_return_and_correction(): void {
        [$invoice,$item]=$this->productInvoice();$data=$this->editData($invoice,$item);$payments=$invoice->payments()->count();$movements=DB::table('stock_movements')->count();
        $preview=$this->postJson(route('invoice.preview',['id'=>$invoice->id,'action'=>'update']),$data)->assertOk()->json();
        $this->assertEquals(100,$preview['before_total']);$this->assertEquals(50,$preview['after_total']);$this->assertEquals(-50,$preview['payment_adjustment']);$this->assertSame(1,$preview['stocks'][0]['delta']);
        $this->assertSame(8,(int)$item->fresh()->quantity);$this->assertSame($payments,$invoice->payments()->count());$this->assertSame($movements,DB::table('stock_movements')->count());
        $this->put(route('invoice.update',encrypt($invoice->id)),$data+['operation_token'=>InvoiceJournal::token(),'preview_token'=>$preview['token']])->assertSessionHasNoErrors();
        $this->assertSame(9,(int)$item->fresh()->quantity);$this->assertEquals(50,$invoice->payments()->accounted()->sum('amount'));
    }
    public function test_invoice_preview_rejects_changed_body_inventory_missing_token_and_foreign_invoice(): void {
        [$invoice,$item]=$this->productInvoice();$data=$this->editData($invoice,$item);$url=route('invoice.update',encrypt($invoice->id));
        $this->put($url,$data+['operation_token'=>InvoiceJournal::token()])->assertSessionHasErrors('preview_token');
        $preview=$this->postJson(route('invoice.preview',['id'=>$invoice->id,'action'=>'update']),$data)->assertOk()->json();
        $this->put($url,array_replace($data,['quantity'=>[2],'operation_token'=>InvoiceJournal::token(),'preview_token'=>$preview['token']]))->assertSessionHasErrors('preview_token');
        DB::table('items')->where('id',$item->id)->increment('quantity');$this->put($url,$data+['operation_token'=>InvoiceJournal::token(),'preview_token'=>$preview['token']])->assertSessionHasErrors('preview_token');
        $this->actingAs($this->b)->postJson(route('invoice.preview',['id'=>$invoice->id,'action'=>'delete']),['invoice_revision'=>$invoice->revision,'return_stock'=>1])->assertNotFound();
    }
    public function test_delete_preview_reports_selected_stock_policy_and_does_not_delete_until_confirmed(): void {
        [$invoice,$item]=$this->productInvoice();$data=['invoice_revision'=>$invoice->revision,'return_stock'=>1];
        $preview=$this->postJson(route('invoice.preview',['id'=>$invoice->id,'action'=>'delete']),$data)->assertOk()->json();$this->assertSame(2,$preview['stocks'][0]['delta']);$this->assertTrue($preview['deleting']);
        $this->assertNotNull($invoice->fresh());$this->assertSame(8,(int)$item->fresh()->quantity);
        $this->delete(route('invoice.destroy',$invoice->id),$data+['operation_token'=>InvoiceJournal::token(),'preview_token'=>$preview['token']])->assertSessionHasNoErrors();
        $this->assertNull($invoice->fresh());$this->assertSame(10,(int)$item->fresh()->quantity);
    }
    public function test_new_management_pages_render(): void {
        $this->open();$row=$this->referral();$this->get(route('employee-departures.show',$this->staff->id))->assertOk()->assertSee('Personel ayrılış');
        $this->get(route('branch-referrals.index'))->assertOk()->assertDontSee('PRIVATE_NOTE');$this->actingAs($this->b)->get(route('branch-referrals.show',$row->id))->assertOk()->assertSee('Araç eşleştirmesi')->assertDontSee('5551234567');
    }
    public function test_capacity_failure_leaves_referral_pending_and_customer_creation_is_rolled_back(): void {
        $plan=\App\Models\Subscription::create(['title'=>'One vehicle','vehicle_limit'=>1]);$this->b->subscription=$plan->id;$this->b->save();
        Vehicle::create(['parent_id'=>$this->b->id,'chassis_no'=>'WVWZZZ1JZXW000002']);$row=$this->referral();$before=User::count();
        $this->actingAs($this->b)->post(route('branch-referrals.resolve',$row->id),$this->accept($row))->assertRedirect()->assertSessionHasErrors();
        $this->assertSame($before,User::count());$this->assertSame('pending',$row->fresh()->status);$this->assertSame(0,Service::where('parent_id',$this->b->id)->count());
    }
    public function test_changed_send_payload_duplicate_and_foreign_target_are_rejected(): void {
        $key=(string)Str::uuid();$row=$this->referral(['request_key'=>$key]);$this->referral(['request_key'=>$key]);$this->assertSame(1,BranchReferral::count());
        $base=['group'=>$this->group->id,'source_owner_id'=>$this->a->id,'target_owner_id'=>$this->b->id,'vehicle_id'=>$this->vehicle->id,'share'=>['phone'],'request_key'=>$key,'confirm'=>1];
        $this->post(route('branch-referrals.send'),$base)->assertStatus(409);
        $this->post(route('branch-referrals.send'),array_replace($base,['target_owner_id'=>$this->c->id,'request_key'=>(string)Str::uuid()]))->assertForbidden();
        $this->assertSame(1,BranchReferral::count());
    }
    public function test_departed_staff_cannot_be_assigned_new_work_but_completed_history_can_be_edited(): void {
        $done=$this->open('completed');app(EmployeeDeparture::class)->depart($this->staff->id,[]);
        $data=['client'=>$this->customer->id,'vehicle'=>$this->vehicle->id,'assign'=>$this->staff->id,'status'=>'completed','types'=>[],'notes'=>'Historical correction'];
        $this->put(route('service.update',$done->id),$data)->assertRedirect()->assertSessionMissing('error');$this->assertSame($this->staff->id,(int)$done->fresh()->assign);
        $this->post(route('service.store'),array_replace($data,['status'=>'scheduled']))->assertSessionHas('error');$this->assertSame(1,Service::count());
        $this->put(route('service.update',$done->id),array_replace($data,['status'=>'scheduled']))->assertSessionHas('error');$this->assertSame('completed',$done->fresh()->status);
    }
    public function test_expired_preview_and_different_actor_cannot_confirm(): void {
        [$invoice,$item]=$this->productInvoice();$data=$this->editData($invoice,$item);$token=$this->postJson(route('invoice.preview',['id'=>$invoice->id,'action'=>'update']),$data)->assertOk()->json('token');
        $this->travel(11)->minutes();$this->put(route('invoice.update',encrypt($invoice->id)),$data+['operation_token'=>InvoiceJournal::token(),'preview_token'=>$token])->assertSessionHasErrors('preview_token');$this->travelBack();
        $this->actingAs($this->staff)->withSession(['employee_epoch'=>0]);$this->put(route('invoice.update',encrypt($invoice->id)),$data+['operation_token'=>InvoiceJournal::token(),'preview_token'=>$token])->assertSessionHasErrors('preview_token');
        $this->assertSame(8,(int)$item->fresh()->quantity);
    }
    public function test_delete_without_stock_return_preview_leaves_quantity_and_payments_unchanged(): void {
        [$invoice,$item]=$this->productInvoice();$data=['invoice_revision'=>$invoice->revision,'return_stock'=>0];
        $preview=$this->postJson(route('invoice.preview',['id'=>$invoice->id,'action'=>'delete']),$data)->assertOk()->json();$this->assertSame([],$preview['stocks']);
        $this->assertSame(8,(int)$item->fresh()->quantity);$this->assertEquals(100,$invoice->payments()->sum('amount'));$this->assertNotNull($invoice->fresh());
    }
}
