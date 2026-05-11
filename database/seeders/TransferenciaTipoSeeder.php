<?php

namespace Database\Seeders;

use App\Models\LegacyInstitution;
use App\Models\LegacyTransferType;
use Illuminate\Database\Seeder;

/**
 * Cadastra os motivos padrão de transferência escolar em todas as instituições.
 *
 * Motivos: mudança de endereço, mudança de escola na mesma rede, mudança de
 * curso/etapa, encerramento de turma, solicitação da família e motivo disciplinar.
 * Idempotente via firstOrCreate por nome + instituição.
 */
class TransferenciaTipoSeeder extends Seeder
{
    private const USUARIO_CAD = 1;

    /**
     * Motivos padrão de transferência escolar.
     *
     * @return array<int, array{nm_tipo: string, desc_tipo: string|null}>
     */
    private function motivosPadrao(): array
    {
        return [
            [
                'nm_tipo' => 'Mudança de endereço',
                'desc_tipo' => 'Transferência motivada por mudança de residência da família para outra região ou município.',
            ],
            [
                'nm_tipo' => 'Mudança de escola na mesma rede',
                'desc_tipo' => 'Transferência interna entre escolas da mesma rede de ensino.',
            ],
            [
                'nm_tipo' => 'Mudança de curso ou etapa',
                'desc_tipo' => 'Transferência para outra escola em razão de mudança de curso, etapa ou modalidade de ensino.',
            ],
            [
                'nm_tipo' => 'Encerramento de turma ou escola',
                'desc_tipo' => 'Transferência motivada por encerramento de turma, série ou unidade escolar.',
            ],
            [
                'nm_tipo' => 'Solicitação da família ou responsável',
                'desc_tipo' => 'Transferência por interesse direto da família ou responsável legal.',
            ],
            [
                'nm_tipo' => 'Motivo disciplinar',
                'desc_tipo' => 'Transferência decorrente de medida disciplinar registrada em ata ou conselho.',
            ],
        ];
    }

    /**
     * Cria os motivos de transferência padrão para cada instituição cadastrada.
     */
    public function run(): void
    {
        $instituicoes = LegacyInstitution::all();

        if ($instituicoes->isEmpty()) {
            $this->command?->warn('Nenhuma instituição encontrada. TransferenciaTipoSeeder não foi executado.');
            return;
        }

        $motivos = $this->motivosPadrao();

        foreach ($instituicoes as $instituicao) {
            foreach ($motivos as $motivo) {
                LegacyTransferType::firstOrCreate(
                    [
                        'nm_tipo' => $motivo['nm_tipo'],
                        'ref_cod_instituicao' => $instituicao->getKey(),
                    ],
                    [
                        'ref_usuario_cad' => self::USUARIO_CAD,
                        'desc_tipo' => $motivo['desc_tipo'],
                        'ativo' => 1,
                    ]
                );
            }
        }

        $this->command?->info('Motivos de transferência padrão criados/atualizados para todas as instituições.');
    }
}

