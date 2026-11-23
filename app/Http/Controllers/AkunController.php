<?php

namespace App\Http\Controllers;

use App\Models\Pengguna;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AkunController extends Controller
{
    public function registerForm(): View
    {
        return view('autentikasi.daftar');
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
        $user = Pengguna::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => 'member',
        ]);
        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.history')->with('success', 'Akun member berhasil dibuat.');
    }

    public function loginForm(): View
    {
        return view('autentikasi.masuk');
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

        return view('akun.riwayat', compact('services'));
    }

    public function staffAccounts(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        $accounts = Pengguna::query()
            ->whereIn('role', ['admin', 'technician'])
            ->latest()
            ->paginate(20);
        $adminCount = Pengguna::query()->where('role', 'admin')->count();

        return view('staf.akun', compact('accounts', 'adminCount'));
    }

    public function storeStaffAccount(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'min:8', 'max:25', function (string $attribute, mixed $value, \Closure $fail): void {
                if (strlen(preg_replace('/\D+/', '', (string) $value)) < 8) {
                    $fail('Nomor WhatsApp harus memiliki sedikitnya 8 angka.');
                }
            }],
            'role' => ['required', 'string', 'in:admin,technician'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $validated['phone'] = preg_replace('/\D+/', '', $validated['phone']);

        Pengguna::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('staff.accounts')->with('success', 'Akun staf berhasil dibuat.');
    }

    public function editStaffAccount(Request $request, Pengguna $account): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless(in_array($account->role, ['admin', 'technician'], true), 404);

        return view('staf.akun-edit', compact('account'));
    }

    public function updateStaffAccount(Request $request, Pengguna $account): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless(in_array($account->role, ['admin', 'technician'], true), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($account->id)],
            'phone' => ['required', 'string', 'min:8', 'max:25', function (string $attribute, mixed $value, \Closure $fail): void {
                if (strlen(preg_replace('/\D+/', '', (string) $value)) < 8) {
                    $fail('Nomor WhatsApp harus memiliki sedikitnya 8 angka.');
                }
            }],
            'role' => ['required', 'string', 'in:admin,technician'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);
        $validated['phone'] = preg_replace('/\D+/', '', $validated['phone']);

        DB::transaction(function () use ($account, $validated): void {
            $adminIds = Pengguna::query()
                ->where('role', 'admin')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
            $lockedAccount = Pengguna::query()->lockForUpdate()->findOrFail($account->id);
            abort_unless(in_array($lockedAccount->role, ['admin', 'technician'], true), 404);

            if ($lockedAccount->role === 'admin' && $validated['role'] !== 'admin' && $adminIds->count() <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'Setidaknya satu akun admin harus tetap tersedia.',
                ]);
            }

            $attributes = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'role' => $validated['role'],
            ];
            if (! empty($validated['password'])) {
                $attributes['password'] = Hash::make($validated['password']);
            }

            $lockedAccount->update($attributes);
        });

        return redirect()->route('staff.accounts')->with('success', 'Akun staf berhasil diperbarui.');
    }

    public function destroyStaffAccount(Request $request, Pengguna $account): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless(in_array($account->role, ['admin', 'technician'], true), 404);

        DB::transaction(function () use ($request, $account): void {
            $adminIds = Pengguna::query()
                ->where('role', 'admin')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
            $lockedAccount = Pengguna::query()->lockForUpdate()->findOrFail($account->id);
            abort_unless(in_array($lockedAccount->role, ['admin', 'technician'], true), 404);

            if ($request->user()->is($lockedAccount)) {
                throw ValidationException::withMessages([
                    'account' => 'Akun yang sedang digunakan tidak dapat dihapus.',
                ]);
            }

            if ($lockedAccount->role === 'admin' && $adminIds->count() <= 1) {
                throw ValidationException::withMessages([
                    'account' => 'Akun admin terakhir tidak dapat dihapus.',
                ]);
            }

            $lockedAccount->delete();
        });

        return redirect()->route('staff.accounts')->with('success', 'Akun staf berhasil dihapus.');
    }
}
