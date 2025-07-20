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
        // Create 'packages' table first, as 'ppp_users' depends on it
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
        });

        // Create 'ppp_users' table
        Schema::create('ppp_users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('service')->default('pppoe');
            $table->string('local_address')->nullable();
            $table->string('remote_address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('grace_period_days')->default(1);
            $table->dateTime('suspended_at')->nullable();
            $table->dateTime('restored_at')->nullable();
            $table->decimal('balance', 12, 2)->default(0);
            $table->enum('status', ['active', 'suspended', 'expired', 'pending'])->default('pending');
            $table->string('mikrotik_id')->nullable();

            // Foreign key to 'packages' table
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Additional indexes
            $table->index('status');
            $table->index('package_id');
            $table->index('expired_at');
            $table->index('due_date');
        });

        // Create 'payments' table, as it depends on 'ppp_users'
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Foreign key to 'ppp_users' table
            $table->foreignId('ppp_user_id')->constrained('ppp_users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method');
            $table->string('reference')->nullable();
            $table->string('order_id')->unique();
            $table->string('status')->default('pending');
            $table->string('snap_token')->nullable();
            $table->dateTime('payment_date');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('ppp_users');
        Schema::dropIfExists('packages');
    }
};
