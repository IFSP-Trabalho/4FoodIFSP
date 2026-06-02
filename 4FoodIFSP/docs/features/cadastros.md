# Feature: admin-cadastros

> Contexto: Módulo de cadastros no painel admin com navegação secundária lateral. Primeira entrega focada na listagem e gestão de usuários.  
> Depende de: [auth.md](auth.md), [schema.md](../database/schema.md)  
> Roles com acesso: `admin` apenas  
> Stack: Inertia.js + Vue 3, Laravel controller

---

## Objetivo

Organizar os cadastros do sistema em uma área única, acessada pela sidebar principal. Ao clicar no ícone de Cadastros, deve abrir um menu lateral secundário com os tipos de cadastro disponíveis. A tela de **Usuários** possui criação funcional (formulário + persistência), enquanto **Departamentos (roles)** e **Pratos** ficam preparados para fase futura.

### Regra técnica de CSS (obrigatória)

- CSS não deve ficar embutido dentro de `.vue`.
- Cada componente/página deve usar arquivo externo na pasta `styles`, por exemplo: `<style scoped src="./styles/Users.css"></style>`.
- Em novas features, manter o mesmo padrão para preservar legibilidade e manutenção.

---

## Layout

### Estrutura geral

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ [sidebar principal] │ [submenu cadastros] │ [topbar]                        │
│ 52px (ícones)       │ 220px               │ Usuários      [buscar][+Add]    │
│                     │                      ├──────────────────────────────────│
│ [ícone livro +]     │ • Usuários (ativo)  │ tabela/lista de usuários         │
│                     │ • Departamentos      │ id | usuário | e-mail | dept |  │
│                     │ • Pratos             │ ações                            │
└──────────────────────────────────────────────────────────────────────────────┘
```

### Sidebar principal (`AppSidebar.vue`)

- Largura fixa: `52px`
- Item de Cadastros com ícone formal de **livro +** (conceito: registro/cadastro institucional)
- Estado ativo:
  - Fundo: `#FAECE7`
  - Ícone: `#993C1D`
- Ao clicar em Cadastros, abre painel lateral secundário (não troca de página imediatamente)

### Ícone de Cadastros

Requisito visual:
- Estilo formal e administrativo
- Referência semântica: `book-plus` (livro com sinal de mais)
- Não usar ícone lúdico; manter alinhado ao padrão corporativo da sidebar

Exemplos de nomenclatura conforme biblioteca de ícones:
- Lucide: `BookPlus`
- Heroicons (aproximação): `BookOpenIcon` com badge `+`
- Phosphor (aproximação): `BookBookmark` + marcador plus

---

## Navegação de Cadastros

### Submenu lateral (ao lado da sidebar)

Ao clicar em Cadastros na sidebar, abrir um painel secundário com 3 opções:

1. **Usuários**
2. **Departamentos (roles)** *(cadastro para depois)*
3. **Pratos** *(cadastro para depois)*

### Comportamento

- Submenu fixo à esquerda do conteúdo principal
- Item ativo destacado
- Itens não implementados (`Departamentos`, `Pratos`) com badge `Em breve`
- Clique em item não implementado:
  - mantém navegação no contexto de Cadastros
  - pode mostrar estado vazio explicando que a feature será liberada na próxima fase

---

## Tela: Usuários

### Cabeçalho da página

Na área principal:

- Título à esquerda: `Usuários`
- Ações à direita:
  - Campo de busca: `Localize`
  - Botão primário: `Adicionar`

Layout textual esperado:

`Usuários                                          Localize - Adicionar`

### Tabela/lista de usuários

Colunas:

| Coluna | Conteúdo |
|---|---|
| ID | `users.id` |
| Nome usuário | avatar circular + nome |
| E-mail | `users.email` |
| Departamentos | lista/badge dos departamentos vinculados |
| Ações | gestão de departamentos, editar, deletar |

Cabeçalho conforme solicitado:

`id Nome usuário : e-mail : departamentos : ações`

### Linha do usuário (row item)

- Avatar redondo (iniciais ou ícone de usuário)
- Nome ao lado do avatar
- E-mail em coluna própria
- Departamentos em badges (ex: `Admin`, `Financeiro`)
- Ações (ícones):
  - Gestão de departamentos (vincular/desvincular depto)
  - Editar (`lápis`)
  - Deletar (`lixeira`)

---

## Regras de negócio (Usuários)

- Apenas `admin` acessa o módulo de cadastros
- Não existe auto-registro público
- Usuário novo é criado por admin com senha temporária
- Vinculação com departamentos/roles respeita regras de RBAC
- Limite de usuários por liberação do admin já possui scaffold técnico, mas ainda não está ativo
- Exclusão usa soft delete (`deleted_at`) com confirmação obrigatória
- Exclusão desativa a conta no Firebase (`disabled=true`) antes do soft delete local
- Admin root não pode ser removido pela UI (regra de segurança)

---

## Rotas

| Método | URI | Controller@method | Middleware |
|---|---|---|---|
| GET | `/admin/cadastros/users` | `Admin\\UsersController@index` | `firebase.auth`, `role:admin` |
| POST | `/admin/cadastros/users` | `Admin\\UsersController@store` | `firebase.auth`, `role:admin` |
| PUT | `/admin/cadastros/users/{user}` | `Admin\\UsersController@update` | `firebase.auth`, `role:admin` |
| DELETE | `/admin/cadastros/users/{user}` | `Admin\\UsersController@destroy` | `firebase.auth`, `role:admin` |
| PUT | `/admin/cadastros/users/{user}/departments` | `Admin\\UsersController@syncDepartments` | `firebase.auth`, `role:admin` |

