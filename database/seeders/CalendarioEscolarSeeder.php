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

class CalendarioEscolarSeeder extends Seeder
{
    private const USUARIO_CAD = 1;

    public function run(): void
    {
        $anoAnterior = Carbon::now()->year - 1;
        $anoAtual = Carbon::now()->year;

        $schools = LegacySchool::all();

        if ($schools->isEmpty()) {
            $this->command?->warn('Nenhuma escola encontrada para criar calendário.');
            return;
        }

        foreach ($schools as $school) {
            foreach ([$anoAnterior, $anoAtual] as $ano) {
                $this->criarCalendarioAnual($school, $ano);
                $this->criarAnoLetivoModulos($school, $ano);
            }
        }
    }

    private function criarAnoLetivoModulos(LegacySchool $school, int $ano): void
    {
        $schoolAcademicYear = LegacySchoolAcademicYear::query()
            ->where('ref_cod_escola', $school->cod_escola)
            ->where('ano', $ano)
            ->first();

        if (!$schoolAcademicYear) {
            return;
        }

        $modulo = LegacyStageType::query()
            ->where('ativo', 1)
            ->where('ref_cod_instituicao', $school->ref_cod_instituicao)
            ->first();

        if (!$modulo) {
            $modulo = LegacyStageType::query()->where('ativo', 1)->first();
        }

        if (!$modulo) {
            $this->command?->warn("Nenhum módulo (tipo de etapa) encontrado. Cadastre em Cadastros > Tipos de etapa.");
            return;
        }

        $etapasDatas = $this->obterDatasEtapas($ano, $modulo->num_etapas);

        for ($sequencial = 1; $sequencial <= $modulo->num_etapas; $sequencial++) {
            $datas = $etapasDatas[$sequencial] ?? $etapasDatas[1];

            LegacyAcademicYearStage::firstOrCreate(
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
                    'escola_ano_letivo_id' => $schoolAcademicYear->getKey(),
                ]
            );
        }
    }

    /** @return array<int, array{inicio: \Carbon\Carbon, fim: \Carbon\Carbon}> */
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
