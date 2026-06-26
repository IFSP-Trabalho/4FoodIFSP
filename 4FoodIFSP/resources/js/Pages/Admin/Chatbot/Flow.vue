<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { VueFlow, useVueFlow, Position } from '@vue-flow/core';
import { Background }                    from '@vue-flow/background';
import { Controls }                      from '@vue-flow/controls';
import { MiniMap }                       from '@vue-flow/minimap';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';
import '@vue-flow/minimap/dist/style.css';

import StartNode   from '../../../Components/FlowBuilder/nodes/StartNode.vue';
import MessageNode from '../../../Components/FlowBuilder/nodes/MessageNode.vue';
import ActionNode  from '../../../Components/FlowBuilder/nodes/ActionNode.vue';
import ConfigNode  from '../../../Components/FlowBuilder/nodes/ConfigNode.vue';

const props = defineProps({
    flow:  { type: Object, required: true },
    nodes: { type: Array,  default: () => [] },
    edges: { type: Array,  default: () => [] },
});

// ── Utils ─────────────────────────────────────────────────────────────────────
function uid() {
    return (typeof crypto !== 'undefined' && crypto.randomUUID)
        ? `temp-${crypto.randomUUID()}`
        : `temp-${Math.random().toString(36).slice(2)}${Date.now().toString(36)}`;
}

function parsePayload(raw) {
    if (!raw) return {};
    if (typeof raw === 'object') return raw;
    try { return JSON.parse(raw); } catch { return {}; }
}

// Garante a lista de interações do nó (migra de um data.text antigo).
function ensureInteractions(node) {
    if (!node || !node.data) return;
    if (!Array.isArray(node.data.interactions)) {
        node.data.interactions = node.data.text
            ? [{ id: uid(), type: 'text', text: node.data.text }]
            : [{ id: uid(), type: 'text', text: '' }];
    }
}

// ── Nós fixos (sempre presentes, nunca salvos no banco como config) ───────────
const FIXED_CONFIG_NODE = {
    id:        'config-fixed',
    type:      'configurations',
    position:  { x: 60, y: 40 },
    data:      {},
    draggable: true,
};

const DEFAULT_START_NODE = {
    id:       'start-default',
    type:     'start',
    position: { x: 80, y: 200 },
    data:     { name: 'Início' },
};

// Boas-vindas: primeira mensagem enviada após o contato. Etapa inicial
// obrigatória, sempre ligada ao Início e não removível (marcada por role).
const DEFAULT_WELCOME_NODE = {
    id:       'welcome-default',
    type:     'message',
    position: { x: 300, y: 200 },
    data:     { name: 'Boas-vindas', role: 'welcome', interactions: [{ id: uid(), type: 'text', text: '' }] },
};

const LOCKED_TYPES = new Set(['start', 'configurations']);

function isWelcome(node) {
    return node?.data?.role === 'welcome';
}

function isLockedNode(node) {
    return !node || LOCKED_TYPES.has(node.type) || isWelcome(node);
}

// DB nodes → VueFlow.
const dbNodes = props.nodes.map(n => ({
    id:       String(n.id),
    type:     n.type,
    position: { x: n.position_x ?? 120, y: n.position_y ?? 120 },
    data:     parsePayload(n.payload),
}));

dbNodes.forEach(n => {
    if (n.type === 'message' || n.type === 'action') ensureInteractions(n);
});

// Etapa inicial obrigatória 1 — Início (ponto de entrada, não faz nada).
let startNode = dbNodes.find(n => n.type === 'start');
if (!startNode) {
    startNode = DEFAULT_START_NODE;
    dbNodes.unshift(startNode);
}

// Etapa inicial obrigatória 2 — Boas-vindas (primeira mensagem do chatbot).
let welcomeNode = dbNodes.find(isWelcome);
const welcomeInjected = !welcomeNode;
if (!welcomeNode) {
    welcomeNode = DEFAULT_WELCOME_NODE;
    dbNodes.push(welcomeNode);
}

// Config node é sempre injetado no front — nunca vem do banco
const initNodes = [...dbNodes, FIXED_CONFIG_NODE];

