<script setup>
import './styles/TableUnavailable.css';

defineProps({
    mesa: { type: Number, required: true },
});
</script>

<template>
    <div class="table-unavailable theme-force-light">
        <div class="table-unavailable-card">
            <div class="table-unavailable-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 5h16v3H4zm7 3h2v11h-2zM6 19h12v2H6z"/>
                </svg>
            </div>
            <h1 class="table-unavailable-title">Mesa indisponível</h1>
            <p class="table-unavailable-text">
                Esta mesa não está disponível no momento.<br>
                Por favor, aguarde ou chame um atendente.
            </p>
        </div>
    </div>
</template>
