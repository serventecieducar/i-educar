{{--
    Cabeçalho padrão de impressão (conforme EnrollmentQuantitativeMap / header-landscape-listing).
    Usado em bi-print-wrapper e no PDF de gráficos do BI.
    Sem linha de nome da escola (não faz sentido no contexto do BI).
    @param string $title Título da página/seção
    @param string|null $logoBase64 Logo em base64 (para PDF); quando null, usa URL
--}}
@props([
    'title' => '',
])

@php
    $logoBase64 = $logoBase64 ?? (isset($attributes) ? $attributes->get('logoBase64') : null);
    if ($logoBase64) {
        $logoSrc = $logoBase64;
    } else {
        $logoPath = config('legacy.config.ieducar_image') ?? config('legacy.app.template.pdf.logo');
        $logoSrc = $logoPath
            ? (str_starts_with($logoPath, 'http') ? $logoPath : (Asset::get($logoPath) ?? url($logoPath)))
            : url('intranet/imagens/brasao-republica.png');
    }
@endphp
<div class="bi-print-header-layout" style="border: 1px solid #ddd; display: table; width: 100%; margin-bottom: 4px;">
    <div style="display: table-cell; width: 67px; vertical-align: top; background: #f5f5f5; border-right: 1px solid #ddd; padding: 4px;">
        <img src="{{ $logoSrc }}" alt="Logo" style="max-width: 59px; max-height: 55px; display: block; margin: 0 auto;">
    </div>
    <div style="display: table-cell; vertical-align: top; padding: 4px 8px; font-family: DejaVu Sans, sans-serif; font-size: 9px;">
        <div style="font-size: 9px; margin-bottom: 2px;">{{ $headerNmInstituicao ?? mb_strtoupper(config('legacy.config.ieducar_entity_name') ?? config('legacy.app.entity.name') ?? 'i-Educar', 'UTF-8') }}</div>
        @if(!empty($headerNmResponsavel))
        <div style="font-size: 9px; margin-bottom: 2px;">{{ $headerNmResponsavel }}</div>
        @endif
        @if(!empty($headerEndereco))
        <div style="font-size: 8px; margin-bottom: 2px;">{{ $headerEndereco }}</div>
        @endif
        @if(!empty($headerTelefone))
        <div style="font-size: 8px;"><strong>Telefone:</strong> {{ $headerTelefone }}</div>
        @endif
    </div>
</div>
<div style="text-align: center; font-size: 12px; font-weight: bold; margin: 6px 0; font-family: DejaVu Sans, sans-serif;">{{ $title ?: (config('legacy.app.template.pdf.titulo') ?? 'Relatório i-Educar') }}</div>
