<?php

namespace Database\Seeders\Setup;

use App\IeducarSetup\Itamari\ItamariMunicipalData;
use App\Models\LegacyEvaluationRule;
use App\Models\LegacyGeneralConfiguration;
use App\Models\LegacyInstitution;
use App\Models\LegacyOrganization;
use App\Models\LegacyPerson;
use App\Models\LegacySchool;
use App\Models\LegacySchoolAcademicYear;
use App\Models\LegacyStageType;
use App\Models\SchoolInep;
use Database\Seeders\CalendarioEscolarSeeder;
use Database\Seeders\ConfiguracaoEscolarSeeder;
use iEducar\Modules\Educacenso\Model\DependenciaAdministrativaEscola;
use iEducar\Modules\Educacenso\Model\Regulamentacao;
use iEducar\Modules\Educacenso\Model\SituacaoFuncionamento;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Setup específico Itamari/BA: instituição, regra de avaliação, módulo bimestral,
 * cadastro das escolas (INEP), anos letivos 2025 encerrado e 2026 em andamento,
 * cursos/turmas/calendário para os anos 2025 e 2026.
 *
 * Educacenso / INEP (atenção em produção):
 * - Código INEP da escola em `modules.educacenso_cod_escola` vem da planilha oficial; conferir no Cadastro da escola.
 * - CNPJ em `cadastro.juridica` é gerado de forma **única e provisória** para o seed (não é o CNPJ real da mantenedora).
 * - Muitos campos do registro 00/10/20 do Censo (endereço no padrão Educacenso, órgão regional, esfera, telefone,
 *   e-mail institucional completo, gestores com INEP de 12 dígitos quando exigido, etc.) **não** são preenchidos aqui;
 *   completar no i-Educar antes de exportar ou homologar com o MEC.
 */
class ItamariBaSetupSeeder extends Seeder
{
    private const USUARIO_CAD = 1;

    /** @see \RegraAvaliacao_Model_Nota_TipoValor::NUMERICACONCEITUAL */
    private const TIPO_NOTA_MISTA = 3;

    /** @see LegacyEvaluationRule::PARALLEL_REMEDIAL_PER_STAGE */
    private const RECUPERACAO_FIM_DE_CADA_PERIODO = 1;

    public function run(): void
    {
        DB::transaction(function (): void {
            $instituicao = LegacyInstitution::query()->whereKey(1)->first();
            if ($instituicao === null) {
                $this->command?->error('Instituição cod_instituicao=1 não encontrada. Rode as migrations/seeders base antes do ieducar:setup itamari-ba.');

                return;
            }

            $this->aplicarInstituicaoEMunicipio($instituicao);
            $this->aplicarConfiguracoesGerais($instituicao->getKey());
            $this->garantirModuloQuatroBimestres($instituicao->getKey());
            $this->aplicarRegraAvaliacaoItamari($instituicao->getKey());

            $codigosEscolasItamari = [];
            foreach (ItamariMunicipalData::escolas() as $dados) {
                $codigosEscolasItamari[] = $this->sincronizarEscola($dados, $instituicao->getKey());
            }

            $this->call(ConfiguracaoEscolarSeeder::class);
            $this->call(CalendarioEscolarSeeder::class);

            $this->ajustarAnosLetivosItamari($codigosEscolasItamari);
        });

        Log::channel('daily')->info('ieducar:setup [itamari-ba] — rotina municipal concluída.', [
            'escolas' => count(ItamariMunicipalData::escolas()),
        ]);
    }

    private function aplicarInstituicaoEMunicipio(LegacyInstitution $instituicao): void
    {
        $instituicao->update([
            'nm_instituicao' => 'Prefeitura Municipal de '.ItamariMunicipalData::CIDADE,
            'ref_sigla_uf' => ItamariMunicipalData::UF,
            'cidade' => ItamariMunicipalData::CIDADE,
            'cep' => (int) ItamariMunicipalData::CEP_PADRAO,
            'bairro' => 'Centro',
            'logradouro' => 'Praça Municipal',
            'nm_responsavel' => ItamariMunicipalData::SECRETARIO_EDUCACAO,
        ]);
    }

