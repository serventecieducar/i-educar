<?php

namespace Database\Seeders\Setup;

use App\IeducarSetup\FormosaDoRioPretoBa\FormosaDoRioPretoBaMunicipalData;
use App\Models\LegacyAverageFormula;
use App\Models\LegacyCourse;
use App\Models\LegacyGeneralConfiguration;
use App\Models\LegacyInstitution;
use App\Models\LegacySchool;
use App\Models\LegacyStageType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Setup específico Formosa do Rio Preto/BA.
 *
 * Objetivos (para 2026):
 * - Notas numéricas, 3 trimestres com pesos 30/30/40.
 * - Recuperação paralela com média 60% (6,0) e recuperação final com média 50% (5,0).
 * - Não ofertar Ensino Médio (somente Educação Infantil e Ensino Fundamental).
 * - Todas as escolas com cursos EI + Fundamental habilitados para 2026.
 * - Não criar turmas automaticamente em 2026 (apenas permitir cadastro manual).
 * - Montar calendário (ano letivo modular) em 3 trimestres para 2026.
 *
 * Execução:
 * - `php artisan ieducar:setup formosa-do-rio-preto-ba`
 *
 * Observações:
 * - Este seeder roda APÓS o seed padrão (SeederMaster). Qualquer ajuste/correção do padrão
 *   para o município deve ser feito aqui.
 * - O seed padrão cria cursos/séries/turmas para ano atual e anterior. Aqui nós:
 *   - mantemos os vínculos escola-curso e escola-série para 2026 (para permitir cadastrar turmas),
 *   - mas desativamos as turmas geradas automaticamente em 2026.
 */
class FormosaDoRioPretoBaSetupSeeder extends Seeder
{
    private const USUARIO_CAD = 1;

    /** @see \RegraAvaliacao_Model_Nota_TipoValor::NUMERICA */
    private const TIPO_NOTA_NUMERICA = 1;

    /** @see \App\Models\LegacyEvaluationRule::PARALLEL_REMEDIAL_PER_STAGE */
    private const RECUPERACAO_PARALELA_POR_ETAPA = 1;

    public function run(): void
    {
        DB::transaction(function (): void {
            $instituicao = LegacyInstitution::query()->whereKey(1)->first();
            if ($instituicao === null) {
                $this->command?->error('Instituição cod_instituicao=1 não encontrada. Rode as migrations/seeders base antes do ieducar:setup formosa-do-rio-preto-ba.');

                return;
            }

            $this->aplicarInstituicaoDadosOficiais($instituicao);
            $this->aplicarConfiguracoesGerais((int) $instituicao->getKey());
            $moduloTrimestre = $this->garantirModuloTresTrimestres((int) $instituicao->getKey());
            $formulaMediaId = $this->garantirFormulaMediaTrimestres303040((int) $instituicao->getKey());
            $this->aplicarRegraAvaliacaoMunicipal((int) $instituicao->getKey(), $formulaMediaId);

            $this->restringirCursosParaEducacaoInfantilEFundamental((int) $instituicao->getKey());
            $this->montarCalendarioTrimestral2026((int) $instituicao->getKey(), $moduloTrimestre->cod_modulo);

            $this->desativarTurmasGeradasAutomaticamenteEm2026((int) $instituicao->getKey());
        });

        Log::channel('daily')->info('ieducar:setup [formosa-do-rio-preto-ba] — rotina municipal concluída.');
    }

    private function aplicarInstituicaoDadosOficiais(LegacyInstitution $instituicao): void
    {
        $instituicao->update([
            'nm_instituicao' => FormosaDoRioPretoBaMunicipalData::INSTITUICAO_NOME_OFICIAL,
            'ref_sigla_uf' => FormosaDoRioPretoBaMunicipalData::UF,
            'cidade' => FormosaDoRioPretoBaMunicipalData::CIDADE,
            'cep' => FormosaDoRioPretoBaMunicipalData::CEP_PADRAO,
            'nm_responsavel' => FormosaDoRioPretoBaMunicipalData::SECRETARIO_EDUCACAO,
        ]);
    }

