<?php
namespace Tests\Feature;
use App\Models\{User,Announcement,AnnouncementRecipient,AdminAuditLog,Vehicle,Service};
use Illuminate\Support\Facades\{DB,Gate};
use Tests\TestCase;
class AdminOperationsTest extends TestCase {
 private $admin;private $owner;private $other;
 protected function setUp():void {parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);DB::purge('sqlite');$paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));$this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);$this->admin=User::create(['name'=>'Admin','email'=>'audit-admin@example.test','password'=>'x','type'=>'super admin']);$this->owner=User::create(['name'=>'First shop','email'=>'audit-owner@example.test','password'=>'x','type'=>'owner']);$this->other=User::create(['name'=>'Other shop','email'=>'audit-other@example.test','password'=>'x','type'=>'owner']);Gate::before(fn($u)=>true);$this->actingAs($this->admin)->withoutMiddleware(\App\Http\Middleware\XSS::class);}
 public function test_selected_notification_is_private_and_only_owner_open_marks_read(){
  $this->post(route('announcements.store'),['title'=>'Bakım duyurusu','body'=>'<script>alert(1)</script>','audience'=>'selected','owner_ids'=>[$this->owner->id]])->assertRedirect();
  $a=Announcement::firstOrFail();$this->assertSame(1,$a->recipients()->count());
  $this->get(route('announcements.show',$a->id))->assertOk()->assertSee('&lt;script&gt;',false);
  $this->assertNull($a->recipients()->first()->read_at);
  $this->actingAs($this->other)->get(route('announcements.show',$a->id))->assertNotFound();
  $this->getJson('/appointments/notifications')->assertJsonPath('count',0);
  $this->actingAs($this->owner)->getJson('/appointments/notifications')->assertJsonPath('count',1)->assertJsonPath('items.0.type','announcement');
  $this->get(route('announcements.show',$a->id))->assertOk();$read=$a->recipients()->first()->read_at;$this->assertNotNull($read);
  $this->getJson('/appointments/notifications')->assertJsonPath('count',0);
  $this->get(route('announcements.index'))->assertOk()->assertSee('Bakım duyurusu')->assertDontSee('Panel bildirimi gönder');
  $this->get(route('announcements.show',$a->id))->assertOk();$this->assertEquals($read,$a->recipients()->first()->read_at);
  $this->assertSame('announcement.created',AdminAuditLog::first()->action);
 }
 public function test_broadcast_withdraw_and_validation_are_admin_only(){
  $this->post(route('announcements.store'),['title'=>'Genel duyuru','body'=>'Test','audience'=>'all'])->assertRedirect();$a=Announcement::firstOrFail();$this->assertSame(2,$a->recipients()->count());
  $this->actingAs($this->owner)->post(route('announcements.withdraw',$a->id))->assertForbidden();
  $this->post(route('announcements.store'),['title'=>'No','body'=>'No','audience'=>'all'])->assertForbidden();
  $this->get(route('admin-audit.index'))->assertForbidden();$this->get(route('business-usage.index'))->assertForbidden();
  $this->actingAs($this->admin)->post(route('announcements.withdraw',$a->id))->assertSessionHas('success');
  $this->assertFalse($a->fresh()->published);$this->actingAs($this->owner)->getJson('/appointments/notifications')->assertJsonPath('count',0);
  $this->get(route('announcements.show',$a->id))->assertNotFound();
  $client=User::create(['name'=>'Client','email'=>'audit-client@example.test','password'=>'x','type'=>'client','parent_id'=>$this->owner->id]);
  $this->actingAs($client)->get(route('announcements.index'))->assertForbidden();
  $this->actingAs($this->admin)->post(route('announcements.store'),['title'=>'Bad','body'=>'Test','audience'=>'selected','owner_ids'=>[$client->id]])->assertSessionHasErrors('owner_ids.0');
  $this->assertSame(1,Announcement::count());$this->assertSame(2,AdminAuditLog::count());
 }
 public function test_audit_filters_redaction_and_failed_transactions(){
  \App\Services\AdminAudit::record('communication.smtp',$this->owner->id,['SERVER_PASSWORD'=>'not-visible'],['api_key'=>'not-visible','nested'=>['auth_token'=>'not-visible'],'port'=>465]);
  $this->assertStringNotContainsString('not-visible',AdminAuditLog::first()->toJson());
  $this->get(route('admin-audit.index',['owner'=>$this->owner->id]))->assertOk()->assertSee('SMTP ayarları değiştirildi')->assertSee('First shop');
  $this->get(route('admin-audit.index',['owner'=>$this->other->id]))->assertOk()->assertSee('Henüz kayıt yok');
  DB::beginTransaction();\App\Services\AdminAudit::record('announcement.created');DB::rollBack();$this->assertSame(1,AdminAuditLog::count());
  $this->actingAs($this->owner);\App\Services\AdminAudit::record('announcement.created');$this->assertSame(1,AdminAuditLog::count());
 }
 public function test_usage_counts_are_scoped_and_monthly(){
  Vehicle::create(['parent_id'=>$this->owner->id]);Vehicle::create(['parent_id'=>$this->other->id]);
  Service::create(['parent_id'=>$this->owner->id]);$old=Service::create(['parent_id'=>$this->owner->id]);$old->timestamps=false;$old->created_at=now()->subMonths(2);$old->save();Service::create(['parent_id'=>$this->other->id]);
  $this->get(route('business-usage.index',['search'=>'First shop']))->assertOk()->assertSee('First shop')->assertDontSee('Other shop')->assertViewHas('owners',fn($owners)=>$owners->count()===1 && (int)$owners->first()->vehicle_count===1 && (int)$owners->first()->month_services===1);
  $this->get(route('business-detail.show',$this->owner->id))->assertOk()->assertSee('İşletmeye panel bildirimi gönder')->assertSee('Bu ayın kullanım özeti');
 }
}
