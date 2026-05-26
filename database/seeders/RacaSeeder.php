<?php

namespace Database\Seeders;

use App\Models\LegacyRace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Garante raças/cores padrão IBGE/INEP em cadastro.raca, com raca_educacenso preenchido.
 *
 * Executado pelo SeederMaster (ieducar:setup) e reutilizado na migration de dados
 * iniciais via DefaultCadastroRacaTableSeeder.
 *
 * Quilombola: no Censo Escolar a comunidade quilombola é informada em
 * localização diferenciada da pessoa (não é código exclusivo de cor/raça).
 * A opção abaixo existe para uso operacional da rede; na exportação do Educacenso
 * o código enviado é o de raca_educacenso (Parda = 3), conforme orientação IBGE.
 *
 * @see \iEducar\Modules\Educacenso\Model\LocalizacaoDiferenciadaPessoa::COMUNIDADES_REMANESCENTES_QUILOMBOS
 */
class RacaSeeder extends Seeder
{
    private const USUARIO_CAD = 1;

    /**
     * Cor/raça oficial do IBGE/INEP (registro 30).
     *
     * @var array<int, array{nm_raca: string, raca_educacenso: int}>
     */
    private const RACAS_OFICIAIS = [
        ['nm_raca' => 'Branca', 'raca_educacenso' => 1],
        ['nm_raca' => 'Preta', 'raca_educacenso' => 2],
        ['nm_raca' => 'Parda', 'raca_educacenso' => 3],
        ['nm_raca' => 'Amarela', 'raca_educacenso' => 4],
        ['nm_raca' => 'Indígena', 'raca_educacenso' => 5],
        ['nm_raca' => 'Não Declarada', 'raca_educacenso' => 0],
    ];

    /**
     * Opções complementares para cadastro interno (sempre com raca_educacenso válido no Censo).
     *
     * @var array<int, array{nm_raca: string, raca_educacenso: int}>
     */
    private const RACAS_COMPLEMENTARES = [
        ['nm_raca' => 'Quilombola', 'raca_educacenso' => 3],
    ];

    public function run(): void
    {
        foreach (array_merge(self::RACAS_OFICIAIS, self::RACAS_COMPLEMENTARES) as $definicao) {
            $this->garantirRaca($definicao['nm_raca'], $definicao['raca_educacenso']);
        }

        $this->corrigirRacasSemCodigoEducacenso();

        DB::unprepared(
            "SELECT pg_catalog.setval(
                'cadastro.raca_cod_raca_seq',
                (SELECT COALESCE(MAX(cod_raca), 0) + 1 FROM cadastro.raca),
                false
            );"
        );
    }

    private function garantirRaca(string $nome, int $codigoEducacenso): void
    {
        LegacyRace::query()->updateOrCreate(
            ['nm_raca' => $nome],
            [
                'raca_educacenso' => $codigoEducacenso,
                'idpes_cad' => self::USUARIO_CAD,
                'ativo' => true,
            ]
        );
    }

    /**
     * Preenche raca_educacenso em registros legados (evita falha na análise do registro 30).
     */
    private function corrigirRacasSemCodigoEducacenso(): void
    {
        $mapaPorNome = [
            'branca' => 1,
            'preta' => 2,
            'parda' => 3,
            'amarela' => 4,
            'indígena' => 5,
            'indigena' => 5,
            'não declarada' => 0,
            'nao declarada' => 0,
            'não declarado' => 0,
            'nao declarado' => 0,
            'quilombola' => 3,
        ];

        LegacyRace::query()
            ->whereNull('raca_educacenso')
            ->where('ativo', true)
            ->each(function (LegacyRace $raca) use ($mapaPorNome): void {
                $chave = mb_strtolower(trim($raca->nm_raca));
                if (!isset($mapaPorNome[$chave])) {
                    return;
                }

                $raca->update(['raca_educacenso' => $mapaPorNome[$chave]]);
            });
    }
}
