# Database: Schema e Migrations

> Stack: MySQL ou PostgreSQL  
> Convenção: snake_case, timestamps em todas as tabelas  
> Autenticação: Firebase Authentication — `users.id` é o `firebaseUid` (string), não um UUID gerado pelo banco  
> Seed obrigatório: departamentos e usuário admin

---

## Ordem de criação das migrations

Siga essa ordem para evitar erros de FK:

```
1. departments
2. users
3. dish_categories
4. dishes
5. tables
6. wa_tickets
7. orders
8. order_items
9. wa_messages leve
```

---

## Tabelas

---

### `departments`

Pré-populada via seeder. Sem interface de CRUD.

```php
Schema::create('departments', function (Blueprint $table) {
    $table->string('id')->primary();      // Str::uuid() gerado manualmente no seeder
    $table->string('name');               // "Cozinha", "Financeiro", "Admin", "Garçom"
    $table->string('slug')->unique();     // "kitchen", "finance", "admin", "waiter"
    $table->string('color', 7);           // hex "#RRGGBB" — editável pelo admin
    $table->timestamps();
});
```

**Seed obrigatório:**
```php
Department::insert([
    ['id' => Str::uuid(), 'name' => 'Admin',      'slug' => 'admin'],
    ['id' => Str::uuid(), 'name' => 'Cozinha',    'slug' => 'kitchen'],
    ['id' => Str::uuid(), 'name' => 'Financeiro', 'slug' => 'finance'],
    ['id' => Str::uuid(), 'name' => 'Garçom',     'slug' => 'waiter'],
]);
```

---

### `users`

Criados pelo admin. Sem registro público.  
A PK **não é um UUID gerado pelo banco** — é o `uid` retornado pelo Firebase Authentication após o admin criar o usuário via Firebase Admin SDK. O Laravel não gerencia senha, login ou sessão tradicional.

```php
Schema::create('users', function (Blueprint $table) {
    $table->string('id')->primary();               // firebaseUid — ex: "abc123XYZ..."
    $table->string('name');
    $table->string('email')->unique();
    $table->string('role');                        // enum manual: admin|kitchen|finance|waiter|whatsapp_agent
    $table->string('department_id');               // FK para departments (string, não uuid)
    $table->foreign('department_id')
          ->references('id')
          ->on('departments')
          ->restrictOnDelete();
    $table->boolean('must_reset_password')
          ->default(true);                         // true no primeiro login
    $table->timestamps();
});
```

> **Sem `password` e sem `rememberToken`** — a autenticação é 100% via Firebase.  
> O Laravel recebe o `idToken` do Firebase no frontend, valida via Firebase Admin SDK  
> (ou pacote `kreait/laravel-firebase`) e identifica o usuário pelo `uid`.

**Seed — admin padrão:**
```php
// O firebaseUid do admin é criado manualmente no Firebase Console
// e então registrado aqui como seed
User::create([
    'id'                  => env('ADMIN_FIREBASE_UID'), // definido no .env
    'name'                => 'Administrador',
    'email'               => 'admin@restaurante.com',
    'role'                => 'admin',
    'department_id'       => Department::where('slug', 'admin')->first()->id,
    'must_reset_password' => false,
]);
```

**Fluxo de criação de usuário pelo admin:**
1. Admin preenche o formulário (nome, e-mail, departamento)
2. Backend chama `Firebase::auth()->createUser([...])` → recebe `uid`
3. Backend salva o registro em `users` com `id = uid`
4. Firebase envia e-mail de definição de senha para o novo usuário
5. No primeiro login, `must_reset_password = true` intercepta e força troca

**Índices:**
- `email` — já unique
- `role` — índice simples (filtra por role nas queries de RBAC)
- `department_id` — FK

---

### `dish_categories`

```php
Schema::create('dish_categories', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');           // "Pratos Principais", "Bebidas", "Sobremesas"
    $table->string('slug')->unique(); // "main_course", "drinks", "desserts"
    $table->timestamps();
});
```

**Seed sugerido:**
```php
DishCategory::insert([
    ['id' => Str::uuid(), 'name' => 'Pratos Principais', 'slug' => 'main_course'],
    ['id' => Str::uuid(), 'name' => 'Bebidas',           'slug' => 'drinks'],
    ['id' => Str::uuid(), 'name' => 'Sobremesas',        'slug' => 'desserts'],
]);
```

---

### `dishes`

```php
Schema::create('dishes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->text('description')->nullable();
    $table->decimal('price', 8, 2);
    $table->string('photo_path')->nullable();      // caminho no storage
    $table->foreignUuid('category_id')
          ->constrained('dish_categories')
          ->restrictOnDelete();
    $table->boolean('active')->default(true);      // soft disable sem deletar
    $table->timestamps();
});
```

**Índices:**
- `category_id` — FK padrão
- `active` — filtra pratos disponíveis no tablet

