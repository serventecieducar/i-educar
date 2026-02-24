<?php

namespace App\Console\Commands;

use App\Models\LegacyEvaluationRule;
use App\Models\LegacyEvaluationRuleGradeYear;
use App\Models\LegacyGrade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RegraAvaliacao_Model_Nota_TipoValor;
use RegraAvaliacao_Model_TipoParecerDescritivo;
use RegraAvaliacao_Model_TipoPresenca;
use RegraAvaliacao_Model_TipoProgressao;

class EducacaoInfantilRegraQualitativa extends Command
{
    protected $signature = 'ieducar:educacao-infantil-regra-qualitativa
                            {--instituicao= : ID da instituição (opcional, processa todas se omitido)}';

    protected $description = 'Transforma as regras de avaliação da Educação Infantil em qualitativas: sem reprovação, com parecer descritivo por etapa e componente';

    public function handle(): int
    {
        $this->info('🎓 Configurando regras qualitativas para Educação Infantil...');

        $instituicaoId = $this->option('instituicao');
        $query = DB::table('pmieducar.instituicao')->whereNull('data_exclusao');
        if ($instituicaoId) {
            $query->where('cod_instituicao', $instituicaoId);
        }
        $instituicoes = $query->get();

        if ($instituicoes->isEmpty()) {
            $this->error('Nenhuma instituição encontrada.');
            return 1;
        }

        $totalAtualizadas = 0;

        /** @var \stdClass $instituicao */
        foreach ($instituicoes as $instituicao) {
            $totalAtualizadas += $this->processarInstituicao((int) $instituicao->cod_instituicao);
        }

        $this->info("✅ Concluído. {$totalAtualizadas} série(s)/ano(s) atualizada(s) com regra qualitativa.");

        return 0;
    }

    private function processarInstituicao(int $instituicaoId): int
    {
        $cursoEI = DB::table('pmieducar.curso')
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('nm_curso', 'Educação Infantil')
            ->whereNull('data_exclusao')
            ->first();

        if (!$cursoEI) {
            $this->warn("Instituição {$instituicaoId}: Curso 'Educação Infantil' não encontrado.");

            return 0;
        }

        $grades = LegacyGrade::query()
            ->where('ref_cod_curso', $cursoEI->cod_curso)
            ->where('ativo', 1)
            ->get();

        if ($grades->isEmpty()) {
            $this->warn("Instituição {$instituicaoId}: Nenhuma série da Educação Infantil encontrada.");

            return 0;
        }

        $regraQualitativa = $this->obterOuCriarRegraQualitativa($instituicaoId);
        $count = 0;

        foreach ($grades as $grade) {
            $atualizados = LegacyEvaluationRuleGradeYear::query()
                ->where('serie_id', $grade->cod_serie)
                ->update(['regra_avaliacao_id' => $regraQualitativa->id]);

            $count += $atualizados;
        }

        if ($count > 0) {
            $this->line("  Instituição {$instituicaoId}: {$count} vínculo(s) atualizado(s) para '{$regraQualitativa->nome}'");
        }

        return $count;
    }

    private function obterOuCriarRegraQualitativa(int $instituicaoId): LegacyEvaluationRule
    {
        $nomeRegra = 'Educação Infantil - Qualitativa (parecer)';

        $regra = LegacyEvaluationRule::query()
            ->where('instituicao_id', $instituicaoId)
            ->where('nome', $nomeRegra)
            ->first();

        if ($regra) {
            return $regra;
        }

        $regra = new LegacyEvaluationRule();
        $regra->instituicao_id = $instituicaoId;
        $regra->nome = $nomeRegra;
        $regra->tipo_nota = RegraAvaliacao_Model_Nota_TipoValor::NENHUM;
        $regra->tipo_progressao = RegraAvaliacao_Model_TipoProgressao::CONTINUADA;
        $regra->tipo_presenca = RegraAvaliacao_Model_TipoPresenca::POR_COMPONENTE;
        $regra->parecer_descritivo = RegraAvaliacao_Model_TipoParecerDescritivo::ETAPA_COMPONENTE;
        $formula = DB::table('modules.formula_media')->where('instituicao_id', $instituicaoId)->first();
        $tabela = DB::table('modules.tabela_arredondamento')->where('instituicao_id', $instituicaoId)->first();
        $regra->formula_media_id = $formula?->id ?? 1;
        $regra->formula_recuperacao_id = null;
        $regra->tabela_arredondamento_id = $tabela?->id ?? 1;
        $regra->tabela_arredondamento_id_conceitual = $tabela?->id ?? 2;
        $regra->media = 0;
        $regra->porcentagem_presenca = 75;
        $regra->media_recuperacao = 0;
        $regra->tipo_recuperacao_paralela = 0;
        $regra->nota_maxima_geral = 10;
        $regra->nota_minima_geral = 0;
        $regra->nota_maxima_exame_final = 10;
        $regra->qtd_casas_decimais = 2;
        $regra->nota_geral_por_etapa = 0;
        $regra->reprovacao_automatica = 0;
        $regra->aprova_media_disciplina = 0;

        $regra->save();

        $this->line("  Nova regra criada: '{$regra->nome}' (ID: {$regra->id})");

        return $regra;
    }
}
