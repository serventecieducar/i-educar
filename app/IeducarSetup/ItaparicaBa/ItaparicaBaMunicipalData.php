<?php

namespace App\IeducarSetup\ItaparicaBa;

/**
 * Dados do município de Itaparica/BA e cadastro das unidades escolares.
 *
 * Importante:
 * - Este arquivo deve ser completado com dados oficiais da SME.
 * - O fluxo do setup segue a mesma lógica de Formosa do Rio Preto/BA:
 *   trimestres (30/30/40), fórmula de média, regras de recuperação, calendário 2026 e restrições de cursos.
 */
final class ItaparicaBaMunicipalData
{
    public const CIDADE = 'Itaparica';

    public const UF = 'BA';

    /** Nome da mantenedora (Prefeitura) em cadastros, relatórios e entidade i-Educar. */
    public const INSTITUICAO_NOME_OFICIAL = 'Prefeitura Municipal de Itaparica';

    /** Secretaria de Educação (como deve sair no cabeçalho dos documentos). */
    public const ORGAO_REGIONAL_EDUCACAO = 'Secretaria Municipal de Educação e Esportes de Itaparica';

    public const SECRETARIA_OFICIAL = self::ORGAO_REGIONAL_EDUCACAO;

    public const PREFEITO = 'José Elias das Virgens Oliveira';

    public const SECRETARIO_EDUCACAO = 'Larissa Oliveira de Jesus Lima';

    public const ATO_NOMEACAO_SECRETARIO = 'Decreto Nº 2632/2024';

    /**
     * CEP sede (seed). Ajuste ao logradouro real se necessário.
     * Itaparica/BA possui mais de um CEP dependendo do distrito/bairro.
     */
    public const CEP_PADRAO = '44460000';

    /** Endereço oficial (referência para cadastro da instituição). */
    public const ENDERECO_LOGRADOURO = 'Praça Municipal';

    public const ENDERECO_NUMERO = 0;

    public const ENDERECO_BAIRRO = 'Centro';

    public const TELEFONE_DDD = 71;

    /** Telefone principal (sem DDD, apenas números). */
    public const TELEFONE_NUMERO = 0;

    /** Regime avaliativo do município (referência para relatórios / rodapé interno). */
    public const FORMA_AVALIATIVA = 'Quantitativa (nota numérica)';

    /** Trimestres com pesos em percentual (30/30/40). */
    public const PESOS_TRIMESTRAIS = [30, 30, 40];

    /** Média mínima para aprovação (final do ano). */
    public const MEDIA_APROVACAO = 5.0;

    /**
     * Recuperação paralela: não utilizada (somente recuperação final ao fim do ano).
     * Mantemos a constante para compatibilidade com o fluxo, mas o seeder não aplica recuperação paralela.
     */
    public const MEDIA_RECUPERACAO_PARALELA = 0.0;

    /** Média mínima para aprovação na recuperação final. */
    public const MEDIA_RECUPERACAO_FINAL = 5.0;

    /** Frequência mínima percentual padrão. */
    public const FREQUENCIA_MINIMA_PERCENTUAL = 75.0;

    private function __construct()
    {
        // static only
    }
}

