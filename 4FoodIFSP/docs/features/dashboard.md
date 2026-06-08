# Dashboard — Spec de Funcionalidade

## Visão Geral

O Dashboard é o centro de inteligência operacional do 4Foods. Ele serve como ponto único onde o gerente visualiza o estado completo do restaurante — financeiro, cozinha, mesas e atendimento — em tempo real e com histórico, sem precisar abrir nenhuma outra tela.

Não é um dashboard genérico com cards flutuantes. É um painel de controle com intenção visual clara: **cada dado no lugar certo, com peso visual proporcional à sua importância**. A referência de qualidade visual é o Power BI — denso em informação, mas limpo, profissional e hierarquicamente legível.

**Acesso:** role `admin` exclusivamente.  
**Rota:** `/admin/dashboard`  
**Controller:** `Admin\DashboardController`

---

## Princípios de Design Visual

Este bloco é parte integrante da spec e não deve ser ignorado durante a implementação. O dashboard não pode ser construído como uma grade genérica de cards — cada decisão visual tem uma razão operacional.

### 1. Hierarquia visual rígida

O olho do gerente precisa encontrar a informação mais crítica em menos de dois segundos. Para isso:

- **Hero KPIs** ficam sempre no topo, com tipografia grande e números em destaque
- Indicadores de tendência (Δ%) ficam imediatamente abaixo do número principal, em tamanho menor
- Gráficos ocupam sempre a linha seguinte — são suporte, não protagonistas
- Tabelas e rankings ficam na base de cada bloco — são leitura secundária

### 2. Paleta de cores com significado

Cores não são decoração — comunicam estado:

| Cor | Uso | Token |
|---|---|---|
| `#10B981` (verde esmeralda) | Positivo, pago, concluído, disponível | `--color-success` |
| `#F59E0B` (âmbar) | Atenção, pendente, aguardando | `--color-warning` |
| `#EF4444` (vermelho) | Crítico, cancelado, alerta, triagem sem resposta | `--color-danger` |
| `#6366F1` (índigo) | Primário de interface, ações, links | `--color-primary` |
| `#0EA5E9` (azul céu) | Informativo, delivery, WhatsApp | `--color-info` |
| `#1E293B` (slate 800) | Fundo dos cards | `--color-card` |
| `#0F172A` (slate 900) | Fundo da página | `--color-bg` |

O dashboard usa **tema escuro** (dark mode nativo), que eleva a qualidade visual percebida e é padrão em ferramentas analíticas profissionais como Grafana, Power BI e Metabase no tema escuro.

### 3. Cards com anatomia definida

Todo card de KPI segue a mesma estrutura interna, sem variações:

```
┌─────────────────────────────┐
│  Ícone   Label do KPI       │  ← linha de cabeçalho, texto pequeno, cor neutra
│                             │
│  R$ 3.480,00                │  ← valor principal, fonte bold, grande
│  ▲ 12,4% vs ontem           │  ← tendência, verde/vermelho conforme direção
│                             │
│  ▬▬▬▬▬▬▬▬▬  sparkline      │  ← mini gráfico últimos 7 dias (opcional por card)
└─────────────────────────────┘
```

Cards não têm bordas coloridas laterais (padrão Bootstrap de status). Usam **fundo sólido com sombra sutil** e o ícone no cabeçalho é que carrega a cor semântica.

### 4. Gráficos com identidade visual própria

- Todos os gráficos usam a mesma família de cores e fontes da interface
- Sem legendas flutuantes ou tooltips excessivamente verbosos
- Eixos com mínimo de labels — só o necessário para leitura
- Gráficos de linha usam área preenchida com gradiente (fill transparente abaixo da linha)
- Barras horizontais para rankings (mais fáceis de ler que barras verticais com labels longos)
- Donut charts com valor central em destaque, não legenda lateral

### 5. Espaçamento e grid

- Grid de 12 colunas (Tailwind)
- Padding interno dos cards: `p-6`
- Gap entre cards: `gap-4` ou `gap-6`
- Sem mistura de tamanhos de card na mesma linha — cada linha tem cards do mesmo height
- Separadores visuais entre blocos: linha horizontal sutil (`border-slate-700`) com label de seção em caixa alta, pequeno, espaçado (`tracking-widest text-xs text-slate-500`)

### 6. Estados de dados

