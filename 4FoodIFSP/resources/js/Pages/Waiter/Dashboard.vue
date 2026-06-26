<script setup>
import { router } from '@inertiajs/vue3';

function logout() {
    router.post('/logout');
}
</script>

<template>
    <main class="min-h-screen p-6 bg-neutral-100 dark:bg-[#15181f]">
        <div class="max-w-3xl mx-auto bg-white rounded-lg border border-neutral-200 p-6 text-neutral-900 dark:bg-[#20252f] dark:border-[#2b313c] dark:text-[#e9ebef]">
            <h1 class="text-2xl font-semibold">Waiter Dashboard</h1>
            <p class="mt-2 text-neutral-600 dark:text-[#959cab]">Login concluido com sucesso para role waiter.</p>
            <button class="mt-6 px-4 py-2 rounded-md bg-neutral-900 text-white dark:bg-[#993c1d]" @click="logout">Sair</button>
        </div>
    </main>
</template>
