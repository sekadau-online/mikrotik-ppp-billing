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
            $table->ipAddress('local_address');
            $table->ipAddress('remote_address')->unique();
            $table->string('phone');
            $table->string('email');
            $table->text('address');
            $table->date('activated_at')->nullable();
            $table->date('expired_at')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('grace_period_days')->default(7);
            $table->date('suspended_at')->nullable();
            $table->timestamp('restored_at')->nullable()->after('suspended_at');
            $table->decimal('balance', 10, 2)->default(0);
            $table->json('payment_history')->nullable();
            $table->enum('status', ['active', 'suspended', 'expired'])->default('active');
            
            // Ubah menjadi nullable dulu untuk migrasi awal
            $table->foreignId('package_id')->nullable()->constrained();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::table('ppp_users', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
        });
        
        Schema::dropIfExists('ppp_users');
    }
};