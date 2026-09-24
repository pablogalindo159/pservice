<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;use App\Models\User;use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder{public function run():void{$email=env('ADMIN_EMAIL','admin@pservice.local');$password=env('ADMIN_PASSWORD','TroqueEstaSenha123!');User::updateOrCreate(['email'=>$email],['name'=>env('ADMIN_NAME','Administrador'),'password'=>Hash::make($password),'role'=>'admin','active'=>true]);}}
