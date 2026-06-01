<script setup>
import { computed } from 'vue';
import AppSidebar from '../../Components/AppSidebar.vue';
import AppTopbar from '../../Components/AppTopbar.vue';
import ReportKpi from '../../Components/ReportKpi.vue';
import ReportFilters from '../../Components/ReportFilters.vue';
import ChartArea from '../../Components/charts/ChartArea.vue';
import ChartDonut from '../../Components/charts/ChartDonut.vue';

const props = defineProps({
    filters: { type: Object, required: true },
    date: { type: String, required: true },
    kpis: { type: Object, required: true },
    motivos: { type: Array, default: () => [] },
    por_dia: { type: Array, default: () => [] },
});

const diaPoints = computed(() =>
    props.por_dia.map((row) => ({ label: row.dia, value: row.qtd, display: `${row.qtd}` })),
);

const motivoItems = computed(() =>
    props.motivos.map((row) => ({ label: row.motivo, value: row.qtd, display: `${row.qtd}` })),
);
</script>

<template>
    <div class="shell">
        <AppSidebar active="reports" />
        <div class="main">
            <AppTopbar title="Relatório de atendimento WhatsApp" :subtitle="props.date" role-badge="Admin" />
            <div class="content">
                <ReportFilters path="/relatorios/whatsapp" :date-from="props.filters.date_from" :date-to="props.filters.date_to" />

                <section>
                    <p class="section-label">resumo</p>
                    <div class="kpi-grid">
                        <ReportKpi label="em triagem agora" :value="props.kpis.triagem" accent="#ef9f27" hint="estado atual" />
                        <ReportKpi label="em andamento agora" :value="props.kpis.em_andamento" accent="#378add" hint="estado atual" />
                        <ReportKpi label="fechados no período" :value="props.kpis.fechados" accent="#1d9e75" />
                        <ReportKpi label="pedidos delivery" :value="props.kpis.pedidos_delivery" accent="#993c1d" />
                    </div>
                </section>

                <section class="report-panel">
                    <div class="report-panel-head">
                        <div>
                            <h3>Atendimentos por dia</h3>
                            <p>Novos tickets abertos no período</p>
                        </div>
                    </div>
                    <ChartArea :points="diaPoints" color="#378add" value-type="number" />
                </section>

                <section class="report-panel">
                    <div class="report-panel-head">
                        <div>
                            <h3>Fechamentos por motivo</h3>
                            <p>Atendimentos encerrados no período</p>
                        </div>
                    </div>
                    <ChartDonut :items="motivoItems" center-label="fechados" empty-text="Nenhum atendimento fechado no período." />
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped src="./styles/Reports.css"></style>
