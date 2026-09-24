<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private function admin(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    private function roleRule(): array
    {
        return ['required', Rule::in(array_keys(config('pservice.roles')))];
    }

    public function index()
    {
        $this->admin();
        $users = User::orderBy('name')->paginate(20);

        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $this->admin();
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => $this->roleRule(),
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['active'] = true;
        $user = User::create($data);
        AuditLog::record('user.created', null, null, ['user_id' => $user->id, 'email' => $user->email, 'role' => $user->role]);

        return back()->with('ok', 'Usuário criado.');
    }

    public function update(Request $request, User $user)
    {
        $this->admin();
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'role' => $this->roleRule(),
        ]);

        if ($user->id === $request->user()->id && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'Você não pode remover seu próprio perfil de administrador.']);
        }

        $old = $user->only('name', 'role');
        $user->update($data);
        AuditLog::record('user.updated', null, null, ['user_id' => $user->id, 'from' => $old, 'to' => $user->only('name', 'role')]);

        return back()->with('ok', 'Usuário atualizado.');
    }

    public function password(Request $request, User $user)
    {
        $this->admin();
        $data = $request->validate(['password' => 'required|string|min:8']);

        $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
        AuditLog::record('user.password_reset', null, null, ['user_id' => $user->id]);

        return back()->with('ok', "Senha de {$user->name} redefinida.");
    }

    public function toggle(Request $request, User $user)
    {
        $this->admin();
        abort_if($user->id === $request->user()->id, 422, 'Não desative seu próprio usuário.');

        $user->update(['active' => ! $user->active]);
        AuditLog::record('user.toggled', null, null, ['user_id' => $user->id, 'active' => $user->active]);

        return back()->with('ok', 'Status atualizado.');
    }
}
