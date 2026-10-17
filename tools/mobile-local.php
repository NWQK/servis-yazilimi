<?php
/** Isolated synthetic API harness. Never loads the repository's tracked .env. */
$base=dirname(__DIR__);
$state=$base.'/storage/app/mobile-local';
if(!is_dir($state))mkdir($state,0700,true);
$keyFile=$state.'/app-key';
if(!file_exists($keyFile))file_put_contents($keyFile,'base64:'.base64_encode(random_bytes(32)));
$database=$state.'/synthetic.sqlite';
foreach(['APP_ENV'=>'local','APP_DEBUG'=>'false','APP_KEY'=>trim(file_get_contents($keyFile)),
    'APP_URL'=>'http://127.0.0.1:8787','DB_CONNECTION'=>'sqlite','DB_DATABASE'=>$database,
    'DATABASE_URL'=>'','CACHE_DRIVER'=>'file','SESSION_DRIVER'=>'array','MAIL_MAILER'=>'array',
    'QUEUE_CONNECTION'=>'sync','COMMUNICATIONS_ASYNC'=>'false','BCRYPT_ROUNDS'=>'4'] as $k=>$v) {
    putenv($k.'='.$v);$_ENV[$k]=$_SERVER[$k]=$v;
}
require $base.'/vendor/autoload.php';
$app=require $base.'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.mobile-local-unused');
if(PHP_SAPI==='cli') {
    if(($argv[1]??'')!=='seed'){fwrite(STDERR,"Usage: php -d extension=intl tools/mobile-local.php seed\n");exit(1);}
    if(file_exists($database)){fwrite(STDERR,"Synthetic database already exists; preserved without reset.\n");exit(1);}
    touch($database);
    $kernel=$app->make(Illuminate\Contracts\Console\Kernel::class);$kernel->bootstrap();
    Illuminate\Support\Facades\Mail::fake();Illuminate\Support\Facades\Http::preventStrayRequests();
    $paths=array_values(array_filter(glob($base.'/database/migrations/*.php'),fn($p)=>!str_contains($p,'version_1_7_filled')));
    $exit=$kernel->call('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true]);
    if($exit!==0){echo $kernel->output();exit($exit);}
    $owner=App\Models\User::create(['name'=>'Örnek Sanayi Servisi','email'=>'esnaf@example.test','password'=>Illuminate\Support\Facades\Hash::make('Yerel-Test-2026!'),'type'=>'owner','is_active'=>1]);
    $owner->email_verified_at=now();$owner->save();
    foreach(['manage client','show client','create client','manage vehicle','show vehicle','manage service','show service'] as $permission){
        Spatie\Permission\Models\Permission::firstOrCreate(['name'=>$permission,'guard_name'=>'web']);$owner->givePermissionTo($permission);
    }
    Illuminate\Support\Facades\DB::table('settings')->insert(['parent_id'=>1,'name'=>'pricing_feature','value'=>'off']);
    for($i=1;$i<=25;$i++) {
        $customer=app(App\Services\ClientRegistration::class)->create($owner->id,['name'=>'Deneme Müşteri '.str_pad($i,2,'0',STR_PAD_LEFT),'phone_number'=>'555'.str_pad($i,7,'0',STR_PAD_LEFT),'city'=>'İstanbul','notes'=>'Tamamen sentetik yerel test kaydı.']);
        $vehicle=App\Models\Vehicle::create(['parent_id'=>$owner->id,'client'=>$customer->id,'vehicle_id'=>$i,'license_plate'=>'34 TEST '.str_pad($i,3,'0',STR_PAD_LEFT),'model'=>'Örnek araç','color'=>'Beyaz','mileage'=>10000+$i*100]);
        App\Models\Service::create(['parent_id'=>$owner->id,'vehicle'=>$vehicle->id,'client'=>$customer->id,'service_id'=>$i,'assign'=>$owner->id,'status'=>$i%2?'in_progress':'scheduled','service_date'=>now()->toDateString(),'notes'=>'Sentetik periyodik bakım kaydı.']);
    }
    echo "Synthetic SQLite API seeded. Account: esnaf@example.test / Yerel-Test-2026!\n";exit;
}
if(!file_exists($database)){http_response_code(503);header('Content-Type: application/json');echo '{"message":"Önce sentetik test verisini hazırlayın."}';exit;}
if(!str_starts_with(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/api/v1/')){http_response_code(404);exit;}
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
$response=$kernel->handle($request=Illuminate\Http\Request::capture());$response->send();$kernel->terminate($request,$response);
