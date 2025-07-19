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
            // Hapus baris ini: $table->string('profile'); // Profil diambil dari paket atau status
            $table->string('local_address')->nullable(); // Mungkin bisa null jika tidak selalu diset
            $table->string('remote_address')->nullable(); // Mungkin bisa null jika tidak selalu diset
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('grace_period_days')->default(1); // Mengubah default jadi 1
            $table->dateTime('suspended_at')->nullable();
            $table->dateTime('restored_at')->nullable();
            $table->decimal('balance', 12, 2)->default(0);
            $table->enum('status', ['active', 'suspended', 'expired', 'pending'])->default('pending');

            // Ini akan mengacu ke tabel 'packages' secara default.
            // Pertimbangkan untuk mengganti nullOnDelete() jika Anda ingin perilaku berbeda.
            // constrained('packages') tidak perlu jika nama tabel sudah standar Laravel.
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Tambahan index
            $table->index('status');
            $table->index('package_id');
            $table->index('expired_at');
            $table->index('due_date'); // Tambahkan index untuk due_date juga
        });
    }

    public function down()
    {
        Schema::dropIfExists('ppp_users');
    }
};