    private function aplicarConfiguracoesGerais(int $instituicaoId): void
    {
        $rodape = sprintf(
            '<p><strong>%s</strong><br>'
            .'Prefeito(a): %s<br>'
            .'Secretário(a) de Educação: %s<br>'
            .'%s<br>'
            .'Critérios avaliativos: %s — %s (%d/%d/%d) — Recuperação paralela média %.1f — Recuperação final média %.1f.</p>',
            e(FormosaDoRioPretoBaMunicipalData::SECRETARIA_OFICIAL),
            e(FormosaDoRioPretoBaMunicipalData::PREFEITO),
            e(FormosaDoRioPretoBaMunicipalData::SECRETARIO_EDUCACAO),
            e(FormosaDoRioPretoBaMunicipalData::ATO_NOMEACAO_SECRETARIO),
            e(FormosaDoRioPretoBaMunicipalData::FORMA_AVALIATIVA),
            '3 trimestres',
            FormosaDoRioPretoBaMunicipalData::PESOS_TRIMESTRAIS[0],
            FormosaDoRioPretoBaMunicipalData::PESOS_TRIMESTRAIS[1],
            FormosaDoRioPretoBaMunicipalData::PESOS_TRIMESTRAIS[2],
            FormosaDoRioPretoBaMunicipalData::MEDIA_RECUPERACAO_PARALELA,
            FormosaDoRioPretoBaMunicipalData::MEDIA_RECUPERACAO_FINAL
        );

        LegacyGeneralConfiguration::query()->updateOrCreate(
            ['ref_cod_instituicao' => $instituicaoId],
            [
                'ieducar_entity_name' => FormosaDoRioPretoBaMunicipalData::INSTITUICAO_NOME_OFICIAL,
                'ieducar_internal_footer' => $rodape,
            ]
        );
    }

    private function garantirModuloTresTrimestres(int $instituicaoId): LegacyStageType
    {
        $modulo = LegacyStageType::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('ativo', 1)
            ->orderBy('cod_modulo')
            ->first();

        if ($modulo === null) {
            return LegacyStageType::query()->create([
                'ref_usuario_cad' => self::USUARIO_CAD,
                'nm_tipo' => 'Trimestre',
                'num_etapas' => 3,
                'descricao' => 'Três trimestres (30/30/40) — Formosa do Rio Preto/BA',
                'ref_cod_instituicao' => $instituicaoId,
                'ativo' => 1,
                'data_cadastro' => now(),
            ]);
        }

        LegacyStageType::query()->where('cod_modulo', $modulo->cod_modulo)->update([
            'nm_tipo' => 'Trimestre',
            'num_etapas' => 3,
            'descricao' => 'Três trimestres (30/30/40) — Formosa do Rio Preto/BA',
        ]);

        /** @var LegacyStageType $modulo */
        return $modulo->refresh();
    }

    private function garantirFormulaMediaTrimestres303040(int $instituicaoId): int
    {
        $nome = 'Formosa do Rio Preto-BA: Trimestres 30/30/40';
        $formula = '((C1*E1*30) + (C2*E2*30) + (C3*E3*40)) / ((C1*30) + (C2*30) + (C3*40))';

        $row = LegacyAverageFormula::query()->where('instituicao_id', $instituicaoId)->where('nome', $nome)->first();
        if ($row === null) {
            $row = LegacyAverageFormula::query()->create([
                'instituicao_id' => $instituicaoId,
                'nome' => $nome,
                'formula_media' => $formula,
                'tipo_formula' => 1, // média final
                'substitui_menor_nota_rc' => 0,
            ]);
        } else {
            $row->update([
                'formula_media' => $formula,
                'tipo_formula' => 1,
                'substitui_menor_nota_rc' => 0,
            ]);
        }

        return (int) $row->id;
    }

    private function aplicarRegraAvaliacaoMunicipal(int $instituicaoId, int $formulaMediaId): void
    {
        // Ajusta a regra quantitativa padrão (id=1) para o regime municipal.
        DB::table('modules.regra_avaliacao')
            ->where('id', 1)
            ->where('instituicao_id', $instituicaoId)
            ->update([
                // limite do campo: varchar(50)
                'nome' => 'FdoRioPreto-BA: num 3tri 30/30/40 r6/r5',
                'tipo_nota' => self::TIPO_NOTA_NUMERICA,
                'formula_media_id' => $formulaMediaId,
                // Mantém a fórmula de recuperação padrão (id=2) do seed nacional.
                'formula_recuperacao_id' => 2,
                'media' => FormosaDoRioPretoBaMunicipalData::MEDIA_APROVACAO,
                'tipo_recuperacao_paralela' => self::RECUPERACAO_PARALELA_POR_ETAPA,
                'media_recuperacao_paralela' => FormosaDoRioPretoBaMunicipalData::MEDIA_RECUPERACAO_PARALELA,
                'media_recuperacao' => FormosaDoRioPretoBaMunicipalData::MEDIA_RECUPERACAO_FINAL,
                // cálculo de recuperação paralela: média entre nota e recuperação
                'tipo_calculo_recuperacao_paralela' => 2,
            ]);
    }

