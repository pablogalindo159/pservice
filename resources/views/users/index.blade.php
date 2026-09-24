@extends('layouts.app')
@section('content')
@php
    $roles = config('pservice.roles');
    $meId = auth()->id();
@endphp
<div class="page-head"><div><span class="eyebrow">ADMINISTRAÇÃO</span><h1>Usuários</h1><p>Controle de acesso</p></div></div>

<details class="panel new-user" @if($errors->any() || $users->total() <= 1) open @endif>
    <summary>＋ Novo usuário</summary>
    <form class="form-grid" method="post" action="{{ route('users.store') }}">@csrf
        <input name="name" value="{{ old('name') }}" placeholder="Nome" required>
        <input type="email" name="email" value="{{ old('email') }}" placeholder="E-mail" required autocapitalize="none">
        <input type="password" name="password" placeholder="Senha (mín. 8)" minlength="8" required autocomplete="new-password">
        <select name="role">@foreach($roles as $k => $label)<option value="{{ $k }}" @selected(old('role', 'technician') === $k)>{{ $label }}</option>@endforeach</select>
        <button class="primary">Criar usuário</button>
    </form>
</details>

<form class="searchbar user-search" method="get" action="{{ route('users.index') }}">
    <input type="search" name="q" value="{{ $q }}" placeholder="🔎  Buscar por nome ou e-mail" inputmode="search" autocomplete="off">
    <select name="role" onchange="this.form.submit()" aria-label="Perfil"><option value="">Todos os perfis</option>@foreach($roles as $k => $label)<option value="{{ $k }}" @selected($role === $k)>{{ $label }}</option>@endforeach</select>
    <select name="status" onchange="this.form.submit()" aria-label="Situação"><option value="">Ativos e inativos</option><option value="ativo" @selected($status === 'ativo')>Só ativos</option><option value="inativo" @selected($status === 'inativo')>Só inativos</option></select>
    <button class="primary">Buscar</button>
</form>
<p class="result-count">{{ $users->total() }} usuário(s)@if($q || $role || $status) encontrado(s) · <a href="{{ route('users.index') }}">limpar filtros</a>@endif</p>

<div class="user-list">
@forelse($users as $u)
    @php
        $initials = collect(preg_split('/\s+/', trim($u->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
        $isMe = $u->id === $meId;
    @endphp
    <article @class(['user-card', 'inactive' => ! $u->active])>
        <header>
            <div class="avatar role-{{ $u->role }}">{{ $initials ?: '?' }}</div>
            <div class="who">
                <b>{{ $u->name }}@if($isMe) <span class="tag-me">você</span>@endif</b>
                <span class="email">{{ $u->email }}</span>
            </div>
            <span @class(['state', 'on' => $u->active])>{{ $u->active ? 'Ativo' : 'Inativo' }}</span>
        </header>

        <form class="uc-edit" method="post" action="{{ route('users.update', $u) }}">@csrf @method('PATCH')
            <label>Nome<input name="name" value="{{ $u->name }}" required></label>
            <label>Perfil<select name="role">@foreach($roles as $k => $label)<option value="{{ $k }}" @selected($u->role === $k)>{{ $label }}</option>@endforeach</select></label>
            <button class="secondary">Salvar alterações</button>
        </form>

        <details class="uc-password">
            <summary>🔑 Redefinir senha</summary>
            <form method="post" action="{{ route('users.password', $u) }}">@csrf @method('PATCH')
                <input type="password" name="password" minlength="8" placeholder="Nova senha (mín. 8)" required autocomplete="new-password">
                <button class="secondary">Definir</button>
            </form>
        </details>

        <footer>
            <small>Desde {{ $u->created_at?->format('d/m/Y') }}</small>
            @unless($isMe)
            <form method="post" action="{{ route('users.toggle', $u) }}" @if($u->active) onsubmit="return confirm('Desativar {{ addslashes($u->name) }}? O acesso é bloqueado na hora.')" @endif>@csrf @method('PATCH')
                <button @class(['sm', 'toggle-off' => $u->active, 'toggle-on' => ! $u->active])>{{ $u->active ? 'Desativar acesso' : 'Reativar acesso' }}</button>
            </form>
            @endunless
        </footer>
    </article>
@empty
    <div class="panel"><p class="muted">Nenhum usuário encontrado com esses filtros.</p></div>
@endforelse
</div>
<div class="pager">{{ $users->links('partials.pager') }}</div>
@endsection
