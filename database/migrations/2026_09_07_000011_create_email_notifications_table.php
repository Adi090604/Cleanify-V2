<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->date('collection_date');
            $table->string('reminder_type', 50)->default('one_day_before');
            $table->string('email_address');
            $table->string('subject');
            $table->text('message');
            $table->timestamp('scheduled_for');
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 20);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'schedule_id', 'collection_date', 'reminder_type'], 'email_reminder_once_per_collection');
            $table->index(['status', 'collection_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_notifications');
    }
};
