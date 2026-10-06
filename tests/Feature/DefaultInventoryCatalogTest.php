<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\DefaultInventoryCatalog;
use Database\Seeders\DefaultInventoryCatalogSeeder;
use Illuminate\Support\Facades\{DB, Mail};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DefaultInventoryCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);
        DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
    }

    private function owner(string $email): User
    {
        return User::create(['name'=>'Servis','email'=>$email,'password'=>'x','type'=>'owner']);
    }

    public function test_targeted_seeder_is_repeatable_and_separates_businesses()
    {
        $first=$this->owner('catalog-one@example.test');
        $second=$this->owner('catalog-two@example.test');
        $admin=User::create(['name'=>'Admin','email'=>'catalog-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->seed(DefaultInventoryCatalogSeeder::class);
        $this->seed(DefaultInventoryCatalogSeeder::class);
        foreach ([$first,$second] as $owner) {
            $this->assertSame(3,DB::table('taxes')->where('parent_id',$owner->id)->count());
            $this->assertEquals([1,10,20],DB::table('taxes')->where('parent_id',$owner->id)->orderBy('rate')->pluck('rate')->all());
            $this->assertSame(12,DB::table('units')->where('parent_id',$owner->id)->count());
        }
        $this->assertSame(0,DB::table('units')->where('parent_id',$admin->id)->count());
        $this->assertSame(3,User::count());
    }

    public function test_existing_names_ids_and_custom_rates_are_preserved()
    {
        $owner=$this->owner('catalog-existing@example.test');
        $taxId=DB::table('taxes')->insertGetId(['parent_id'=>$owner->id,'title'=>'kdv (%20)','rate'=>20,'updated_at'=>'2025-01-01']);
        $customId=DB::table('taxes')->insertGetId(['parent_id'=>$owner->id,'title'=>'Özel oran','rate'=>7]);
        $kgId=DB::table('units')->insertGetId(['parent_id'=>$owner->id,'unit'=>'KG']);
        DB::table('units')->insert(['parent_id'=>$owner->id,'unit'=>'ml']);
        DB::table('units')->insert(['parent_id'=>$owner->id,'unit'=>'Özel ambalaj']);
        app(DefaultInventoryCatalog::class)->seed($owner->id);
        app(DefaultInventoryCatalog::class)->seed($owner->id);
        $this->assertSame(4,DB::table('taxes')->where('parent_id',$owner->id)->count());
        $this->assertEquals(7,DB::table('taxes')->where('id',$customId)->value('rate'));
        $this->assertSame('2025-01-01',DB::table('taxes')->where('id',$taxId)->value('updated_at'));
        $this->assertSame('KG',DB::table('units')->where('id',$kgId)->value('unit'));
        $this->assertSame(13,DB::table('units')->where('parent_id',$owner->id)->count());
        $this->assertFalse(DB::table('units')->where('parent_id',$owner->id)->whereIn('unit',['Kilogram','Mililitre'])->exists());
    }

    public function test_public_business_registration_creates_its_own_defaults()
    {
        Mail::fake();
        Role::create(['name'=>'owner','guard_name'=>'web','parent_id'=>1]);
        $this->withoutMiddleware(\App\Http\Middleware\XSS::class)->post('/register',[
            'name'=>'Yeni Servis','email'=>'catalog-registration@example.test',
            'password'=>'ExamplePassword123!','password_confirmation'=>'ExamplePassword123!',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $owner=User::where('email','catalog-registration@example.test')->firstOrFail();
        $this->assertSame(3,DB::table('taxes')->where('parent_id',$owner->id)->count());
        $this->assertSame(12,DB::table('units')->where('parent_id',$owner->id)->count());
    }
}
