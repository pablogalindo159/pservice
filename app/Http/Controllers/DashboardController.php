<?php
namespace App\Http\Controllers;
use App\Models\ServiceOrder;
use App\Models\Photo;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller {
 public function index(){
  $total=ServiceOrder::count();
  $photos=Photo::count();
  $ordersToday=ServiceOrder::whereDate('created_at',today())->count();
  $photosToday=Photo::whereDate('captured_at',today())->count();
  $byStage=Photo::select('stage',DB::raw('count(*) as total'))->groupBy('stage')->pluck('total','stage');
  $recentPhotos=Photo::with('order','user')->latest('captured_at')->limit(12)->get();
  $recentActivity=AuditLog::with('user','order')->latest()->limit(10)->get();
  return view('dashboard',compact('total','photos','ordersToday','photosToday','byStage','recentPhotos','recentActivity'));
 }
}
