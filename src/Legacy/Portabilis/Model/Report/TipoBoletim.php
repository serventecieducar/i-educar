<?php

/**
 * Enum legado de tipos de boletim (campos `tipo_boletim` / `tipo_boletim_diferenciado` em turmas).
 *
 * Histórico: a implementação original vinha do pacote `portabilis/i-educar-reports-package`, hoje
 * descontinuado em vários ambientes. O core mantém esta classe para:
 * - cadastro de turma (`educar_turma_cad.php`);
 * - API de turmas (`TurmaController`);
 * - atualização em lote de modelo de boletim (`UpdateSchoolClassReportCardController`).
 *
 * Não depende de Jasper nem de ficheiros do pacote Portabilis: `getEnums()` alimenta apenas os
 * selects; `getReports()` devolve chaves estáveis compatíveis com código legado (não garante
 * geração de PDF sem um motor de relatórios à parte).
 *
 * Localização: `src/Legacy/...` — código de compatibilidade fora de `ieducar/lib`, com entrada
 * explícita no `classmap` do `composer.json` para autoload fiável após `composer dump-autoload`.
 */
class Portabilis_Model_Report_TipoBoletim extends CoreExt_Enum
{
    const NUMERIC = 1;

    const CONCEPTUAL = 2;

    const CONCEPTUAL_LANDSCAPE = 3;

    const PARECER_DESCRITIVO_COMPONENTE = 9;

    const PARECER_DESCRITIVO_GERAL = 10;

    protected $_data = [
        self::NUMERIC => 'Boletim numérico',
        self::CONCEPTUAL => 'Boletim conceitual',
        self::PARECER_DESCRITIVO_COMPONENTE => 'Parecer descritivo por componente',
        self::PARECER_DESCRITIVO_GERAL => 'Parecer descritivo geral',
    ];

    /** @var array<int, string> chaves estáveis (legado Jasper / API) */
    protected $_reports = [
        self::NUMERIC => 'report-card',
        self::CONCEPTUAL => 'conceptual-report-card',
        self::CONCEPTUAL_LANDSCAPE => 'conceptual-landscape-report-card',
        self::PARECER_DESCRITIVO_COMPONENTE => 'descriptive-opinion-report-card',
        self::PARECER_DESCRITIVO_GERAL => 'general-opinion-report-card',
    ];

    public function getReports()
    {
        return $this->_reports;
    }

    public static function getInstance()
    {
        return self::_getInstance(__CLASS__);
    }
}
