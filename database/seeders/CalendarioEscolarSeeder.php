<?php

namespace Database\Seeders;

use App\Models\LegacyAcademicYearStage;
use App\Models\LegacyCalendarDay;
use App\Models\LegacyCalendarYear;
use App\Models\LegacySchool;
use App\Models\LegacySchoolAcademicYear;
use App\Models\LegacyStageType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gera o calendário escolar (dias letivos e módulos/etapas) para o ano anterior e
 * o ano corrente em todas as escolas cadastradas.
 *
 * Cria o registro de calendário anual (pmieducar.calendario_ano_letivo), os dias
 * letivos (segunda a sexta, excluindo fins de semana, até 200 dias) e vincula os
 * módulos (trimestres/bimestres) ao ano letivo de cada escola.
 *
 * Suporta retomada: escolas que já possuem calendário anual com dias letivos e
 * módulos vinculados para o ano corrente são puladas automaticamente.
 * Para retomar de uma escola específica, defina `CALENDARIO_SEEDER_FROM_ESCOLA=<cod_escola>`.
 */
class CalendarioEscolarSeeder extends Seeder
{
    private const USUARIO_CAD = 1;

    private function step(string $message): void
    {
        $line = '[CalendarioEscolarSeeder] ' . $message;
        Log::info($line);
        $this->command?->info($line);
    }

    /**
     * Para cada escola, cria calendário anual e módulos dos anos anterior e atual.
     * Pula escolas já configuradas para acelerar re-execuções.
     */
    public function run(): void
    {
        $tInicio = microtime(true);
        $anoAnterior = Carbon::now()->year - 1;
        $anoAtual = Carbon::now()->year;

        $fromEscola = $this->resolveFromEscola();

        $query = LegacySchool::query()->orderBy('cod_escola');
        if ($fromEscola !== null) {
            $query->where('cod_escola', '>=', $fromEscola);
        }

        $schools = $query->get();

        if ($schools->isEmpty()) {
            $this->command?->warn('Nenhuma escola encontrada para criar calendário.');

            return;
        }

        $totalEscolas = $schools->count();
        $this->step(sprintf(
            'Início (anos %d e %d, %d escolas%s).',
            $anoAnterior,
            $anoAtual,
            $totalEscolas,
            $fromEscola !== null ? ", a partir da escola {$fromEscola}" : ''
        ));

        $puladas = 0;
        foreach ($schools as $school) {
            if ($this->escolaJaTemCalendario($school, $anoAtual)) {
                $puladas++;

                continue;
            }

            foreach ([$anoAnterior, $anoAtual] as $ano) {
                $this->criarCalendarioAnual($school, $ano);
                $this->criarAnoLetivoModulos($school, $ano);
            }
        }

        if ($puladas > 0) {
            $this->step(sprintf('%d escola(s) já com calendário completo foram puladas.', $puladas));
        }
        $this->step(sprintf('Finalizado em %.2fs (%d processadas, %d puladas).', microtime(true) - $tInicio, $totalEscolas - $puladas, $puladas));
    }

    private function resolveFromEscola(): ?int
    {
        $val = env('CALENDARIO_SEEDER_FROM_ESCOLA');
        if ($val === null || $val === '' || $val === false) {
            return null;
        }

        $id = (int) $val;
        $this->step("Variável CALENDARIO_SEEDER_FROM_ESCOLA={$id} detectada — escolas anteriores serão ignoradas.");

        return $id;
    }

    /**
     * Escola já possui calendário anual com dias letivos e módulos vinculados
     * para o ano corrente — pode ser pulada com segurança.
     */
    private function escolaJaTemCalendario(LegacySchool $school, int $anoAtual): bool
    {
        $calendario = LegacyCalendarYear::query()
            ->where('ref_cod_escola', $school->cod_escola)
            ->where('ano', $anoAtual)
            ->where('ativo', 1)
            ->first();

        if ($calendario === null) {
            return false;
        }

        $temDias = LegacyCalendarDay::query()
            ->where('ref_cod_calendario_ano_letivo', $calendario->cod_calendario_ano_letivo)
            ->exists();

        if (!$temDias) {
            return false;
        }

        $temModulos = LegacyAcademicYearStage::query()
            ->where('ref_ref_cod_escola', $school->cod_escola)
            ->where('ref_ano', $anoAtual)
            ->exists();

        return $temModulos;
    }

