<x-pulse>
    {{-- Título e identidade i-Educar --}}
    <div class="default:col-span-full ieducar-pulse-section ieducar-pulse-section--first">
        <h2 class="ieducar-pulse-section-title">Visão geral</h2>
        <p class="ieducar-pulse-section-subtitle">Servidores, uso, filas e cache do ambiente.</p>
    </div>

    {{-- Servidores: linha inteira --}}
    <div class="default:col-span-full flex flex-col gap-0">
        <livewire:pulse.servers cols="full" />
        <div class="ieducar-pulse-desc -mt-1">
            <p><strong>Servidores.</strong> Estado dos servidores que reportam ao Pulse (CPU, memória, disco). Útil para monitorar a carga do ambiente onde o i-Educar roda.</p>
        </div>
    </div>

    {{-- Uso, Filas e Cache: três colunas lado a lado, mesma altura (rows="2") --}}
    <div class="default:col-span-4 flex flex-col gap-0 ieducar-pulse-card-wrap">
        <livewire:pulse.usage cols="4" rows="2" />
        <div class="ieducar-pulse-desc -mt-1">
            <p><strong>Uso (Usage).</strong> Top usuários por requisições, endpoints lentos ou jobs. Seletor: requisições / lentas / jobs.</p>
        </div>
    </div>
    <div class="default:col-span-4 flex flex-col gap-0 ieducar-pulse-card-wrap">
        <livewire:pulse.queues cols="4" rows="2" />
        <div class="ieducar-pulse-desc -mt-1">
            <p><strong>Filas (Queues).</strong> Jobs processados, falhas e tempo de espera por fila. Requer driver de fila (redis, database, etc.) e workers rodando.</p>
        </div>
    </div>
    <div class="default:col-span-4 flex flex-col gap-0 ieducar-pulse-card-wrap">
        <livewire:pulse.cache cols="4" rows="2" />
        <div class="ieducar-pulse-desc -mt-1">
            <p><strong>Cache.</strong> Leituras e gravações no cache (hits/misses). Ajuda a identificar abuso ou padrões de uso do cache (ex.: Redis, file).</p>
        </div>
    </div>

    {{-- Métricas com limite (threshold) --}}
    <div class="default:col-span-full ieducar-pulse-section">
        <h2 class="ieducar-pulse-section-title">Métricas com limite (threshold)</h2>
        <p class="ieducar-pulse-section-subtitle">Cards abaixo mostram apenas itens que ultrapassam o limite configurado (consultas/requisições/jobs lentos).</p>
    </div>

    {{-- Consultas lentas + Exceções: 6+6 --}}
    <div class="default:col-span-6 flex flex-col gap-0">
        <livewire:pulse.slow-queries cols="6" />
        <div class="ieducar-pulse-desc -mt-1">
            <p><strong>Consultas lentas (Slow Queries).</strong> SQL que ultrapassam o limite em ms (configurável em <code>config/pulse.php</code>). Exibe query, quantidade de execuções e tempo máximo.</p>
        </div>
    </div>
    <div class="default:col-span-6 flex flex-col gap-0">
        <livewire:pulse.exceptions cols="6" />
        <div class="ieducar-pulse-desc -mt-1">
            <p><strong>Exceções.</strong> Erros e exceções reportadas pela aplicação (classe, mensagem, arquivo e linha). Ajuda a acompanhar bugs em produção.</p>
        </div>
    </div>

    {{-- Requisições lentas + Jobs lentos: 6+6 --}}
    <div class="default:col-span-6 flex flex-col gap-0">
        <livewire:pulse.slow-requests cols="6" />
        <div class="ieducar-pulse-desc -mt-1">
            <p><strong>Requisições lentas (Slow Requests).</strong> Rotas HTTP que demoraram mais que o limite (ms). Método, URI e tempo. Use para identificar endpoints pesados.</p>
        </div>
    </div>
    <div class="default:col-span-6 flex flex-col gap-0">
        <livewire:pulse.slow-jobs cols="6" />
        <div class="ieducar-pulse-desc -mt-1">
            <p><strong>Jobs lentos (Slow Jobs).</strong> Jobs de fila que excederam o tempo configurado. Exibe classe do job, tempo e quantidade.</p>
        </div>
    </div>

    {{-- Requisições de saída lentas: linha inteira --}}
    <div class="default:col-span-full flex flex-col gap-0">
        <livewire:pulse.slow-outgoing-requests cols="full" />
        <div class="ieducar-pulse-desc -mt-1">
            <p><strong>Requisições de saída lentas.</strong> Chamadas HTTP externas (Guzzle, etc.) que passaram do limite em ms. Útil para monitorar integrações e APIs terceiras.</p>
        </div>
    </div>

    {{-- Carga completa do sistema --}}
    <div class="default:col-span-full ieducar-pulse-section ieducar-pulse-section--full">
        <h2 class="ieducar-pulse-section-title">Carga completa do sistema</h2>
        <p class="ieducar-pulse-section-subtitle">Métricas sem filtro de limite: todas as requisições, jobs e consultas no período, para visão total da carga.</p>
    </div>

    {{-- Requisições (carga completa) + Jobs (carga completa): 6+6 --}}
    <div class="default:col-span-6 flex flex-col gap-0">
        <livewire:pulse.usage usage="requests" cols="6" />
        <div class="ieducar-pulse-desc -mt-1">
            <p><strong>Requisições (carga completa).</strong> Top usuários por total de requisições no período, sem filtro de tempo. Visão geral do uso da aplicação.</p>
        </div>
    </div>
    <div class="default:col-span-6 flex flex-col gap-0">
        <livewire:pulse.usage usage="jobs" cols="6" />
        <div class="ieducar-pulse-desc -mt-1">
            <p><strong>Jobs (carga completa).</strong> Top usuários por total de jobs despachados no período, sem filtro de tempo. Carga total de filas.</p>
        </div>
    </div>

    {{-- Queries mais usadas (carga completa): linha inteira --}}
    <div class="default:col-span-full flex flex-col gap-0">
        <livewire:pulse.most-used-queries cols="full" />
        <div class="ieducar-pulse-desc -mt-1">
            <p><strong>Consultas mais usadas (carga completa).</strong> SQL mais executados no sistema (agrupados por texto normalizado), sem filtro de tempo. Configurável em <code>config/ieducar-pulse.php</code>.</p>
        </div>
    </div>

    {{-- Relatórios gráficos e análise --}}
    <div class="default:col-span-full ieducar-pulse-section ieducar-pulse-section--reports">
        <h2 class="ieducar-pulse-section-title">Relatórios e análise</h2>
        <p class="ieducar-pulse-section-subtitle">Totais do período para comparativo entre intervalos (ex.: 1h vs 24h) e análise de tendências.</p>
    </div>

    <div class="default:col-span-full flex flex-col gap-0">
        <livewire:pulse.summary-totals cols="full" />
        <div class="ieducar-pulse-desc -mt-1">
            <p><strong>Resumo do período.</strong> Total de requisições, jobs e exceções no intervalo selecionado. Altere o período no topo do dashboard para comparar (ex.: 1h, 6h, 24h, 7 dias).</p>
        </div>
    </div>

    <div class="default:col-span-full flex flex-col gap-0">
        <livewire:pulse.metrics-graph cols="full" />
        <div class="ieducar-pulse-desc -mt-1">
            <p><strong>Evolução no tempo.</strong> Gráfico de barras por intervalo: requisições e exceções ao longo do período. Use para identificar picos e tendências.</p>
        </div>
    </div>
