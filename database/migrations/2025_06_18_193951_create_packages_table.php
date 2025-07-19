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
    Schema::create('packages', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
        $table->string('code')->unique();
        $table->string('speed_limit')->nullable();
        $table->integer('download_speed')->default(0);
        $table->integer('upload_speed')->default(0);
        $table->integer('duration_days')->default(30);
        $table->decimal('price', 10, 2)->default(0.00);
        $table->text('description')->nullable();
        $table->json('features')->nullable();
        $table->boolean('is_active')->default(true);
        $table->integer('sort_order')->default(0);
        $table->string('mikrotik_profile_name')->nullable();
        $table->softDeletes();
        $table->timestamps();
    }); // <-- THIS LINE WAS LIKELY MISSING OR INCORRECT
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};