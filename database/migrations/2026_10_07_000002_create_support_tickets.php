<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_tickets', function(Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->index();
            $table->string('subject',150);
            $table->string('priority',20)->default('normal');
            $table->string('status',30)->default('waiting_support');
            $table->boolean('owner_unread')->default(false);
            $table->boolean('admin_unread')->default(true);
            $table->timestamp('last_message_at');
            $table->timestamps();
            $table->index(['owner_id','status','last_message_at']);
            $table->index(['admin_unread','last_message_at']);
        });
        Schema::create('support_ticket_messages', function(Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->unsignedBigInteger('author_id');
            $table->boolean('is_admin');
            $table->text('body');
            $table->timestamps();
            $table->index(['ticket_id','id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
