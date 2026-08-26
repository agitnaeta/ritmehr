<?php

namespace App\Http\Controllers\Career;

use App\Models\Candidate;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * M17 — Candidate account auth (register / login / logout) on the "candidate"
 * guard. Completely separate from the admin/employee login.
 */
class CandidateAuthController extends Controller
{
    public function showRegister()
    {
        if (Auth::guard('candidate')->check()) {
            return redirect()->route('career.dashboard');
        }

        return view('career.auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => 'required|email|max:150|unique:candidates,email',
            'phone'    => 'nullable|string|max:30',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'name.required'     => 'Nama wajib diisi.',
            'email.required'    => 'Email wajib diisi.',
            'email.unique'      => 'Email sudah terdaftar. Silakan masuk.',
            'password.min'      => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $candidate = Candidate::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'password' => $data['password'], // hashed by cast
        ]);

        Auth::guard('candidate')->login($candidate);

        return redirect()->route('career.dashboard')
            ->with('success', 'Akun berhasil dibuat. Selamat datang, ' . $candidate->name . '!');
    }

    public function showLogin()
    {
        if (Auth::guard('candidate')->check()) {
            return redirect()->route('career.dashboard');
        }

        return view('career.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required'    => 'Email wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        if (! Auth::guard('candidate')->attempt($data, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Email atau kata sandi salah.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('career.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::guard('candidate')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('career.index');
    }
}
