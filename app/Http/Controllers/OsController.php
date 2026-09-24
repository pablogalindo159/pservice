<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ServiceOrder;
use App\Support\Stages;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OsController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->trim()->toString();
        $status = $request->string('status')->toString();

        $orders = ServiceOrder::query()
            ->withCount('photos')
            ->when($q, fn ($x) => $x->where(fn ($w) => $w
                ->where('number', 'like', '%'.ltrim(preg_replace('/^os/i', '', $q)).'%')
                ->orWhere('client_name', 'like', "%{$q}%")))
            ->when(array_key_exists($status, config('pservice.statuses')), fn ($x) => $x->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('os.index', compact('orders', 'q', 'status'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->canCreateOs(), 403);

        // Aceita "OS1020" ou "1020" e guarda só "1020".
        $request->merge(['number' => preg_replace('/^os\s*/i', '', trim((string) $request->input('number')))]);

        $data = $request->validate([
            'number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/', 'unique:service_orders,number'],
            'client_name' => 'required|string|max:255',
        ], [
            'number.regex' => 'O número da OS deve conter apenas letras, números e hífen.',
            'number.unique' => 'Já existe uma OS com este número.',
        ]);

        $order = ServiceOrder::create($data);
        AuditLog::record('os.created', $order->id, null, ['client' => $order->client_name]);

        return redirect()->route('os.show', $order)->with('ok', 'OS criada.');
    }

    public function show(ServiceOrder $os)
    {
        $stages = Stages::all();
        // Mais antiga primeiro: a primeira foto da etapa é a capa.
        $photos = $os->photos()->with('user')->orderBy('captured_at')->orderBy('id')->get()->groupBy('stage');

        return view('os.show', compact('os', 'stages', 'photos'));
    }

    public function status(Request $request, ServiceOrder $os)
    {
        abort_unless($request->user()?->canCreateOs(), 403);

        $data = $request->validate(['status' => ['required', Rule::in(array_keys(config('pservice.statuses')))]]);
        $old = $os->status;

        if ($old !== $data['status']) {
            $os->update(['status' => $data['status']]);
            AuditLog::record('os.status_changed', $os->id, null, ['from' => $old, 'to' => $os->status]);
        }

        return back()->with('ok', 'Status atualizado.');
    }
}
