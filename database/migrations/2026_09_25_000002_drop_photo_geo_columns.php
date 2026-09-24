<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Localização serve só para liberar o acesso; as fotos não guardam posição.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('photos', 'geo_lat')) {
            Schema::table('photos', fn (Blueprint $t) => $t->dropColumn(['geo_lat', 'geo_lng', 'geo_distance']));
        }
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $t) {
            $t->decimal('geo_lat', 10, 7)->nullable();
            $t->decimal('geo_lng', 10, 7)->nullable();
            $t->unsignedInteger('geo_distance')->nullable();
        });
    }
};
