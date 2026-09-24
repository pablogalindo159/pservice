<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->canAudit(), 403);
        $alerts = Alert::with(['user', 'order', 'seenBy'])->latest('updated_at')->paginate(30);

        return view('alerts.index', compact('alerts'));
    }

    public function seen(Request $request)
    {
        abort_unless($request->user()?->canAudit(), 403);
        Alert::whereNull('seen_at')->update(['seen_at' => now(), 'seen_by' => $request->user()->id]);

        return back()->with('ok', 'Alertas marcados como vistos.');
    }
}
