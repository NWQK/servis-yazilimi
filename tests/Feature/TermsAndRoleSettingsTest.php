<?php

namespace Tests\Feature;

use App\Models\{User, Page};
use App\Services\{RolePermissions, TermsAndConditions};
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class TermsAndRoleSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:', 'cache.default'=>'array', 'session.driver'=>'array']);
        DB::purge('sqlite');
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn($p)=>!str_contains($p, 'version_1_7_filled')));
        $this->artisan('migrate', ['--path'=>$paths, '--realpath'=>true, '--force'=>true])->assertExitCode(0);
        $this->withoutMiddleware(\App\Http\Middleware\XSS::class);
        app()->setLocale('tr');
    }

    private function owner(): User
    {
        $owner = User::create(['name'=>'Test shop', 'email'=>'shop@example.test', 'password'=>'x', 'type'=>'owner']);
        $role = Role::create(['name'=>'owner', 'guard_name'=>'web', 'parent_id'=>1]);
        foreach (['manage role','create role','edit role','delete role','manage client','manage n8n','manage email settings','manage openai settings','edit Page'] as $name) {
            $role->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        $owner->assignRole($role);
        $this->actingAs($owner);
        DB::table('settings')->insert(['parent_id'=>$owner->id,'name'=>'pricing_feature','value'=>'off']);
        return $owner;
    }

    public function test_terms_work_without_seeded_page_and_do_not_write_on_get(): void
    {
        Page::where('slug', TermsAndConditions::SLUG)->delete();
        $this->get(route('page', TermsAndConditions::SLUG))->assertOk()
            ->assertSee('sanayi-legal.css')->assertSee('sanayirandevu-logo.svg')
            ->assertSee('iletisim@sanayirandevu.com')->assertSee('temsili belgelerdir')
            ->assertSee('pazarlama iletilerine izin verildiği anlamına gelmez')
            ->assertDontSee('[Your Company Name]')->assertDontSee('Acceptance of Terms');
        $this->assertSame(0, Page::count());
        $this->get(route('marketing.index'))->assertOk()->assertSee(route('page', TermsAndConditions::SLUG));
    }

    public function test_terms_migration_preserves_custom_text_and_publication_switch(): void
    {
        $page = Page::where('slug', TermsAndConditions::SLUG)->firstOrFail();
        $page->update(['content'=>'Acceptance of Terms [Your Company Name]', 'enabled'=>0]);
        $migration = require database_path('migrations/2026_10_11_000001_refresh_default_terms_and_conditions.php');
        $migration->up();
        $this->assertSame(TermsAndConditions::content(), $page->fresh()->content);
        $this->assertEquals(0, $page->fresh()->enabled);
        $this->get(route('page', TermsAndConditions::SLUG))->assertNotFound();
        $page->update(['content'=>'<h2>Özel koşullar</h2><p>Özel metin.</p>', 'enabled'=>1]);
        $migration->up(); $migration->up();
        $this->assertSame('<h2>Özel koşullar</h2><p>Özel metin.</p>', $page->fresh()->content);
        $this->get(route('page', TermsAndConditions::SLUG))->assertOk()->assertSee('Özel metin.');
        $this->assertSame(1, Page::where('slug', TermsAndConditions::SLUG)->count());
    }

    public function test_terms_title_edit_keeps_registration_link_stable(): void
    {
        $this->owner();
        $page = Page::where('slug', TermsAndConditions::SLUG)->firstOrFail();
        $this->put(route('pages.update', $page), ['title'=>'Yeni koşullar', 'content'=>'<p>Yeni içerik.</p>', 'enabled'=>1])->assertRedirect();
        $this->assertSame(TermsAndConditions::SLUG, $page->fresh()->slug);
        $this->get(route('page', TermsAndConditions::SLUG))->assertOk()->assertSee('Yeni içerik.');
    }

    public function test_roles_are_translated_and_retired_permissions_are_not_offered(): void
    {
        $owner = $this->owner();
        $client = Role::create(['name'=>'client', 'guard_name'=>'web', 'parent_id'=>$owner->id]);
        $employee = Role::create(['name'=>'employee', 'guard_name'=>'web', 'parent_id'=>$owner->id]);
        $this->get(route('role.index'))->assertOk()->assertSee('Personel')->assertDontSee('>Employee', false)
            ->assertViewHas('roleData', fn($roles)=>!$roles->contains('id', $client->id) && $roles->contains('id', $employee->id));
        $this->get(route('role.edit', $employee))->assertOk()->assertSee('Personel')
            ->assertViewHas('permissionList', fn($list)=>!$list->contains('name','manage n8n') && !$list->contains('name','manage email settings') && !$list->contains('name','manage openai settings') && $list->contains('name','manage client'));
        $this->assertSame('client', $client->fresh()->name);
    }

    public function test_role_submission_cannot_restore_retired_or_unowned_permissions(): void
    {
        $owner = $this->owner();
        $employee = Role::create(['name'=>'employee', 'guard_name'=>'web', 'parent_id'=>$owner->id]);
        $normal = Permission::findByName('manage client');
        $retired = Permission::findByName('manage n8n');
        $unowned = Permission::findOrCreate('delete invoice', 'web');
        $employee->givePermissionTo([$normal, $retired]);
        foreach ([$retired, $unowned] as $permission) {
            $this->post(route('role.store'), ['title'=>'Bad role', 'user_permission'=>[$permission->id]])->assertSessionHasErrors('user_permission.0');
            $this->put(route('role.update', $employee), ['name'=>'employee', 'user_permission'=>[$permission->id]])->assertSessionHasErrors('user_permission.0');
        }
        $this->put(route('role.update', $employee), ['name'=>'Renamed employee', 'user_permission'=>[$normal->id], 'editable_permissions'=>[]])->assertRedirect()->assertSessionHasNoErrors();
        $employee->refresh();
        $this->assertSame('employee', $employee->name);
        $this->assertSame([$normal->id], $employee->permissions->pluck('id')->all());
        $this->assertSame(0, Role::where('name','Bad role')->count());
    }

    public function test_foreign_roles_cannot_be_changed_and_builtin_roles_cannot_be_deleted(): void
    {
        $owner = $this->owner();
        $foreign = Role::create(['name'=>'Other shop role', 'guard_name'=>'web', 'parent_id'=>999]);
        $this->get(route('role.edit', $foreign))->assertNotFound();
        $this->put(route('role.update', $foreign), ['name'=>'Changed'])->assertNotFound();
        $this->delete(route('role.destroy', $foreign))->assertNotFound();
        $client = Role::create(['name'=>'client', 'guard_name'=>'web', 'parent_id'=>$owner->id]);
        $this->delete(route('role.destroy', $client))->assertForbidden();
    }

    public function test_red_black_theme_is_default_but_saved_preferences_win(): void
    {
        $owner = $this->owner();
        $this->assertSame('bike-and-more', settingsKeys()['accent_color']);
        $this->assertSame('bike-and-more', settings()['accent_color']);
        $this->get(route('role.index'))->assertOk()->assertSee('data-pc-preset="bike-and-more"', false);
        DB::table('settings')->insert(['parent_id'=>$owner->id,'name'=>'accent_color','value'=>'preset-2']);
        $this->assertSame('preset-2', settings()['accent_color']);
        $this->assertSame('preset-2', settingsById($owner->id)['accent_color']);
    }
    public function test_client_role_is_hidden_and_immutable_for_owner_and_staff(): void
    {
        $owner = $this->owner();
        $client = Role::create(['name'=>'client', 'guard_name'=>'web', 'parent_id'=>$owner->id]);
        $client->givePermissionTo(Permission::findByName('manage client'));
        $before = $client->permissions->pluck('id')->all();
        $staff = User::create(['name'=>'Staff', 'email'=>'staff@example.test', 'password'=>'x', 'type'=>'employee', 'parent_id'=>$owner->id]);
        $staff->assignRole($owner->roles->first());
        foreach ([$owner, $staff] as $operator) {
            $this->actingAs($operator);
            $this->get(route('role.index'))->assertOk()->assertViewHas('roleData', fn($roles)=>!$roles->contains('id',$client->id));
            $this->get(route('role.edit',$client))->assertForbidden();
            $this->put(route('role.update',$client), ['name'=>'Changed','user_permission'=>[]])->assertForbidden();
            $this->delete(route('role.destroy',$client))->assertForbidden();
        }
        $this->assertSame('client',$client->fresh()->name);
        $this->assertSame($before,$client->fresh()->permissions->pluck('id')->all());
    }

    public function test_client_role_name_cannot_be_created_or_assigned_to_custom_role(): void
    {
        $owner = $this->owner();
        $permission = Permission::findByName('manage client');
        $custom = Role::create(['name'=>'Reception', 'guard_name'=>'web', 'parent_id'=>$owner->id]);
        foreach (['client','CLIENT',' Client '] as $name) {
            $this->post(route('role.store'), ['title'=>$name,'user_permission'=>[$permission->id]])->assertSessionHasErrors('title');
            $this->put(route('role.update',$custom), ['name'=>$name,'user_permission'=>[$permission->id]])->assertSessionHasErrors('name');
        }
        $this->assertSame('Reception',$custom->fresh()->name);
        $this->assertSame(0,Role::where('parent_id',$owner->id)->whereRaw('LOWER(TRIM(name)) = ?', ['client'])->count());
        $this->post(route('role.store'), ['title'=>'Workshop','user_permission'=>[$permission->id]])->assertRedirect()->assertSessionHasNoErrors();
        $workshop = Role::where('name','Workshop')->firstOrFail();
        $this->put(route('role.update',$workshop), ['name'=>'Workshop team','user_permission'=>[$permission->id]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Workshop team',$workshop->fresh()->name);
    }

    public function test_user_role_assignment_cannot_be_used_to_assign_or_replace_client_role(): void
    {
        $owner = $this->owner();
        foreach (['create user','edit user'] as $name) $owner->roles->first()->givePermissionTo(Permission::findOrCreate($name,'web'));
        $client = Role::create(['name'=>'client','guard_name'=>'web','parent_id'=>$owner->id]);
        $custom = Role::create(['name'=>'Reception','guard_name'=>'web','parent_id'=>$owner->id]);
        $customer = User::create(['name'=>'Customer','email'=>'customer@example.test','password'=>'x','type'=>'client','parent_id'=>$owner->id]);
        $customer->assignRole($client);
        $this->post(route('users.store'),['name'=>'Forged','email'=>'forged@example.test','password'=>'password123','role'=>$client->id])->assertNotFound();
        $this->put(route('users.update',$customer),['name'=>'Changed','email'=>$customer->email,'role'=>$custom->id])->assertForbidden();
        $this->get(route('users.edit',$customer))->assertForbidden();
        $this->assertSame('client',$customer->fresh()->type);
        $this->assertTrue($customer->fresh()->hasRole($client));
        $this->assertFalse(User::where('email','forged@example.test')->exists());
    }

    public function test_global_permission_endpoints_cannot_change_client_permissions_for_a_shop(): void
    {
        $owner = $this->owner();
        $client = Role::create(['name'=>'client','guard_name'=>'web','parent_id'=>$owner->id]);
        $permission = Permission::findByName('manage client');
        $client->givePermissionTo($permission);
        $this->get(route('permission.index'))->assertForbidden();
        $this->get(route('permission.create'))->assertForbidden();
        $this->post(route('permission.store'), ['title'=>'forged permission','user_roles'=>[$client->id]])->assertForbidden();
        $this->delete(route('permission.destroy',$permission))->assertForbidden();
        $this->assertFalse(Permission::where('name','forged permission')->exists());
        $this->assertTrue($client->fresh()->hasPermissionTo($permission));
    }

}
