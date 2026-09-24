<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $t) {
            $t->string('preview_path')->nullable()->after('original_path');
        });

        Schema::table('audit_logs', function (Blueprint $t) {
            $t->string('ip', 45)->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('photos', fn (Blueprint $t) => $t->dropColumn('preview_path'));
        Schema::table('audit_logs', fn (Blueprint $t) => $t->dropColumn('ip'));
    }
};