</x-pulse>

<style>
    .ieducar-pulse-section {
        margin-top: 1.75rem;
        margin-bottom: 0.75rem;
        padding: 1rem 1.25rem;
        border-radius: 0.5rem;
        border-left: 4px solid #47728f;
        background: linear-gradient(135deg, rgb(249 250 251) 0%, rgb(243 247 250) 100%);
        box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
    }
    .ieducar-pulse-section:first-child,
    .ieducar-pulse-section.ieducar-pulse-section--first {
        margin-top: 0;
    }
    .ieducar-pulse-section--threshold {
        border-left-color: #b45309;
        background: linear-gradient(135deg, rgb(255 251 247) 0%, rgb(254 248 240) 100%);
    }
    .ieducar-pulse-section--full {
        border-left-color: #0d9488;
        background: linear-gradient(135deg, rgb(240 253 250) 0%, rgb(236 252 248) 100%);
    }
    .ieducar-pulse-section--reports {
        border-left-color: #7c3aed;
        background: linear-gradient(135deg, rgb(250 245 255) 0%, rgb(245 240 255) 100%);
    }
    .dark .ieducar-pulse-section {
        background: linear-gradient(135deg, rgb(31 41 55 / 0.7) 0%, rgb(30 41 55) 100%);
        border-left-color: #6b9bb8;
        box-shadow: 0 1px 3px rgb(0 0 0 / 0.2);
    }
    .dark .ieducar-pulse-section--threshold { border-left-color: #f59e0b; }
    .dark .ieducar-pulse-section--full { border-left-color: #2dd4bf; }
    .dark .ieducar-pulse-section--reports { border-left-color: #a78bfa; }
    .ieducar-pulse-card-wrap {
        min-height: 320px;
    }
    .ieducar-pulse-section-title {
        font-family: "Open Sans", Arial, sans-serif;
        font-size: 1.25rem;
        font-weight: 700;
        color: #47728f;
        margin: 0 0 0.35rem 0;
        letter-spacing: 0.02em;
    }
    .ieducar-pulse-section--threshold .ieducar-pulse-section-title { color: #b45309; }
    .ieducar-pulse-section--full .ieducar-pulse-section-title { color: #0d9488; }
    .ieducar-pulse-section--reports .ieducar-pulse-section-title { color: #7c3aed; }
    .dark .ieducar-pulse-section-title {
        color: #6b9bb8;
    }
    .dark .ieducar-pulse-section--threshold .ieducar-pulse-section-title { color: #f59e0b; }
    .dark .ieducar-pulse-section--full .ieducar-pulse-section-title { color: #2dd4bf; }
    .dark .ieducar-pulse-section--reports .ieducar-pulse-section-title { color: #a78bfa; }
    .ieducar-pulse-section-subtitle {
        font-family: "Open Sans", Arial, sans-serif;
        font-size: 0.8125rem;
        color: rgb(107 114 128);
        margin: 0;
        line-height: 1.4;
    }
    .dark .ieducar-pulse-section-subtitle {
        color: rgb(156 163 175);
    }
    .ieducar-pulse-desc {
        padding: 0.5rem 1rem 0.75rem;
        font-size: 0.75rem;
        line-height: 1.35;
        color: rgb(107 114 128);
        background: rgb(249 250 251 / 0.9);
        border-top: 1px solid rgb(243 244 246);
        border-radius: 0 0 0.75rem 0.75rem;
    }
    .dark .ieducar-pulse-desc {
        color: rgb(156 163 175);
        background: rgb(31 41 55 / 0.5);
        border-top-color: rgb(55 65 81);
    }
    .ieducar-pulse-desc strong {
        color: rgb(75 85 99);
        font-weight: 600;
    }
    .dark .ieducar-pulse-desc strong {
        color: rgb(209 213 219);
    }
    .ieducar-pulse-desc code {
        padding: 0.125rem 0.375rem;
        font-size: 0.6875rem;
        background: rgb(243 244 246);
        border-radius: 0.25rem;
        color: rgb(55 65 99);
    }
    .dark .ieducar-pulse-desc code {
        background: rgb(55 65 81);
        color: rgb(209 213 219);
    }
    .ieducar-summary-totals {
        font-family: "Open Sans", Arial, sans-serif;
    }
    .ieducar-summary-item {
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        background: rgb(249 250 251);
        border: 1px solid rgb(243 244 246);
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    .dark .ieducar-summary-item {
        background: rgb(31 41 55 / 0.6);
        border-color: rgb(55 65 81);
    }
    .ieducar-summary-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: rgb(107 114 128);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .dark .ieducar-summary-label {
        color: rgb(156 163 175);
    }
    .ieducar-summary-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #47728f;
        line-height: 1.2;
    }
    .dark .ieducar-summary-value {
        color: #6b9bb8;
    }
    .ieducar-summary-exceptions .ieducar-summary-value {
        color: rgb(185 28 28);
    }
    .dark .ieducar-summary-exceptions .ieducar-summary-value {
        color: rgb(248 113 113);
    }
</style>