    private function aplicarConfiguracoesGerais(int $instituicaoId): void
    {
        $rodape = sprintf(
            '<p><strong>%s</strong><br>Prefeito(a): %s<br>Secretário(a) de Educação: %s<br>%s<br>'
            .'Critérios avaliativos (padrão seed até formalização pela SME): %s — %s — %s — %s.</p>',
            e(ItamariMunicipalData::SECRETARIA_OFICIAL),
            e(ItamariMunicipalData::PREFEITO),
            e(ItamariMunicipalData::SECRETARIO_EDUCACAO),
            e(ItamariMunicipalData::ATO_NOMEACAO_SECRETARIO),
            e(ItamariMunicipalData::FORMA_AVALIATIVA),
            e(ItamariMunicipalData::CRITERIO_APROVACAO),
            e(ItamariMunicipalData::PERIODOS_AVALIATIVOS),
            e(ItamariMunicipalData::REGIME_RECUPERACAO)
        );

        LegacyGeneralConfiguration::query()->updateOrCreate(
            ['ref_cod_instituicao' => $instituicaoId],
            [
                'ieducar_entity_name' => ItamariMunicipalData::SECRETARIA_OFICIAL,
                'ieducar_internal_footer' => $rodape,
            ]
        );
    }

    private function garantirModuloQuatroBimestres(int $instituicaoId): void
    {
        $modulo = LegacyStageType::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('ativo', 1)
            ->orderBy('cod_modulo')
            ->first();

        if ($modulo === null) {
            LegacyStageType::query()->create([
                'ref_usuario_cad' => self::USUARIO_CAD,
                'nm_tipo' => 'Bimestre',
                'num_etapas' => 4,
                'descricao' => 'Quatro bimestres — Itamari/BA',
                'ref_cod_instituicao' => $instituicaoId,
                'ativo' => 1,
                'data_cadastro' => now(),
            ]);

            return;
        }

        LegacyStageType::query()->where('cod_modulo', $modulo->cod_modulo)->update([
            'nm_tipo' => 'Bimestre',
            'num_etapas' => 4,
            'descricao' => 'Quatro bimestres — Itamari/BA',
        ]);
    }

    private function aplicarRegraAvaliacaoItamari(int $instituicaoId): void
    {
        $nomeRegra = 'Itamari-BA: mista 6,0 75% 4bim rec/etapa';
        DB::table('modules.regra_avaliacao')
            ->where('id', 1)
            ->where('instituicao_id', $instituicaoId)
            ->update([
                'nome' => $nomeRegra,
                'tipo_nota' => self::TIPO_NOTA_MISTA,
                'media' => ItamariMunicipalData::MEDIA_APROVACAO,
                'porcentagem_presenca' => ItamariMunicipalData::FREQUENCIA_MINIMA_PERCENTUAL,
                'tipo_recuperacao_paralela' => self::RECUPERACAO_FIM_DE_CADA_PERIODO,
            ]);
    }