Cada área do dashboard precisa lidar com três estados:

- **Carregando:** skeleton loaders com animação de pulse (não spinner global)
- **Vazio:** mensagem contextual dentro do próprio card, sem tela de erro global
- **Erro:** badge vermelho discreto no card afetado, sem quebrar o layout inteiro

---

## Layout Geral da Tela

```
┌─────────────────────────────────────────────────────────────────────────┐
│  [← 4Foods]    Dashboard                    Atualizado às 14:32  [↺]   │
├─────────────────────────────────────────────────────────────────────────┤
│  Filtro de período:  [Hoje]  [7 dias]  [30 dias]  [Personalizado ▾]    │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ── VISÃO GERAL DO DIA ─────────────────────────────────────────────   │
│                                                                         │
│  [Faturamento]  [Ticket Médio]  [Pedidos]  [Mesas Agora]  [WA Triagem] │
│                                                                         │
│  ── FINANCEIRO ─────────────────────────────────────────────────────   │
│                                                                         │
│  [Receita últimos 7 dias — line chart, 8 col]  [Mesa vs Delivery, 4col]│
│  [Pedidos por hora hoje — bar chart, full width]                        │
│                                                                         │
│  ── OPERACIONAL ────────────────────────────────────────────────────   │
│                                                                         │
│  [Status pedidos agora, 5 col]  [Top 5 pratos do dia, 7 col]           │
│  [Cancelados / Prontos aguardando / Horário pico — 3 cards, 4 col each]│
│                                                                         │
│  ── MESAS ──────────────────────────────────────────────────────────   │
│                                                                         │
│  [Grid de mesas mini, 7 col]  [KPIs de giro e ocupação, 5 col]         │
│                                                                         │
│  ── WHATSAPP & DELIVERY ────────────────────────────────────────────   │
│                                                                         │
│  [Funil tickets, 5 col]  [Delivery KPIs, 4 col]  [Receita delivery, 3] │
│                                                                         │
│  ── CARDÁPIO ───────────────────────────────────────────────────────   │
│                                                                         │
│  [Pratos ativos/inativos, 4 col]  [Distribuição por categoria, 8 col]  │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Blocos de Métricas

### Bloco 1 — Visão Geral do Dia (Hero KPIs)

Sempre exibem dados **do dia atual**, independente do filtro de período selecionado. São a primeira leitura do gerente ao abrir o sistema.

#### KPI 1 — Faturamento do Dia

- **Valor:** `SUM(order_items.unit_price * order_items.quantity)` WHERE `orders.paid = true` AND `DATE(orders.created_at) = today`
- **Tendência:** `%Δ` comparado ao mesmo dia da semana anterior (não ontem — evita distorção por dia da semana)
- **Ícone:** cifrão, cor verde
- **Sparkline:** linha dos últimos 7 dias

#### KPI 2 — Ticket Médio

- **Valor:** Faturamento do dia ÷ COUNT(pedidos distintos com `paid=true` hoje)
- **Tendência:** `%Δ` comparado à semana anterior
- **Ícone:** gráfico de linha
- **Nota:** reflete o valor médio por pedido, não por item

#### KPI 3 — Total de Pedidos Hoje

- **Valor:** COUNT(`orders`) WHERE `DATE(created_at) = today` e `status != cancelled`
- **Sub-label:** `X pagos · Y em aberto`
- **Ícone:** receipt/nota fiscal

#### KPI 4 — Mesas Ocupadas Agora

- **Valor:** COUNT(`table_sessions`) WHERE `closed_at IS NULL`
- **Sub-label:** `de N mesas ativas` (ex: "4 de 10 mesas")
- **Indicador visual:** barra de progresso mini dentro do card
- **Ícone:** mesa/chair
- **Atualização:** polling 30s (tempo real)

#### KPI 5 — Tickets WA em Triagem

- **Valor:** COUNT(`wa_tickets`) WHERE `status = 'triage'`
- **Estado de alerta:** se valor > 0, o card muda para fundo âmbar; se > 3, vermelho
- **Sub-label:** `X em atendimento`
- **Ícone:** WhatsApp logo ou balão de chat
- **Atualização:** polling 30s

---

### Bloco 2 — Financeiro

#### Gráfico 1 — Receita dos Últimos N Dias (line chart)

- **Dados:** `SUM(order_items) GROUP BY DATE(orders.created_at)` para o período do filtro
- **Visual:** linha com área preenchida (gradiente de cima para baixo, opacity 0.2)
- **Eixo X:** datas, labels simplificados ("Seg", "Ter" etc. para 7 dias; "01/06", "02/06" para mais)
- **Eixo Y:** valores em BRL formatados (R$ 1.2k, R$ 3.4k)
- **Tooltip ao hover:** data completa + valor exato + comparação com mesmo dia semana anterior
- **Largura:** 8 colunas

#### Gráfico 2 — Distribuição Mesa vs. Delivery (donut chart)

- **Dados:** `SUM(order_items) GROUP BY orders.origin` no período
- **Visual:** donut com valor total no centro
- **Cores:** índigo para mesa, azul céu para delivery
- **Labels:** nome da origem + percentual
- **Largura:** 4 colunas

#### Gráfico 3 — Pedidos por Hora Hoje (bar chart)

- **Dados:** `COUNT(orders) GROUP BY HOUR(created_at)` WHERE `DATE = today`
- **Visual:** barras verticais, cor primária, com destaque na hora de maior volume (barra em cor diferente + label de pico)
- **Eixo X:** horas (00h–23h)
- **Largura:** 12 colunas (full width)
- **Valor:** identifica o horário de pico automaticamente com anotação visual

---

### Bloco 3 — Operacional

#### Painel de Status dos Pedidos Agora (horizontal stacked ou 4 mini-cards)

Exibe a **distribuição ao vivo** dos pedidos do dia por status:

| Status | Query | Cor |
|---|---|---|
| Pendentes | COUNT WHERE `status = 'pending'` e `DATE = today` | âmbar |
| Em preparo | COUNT WHERE `status = 'in_progress'` e `DATE = today` | índigo |
| Prontos | COUNT WHERE `status = 'ready'` e `DATE = today` | verde |
| Cancelados | COUNT WHERE `status = 'cancelled'` e `DATE = today` | vermelho |

- **Visual:** 4 mini-cards em linha com número grande e label, sem gráfico
- **Atualização:** polling 30s
- **Largura:** 5 colunas

#### Tabela — Top 5 Pratos do Dia

- **Dados:** `SUM(order_items.quantity) GROUP BY dish_id ORDER BY total DESC LIMIT 5` WHERE `DATE = hoje`
- **Colunas:** Posição (1–5 com medalha nos 3 primeiros), Nome do Prato, Qtd Vendida, Receita Gerada
- **Visual:** tabela sem bordas externas, linhas alternadas em sutil diferença de fundo, linha 1 com destaque leve
- **Largura:** 7 colunas

#### 3 KPI Cards secundários

| Card | Query | Por quê |
|---|---|---|
| Prontos aguardando retirada | COUNT `status=ready` AND `paid=false` hoje | Gargalo entre cozinha e garçom |
| Cancelados hoje | COUNT `status=cancelled` AND `DATE=today` | Alarme de problema operacional |
| Horário de pico | HOUR mais frequente do dia | Info de gestão de equipe |

---

### Bloco 4 — Mesas

#### Grid Visual de Mesas

- **Dados:** todas as mesas ativas com seu estado atual (`table_sessions` com `closed_at IS NULL`)
- **Visual:** grade de cards pequenos, um por mesa, com número central
  - Disponível: fundo verde escuro + número claro
  - Ocupada: fundo índigo + número claro + tempo de sessão abaixo (ex: "01:23")
  - Inativa: fundo cinza escuro + ícone de bloqueio
- **Largura:** 7 colunas
- **Atualização:** polling 30s

#### KPIs de Mesas (coluna direita)

| KPI | Query |
|---|---|
| Taxa de ocupação | `(mesas ocupadas / mesas ativas) * 100` |
| Tempo médio de sessão hoje | `AVG(closed_at - started_at)` WHERE `DATE(closed_at) = today` |
| Sessões encerradas hoje | COUNT `table_sessions` WHERE `DATE(closed_at) = today` |
| Giro de mesas | Sessões encerradas ÷ total de mesas ativas |

- **Visual:** 4 mini-cards empilhados verticalmente
- **Largura:** 5 colunas

---

### Bloco 5 — WhatsApp & Delivery

#### Funil de Tickets

- **Dados:** COUNT por `wa_tickets.status` (triage, in_progress, closed)
- **Visual:** 3 cards em sequência com seta entre eles (funil horizontal)
  - Triagem → Atendimento → Encerrados hoje
- **Alerta:** se triagem > 3, exibir badge vermelho pulsante no card
- **Largura:** 5 colunas

#### KPIs de Delivery

| KPI | Query |
|---|---|
| Pedidos delivery hoje | COUNT `orders.origin = 'delivery'` AND `DATE = today` |
| Receita delivery hoje | SUM `order_items` WHERE `origin = 'delivery'` AND `DATE = today` |
| Ticket médio delivery | Receita ÷ COUNT pedidos delivery |

- **Largura:** 4 colunas

#### Receita Delivery Histórica

- **Dados:** `SUM(order_items) GROUP BY DATE` WHERE `origin = 'delivery'` no período do filtro
- **Visual:** bar chart simples, uma barra por dia
- **Largura:** 3 colunas

---

### Bloco 6 — Cardápio

#### KPIs do Cardápio

| KPI | Query |
|---|---|
| Pratos ativos | COUNT `dishes` WHERE `active = true` |
| Pratos inativos | COUNT `dishes` WHERE `active = false` |

- **Visual:** 2 mini-cards side-by-side, verde e cinza respectivamente
- **Largura:** 4 colunas

#### Distribuição por Categoria (bar chart horizontal)

- **Dados:** COUNT `dishes GROUP BY dish_categories.name` WHERE `active = true`
- **Visual:** barras horizontais, uma por categoria, ordenadas por quantidade decrescente
- **Labels:** nome da categoria + contagem à direita da barra
- **Largura:** 8 colunas

---

## Comportamento do Filtro de Período

O seletor de período no topo controla os gráficos históricos e rankings. Os Hero KPIs do Bloco 1 são sempre do dia atual e **não respondem ao filtro**.

| Opção | Comportamento |
|---|---|
| Hoje | DATE = today (padrão ao abrir) |
| 7 dias | DATE BETWEEN today-6 AND today |
| 30 dias | DATE BETWEEN today-29 AND today |
| Personalizado | Date picker com range inicio/fim |

---

## Atualização de Dados

| Tipo | Estratégia | Frequência |
|---|---|---|
| Hero KPIs operacionais (mesas, pedidos, WA) | Polling via Axios | 30 segundos |
| KPIs financeiros | Request no load + ao mudar filtro | Sob demanda |
| Gráficos históricos | Request no load + ao mudar filtro | Sob demanda |
| Timestamp "Atualizado às HH:MM" | Atualiza no último polling bem-sucedido | Automático |

Quando disponível (fase 2 com Broadcasting), as métricas operacionais migram de polling para eventos: `OrderCreated`, `OrderStatusUpdated`, `TableClosed`.

---

## Queries do Controller

Cada bloco tem seu próprio método no controller — sem query monolítica:

```
DashboardController
  ├── heroKpis()         → Bloco 1
  ├── financialSummary() → Bloco 2 gráficos e KPIs financeiros
  ├── operationalStatus()→ Bloco 3 status pedidos e top pratos
  ├── tablesStatus()     → Bloco 4 mesas e sessões
  ├── whatsappSummary()  → Bloco 5 tickets e delivery
  └── menuSummary()      → Bloco 6 cardápio
```

O método `heroKpis()` é chamado no polling de 30s. Os demais são chamados no carregamento da página e ao mudar o filtro de período.

---

## Referências Visuais

A implementação deve ter como referência de qualidade visual:

- **Power BI** (dark theme): densidade de informação, hierarquia tipográfica, gráficos com área preenchida
- **Grafana** (dark theme): grid limpo, separação clara entre painéis, estados de dado bem tratados
- **Linear Analytics**: cards com tendência, espaçamento generoso, sem excesso de bordas

O dashboard **não deve parecer** um painel feito com componentes de UI library sem customização. Cada elemento precisa ter identidade visual consistente com a paleta e tipografia do 4Foods.

---

## Fase de Implementação

| Fase | Escopo |
|---|---|
| **MVP** | Hero KPIs com dados reais, gráfico de receita 7 dias, status operacional com polling |
| **Fase 2** | Todos os blocos completos, filtro de período funcional, grid de mesas ao vivo |
| **Fase 3** | Broadcasting substituindo polling, top pratos por prato mais lucrativo, export PDF |
