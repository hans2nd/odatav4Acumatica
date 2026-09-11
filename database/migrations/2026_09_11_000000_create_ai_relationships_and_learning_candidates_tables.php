<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_metadata_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $table->string('source_table', 150);
            $table->string('target_table', 150);
            $table->string('source_key', 150);
            $table->string('target_key', 150);
            $table->string('relationship_type', 30)->default('reference');
            $table->string('cardinality', 20)->nullable();
            $table->boolean('allowed_for_chatedp')->default(false);
            $table->json('business_context')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['connection_id', 'source_table', 'target_table', 'source_key', 'target_key'], 'ai_rel_unique');
        });

        Schema::create('ai_learning_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 80)->nullable();
            $table->string('term', 150);
            $table->string('term_type', 30);
            $table->json('candidate_mapping');
            $table->decimal('confidence', 5, 4)->default(0);
            $table->foreignId('source_conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->foreignId('source_message_id')->nullable()->constrained('ai_messages')->nullOnDelete();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['connection_id', 'term', 'term_type'], 'ai_learning_candidate_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_learning_candidates');
        Schema::dropIfExists('ai_metadata_relationships');
    }
};
