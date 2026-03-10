{{--
    Rodapé padrão de impressão do sistema (BI e relatórios).
    Usado em bi-print-wrapper e no PDF de gráficos.
--}}
<hr style="margin: 10px 0; border-color: #ddd;">
<div style="font-size: 10px; color: #666;">
    {{ now()->translatedFormat('d/m/Y H:i') }}
    @if(!empty(config('legacy.config.ieducar_internal_footer')))
        <br>{!! config('legacy.config.ieducar_internal_footer') !!}
    @else
        <br>Produzido por {{ config('legacy.config.ieducar_entity_name') ?? 'i-Educar' }}
    @endif
</div>
