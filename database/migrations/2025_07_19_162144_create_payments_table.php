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
        Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ppp_user_id')->constrained()->cascadeOnDelete();
    $table->decimal('amount', 12, 2); // 12 digit total, 2 decimal
    $table->string('payment_method'); // midtrans, transfer, tunai, dll
    $table->string('reference')->nullable(); // nomor referensi/kode unik
    $table->string('order_id')->unique(); // untuk integrasi Midtrans
    $table->string('status')->default('pending'); // pending, success, failed
    $table->string('snap_token')->nullable(); // token dari Midtrans
    $table->dateTime('payment_date'); // tanggal pembayaran
    $table->text('description')->nullable(); // deskripsi pembayaran
    $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
