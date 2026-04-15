<?php

namespace App\IeducarSetup\FormosaDoRioPretoBa;

final class FormosaDoRioPretoBaMunicipalData
{
    public const CIDADE = 'Formosa do Rio Preto';
    public const UF = 'BA';

    /** Nome da mantenedora (Prefeitura) em cadastros, relatórios e entidade i-Educar. */
    public const INSTITUICAO_NOME_OFICIAL = 'Prefeitura Municipal de Formosa do Rio Preto';

    /** Secretaria de Educação (órgão regional no cadastro da instituição e no rodapé). */
    public const ORGAO_REGIONAL_EDUCACAO = 'Secretaria Municipal de Educação';

    public const SECRETARIA_OFICIAL = self::ORGAO_REGIONAL_EDUCACAO;

    public const PREFEITO = 'Manoel Afonso de Araújo';

    public const SECRETARIO_EDUCACAO = 'Marinélia da Silva Rocha';

    /** Referência do ato de nomeação do(a) secretário(a) de educação. */
    public const ATO_NOMEACAO_SECRETARIO = 'Portaria nº 724 de 21 de março de 2023';

    /** CEP sede (faixa municipal ~47990-000 a 47999-999). Ajuste ao logradouro real se necessário. */
    public const CEP_PADRAO = '47990000';

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

