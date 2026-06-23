<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_type_id')->constrained()->cascadeOnDelete();
            $table->string('field');
            $table->string('type');
            $table->string('display_name');
            $table->boolean('required')->default(false);
            $table->boolean('browse')->default(true);
            $table->boolean('read')->default(true);
            $table->boolean('edit')->default(true);
            $table->boolean('add')->default(true);
            $table->boolean('delete')->default(true);
            $table->text('details')->nullable();
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_rows');
    }
};
