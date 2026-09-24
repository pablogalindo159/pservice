@extends('layouts.app')
@section('content')
<div class="page-head"><div><span class="eyebrow">ADMINISTRAÇÃO</span><h1>Usuários</h1><p>Controle de acesso</p></div></div>
<div class="panel"><h2>Novo usuário</h2>
<form class="form-grid" method="post" action="{{ route('users.store') }}">@csrf
<input name="name" value="{{ old('name') }}" placeholder="Nome" required>
<input type="email" name="email" value="{{ old('email') }}" placeholder="E-mail" required>
<input type="password" name="password" placeholder="Senha (mín. 8)" minlength="8" required autocomplete="new-password">
<select name="role">@foreach(config('pservice.roles') as $k => $label)<option value="{{ $k }}" @selected(old('role', 'technician') === $k)>{{ $label }}</option>@endforeach</select>
<button class="primary">Criar</button></form></div>
<div class="panel table-wrap"><table>
<tr><th>Nome / perfil</th><th>E-mail</th><th>Status</th><th>Nova senha</th><th></th></tr>
@foreach($users as $u)
<tr @class(['inactive' => ! $u->active])>
<td><form class="inline" method="post" action="{{ route('users.update', $u) }}">@csrf @method('PATCH')<input name="name" value="{{ $u->name }}" required><select name="role">@foreach(config('pservice.roles') as $k => $label)<option value="{{ $k }}" @selected($u->role === $k)>{{ $label }}</option>@endforeach</select><button class="secondary">Salvar</button></form></td>
<td>{{ $u->email }}</td>
<td>{{ $u->active ? 'Ativo' : 'Inativo' }}</td>
<td><form class="inline" method="post" action="{{ route('users.password', $u) }}">@csrf @method('PATCH')<input type="password" name="password" minlength="8" placeholder="••••••••" required autocomplete="new-password"><button class="secondary">Definir</button></form></td>
<td>@if($u->id !== auth()->id())<form method="post" action="{{ route('users.toggle', $u) }}">@csrf @method('PATCH')<button class="secondary">{{ $u->active ? 'Desativar' : 'Ativar' }}</button></form>@endif</td>
</tr>
@endforeach
</table></div>
<div class="pager">{{ $users->links('partials.pager') }}</div>
@endsection
