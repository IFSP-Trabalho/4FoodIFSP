<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirebaseTokenVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly FirebaseTokenVerifier $tokenVerifier
    ) {
    }

    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($user->must_reset_password) {
                return redirect()->route('password.change.show');
            }

            return $this->redirectByRole($user->role);
        }

        return Inertia::render('Auth/Login');
    }

    public function firebaseLogin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'idToken' => ['required', 'string'],
        ]);

        try {
            $uid = $this->tokenVerifier->verifyAndGetUid($validated['idToken']);
        } catch (Throwable) {
            return back()->withErrors([
                'email' => 'Token invalido. Tente novamente.',
            ]);
        }

        $user = User::find($uid);

        if (! $user instanceof User) {
            $deletedUser = User::withTrashed()->find($uid);

            if ($deletedUser instanceof User && $deletedUser->trashed()) {
                return back()->withErrors([
                    'email' => 'Usuario removido da plataforma.',
                ]);
            }

            abort(HttpResponse::HTTP_FORBIDDEN, 'Usuario nao cadastrado no sistema.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($user->must_reset_password) {
            return redirect()->route('password.change.show');
        }

        return $this->redirectByRole($user->role);
    }

    public function showPasswordChange(): Response|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->must_reset_password) {
            return $this->redirectByRole($user->role);
        }

        return Inertia::render('Auth/Login', ['changePassword' => true]);
    }

    public function updatePasswordOnFirstAccess(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->must_reset_password) {
            return $this->redirectByRole($user->role);
        }

        $validated = $request->validate([
            'idToken' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'max:80', 'confirmed'],
        ]);

        try {
            $uid = $this->tokenVerifier->verifyAndGetUid($validated['idToken']);
        } catch (Throwable) {
            return back()->withErrors([
                'new_password' => 'Nao foi possivel validar a nova senha no Firebase. Tente novamente.',
            ]);
        }

        if ($uid !== (string) $user->id) {
            abort(HttpResponse::HTTP_FORBIDDEN, 'Token invalido para este usuario.');
        }

        $user->forceFill([
            'must_reset_password' => false,
        ])->save();

        return $this->redirectByRole($user->role)
            ->with('success', 'Senha atualizada com sucesso.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function redirectByRole(string $role): RedirectResponse
    {
        return match ($role) {
            'admin' => redirect()->route('admin.dashboard'),
            'kitchen' => redirect()->route('admin.orders'),
            'finance' => redirect()->route('admin.orders'),
            'waiter' => redirect()->route('admin.mesas.index'),
            'whatsapp_agent' => redirect()->route('whatsapp.inbox'),
            default => redirect()
                ->route('login')
                ->withErrors(['email' => 'Perfil sem rota de acesso configurada.']),
        };
    }
}
