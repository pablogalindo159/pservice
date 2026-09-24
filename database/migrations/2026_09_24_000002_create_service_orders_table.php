<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void{Schema::create('service_orders',function(Blueprint $t){$t->id();$t->string('number',50)->unique();$t->string('client_name');$t->string('status')->default('aberta')->index();$t->timestamps();});}public function down():void{Schema::dropIfExists('service_orders');}};
