<?php

namespace Database\Seeders;

use App\Models\LegacyEmployee;
use App\Models\LegacyIndividual;
use App\Models\LegacyInstitution;
use App\Models\LegacyPerson;
use App\Models\LegacyUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Cria um único usuário fictício do tipo **SME — Apoio (operacional)** para servir de modelo
 * (login e senha = CPF fictício, sem dados reais de pessoa).
 *
 * Pré-requisitos:
 * - Instituição cadastrada (ex.: cod_instituicao=1).
 * - Perfil municipal criado por {@see PerfisUsuariosMunicipioSeeder} (este seeder chama-o se o perfil não existir).
 *
 * Credenciais de homologação (não usar em produção):
 * - **Login (matrícula):** 52998224725 (apenas dígitos, sem pontuação)
 * - **Senha:** a mesma sequência numérica (CPF fictício válido só para testes de software)
 *
 * Execução:
 * - `php artisan db:seed --class=Database\\Seeders\\ModeloUsuarioSmeApoioSeeder`
 */
class ModeloUsuarioSmeApoioSeeder extends Seeder
{
    private const PERFIL_NOME = 'SME — Apoio (operacional)';

    /**
     * CPF fictício com dígitos verificadores válidos, reservado para documentação / ambiente de teste
     * (não corresponde a cidadão real — uso apenas como modelo técnico).
     */
    private const CPF_FICTICIO_DIGITOS = '52998224725';

    private const NOME_EXIBICAO = 'Usuário modelo SME Apoio (fictício — não reproduzir CPF em produção)';

    private const EMAIL_FICTICIO = 'modelo.sme.apoio@exemplo.invalid';

    private const USUARIO_CAD = 1;

    public function run(): void
    {
        $matricula = self::CPF_FICTICIO_DIGITOS;

        if (LegacyEmployee::query()->where('matricula', $matricula)->exists()) {
            $this->command?->info('[ModeloUsuarioSmeApoioSeeder] Já existe funcionário com matrícula/CPF de modelo. Nada a fazer.');

            return;
        }

        $tipoUsuarioId = $this->resolveTipoSmeApoioId();
        if ($tipoUsuarioId === null) {
            $this->command?->error('[ModeloUsuarioSmeApoioSeeder] Não foi possível obter o cod_tipo_usuario do perfil SME — Apoio.');

            return;
        }

        $instituicao = LegacyInstitution::query()->orderBy('cod_instituicao')->first();
        if ($instituicao === null) {
            $this->command?->error('[ModeloUsuarioSmeApoioSeeder] Nenhuma instituição encontrada. Cadastre a instituição antes.');

            return;
        }

        $cpfInt = (int) $matricula;
        $senhaPlana = $matricula;

        DB::transaction(function () use ($matricula, $tipoUsuarioId, $instituicao, $cpfInt, $senhaPlana): void {
            $person = LegacyPerson::create([
                'nome' => self::NOME_EXIBICAO,
                'tipo' => 'F',
                'situacao' => 'A',
                'origem_gravacao' => 'M',
                'operacao' => 'I',
                'email' => self::EMAIL_FICTICIO,
            ]);

            LegacyIndividual::create([
                'idpes' => $person->getKey(),
                'data_nasc' => now()->subYears(35),
                'sexo' => 'M',
                'cpf' => $cpfInt,
                'operacao' => 'I',
                'origem_gravacao' => 'M',
                'data_cad' => now(),
                'zona_localizacao_censo' => 1,
                'ativo' => 1,
            ]);

            $employee = LegacyEmployee::create([
                'ref_cod_pessoa_fj' => $person->getKey(),
                'matricula' => $matricula,
                'senha' => Hash::make($senhaPlana),
                'ativo' => 1,
                'force_reset_password' => false,
                'email' => self::EMAIL_FICTICIO,
            ]);

            LegacyUser::create([
                'cod_usuario' => $employee->getKey(),
                'ref_cod_instituicao' => $instituicao->getKey(),
                'ref_funcionario_cad' => self::USUARIO_CAD,
                'ref_cod_tipo_usuario' => $tipoUsuarioId,
                'data_cadastro' => now(),
                'ativo' => 1,
            ]);
        });

        $this->command?->info(sprintf(
            '[ModeloUsuarioSmeApoioSeeder] Usuário modelo criado. Login (matrícula) e senha: %s — trocar a senha antes de qualquer uso real.',
            $matricula
        ));
    }

    private function resolveTipoSmeApoioId(): ?int
    {
        $id = DB::table('pmieducar.tipo_usuario')
            ->where('nm_tipo', self::PERFIL_NOME)
            ->where('ativo', 1)
            ->value('cod_tipo_usuario');

        if ($id !== null) {
            return (int) $id;
        }

        $this->command?->warn('[ModeloUsuarioSmeApoioSeeder] Perfil não encontrado; executando PerfisUsuariosMunicipioSeeder...');
        $this->call(PerfisUsuariosMunicipioSeeder::class);

        $id = DB::table('pmieducar.tipo_usuario')
            ->where('nm_tipo', self::PERFIL_NOME)
            ->where('ativo', 1)
            ->value('cod_tipo_usuario');

        return $id !== null ? (int) $id : null;
    }
}
