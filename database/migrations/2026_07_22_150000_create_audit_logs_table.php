<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail des actions système effectuées par les utilisateurs
     * (kill process, restart service, gestion des comptes, etc.).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100)->index();          // ex: process.kill, service.restart
            $table->string('target')->nullable();            // ex: PID 1234, nginx
            $table->json('details')->nullable();             // payload contextuel (params, résultat)
            $table->string('status', 20)->default('success')->index(); // success | failed | denied
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
