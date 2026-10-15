<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up(): void {
        Schema::table('services',function(Blueprint $table){
            $table->dateTime('completed_at')->nullable();
            $table->index(['status','completed_at','parent_id'],'service_photo_expiry');
        });
        // Historical completion times were not recorded. Start their retention window at rollout.
        DB::table('services')->where('status','completed')->update(['completed_at'=>now()]);
        Schema::create('service_photo_deletions',function(Blueprint $table){
            $table->string('filename',120)->primary();
            $table->unsignedBigInteger('parent_id')->index();
            $table->dateTime('purged_at');
        });
        DB::table('settings')->updateOrInsert(['parent_id'=>0,'type'=>'platform','name'=>'photo_retention_days'],['value'=>'90']);
    }
    public function down(): void {
        Schema::dropIfExists('service_photo_deletions');
        DB::table('settings')->where('parent_id',0)->where('type','platform')->whereIn('name',['photo_retention_days','photo_cleanup_last','photo_cleanup_error'])->delete();
        Schema::table('services',function(Blueprint $table){$table->dropIndex('service_photo_expiry');$table->dropColumn('completed_at');});
    }
};
