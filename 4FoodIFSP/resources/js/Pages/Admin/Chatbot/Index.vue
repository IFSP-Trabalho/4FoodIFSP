<script setup>
import { computed, ref } from 'vue';
import AppSidebar from '../../../Components/AppSidebar.vue';

const props = defineProps({
    bots: {
        type: Array,
        default: () => [],
    },
});

const search = ref('');

const filteredBots = computed(() => {
    if (!search.value.trim()) {
        return props.bots;
    }

    const query = search.value.toLowerCase();

    return props.bots.filter((bot) => (
        String(bot.name ?? '').toLowerCase().includes(query)
    ));
});
</script>

<template>
    <div class="shell">
        <AppSidebar active="chatbot" />

        <div class="main">
            <header class="topbar">
                <h1>ChatBot</h1>
                <div class="head-actions">
                    <input v-model="search" type="text" placeholder="Localize">
                    <button type="button" disabled>
                        Adicionar
                    </button>
                </div>
            </header>

            <div class="content">
                <section class="table-card">
                    <div class="table-head">
                        <span>Nomes</span>
                        <span>Ativo</span>
                        <span>Acoes</span>
                    </div>

                    <template v-if="props.bots.length === 0">
                        <div class="empty-state">
                            <p>Nenhum bot cadastrado.</p>
                        </div>
                    </template>

                    <template v-else-if="filteredBots.length === 0">
                        <p class="search-empty">Nenhum bot encontrado.</p>
                    </template>

                    <template v-else>
                        <div v-for="bot in filteredBots" :key="bot.id" class="table-row">
                            <span class="bot-name">{{ bot.name }}</span>
                            <span>
                                <span class="status-badge" :class="bot.active ? 'on' : 'off'">
                                    {{ bot.active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </span>
                            <span class="row-actions">
                                <button type="button" class="action-btn" disabled>Editar</button>
                            </span>
                        </div>
                    </template>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped src="./styles/Index.css"></style>