---

### `tables`

Representa as mesas físicas do restaurante.  
Tablet N → mesa N (configurado via variável de ambiente no tablet).

```php
Schema::create('tables', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->unsignedInteger('number')->unique(); // 1, 2, 3...
    $table->string('label')->nullable();         // "Mesa VIP", "Varanda 1"
    $table->timestamps();
});
```

**Seed sugerido:** criar as mesas 1 a 10 por padrão.

---

### `wa_tickets`

Gerado via webhook do WhatsApp. Um ticket = uma conversa com um cliente.

```php
Schema::create('wa_tickets', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('phone_number');               // número do cliente no formato E.164
    $table->string('customer_name')->nullable();
    $table->enum('status', ['triage', 'in_progress', 'closed'])
          ->default('triage');
    $table->string('agent_id')                // firebaseUid do atendente que assumiu
          ->nullable()
          ->index();
    $table->foreign('agent_id')
          ->references('id')
          ->on('users')
          ->nullOnDelete();
    $table->timestamps();
});
```

**Índices:**
- `status` — filtra inbox por aba
- `phone_number` — busca ticket existente no webhook (evita duplicatas)

---

### `orders`

Núcleo do sistema. Criado pelo tablet (presencial) ou pelo módulo delivery/WhatsApp.

```php
Schema::create('orders', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('table_id')
          ->nullable()                            // nullable para pedidos delivery sem mesa
          ->constrained()
          ->restrictOnDelete();
    $table->enum('origin', ['table', 'delivery'])
          ->default('table');
    $table->enum('status', ['pending', 'in_progress', 'ready', 'cancelled'])
          ->default('pending');
    $table->boolean('paid')->default(false);
    $table->foreignUuid('wa_ticket_id')           // nullable: só pedidos vindos do WA
          ->nullable()
          ->constrained()
          ->nullOnDelete();
    $table->string('customer_name')->nullable();  // usado em pedidos delivery
    $table->string('delivery_address')->nullable();
    $table->timestamps();
});
```

**Índices:**
- `table_id` — agrupar pedidos por mesa (caixa)
- `status` — filtra cozinha (in_progress) e histórico
- `paid` — calcula total em aberto por mesa
- `origin` — separa fluxo presencial/delivery
- Composto `[table_id, paid]` — query frequente: "total em aberto da mesa X"

---

### `order_items`

Itens individuais de cada pedido. Guarda o `unit_price` no momento do pedido  
(não referencia o preço atual do prato — preço pode mudar depois).

```php
Schema::create('order_items', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('order_id')
          ->constrained()
          ->cascadeOnDelete();
    $table->foreignUuid('dish_id')
          ->constrained()
          ->restrictOnDelete();
    $table->unsignedSmallInteger('quantity')->default(1);
    $table->decimal('unit_price', 8, 2);         // snapshot do preço no momento do pedido
    $table->text('note')->nullable();            // "Sem picles", "Ponto mal passado"
    $table->timestamps();
});
```

**Índices:**
- `order_id` — FK + listagem de itens por pedido

---

### `wa_messages`

Mensagens trocadas dentro de um ticket WhatsApp.

```php
Schema::create('wa_messages', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('wa_ticket_id')
          ->constrained()
          ->cascadeOnDelete();
    $table->enum('direction', ['inbound', 'outbound']);
    $table->text('body');
    $table->string('wa_message_id')->nullable();  // ID da mensagem na API do WhatsApp
    $table->timestamp('sent_at')->nullable();     // timestamp da API, não do servidor
    $table->timestamps();
});
```

**Índices:**
- `wa_ticket_id` — carrega histórico de mensagens do ticket
- `wa_message_id` — unique (evita duplicatas de webhook)

---

### `wa_connections`

Canais WhatsApp configurados pelo admin. Cada registro representa uma instância WhatsApp (ex.: "Comercial 1").

```php
Schema::create('wa_connections', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name')->unique();
    $table->string('channel_type')->default('whatsapp');   // extensível para outros canais
    $table->string('phone_number')->nullable();            // E.164 — preenchido após pareamento Baileys (Fase 2)
    $table->string('connection_status')->default('disconnected'); // connected|disconnected|pairing
    $table->timestamp('last_status_at')->nullable();
    $table->string('baileys_session_id')->nullable();      // ID da sessão no serviço Node (Fase 2)
    $table->timestamps();
});
```

**Índices / constraints:**

| Item | Regra |
|------|-------|
| `name` | `unique()` — evita duplicata no MVP single-tenant |
| `connection_status` | Valores válidos: `connected`, `disconnected`, `pairing` |
| `channel_type` | Valores válidos: `whatsapp` (extensível) |

**Valores de `connection_status`:**