    /**
     * @param  array{inep: int, nome: string, cep: string, logradouro: string, numero: string, bairro: string, zona_urbana: bool, email: ?string}  $dados
     */
    private function sincronizarEscola(array $dados, int $instituicaoId): int
    {
        $dados['nome'] = ItamariMunicipalData::nomeEscolaParaExibicao($dados['nome']);
        $inep = $dados['inep'];
        $existenteInep = SchoolInep::query()->where('cod_escola_inep', $inep)->first();

        if ($existenteInep !== null) {
            $escola = LegacySchool::query()->whereKey($existenteInep->cod_escola)->first();
            if ($escola !== null) {
                $this->atualizarPessoaEscola($escola->ref_idpes, $dados);

                return $escola->getKey();
            }
        }

        $person = LegacyPerson::query()->create([
            'nome' => $dados['nome'],
            'tipo' => 'J',
            'email' => $dados['email'],
        ]);

        $cnpj = 29000000000000 + ($inep * 100);

        LegacyOrganization::query()->create([
            'idpes' => $person->getKey(),
            'cnpj' => $cnpj,
            'insc_estadual' => 0,
            'origem_gravacao' => 'M',
            'idpes_cad' => self::USUARIO_CAD,
            'data_cad' => now(),
            'operacao' => 'I',
            'fantasia' => $dados['nome'],
        ]);

        $zona = $dados['zona_urbana'] ? 1 : 2;

        $escola = LegacySchool::query()->create([
            'ref_usuario_cad' => self::USUARIO_CAD,
            'ref_cod_instituicao' => $instituicaoId,
            'sigla' => $this->gerarSiglaEscola($inep),
            'ref_idpes' => $person->getKey(),
            'ativo' => 1,
            'situacao_funcionamento' => SituacaoFuncionamento::EM_ATIVIDADE,
            'dependencia_administrativa' => DependenciaAdministrativaEscola::MUNICIPAL,
            'regulamentacao' => Regulamentacao::SIM,
            'zona_localizacao' => $zona,
            'nao_ha_funcionarios_para_funcoes' => true,
            'data_cadastro' => now(),
        ]);

        SchoolInep::query()->updateOrCreate(
            ['cod_escola' => $escola->getKey()],
            [
                'cod_escola_inep' => $inep,
                'nome_inep' => $dados['nome'],
                'fonte' => 'MUNICIPIO_ITAMARI_BA',
            ]
        );

        return $escola->getKey();
    }

    /**
     * @param  array{inep: int, nome: string, cep: string, logradouro: string, numero: string, bairro: string, zona_urbana: bool, email: ?string}  $dados
     */
    private function atualizarPessoaEscola(int $idpes, array $dados): void
    {
        $dados['nome'] = ItamariMunicipalData::nomeEscolaParaExibicao($dados['nome']);

        LegacyPerson::query()->whereKey($idpes)->update([
            'nome' => $dados['nome'],
            'email' => $dados['email'],
        ]);

        LegacyOrganization::query()->whereKey($idpes)->update([
            'fantasia' => $dados['nome'],
        ]);

        $zona = $dados['zona_urbana'] ? 1 : 2;

        $escola = LegacySchool::query()->where('ref_idpes', $idpes)->first();
        if ($escola !== null) {
            $payload = ['zona_localizacao' => $zona];
            if ($escola->sigla === null || $escola->sigla === '') {
                $payload['sigla'] = $this->gerarSiglaEscola($dados['inep']);
            }
            $escola->update($payload);

            SchoolInep::query()->updateOrCreate(
                ['cod_escola' => $escola->getKey()],
                [
                    'cod_escola_inep' => $dados['inep'],
                    'nome_inep' => $dados['nome'],
                    'fonte' => 'MUNICIPIO_ITAMARI_BA',
                ]
            );
        }
    }

    /** Sigla única da escola (`pmieducar.escola.sigla`, máx. 20 caracteres, NOT NULL). */
    private function gerarSiglaEscola(int $inep): string
    {
        return substr((string) $inep, 0, 20);
    }

    /**
     * @param  list<int>  $codigosEscolas
     */
    private function ajustarAnosLetivosItamari(array $codigosEscolas): void
    {
        foreach ($codigosEscolas as $codEscola) {
            LegacySchoolAcademicYear::query()
                ->where('ref_cod_escola', $codEscola)
                ->where('ano', 2025)
                ->where('ativo', 1)
                ->update([
                    'andamento' => LegacySchoolAcademicYear::FINALIZED,
                    'ref_usuario_exc' => self::USUARIO_CAD,
                ]);

            LegacySchoolAcademicYear::query()
                ->where('ref_cod_escola', $codEscola)
                ->where('ano', 2026)
                ->where('ativo', 1)
                ->update([
                    'andamento' => LegacySchoolAcademicYear::IN_PROGRESS,
                    'ref_usuario_exc' => null,
                ]);
        }
    }
}
