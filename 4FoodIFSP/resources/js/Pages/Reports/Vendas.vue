<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppSidebar from '../../Components/AppSidebar.vue';
import AppTopbar from '../../Components/AppTopbar.vue';
import ReportKpi from '../../Components/ReportKpi.vue';
import ReportFilters from '../../Components/ReportFilters.vue';
import ChartArea from '../../Components/charts/ChartArea.vue';
import ChartBars from '../../Components/charts/ChartBars.vue';
import ChartDonut from '../../Components/charts/ChartDonut.vue';

const props = defineProps({
    filters: { type: Object, required: true },
    date: { type: String, required: true },
    kpis: { type: Object, required: true },
    por_dia: { type: Array, default: () => [] },
    top_pratos: { type: Array, default: () => [] },
    pagamentos: { type: Array, default: () => [] },
});

const roleLabel = computed(() => {
    const role = usePage().props.auth?.user?.role ?? '';
    return role === 'finance' ? 'Financeiro' : 'Admin';
});

const diaPoints = computed(() =>
    props.por_dia.map((row) => ({
        label: row.dia,
        value: row.total,
        display: row.total_label,
        hint: `${row.pedidos} pedido${row.pedidos === 1 ? '' : 's'}`,
    })),
);

const pratoBars = computed(() =>
    props.top_pratos.map((row) => ({ label: row.nome, value: row.total, display: row.total_label, sub: `${row.qtd}x` })),
);

const pagamentoItems = computed(() =>
    props.pagamentos.map((row) => ({ label: row.metodo, value: row.qtd, display: `${row.qtd}` })),
);
</script>

<template>
    <div class="shell">
        <AppSidebar active="reports" />
        <div class="main">
            <AppTopbar title="Relatório de vendas" :subtitle="props.date" :role-badge="roleLabel" />
            <div class="content">
                <ReportFilters path="/relatorios/vendas" :date-from="props.filters.date_from" :date-to="props.filters.date_to" />

                <section>
                    <p class="section-label">resumo do período</p>
                    <div class="kpi-grid">
                        <ReportKpi label="faturamento" :value="props.kpis.faturamento" accent="#993c1d" />
                        <ReportKpi label="recebido" :value="props.kpis.recebido" accent="#1d9e75" />
                        <ReportKpi label="pendente" :value="props.kpis.pendente" accent="#ef9f27" />
                        <ReportKpi label="ticket médio" :value="props.kpis.ticket_medio" accent="#993c1d" />
                        <ReportKpi label="pedidos" :value="props.kpis.pedidos" accent="#993c1d" />
                        <ReportKpi label="cancelados" :value="props.kpis.cancelados" accent="#9aa0ab" />
                    </div>
                </section>

                <section class="report-panel">
                    <div class="report-panel-head">
                        <div>
                            <h3>Faturamento por dia</h3>
                            <p>Receita bruta de pedidos não cancelados</p>
                        </div>
                        <span class="report-panel-tag">{{ props.kpis.faturamento }}</span>
                    </div>
                    <ChartArea :points="diaPoints" color="#993c1d" value-type="currency" />
                </section>

                <section class="report-cols">
                    <div class="report-panel">
                        <div class="report-panel-head">
                            <div>
                                <h3>Pratos mais vendidos</h3>
                                <p>Por receita no período</p>
                            </div>
                        </div>
                        <ChartBars :items="pratoBars" color="#993c1d" rank empty-text="Sem vendas no período." />
                    </div>

                    <div class="report-panel">
                        <div class="report-panel-head">
                            <div>
                                <h3>Formas de pagamento</h3>
                                <p>Contas fechadas no período</p>
                            </div>
                        </div>
                        <ChartDonut :items="pagamentoItems" center-label="contas" empty-text="Nenhuma conta fechada no período." />
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped src="./styles/Reports.css"></style>