    private function restringirCursosParaEducacaoInfantilEFundamental(int $instituicaoId): void
    {
        $schools = LegacySchool::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('ativo', 1)
            ->get(['cod_escola']);

        if ($schools->isEmpty()) {
            return;
        }

        $cursoInfantil = LegacyCourse::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('nm_curso', 'Educação Infantil')
            ->first();

        $cursoFundamental = LegacyCourse::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('nm_curso', 'Ensino Fundamental')
            ->first();

        $cursoMedio = LegacyCourse::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('nm_curso', 'Ensino Médio')
            ->first();

        // Garante EI + Fundamental ativos no curso (instituição).
        foreach ([$cursoInfantil, $cursoFundamental] as $curso) {
            if ($curso !== null) {
                $curso->update(['ativo' => 1]);
            }
        }

        // Desativa Ensino Médio (não ofertado).
        if ($cursoMedio !== null) {
            $cursoMedio->update(['ativo' => 0]);

            // Desativa vínculo escola-curso do Ensino Médio para evitar cadastros.
            DB::table('pmieducar.escola_curso')
                ->whereIn('ref_cod_escola', $schools->pluck('cod_escola')->all())
                ->where('ref_cod_curso', $cursoMedio->cod_curso)
                ->update([
                    'ativo' => 0,
                    'ref_usuario_exc' => self::USUARIO_CAD,
                    'data_exclusao' => now(),
                ]);
        }

        // Opcionalmente, restringe a lista de cursos ativos por escola (mantendo EI + Fundamental).
        $permitidos = array_values(array_filter([
            $cursoInfantil?->cod_curso,
            $cursoFundamental?->cod_curso,
        ]));

        if ($permitidos !== []) {
            DB::table('pmieducar.escola_curso')
                ->whereIn('ref_cod_escola', $schools->pluck('cod_escola')->all())
                ->whereNotIn('ref_cod_curso', $permitidos)
                ->update([
                    'ativo' => 0,
                    'ref_usuario_exc' => self::USUARIO_CAD,
                    'data_exclusao' => now(),
                ]);
        }
    }

    private function montarCalendarioTrimestral2026(int $instituicaoId, int $codModuloTrimestre): void
    {
        $schools = LegacySchool::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('ativo', 1)
            ->get(['cod_escola']);

        if ($schools->isEmpty()) {
            return;
        }

        // Datas de referência (ajuste municipal pode ser refinado depois).
        $etapas = [
            1 => ['inicio' => '2026-02-04', 'fim' => '2026-06-07', 'dias' => 60],
            2 => ['inicio' => '2026-06-08', 'fim' => '2026-09-07', 'dias' => 60],
            3 => ['inicio' => '2026-09-08', 'fim' => '2026-12-18', 'dias' => 80],
        ];

        foreach ($schools as $school) {
            $escolaAnoLetivoId = DB::table('pmieducar.escola_ano_letivo')
                ->where('ref_cod_escola', $school->cod_escola)
                ->where('ano', 2026)
                ->value('id');

            if (!$escolaAnoLetivoId) {
                continue;
            }

            // Substitui (idempotente): remove o calendário modular existente de 2026 para o módulo-alvo.
            DB::table('pmieducar.ano_letivo_modulo')
                ->where('ref_ref_cod_escola', $school->cod_escola)
                ->where('ref_ano', 2026)
                ->where('ref_cod_modulo', $codModuloTrimestre)
                ->delete();

            foreach ($etapas as $sequencial => $dados) {
                DB::table('pmieducar.ano_letivo_modulo')->insert([
                    'escola_ano_letivo_id' => $escolaAnoLetivoId,
                    'ref_ano' => 2026,
                    'ref_ref_cod_escola' => $school->cod_escola,
                    'sequencial' => $sequencial,
                    'ref_cod_modulo' => $codModuloTrimestre,
                    'data_inicio' => $dados['inicio'],
                    'data_fim' => $dados['fim'],
                    'dias_letivos' => $dados['dias'],
                ]);
            }
        }
    }

    private function desativarTurmasGeradasAutomaticamenteEm2026(int $instituicaoId): void
    {
        // O seed padrão cria turmas com:
        // - ref_usuario_cad = 1
        // - nm_turma = "{nm_serie} - {ano}"
        // - ano = 2026
        DB::table('pmieducar.turma')
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('ano', 2026)
            ->where('ativo', 1)
            ->where('ref_usuario_cad', self::USUARIO_CAD)
            ->where('nm_turma', 'like', '% - 2026')
            ->update([
                'ativo' => 0,
                'visivel' => 0,
                'data_exclusao' => now(),
                'ref_usuario_exc' => self::USUARIO_CAD,
            ]);
    }
}