```php
// routes/web.php
Route::middleware(['firebase.auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('cadastros')->name('cadastros.')->group(function () {
        Route::get('/users', [UsersController::class, 'index'])->name('users.index');
        Route::post('/users', [UsersController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UsersController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UsersController::class, 'destroy'])->name('users.destroy');
        Route::put('/users/{user}/departments', [UsersController::class, 'syncDepartments'])->name('users.syncDepartments');
    });
});
```

---

## Implementação — backend

```php
<?php
// app/Http/Controllers/Admin/UsersController.php

public function index(): Response
{
    return Inertia::render('Admin/Cadastros/Users', [
        'users' => $this->getUsers(),           // consulta real no banco
        'departments' => $this->getDepartments() // select do formulario
    ]);
}

public function store(Request $request): RedirectResponse
{
    // valida username, departamento, email e senha
    // verifica scaffold de limite (ainda sem bloquear)
    // cria no Firebase Auth e persiste em users com id = uid
    return redirect()->route('admin.cadastros.users.index');
}

public function destroy(string $user): RedirectResponse
{
    // bloqueia exclusao do admin root
    // desativa conta no Firebase
    // aplica soft delete em users (deleted_at)
    return redirect()->route('admin.cadastros.users.index');
}
```

---

## Implementação — frontend

### Estrutura de arquivos

```
resources/js/
  Pages/
    Admin/
      Cadastros/
        Users.vue                 ← tela principal de usuários
  Components/
    AppSidebar.vue               ← sidebar principal
    CadastrosSubmenu.vue         ← submenu lateral de cadastros
    UsersTableRow.vue            ← linha da tabela de usuários
```

### `Pages/Admin/Cadastros/Users.vue` — estrutura

```vue
<script setup>
import AppSidebar from '../../../Components/AppSidebar.vue';
import CadastrosSubmenu from '../../../Components/CadastrosSubmenu.vue';
import { ref, computed } from 'vue';

const props = defineProps({
  users: Array,
  departments: Array,
});

const search = ref('');

const filteredUsers = computed(() => {
  if (!search.value) return props.users;
  const q = search.value.toLowerCase();
  return props.users.filter((u) =>
    u.name.toLowerCase().includes(q) ||
    u.email.toLowerCase().includes(q) ||
    u.id.toLowerCase().includes(q)
  );
});
</script>

<template>
  <div class="shell">
    <AppSidebar active="cadastros" />
    <CadastrosSubmenu active="users" />

    <div class="main">
      <header class="page-head">
        <h1>Usuários</h1>
        <div class="head-actions">
          <input v-model="search" placeholder="Localize" />
          <button>Adicionar</button>
        </div>
      </header>

      <!-- tabela users -->
    </div>
  </div>
</template>
```

---

## Design tokens para esta tela

| Token | Valor |
|---|---|
| Sidebar principal | `52px` |
| Submenu cadastros | `220px` |
| Fundo da página | `#f6f7f9` |
| Cor ativa nav | `#FAECE7` + `#993C1D` |
| Título seção | `20px / 600` |
| Header tabela | `12px / 600 / uppercase` |
| Avatar usuário | `32px` (círculo) |

### Ícones de ação (coluna ações)

| Ação | Ícone | Cor sugerida |
|---|---|---|
| Gestão de departamentos | engrenagem / organograma | `#5E6B7A` |
| Editar | lápis | `#1E6AD6` |
| Deletar | lixeira | `#C53030` |

---

## Estados de interface

- **Loading:** skeleton nas linhas da tabela
- **Sem resultados na busca:** mensagem `Nenhum usuário encontrado`
- **Sem usuários cadastrados:** CTA `Adicionar primeiro usuário`
- **Departamentos/Pratos (submenu):** estado vazio com etiqueta `Em breve`

---

## O que NÃO está nesta feature

- CRUD de **Departamentos (roles)** (fase futura)
- CRUD de **Pratos** (fase futura)
- Paginação server-side de usuários
- Upload de foto/avatar do usuário

---

## Status de implementação

- [ ] Ícone formal de cadastros (`book-plus`) aplicado na sidebar
- [ ] Submenu lateral de cadastros abre ao clicar no item da sidebar
- [ ] Item `Usuários` implementado e ativo no submenu
- [ ] Itens `Departamentos` e `Pratos` marcados como `Em breve`
- [x] Cabeçalho da tela com `Usuários`, `Localize` e botão `Adicionar`
- [ ] Tabela com colunas: ID, Nome usuário, E-mail, Departamentos, Ações
- [ ] Linha com avatar redondo + nome do usuário
- [ ] Ações por linha: gestão de departamentos, editar, deletar
- [x] Rotas admin/cadastros/users definidas
- [x] `Admin\\UsersController@index` retornando dados reais do banco
- [x] `Admin\\UsersController@store` criando usuário no Firebase + tabela `users`
- [x] `Admin\\UsersController@destroy` com soft delete + disable no Firebase
- [x] `Admin\\UsersController@update` atualizando Firebase + tabela `users` (nome, e-mail, senha opcional)
- [x] `UserEditPanel.vue` — modal de edição reutilizando `AdminModal.css`
