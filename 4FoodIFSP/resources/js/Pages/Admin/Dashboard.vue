<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import AppSidebar from '../../Components/AppSidebar.vue';
import AppTopbar from '../../Components/AppTopbar.vue';
import ChartArea from '../../Components/charts/ChartArea.vue';
import ChartBars from '../../Components/charts/ChartBars.vue';
import ChartDonut from '../../Components/charts/ChartDonut.vue';
import ChartSparkline from '../../Components/charts/ChartSparkline.vue';
import ChartBarsVertical from '../../Components/charts/ChartBarsVertical.vue';

const props = defineProps({
    heroKpis:    { type: Object, required: true },
    financial:   { type: Object, required: true },
    operational: { type: Object, required: true },
    tables:      { type: Object, required: true },
    whatsapp:    { type: Object, required: true },
    menu:        { type: Object, required: true },
    filters:     { type: Object, required: true },
    date:        { type: String, required: true },
});

// ── Reactive state ────────────────────────────────────────────────────────────

const kpis = ref({ ...props.heroKpis });
const fin  = ref({ ...props.financial });
const ops  = ref({ ...props.operational });
const tabs = ref({ ...props.tables });
const wa   = ref({ ...props.whatsapp });
const menu = ref({ ...props.menu });

const today    = new Date().toISOString().slice(0, 10);
const dateFrom = ref(props.filters.from ?? today);
const dateTo   = ref(props.filters.to ?? today);
const activePreset = ref(props.filters.preset ?? 'today');
const updatedAt = ref(props.heroKpis.updated_at ?? '--:--');
const tick = ref(Date.now());
const showExportMenu = ref(false);
const appliedRange = ref('');

let pollTimer = null;
let tickTimer = null;

// ── Computed ──────────────────────────────────────────────────────────────────

const mesaOccupancyPct = computed(() => {
    const { ocupadas, ativas } = kpis.value.mesas;
    return ativas > 0 ? Math.round((ocupadas / ativas) * 100) : 0;
});

const waAlertLevel = computed(() => {
    const t = kpis.value.wa_triagem.triagem;
    if (t > 3) return 'danger';
    if (t > 0) return 'warn';
    return 'normal';
});

// ── Period filter ─────────────────────────────────────────────────────────────

function formatAppliedRange() {
    const fmt = (d) => {
        const [y, m, day] = d.split('-');
        return `${day}/${m}/${y}`;
    };
    if (dateFrom.value === dateTo.value) return fmt(dateFrom.value);
    return `${fmt(dateFrom.value)} — ${fmt(dateTo.value)}`;
}

function setPreset(preset) {
    activePreset.value = preset;
    const end = new Date();
    const start = new Date();
    if (preset === '7d') start.setDate(end.getDate() - 6);
    else if (preset === '30d') start.setDate(end.getDate() - 29);
    dateFrom.value = start.toISOString().slice(0, 10);
    dateTo.value   = end.toISOString().slice(0, 10);
    loadPeriodData();
}

function onDateChange() {
    activePreset.value = null;
    loadPeriodData();
}

// ── Data loading ──────────────────────────────────────────────────────────────

async function loadPeriodData() {
    try {
        const params = { date_from: dateFrom.value, date_to: dateTo.value };
        const [finRes, waRes] = await Promise.all([
            axios.get('/admin/dashboard/financial', { params }),
            axios.get('/admin/dashboard/whatsapp',  { params }),
        ]);
        fin.value = finRes.data;
        wa.value  = waRes.data;
        appliedRange.value = formatAppliedRange();
    } catch {}
}

async function pollLive() {
    try {
        const [kpisRes, opsRes, tabsRes] = await Promise.all([
            axios.get('/admin/dashboard/hero-kpis'),
            axios.get('/admin/dashboard/operational'),
            axios.get('/admin/dashboard/tables'),
        ]);
        kpis.value = kpisRes.data;
        ops.value  = opsRes.data;
        tabs.value = tabsRes.data;
        updatedAt.value = kpisRes.data.updated_at ?? '--:--';
    } catch {}
}

async function refreshAll() {
    await Promise.all([pollLive(), loadPeriodData()]);
}

// ── Export ────────────────────────────────────────────────────────────────────

