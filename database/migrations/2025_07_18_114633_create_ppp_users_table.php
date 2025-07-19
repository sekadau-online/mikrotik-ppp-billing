<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('ppp_users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('service')->default('pppoe');
            $table->string('profile');
            $table->string('local_address');
            $table->string('remote_address');
            $table->string('phone');
            $table->string('email');
            $table->text('address');
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('grace_period_days')->default(7);
            $table->dateTime('suspended_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->decimal('balance', 10, 2)->default(0);
            $table->json('payment_history')->nullable();
            $table->enum('status', ['active', 'suspended', 'expired'])->default('active');
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ppp_users');
    }
};