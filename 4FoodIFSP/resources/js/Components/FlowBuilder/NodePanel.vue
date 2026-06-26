<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
    node:     { type: Object, default: null },
    allNodes: { type: Array,  default: () => [] },
    edges:    { type: Array,  default: () => [] },
});

const emit = defineEmits(['update', 'remove', 'close', 'addEdge', 'removeEdge', 'updateEdge']);

const form = ref({ name: '', text: '', action: '' });

watch(() => props.node, (n) => {
    if (!n) return;
    form.value = {
        name:   n.data?.name   ?? '',
        text:   n.data?.text   ?? '',
        action: n.data?.action ?? '',
    };
}, { immediate: true });

const outgoingEdges = computed(() =>
    props.edges.filter(e => e.source === props.node?.id)
);

const otherNodes = computed(() =>
    props.allNodes.filter(n => n.id !== props.node?.id)
);

function save() {
    emit('update', props.node.id, { ...form.value });
}

function addEdge() {
    emit('addEdge', props.node.id);
}

function removeEdge(edgeId) {
    emit('removeEdge', edgeId);
}

function updateEdge(edgeId, field, value) {
    emit('updateEdge', edgeId, { [field]: value });
}
</script>

<template>
    <aside v-if="node" class="node-panel">
        <div class="node-panel__header">
            <span class="node-panel__title">Configurações</span>
            <button type="button" class="node-panel__close" @click="$emit('close')">✕</button>
        </div>

        <div class="node-panel__body">
            <div class="node-panel__field">
                <label class="node-panel__label">Nome</label>
                <input
                    v-model="form.name"
                    type="text"
                    class="node-panel__input"
                    :disabled="node.type === 'start'"
                    @input="save"
                />
            </div>

            <template v-if="node.type !== 'start'">
                <div class="node-panel__field">
                    <label class="node-panel__label">Mensagem</label>
                    <textarea
                        v-model="form.text"
                        class="node-panel__textarea"
                        rows="4"
                        placeholder="Texto enviado ao cliente neste passo..."
                        @input="save"
                    />
                </div>
            </template>

            <template v-if="node.type === 'action'">
                <div class="node-panel__field">
                    <label class="node-panel__label">Ação</label>
                    <select v-model="form.action" class="node-panel__select" @change="save">
                        <option value="">— selecione —</option>
                        <option value="handoff">Transferir para atendente</option>
                        <option value="end">Encerrar conversa</option>
                    </select>
                </div>
            </template>

            <div v-if="node.type !== 'start'" class="node-panel__section">
                <div class="node-panel__section-head">
                    <span class="node-panel__section-title">Saídas</span>
                    <button type="button" class="node-panel__btn-add" @click="addEdge">+ Adicionar</button>
                </div>

                <div
                    v-for="edge in outgoingEdges"
                    :key="edge.id"
                    class="node-panel__edge"
                >
                    <input
                        :value="edge.data?.match_value ?? ''"
                        type="text"
                        class="node-panel__input node-panel__input--sm"
                        placeholder="Resposta (ex: 1)"
                        @input="updateEdge(edge.id, 'match_value', $event.target.value)"
                    />

                    <select
                        :value="edge.target"
                        class="node-panel__select node-panel__select--sm"
                        @change="updateEdge(edge.id, 'target', $event.target.value)"
                    >
                        <option value="">— ir para —</option>
                        <option v-for="n in otherNodes" :key="n.id" :value="n.id">
                            {{ n.data?.name || n.type }}
                        </option>
                    </select>

                    <button
                        type="button"
                        class="node-panel__btn-remove"
                        @click="removeEdge(edge.id)"
                    >✕</button>
                </div>

                <p v-if="!outgoingEdges.length" class="node-panel__empty">
                    Nenhuma saída configurada.
                </p>
            </div>

            <div v-if="node.type !== 'start'" class="node-panel__danger">
                <button type="button" class="node-panel__btn-delete" @click="$emit('remove', node.id)">
                    Remover nó
                </button>
            </div>
        </div>
    </aside>
</template>
