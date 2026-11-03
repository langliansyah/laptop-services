<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function registerForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'min:8', 'max:25', function (string $attribute, mixed $value, \Closure $fail): void {
                if (strlen(preg_replace('/\D+/', '', (string) $value)) < 8) {
                    $fail('Nomor WhatsApp harus memiliki sedikitnya 8 angka.');
                }
            }],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $validated['phone'] = preg_replace('/\D+/', '', $validated['phone']);
        $user = User::create([...$validated, 'password' => Hash::make($validated['password']), 'role' => 'member']);
        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.history')->with('success', 'Akun member berhasil dibuat.');
    }

    public function loginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau kata sandi tidak sesuai.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return $request->user()->isStaff()
            ? redirect()->route('staff.index')
            : redirect()->route('account.history');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function history(Request $request): View
    {
        abort_unless($request->user()->isMember(), 403);
        $services = $request->user()->services()->latest()->paginate(15);
        return view('account.history', compact('services'));
    }
}
