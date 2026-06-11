<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('chatbot_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_conversation_id')
                ->constrained('chatbot_conversations')
                ->cascadeOnDelete();
            $table->enum('role', ['user', 'bot']);
            $table->text('text')->nullable();
            $table->json('reply')->nullable();
            $table->timestamps();

            $table->index(['chatbot_conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_messages');
        Schema::dropIfExists('chatbot_conversations');
    }
};