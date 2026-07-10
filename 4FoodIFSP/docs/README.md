# 4Food — Documentação

> Índice central da documentação do projeto (vault Obsidian + landing de docs no GitHub).
> Stack: Laravel + Inertia.js/Vue 3, MySQL, Firebase Auth, Baileys (WhatsApp).

Comece por [project_overview.md](project_overview.md) — visão técnica de todos os módulos.

---

## 🧭 Visão geral

- [project_overview.md](project_overview.md) — arquitetura, módulos, RBAC, modelos de dados e backlog

## 🗄️ Base de dados

- [database/schema.md](database/schema.md) — tabelas, enums e relações (referência de todas as features)
- [database/diagrama.md](database/diagrama.md) — diagrama ER (Mermaid) gerado do banco atual · imagem: [database/diagrama.png](database/diagrama.png)

## 🚀 Deploy

- [deployment/docker-production.md](deployment/docker-production.md) — playbook de operação com Docker

## 🔐 Infra de auth

- [firebase/init.md](firebase/init.md) — inicialização do Firebase Authentication

---

## 📋 Features (telas e specs)

| Feature | Doc | Role |
|---|---|---|
| Autenticação | [features/auth.md](features/auth.md) | todos |
| Dashboard admin | [features/dashboardAdm.md](features/dashboardAdm.md) | `admin` |
| Cadastros (módulo) | [features/cadastros.md](features/cadastros.md) | `admin` |
| Cadastro de pratos | [features/cadastroPratos.md](features/cadastroPratos.md) | `admin` |
| Departamentos | [features/departamentos.md](features/departamentos.md) | `admin` |
| Pedidos (cozinha) | [features/pedidos.md](features/pedidos.md) | `kitchen` |
| Mesas / Caixa | [features/mesas.md](features/mesas.md) | `finance`, `waiter` |
| Tablet (cliente) | [features/tablet.md](features/tablet.md) | público |
| Atendimento WhatsApp | [features/atendimento.md](features/atendimento.md) | `whatsapp_agent` |
| Chatbot (construtor de fluxo) | [features/chatbot-fluxo.md](features/chatbot-fluxo.md) | `admin` |

---

## 🔁 Fluxos de implementação (`flow/`)

### Autenticação / usuários
- [flow/auth/cadastroUsuario.md](flow/auth/cadastroUsuario.md) — criação de usuário
- [flow/auth/edicaoUsuario.md](flow/auth/edicaoUsuario.md) — edição de dados de login
- [flow/auth/gestaoDepartamentosUsuario.md](flow/auth/gestaoDepartamentosUsuario.md) — gestão de departamentos por usuário

### Departamentos
- [flow/depts/edicaoDepartamento.md](flow/depts/edicaoDepartamento.md) — edição de departamento (cor dinâmica)

### Pratos
- [flow/dishes/criarPrato.md](flow/dishes/criarPrato.md) — criar prato
- [flow/dishes/editarPratos.md](flow/dishes/editarPratos.md) — editar / excluir prato

### Tablet
- [flow/tablets/lanchesVinculados.md](flow/tablets/lanchesVinculados.md) — cardápio real vinculado
- [flow/tablets/pedidoCozinha.md](flow/tablets/pedidoCozinha.md) — envio do pedido à cozinha

### WhatsApp
- [flow/WhatsApp/Conexoes.md](flow/WhatsApp/Conexoes.md) — conexões (Fase 1)
- [flow/WhatsApp/QrCode.md](flow/WhatsApp/QrCode.md) — QR Code / Baileys (Fase 2)
- [flow/WhatsApp/Atendimentos.md](flow/WhatsApp/Atendimentos.md) — inbox real, chat, webhook (Fase 3)
- [flow/WhatsApp/Contatos.md](flow/WhatsApp/Contatos.md) — contatos
- [flow/WhatsApp/fechamento.md](flow/WhatsApp/fechamento.md) — encerramento de ticket

### Chatbot
- [flow/chatBot/motorFase1.md](flow/chatBot/motorFase1.md) — motor (máquina de estados) · handoff para humano · Fase 1

### App celular
- [flow/AppCel/AppCelular.md](flow/AppCel/AppCelular.md) — spec do app mobile

---

## Convenção de links

A documentação usa **links Markdown relativos** (`[texto](caminho.md)`) — funcionam tanto no GitHub quanto no Obsidian (graph view + backlinks). Evite wikilinks `[[ ]]`, que não renderizam no GitHub.