| Valor | Significado |
|-------|-------------|
| `disconnected` | Sem sessão ativa; aguardando QR Code |
| `pairing` | QR Code gerado, aguardando scan (Fase 2) |
| `connected` | Sessão WhatsApp ativa no Baileys (Fase 2) |

---

## Relacionamentos (resumo)

```
users           N:1   departments
dishes          N:1   dish_categories
orders          N:1   tables
orders          1:N   order_items
order_items     N:1   dishes
orders          N:1   wa_tickets      (nullable)
wa_messages     N:1   wa_tickets
wa_tickets      N:1   users           (agent, nullable)
```

---

## Enums — valores válidos

| Tabela | Campo | Valores |
|---|---|---|
| `users` | `role` | `admin`, `kitchen`, `finance`, `waiter`, `whatsapp_agent` |
| `orders` | `origin` | `table`, `delivery` |
| `orders` | `status` | `pending`, `in_progress`, `ready`, `cancelled` |
| `wa_tickets` | `status` | `triage`, `in_progress`, `closed` |
| `wa_messages` | `direction` | `inbound`, `outbound` |

> Dica de implementação: use `enum` no MySQL ou `check constraint` no PostgreSQL.  
> No Laravel, pode usar cast para `Enum` nativo do PHP 8.1+ nos models.

---

## Regras de integridade importantes

- `order_items.unit_price` deve ser sempre populado com `dishes.price` no momento da criação — nunca referenciar o preço atual em tempo de exibição
- `orders.table_id` pode ser `null` apenas se `origin = 'delivery'`
- `orders.wa_ticket_id` só é preenchido se `origin = 'delivery'` via WhatsApp
- Um `wa_ticket` com `status = 'closed'` não deve aceitar novas mensagens (validação na camada de serviço)
- `users.must_reset_password = true` deve interceptar todas as rotas exceto `/password/change`

---

## Queries mais frequentes (para otimizar índices)

```sql
-- Cozinha: pedidos em preparo
SELECT * FROM orders WHERE status = 'in_progress' ORDER BY created_at ASC;

-- Caixa: total em aberto por mesa
SELECT table_id, SUM(oi.quantity * oi.unit_price) as total
FROM orders o
JOIN order_items oi ON oi.order_id = o.id
WHERE o.paid = false AND o.status != 'cancelled'
GROUP BY table_id;

-- Histórico com filtro de data
SELECT * FROM orders
WHERE status IN ('ready','cancelled')
AND created_at BETWEEN :date_from AND :date_to
ORDER BY created_at DESC;

-- WhatsApp: inbox por status
SELECT * FROM wa_tickets WHERE status = 'triage' ORDER BY created_at ASC;
```

---

---

## ADR: Autenticação via Firebase

**Decisão:** `users.id` é o `firebaseUid` (string) retornado pelo Firebase Authentication, não um UUID gerado pelo banco.

**Consequências:**
- Sem `password`, `remember_token` ou lógica de sessão no Laravel
- O Laravel valida o `idToken` JWT enviado pelo frontend via Firebase Admin SDK (`kreait/laravel-firebase`)
- Middleware customizado (`FirebaseAuthMiddleware`) substitui o `auth` padrão do Laravel
- O `HasUuids` trait **não deve ser usado** no model `User`
- Todas as FKs que referenciam `users.id` usam `string` em vez de `uuid`

**Pacote recomendado:** `kreait/laravel-firebase`

```php
// Exemplo de middleware de autenticação
public function handle(Request $request, Closure $next): Response
{
    $idToken = $request->bearerToken();
    $verifiedToken = Firebase::auth()->verifyIdToken($idToken);
    $uid = $verifiedToken->claims()->get('sub');

    $user = User::findOrFail($uid);
    Auth::setUser($user);

    return $next($request);
}
```

**Alternativas descartadas:**
- Laravel Sanctum — descartado porque a gestão de identidade fica duplicada (Firebase já faz isso)
- UUID gerado pelo banco como PK — descartado porque criaria dessincronização entre o banco e o Firebase


## Status de implementação

- [ ] Migration `departments` criada
- [ ] Migration `users` criada (sem password, sem rememberToken)
- [ ] Migration `dish_categories` criada
- [ ] Migration `dishes` criada
- [ ] Migration `tables` criada
- [ ] Migration `wa_tickets` criada
- [ ] Migration `orders` criada
- [ ] Migration `order_items` criada
- [ ] Migration `wa_messages` criada
- [ ] Seeder `DepartmentSeeder` criado
- [ ] Seeder `AdminUserSeeder` criado (requer `ADMIN_FIREBASE_UID` no `.env`)
- [ ] Seeder `DishCategorySeeder` criado
- [ ] Models com relacionamentos Eloquent criados
- [ ] Enums PHP 8.1 criados para status
- [ ] `kreait/laravel-firebase` instalado e configurado
- [ ] `FirebaseAuthMiddleware` criado e registrado