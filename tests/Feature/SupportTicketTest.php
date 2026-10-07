<?php
namespace Tests\Feature;
use App\Models\{User,SupportTicket,SupportTicketMessage};
use Illuminate\Support\Facades\{DB,Gate,Mail,Cache};
use Tests\TestCase;
class SupportTicketTest extends TestCase {
    private $owner;private $admin;
    protected function setUp():void {
        parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        $this->owner=User::create(['name'=>'Demo İşletme','email'=>'support-owner@example.test','password'=>'x','type'=>'owner']);
        $this->admin=User::create(['name'=>'Admin','email'=>'support-admin@example.test','password'=>'x','type'=>'super admin']);
        Gate::before(fn($u)=>in_array($u->type,['owner','super admin'])?true:null);
        $this->withoutMiddleware(\App\Http\Middleware\XSS::class);$this->actingAs($this->owner);Mail::fake();
    }
    private function ticket() {
        $this->actingAs($this->owner)->post(route('support.store'),['subject'=>'Logo görünmüyor','priority'=>'high','body'=>'Fatura ekranında logo görünmüyor.'])->assertRedirect();
        return SupportTicket::latest('id')->firstOrFail();
    }
    public function test_owner_creates_ticket_with_first_message_and_admin_can_find_it() {
        $this->get(route('support.create'))->assertOk()->assertSee('Yeni destek bileti');
        $this->post(route('support.store'),['subject'=>'Logo görünmüyor','priority'=>'high','body'=>'Test mesajı','owner_id'=>$this->admin->id,'status'=>'closed','admin_unread'=>false])->assertRedirect();
        $t=SupportTicket::firstOrFail();$this->assertEquals($this->owner->id,$t->owner_id);$this->assertSame('waiting_support',$t->status);$this->assertTrue($t->admin_unread);
        $this->assertSame(1,$t->messages()->count());$this->assertFalse($t->messages()->first()->is_admin);
        $this->get(route('support.index'))->assertOk()->assertSee('Logo görünmüyor');
        $this->actingAs($this->admin)->get(route('support.index',['search'=>'Demo İşletme','priority'=>'high','unread'=>1]))->assertOk()->assertSee('Logo görünmüyor')->assertSee('Destek biletleri (1)');
        Mail::assertNothingSent();
    }
    public function test_reply_and_read_flags_work_in_both_directions() {
        $t=$this->ticket();$this->actingAs($this->admin)->get(route('support.show',$t->id))->assertOk();$this->assertFalse($t->fresh()->admin_unread);
        $this->post(route('support.reply',$t->id),['body'=>'Logo ayarını kontrol ettim.','author_id'=>$this->owner->id])->assertRedirect();
        $this->assertTrue($t->fresh()->owner_unread);$this->assertSame('waiting_owner',$t->fresh()->status);$this->assertEquals($this->admin->id,$t->messages()->latest('id')->first()->author_id);
        $this->actingAs($this->owner)->get(route('support.index',['unread'=>1]))->assertSee('Logo görünmüyor')->assertSee('Destek biletleri (1)');
        $this->get(route('support.show',$t->id))->assertOk()->assertSee('Logo ayarını kontrol ettim.');$this->assertFalse($t->fresh()->owner_unread);
        $this->post(route('support.reply',$t->id),['body'=>'Teşekkürler.','is_admin'=>true])->assertRedirect();
        $this->assertSame('waiting_support',$t->fresh()->status);$this->assertTrue($t->fresh()->admin_unread);$this->assertFalse($t->messages()->latest('id')->first()->is_admin);
    }
    public function test_bell_notifications_follow_ticket_replies_and_reading_without_leaking_to_other_owners() {
        $t=$this->ticket();
        $this->getJson(route('appointments.notifications'))->assertOk()->assertJsonPath('count',0);
        $other=User::create(['name'=>'Other','email'=>'bell-other@example.test','password'=>'x','type'=>'owner']);
        $this->actingAs($other)->getJson(route('appointments.notifications'))->assertOk()->assertJsonCount(0,'items');
        $this->actingAs($this->admin)->get(route('support.index'))->assertOk()->assertSee('id="appointment-notifications-toggle"',false);
        $this->getJson(route('appointments.notifications'))->assertOk()->assertJsonPath('count',1)
            ->assertJsonPath('items.0.type','support')->assertJsonPath('items.0.url',route('support.show',$t->id));
        $this->assertTrue($t->fresh()->admin_unread);
        $this->get(route('support.show',$t->id))->assertOk();
        $this->getJson(route('appointments.notifications'))->assertJsonPath('count',0);
        $this->post(route('support.reply',$t->id),['body'=>'Destekten yanıt'])->assertRedirect();
        $this->actingAs($this->owner)->getJson(route('appointments.notifications'))->assertOk()->assertJsonPath('count',1)
            ->assertJsonPath('items.0.type','support')->assertJsonPath('items.0.url',route('support.show',$t->id));
        $this->assertTrue($t->fresh()->owner_unread);
        $this->actingAs($other)->getJson(route('appointments.notifications'))->assertJsonPath('count',0);
        $this->actingAs($this->owner)->get(route('support.show',$t->id))->assertOk();
        $this->getJson(route('appointments.notifications'))->assertJsonPath('count',0);
        $this->post(route('support.reply',$t->id),['body'=>'Esnaf yanıtı'])->assertRedirect();
        $this->actingAs($this->admin)->getJson(route('appointments.notifications'))->assertJsonPath('count',1);
        $client=User::create(['name'=>'Client','email'=>'bell-client@example.test','password'=>'x','type'=>'client']);
        $this->actingAs($client)->getJson(route('appointments.notifications'))->assertForbidden();
    }
    public function test_other_owner_cannot_list_read_reply_or_change_ticket() {
        $t=$this->ticket();$other=User::create(['name'=>'Other','email'=>'support-other@example.test','password'=>'x','type'=>'owner']);$this->actingAs($other);
        $this->get(route('support.index'))->assertOk()->assertDontSee('Logo görünmüyor');
        $this->get(route('support.show',$t->id))->assertNotFound();
        $this->post(route('support.reply',$t->id),['body'=>'Foreign reply'])->assertNotFound();
        $this->post(route('support.status',$t->id),['status'=>'closed'])->assertNotFound();
        $this->assertSame(1,$t->messages()->count());$this->assertSame('waiting_support',$t->fresh()->status);$this->assertTrue($t->fresh()->admin_unread);
    }
    public function test_clients_employees_and_guests_have_no_support_access() {
        $t=$this->ticket();
        foreach(['client','employee'] as $type) {
            $u=User::create(['name'=>$type,'email'=>$type.'-support@example.test','password'=>'x','type'=>$type,'parent_id'=>$this->owner->id]);$this->actingAs($u);
            $this->get(route('support.index'))->assertForbidden();$this->get(route('support.show',$t->id))->assertForbidden();
            $this->post(route('support.store'),['subject'=>'Unauthorized','priority'=>'normal','body'=>'Test'])->assertForbidden();
        }
        auth()->logout();$this->get(route('support.index'))->assertRedirect(route('login'));
        $this->actingAs($this->admin)->get(route('support.create'))->assertForbidden();
    }
    public function test_closing_reopening_and_admin_priority_change_are_logged_in_conversation() {
        $t=$this->ticket();$this->post(route('support.status',$t->id),['status'=>'closed','priority'=>'low'])->assertRedirect();
        $this->assertSame('closed',$t->fresh()->status);$this->assertSame('high',$t->fresh()->priority);
        $this->post(route('support.reply',$t->id),['body'=>'Should not send'])->assertSessionHasErrors('body');
        $this->post(route('support.status',$t->id),['status'=>'waiting_support'])->assertRedirect();
        $this->actingAs($this->admin)->post(route('support.status',$t->id),['status'=>'open','priority'=>'normal'])->assertRedirect();
        $this->assertSame('open',$t->fresh()->status);$this->assertSame('normal',$t->fresh()->priority);$this->assertTrue($t->fresh()->owner_unread);
        $this->get(route('support.show',$t->id))->assertSee('İnceleniyor');$this->assertSame(4,$t->messages()->count());
    }
    public function test_validation_xss_escaping_pagination_and_rate_limits() {
        $this->post(route('support.store'),['subject'=>'','priority'=>'invalid','body'=>''])->assertSessionHasErrors(['subject','priority','body']);
        $this->assertSame(0,SupportTicket::count());
        $this->post(route('support.store'),['subject'=>'<script>alert(1)</script>','priority'=>'normal','body'=>'<img src=x onerror=alert(2)>'])->assertRedirect();
        $t=SupportTicket::firstOrFail();$this->get(route('support.show',$t->id))->assertOk()->assertDontSee('<script>alert(1)</script>',false)->assertDontSee('<img src=x onerror=alert(2)>',false);
        $this->post(route('support.reply',$t->id),['body'=>str_repeat('x',10001)])->assertSessionHasErrors('body');
        for($i=0;$i<45;$i++) $t->messages()->create(['author_id'=>$this->owner->id,'is_admin'=>false,'body'=>'Message '.$i]);
        $this->get(route('support.show',$t->id))->assertOk()->assertSee('Message 44')->assertDontSee('Message 0');
        $this->get(route('support.show',['id'=>$t->id,'page'=>2]))->assertOk()->assertSee('Message 0');
        Cache::flush();
        for($i=0;$i<5;$i++) $this->post(route('support.store'),['subject'=>'Ticket '.$i,'priority'=>'normal','body'=>'Test'])->assertRedirect();
        $this->post(route('support.store'),['subject'=>'Over limit','priority'=>'normal','body'=>'Test'])->assertStatus(429);
    }
}
