<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_flow_trackers', function (Blueprint $table) {
            $table->id();

            $table->uuid('run_id');
            $table->string('flow', 191);
            $table->string('step', 191)->nullable();
            $table->string('task', 191)->nullable();
            $table->string('status'); // pending, running, finished, errpr
            $table->text('message')->nullable();
            $table->json('meta');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->unique(['run_id', 'flow', 'step', 'task']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_flow_trackers');
    }
};
