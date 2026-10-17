<?php
namespace Tests\Feature;

use App\Models\{User, Employee, LoggedHistory, NoticeBoard, ServiceType, Tax, Subscription, PackageTransaction, Vehicle, VehicleQrCode, Service, Invoice, InvoicePayment};
use Illuminate\Support\Facades\{DB, Schema, Hash, Mail};
use Spatie\Permission\Models\{Role, Permission};
use Tests\TestCase;

class AdversarialSystemAuditTest extends TestCase
{
    private User $admin, $owner, $other;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array','communications.async'=>false]);
        DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        Schema::table('invoice_payments',function($t){foreach(['transaction_id','receipt','payment_type','payment_status'] as $name)$t->string($name)->nullable();});
        if(!Schema::hasColumn('service_items','tax'))Schema::table('service_items',fn($t)=>$t->string('tax')->nullable());
        Mail::fake();
        $this->admin=User::create(['name'=>'Admin','email'=>'admin@audit.test','type'=>'super admin','password'=>Hash::make('Password123!'),'is_active'=>1]);
        $this->owner=User::create(['name'=>'Shop','email'=>'owner@audit.test','type'=>'owner','password'=>Hash::make('Password123!'),'is_active'=>1]);
        $this->other=User::create(['name'=>'Other','email'=>'other@audit.test','type'=>'owner','password'=>'x','is_active'=>1]);
        DB::table('settings')->updateOrInsert(['parent_id'=>1,'name'=>'pricing_feature'],['value'=>'off']);
        $this->grant($this->owner,['create role','edit role','manage role','create user','edit user','show user','manage logged history','delete logged history',
            'manage note','create note','edit note','delete note','create service type','edit service type','edit employee','manage company settings']);
        $this->actingAs($this->owner);
    }
    private function grant(User $user,array $names): void
    {
        $role=Role::firstOrCreate(['name'=>'audit-role-'.$user->id,'parent_id'=>$user->parent_id?:$user->id,'guard_name'=>'web']);
        foreach($names as $name)$role->givePermissionTo(Permission::findOrCreate($name,'web'));
        $user->assignRole($role);
    }
    public function test_reserved_role_names_cannot_create_privileged_account_types(): void
    {
        foreach(['super admin',' Super Admin ','owner','OWNER','employee','branch_manager','client'] as $name){
            $this->post(route('role.store'),['title'=>$name,'user_permission'=>[Permission::findByName('create role')->id]])->assertSessionHasErrors('title');
            $this->assertFalse(Role::where('parent_id',$this->owner->id)->where('name',$name)->exists());
        }
        $role=Role::create(['name'=>'Legacy harmless name','guard_name'=>'web','parent_id'=>$this->owner->id]);
        $this->put(route('role.update',$role->id),['name'=>'super admin'])->assertSessionHasErrors('name');
        $this->assertSame('Legacy harmless name',$role->fresh()->name);
    }
    public function test_legacy_reserved_role_cannot_be_assigned_even_if_present_in_database(): void
    {
        $role=Role::create(['name'=>'super admin','guard_name'=>'web','parent_id'=>$this->owner->id]);
        $before=User::count();
        $this->post(route('users.store'),['name'=>'Attack','email'=>'attack@audit.test','password'=>'Password123!','role'=>$role->id])->assertNotFound();
        $this->assertSame($before,User::count());
    }
    public function test_custom_role_remains_creatable_and_assignable(): void
    {
        $this->post(route('role.store'),['title'=>'Reception','user_permission'=>[Permission::findByName('create role')->id]])->assertSessionHasNoErrors();
        $role=Role::where('name','Reception')->firstOrFail();
        $this->post(route('users.store'),['name'=>'Receptionist','email'=>'reception@audit.test','password'=>'Password123!','role'=>$role->id])->assertSessionHasNoErrors();
        $this->assertSame('Reception',User::where('email','reception@audit.test')->value('type'));
    }
    public function test_guessed_log_ids_do_not_read_or_delete_another_business(): void
    {
        $log=LoggedHistory::create(['parent_id'=>$this->other->id,'user_id'=>$this->other->id,'ip'=>'203.0.113.9','date'=>now()->toDateString(),'details'=>'PRIVATE_DEVICE','type'=>'desktop']);
        $this->get(route('logged.history.show',$log->id))->assertNotFound();
        $this->delete(route('logged.history.destroy',$log->id))->assertNotFound();
        $this->assertNotNull($log->fresh());
    }
    public function test_notes_cannot_leak_from_parent_admin_or_foreign_business(): void
    {
        foreach([$this->admin,$this->other] as $source){
            $note=new NoticeBoard();$note->title='PRIVATE_NOTE_'.$source->id;$note->description='Secret';$note->parent_id=$source->id;$note->save();
            $this->get(route('note.edit',$note->id))->assertNotFound();
            $this->put(route('note.update',$note->id),['title'=>'Changed','description'=>'Changed'])->assertNotFound();
            $this->delete(route('note.destroy',$note->id))->assertNotFound();
            $this->assertSame('Secret',$note->fresh()->description);
        }
        $this->get(route('note.index'))->assertOk()->assertDontSee('PRIVATE_NOTE_');
    }
    public function test_foreign_service_type_edit_and_tax_injection_are_rejected(): void
    {
        $foreign=ServiceType::create(['type'=>'Private','rate'=>100,'parent_id'=>$this->other->id]);
        $tax=new Tax();$tax->title='Foreign tax';$tax->rate=20;$tax->parent_id=$this->other->id;$tax->save();
        $this->get(route('service-type.edit',$foreign->id))->assertNotFound();
        foreach([['tax'=>[$tax->id],'rate'=>100],['tax'=>[],'rate'=>-5],['tax'=>'broken','rate'=>100]] as $extra){
            $this->post(route('service-type.store'),['type'=>'Bad']+$extra)->assertSessionHas('error');
        }
        $this->assertSame(0,ServiceType::where('parent_id',$this->owner->id)->count());
    }
    public function test_theme_endpoint_cannot_write_smtp_or_platform_controls(): void
    {
        foreach([$this->owner,$this->admin] as $actor){
            $this->actingAs($actor)->post(route('theme.settings'),['theme_mode'=>'dark','accent_color'=>'bike-and-more',
                'custom_color'=>'--primary-rgb: 0,0,0','SERVER_PASSWORD'=>'injected','email_enabled'=>'on','app_name'=>'injected','register_page'=>'off'])->assertSessionHasNoErrors();
            $this->assertSame('dark',DB::table('settings')->where('parent_id',$actor->id)->where('name','theme_mode')->value('value'));
            $this->assertFalse(DB::table('settings')->where('parent_id',$actor->id)->whereIn('name',['SERVER_PASSWORD','email_enabled'])->exists());
        }
        $this->postJson(route('theme.settings'),['layout_font'=>"');alert(1);//"])->assertUnprocessable();
    }
    public function test_company_form_does_not_write_unrelated_settings_and_requires_permission(): void
    {
        $data=['company_name'=>'Garage','company_email'=>'garage@audit.test','company_phone'=>'5551234567','company_address'=>'Address','SERVER_PASSWORD'=>'injected','email_enabled'=>'on'];
        $this->post(route('setting.company'),$data)->assertSessionHasNoErrors();
        $this->assertFalse(DB::table('settings')->where('parent_id',$this->owner->id)->where('name','SERVER_PASSWORD')->exists());
        $staff=User::create(['name'=>'No rights','email'=>'none@audit.test','type'=>'employee','password'=>'x','parent_id'=>$this->owner->id,'is_active'=>1]);
        $this->actingAs($staff)->post(route('setting.company'),$data)->assertForbidden();
    }
    public function test_hidden_central_setting_routes_cannot_be_posted_by_owner(): void
    {
        foreach(['setting.site.seo','setting.google.recaptcha','setting.footer'] as $name)$this->post(route($name),[])->assertForbidden();
    }
    public function test_admin_identity_edit_ignores_injected_password_twofa_and_subscription(): void
    {
        $this->grant($this->admin,['edit user']);$password=$this->owner->password;
        $this->actingAs($this->admin)->put(route('users.update',$this->owner->id),['name'=>'Renamed','email'=>$this->owner->email,
            'password'=>'plaintext','twofa_secret'=>'ABCDEF','is_active'=>0,'subscription'=>999,'type'=>'super admin','parent_id'=>999])->assertSessionHasNoErrors();
        $fresh=$this->owner->fresh();$this->assertSame('Renamed',$fresh->name);$this->assertSame($password,$fresh->password);
        $this->assertNull($fresh->twofa_secret);$this->assertSame('owner',$fresh->type);$this->assertSame(1,(int)$fresh->is_active);$this->assertNotEquals(999,$fresh->subscription);
    }
    public function test_employee_edit_updates_existing_details_without_orphaning_or_duplicate(): void
    {
        $staff=User::create(['name'=>'Staff','email'=>'staff@audit.test','password'=>'x','type'=>'employee','parent_id'=>$this->owner->id,'is_active'=>1]);
        $details=Employee::create(['employee_id'=>4,'user_id'=>$staff->id,'parent_id'=>$this->owner->id,'document'=>'keep.pdf','address'=>'Old']);
        $this->put(route('employee.update',$staff->id),['name'=>'New name','email'=>$staff->email,'phone_number'=>'5551234567','gender'=>'Male','age'=>30,'joining_date'=>'2026-01-01','address'=>'New'])->assertSessionHasNoErrors();
        $this->assertSame(1,Employee::count());$this->assertSame('New',$details->fresh()->address);$this->assertSame('keep.pdf',$details->fresh()->document);
    }
    public function test_package_change_does_not_reactivate_departed_personnel_or_archived_clients(): void
    {
        $plan=Subscription::create(['title'=>'500','interval'=>'Monthly','vehicle_limit'=>500]);
        $staff=User::create(['name'=>'Left','email'=>'left@audit.test','password'=>'x','type'=>'employee','parent_id'=>$this->owner->id,'is_active'=>0]);
        DB::table('users')->where('id',$staff->id)->update(['departed_at'=>now()]);
        $customer=User::create(['name'=>'Archived','password'=>'x','type'=>'client','parent_id'=>$this->owner->id,'is_active'=>0]);
        DB::table('users')->where('id',$customer->id)->update(['client_archived_at'=>now()]);
        SetLimit($plan,$this->owner->id);
        $this->assertSame(0,(int)$staff->fresh()->is_active);$this->assertSame(0,(int)$customer->fresh()->is_active);
    }
    public function test_installer_updater_and_removed_integrations_are_unavailable_to_all_actors(): void
    {
        foreach([null,$this->owner,$this->admin] as $actor){
            auth()->logout();if($actor)$this->actingAs($actor);
            foreach(['/install','/install/environment/classic','/update/database','/update/final'] as $path)$this->get($path)->assertNotFound();
            $this->post('/install/environment/saveClassic',['envConfig'=>'INJECTED'])->assertNotFound();
        }
        $this->actingAs($this->owner)->get('/n8n')->assertStatus(410);
        $this->post('/openai/settings',['openai_module'=>'on'])->assertStatus(410);
    }
    public function test_guest_reports_redirect_to_login_instead_of_server_error(): void
    {
        auth()->logout();foreach(['report.service','report.income','report.expense','report.profit_loss'] as $name)$this->get(route($name))->assertRedirect(route('login'));
    }
    public function test_registration_switch_is_enforced_on_post(): void
    {
        auth()->logout();DB::table('settings')->updateOrInsert(['parent_id'=>1,'name'=>'register_page'],['value'=>'off']);$before=User::count();
        $this->post('/register',['name'=>'Bypass','email'=>'bypass@audit.test','password'=>'Password123!','password_confirmation'=>'Password123!'])->assertForbidden();
        $this->assertSame($before,User::count());
    }
    public function test_predictable_legacy_email_verification_is_not_accepted_and_random_token_is_single_use(): void
    {
        auth()->logout();$legacy=sha1($this->owner->email);DB::table('users')->where('id',$this->owner->id)->update(['email_verified_at'=>null,'email_verification_token'=>$legacy]);
        $this->get(route('email-verification',$legacy))->assertNotFound();$this->assertNull($this->owner->fresh()->email_verified_at);
        $token=bin2hex(random_bytes(32));DB::table('users')->where('id',$this->owner->id)->update(['email_verification_token'=>$token]);
        $this->get(route('email-verification',$token))->assertRedirect(route('login'));$this->get(route('email-verification',$token))->assertNotFound();
        $this->assertNotNull($this->owner->fresh()->email_verified_at);
    }
    public function test_password_change_revokes_other_sessions_and_keeps_current_session(): void
    {
        $this->withSession(['account_epoch'=>0])->post(route('setting.password'),['current_password'=>'Password123!','new_password'=>'NewPassword123!','confirm_password'=>'NewPassword123!'])->assertSessionHasNoErrors();
        $this->assertSame(1,(int)$this->owner->fresh()->session_epoch);
        $this->get(route('theme.settings'))->assertStatus(405);
        $this->withSession(['account_epoch'=>0])->get(route('setting.index'))->assertRedirect(route('login'));$this->assertGuest();
    }
    public function test_inactive_custom_account_loses_existing_session(): void
    {
        $custom=User::create(['name'=>'Custom','email'=>'custom@audit.test','type'=>'Reception','password'=>'x','parent_id'=>$this->owner->id,'is_active'=>0]);
        $this->actingAs($custom)->get(route('setting.index'))->assertRedirect(route('login'));$this->assertGuest();
    }
    public function test_bank_review_requires_post_and_cannot_repeat_or_reverse_a_resolution(): void
    {
        $plan=Subscription::create(['title'=>'500','interval'=>'Monthly','vehicle_limit'=>500]);
        $order=PackageTransaction::create(['user_id'=>$this->owner->id,'subscription_id'=>$plan->id,'subscription_transactions_id'=>'audit-one','payment_status'=>'Pending','payment_type'=>'Bank Transfer']);
        $url=route('subscription.bank.transfer.action',[$order->id,'accept']);
        $this->actingAs($this->admin)->get($url)->assertStatus(405);
        $this->post($url)->assertSessionHasNoErrors();$expiry=$this->owner->fresh()->subscription_expire_date;
        $this->post($url)->assertSessionHasNoErrors();$this->assertSame($expiry,$this->owner->fresh()->subscription_expire_date);
        $this->post(route('subscription.bank.transfer.action',[$order->id,'reject']))->assertStatus(409);
        $this->assertSame('Success',$order->fresh()->payment_status);
        $this->actingAs($this->other)->post($url)->assertForbidden();
    }
    public function test_customer_invoice_excludes_pending_rejected_payments_and_foreign_invoices(): void
    {
        $vehicle=Vehicle::create(['parent_id'=>$this->owner->id,'license_plate'=>'54TEST1']);
        $service=Service::create(['parent_id'=>$this->owner->id,'vehicle'=>$vehicle->id,'service_id'=>1]);
        $invoice=Invoice::create(['parent_id'=>$this->owner->id,'service'=>$service->id,'invoice_id'=>1,'invoice_date'=>'2026-10-16','external_labor_amount'=>100]);
        foreach([['success',30],['pending',40],['reject',20],[null,5],['success',-10]] as [$status,$amount]){
            $p=new InvoicePayment();$p->parent_id=$this->owner->id;$p->invoice_id=$invoice->id;$p->payment_status=$status;$p->amount=$amount;$p->payment_date='2026-10-16';$p->save();
        }
        $qr=VehicleQrCode::create(['parent_id'=>$this->owner->id,'vehicle_id'=>$vehicle->id,'token'=>bin2hex(random_bytes(32)),'assigned_at'=>now()]);auth()->logout();
        $this->get(route('vehicle-portal.invoice',[$qr->token,$invoice->id]))->assertOk()->assertViewHas('paid',25)->assertViewHas('invoice',fn($i)=>$i->payments->count()===3);
        $foreign=Invoice::create(['parent_id'=>$this->other->id,'invoice_id'=>1]);
        $this->get(route('vehicle-portal.invoice',[$qr->token,$foreign->id]))->assertNotFound();
    }
    public function test_invoiced_tax_rate_and_deletion_cannot_change_historical_total(): void
    {
        $this->grant($this->owner,['edit tax','delete tax']);
        $tax=new Tax();$tax->parent_id=$this->owner->id;$tax->title='KDV';$tax->rate=20;$tax->save();
        $invoice=Invoice::create(['parent_id'=>$this->owner->id,'invoice_id'=>1]);
        \App\Models\InvoiceService::create(['parent_id'=>$this->owner->id,'invoice_id'=>$invoice->id,'service_type'=>0,'rate'=>100,'tax'=>(string)$tax->id]);
        $this->assertEquals(120,$invoice->fresh()->getInvoiceAllTotalAmount());
        $this->put(route('tax.update',$tax->id),['title'=>'KDV','rate'=>10])->assertSessionHasErrors('rate');
        $this->delete(route('tax.destroy',$tax->id))->assertSessionHasErrors('tax');
        $this->assertEquals(20,$tax->fresh()->rate);$this->assertEquals(120,$invoice->fresh()->getInvoiceAllTotalAmount());
        $this->put(route('tax.update',$tax->id),['title'=>'KDV %20','rate'=>20])->assertSessionHasNoErrors();
    }
    public function test_product_form_rejects_foreign_unit_tax_and_invalid_tax_shape(): void
    {
        $this->grant($this->owner,['create item']);
        $unit=DB::table('units')->insertGetId(['unit'=>'Adet','parent_id'=>$this->owner->id]);
        $foreign=DB::table('units')->insertGetId(['unit'=>'Foreign','parent_id'=>$this->other->id]);
        $tax=DB::table('taxes')->insertGetId(['title'=>'Foreign','rate'=>20,'parent_id'=>$this->other->id]);
        $data=['title'=>'Oil','item_code'=>'OIL','quantity'=>2,'units'=>$unit,'purchase_price'=>10,'sales_price'=>20,'purchase_date'=>'2026-10-16'];
        foreach([['units'=>$foreign],['taxs'=>[$tax]],['taxs'=>'invalid']] as $extra)$this->post(route('item.store'),array_replace($data,$extra))->assertSessionHas('error');
        $this->assertSame(0,\App\Models\Item::count());$this->assertSame(0,\App\Models\Expense::count());
        $this->post(route('item.store'),$data)->assertSessionHasNoErrors();$this->assertSame(1,\App\Models\Item::count());
    }
    public function test_employee_cannot_read_edit_or_delete_other_assignees_work(): void
    {
        $staff=User::create(['name'=>'Staff','email'=>'worker@audit.test','password'=>'x','type'=>'employee','parent_id'=>$this->owner->id,'is_active'=>1]);
        $this->grant($staff,['manage service','show service','edit service','delete service']);
        $job=Service::create(['parent_id'=>$this->owner->id,'assign'=>$this->owner->id,'service_id'=>1,'status'=>'scheduled','service_date'=>now()->toDateString()]);
        $this->actingAs($staff)->get(route('service.edit',encrypt($job->id)))->assertForbidden();
        $this->put(route('service.update',$job->id),[])->assertForbidden();
        $this->delete(route('service.destroy',$job->id))->assertForbidden();
        $this->get(route('service.today'))->assertOk()->assertViewHas('services',fn($rows)=>$rows->isEmpty());
        $this->get(route('service.kanban'))->assertOk()->assertViewHas('stages',fn($rows)=>$rows->every(fn($r)=>$r->requests->isEmpty()));
        $this->assertNotNull($job->fresh());
    }
    public function test_quotation_conversion_is_once_only_and_missing_optional_lines_do_not_crash(): void
    {
        $this->grant($this->owner,['convert quotation']);
        $customer=User::create(['name'=>'Customer','password'=>'x','type'=>'client','parent_id'=>$this->owner->id]);
        $vehicle=Vehicle::create(['parent_id'=>$this->owner->id,'client'=>$customer->id]);
        $quote=\App\Models\Quotation::create(['parent_id'=>$this->owner->id,'client_id'=>$customer->id,'vehicle_id'=>$vehicle->id,'quotation_id'=>1,'convert_service'=>0]);
        $data=['assign'=>$this->owner->id,'status'=>'scheduled','service_date'=>'2026-10-16','service_time'=>'09:00','due_date'=>'2026-10-16','due_time'=>'10:00'];
        $url=route('quotation.convertservice',$quote->id);
        $this->post($url,$data)->assertSessionHasNoErrors();
        $this->post($url,$data)->assertStatus(409);$this->assertSame(1,Service::count());
    }
    public function test_verification_resend_requires_password_checked_session_and_rotates_legacy_token(): void
    {
        auth()->logout();DB::table('users')->where('id',$this->owner->id)->update(['email_verified_at'=>null,'email_verification_token'=>sha1($this->owner->email)]);
        $this->post(route('verification.account.resend'))->assertForbidden();
        $this->withSession(['pending_verification_user'=>$this->owner->id])->post(route('verification.account.resend'))->assertRedirect();
        $token=$this->owner->fresh()->email_verification_token;$this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D',$token);
        $this->post(route('verification.account.resend'))->assertRedirect();$this->assertNotSame($token,$this->owner->fresh()->email_verification_token);
        $this->get(route('email-verification',$token))->assertNotFound();
    }
    public function test_password_reset_revokes_previous_account_sessions(): void
    {
        auth()->logout();$token=\Illuminate\Support\Facades\Password::createToken($this->owner);
        $this->post(route('password.update'),['token'=>$token,'email'=>$this->owner->email,'password'=>'ChangedPassword123!','password_confirmation'=>'ChangedPassword123!'])->assertRedirect(route('login'));
        $this->assertSame(1,(int)$this->owner->fresh()->session_epoch);
        $this->actingAs($this->owner->fresh())->withSession(['account_epoch'=>0])->get(route('setting.index'))->assertRedirect(route('login'));
    }
    public function test_preview_with_ten_thousand_other_products_reads_only_relevant_stock(): void
    {
        $this->grant($this->owner,['edit invoice']);config(['invoice_changes.require_preview'=>true]);
        for($chunk=0;$chunk<20;$chunk++){
            $rows=[];for($i=0;$i<500;$i++)$rows[]=['parent_id'=>$this->owner->id,'title'=>'Unrelated-'.$chunk.'-'.$i,'quantity'=>2];DB::table('items')->insert($rows);
        }
        $item=app(\App\Services\InventoryAccounting::class)->savePurchase($this->owner->id,['title'=>'Oil','quantity'=>10,'purchase_price'=>10,'sales_price'=>50]);
        $customer=User::create(['name'=>'Buyer','password'=>'x','type'=>'client','parent_id'=>$this->owner->id]);
        $invoice=Invoice::create(['parent_id'=>$this->owner->id,'client'=>$customer->id,'invoice_id'=>1,'invoice_date'=>'2026-10-16']);$invoice->invoice_kind='product';$invoice->save();
        $line=app(\App\Services\InventoryAccounting::class)->add($invoice,['item'=>$item->id,'quantity'=>2]);$invoice->refresh();
        DB::enableQueryLog();
        $response=$this->postJson(route('invoice.preview',[$invoice->id,'update']),['invoice_revision'=>$invoice->revision,'invoice_date'=>'2026-10-16','client'=>$customer->id,'item'=>[$item->id],'item_id'=>[$line->id],'quantity'=>[1]])->assertOk();
        $this->assertEquals(1,$response->json('stocks.0.delta'));$this->assertSame(8,(int)$item->fresh()->quantity);
        $stockReads=collect(DB::getQueryLog())->filter(fn($q)=>str_contains($q['query'],'"inventory_key", "title", "quantity"'));
        $this->assertCount(2,$stockReads);foreach($stockReads as $query)$this->assertStringContainsString('"id" in (',$query['query']);
        DB::disableQueryLog();
    }
    public function test_invoice_ajax_requires_permissions_scopes_service_and_hides_purchase_cost(): void
    {
        $job=Service::create(['parent_id'=>$this->other->id,'service_id'=>1]);
        $this->get(route('get.service.type',$job->id))->assertForbidden();
        $this->grant($this->owner,['create invoice']);
        $this->get(route('get.service.type',$job->id))->assertNotFound();
        $item=\App\Models\Item::create(['parent_id'=>$this->owner->id,'title'=>'Oil','quantity'=>10,'purchase_price'=>31.45,'sales_price'=>50]);
        $response=$this->post(route('invoice.item'),['item_id'=>$item->id])->assertOk();
        $this->assertStringNotContainsString('purchase_price',$response->getContent());$this->assertStringContainsString('sales_price',$response->getContent());
    }
    public function test_service_line_mass_assignment_preserves_selected_tax(): void
    {
        $job=Service::create(['parent_id'=>$this->owner->id,'service_id'=>1]);
        $line=\App\Models\ServiceItem::create(['parent_id'=>$this->owner->id,'service_id'=>$job->id,'type_id'=>0,'rate'=>100,'tax'=>'123']);
        $this->assertSame('123',$line->fresh()->tax);
    }
    public function test_registration_uses_random_verification_token_and_does_not_auto_verify(): void
    {
        Role::firstOrCreate(['name'=>'owner','parent_id'=>1,'guard_name'=>'web']);
        foreach(['register_page'=>'on','owner_email_verification'=>'on','google_recaptcha'=>'off','EMAIL_ENABLED'=>'0'] as $key=>$value)
            DB::table('settings')->updateOrInsert(['parent_id'=>1,'name'=>$key],['value'=>$value]);
        auth()->logout();
        $this->post('/register',['name'=>'New Shop','email'=>'new-shop@audit.test','password'=>'Password123!','password_confirmation'=>'Password123!'])->assertRedirect(route('login'));
        $user=User::where('email','new-shop@audit.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);$this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D',$user->email_verification_token);
        $this->assertNotSame(sha1($user->email),$user->email_verification_token);$this->assertGuest();
        $this->assertGreaterThan(0,Role::where('parent_id',$user->id)->count());
    }
    public function test_large_purchase_is_rejected_without_stock_expense_or_movement(): void
    {
        $before=\App\Models\Item::count();$expenses=\App\Models\Expense::count();$movements=DB::table('stock_movements')->count();
        try{app(\App\Services\InventoryAccounting::class)->savePurchase($this->owner->id,['title'=>'Too large','quantity'=>100000000,'purchase_price'=>9999999.99,'sales_price'=>9999999.99]);$this->fail('Excessive amount accepted');}
        catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('amount',$e->errors());}
        $this->assertSame($before,\App\Models\Item::count());$this->assertSame($expenses,\App\Models\Expense::count());$this->assertSame($movements,DB::table('stock_movements')->count());
    }
    public function test_money_multiplication_is_cent_exact_and_has_a_controlled_limit(): void
    {
        $this->assertSame('9999999990.00',\App\Services\FinancialAmount::multiply('9999999.99',1000));
        $this->assertSame('0.30',\App\Services\FinancialAmount::multiply('0.10',3));
        $this->assertSame('0.00',\App\Services\FinancialAmount::multiply('9999999.99',0));
        $this->expectException(\Illuminate\Validation\ValidationException::class);\App\Services\FinancialAmount::multiply('9999999.99',1001);
    }
    public function test_all_email_rules_reject_header_injection_including_nested_customer_fields(): void
    {
        foreach(["victim@example.test\r\nBcc: attacker@example.test", "\"victim\r\n\"@example.test", "victim\x00@example.test"] as $email){
            $v=\Validator::make(['billing'=>['email'=>$email]],['billing.email'=>'required|email']);
            $this->assertTrue($v->fails());$this->assertArrayHasKey('billing.email',$v->errors()->toArray());
        }
        $this->assertTrue(\Validator::make(['email'=>'real@example.test'],['email'=>'required|email'])->passes());
    }
    public function test_package_payment_upload_requires_actual_file_and_owner_actor(): void
    {
        $plan=Subscription::firstOrFail();$url=route('subscription.bank.transfer',encrypt($plan->id));
        $this->post($url,['payment_receipt'=>'not-a-file'])->assertSessionHas('error');
        $this->post(route('subscription.bank.transfer','invalid-token'),['payment_receipt'=>'not-a-file'])->assertNotFound();
        $staff=User::create(['name'=>'Staff','email'=>'payment-staff@audit.test','password'=>'x','type'=>'employee','parent_id'=>$this->owner->id,'is_active'=>1]);
        $this->actingAs($staff)->post($url,['payment_receipt'=>'not-a-file'])->assertForbidden();
        $this->assertSame(0,PackageTransaction::count());
    }
    public function test_production_rejects_forged_host_and_does_not_trust_arbitrary_subdomains(): void
    {
        auth()->logout();config(['app.url'=>'https://sanayirandevu.com']);$this->app['env']='production';
        try{
            $this->get('https://attacker.example/login')->assertStatus(400);
            $this->get('https://unapproved.sanayirandevu.com/login')->assertStatus(400);
            $this->get('https://sanayirandevu.com/login')->assertOk();
            $this->get('https://www.sanayirandevu.com/login')->assertOk();
        }finally{\Symfony\Component\HttpFoundation\Request::setTrustedHosts([]);$this->app['env']='testing';}
    }
    public function test_https_app_url_defaults_session_cookies_to_secure(): void
    {
        $oldEnv=$_ENV['APP_URL']??null;$oldServer=$_SERVER['APP_URL']??null;
        try{$_ENV['APP_URL']='https://sanayirandevu.com';$_SERVER['APP_URL']='https://sanayirandevu.com';
            $session=require config_path('session.php');$this->assertTrue($session['secure']);
        }finally{if($oldEnv===null)unset($_ENV['APP_URL']);else $_ENV['APP_URL']=$oldEnv;if($oldServer===null)unset($_SERVER['APP_URL']);else $_SERVER['APP_URL']=$oldServer;}
    }
    public function test_framework_rehash_updates_password_without_overwriting_account_fields(): void
    {
        $provider=auth()->getProvider();$stale=$this->owner->fresh();
        DB::table('users')->where('id',$stale->id)->update(['name'=>'Updated shop']);
        $oldHash=$stale->password;
        $provider->rehashPasswordIfRequired($stale,['password'=>'Password123!'],true);
        $fresh=$this->owner->fresh();
        $this->assertNotSame($oldHash,$fresh->password);
        $this->assertTrue(Hash::check('Password123!',$fresh->password));
        $this->assertSame('Updated shop',$fresh->name);
    }
    public function test_framework_rehash_cannot_undo_password_reset_or_touch_departed_account(): void
    {
        $provider=auth()->getProvider();$stale=$this->owner->fresh();$resetHash=Hash::make('NewPassword456!');
        DB::table('users')->where('id',$stale->id)->update(['password'=>$resetHash,'session_epoch'=>1]);
        $provider->rehashPasswordIfRequired($stale,['password'=>'Password123!'],true);
        $this->assertSame($resetHash,$this->owner->fresh()->password);
        $this->assertSame(1,(int)$this->owner->fresh()->session_epoch);
        $inactive=$this->owner->fresh();
        DB::table('users')->where('id',$inactive->id)->update(['is_active'=>0]);
        $provider->rehashPasswordIfRequired($inactive,['password'=>'NewPassword456!'],true);
        $this->assertSame($resetHash,$this->owner->fresh()->password);
        $this->assertSame(0,(int)$this->owner->fresh()->is_active);
    }
    public function test_form_builder_preserves_csrf_method_binding_and_escaping(): void
    {
        $html=(string)\Form::model($this->owner,['url'=>'/audit-form','method'=>'PATCH']);
        $html.=(string)\Form::text('name',null);
        $html.=(string)\Form::text('notes','"><script>alert(1)</script>');
        $html.=(string)\Form::close();
        $this->assertStringContainsString('name="_token"',$html);
        $this->assertStringContainsString('name="_method"',$html);
        $this->assertStringContainsString('value="PATCH"',$html);
        $this->assertStringContainsString('value="Shop"',$html);
        $this->assertStringNotContainsString('<script>',$html);
        $this->assertStringContainsString('&lt;script&gt;',$html);
    }
    public function test_framework_url_query_api_is_available_with_branch_url_generator(): void
    {
        $this->assertInstanceOf(\App\Routing\BranchUrlGenerator::class,app('url'));
        $url=app('url')->query('/audit?existing=1',['filter'=>'a b']);
        parse_str(parse_url($url,PHP_URL_QUERY),$query);
        $this->assertSame(['existing'=>'1','filter'=>'a b'],$query);
    }
    public function test_sanctum_storage_upgrade_keeps_existing_tokens_on_repeat_and_rollback(): void
    {
        $migration=require database_path('migrations/2026_10_17_000001_preserve_sanctum_token_storage.php');
        DB::table('personal_access_tokens')->insert(['tokenable_type'=>User::class,'tokenable_id'=>$this->owner->id,
            'name'=>'Existing token','token'=>hash('sha256','test-token'),'abilities'=>'["*"]']);
        $migration->up();$migration->up();$migration->down();
        $this->assertSame(1,DB::table('personal_access_tokens')->count());
        $this->assertSame(hash('sha256','test-token'),DB::table('personal_access_tokens')->value('token'));
        $this->assertTrue(Schema::hasColumn('personal_access_tokens','expires_at'));
    }
    public function test_carbon_upgrade_keeps_monthly_report_labels_in_istanbul_time(): void
    {
        $report=app(\App\Http\Controllers\ReportController::class)->incomeByMonth(2026);
        $this->assertCount(12,$report['label']);
        $this->assertSame('Oca 2026',$report['label'][0]);
        $this->assertSame('Ara 2026',$report['label'][11]);
    }

}
