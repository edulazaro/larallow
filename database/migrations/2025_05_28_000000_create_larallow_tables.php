<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_type', 80)->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
   
            $table->string('actor_type', 80)->nullable();
            $table->string('scope_type', 80)->nullable();

            $table->string('handle');
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->json('translations')->nullable();
            $table->timestamps();

            $table->unique(['handle', 'actor_type', 'scope_type', 'tenant_type', 'tenant_id'], 'unique_roles');
        });

        Schema::create('actor_role', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 80);
            $table->unsignedBigInteger('actor_id');
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type', 80)->nullable();
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->timestamps();

            $table->unique(['actor_type', 'actor_id', 'role_id', 'scope_type', 'scope_id'], 'unique_actor_role');
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 160);
            $table->timestamps();

            $table->unique(['role_id', 'permission'], 'unique_role_permission');
        });

        Schema::create('actor_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 80)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('scope_type', 80)->nullable();
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('permission', 160);
            $table->timestamps();

            $table->unique([
                'actor_type',
                'actor_id',
                'scope_type',
                'scope_id',
                'permission',
            ], 'unique_actor_permission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actor_permissions');
        Schema::dropIfExists('actor_role');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');   
    }
};
