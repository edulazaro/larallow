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
            $table->json('roleable_types')->nullable();
            $table->json('actor_types')->nullable();
            $table->string('handle');
            $table->string('name')->nullable();
            $table->json('translations')->nullable();
            $table->timestamps();

            $table->unique([
                'handle',
            ], 'unique_roles');
        });

        Schema::create('actor_role', function (Blueprint $table) {
            $table->id();
            $table->morphs('actor');
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('roleable');
            $table->timestamps();

            $table->unique(['actor_type', 'actor_id', 'role_id', 'roleable_type', 'roleable_id'], 'unique_actor_role');
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
            $table->string('permissionable_type', 80)->nullable();
            $table->unsignedBigInteger('permissionable_id')->nullable();
            $table->string('permission', 160);
            $table->timestamps();

            $table->unique([
                'actor_type',
                'actor_id',
                'permissionable_type',
                'permissionable_id',
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
