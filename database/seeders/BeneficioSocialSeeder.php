<?php

namespace Database\Seeders;

use App\Models\LegacyBenefit;
use Illuminate\Database\Seeder;

/**
 * Cadastra os benefícios sociais ofertados aos alunos conforme programas federais
 * em vigência, permitindo que as escolas vinculem estudantes aos benefícios que recebem.
 *
 * Baseado nos programas do FNDE/MEC e MDSA (Ministério do Desenvolvimento e Assistência Social).
 */
class BeneficioSocialSeeder extends Seeder
{
    private const USUARIO_CAD = 1;

    /**
     * Benefícios sociais federais vigentes para o segmento da educação básica.
     *
     * @var array<int, array{nm_beneficio: string, desc_beneficio: string}>
     */
    private const BENEFICIOS_FEDERAIS = [
        [
            'nm_beneficio' => 'PNAE - Merenda Escolar',
            'desc_beneficio' => 'Programa Nacional de Alimentação Escolar (Lei 11.947/2009). Oferece alimentação gratuita aos estudantes da educação básica em escolas públicas e conveniadas.',
        ],
        [
            'nm_beneficio' => 'PNATE - Transporte Escolar',
            'desc_beneficio' => 'Programa Nacional de Apoio ao Transporte do Escolar. Auxílio financeiro para transporte de estudantes da educação básica residentes em zona rural.',
        ],
        [
            'nm_beneficio' => 'Caminho da Escola',
            'desc_beneficio' => 'Programa do FNDE que apoia a aquisição de veículos e embarcações para o transporte escolar. Estudantes se beneficiam do transporte garantido.',
        ],
        [
            'nm_beneficio' => 'Bolsa Família',
            'desc_beneficio' => 'Programa de transferência de renda do Governo Federal (Lei 10.836/2004). Famílias em situação de pobreza recebem auxílio. Estudantes de 6 a 17 anos devem estar matriculados e com frequência mínima.',
        ],
        [
            'nm_beneficio' => 'BPC - Benefício de Prestação Continuada',
            'desc_beneficio' => 'Benefício assistencial (Lei 8.742/1993) para idosos e pessoas com deficiência. O BPC na Escola acompanha o acesso à educação dos beneficiários.',
        ],
        [
            'nm_beneficio' => 'CadÚnico',
            'desc_beneficio' => 'Cadastro Único para Programas Sociais. Pré-requisito para Bolsa Família, Auxílio Brasil e outros. Permite isenção em ENEM e concursos públicos.',
        ],
        [
            'nm_beneficio' => 'Auxílio Criança Cidadã',
            'desc_beneficio' => 'Benefício do Auxílio Brasil para acesso a creches (turno parcial R$ 200 ou integral R$ 300/mês). Destinado a famílias em vulnerabilidade.',
        ],
        [
            'nm_beneficio' => 'Material Escolar',
            'desc_beneficio' => 'Entrega de materiais didáticos e escolares via programas federais (PNLD, PDDE) e ações das redes de ensino.',
        ],
        [
            'nm_beneficio' => 'Uniforme Escolar',
            'desc_beneficio' => 'Distribuição de uniformes escolares. Pode ser oferecido por programas estaduais/municipais ou recursos do PDDE.',
        ],
        [
            'nm_beneficio' => 'Livro Didático - PNLD',
            'desc_beneficio' => 'Programa Nacional do Livro e do Material Didático (Lei 9.394/1996). Distribui livros gratuitamente aos alunos da rede pública.',
        ],
        [
            'nm_beneficio' => 'Auxílio Merenda (Estadual/Municipal)',
            'desc_beneficio' => 'Programas complementares de transferência de renda para compra de alimentos quando as aulas estão suspensas. Vigente em diversos estados e municípios.',
        ],
        [
            'nm_beneficio' => 'Isenção de Taxas',
            'desc_beneficio' => 'Isenção da taxa de inscrição no ENEM e em concursos públicos para participantes do CadÚnico em situação de vulnerabilidade.',
        ],
    ];

    /**
     * Cria benefícios novos e atualiza descrição/ativação dos existentes.
     * Idempotente: busca por nome para evitar duplicatas.
     */
    public function run(): void
    {
        $totalCriados = 0;
        $totalExistentes = 0;

        foreach (self::BENEFICIOS_FEDERAIS as $beneficio) {
            $existente = LegacyBenefit::query()
                ->where('nm_beneficio', $beneficio['nm_beneficio'])
                ->first();

            if ($existente) {
                $existente->update([
                    'desc_beneficio' => $beneficio['desc_beneficio'],
                    'ativo' => 1,
                    'data_exclusao' => null,
                ]);
                $totalExistentes++;
            } else {
                LegacyBenefit::create([
                    'ref_usuario_cad' => self::USUARIO_CAD,
                    'nm_beneficio' => $beneficio['nm_beneficio'],
                    'desc_beneficio' => $beneficio['desc_beneficio'],
                    'ativo' => 1,
                ]);
                $totalCriados++;
            }
        }

        $this->command?->info(
            "Benefícios sociais cadastrados: {$totalCriados} novos, {$totalExistentes} atualizados."
        );
    }
}
