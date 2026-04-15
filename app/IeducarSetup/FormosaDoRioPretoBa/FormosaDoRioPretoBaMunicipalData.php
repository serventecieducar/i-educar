<?php

namespace App\IeducarSetup\FormosaDoRioPretoBa;

final class FormosaDoRioPretoBaMunicipalData
{
    public const CIDADE = 'Formosa do Rio Preto';
    public const UF = 'BA';

    public const SECRETARIA_OFICIAL = 'Secretaria Municipal de Educação';

    /** Regime avaliativo do município (referência para relatórios / rodapé interno). */
    public const FORMA_AVALIATIVA = 'Quantitativa (nota numérica)';

    /** Trimestres com pesos em percentual (30/30/40). */
    public const PESOS_TRIMESTRAIS = [30, 30, 40];

    /** Média mínima para aprovação (final do ano). */
    public const MEDIA_APROVACAO = 6.0;

    /** Média mínima para aprovação na recuperação paralela. */
    public const MEDIA_RECUPERACAO_PARALELA = 6.0;

    /** Média mínima para aprovação na recuperação final. */
    public const MEDIA_RECUPERACAO_FINAL = 5.0;
}

