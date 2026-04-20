<?php

namespace Database\Seeders\Setup;

use App\IeducarSetup\ItaparicaBa\ItaparicaBaMunicipalData;
use App\Models\LegacyAverageFormula;
use App\Models\LegacyCourse;
use App\Models\LegacyGeneralConfiguration;
use App\Models\LegacyInstitution;
use App\Models\LegacySchool;
use Database\Seeders\PerfisUsuariosMunicipioSeeder;
use Database\Seeders\Setup\ItaparicaBaSchoolsSeeder;
use Database\Seeders\Setup\ItaparicaBaAeeSchoolClassesSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Setup específico Itaparica/BA, seguindo a mesma lógica de Formosa do Rio Preto/BA.
 *
 * Objetivos (para 2026):
 * - Notas numéricas, 3 trimestres com pesos 30/30/40.
 * - Recuperação paralela com média 60% (6,0) e recuperação final com média 50% (5,0).
 * - Todas as escolas com cursos EI + Fundamental habilitados para 2026.
 * - Não criar turmas automaticamente em 2026 (apenas permitir cadastro manual).
 * - Montar calendário (ano letivo modular) em 3 trimestres para 2026.
 *
 * Execução:
 * - `php artisan ieducar:setup itaparica-ba`
 *
 * Observações:
 * - Este seeder roda APÓS o seed padrão (SeederMaster). Qualquer ajuste/correção do padrão
 *   para o município deve ser feito aqui.
 */
class ItaparicaBaSetupSeeder extends Seeder
{
    private const USUARIO_CAD = 1;

    /** @see \RegraAvaliacao_Model_Nota_TipoValor::NUMERICA */
    private const TIPO_NOTA_NUMERICA = 1;

    /** @see \App\Models\LegacyEvaluationRule::PARALLEL_REMEDIAL_PER_STAGE */
    private const RECUPERACAO_PARALELA_POR_ETAPA = 1;

    /** Sem recuperação paralela (somente recuperação final ao fim do ano). */
    private const SEM_RECUPERACAO_PARALELA = 0;

    public function run(): void
    {
        DB::transaction(function (): void {
            $instituicao = LegacyInstitution::query()->whereKey(1)->first();
            if ($instituicao === null) {
                $this->command?->error('Instituição cod_instituicao=1 não encontrada. Rode as migrations/seeders base antes do ieducar:setup itaparica-ba.');

                return;
            }

            $this->aplicarInstituicaoDadosOficiais($instituicao);
            $this->aplicarConfiguracoesGerais((int) $instituicao->getKey());
            $moduloTrimestre = $this->garantirModuloTresTrimestres((int) $instituicao->getKey());
            $formulaMediaId = $this->garantirFormulaMediaTrimestres303040((int) $instituicao->getKey());
            $this->aplicarRegraAvaliacaoMunicipal((int) $instituicao->getKey(), $formulaMediaId);

            // Importação municipal (CSV) para preencher dados de escolas/INEP/endereço.
            $this->call(ItaparicaBaSchoolsSeeder::class);
            $this->call(ItaparicaBaAeeSchoolClassesSeeder::class);

            $this->restringirCursosParaEducacaoInfantilEFundamental((int) $instituicao->getKey());
            $this->montarCalendarioTrimestral2026((int) $instituicao->getKey(), (int) $moduloTrimestre->cod_modulo);
            $this->desativarTurmasGeradasAutomaticamenteEm2026((int) $instituicao->getKey());

            // Ao final, cria/atualiza tipos de usuários (perfis) municipais e permissões de menu.
            $this->call(PerfisUsuariosMunicipioSeeder::class);
        });

        Log::channel('daily')->info('ieducar:setup [itaparica-ba] — rotina municipal concluída.', [
            'cidade' => ItaparicaBaMunicipalData::CIDADE,
        ]);
    }

    private function aplicarInstituicaoDadosOficiais(LegacyInstitution $instituicao): void
    {
        $instituicao->update([
            'nm_instituicao' => ItaparicaBaMunicipalData::INSTITUICAO_NOME_OFICIAL,
            'ref_sigla_uf' => ItaparicaBaMunicipalData::UF,
            'cidade' => ItaparicaBaMunicipalData::CIDADE,
            'cep' => ItaparicaBaMunicipalData::CEP_PADRAO,
            'bairro' => ItaparicaBaMunicipalData::ENDERECO_BAIRRO,
            'logradouro' => ItaparicaBaMunicipalData::ENDERECO_LOGRADOURO,
            'numero' => ItaparicaBaMunicipalData::ENDERECO_NUMERO,
            'ddd_telefone' => ItaparicaBaMunicipalData::TELEFONE_DDD,
            'telefone' => ItaparicaBaMunicipalData::TELEFONE_NUMERO,
            'nm_responsavel' => ItaparicaBaMunicipalData::SECRETARIO_EDUCACAO,
        ]);
    }