// Cada condição pode ter várias respostas → no banco vira 1 aresta por resposta,
// agrupadas pela coluna `label` (id do grupo). Aqui reagrupamos numa só aresta.
const edgeGroups = new Map();
props.edges.forEach(e => {
    const key = e.label ? `g:${e.label}` : `e:${e.id}`;
    if (!edgeGroups.has(key)) {
        edgeGroups.set(key, {
            id:        uid(),
            source:    String(e.from_node_id),
            target:    String(e.to_node_id),
            group:     e.label || null,
            responses: [],
        });
    }
    const g = edgeGroups.get(key);
    if (e.match_value !== null && e.match_value !== undefined && e.match_value !== '') {
        g.responses.push(String(e.match_value));
    }
});

const initEdges = [...edgeGroups.values()].map(g => ({
    id:     g.id,
    source: g.source,
    target: g.target,
    label:  g.responses.join(', '),
    data:   {
        match_type: g.responses.length ? 'responses' : 'any',
        responses:  g.responses,
        group:      g.group,
    },
    type:   'smoothstep',
}));

// Ligação obrigatória Início → Boas-vindas (reposta caso não exista).
const hasStartToWelcome = initEdges.some(
    e => e.source === startNode.id && e.target === welcomeNode.id,
);
if (!hasStartToWelcome) {
    initEdges.push({
        id:     uid(),
        source: startNode.id,
        target: welcomeNode.id,
        label:  '',
        data:   { match_type: 'any', responses: [], group: uid() },
        type:   'smoothstep',
    });
}

// ── VueFlow ───────────────────────────────────────────────────────────────────
const {
    nodes,
    edges,
    addNodes,
    addEdges,
    removeNodes,
    removeEdges,
    setNodes,
    setEdges,
    findNode,
    findEdge,
} = useVueFlow({ nodes: initNodes, edges: initEdges });

const nodeTypes = {
    start:          StartNode,
    message:        MessageNode,
    action:         ActionNode,
    configurations: ConfigNode,
};

// ── Histórico (desfazer / refazer) ────────────────────────────────────────────
const past   = ref([]);
const future = ref([]);
const MAX_HISTORY = 50;

function snapNode(n) {
    return {
        id:       n.id,
        type:     n.type,
        position: { x: n.position.x, y: n.position.y },
        data:     JSON.parse(JSON.stringify(n.data ?? {})),
    };
}

function snapEdge(e) {
    return {
        id:     e.id,
        source: e.source,
        target: e.target,
        label:  e.label ?? '',
        data:   JSON.parse(JSON.stringify(e.data ?? {})),
        type:   e.type ?? 'smoothstep',
    };
}

function snapshot() {
    return {
        nodes: nodes.value.map(snapNode),
        edges: edges.value.map(snapEdge),
    };
}

function applySnapshot(snap) {
    setNodes(snap.nodes);
    setEdges(snap.edges);
    selectedNodeId.value = null;
}

// Chamado ANTES de uma alteração: registra o estado e invalida o "refazer".
function pushHistory() {
    past.value.push(snapshot());
    if (past.value.length > MAX_HISTORY) past.value.shift();
    future.value = [];
}

const canUndo = computed(() => past.value.length > 0);
const canRedo = computed(() => future.value.length > 0);

function undo() {
    if (!past.value.length) return;
    future.value.push(snapshot());
    applySnapshot(past.value.pop());
}

function redo() {
    if (!future.value.length) return;
    past.value.push(snapshot());
    applySnapshot(future.value.pop());
}

// ── Selected node ─────────────────────────────────────────────────────────────
// Em fluxo novo, abre o painel da etapa Boas-vindas por padrão
const selectedNodeId = ref(welcomeInjected ? welcomeNode.id : null);
const panelTab = ref('interacoes'); // 'interacoes' | 'condicoes'

const selectedNode = computed(() =>
    selectedNodeId.value ? findNode(selectedNodeId.value) : null
);

const interactions = computed(() => selectedNode.value?.data?.interactions ?? []);

const selectedEdges = computed(() =>
    edges.value.filter(e => e.source === selectedNodeId.value)
);

const otherNodes = computed(() =>
    nodes.value.filter(n => n.id !== selectedNodeId.value)
);

// Destinos válidos para rotear uma condição: exclui o próprio nó, Config,
// Boas-vindas, Início e qualquer nó que ALCANCE o atual (impede loop/reconexão
// entre 2 etapas).
const routeTargets = computed(() => {
    const selId = selectedNodeId.value;
    if (!selId) return [];

    // Ancestrais: nós a partir dos quais dá pra chegar no nó atual.
    const ancestors = new Set();
    const stack = [selId];
    while (stack.length) {
        const cur = stack.pop();
        edges.value.forEach(e => {
            if (e.target === cur && !ancestors.has(e.source)) {
                ancestors.add(e.source);
                stack.push(e.source);
            }
        });
    }

    return nodes.value.filter(n =>
        n.id !== selId &&
        n.type !== 'configurations' &&
        n.type !== 'start' &&
        !isWelcome(n) &&
        !ancestors.has(n.id)
    );
});

