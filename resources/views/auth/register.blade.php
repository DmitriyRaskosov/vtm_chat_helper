@extends('layouts.app')

@section('title', 'Регистрация')

@section('content')
    <div class="top">
        <h1>Game Chat</h1>
        <span class="muted">регистрация</span>
    </div>
    <div class="card">
        <form method="post" action="{{ route('register') }}">
            @csrf
            <label for="login">Логин</label>
            <input id="login" type="text" name="login" value="{{ old('login') }}" required autofocus autocomplete="username">
            @error('login') <p class="error">{{ $message }}</p> @enderror

            <label for="name">Имя в чате</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required>
            @error('name') <p class="error">{{ $message }}</p> @enderror

            <label for="password">Пароль</label>
            <input id="password" type="password" name="password" required>
            @error('password') <p class="error">{{ $message }}</p> @enderror

            <label for="password_confirmation">Ещё раз пароль</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required>

            <button type="submit">Создать аккаунт</button>
        </form>
        <p class="muted">Первый зарегистрированный становится рассказчиком, остальные — игроками.</p>
        <p class="muted">Уже есть аккаунт? <a href="{{ route('login') }}">Войти</a></p>
    </div>
@endsection