function exportCsv() {
    showExportMenu.value = false;
    const sep = ';';
    const bom = '﻿';
    const rows = [
        [`Dashboard 4Foods — gerado em ${new Date().toLocaleString('pt-BR')}`],
        [`Período: ${dateFrom.value} a ${dateTo.value}`],
        [],
        ['RESUMO DO DIA'],
        ['Indicador', 'Valor'],
        ['Faturamento', kpis.value.faturamento.value],
        ['Ticket Médio', kpis.value.ticket_medio.value],
        ['Pedidos totais', kpis.value.pedidos.total],
        ['Pedidos pagos', kpis.value.pedidos.pagos],
        ['Pedidos em aberto', kpis.value.pedidos.abertos],
        ['Mesas ocupadas', `${kpis.value.mesas.ocupadas} de ${kpis.value.mesas.ativas}`],
        ['Tickets WA triagem', kpis.value.wa_triagem.triagem],
        [],
        ['RECEITA POR DIA'],
        ['Data', 'Receita'],
        ...fin.value.por_dia.map((d) => [d.label, d.display]),
        [],
        ['STATUS DE PEDIDOS HOJE'],
        ['Status', 'Quantidade'],
        ['Pendentes', ops.value.status.pendentes],
        ['Em preparo', ops.value.status.em_preparo],
        ['Prontos', ops.value.status.prontos],
        ['Cancelados', ops.value.status.cancelados],
        [],
        ['TOP 5 PRATOS'],
        ['Prato', 'Quantidade', 'Receita'],
        ...ops.value.top_pratos.map((p) => [p.nome, p.qtd, p.receita]),
        [],
        ['WHATSAPP & DELIVERY'],
        ['Indicador', 'Valor'],
        ['Triagem', wa.value.funil.triagem],
        ['Em atendimento', wa.value.funil.em_atendimento],
        ['Encerrados hoje', wa.value.funil.encerrados_hoje],
        ['Pedidos delivery', wa.value.delivery.pedidos],
        ['Receita delivery', wa.value.delivery.receita],
        ['Ticket médio delivery', wa.value.delivery.ticket_medio],
    ];

    const csv = bom + rows.map((r) => r.map((v) => `"${v}"`).join(sep)).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `dashboard_4foods_${today}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

// ── Table timer ───────────────────────────────────────────────────────────────

function elapsedTime(startedAt) {
    if (!startedAt) return '';
    const diff = Math.max(0, Math.floor((tick.value - new Date(startedAt).getTime()) / 1000));
    const h = Math.floor(diff / 3600);
    const m = Math.floor((diff % 3600) / 60);
    const s = diff % 60;
    return h > 0
        ? `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
        : `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
    appliedRange.value = formatAppliedRange();
    pollTimer = setInterval(pollLive, 30000);
    tickTimer = setInterval(() => { tick.value = Date.now(); }, 1000);
    document.addEventListener('click', onClickOutside);
});

onUnmounted(() => {
    clearInterval(pollTimer);
    clearInterval(tickTimer);
    document.removeEventListener('click', onClickOutside);
});

function onClickOutside(e) {
    if (!e.target.closest('.export-wrap')) {
        showExportMenu.value = false;
    }
}
</script>

<template>
    <div class="shell">
        <AppSidebar active="dashboard" />
        <div class="main">
            <AppTopbar title="Dashboard" :subtitle="props.date" />

            <!-- ── Barra de filtro e exportação ──────────────────────── -->
            <div class="dash-bar">
                <div class="bar-left">
                    <svg class="bar-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/>
                    </svg>

                    <div class="date-group">
                        <span class="flabel">De</span>
                        <input v-model="dateFrom" type="date" class="date-input" @change="onDateChange" />
                    </div>

                    <span class="bar-dash">—</span>

                    <div class="date-group">
                        <span class="flabel">Até</span>
                        <input v-model="dateTo" type="date" class="date-input" @change="onDateChange" />
                    </div>

                    <div class="presets">
                        <button :class="['preset', { on: activePreset === 'today' }]" @click="setPreset('today')">Hoje</button>
                        <button :class="['preset', { on: activePreset === '7d' }]"    @click="setPreset('7d')">7 dias</button>
                        <button :class="['preset', { on: activePreset === '30d' }]"   @click="setPreset('30d')">30 dias</button>
                    </div>
                </div>

                <div class="bar-right">
                    <span class="updated-txt">Atualizado às {{ updatedAt }}</span>

                    <div class="refresh-group">
                        <button class="refresh-btn" @click="refreshAll">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
                            Atualizar
                        </button>
                        <span v-if="appliedRange" class="applied-range">{{ appliedRange }}</span>
                    </div>

                    <div class="export-wrap">
                        <button class="export-btn" @click.stop="showExportMenu = !showExportMenu">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                            Exportar
                        </button>
                        <div v-if="showExportMenu" class="export-menu">
                            <button @click="exportCsv">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                                Exportar CSV
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Conteúdo ──────────────────────────────────────────── -->
            <div class="page">

                <!-- ══ BLOCO 1: VISÃO GERAL DO DIA ══════════════════════ -->
                <p class="section-label">visão geral do dia</p>
                <div class="hero-grid">

                    <div class="hero-kpi">
                        <div class="kpi-head">
                            <span class="kpi-dot" style="background: #993c1d; box-shadow: 0 0 0 3px rgba(153,60,29,.14);" />
                            <span class="kpi-lbl">Faturamento</span>
                        </div>
                        <p class="kpi-val">{{ kpis.faturamento.value }}</p>
                        <div
                            v-if="kpis.faturamento.delta !== null"
                            :class="['kpi-delta', kpis.faturamento.delta >= 0 ? 'delta-up' : 'delta-down']"
                        >
                            {{ kpis.faturamento.delta >= 0 ? '▲' : '▼' }} {{ Math.abs(kpis.faturamento.delta) }}% vs sem. ant.
                        </div>
                        <ChartSparkline :values="kpis.faturamento.sparkline" color="#993c1d" />
                    </div>

                    <div class="hero-kpi">
                        <div class="kpi-head">
                            <span class="kpi-dot" style="background: #1d9e75; box-shadow: 0 0 0 3px rgba(29,158,117,.14);" />
                            <span class="kpi-lbl">Ticket Médio</span>
                        </div>
                        <p class="kpi-val">{{ kpis.ticket_medio.value }}</p>
                        <div
                            v-if="kpis.ticket_medio.delta !== null"
                            :class="['kpi-delta', kpis.ticket_medio.delta >= 0 ? 'delta-up' : 'delta-down']"
                        >
                            {{ kpis.ticket_medio.delta >= 0 ? '▲' : '▼' }} {{ Math.abs(kpis.ticket_medio.delta) }}% vs sem. ant.
                        </div>
                    </div>

                    <div class="hero-kpi">
                        <div class="kpi-head">
                            <span class="kpi-dot" style="background: #ef9f27; box-shadow: 0 0 0 3px rgba(239,159,39,.14);" />
                            <span class="kpi-lbl">Pedidos Hoje</span>
                        </div>
                        <p class="kpi-val">{{ kpis.pedidos.total }}</p>
                        <p class="kpi-hint">{{ kpis.pedidos.pagos }} pagos · {{ kpis.pedidos.abertos }} em aberto</p>
                    </div>

                    <div class="hero-kpi">
                        <div class="kpi-head">
                            <span class="kpi-dot" style="background: #378add; box-shadow: 0 0 0 3px rgba(55,138,221,.14);" />
                            <span class="kpi-lbl">Mesas Agora</span>
                        </div>
                        <p class="kpi-val">{{ kpis.mesas.ocupadas }}</p>
                        <p class="kpi-hint">de {{ kpis.mesas.ativas }} mesas ativas</p>
                        <div class="kpi-bar-wrap">
                            <div class="kpi-bar-fill" :style="{ width: mesaOccupancyPct + '%' }" />
                        </div>
                    </div>

                    <div
                        :class="[
                            'hero-kpi',
                            waAlertLevel === 'danger' ? 'kpi-danger' : waAlertLevel === 'warn' ? 'kpi-warn' : '',
                        ]"
                    >
                        <div class="kpi-head">
                            <span
                                class="kpi-dot"
                                :style="waAlertLevel === 'danger'
                                    ? 'background:#e53e3e; box-shadow:0 0 0 3px rgba(229,62,62,.18);'
                                    : waAlertLevel === 'warn'
                                        ? 'background:#ef9f27; box-shadow:0 0 0 3px rgba(239,159,39,.18);'
                                        : 'background:#9aa0ab; box-shadow:0 0 0 3px rgba(154,160,171,.14);'"
                            />
                            <span class="kpi-lbl">Tickets Triagem</span>
                        </div>
                        <p class="kpi-val">{{ kpis.wa_triagem.triagem }}</p>
                        <p class="kpi-hint">{{ kpis.wa_triagem.em_atendimento }} em atendimento</p>
                    </div>

                </div>

                <!-- ══ BLOCO 2: FINANCEIRO ════════════════════════════════ -->
                <p class="section-label">financeiro</p>
                <div class="fin-top">
                    <div class="panel">
                        <div class="panel-head">
                            <div>
                                <h3>Receita por Dia</h3>
                                <p>Pedidos não cancelados no período</p>
                            </div>
                        </div>
                        <ChartArea :points="fin.por_dia" color="#993c1d" value-type="currency" :height="200" />
                    </div>
                    <div class="panel">
                        <div class="panel-head">
                            <div>
                                <h3>Mesa vs Delivery</h3>
                                <p>Distribuição da receita por origem</p>
                            </div>
                        </div>
                        <ChartDonut
                            :items="fin.origem"
                            :palette="['#993c1d', '#378add']"
                            center-label="Receita"
                            :size="160"
                            empty-text="Sem pedidos no período."
                        />
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-head">
                        <div>
                            <h3>Pedidos por Hora — Hoje</h3>
                            <p>Distribuição de volume ao longo do dia</p>
                        </div>
                        <span v-if="fin.pico_hora" class="panel-tag">Pico: {{ fin.pico_hora }}</span>
                    </div>
                    <ChartBarsVertical
                        :items="fin.por_hora"
                        color="#993c1d"
                        peak-color="#1d9e75"
                        :height="140"
                    />
                </div>

                <!-- ══ BLOCO 3: OPERACIONAL ═══════════════════════════════ -->
                <p class="section-label">operacional</p>
                <div class="op-top">
                    <div class="panel">
                        <div class="panel-head">
                            <div><h3>Status dos Pedidos</h3><p>Ao vivo — atualiza a cada 30s</p></div>
                        </div>
                        <div class="status-grid">
                            <div class="status-box status-amber">
                                <span class="sb-num">{{ ops.status.pendentes }}</span>
                                <p>Pendentes</p>
                            </div>
                            <div class="status-box status-blue">
                                <span class="sb-num">{{ ops.status.em_preparo }}</span>
                                <p>Em Preparo</p>
                            </div>
                            <div class="status-box status-green">
                                <span class="sb-num">{{ ops.status.prontos }}</span>
                                <p>Prontos</p>
                            </div>
                            <div class="status-box status-gray">
                                <span class="sb-num">{{ ops.status.cancelados }}</span>
                                <p>Cancelados</p>
                            </div>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="panel-head">
                            <div><h3>Top 5 Pratos Hoje</h3><p>Por quantidade vendida</p></div>
                        </div>
                        <table class="top-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Prato</th>
                                    <th class="right">Qtd</th>
                                    <th class="right">Receita</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(p, i) in ops.top_pratos" :key="i">
                                    <td>
                                        <span :class="['medal', i === 0 ? 'medal-1' : i === 1 ? 'medal-2' : i === 2 ? 'medal-3' : '']">
                                            {{ i + 1 }}
                                        </span>
                                    </td>
                                    <td class="pname">{{ p.nome }}</td>
                                    <td class="right tqtd">{{ p.qtd }}</td>
                                    <td class="right tval">{{ p.receita }}</td>
                                </tr>
                                <tr v-if="!ops.top_pratos.length">
                                    <td colspan="4" class="nodata">Sem pedidos hoje</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="op-kpis">
                    <div class="mini-kpi">
                        <span class="mk-dot" style="background:#ef9f27" />
                        <span class="mk-val">{{ ops.prontos_aguardando }}</span>
                        <span class="mk-lbl">Prontos aguardando retirada</span>
                    </div>
                    <div class="mini-kpi">
                        <span class="mk-dot" style="background:#e53e3e" />
                        <span class="mk-val">{{ ops.cancelados_hoje }}</span>
                        <span class="mk-lbl">Cancelados hoje</span>
                    </div>
                    <div class="mini-kpi">
                        <span class="mk-dot" style="background:#378add" />
                        <span class="mk-val">{{ ops.horario_pico }}</span>
                        <span class="mk-lbl">Horário de pico</span>
                    </div>
                </div>

                <!-- ══ BLOCO 4: MESAS ═════════════════════════════════════ -->
                <p class="section-label">mesas</p>
                <div class="tables-row">
                    <div class="panel">
                        <div class="panel-head">
                            <div><h3>Status das Mesas</h3><p>Ao vivo — atualiza a cada 30s</p></div>
                        </div>
                        <div v-if="tabs.mesas.length" class="tables-grid">
                            <div
                                v-for="mesa in tabs.mesas"
                                :key="mesa.id"
                                :class="['table-chip', mesa.ocupada ? 'chip-occ' : 'chip-free']"
                            >
                                <span class="chip-num">{{ mesa.numero }}</span>
                                <span v-if="mesa.ocupada" class="chip-time">{{ elapsedTime(mesa.started_at) }}</span>
                            </div>
                        </div>
                        <p v-else class="nodata">Nenhuma mesa ativa</p>
                    </div>
                    <div class="panel">
                        <div class="panel-head">
                            <div><h3>KPIs de Mesas</h3><p>Sessões do dia</p></div>
                        </div>
                        <div class="tkpi-list">
                            <div class="tkpi-row">
                                <span class="tkpi-l">Taxa de Ocupação</span>
                                <span class="tkpi-v">{{ tabs.kpis.taxa_ocupacao }}%</span>
                            </div>
                            <div class="tkpi-row">
                                <span class="tkpi-l">Tempo Médio de Sessão</span>
                                <span class="tkpi-v">{{ tabs.kpis.tempo_medio }}</span>
                            </div>
                            <div class="tkpi-row">
                                <span class="tkpi-l">Sessões Encerradas Hoje</span>
                                <span class="tkpi-v">{{ tabs.kpis.sessoes_encerradas }}</span>
                            </div>
                            <div class="tkpi-row">
                                <span class="tkpi-l">Giro de Mesas</span>
                                <span class="tkpi-v">{{ tabs.kpis.giro }}×</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══ BLOCO 5: WHATSAPP & DELIVERY ══════════════════════ -->
                <p class="section-label">whatsapp &amp; delivery</p>
                <div class="wa-row">
                    <div class="panel">
                        <div class="panel-head">
                            <div><h3>Funil de Tickets</h3><p>Estado atual dos atendimentos</p></div>
                        </div>
                        <div class="funil">
                            <div :class="['funil-step', wa.funil.triagem > 3 ? 'fs-danger' : wa.funil.triagem > 0 ? 'fs-warn' : 'fs-neutral']">
                                <span v-if="wa.funil.triagem > 3" class="pulse" />
                                <span class="fs-num">{{ wa.funil.triagem }}</span>
                                <span class="fs-lbl">Triagem</span>
                            </div>
                            <svg class="funil-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>
                            <div class="funil-step fs-blue">
                                <span class="fs-num">{{ wa.funil.em_atendimento }}</span>
                                <span class="fs-lbl">Atendimento</span>
                            </div>
                            <svg class="funil-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>
                            <div class="funil-step fs-green">
                                <span class="fs-num">{{ wa.funil.encerrados_hoje }}</span>
                                <span class="fs-lbl">Encerrados</span>
                            </div>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="panel-head">
                            <div><h3>Delivery Hoje</h3><p>Pedidos via WhatsApp</p></div>
                        </div>
                        <div class="dkpi-list">
                            <div class="dkpi-row">
                                <span class="dkpi-l">Pedidos</span>
                                <span class="dkpi-v">{{ wa.delivery.pedidos }}</span>
                            </div>
                            <div class="dkpi-row">
                                <span class="dkpi-l">Receita</span>
                                <span class="dkpi-v accent">{{ wa.delivery.receita }}</span>
                            </div>
                            <div class="dkpi-row">
                                <span class="dkpi-l">Ticket Médio</span>
                                <span class="dkpi-v">{{ wa.delivery.ticket_medio }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="panel-head">
                            <div><h3>Receita Delivery</h3><p>Por dia no período</p></div>
                        </div>
                        <ChartBarsVertical
                            :items="wa.delivery_historico"
                            color="#378add"
                            :height="130"
                            :show-labels="false"
                            empty-text="Sem entregas no período."
                        />
                    </div>
                </div>

                <!-- ══ BLOCO 6: CARDÁPIO ══════════════════════════════════ -->
                <p class="section-label">cardápio</p>
                <div class="menu-row">
                    <div class="panel">
                        <div class="panel-head">
                            <div><h3>Status do Cardápio</h3><p>Pratos cadastrados</p></div>
                        </div>
                        <div class="menu-kpis">
                            <div class="menu-card menu-active">
                                <span class="mc-val">{{ menu.ativos }}</span>
                                <span class="mc-lbl">Ativos</span>
                            </div>
                            <div class="menu-card menu-inactive">
                                <span class="mc-val">{{ menu.inativos }}</span>
                                <span class="mc-lbl">Inativos</span>
                            </div>
                        </div>
                    </div>
                    <div class="panel">
                        <div class="panel-head">
                            <div><h3>Pratos por Categoria</h3><p>Apenas pratos ativos</p></div>
                        </div>
                        <ChartBars :items="menu.categorias" color="#993c1d" empty-text="Nenhuma categoria." />
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped src="./styles/Dashboard.css"></style>
