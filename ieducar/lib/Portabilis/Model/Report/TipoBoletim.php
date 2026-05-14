<?php

/**
 * Cópia alinhada ao `portabilis/i-educar-reports-package` (ieducar/Tipos/TipoBoletim.php).
 *
 * Mantida no core para ambientes em que o pacote de relatórios não está no `vendor/`
 * (ex.: deploy sem plug-and-play / `composer install` mínimo), evitando erro ao abrir
 * o cadastro de turma. Quando o pacote oficial estiver instalado, o Composer costuma
 * mapear apenas um caminho para esta classe; este ficheiro deve permanecer idêntico
 * ao do pacote para não divergir comportamento.
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
