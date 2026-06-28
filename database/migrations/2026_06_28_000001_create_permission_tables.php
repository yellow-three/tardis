<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tardis_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('group')->nullable();
            $table->timestamps();
        });

        Schema::create('tardis_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('tardis_permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('tardis_permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('tardis_roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('tardis_role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('tardis_roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tardis_role_user');
        Schema::dropIfExists('tardis_permission_role');
        Schema::dropIfExists('tardis_roles');
        Schema::dropIfExists('tardis_permissions');
    }
};