function onNodeClick({ node }) {
    selectedNodeId.value = node.id;
    panelTab.value = 'interacoes';
    ensureInteractions(findNode(node.id));
}

// ── Interações (mensagens que o chatbot envia na etapa) ───────────────────────
function addInteraction(type = 'text') {
    if (!selectedNode.value) return;
    pushHistory();
    ensureInteractions(selectedNode.value);
    selectedNode.value.data.interactions.push({ id: uid(), type, text: '' });
}

function removeInteraction(index) {
    const list = selectedNode.value?.data?.interactions;
    if (!list) return;
    pushHistory();
    list.splice(index, 1);
}

function moveInteraction(index, dir) {
    const list = selectedNode.value?.data?.interactions;
    if (!list) return;
    const target = index + dir;
    if (target < 0 || target >= list.length) return;
    pushHistory();
    const [item] = list.splice(index, 1);
    list.splice(target, 0, item);
}

function onPaneClick() {
    selectedNodeId.value = null;
}

// ── Node actions ──────────────────────────────────────────────────────────────
function addNode() {
    pushHistory();
    const id = uid();
    addNodes([{
        id,
        type:     'message',
        position: { x: 200 + nodes.value.length * 30, y: 200 },
        data:     { name: 'Nova etapa', action: null, interactions: [{ id: uid(), type: 'text', text: '' }] },
    }]);
    selectedNodeId.value = id;
    panelTab.value = 'interacoes';
}

function removeSelectedNode() {
    const node = selectedNode.value;
    if (isLockedNode(node)) return;
    if (!confirm(`Remover o nó "${node.data?.name || node.type}"?`)) return;
    pushHistory();
    removeNodes([node.id]);
    selectedNodeId.value = null;
}

// ── Edge actions ──────────────────────────────────────────────────────────────
function addEdge() {
    if (!selectedNodeId.value) return;
    pushHistory();
    addEdges([{
        id:     uid(),
        source: selectedNodeId.value,
        target: routeTargets.value[0]?.id ?? '',
        label:  '',
        data:   { match_type: 'responses', responses: [], group: uid() },
        type:   'smoothstep',
    }]);
}

function setMatchType(edge, value) {
    pushHistory();
    edge.data = { ...edge.data, match_type: value };
    if (value === 'responses' && !Array.isArray(edge.data.responses)) {
        edge.data.responses = [];
    }
    edge.label = value === 'responses' ? (edge.data.responses || []).join(', ') : '';
}

function addResponse(edge, value) {
    const v = (value || '').trim();
    if (!v) return;
    pushHistory();
    if (!Array.isArray(edge.data.responses)) {
        edge.data = { ...edge.data, responses: [], match_type: 'responses' };
    }
    if (!edge.data.responses.includes(v)) {
        edge.data.responses.push(v);
        edge.label = edge.data.responses.join(', ');
    }
}

function removeResponse(edge, index) {
    if (!Array.isArray(edge.data?.responses)) return;
    pushHistory();
    edge.data.responses.splice(index, 1);
    edge.label = edge.data.responses.join(', ');
}

function removeEdge(edgeId) {
    pushHistory();
    removeEdges([edgeId]);
}

function moveCondition(index, dir) {
    const list = selectedEdges.value;
    const target = index + dir;
    if (target < 0 || target >= list.length) return;
    pushHistory();

    const reordered = edges.value.map(e => ({
        id:     e.id,
        source: e.source,
        target: e.target,
        label:  e.label ?? '',
        data:   { ...(e.data || {}) },
        type:   e.type ?? 'smoothstep',
    }));

    const ia = reordered.findIndex(e => e.id === list[index].id);
    const ib = reordered.findIndex(e => e.id === list[target].id);
    if (ia < 0 || ib < 0) return;

    [reordered[ia], reordered[ib]] = [reordered[ib], reordered[ia]];
    setEdges(reordered);
}

