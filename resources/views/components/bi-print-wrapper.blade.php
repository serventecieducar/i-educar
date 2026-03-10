{{--
    Componente para envolver conteúdo de BI com suporte à impressão e exportação Excel.
    Inclui cabeçalho e rodapé padrões do sistema (configuráveis em Configurações).

    Uso:
    <x-bi-print-wrapper title="BI - Matrículas" :export-url="route('bis.theme.export', ['theme' => 'matriculas'])">
        ... gráficos e dados ...
    </x-bi-print-wrapper>
--}}
@props([
    'title' => 'BI',
    'exportUrl' => null,
    'showTopActions' => true,
])

<div class="bi-print-wrapper" data-bi-print>
    {{-- Cabeçalho de impressão (visível apenas ao imprimir) --}}
    <div class="bi-print-header bi-print-only">
        @include('components.bi-print-header', ['title' => $title])
    </div>

    {{-- Botões imprimir e exportar (ocultos na impressão) --}}
    @if($showTopActions)
    <div class="bi-print-actions bi-no-print">
        <button type="button" class="btn btn-primary bi-print-btn" onclick="window.print()" title="Imprimir gráficos e dados">
            <i class="fa fa-print"></i> Imprimir
        </button>
        @if($exportUrl)
        <a href="{{ $exportUrl }}" class="btn btn-success bi-print-btn" title="Exportar para Excel">
            <i class="fa fa-file-excel-o"></i> Exportar Excel
        </a>
        @endif
    </div>
    @endif

    {{-- Conteúdo (gráficos, tabelas) --}}
    <div class="bi-print-content">
        {{ $slot }}
    </div>

    {{-- Rodapé de impressão (visível apenas ao imprimir) --}}
    <div class="bi-print-footer bi-print-only">
        @include('components.bi-print-footer')
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="{{ Asset::get('css/bi-print.css') }}">
@endpush