    /**
     * Vincula o tipo de etapa (trimestre/bimestre) ao ano letivo da escola,
     * distribuindo as datas de início/fim proporcionalmente no período fev–dez.
     */
    private function criarAnoLetivoModulos(LegacySchool $school, int $ano): void
    {
        $temAnoLetivo = LegacySchoolAcademicYear::query()
            ->where('ref_cod_escola', $school->cod_escola)
            ->where('ano', $ano)
            ->exists();

        if (!$temAnoLetivo) {
            return;
        }

        /** @var int|null $escolaAnoLetivoId PK numérica `pmieducar.escola_ano_letivo.id` (obrigatória em `ano_letivo_modulo` após migrations recentes). */
        $escolaAnoLetivoId = DB::table('pmieducar.escola_ano_letivo')
            ->where('ref_cod_escola', $school->cod_escola)
            ->where('ano', $ano)
            ->value('id');

        if ($escolaAnoLetivoId === null) {
            $this->command?->warn(sprintf(
                'Escola cod_escola=%s, ano %d: registo em escola_ano_letivo sem coluna `id` ou inexistente; não é possível criar ano_letivo_modulo (escola_ano_letivo_id). Confirme migrations (pmieducar.escola_ano_letivo.id).',
                $school->cod_escola,
                $ano
            ));

            return;
        }

        $escolaAnoLetivoId = (int) $escolaAnoLetivoId;

        $modulo = LegacyStageType::query()
            ->where('ativo', 1)
            ->where('ref_cod_instituicao', $school->ref_cod_instituicao)
            ->first();

        if (!$modulo) {
            $modulo = LegacyStageType::query()->where('ativo', 1)->first();
        }

        if (!$modulo) {
            $this->command?->warn('Nenhum módulo (tipo de etapa) encontrado. Cadastre em Cadastros > Tipos de etapa.');

            return;
        }

        $etapasDatas = $this->obterDatasEtapas($ano, $modulo->num_etapas);

        for ($sequencial = 1; $sequencial <= $modulo->num_etapas; $sequencial++) {
            $datas = $etapasDatas[$sequencial] ?? $etapasDatas[1];

            LegacyAcademicYearStage::updateOrCreate(
                [
                    'ref_ano' => $ano,
                    'ref_ref_cod_escola' => $school->cod_escola,
                    'sequencial' => $sequencial,
                    'ref_cod_modulo' => $modulo->cod_modulo,
                ],
                [
                    'data_inicio' => $datas['inicio'],
                    'data_fim' => $datas['fim'],
                    'dias_letivos' => (int) ceil(200 / $modulo->num_etapas),
                    'escola_ano_letivo_id' => $escolaAnoLetivoId,
                ]
            );
        }
    }

    /** @return array<int, array{inicio: Carbon, fim: Carbon}> */
    private function obterDatasEtapas(int $ano, int $numEtapas): array
    {
        $inicioAno = Carbon::create($ano, 2, 1);
        $fimAno = Carbon::create($ano, 12, 15);
        $diasTotal = $inicioAno->diffInDays($fimAno);
        $diasPorEtapa = (int) floor($diasTotal / $numEtapas);
        $result = [];

        for ($i = 1; $i <= $numEtapas; $i++) {
            $inicio = $i === 1 ? $inicioAno->copy() : $result[$i - 1]['fim']->copy()->addDay();
            $fim = $i === $numEtapas ? $fimAno->copy() : $inicio->copy()->addDays($diasPorEtapa);
            $result[$i] = ['inicio' => $inicio, 'fim' => $fim];
        }

        return $result;
    }

    /**
     * Cria o registro de calendário anual da escola e gera os dias letivos do período.
     */
    private function criarCalendarioAnual(LegacySchool $school, int $ano): void
    {
        $calendario = LegacyCalendarYear::firstOrCreate(
            [
                'ref_cod_escola' => $school->cod_escola,
                'ano' => $ano,
            ],
            [
                'ref_usuario_cad' => self::USUARIO_CAD,
                'data_cadastra' => now(),
                'ativo' => 1,
            ]
        );

        $this->gerarDiasLetivos($calendario, $ano);
    }

    /**
     * Insere até 200 dias letivos (seg–sex) no período de fevereiro a dezembro,
     * pulando registros já existentes para idempotência.
     */
    private function gerarDiasLetivos(LegacyCalendarYear $calendario, int $ano): void
    {
        $inicio = Carbon::create($ano, 2, 1);
        $fim = Carbon::create($ano, 12, 15);
        $diasLetivos = 0;

        while ($inicio->lte($fim) && $diasLetivos < 200) {
            if (!$inicio->isWeekend()) {
                $existe = LegacyCalendarDay::query()
                    ->where('ref_cod_calendario_ano_letivo', $calendario->cod_calendario_ano_letivo)
                    ->where('mes', $inicio->month)
                    ->where('dia', $inicio->day)
                    ->exists();

                if (!$existe) {
                    LegacyCalendarDay::create([
                        'ref_cod_calendario_ano_letivo' => $calendario->cod_calendario_ano_letivo,
                        'mes' => $inicio->month,
                        'dia' => $inicio->day,
                        'ref_usuario_cad' => self::USUARIO_CAD,
                        'data_cadastro' => now(),
                        'ativo' => 1,
                        'ref_cod_calendario_dia_motivo' => null,
                        'descricao' => 'Dia letivo',
                    ]);
                }
                $diasLetivos++;
            }
            $inicio->addDay();
        }
    }
}
