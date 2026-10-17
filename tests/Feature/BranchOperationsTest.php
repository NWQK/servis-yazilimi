<?php
namespace Tests\Feature;
use App\Models\{BusinessGroup,BranchStockTransfer,User,Item,Unit,Invoice,InvoicePayment,Service,Tax};
use App\Services\{BusinessGroups,BranchStaff,BranchContext,BranchTransfers,BranchReports};
use Illuminate\Support\Facades\{DB,Hash,Mail,Storage,Schema,Event};
use Illuminate\Support\Str;
use Tests\TestCase;

class BranchOperationsTest extends TestCase
{
    private User $admin,$a,$b,$c,$manager,$staff;
    private BusinessGroup $group;
    private Item $item;
    private int $unitA,$unitB;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','session.driver'=>'array','cache.default'=>'array',
            'recovery.write_lock_path'=>storage_path('framework/testing/branch-ops-'.Str::uuid())]);
        DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        Schema::table('invoice_payments',function($t){foreach(['transaction_id','receipt','payment_type','payment_status'] as $c)$t->string($c)->nullable();});
        Mail::fake(); Storage::fake('local');
        $this->admin=User::create(['name'=>'Admin','email'=>'admin@ops.test','password'=>'x','type'=>'super admin']);
        $this->a=$this->owner('Sakarya','a@ops.test');$this->b=$this->owner('İstanbul','b@ops.test');$this->c=$this->owner('Başka işletme','c@ops.test');
        $role=\Spatie\Permission\Models\Role::firstOrCreate(['name'=>'owner','guard_name'=>'web','parent_id'=>1]);
        foreach(['manage item','create item','edit item','delete item','manage tax','create tax','edit tax','delete tax',
            'manage invoice','create invoice','edit invoice','show invoice','delete invoice','create invoice payment','delete invoice payment',
            'manage client','create client','edit client','delete client','manage vehicle','create vehicle','edit vehicle',
            'manage service','create service','show service','edit service','delete service','manage unit','manage supplier',
            'manage account settings','manage password settings','manage 2FA settings'] as $ability)
            $role->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name'=>$ability,'guard_name'=>'web']));
        foreach([$this->a,$this->b,$this->c] as $owner)$owner->assignRole($role);
        $this->staff=User::create(['name'=>'Personel A','email'=>'staff@ops.test','password'=>'x','type'=>'employee','parent_id'=>$this->a->id,'is_active'=>1]);
        $this->staff->assignRole($role); // Deliberately broad home role: it must not leak into delegated access.
        $this->actingAs($this->admin)->withoutMiddleware(\App\Http\Middleware\XSS::class);
        $this->group=app(BusinessGroups::class)->create(['name'=>'Merkez','manager_name'=>'Merkez yöneticisi','email'=>'manager@ops.test','password'=>'long-password-123']);
        foreach([$this->a,$this->b] as $owner)app(BusinessGroups::class)->membership($this->group->id,$owner->id,$this->group->fresh()->version,true);
        $this->manager=$this->group->manager;
        $this->unitA=DB::table('units')->insertGetId(['parent_id'=>$this->a->id,'unit'=>'Litre']);
        $this->unitB=DB::table('units')->insertGetId(['parent_id'=>$this->b->id,'unit'=>'Litre']);
        $this->item=Item::create(['parent_id'=>$this->a->id,'title'=>'Motor yağı','quantity'=>20,'units'=>$this->unitA,'purchase_price'=>100,'sales_price'=>150]);
        $this->item->inventory_key=(string)Str::uuid();$this->item->save();
    }
    private function owner($name,$email): User {return User::create(['name'=>$name,'email'=>$email,'password'=>'x','type'=>'owner','parent_id'=>1,'is_active'=>1]);}
    private function grant(array $modules=['stock'],bool $write=false,bool $delete=false): void
    {
        $this->actingAs($this->manager);
        app(BranchStaff::class)->save($this->group->id,['user_id'=>$this->staff->id,'owner_id'=>$this->b->id,
            'version'=>$this->group->fresh()->version,'action'=>'grant','modules'=>$modules,'can_write'=>$write,'can_delete'=>$delete]);
    }
    private function staffUrl(string $route,array $params=[]): string
    {
        return route($route,$params+['_branch'=>BranchContext::token($this->group->fresh(),$this->b->fresh(),$this->staff->fresh())]);
    }
    private function payload(array $extra=[]): array
    {
        return $extra+['group'=>$this->group->id,'source_owner_id'=>$this->a->id,'target_owner_id'=>$this->b->id,'source_item_id'=>$this->item->id,
            'quantity'=>4,'request_key'=>(string)Str::uuid(),'confirm'=>1];
    }
    private function send(array $extra=[]): BranchStockTransfer
    {
        $this->actingAs($this->manager)->post(route('branch-transfers.send'),$this->payload($extra))->assertSessionHasNoErrors()->assertSessionHas('success');
        return BranchStockTransfer::latest('id')->firstOrFail();
    }
    private function receipt(BranchStockTransfer $row,array $extra=[]): array
    {
        return $extra+['action'=>'receive','version'=>$row->version,'target_unit_id'=>$this->unitB,'confirm'=>1];
    }
    public function test_read_only_staff_cannot_write_delete_or_use_ungranted_modules(): void
    {
        $this->grant();
        $this->actingAs($this->staff)->get($this->staffUrl('item.index'))->assertOk();
        $this->get($this->staffUrl('dashboard'))->assertOk()->assertSee('Şube çalışma alanı')->assertDontSee('Tahsilat');
        $this->post($this->staffUrl('tax.store'),['title'=>'No','rate'=>20])->assertForbidden();
        $this->delete($this->staffUrl('item.destroy',['item'=>$this->item->id]))->assertForbidden();
        $this->get($this->staffUrl('invoice.index'))->assertForbidden();
        $this->get($this->staffUrl('report.income'))->assertForbidden();
        $this->get(route('branches.report'))->assertForbidden();
        $this->get(route('branches.history'))->assertForbidden();
        $this->get(route('branches.staff'))->assertForbidden();
        $this->assertSame(20,(int)$this->item->fresh()->quantity);
    }
    public function test_write_and_delete_flags_are_separate_and_cannot_be_spoofed(): void
    {
        $this->grant(['stock','invoices'],true,false);
        $this->actingAs($this->staff)->post($this->staffUrl('tax.store'),['title'=>'B tax','rate'=>20])->assertSessionHas('success');
        $tax=Tax::where('parent_id',$this->b->id)->firstOrFail();
        $this->delete($this->staffUrl('tax.destroy',['tax'=>$tax->id]))->assertForbidden();
        $this->post($this->staffUrl('invoice.payment.destroy',['id'=>123,'pid'=>456]),[])->assertForbidden();
        $this->post($this->staffUrl('tax.destroy',['tax'=>$tax->id]),['_method'=>'DELETE'])->assertForbidden();
        $this->assertNotNull($tax->fresh());
        $this->grant(['stock'],true,true);
        $this->actingAs($this->staff)->delete($this->staffUrl('tax.destroy',['tax'=>$tax->id]))->assertSessionHas('success');
        $this->assertNull($tax->fresh());
    }
    public function test_revocation_invalidates_old_forms_and_preserves_home_role(): void
    {
        $this->grant(['stock'],true);
        $url=$this->staffUrl('tax.store');
        $this->actingAs($this->manager);
        app(BranchStaff::class)->save($this->group->id,['user_id'=>$this->staff->id,'owner_id'=>$this->b->id,'version'=>$this->group->fresh()->version,'action'=>'revoke']);
        $this->actingAs($this->staff)->post($url,['title'=>'Old','rate'=>20])->assertStatus(409);
        $this->post(route('tax.store'),['title'=>'Home','rate'=>20])->assertSessionHas('success');
        $this->assertDatabaseHas('taxes',['parent_id'=>$this->a->id,'title'=>'Home']);
        $this->assertDatabaseMissing('taxes',['parent_id'=>$this->b->id,'title'=>'Old']);
    }
    public function test_staff_portal_does_not_disclose_central_finances_or_other_shops(): void
    {
        $this->grant();
        $this->actingAs($this->staff)->get(route('branches.portal'))->assertOk()->assertSee('İstanbul')->assertDontSee('Başka işletme')->assertDontSee('Toplam müşteri kaydı');
        $this->assertSame($this->a->id,$this->staff->fresh()->parent_id);
    }
    public function test_leaving_home_shop_revokes_all_of_its_delegated_staff(): void
    {
        $this->grant(['stock'],true);$url=$this->staffUrl('tax.store');
        $this->actingAs($this->admin);
        app(BusinessGroups::class)->membership($this->group->id,$this->a->id,$this->group->fresh()->version,false);
        $this->assertDatabaseMissing('branch_staff_access',['user_id'=>$this->staff->id]);
        $this->actingAs($this->staff)->post($url,['title'=>'No','rate'=>20])->assertStatus(409);
        $this->assertSame($this->a->id,$this->staff->fresh()->parent_id);
    }
    public function test_foreign_staff_and_foreign_target_grants_are_rejected(): void
    {
        $outsider=User::create(['name'=>'Outside','type'=>'employee','password'=>'x','parent_id'=>$this->c->id]);
        $this->actingAs($this->manager);
        foreach([[$outsider->id,$this->b->id],[$this->staff->id,$this->c->id]] as [$user,$owner])
            $this->post(route('branches.staff.save',$this->group->id),['action'=>'grant','version'=>$this->group->fresh()->version,'user_id'=>$user,'owner_id'=>$owner,'modules'=>['stock']])->assertForbidden();
        $this->assertSame(0,DB::table('branch_staff_access')->count());
    }
    public function test_selected_service_access_is_not_accidentally_limited_to_home_assignment(): void
    {
        $this->grant(['services']);
        $service=Service::create(['parent_id'=>$this->b->id,'service_id'=>4,'assign'=>$this->b->id,'status'=>'scheduled']);
        $this->actingAs($this->staff)->get($this->staffUrl('service.show',['service'=>encrypt($service->id)]))->assertOk();
        $foreign=Service::create(['parent_id'=>$this->a->id,'service_id'=>5,'assign'=>$this->a->id,'status'=>'scheduled']);
        $this->get($this->staffUrl('service.show',['service'=>encrypt($foreign->id)]))->assertNotFound();
    }
    public function test_transfer_moves_stock_once_and_does_not_create_money_records(): void
    {
        $before=[DB::table('expenses')->count(),DB::table('invoice_payments')->count(),DB::table('counter_sales')->count()];
        $payload=$this->payload();$this->actingAs($this->manager);
        $this->post(route('branch-transfers.send'),$payload)->assertSessionHas('success');
        $this->post(route('branch-transfers.send'),$payload)->assertSessionHas('success');
        $row=BranchStockTransfer::firstOrFail();
        $this->assertSame(16,(int)$this->item->fresh()->quantity);$this->assertSame(0,Item::where('parent_id',$this->b->id)->count());
        $this->post(route('branch-transfers.resolve',$row->id),$this->receipt($row))->assertSessionHas('success');
        $this->post(route('branch-transfers.resolve',$row->id),$this->receipt($row))->assertSessionHas('success');
        $target=Item::where('parent_id',$this->b->id)->firstOrFail();
        $this->assertSame(4,(int)$target->quantity);$this->assertNotSame($this->item->inventory_key,$target->inventory_key);
        $this->assertNull($target->supplier_id);$this->assertNull($target->category_id);$this->assertEmpty($target->taxs);
        $this->assertSame($before,[DB::table('expenses')->count(),DB::table('invoice_payments')->count(),DB::table('counter_sales')->count()]);
        $this->assertSame(1,DB::table('stock_movements')->where('kind','transfer_out')->count());
        $this->assertSame(1,DB::table('stock_movements')->where('kind','transfer_in')->count());
    }
    public function test_duplicate_send_key_with_changed_payload_is_rejected(): void
    {
        $payload=$this->payload();$this->actingAs($this->manager)->post(route('branch-transfers.send'),$payload)->assertSessionHas('success');
        $this->post(route('branch-transfers.send'),array_replace($payload,['quantity'=>3]))->assertStatus(409);
        $this->assertSame(16,(int)$this->item->fresh()->quantity);
        $this->assertSame(1,BranchStockTransfer::count());
    }
    public function test_cancel_returns_stock_once_and_receive_after_cancel_is_rejected(): void
    {
        $row=$this->send();$cancel=['action'=>'cancel','version'=>1,'confirm'=>1];
        $this->post(route('branch-transfers.resolve',$row->id),$cancel)->assertSessionHas('success');
        $this->post(route('branch-transfers.resolve',$row->id),$cancel)->assertSessionHas('success');
        $this->post(route('branch-transfers.resolve',$row->id),$this->receipt($row))->assertStatus(409);
        $this->assertSame(20,(int)$this->item->fresh()->quantity);
        $this->assertSame(0,Item::where('parent_id',$this->b->id)->count());
        $this->assertSame(1,DB::table('stock_movements')->where('kind','transfer_return')->count());
    }
    public function test_receipt_cannot_be_reassigned_or_cancelled_after_completion(): void
    {
        $row=$this->send();
        $this->post(route('branch-transfers.resolve',$row->id),$this->receipt($row))->assertSessionHas('success');
        $target=Item::where('parent_id',$this->b->id)->firstOrFail();
        $this->post(route('branch-transfers.resolve',$row->id),$this->receipt($row,['target_item_id'=>$target->id]))->assertStatus(409);
        $this->post(route('branch-transfers.resolve',$row->id),['action'=>'cancel','version'=>1,'confirm'=>1])->assertStatus(409);
        $this->assertSame(4,(int)$target->fresh()->quantity);
    }
    public function test_existing_destination_product_keeps_its_own_prices_and_settings(): void
    {
        $target=Item::create(['parent_id'=>$this->b->id,'title'=>'Yerel yağ','units'=>$this->unitB,'quantity'=>3,'purchase_price'=>90,'sales_price'=>190,'notes'=>'Yerel not']);
        $row=$this->send();
        $this->post(route('branch-transfers.resolve',$row->id),$this->receipt($row,['target_item_id'=>$target->id]))->assertSessionHas('success');
        $this->assertSame(7,(int)$target->fresh()->quantity);$this->assertEquals(190,$target->fresh()->sales_price);$this->assertSame('Yerel not',$target->fresh()->notes);
    }
    public function test_wrong_unit_and_foreign_destination_item_do_not_change_stock(): void
    {
        $kg=DB::table('units')->insertGetId(['parent_id'=>$this->b->id,'unit'=>'Kilogram']);
        $row=$this->send();
        $this->post(route('branch-transfers.resolve',$row->id),$this->receipt($row,['target_unit_id'=>$kg]))->assertSessionHasErrors('transfer');
        $this->post(route('branch-transfers.resolve',$row->id),$this->receipt($row,['target_item_id'=>$this->item->id]))->assertNotFound();
        $this->assertSame('pending',$row->fresh()->status);$this->assertSame(16,(int)$this->item->fresh()->quantity);
        $this->assertSame(0,Item::where('parent_id',$this->b->id)->count());
    }
    public function test_source_owner_cannot_receive_on_behalf_of_target_and_outsiders_cannot_read_transfer(): void
    {
        $row=$this->send();
        $this->actingAs($this->a)->post(route('branch-transfers.resolve',$row->id),$this->receipt($row))->assertForbidden();
        $this->actingAs($this->c)->get(route('branch-transfers.receipt',$row->id))->assertForbidden();
        $this->post(route('branch-transfers.resolve',$row->id),['action'=>'cancel','version'=>1,'confirm'=>1])->assertForbidden();
        $this->actingAs($this->b)->post(route('branch-transfers.resolve',$row->id),$this->receipt($row))->assertSessionHas('success');
    }
    public function test_foreign_branch_same_branch_and_wrong_source_item_are_rejected(): void
    {
        $this->actingAs($this->manager);
        $this->post(route('branch-transfers.send'),$this->payload(['target_owner_id'=>$this->c->id]))->assertForbidden();
        $this->post(route('branch-transfers.send'),$this->payload(['target_owner_id'=>$this->a->id]))->assertStatus(422);
        $this->post(route('branch-transfers.send'),$this->payload(['source_owner_id'=>$this->b->id,'target_owner_id'=>$this->a->id]))->assertNotFound();
        $this->assertSame(20,(int)$this->item->fresh()->quantity);$this->assertSame(0,BranchStockTransfer::count());
    }
    public function test_insufficient_negative_or_stale_quantity_does_not_reserve_stock(): void
    {
        $this->actingAs($this->manager);
        foreach([0,-2,21] as $quantity) $this->post(route('branch-transfers.send'),$this->payload(['quantity'=>$quantity]))->assertSessionHasErrors();
        $this->assertSame(20,(int)$this->item->fresh()->quantity);$this->assertSame(0,BranchStockTransfer::count());
    }
    public function test_pending_transfer_prevents_item_deletion_unit_change_and_branch_detach(): void
    {
        $row=$this->send();
        $this->actingAs($this->a)->delete(route('item.destroy',$this->item))->assertSessionHasErrors('item');
        $this->actingAs($this->admin)->post(route('branches.membership',$this->group->id),['owner_id'=>$this->a->id,'version'=>$this->group->fresh()->version,'action'=>'detach','confirm'=>1])->assertStatus(409);
        $this->assertNotNull($this->item->fresh());
        $this->assertDatabaseHas('business_branches',['owner_id'=>$this->a->id]);
        try{$item=$this->item->fresh();$item->units=$this->unitB;$item->save();$this->fail('Unit change permitted');}
        catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('item',$e->errors());}
    }
    public function test_receipt_failure_rolls_back_target_creation_and_transfer_status(): void
    {
        $row=$this->send();
        Event::listen('eloquent.created: '.Item::class,function(){throw new \RuntimeException('Simulated receipt failure');});
        try {app(BranchTransfers::class)->resolve($row->id,$this->receipt($row),true);$this->fail('Expected failure');}
        catch(\RuntimeException $e){$this->assertSame('Simulated receipt failure',$e->getMessage());}
        $this->assertSame('pending',$row->fresh()->status);
        $this->assertSame(0,Item::where('parent_id',$this->b->id)->count());
        $this->assertSame(0,DB::table('stock_movements')->where('kind','transfer_in')->count());
    }
    public function test_single_shop_recovery_guard_prevents_cross_branch_stock_duplication(): void
    {
        $row=$this->send();$this->post(route('branch-transfers.resolve',$row->id),$this->receipt($row))->assertSessionHas('success');
        foreach([$this->a,$this->b] as $owner)try{BranchTransfers::guardRecovery($owner->id);$this->fail('Unsafe restore allowed');}
            catch(\App\Services\Recovery\RecoveryFailure $e){$this->assertStringContainsString('stok',$e->getMessage());}
        BranchTransfers::guardRecovery($this->c->id);
        $this->assertSame(16,(int)$this->item->fresh()->quantity);
    }
    public function test_comparison_uses_approved_payments_counter_sales_expenses_and_current_debt(): void
    {
        $invoice=Invoice::create(['parent_id'=>$this->a->id,'invoice_id'=>1,'invoice_date'=>'2026-10-15','external_labor_amount'=>1000,'status'=>1]);
        DB::table('invoice_payments')->insert([['parent_id'=>$this->a->id,'invoice_id'=>$invoice->id,'amount'=>200,'payment_date'=>'2026-10-15','payment_status'=>'success'],
            ['parent_id'=>$this->a->id,'invoice_id'=>$invoice->id,'amount'=>500,'payment_date'=>'2026-10-15','payment_status'=>'pending'],
            ['parent_id'=>$this->c->id,'invoice_id'=>$invoice->id,'amount'=>900,'payment_date'=>'2026-10-15','payment_status'=>'success']]);
        DB::table('counter_sales')->insert(['parent_id'=>$this->a->id,'created_by'=>$this->manager->id,'request_key'=>(string)Str::uuid(),'inventory_key'=>(string)Str::uuid(),
            'item_id'=>$this->item->id,'item_title'=>'Satış','quantity'=>1,'unit_price'=>100,'amount'=>100,'sale_date'=>'2026-10-15','stock_movement_id'=>1]);
        DB::table('expenses')->insert(['parent_id'=>$this->a->id,'amount'=>50,'date'=>'2026-10-15']);
        $this->actingAs($this->manager);
        $rows=collect(app(BranchReports::class)->compare($this->group,'2026-10-01','2026-10-31'))->keyBy('owner_id');
        $this->assertEquals(300,$rows[$this->a->id]['collections']);$this->assertEquals(50,$rows[$this->a->id]['expenses']);
        $this->assertEquals(250,$rows[$this->a->id]['cash_difference']);
        $this->assertFalse($rows->has($this->c->id));
        // A deliberately corrupt foreign-parent payment must not reduce this shop's debt.
        $this->assertEquals(800,$rows[$this->a->id]['outstanding']);
        $this->get(route('branches.report',['start'=>'2026-10-01','end'=>'2026-10-31']))->assertOk()->assertSee('Karşılaştırmalı rapor');
    }
    public function test_activity_history_is_scoped_bounded_and_excludes_raw_forms(): void
    {
        $this->actingAs($this->a)->post(route('tax.store'),['title'=>'Sensitive title','rate'=>20,'password'=>'DO_NOT_RECORD_THIS','_token'=>'csrf-placeholder'])->assertSessionHas('success');
        $log=DB::table('branch_activity_logs')->where('action','operation')->latest('id')->first();
        $this->assertNotNull($log);$this->assertSame($this->a->id,$log->owner_id);$this->assertSame($this->a->id,$log->actor_id);
        $this->assertStringNotContainsString('DO_NOT_RECORD_THIS',$log->details);
        $this->assertStringNotContainsString('Sensitive title',$log->details);
        $this->actingAs($this->manager)->get(route('branches.history'))->assertOk()->assertSee('Şube işlemleri geçmişi');
        $this->get(route('branches.history',['owner'=>$this->c->id]))->assertForbidden();
        DB::table('branch_activity_logs')->where('id',$log->id)->update(['created_at'=>now()->subDays(181)]);
        $this->artisan('platform:purge-history')->assertExitCode(0);
        $this->assertDatabaseMissing('branch_activity_logs',['id'=>$log->id]);
        $this->assertNotNull($this->item->fresh());
    }
    public function test_read_only_transfer_access_and_admin_module_escalation_are_blocked(): void
    {
        $this->grant(['stock']);
        $row=$this->send();
        $this->actingAs($this->staff)->get(route('branch-transfers.index'))->assertOk();
        $this->post(route('branch-transfers.resolve',$row->id),$this->receipt($row))->assertForbidden();
        foreach(['users.index','role.index','backups.index','communication-settings.index'] as $route)
            $this->get($this->staffUrl($route))->assertForbidden();
        $this->post(route('branches.staff.save',$this->group->id),['version'=>$this->group->fresh()->version,'action'=>'grant',
            'user_id'=>$this->staff->id,'owner_id'=>$this->b->id,'modules'=>['stock'],'can_write'=>1])->assertForbidden();
        $this->assertSame('pending',$row->fresh()->status);
    }
    public function test_another_centre_cannot_read_reports_history_staff_or_receipts(): void
    {
        $row=$this->send();
        $this->actingAs($this->admin);
        $other=app(BusinessGroups::class)->create(['name'=>'Other centre','manager_name'=>'Other manager','email'=>'other@ops.test','password'=>'long-password']);
        $this->actingAs($other->manager);
        foreach(['branches.report','branches.history','branches.staff'] as $route)
            $this->get(route($route,['group'=>$this->group->id]))->assertNotFound();
        $this->get(route('branch-transfers.receipt',$row->id))->assertForbidden();
        $this->post(route('branch-transfers.send'),$this->payload())->assertForbidden();
        $this->assertSame(16,(int)$this->item->fresh()->quantity);
    }
    public function test_inactive_staff_or_home_shop_invalidates_delegated_context(): void
    {
        $this->grant(['stock'],true);$url=$this->staffUrl('tax.store');
        DB::table('users')->where('id',$this->staff->id)->update(['is_active'=>0]);
        $this->actingAs($this->staff->fresh())->post($url,['title'=>'Inactive','rate'=>20])->assertRedirect(route('login'));$this->assertGuest();
        DB::table('users')->where('id',$this->staff->id)->update(['is_active'=>1]);
        DB::table('users')->where('id',$this->a->id)->update(['is_active'=>0]);
        $this->actingAs($this->staff->fresh())->post($url,['title'=>'Inactive home','rate'=>20])->assertStatus(409);
        $this->assertDatabaseMissing('taxes',['title'=>'Inactive']);
        $this->assertDatabaseMissing('taxes',['title'=>'Inactive home']);
    }
    public function test_stale_receipt_version_and_recovery_pause_do_not_mutate_stock(): void
    {
        $row=$this->send();
        $this->post(route('branch-transfers.resolve',$row->id),$this->receipt($row,['version'=>99]))->assertStatus(409);
        DB::table('business_recovery_locks')->insert(['owner_id'=>$this->b->id,'recovery_id'=>999,'created_at'=>now()]);
        $this->post(route('branch-transfers.resolve',$row->id),$this->receipt($row))->assertStatus(409);
        $this->assertSame('pending',$row->fresh()->status);$this->assertSame(0,Item::where('parent_id',$this->b->id)->count());
        $this->assertSame(16,(int)$this->item->fresh()->quantity);
    }
    public function test_legacy_product_without_inventory_key_can_be_transferred_safely(): void
    {
        $this->item->inventory_key=null;$this->item->save();
        $row=$this->send();
        $this->assertNotEmpty($this->item->fresh()->inventory_key);
        $this->assertSame($this->item->fresh()->inventory_key,$row->source_inventory_key);
    }
    public function test_send_failure_rolls_back_reservation_and_ledger(): void
    {
        $this->actingAs($this->manager);
        Event::listen('eloquent.updated: '.Item::class,function(){throw new \RuntimeException('Simulated send failure');});
        try{app(BranchTransfers::class)->send($this->group->id,$this->payload());$this->fail('Expected failure');}
        catch(\RuntimeException $e){$this->assertSame('Simulated send failure',$e->getMessage());}
        $this->assertSame(20,(int)$this->item->fresh()->quantity);$this->assertSame(0,BranchStockTransfer::count());
        $this->assertSame(0,DB::table('stock_movements')->where('kind','transfer_out')->count());
    }
    public function test_transfer_only_does_not_inflate_comparative_report(): void
    {
        $before=app(BranchReports::class)->compare($this->group,'2026-10-01','2026-10-31');
        $row=$this->send();$this->post(route('branch-transfers.resolve',$row->id),$this->receipt($row))->assertSessionHas('success');
        $after=app(BranchReports::class)->compare($this->group,'2026-10-01','2026-10-31');
        $this->assertSame($before,$after);
    }
    public function test_transfer_note_is_escaped_in_ui_and_history_does_not_store_it(): void
    {
        $row=$this->send(['note'=>'<script>alert("xss")</script>']);
        $this->get(route('branch-transfers.index'))->assertOk()->assertDontSee('<script>alert("xss")</script>',false);
        $log=DB::table('branch_activity_logs')->where('action','transfer.sent')->latest('id')->first();
        $this->assertStringNotContainsString('script',$log->details);
        $this->assertSame($this->manager->id,$log->actor_id);
    }
    public function test_history_and_transfer_management_pages_render_without_secret_or_permission_leak(): void
    {
        $this->grant(['stock'],true);
        foreach(['branches.staff','branches.report','branches.history','branch-transfers.index'] as $route)
            $this->actingAs($this->manager)->get(route($route))->assertOk()->assertDontSee('long-password-123');
        $row=$this->send();
        $this->get(route('branch-transfers.receipt',$row->id))->assertOk()->assertSee('Yeni ürün');
    }
    public function test_completed_transfer_uses_historical_names_after_a_branch_leaves(): void
    {
        $row=$this->send();$this->post(route('branch-transfers.resolve',$row->id),$this->receipt($row))->assertSessionHas('success');
        $this->actingAs($this->admin);
        app(BusinessGroups::class)->membership($this->group->id,$this->a->id,$this->group->fresh()->version,false);
        DB::table('users')->where('id',$this->a->id)->update(['name'=>'PRIVATE_RENAMED_SHOP']);
        $this->actingAs($this->manager)->get(route('branch-transfers.index'))->assertOk()->assertSee('Sakarya')->assertDontSee('PRIVATE_RENAMED_SHOP');
    }
}
