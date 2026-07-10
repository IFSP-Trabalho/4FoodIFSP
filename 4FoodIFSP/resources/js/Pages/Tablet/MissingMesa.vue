<script setup>
import './styles/MissingMesa.css';
</script>

<template>
    <div class="missing-mesa theme-force-light">
        <div class="missing-mesa-card">
            <div class="missing-mesa-icon-wrap">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#E67E22" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <circle cx="12" cy="16" r="0.5" fill="#E67E22" />
                </svg>
            </div>
            <h1 class="missing-mesa-title">Mesa não identificada</h1>
            <p class="missing-mesa-text">
                Acesse o cardápio usando a URL com o número da sua mesa.
            </p>
            <code class="missing-mesa-example">/tablet?mesa=1</code>
        </div>
    </div>
</template>
