<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('model')->nullable();
            $table->string('icon')->nullable();
            $table->string('model_class')->nullable();
            $table->string('controller')->nullable();
            $table->string('policy')->nullable();
            $table->string('order_column')->nullable();
            $table->string('order_direction')->default('asc');
            $table->string('default_search_key')->nullable();
            $table->text('description')->nullable();
            $table->string('server_side')->nullable();
            $table->boolean('generate_permissions')->default(true);
            $table->boolean('generate_model')->default(true);
            $table->boolean('soft_delete')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_types');
    }
};
