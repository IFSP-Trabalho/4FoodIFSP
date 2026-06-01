<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { countries, countryByDial, defaultCountry, flagUrl } from '../utils/countries';

const props = defineProps({
    // Quando presente, o painel está em modo edição.
    contact: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const isEdit = computed(() => props.contact !== null);

const initialCountry = props.contact
    ? countryByDial(props.contact.country_code ?? '55')
    : defaultCountry;

const form = useForm({
    name:         props.contact?.name ?? '',
    country_code: initialCountry.dial,
    ddd:          props.contact?.ddd ?? '',
    number:       props.contact?.number ?? '',
    cpf:          props.contact?.cpf ?? '',
    notes:        props.contact?.notes ?? '',
});

/* ---------- seletor de país ---------- */
const isCountryOpen = ref(false);
const countryRef = ref(null);

const selectedCountry = computed(() => countryByDial(form.country_code));

function toggleCountry() {
    isCountryOpen.value = !isCountryOpen.value;
}

function selectCountry(country) {
    form.country_code = country.dial;
    isCountryOpen.value = false;
}

function onlyDigits(event, field, max) {
    const digits = event.target.value.replace(/\D/g, '').slice(0, max);
    form[field] = digits;
}

function handleCancel() {
    form.reset();
    form.clearErrors();
    emit('close');
}

function handleSubmit() {
    const payload = {
        name:         form.name.trim(),
        country_code: form.country_code,
        ddd:          form.ddd.trim(),
        number:       form.number.trim(),
        cpf:          form.cpf.trim() || null,
        notes:        form.notes.trim() || null,
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    };

    if (isEdit.value) {
        form.transform(() => payload).put(`/whatsapp/contatos/${props.contact.id}`, options);
    } else {
        form.transform(() => payload).post('/whatsapp/contatos', options);
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        handleCancel();
    }
}

function onDocumentClick(event) {
    if (countryRef.value && !countryRef.value.contains(event.target)) {
        isCountryOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    document.addEventListener('click', onDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
    <div class="admin-modal-overlay" @click.self="handleCancel"></div>
    <section
        class="admin-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="contact-panel-title"
    >
        <header class="admin-modal-head">
            <h3 id="contact-panel-title">
                {{ isEdit ? 'Editar contato' : 'Adicionar contato' }}
            </h3>
        </header>

        <form class="admin-modal-form" @submit.prevent="handleSubmit">
            <label class="admin-modal-field">
                <div class="admin-modal-input-wrap">
                    <span class="admin-modal-floating-label">Nome</span>
                    <input
                        v-model="form.name"
                        type="text"
                        placeholder=" "
                        maxlength="120"
                        required
                    >
                </div>
                <small v-if="form.errors.name">{{ form.errors.name }}</small>
            </label>

            <!-- País -->
            <div class="admin-modal-field">
                <div ref="countryRef" class="country-select" :class="{ 'is-open': isCountryOpen }">
                    <span class="admin-modal-floating-label">País</span>
                    <button
                        type="button"
                        class="country-trigger"
                        aria-haspopup="listbox"
                        :aria-expanded="isCountryOpen"
                        @click="toggleCountry"
                    >
                        <img class="country-flag" :src="flagUrl(selectedCountry.iso)" :alt="selectedCountry.name" width="22" height="16">
                        <span class="country-trigger-text">{{ selectedCountry.name }}</span>
                        <span class="country-dial">+{{ selectedCountry.dial }}</span>
                        <svg class="country-chevron" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 10l5 5 5-5H7Z" />
                        </svg>
                    </button>

                    <div v-if="isCountryOpen" class="country-menu" role="listbox">
                        <button
                            v-for="country in countries"
                            :key="country.iso"
                            type="button"
                            class="country-option"
                            role="option"
                            :aria-selected="country.dial === form.country_code"
                            @click="selectCountry(country)"
                        >
                            <img class="country-flag" :src="flagUrl(country.iso)" :alt="country.name" width="22" height="16">
                            <span class="country-option-name">{{ country.name }}</span>
                            <span class="country-dial">+{{ country.dial }}</span>
                        </button>
                    </div>
                </div>
                <small v-if="form.errors.country_code">{{ form.errors.country_code }}</small>
            </div>

            <!-- DDD + Número -->
            <div class="phone-row">
                <label class="admin-modal-field phone-ddd">
                    <div class="admin-modal-input-wrap">
                        <span class="admin-modal-floating-label">DDD</span>
                        <input
                            :value="form.ddd"
                            type="text"
                            inputmode="numeric"
                            placeholder="14"
                            required
                            @input="onlyDigits($event, 'ddd', 3)"
                        >
                    </div>
                    <small v-if="form.errors.ddd">{{ form.errors.ddd }}</small>
                </label>

                <label class="admin-modal-field phone-number">
                    <div class="admin-modal-input-wrap">
                        <span class="admin-modal-floating-label">Número</span>
                        <input
                            :value="form.number"
                            type="text"
                            inputmode="numeric"
                            placeholder="991736181"
                            required
                            @input="onlyDigits($event, 'number', 9)"
                        >
                    </div>
                    <small v-if="form.errors.number">{{ form.errors.number }}</small>
                </label>
            </div>

            <label class="admin-modal-field">
                <div class="admin-modal-input-wrap">
                    <span class="admin-modal-floating-label">CPF (opcional)</span>
                    <input
                        v-model="form.cpf"
                        type="text"
                        placeholder=" "
                        maxlength="20"
                    >
                </div>
                <small v-if="form.errors.cpf">{{ form.errors.cpf }}</small>
            </label>

            <label class="admin-modal-field">
                <div class="admin-modal-input-wrap admin-modal-textarea-wrap">
                    <span class="admin-modal-floating-label">Observações (opcional)</span>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        maxlength="4096"
                        placeholder=" "
                    />
                </div>
                <small v-if="form.errors.notes">{{ form.errors.notes }}</small>
            </label>

            <footer class="admin-modal-actions">
                <button type="button" class="secondary" :disabled="form.processing" @click="handleCancel">
                    Cancelar
                </button>
                <button type="submit" class="primary" :disabled="form.processing">
                    {{ form.processing ? 'Salvando...' : 'Salvar' }}
                </button>
            </footer>
        </form>
    </section>
</template>

<style scoped src="./styles/ContactFormPanel.css"></style>