    private function aplicarConfiguracoesGerais(int $instituicaoId): void
    {
        $rodape = sprintf(
            '<p><strong>%s</strong><br>'
            . 'Prefeito(a): %s<br>'
            . 'Secretário(a) de Educação: %s<br>'
            . '%s<br>'
            . 'Critérios avaliativos: %s — %s (%d/%d/%d) — Recuperação: somente final (média %.1f).</p>',
            e(ItaparicaBaMunicipalData::SECRETARIA_OFICIAL),
            e(ItaparicaBaMunicipalData::PREFEITO),
            e(ItaparicaBaMunicipalData::SECRETARIO_EDUCACAO),
            e(ItaparicaBaMunicipalData::ATO_NOMEACAO_SECRETARIO),
            e(ItaparicaBaMunicipalData::FORMA_AVALIATIVA),
            '3 trimestres',
            ItaparicaBaMunicipalData::PESOS_TRIMESTRAIS[0],
            ItaparicaBaMunicipalData::PESOS_TRIMESTRAIS[1],
            ItaparicaBaMunicipalData::PESOS_TRIMESTRAIS[2],
            ItaparicaBaMunicipalData::MEDIA_RECUPERACAO_FINAL
        );

        LegacyGeneralConfiguration::query()->updateOrCreate(
            ['ref_cod_instituicao' => $instituicaoId],
            [
                'ieducar_entity_name' => ItaparicaBaMunicipalData::INSTITUICAO_NOME_OFICIAL,
                'ieducar_internal_footer' => $rodape,
            ]
        );
    }

    private function garantirModuloTresTrimestres(int $instituicaoId)
    {
        $modulo = \App\Models\LegacyStageType::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('ativo', 1)
            ->orderBy('cod_modulo')
            ->first();

        if ($modulo === null) {
            return \App\Models\LegacyStageType::query()->create([
                'ref_usuario_cad' => self::USUARIO_CAD,
                'nm_tipo' => 'Trimestre',
                'num_etapas' => 3,
                'descricao' => 'Três trimestres (30/30/40) — Itaparica/BA',
                'ref_cod_instituicao' => $instituicaoId,
                'ativo' => 1,
                'data_cadastro' => now(),
            ]);
        }

        \App\Models\LegacyStageType::query()->where('cod_modulo', $modulo->cod_modulo)->update([
            'nm_tipo' => 'Trimestre',
            'num_etapas' => 3,
            'descricao' => 'Três trimestres (30/30/40) — Itaparica/BA',
        ]);

        /** @var \App\Models\LegacyStageType $modulo */
        return $modulo->refresh();
    }

    private function garantirFormulaMediaTrimestres303040(int $instituicaoId): int
    {
        $nome = 'Itaparica-BA: Trimestres 30/30/40';
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
        DB::table('modules.regra_avaliacao')
            ->where('id', 1)
            ->where('instituicao_id', $instituicaoId)
            ->update([
                // limite do campo: varchar(50)
                'nome' => 'Itaparica-BA: num 3tri 30/30/40 r6/r5',
                'tipo_nota' => self::TIPO_NOTA_NUMERICA,
                'formula_media_id' => $formulaMediaId,
                // Mantém a fórmula de recuperação padrão (id=2) do seed nacional.
                'formula_recuperacao_id' => 2,
                'media' => ItaparicaBaMunicipalData::MEDIA_APROVACAO,
                // Itaparica: somente recuperação final ao fim do ano.
                'tipo_recuperacao_paralela' => self::SEM_RECUPERACAO_PARALELA,
                'media_recuperacao_paralela' => 0,
                'media_recuperacao' => ItaparicaBaMunicipalData::MEDIA_RECUPERACAO_FINAL,
                'tipo_calculo_recuperacao_paralela' => 0,
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

        foreach ([$cursoInfantil, $cursoFundamental] as $curso) {
            if ($curso !== null) {
                $curso->update(['ativo' => 1]);
            }
        }

        if ($cursoMedio !== null) {
            $cursoMedio->update(['ativo' => 0]);

            DB::table('pmieducar.escola_curso')
                ->whereIn('ref_cod_escola', $schools->pluck('cod_escola')->all())
                ->where('ref_cod_curso', $cursoMedio->cod_curso)
                ->update([
                    'ativo' => 0,
                    'ref_usuario_exc' => self::USUARIO_CAD,
                    'data_exclusao' => now(),
                ]);
        }

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

        $etapas = [
            1 => ['inicio' => '2026-03-02', 'fim' => '2026-05-23', 'dias' => 63],
            2 => ['inicio' => '2026-05-25', 'fim' => '2026-09-05', 'dias' => 69],
            3 => ['inicio' => '2026-09-09', 'fim' => '2026-12-15', 'dias' => 68],
        ];

        foreach ($schools as $school) {
            $escolaAnoLetivoId = DB::table('pmieducar.escola_ano_letivo')
                ->where('ref_cod_escola', $school->cod_escola)
                ->where('ano', 2026)
                ->value('id');

            if (!$escolaAnoLetivoId) {
                continue;
            }

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

