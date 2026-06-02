# Feature: auth

> Contexto: Autenticação do painel de gestão via Firebase Authentication + Laravel session  
> Depende de: [schema.md](../database/schema.md) (tabela `users` com `id = firebaseUid`)  
> Roles com acesso: todos (tela pública) → redireciona conforme role após login  
> Stack: Inertia.js + Vue 3 no frontend, Laravel no backend

---

## Objetivo

Permitir que funcionários do restaurante façam login com e-mail e senha. O Firebase valida as credenciais e retorna um `idToken` JWT. O Laravel verifica esse token, identifica o usuário no banco e cria a sessão. O admin é redirecionado para `/admin/dashboard` sem passar pela tela de redefinição de senha.

---

## Escopo desta feature (MVP)

- [x] Tela de login (e-mail + senha)
- [x] Integração Firebase client-side (`signInWithEmailAndPassword`)
- [x] Endpoint Laravel que recebe o `idToken` e cria sessão
- [x] Middleware `FirebaseAuthMiddleware`
- [x] Redirecionamento pós-login por role
- [x] Logout
- [ ] Redefinição de senha no primeiro login → ver `feature/first-login.md` (fora deste MVP)

---

## Entidades envolvidas

- `users` — leitura por `id` (firebaseUid) e por `role`

---

## Regras de negócio

- Login só aceita e-mail + senha (sem Google, sem magic link)
- O admin (`role = admin`) é redirecionado para `/admin/dashboard` e **nunca** passa pelo fluxo de `must_reset_password`
- Usuários de outros roles que tenham `must_reset_password = true` são redirecionados para `/password/change` — mas isso é tratado na feature `first-login`, não aqui
- Se o `idToken` for válido no Firebase mas o `uid` não existir na tabela `users`, retornar 403 (usuário Firebase sem cadastro no sistema)
- Sessão é gerenciada pelo Laravel (`Auth::setUser`) após verificação do token — não é stateless JWT puro
- Rota `/login` é pública. Todas as demais rotas do painel exigem `FirebaseAuthMiddleware`

---

## Stack e pacotes

| Pacote | Função |
|---|---|
| `kreait/laravel-firebase` | Verificação do `idToken` no backend |
| `firebase/firebase-js-sdk` (npm) | `signInWithEmailAndPassword` no frontend |
| `@inertiajs/vue3` | Comunicação frontend → backend sem API REST explícita |

---

## Rotas

| Método | URI | Controller@method | Middleware |
|---|---|---|---|
| GET | `/login` | `AuthController@showLogin` | `guest` |
| POST | `/auth/firebase` | `AuthController@firebaseLogin` | `guest` |
| POST | `/logout` | `AuthController@logout` | `firebase.auth` |

---

## Fluxo detalhado

### Login bem-sucedido

1. Usuário acessa `/login` → Vue renderiza o formulário
2. Usuário preenche e-mail + senha e clica em "Entrar"
3. Frontend chama `signInWithEmailAndPassword(auth, email, password)` via Firebase JS SDK
4. Firebase retorna `UserCredential` → extrai `idToken` via `user.getIdToken()`
5. Frontend faz `router.post('/auth/firebase', { idToken })` via Inertia
6. `AuthController@firebaseLogin` recebe o `idToken`
7. `Firebase::auth()->verifyIdToken($idToken)` → obtém `uid` do claim `sub`
8. `User::findOrFail($uid)` → se não existir, lança 403
9. `Auth::setUser($user)` + `request->session()->regenerate()`
10. Redireciona por role:
    - `admin` → `/admin/dashboard`
    - `kitchen` → `/kitchen/dashboard`
    - `finance` → `/finance/dashboard`
    - `waiter` → `/waiter/dashboard`

### Login com credenciais inválidas

- Firebase lança `auth/wrong-password` ou `auth/user-not-found`
- Frontend captura a exceção e exibe mensagem genérica no formulário: *"E-mail ou senha incorretos"*
- Nenhuma requisição é feita ao backend

### Logout

- Frontend chama `router.post('/logout')` via Inertia
- Backend executa `Auth::logout()` + `session()->invalidate()` + `session()->regenerateToken()`
- Redireciona para `/login`

---

## Implementação — backend

### `FirebaseAuthMiddleware`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Kreait\Laravel\Firebase\Facades\Firebase;
use App\Models\User;

class FirebaseAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
```

### `AuthController`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Kreait\Laravel\Firebase\Facades\Firebase;
use App\Models\User;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    public function firebaseLogin(Request $request)
    {
        $request->validate(['idToken' => 'required|string']);

        try {
            $verifiedToken = Firebase::auth()->verifyIdToken($request->idToken);
            $uid = $verifiedToken->claims()->get('sub');
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => 'Token inválido. Tente novamente.']);
        }

        $user = User::find($uid);

        if (!$user) {
            abort(403, 'Usuário não cadastrado no sistema.');
        }

        Auth::setUser($user);
        $request->session()->regenerate();

        return $this->redirectByRole($user->role);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function redirectByRole(string $role)
    {
        return match($role) {
            'admin'            => redirect()->route('admin.dashboard'),
            'kitchen'          => redirect()->route('kitchen.dashboard'),
            'finance'          => redirect()->route('finance.dashboard'),
            'waiter'           => redirect()->route('waiter.dashboard'),
            default            => redirect()->route('login'),
        };
    }
}
```

### Registro do middleware em `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'firebase.auth' => \App\Http\Middleware\FirebaseAuthMiddleware::class,
    ]);
})
```

---

## Implementação — frontend

### `resources/js/Pages/Auth/Login.vue`

```vue
<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { initializeApp } from 'firebase/app'
import { getAuth, signInWithEmailAndPassword } from 'firebase/auth'

const firebaseApp = initializeApp({
  apiKey:            import.meta.env.VITE_FIREBASE_API_KEY,
  authDomain:        import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
  projectId:         import.meta.env.VITE_FIREBASE_PROJECT_ID,
})

const auth = getAuth(firebaseApp)

const email    = ref('')
const password = ref('')
const error    = ref('')
const loading  = ref(false)

async function handleLogin() {
  error.value   = ''
  loading.value = true

  try {
    const credential = await signInWithEmailAndPassword(auth, email.value, password.value)
    const idToken    = await credential.user.getIdToken()

    router.post('/auth/firebase', { idToken }, {
      onError: (errors) => { error.value = errors.email || 'Erro ao autenticar.' },
      onFinish: () => { loading.value = false },
    })
  } catch (e) {
    error.value   = 'E-mail ou senha incorretos.'
    loading.value = false
  }
}
</script>

<template>
  <!-- Estrutura mínima — estilize conforme o design system escolhido -->
  <div class="login-wrapper">
    <form @submit.prevent="handleLogin">
      <h1>Entrar</h1>

      <div v-if="error" class="error-msg">{{ error }}</div>

      <label>E-mail
        <input v-model="email" type="email" autocomplete="email" required />
      </label>

      <label>Senha
        <input v-model="password" type="password" autocomplete="current-password" required />
      </label>

      <button type="submit" :disabled="loading">
        {{ loading ? 'Entrando...' : 'Entrar' }}
      </button>
    </form>
  </div>
</template>
```

---

## Variáveis de ambiente necessárias

```env
# .env (backend — Firebase Admin SDK)
FIREBASE_CREDENTIALS=/caminho/para/serviceAccount.json

# .env (frontend — Firebase JS SDK)
VITE_FIREBASE_API_KEY=...
VITE_FIREBASE_AUTH_DOMAIN=...
VITE_FIREBASE_PROJECT_ID=...
```

---

## Sobre o `dashboard_admin` — resposta direta

A rota correta é `/admin/dashboard`, renderizando `Inertia::render('Admin/Dashboard')`.

Não existe uma convenção "dashboard_adm" — o padrão Inertia é:
- Rota: `/admin/dashboard`
- Controller: `Admin\DashboardController@index`
- View (Vue): `resources/js/Pages/Admin/Dashboard.vue`

Essa view é criada na feature `admin-dashboard.md`, não aqui.
O login apenas **redireciona** para ela — não a renderiza.

---

## O que NÃO está nessa feature

- Criação de usuário pelo admin → ver `features/usuarios.md`
- Redefinição de senha no primeiro login → ver `features/first-login.md`
- Conteúdo do dashboard admin → ver `features/admin-dashboard.md`
- RBAC nas rotas protegidas → o middleware aqui só verifica se está autenticado; a verificação de role fica em middleware separado `RoleMiddleware`

---

## Status de implementação

- [ ] `kreait/laravel-firebase` instalado (`composer require kreait/laravel-firebase`)
- [ ] `firebase/firebase-js-sdk` instalado (`npm install firebase`)
- [ ] `FirebaseAuthMiddleware` criado e registrado
- [ ] `AuthController` criado com `showLogin`, `firebaseLogin`, `logout`
- [ ] Rotas definidas em `routes/web.php`
- [ ] `Pages/Auth/Login.vue` criado
- [ ] Variáveis de ambiente configuradas (`.env` + `VITE_*`)
- [ ] Redirecionamento por role testado para `admin`