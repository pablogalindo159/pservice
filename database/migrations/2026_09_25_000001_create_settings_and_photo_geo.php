<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        // Onde a foto foi enviada (quando a localização do aparelho estava disponível)
        Schema::table('photos', function (Blueprint $t) {
            $t->decimal('geo_lat', 10, 7)->nullable();
            $t->decimal('geo_lng', 10, 7)->nullable();
            $t->unsignedInteger('geo_distance')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::table('photos', fn (Blueprint $t) => $t->dropColumn(['geo_lat', 'geo_lng', 'geo_distance']));
    }
};
