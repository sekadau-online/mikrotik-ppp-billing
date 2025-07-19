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
    // $table->id();
    // $table->foreignId('ppp_user_id')->constrained()->cascadeOnDelete();
    // $table->decimal('amount', 12, 2);
    // $table->string('payment_method');
    // $table->string('reference_number')->nullable();
    // $table->date('date');
    // $table->string('status')->default('confirmed');
    // $table->timestamps();
    $table->id();
    $table->foreignId('ppp_user_id')->constrained()->onDelete('cascade');
    $table->decimal('amount', 10, 2);
    $table->string('method');
    $table->string('reference');
    $table->dateTime('date');
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
