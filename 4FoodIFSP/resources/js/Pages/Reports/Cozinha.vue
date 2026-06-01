<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppSidebar from '../../Components/AppSidebar.vue';
import AppTopbar from '../../Components/AppTopbar.vue';
import ReportKpi from '../../Components/ReportKpi.vue';
import ReportFilters from '../../Components/ReportFilters.vue';
import ChartBars from '../../Components/charts/ChartBars.vue';
import ChartDonut from '../../Components/charts/ChartDonut.vue';

const props = defineProps({
    filters: { type: Object, required: true },
    date: { type: String, required: true },
    kpis: { type: Object, required: true },
    top_pratos: { type: Array, default: () => [] },
    cancelamentos: { type: Array, default: () => [] },
});

const roleLabel = computed(() => {
    const role = usePage().props.auth?.user?.role ?? '';
    return role === 'kitchen' ? 'Cozinha' : 'Admin';
});

const pratoBars = computed(() =>
    props.top_pratos.map((row) => ({ label: row.nome, value: row.qtd, display: `${row.qtd}x` })),
);

const cancelItems = computed(() =>
    props.cancelamentos.map((row) => ({ label: row.motivo, value: row.qtd, display: `${row.qtd}` })),
);
</script>

<template>
    <div class="shell">
        <AppSidebar active="reports" />
        <div class="main">
            <AppTopbar title="Relatório da cozinha" :subtitle="props.date" :role-badge="roleLabel" />
            <div class="content">
                <ReportFilters path="/relatorios/cozinha" :date-from="props.filters.date_from" :date-to="props.filters.date_to" />

                <section>
                    <p class="section-label">resumo do período</p>
                    <div class="kpi-grid">
                        <ReportKpi label="preparados" :value="props.kpis.preparados" accent="#1d9e75" />
                        <ReportKpi label="tempo médio" :value="props.kpis.tempo_medio" accent="#993c1d" hint="do pedido à conclusão" />
                        <ReportKpi label="cancelados" :value="props.kpis.cancelados" accent="#9aa0ab" />
                        <ReportKpi label="em preparo agora" :value="props.kpis.em_preparo" accent="#ef9f27" hint="estado atual" />
                    </div>
                </section>

                <section class="report-cols">
                    <div class="report-panel">
                        <div class="report-panel-head">
                            <div>
                                <h3>Pratos mais preparados</h3>
                                <p>Pedidos concluídos no período</p>
                            </div>
                        </div>
                        <ChartBars :items="pratoBars" color="#ef9f27" rank empty-text="Nada preparado no período." />
                    </div>

                    <div class="report-panel">
                        <div class="report-panel-head">
                            <div>
                                <h3>Cancelamentos por motivo</h3>
                                <p>Pedidos cancelados no período</p>
                            </div>
                        </div>
                        <ChartDonut :items="cancelItems" center-label="cancel." empty-text="Nenhum cancelamento no período." />
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped src="./styles/Reports.css"></style>
