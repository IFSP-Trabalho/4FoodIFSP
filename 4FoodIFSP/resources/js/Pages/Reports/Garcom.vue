<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppSidebar from '../../Components/AppSidebar.vue';
import AppTopbar from '../../Components/AppTopbar.vue';
import ReportKpi from '../../Components/ReportKpi.vue';
import ReportFilters from '../../Components/ReportFilters.vue';
import ChartBars from '../../Components/charts/ChartBars.vue';

const props = defineProps({
    filters: { type: Object, required: true },
    date: { type: String, required: true },
    kpis: { type: Object, required: true },
    por_mesa: { type: Array, default: () => [] },
});

const roleLabel = computed(() => {
    const role = usePage().props.auth?.user?.role ?? '';
    return role === 'waiter' ? 'Garçom' : 'Admin';
});

const mesaBars = computed(() =>
    props.por_mesa.map((row) => ({ label: row.mesa, value: row.total, display: row.total_label, sub: `${row.sessoes} sessões` })),
);
</script>

<template>
    <div class="shell">
        <AppSidebar active="reports" />
        <div class="main">
            <AppTopbar title="Relatório de atendimento de mesas" :subtitle="props.date" :role-badge="roleLabel" />
            <div class="content">
                <ReportFilters path="/relatorios/garcom" :date-from="props.filters.date_from" :date-to="props.filters.date_to" />

                <section>
                    <p class="section-label">resumo do período</p>
                    <div class="kpi-grid">
                        <ReportKpi label="mesas atendidas" :value="props.kpis.mesas_atendidas" accent="#993c1d" />
                        <ReportKpi label="contas fechadas" :value="props.kpis.contas_fechadas" accent="#1d9e75" />
                        <ReportKpi label="faturamento" :value="props.kpis.faturamento" accent="#993c1d" />
                        <ReportKpi label="ticket médio" :value="props.kpis.ticket_medio" accent="#993c1d" hint="por conta fechada" />
                        <ReportKpi label="tempo médio de mesa" :value="props.kpis.tempo_medio" accent="#378add" />
                    </div>
                </section>

                <section class="report-panel">
                    <div class="report-panel-head">
                        <div>
                            <h3>Desempenho por mesa</h3>
                            <p>Faturamento por mesa no período</p>
                        </div>
                    </div>
                    <ChartBars :items="mesaBars" color="#993c1d" empty-text="Nenhuma mesa atendida no período." />
                    <p class="report-note">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z" />
                        </svg>
                        <span>
                            Números consolidados de todas as mesas. O sistema ainda não registra qual garçom atendeu
                            cada mesa, então não é possível separar por atendente.
                        </span>
                    </p>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped src="./styles/Reports.css"></style>
