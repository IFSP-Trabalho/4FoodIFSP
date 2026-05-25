<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: 'whatsapp',
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const rootRef = ref(null);

const options = [
    { value: 'whatsapp', label: 'WhatsApp' },
];

const selectedOption = computed(() =>
    options.find((o) => o.value === props.modelValue) ?? options[0]
);

function toggleOpen(event) {
    event?.stopPropagation?.();
    isOpen.value = !isOpen.value;
}

function selectOption(value, event) {
    event.stopPropagation();
    emit('update:modelValue', value);
    isOpen.value = false;
}

function onDocumentClick(event) {
    if (!rootRef.value?.contains(event.target)) {
        isOpen.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div
        ref="rootRef"
        class="wa-channel-select"
        :class="{ 'is-open': isOpen }"
    >
        <div
            class="wa-channel-select-trigger"
            role="combobox"
            tabindex="0"
            :aria-expanded="isOpen"
            aria-haspopup="listbox"
            @click="toggleOpen"
            @keydown.enter.prevent="toggleOpen"
            @keydown.space.prevent="toggleOpen"
            @keydown.escape="isOpen = false"
        >
            <span class="admin-modal-floating-label">Tipo de canal</span>
            <span class="wa-channel-select-value">{{ selectedOption.label }}</span>
            <svg class="wa-channel-select-chevron" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 10l5 5 5-5H7Z" />
            </svg>
        </div>

        <div v-if="isOpen" class="wa-channel-select-menu" role="listbox">
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                class="wa-channel-select-option"
                role="option"
                :aria-selected="modelValue === option.value"
                @click="selectOption(option.value, $event)"
            >
                {{ option.label }}
            </button>
        </div>

        <small v-if="error" class="wa-channel-select-error">{{ error }}</small>
    </div>
</template>

<style scoped src="./styles/WaChannelTypeSelect.css"></style>
