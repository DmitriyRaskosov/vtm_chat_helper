<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            ...$request->validated(),
            'role' => User::query()->where('role', UserRole::Storyteller)->exists()
                ? UserRole::Player
                : UserRole::Storyteller,
        ]);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('chat');
    }
}
