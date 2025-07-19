<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('packages', function (Blueprint $table) {
    $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('speed_limit');  // Contoh: '10Mbps/5Mbps'
            $table->integer('download_speed')->nullable(); // Dalam kbps (10000 = 10Mbps)
            $table->integer('upload_speed')->nullable();   // Dalam kbps (5000 = 5Mbps)
            $table->integer('duration_days');
            $table->decimal('price', 12, 2); // Diubah ke 12 digit untuk nilai besar
            $table->text('description')->nullable();
            $table->json('features')->nullable(); // Fitur dalam format JSON
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0); // Untuk pengurutan tampilan
            $table->softDeletes(); // Tambahkan soft delete
            $table->timestamps();
            
            // Index tambahan
            $table->index('is_active');
            $table->index('price');
        });
    }

    public function down()
    {
        Schema::dropIfExists('packages');
    }
};