function updateEdgeMatchValue(edgeId, value) {
    const edge = findEdge(edgeId);
    if (!edge) return;
    edge.data = { match_value: value };
    edge.label = value;
}

function updateEdgeTarget(edgeId, target) {
    const edge = findEdge(edgeId);
    if (!edge) return;
    pushHistory();
    edge.target = target;
}

// ── Atalho: DEL apaga o nó/aresta selecionado no canvas ───────────────────────
function onKeydown(event) {
    if (event.key !== 'Delete') return;

    // Não interfere quando o usuário está digitando num campo.
    const el     = event.target;
    const tag    = el?.tagName;
    const typing = el?.isContentEditable || tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
    if (typing) return;

    // Nós travados (Início, Boas-vindas, Config) nunca são apagados.
    const nodesToRemove = nodes.value.filter(n => n.selected && !isLockedNode(n));
    const edgesToRemove = edges.value.filter(e => e.selected);

    if (!nodesToRemove.length && !edgesToRemove.length) return;

    event.preventDefault();
    pushHistory();

    if (nodesToRemove.length) {
        const ids = nodesToRemove.map(n => n.id);
        removeNodes(ids);
        if (ids.includes(selectedNodeId.value)) selectedNodeId.value = null;
    }

    if (edgesToRemove.length) {
        removeEdges(edgesToRemove.map(e => e.id));
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

// ── Save ──────────────────────────────────────────────────────────────────────
const saveForm = useForm({ nodes: [], edges: [] });

function saveFlow() {
    // config-fixed nunca vai para o banco (é frontend-only)
    saveForm.nodes = nodes.value
        .filter(n => n.id !== FIXED_CONFIG_NODE.id)
        .map(n => {
            const data = { ...n.data };
            // O motor lê payload.text — junta as interações em uma mensagem.
            if (Array.isArray(data.interactions)) {
                data.text = data.interactions
                    .map(i => (i.text || '').trim())
                    .filter(Boolean)
                    .join('\n\n');
            }
            return {
                id:         n.id,
                type:       n.type,
                payload:    data,
                position_x: Math.round(n.position.x),
                position_y: Math.round(n.position.y),
            };
        });

    const outEdges = [];
    edges.value
        .filter(e => e.source && e.target && e.source !== e.target
                  && e.source !== FIXED_CONFIG_NODE.id
                  && e.target !== FIXED_CONFIG_NODE.id)
        .forEach(e => {
            const group = e.data?.group || e.id;
            const type  = e.data?.match_type || 'any';
            const responses = Array.isArray(e.data?.responses)
                ? e.data.responses.map(r => String(r).trim()).filter(Boolean)
                : [];

            if (type === 'responses' && responses.length) {
                // 1 aresta por resposta (o motor casa cada valor); mesmo grupo no label.
                responses.forEach(r => outEdges.push({
                    from_node_id: e.source,
                    to_node_id:   e.target,
                    match_value:  r,
                    label:        group,
                }));
            } else {
                // "Qualquer resposta" → aresta automática / catch-all.
                outEdges.push({
                    from_node_id: e.source,
                    to_node_id:   e.target,
                    match_value:  null,
                    label:        group,
                });
            }
        });

    saveForm.edges = outEdges;

    saveForm.post(`/admin/chatbot/${props.flow.id}/flow`);
}

function goBack() {
    router.visit('/admin/chatbot');
}
</script>

<template>
    <div class="flow-page">
        <!-- ── Header ─────────────────────────────────────────────────────── -->
        <header class="flow-header">
            <div class="flow-header__left">
                <button type="button" class="flow-btn-back" @click="goBack">← Voltar</button>
                <span class="flow-header__name">{{ flow.name }}</span>
            </div>
            <div class="flow-header__right">
                <button
                    type="button"
                    class="flow-btn-icon"
                    title="Desfazer"
                    :disabled="!canUndo"
                    @click="undo"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z" />
                    </svg>
                </button>
                <button
                    type="button"
                    class="flow-btn-icon"
                    title="Refazer"
                    :disabled="!canRedo"
                    @click="redo"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M18.4 10.6C16.55 8.99 14.15 8 11.5 8c-4.65 0-8.58 3.03-9.96 7.22L3.9 16c1.05-3.19 4.05-5.5 7.6-5.5 1.95 0 3.73.72 5.12 1.88L13 16h9V7l-3.6 3.6z" />
                    </svg>
                </button>
                <button type="button" class="flow-btn-etapa" @click="addNode">+ Etapa</button>
                <button
                    type="button"
                    class="flow-btn-save"
                    :disabled="saveForm.processing"
                    @click="saveFlow"
                >
                    {{ saveForm.processing ? 'Salvando…' : 'Salvar' }}
                </button>
            </div>
        </header>

        <!-- ── Canvas + Panel ─────────────────────────────────────────────── -->
        <div class="flow-body">
            <VueFlow
                class="flow-canvas"
                :nodes="nodes"
                :edges="edges"
                :node-types="nodeTypes"
                :delete-key-code="null"
                fit-view-on-init
                @node-click="onNodeClick"
                @pane-click="onPaneClick"
                @node-drag-start="pushHistory"
            >
                <Background pattern-color="#e0e3e7" :gap="16" />
                <Controls />
                <MiniMap />
            </VueFlow>

            <!-- ── Right panel ───────────────────────────────────────────── -->
            <Transition name="panel">
            <aside v-if="selectedNode" class="flow-panel">
                <div class="flow-panel__head">
                    <span class="flow-panel__title">Configurações</span>
                    <button type="button" class="flow-panel__close" @click="selectedNodeId = null">✕</button>
                </div>

                <div class="flow-panel__body">

                    <!-- ── Configurações (nó fixo, sem conteúdo por ora) ───── -->
                    <template v-if="selectedNode.type === 'configurations'">
                        <p class="flow-panel__placeholder">
                            Em breve as configurações globais do fluxo aparecerão aqui.
                        </p>
                    </template>

                    <!-- ── Início (nó fixo, sem campos editáveis) ─────────── -->
                    <template v-else-if="selectedNode.type === 'start'">
                        <p class="flow-panel__placeholder">
                            Este é o ponto de entrada do fluxo.
                        </p>
                    </template>

                    <!-- ── Nós editáveis (message / action) ──────────────── -->
                    <template v-else>
                        <!-- Boas-vindas: etapa inicial obrigatória -->
                        <p v-if="isWelcome(selectedNode)" class="flow-panel__placeholder">
                            Mensagem inicial enviada automaticamente após o contato.
                        </p>

                        <!-- Nome da etapa -->
                        <div class="flow-panel__field">
                            <label class="flow-panel__label">Nome da etapa</label>
                            <input
                                v-model="selectedNode.data.name"
                                type="text"
                                class="flow-panel__input"
                            />
                        </div>

                        <!-- Abas: Interações / Condições -->
                        <div class="flow-tabs">
                            <button
                                type="button"
                                :class="{ active: panelTab === 'interacoes' }"
                                @click="panelTab = 'interacoes'"
                            >
                                Interações
                            </button>
                            <button
                                type="button"
                                :class="{ active: panelTab === 'condicoes' }"
                                @click="panelTab = 'condicoes'"
                            >
                                Condições
                            </button>
                        </div>

                        <!-- Aba Interações: o que o chatbot envia ao cliente -->
                        <div v-if="panelTab === 'interacoes'" class="flow-tab-content">
                            <div class="flow-itoolbar">
                                <button type="button" class="flow-itool" title="Mensagem de texto" @click="addInteraction('text')">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z" />
                                    </svg>
                                </button>
                                <button type="button" class="flow-itool" title="Documento" @click="addInteraction('document')">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z" />
                                    </svg>
                                </button>
                            </div>

                            <div
                                v-for="(item, idx) in interactions"
                                :key="item.id"
                                class="flow-interaction"
                            >
                                <div class="flow-interaction__head">
                                    <span class="flow-interaction__num">{{ idx + 1 }}</span>
                                    <div class="flow-interaction__actions">
                                        <button type="button" title="Subir" :disabled="idx === 0" @click="moveInteraction(idx, -1)">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12l1.41 1.41L11 7.83V20h2V7.83l5.58 5.59L20 12l-8-8-8 8z" /></svg>
                                        </button>
                                        <button type="button" title="Descer" :disabled="idx === interactions.length - 1" @click="moveInteraction(idx, 1)">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12l-1.41-1.41L13 16.17V4h-2v12.17l-5.58-5.59L4 12l8 8 8-8z" /></svg>
                                        </button>
                                        <button type="button" class="flow-interaction__del" title="Remover" @click="removeInteraction(idx)">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" /></svg>
                                        </button>
                                    </div>
                                </div>
                                <textarea
                                    v-model="item.text"
                                    class="flow-interaction__text"
                                    rows="6"
                                    placeholder="Mensagem enviada ao cliente…"
                                />
                            </div>

                            <p v-if="!interactions.length" class="flow-panel__empty">
                                Nenhuma interação. Use os ícones acima para adicionar.
                            </p>

                            <!-- Ação (apenas nós de ação) -->
                            <div v-if="selectedNode.type === 'action'" class="flow-panel__field">
                                <label class="flow-panel__label">Ação</label>
                                <select v-model="selectedNode.data.action" class="flow-panel__select">
                                    <option value="">— selecione —</option>
                                    <option value="handoff">Transferir para atendente</option>
                                    <option value="end">Encerrar conversa</option>
                                </select>
                            </div>
                        </div>

                        <!-- Aba Condições: resposta esperada do cliente -->
                        <div v-else class="flow-tab-content">
                            <button type="button" class="flow-panel__btn-add flow-panel__btn-add--block" @click="addEdge">
                                + Adicionar condição
                            </button>

                            <p v-if="!selectedEdges.length" class="flow-panel__empty">
                                Nenhuma condição configurada.
                            </p>

                            <div v-for="(edge, idx) in selectedEdges" :key="edge.id" class="flow-condition">
                                <div class="flow-condition__head">
                                    <span class="flow-condition__num">{{ idx + 1 }}</span>
                                    <div class="flow-condition__actions">
                                        <button type="button" title="Subir" :disabled="idx === 0" @click="moveCondition(idx, -1)">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12l1.41 1.41L11 7.83V20h2V7.83l5.58 5.59L20 12l-8-8-8 8z" /></svg>
                                        </button>
                                        <button type="button" title="Descer" :disabled="idx === selectedEdges.length - 1" @click="moveCondition(idx, 1)">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12l-1.41-1.41L13 16.17V4h-2v12.17l-5.58-5.59L4 12l8 8 8-8z" /></svg>
                                        </button>
                                        <button type="button" class="flow-condition__del" title="Remover" @click="removeEdge(edge.id)">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" /></svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="flow-condition__field">
                                    <label>Se</label>
                                    <select
                                        :value="edge.data?.match_type || 'responses'"
                                        class="flow-panel__select"
                                        @change="setMatchType(edge, $event.target.value)"
                                    >
                                        <option value="any">Qualquer resposta</option>
                                        <option value="responses">Respostas</option>
                                    </select>
                                </div>

                                <div v-if="(edge.data?.match_type || 'responses') === 'responses'" class="flow-condition__field">
                                    <label>Respostas</label>
                                    <div class="flow-tags">
                                        <span v-for="(resp, ri) in (edge.data?.responses || [])" :key="ri" class="flow-tag">
                                            {{ resp }}
                                            <button type="button" class="flow-tag__del" title="Remover" @click="removeResponse(edge, ri)">✕</button>
                                        </span>
                                        <input
                                            type="text"
                                            class="flow-tags__input"
                                            placeholder="digite e tecle Enter"
                                            @keydown.enter.prevent="addResponse(edge, $event.target.value); $event.target.value = ''"
                                            @blur="addResponse(edge, $event.target.value); $event.target.value = ''"
                                        />
                                    </div>
                                </div>

                                <hr class="flow-condition__divider" />

                                <span class="flow-condition__route-label">Rotear para:</span>
                                <label class="flow-condition__radio">
                                    <input type="radio" checked />
                                    <span>Etapa</span>
                                </label>

                                <div class="flow-condition__field">
                                    <label>Etapa</label>
                                    <select
                                        :value="edge.target"
                                        class="flow-panel__select"
                                        @change="updateEdgeTarget(edge.id, $event.target.value)"
                                    >
                                        <option value="">— selecione —</option>
                                        <option v-for="n in routeTargets" :key="n.id" :value="n.id">
                                            {{ n.data?.name || n.type }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Remover nó (oculto na etapa Boas-vindas, obrigatória) -->
                        <div v-if="!isWelcome(selectedNode)" class="flow-panel__danger">
                            <button type="button" class="flow-panel__btn-delete" @click="removeSelectedNode">
                                Remover nó
                            </button>
                        </div>
                    </template>
                    <!-- ── fim nós editáveis ──────────────────────────────── -->

                </div>
            </aside>
            </Transition>
        </div>
    </div>
</template>

<style scoped src="./styles/Flow.css"></style>
