@extends('layouts.app')

@section('title', 'Вход')

@section('content')
    <div class="top">
        <h1>Game Chat</h1>
        <span class="muted">вход</span>
    </div>
    <div class="card">
        <form method="post" action="{{ route('login') }}">
            @csrf
            <label for="login">Логин</label>
            <input id="login" type="text" name="login" value="{{ old('login') }}" required autofocus autocomplete="username">
            @error('login') <p class="error">{{ $message }}</p> @enderror

            <label for="password">Пароль</label>
            <input id="password" type="password" name="password" required>
            @error('password') <p class="error">{{ $message }}</p> @enderror

            <button type="submit">Войти</button>
        </form>
        <p class="muted">Нет аккаунта? <a href="{{ route('register') }}">Регистрация</a></p>
    </div>
@endsection
