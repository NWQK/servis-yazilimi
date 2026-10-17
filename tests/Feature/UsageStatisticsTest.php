<?php

namespace Tests\Feature;

use App\Models\{User, Item, Invoice, Service, ServiceItem, ServiceType};
use App\Services\{UsageStatistics, InventoryAccounting, BusinessGroups, BranchContext};
use Illuminate\Support\Facades\{DB, Schema, Mail, Hash};
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class UsageStatisticsTest extends TestCase
{
    private User $admin, $owner, $other;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);
        DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        Schema::table('invoice_payments',function($t){foreach(['transaction_id','receipt','payment_type','payment_status'] as $c)$t->string($c)->nullable();});
        $this->travelTo(\Carbon\Carbon::parse('2026-10-17 12:00:00', 'Europe/Istanbul'));
        Mail::fake();
        $this->admin=User::create(['name'=>'Admin','type'=>'super admin','password'=>Hash::make('password'),'is_active'=>1]);
        $this->owner=User::create(['name'=>'Garage','type'=>'owner','password'=>'x','is_active'=>1]);
        $this->other=User::create(['name'=>'Other','type'=>'owner','password'=>'x','is_active'=>1]);
        DB::table('settings')->insert(['parent_id'=>1,'name'=>'pricing_feature','value'=>'off']);
        $this->grant($this->owner,['manage item','manage service report']);
        $this->actingAs($this->owner);
    }

    private function grant(User $user,array $permissions): void
    {
        $role=Role::create(['name'=>'stats-'.$user->id,'parent_id'=>$user->parent_id?:$user->id,'guard_name'=>'web']);
        foreach($permissions as $permission)$role->givePermissionTo(Permission::findOrCreate($permission,'web'));
        $user->assignRole($role);
    }
    private function product(string $title='Motor oil',?int $owner=null): Item
    {
        $tenant=$owner??$this->owner->id;
        $unit=DB::table('units')->insertGetId(['unit'=>'Litre','parent_id'=>$tenant]);
        return app(InventoryAccounting::class)->savePurchase($tenant,['title'=>$title,'units'=>$unit,'quantity'=>100,'purchase_price'=>5,'sales_price'=>10]);
    }
    private function invoice(Item $item,int $quantity,string $date='2026-10-10'): Invoice
    {
        $invoice=Invoice::create(['parent_id'=>$item->parent_id,'invoice_id'=>1,'invoice_date'=>$date]);
        app(InventoryAccounting::class)->add($invoice,['item'=>$item->id,'quantity'=>$quantity]);
        return $invoice;
    }
    private function job(int $type,string $status='completed',string $date='2026-10-10',?int $owner=null): Service
    {
        $tenant=$owner??$this->owner->id;
        $job=Service::create(['parent_id'=>$tenant,'service_id'=>1,'status'=>$status,'service_date'=>$date]);
        ServiceItem::create(['service_id'=>$job->id,'type_id'=>$type,'parent_id'=>$tenant]);
        return $job;
    }
    private function report(): array {return app(UsageStatistics::class)->report($this->owner->id,'2026-10-01','2026-10-31');}

    public function test_product_quantity_includes_unpaid_invoices_and_counter_sales_once(): void
    {
        $item=$this->product();$invoice=$this->invoice($item,5);
        $key=(string)\Str::uuid();
        app(InventoryAccounting::class)->counterSale($this->owner->id,$this->owner->id,$item->id,2,$key,10);
        app(InventoryAccounting::class)->counterSale($this->owner->id,$this->owner->id,$item->id,2,$key,10);
        $result=$this->report();$row=$result['products']->first();
        $this->assertSame(1,$result['product_count']);$this->assertEquals(7,$row->quantity);
        $this->assertEquals(5,$row->invoice_quantity);$this->assertEquals(2,$row->counter_quantity);
        DB::table('invoice_payments')->insert(['parent_id'=>$this->owner->id,'invoice_id'=>$invoice->id,'amount'=>10,'payment_status'=>'success']);
        $this->assertEquals(7,$this->report()['products']->first()->quantity);
    }
    public function test_edits_returns_removed_lines_and_deleted_invoices_do_not_inflate_usage(): void
    {
        $item=$this->product();$invoice=$this->invoice($item,5);$line=$invoice->items()->first();
        app(InventoryAccounting::class)->sync($invoice,[['id'=>$line->id,'item'=>$item->id,'quantity'=>2]]);
        $this->assertEquals(2,$this->report()['products']->first()->quantity);
        app(InventoryAccounting::class)->remove($this->owner->id,$invoice->id,$line->id);
        $this->assertSame(0,$this->report()['product_count']);
        $this->invoice($item,3)->delete();
        $this->assertSame(0,$this->report()['product_count']);
    }
    public function test_product_snapshot_survives_catalogue_deletion_and_purchase_is_not_usage(): void
    {
        $item=$this->product();$this->assertSame(0,$this->report()['product_count']);
        $this->invoice($item,3);$item->delete();
        $row=$this->report()['products']->first();
        $this->assertSame('Motor oil',$row->title);$this->assertSame('Litre',$row->unit_name);$this->assertEquals(3,$row->quantity);
    }
    public function test_same_named_products_with_different_identities_are_not_merged(): void
    {
        $this->invoice($this->product('Same name'),2);$this->invoice($this->product('Same name'),5);
        $report=$this->report();$this->assertSame(2,$report['product_count']);$this->assertEquals(5,$report['products'][0]->quantity);
    }
    public function test_service_type_counts_completed_jobs_once_not_repeated_lines_or_invoices(): void
    {
        $oil=ServiceType::create(['parent_id'=>$this->owner->id,'type'=>'Oil maintenance']);
        $job=$this->job($oil->id);$this->job($oil->id);
        ServiceItem::create(['parent_id'=>$this->owner->id,'service_id'=>$job->id,'type_id'=>$oil->id]);
        $this->job($oil->id,'scheduled');$this->job($oil->id,'cancelled');$this->job($oil->id,'completed','2026-09-30');
        Invoice::create(['parent_id'=>$this->owner->id,'service'=>$job->id,'invoice_date'=>'2026-10-10']);
        $report=$this->report();$this->assertSame(2,$report['completed_services']);$this->assertSame(1,$report['service_type_count']);$this->assertEquals(2,$report['services'][0]->uses);
        $job->status='in_progress';$job->save();$this->assertEquals(1,$this->report()['services'][0]->uses);
    }
    public function test_date_boundaries_and_legacy_missing_dates_use_record_creation_date(): void
    {
        $item=$this->product();foreach(['2026-10-01','2026-10-31','2026-09-30','2026-11-01'] as $date)$this->invoice($item,1,$date);
        $invoice=$this->invoice($item,1);DB::table('invoices')->where('id',$invoice->id)->update(['invoice_date'=>null,'created_at'=>'2026-10-31 23:59:59']);
        $this->assertEquals(3,$this->report()['products'][0]->quantity);
    }
    public function test_foreign_rows_and_malformed_cross_business_links_cannot_leak(): void
    {
        $item=$this->product();$invoice=$this->invoice($item,2);
        $foreign=$this->product('FOREIGN_SECRET',$this->other->id);$otherInvoice=$this->invoice($foreign,20);
        DB::table('invoice_items')->insert(['parent_id'=>$this->owner->id,'invoice_id'=>$otherInvoice->id,'item'=>$item->id,'quantity'=>90]);
        DB::table('invoice_items')->insert(['parent_id'=>$this->owner->id,'invoice_id'=>$invoice->id,'item'=>$foreign->id,'quantity'=>90]);
        $type=ServiceType::create(['parent_id'=>$this->other->id,'type'=>'PRIVATE_SERVICE']);$this->job($type->id);
        $this->assertEquals(2,$this->report()['products'][0]->quantity);$this->assertSame(0,$this->report()['service_type_count']);
        $this->get(route('report.usage',['parent_id'=>$this->other->id]))->assertOk()->assertDontSee('FOREIGN_SECRET')->assertDontSee('PRIVATE_SERVICE')->assertHeader('Cache-Control','no-store, private');
    }
    public function test_statistics_require_both_permissions_and_reject_customer_accounts(): void
    {
        $staff=User::create(['name'=>'Staff','type'=>'employee','parent_id'=>$this->owner->id,'password'=>'x','is_active'=>1]);$this->grant($staff,['manage item']);
        $this->actingAs($staff)->get(route('report.usage'))->assertForbidden();
        $staff->roles->first()->givePermissionTo(Permission::findOrCreate('manage service report','web'));
        $this->actingAs($staff->fresh())->get(route('report.usage'))->assertOk();
        $client=User::create(['name'=>'Client','type'=>'client','parent_id'=>$this->owner->id,'password'=>'x','is_active'=>1]);$this->grant($client,['manage item','manage service report']);
        $this->actingAs($client)->get(route('report.usage'))->assertForbidden();
        $reception=User::create(['name'=>'Reception','type'=>'Reception','parent_id'=>$this->owner->id,'password'=>'x','is_active'=>1]);$this->grant($reception,['manage item','manage service report']);
        $this->actingAs($reception)->get(route('report.usage'))->assertOk();
    }
    public function test_empty_state_and_invalid_date_ranges_are_clear(): void
    {
        $this->get(route('report.usage'))->assertOk()->assertSee('Henüz kayıt yok')->assertSee('2026-10-01')->assertSee('2026-10-17');
        foreach([['start_date'=>'not-a-date'],['start_date'=>['2026-10-01']],['start_date'=>'2026-10-31','end_date'=>'2026-10-01'],['start_date'=>'2020-01-01','end_date'=>'2026-10-17']] as $query)
            $this->getJson(route('report.usage',$query))->assertUnprocessable();
    }
    public function test_branch_manager_filter_keeps_the_selected_business_context(): void
    {
        $this->invoice($this->product('SELECTED_SHOP_PRODUCT'),2);$this->invoice($this->product('FOREIGN_BRANCH_PRODUCT',$this->other->id),8);
        $this->actingAs($this->admin);$group=app(BusinessGroups::class)->create(['name'=>'Group','manager_name'=>'Centre','email'=>'centre@stats.test','password'=>'long-password123']);
        foreach([$this->owner,$this->other] as $owner)app(BusinessGroups::class)->membership($group->id,$owner->id,$group->fresh()->version,true);
        $manager=$group->manager;$token=BranchContext::token($group->fresh(),$this->owner,$manager);
        $this->actingAs($manager)->get(route('report.usage',['_branch'=>$token,'start_date'=>'2026-10-01','end_date'=>'2026-10-31']))->assertOk()->assertSee('SELECTED_SHOP_PRODUCT')->assertDontSee('FOREIGN_BRANCH_PRODUCT')->assertSee('name="_branch"',false);
    }
    public function test_changed_units_are_displayed_separately_instead_of_adding_litres_and_pieces(): void
    {
        $item=$this->product();$this->invoice($item,3);
        $pieces=DB::table('units')->insertGetId(['unit'=>'Adet','parent_id'=>$this->owner->id]);
        $item->units=$pieces;$item->save();$this->invoice($item,5);
        $report=$this->report();$this->assertSame(1,$report['product_count']);$this->assertCount(2,$report['products']);
        $this->assertEquals(5,$report['products'][0]->quantity);$this->assertSame('Adet',$report['products'][0]->unit_name);
        $this->assertEquals(3,$report['products'][1]->quantity);$this->assertSame('Litre',$report['products'][1]->unit_name);
    }
    public function test_top_ten_is_bounded_and_untrusted_catalogue_titles_are_escaped(): void
    {
        for($i=1;$i<=12;$i++)$this->invoice($this->product('Product '.$i),$i);
        $dangerous='<img src=x onerror=alert(1)>';$this->invoice($this->product($dangerous),20);
        $report=$this->report();$this->assertSame(13,$report['product_count']);$this->assertCount(10,$report['products']);
        $this->assertEquals(20,$report['products'][0]->quantity);$this->assertEquals(4,$report['products'][9]->quantity);
        $this->get(route('report.usage'))->assertOk()->assertDontSee($dangerous,false)->assertSee($dangerous);
    }
}
