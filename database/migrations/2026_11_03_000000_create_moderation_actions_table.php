<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_report_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('related_action_id')->nullable()->constrained('moderation_actions')->nullOnDelete();
            $table->string('action_type', 64);
            $table->text('resident_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'action_type', 'expires_at'], 'moderation_active_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_actions');
    }
};
