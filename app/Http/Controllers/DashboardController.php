<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Photo;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $total = ServiceOrder::count();
        $photos = Photo::count();
        $ordersToday = ServiceOrder::whereDate('created_at', today())->count();
        $photosToday = Photo::whereDate('captured_at', today())->count();
        $openOrders = ServiceOrder::whereIn('status', ['aberta', 'em_andamento', 'aguardando'])->count();
        $byStage = Photo::select('stage', DB::raw('count(*) as total'))->groupBy('stage')->pluck('total', 'stage');
        $recentOrders = ServiceOrder::withCount('photos')->latest('updated_at')->limit(8)->get();
        $recentPhotos = Photo::with('order', 'user')->latest('captured_at')->limit(8)->get();
        $recentActivity = auth()->user()->canAudit()
            ? AuditLog::with('user', 'order')->latest()->limit(10)->get()
            : collect();

        return view('dashboard', compact(
            'total', 'photos', 'ordersToday', 'photosToday', 'openOrders',
            'byStage', 'recentOrders', 'recentPhotos', 'recentActivity'
        ));
    }
}
