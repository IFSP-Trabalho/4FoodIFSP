# Spec — Tela de Contatos (WhatsApp)

## Visão Geral

Tela dedicada à listagem e gerenciamento de contatos oriundos do canal WhatsApp.

---

## Inserção Automática de Contatos

- Sempre que um contato enviar uma mensagem pelo WhatsApp, ele deve ser **automaticamente inserido** na lista de contatos, caso ainda não exista (verificação por número).
- Se o contato **já existir**, a mensagem é ignorada silenciosamente — nenhum dado é atualizado.
- O **nome é fixo após a primeira inserção**: não é atualizado mesmo que o pushname mude no WhatsApp.

---

## Listagem de Contatos

### Colunas exibidas

| Coluna | Descrição |
|--------|-----------|
| **Nome** | Pushname capturado no momento da primeira inserção do contato |
| **Número** | Número de telefone do contato |
| **Ações** | Conjunto de ações disponíveis para o contato |

### Paginação

- Exibir no máximo **30 contatos por página**.
- Ao ultrapassar 30 contatos, criar abas paginadas: `1 / 2`, `1 / 3`, etc.
- Setas de navegação para avançar e retroceder entre as páginas.

---

## Ações por Contato

### Abrir Atendimento

- **Verificação:** buscar ticket aberto vinculado ao **LID** do contato.
- **Se não houver ticket aberto:** abre um novo atendimento via chat com o usuário.
- **Se já houver ticket aberto:** redireciona para o ticket mais recente em aberto vinculado ao LID.

### Editar Contato

- Abre a mesma tela/modal de "Adicionar Contato" com os dados pré-preenchidos.

### Menu de 3 pontos

- **Remover contato:**
  - Se houver **ticket aberto** vinculado ao LID do contato → **bloqueia a remoção** e exibe aviso ao usuário informando que não é possível remover enquanto houver atendimento ativo.
  - Se não houver ticket aberto → remove o contato da lista.
- **Histórico de chamados** — item visível porém **desabilitado (cinza)**. *(Implementar depois.)*

---

## Barra Superior (Header)

### Botão Adicionar Contato

- Posicionado no **canto superior direito**, no mesmo padrão visual dos demais botões da plataforma.
- Abre modal de criação de contato (ver seção abaixo).

### Botão de Exportar / Importar (dropdown com setinha)

- Seta ao lado do botão principal exibe opções de **exportar** e **importar** lista de contatos.
- **Estado inicial: inativo** (desabilitado / em breve).

### Botão Recarregar (Reload)

- Ícone de reload ao lado dos botões acima.
- Atualiza a listagem manualmente caso algum contato não apareça salvo na plataforma.

---

## Filtros

Localizado abaixo da barra superior:

- **Input — Nome:** pesquisa por nome do contato.
- **Input — Número:** pesquisa por número de telefone.
- **Botão Limpar:** ao lado dos inputs, limpa os campos e recarrega a listagem completa.

---

## Modal — Adicionar / Editar Contato

### Campos obrigatórios

| Campo | Tipo |
|-------|------|
| Nome | Texto |
| DDD | Numérico (ex: 11) |
| Número | Numérico (sem DDD) |

### Campos opcionais

| Campo | Tipo | Observação |
|-------|------|------------|
| CPF | Texto / numérico formatado | — |
| Observações | Texto livre | Informações relevantes para o atendimento (ex: restrições alimentares, preferências, histórico). Usado pela IA para personalizar as respostas ao contato. |

### Ações do modal

- **Cancelar** — fecha o modal sem salvar.
- **Salvar** — persiste o contato e fecha o modal.
