<?php

namespace Database\Seeders;

use App\Models\LegacyInstitution;
use App\Models\LegacyKnowledgeArea;
use Illuminate\Database\Seeder;

/**
 * Cria as áreas do conhecimento conforme a BNCC (Base Nacional Comum Curricular)
 * para garantir rigidez e conformidade do sistema com o currículo nacional.
 *
 * Educação Infantil: Campos de Experiência (1 área)
 * Ensino Fundamental: 5 áreas
 * Ensino Médio: 4 áreas (formato novo BNCC)
 */
class AreaConhecimentoBnccSeeder extends Seeder
{
    /**
     * Áreas do conhecimento BNCC por etapa de ensino.
     * ordenamento: ordem de exibição (menor = primeiro)
     */
    private const AREAS_BNCC = [
        // Educação Infantil - Campos de Experiência
        'Educação Infantil' => [
            ['nome' => 'Campos de Experiência', 'ordenamento' => 1],
        ],
        // Ensino Fundamental - 5 áreas
        'Ensino Fundamental' => [
            ['nome' => 'Linguagens', 'ordenamento' => 1],
            ['nome' => 'Matemática', 'ordenamento' => 2],
            ['nome' => 'Ciências da Natureza', 'ordenamento' => 3],
            ['nome' => 'Ciências Humanas', 'ordenamento' => 4],
            ['nome' => 'Ensino Religioso', 'ordenamento' => 5],
        ],
        // Ensino Médio - 4 áreas (BNCC 2018)
        'Ensino Médio' => [
            ['nome' => 'Linguagens e suas Tecnologias', 'ordenamento' => 1],
            ['nome' => 'Matemática e suas Tecnologias', 'ordenamento' => 2],
            ['nome' => 'Ciências da Natureza e suas Tecnologias', 'ordenamento' => 3],
            ['nome' => 'Ciências Humanas e Sociais Aplicadas', 'ordenamento' => 4],
        ],
    ];

    /**
     * Cria/atualiza as áreas de conhecimento BNCC em todas as instituições ativas.
     * Usa firstOrCreate para idempotência.
     */
    public function run(): void
    {
        $instituicoes = LegacyInstitution::query()
            ->where('ativo', 1)
            ->pluck('cod_instituicao');

        if ($instituicoes->isEmpty()) {
            $this->command?->warn('Nenhuma instituição ativa encontrada.');
            return;
        }

        $todasAreas = collect(self::AREAS_BNCC)->flatten(1)->unique('nome');

        foreach ($instituicoes as $instituicaoId) {
            foreach ($todasAreas as $areaConfig) {
                $area = LegacyKnowledgeArea::firstOrCreate(
                    [
                        'instituicao_id' => $instituicaoId,
                        'nome' => $areaConfig['nome'],
                    ],
                    [
                        'instituicao_id' => $instituicaoId,
                        'nome' => $areaConfig['nome'],
                    ]
                );

                if (isset($areaConfig['ordenamento']) && $area->ordenamento_ac !== $areaConfig['ordenamento']) {
                    $area->ordenamento_ac = $areaConfig['ordenamento'];
                    $area->save();
                }
            }
        }

        $totalAreas = $todasAreas->count() * $instituicoes->count();
        $this->command?->info("Áreas do conhecimento BNCC configuradas: {$totalAreas} vínculos.");
    }

    /**
     * Retorna o nome da área BNCC para uma disciplina em um determinado curso.
     *
     * @return array<string, array<int, array{nome: string, area: string}>>
     */
    public static function getDisciplinasPorCurso(): array
    {
        return [
            'Educação Infantil' => [
                ['nome' => 'Campos de Experiência: O eu, o outro e o nós', 'area' => 'Campos de Experiência'],
                ['nome' => 'Campos de Experiência: Corpo, gestos e movimentos', 'area' => 'Campos de Experiência'],
                ['nome' => 'Campos de Experiência: Traços, sons, cores e formas', 'area' => 'Campos de Experiência'],
                ['nome' => 'Campos de Experiência: Escuta, fala, pensamento e imaginação', 'area' => 'Campos de Experiência'],
                ['nome' => 'Campos de Experiência: Espaços, tempos, quantidades, relações e transformações', 'area' => 'Campos de Experiência'],
            ],
            'Ensino Fundamental' => [
                ['nome' => 'Língua Portuguesa', 'area' => 'Linguagens'],
                ['nome' => 'Matemática', 'area' => 'Matemática'],
                ['nome' => 'Ciências', 'area' => 'Ciências da Natureza'],
                ['nome' => 'História', 'area' => 'Ciências Humanas'],
                ['nome' => 'Geografia', 'area' => 'Ciências Humanas'],
                ['nome' => 'Arte', 'area' => 'Linguagens'],
                ['nome' => 'Educação Física', 'area' => 'Linguagens'],
                ['nome' => 'Ensino Religioso', 'area' => 'Ensino Religioso'],
                ['nome' => 'Inglês', 'area' => 'Linguagens'],
            ],
            'Ensino Médio' => [
                ['nome' => 'Língua Portuguesa', 'area' => 'Linguagens e suas Tecnologias'],
                ['nome' => 'Matemática', 'area' => 'Matemática e suas Tecnologias'],
                ['nome' => 'Biologia', 'area' => 'Ciências da Natureza e suas Tecnologias'],
                ['nome' => 'Física', 'area' => 'Ciências da Natureza e suas Tecnologias'],
                ['nome' => 'Química', 'area' => 'Ciências da Natureza e suas Tecnologias'],
                ['nome' => 'História', 'area' => 'Ciências Humanas e Sociais Aplicadas'],
                ['nome' => 'Geografia', 'area' => 'Ciências Humanas e Sociais Aplicadas'],
                ['nome' => 'Filosofia', 'area' => 'Ciências Humanas e Sociais Aplicadas'],
                ['nome' => 'Sociologia', 'area' => 'Ciências Humanas e Sociais Aplicadas'],
                ['nome' => 'Educação Física', 'area' => 'Linguagens e suas Tecnologias'],
                ['nome' => 'Arte', 'area' => 'Linguagens e suas Tecnologias'],
                ['nome' => 'Inglês', 'area' => 'Linguagens e suas Tecnologias'],
            ],
        ];
    }
}
