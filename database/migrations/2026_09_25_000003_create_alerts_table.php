<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();         // quem gerou
            $t->foreignId('service_order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type')->index();
            $t->string('message');
            $t->json('meta')->nullable();
            $t->timestamp('seen_at')->nullable()->index();
            $t->foreignId('seen_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
