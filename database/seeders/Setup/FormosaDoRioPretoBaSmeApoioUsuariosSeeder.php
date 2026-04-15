<?php

namespace Database\Seeders\Setup;

use App\Http\Middleware\CheckResetPassword;
use App\Models\LegacyEmployee;
use App\Models\LegacyIndividual;
use App\Models\LegacyInstitution;
use App\Models\LegacyPerson;
use App\Models\LegacySchool;
use App\Models\LegacyUser;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Usuários municipais — Formosa do Rio Preto/BA — perfil **SME — Apoio (operacional)**.
 *
 * **Quem são no modelo de dados (não confundir com “servidor” escolar):**
 * - São **apenas usuários do sistema** (login, permissões de menu, vínculo às escolas como **usuário**).
 * - **Não** são cadastrados como **servidores da rede** (`pmieducar.servidor`): não há função, alocação,
 *   disciplinas ministradas nem vínculo de RH escolar. O cadastro de “servidor” é outro fluxo (educar_servidor_*).
 * - A tabela `portal.funcionario` guarda **matrícula/senha do acesso web**; o nome legado “funcionário” aqui
 *   não implica servidor escolar no Educacenso.
 *
 * - Login na tela pública: **CPF somente números** (campo matrícula) + senha inicial.
 * - `force_reset_password`: o usuário é redirecionado para **definir senha pessoal** no primeiro acesso
 *   (`App\Http\Middleware\CheckResetPassword`).
 * - Vínculo em **todas as escolas ativas** da instituição (`pmieducar.escola_usuario`), para permissões
 *   operacionais em **qualquer escola** da rede (perfil institucional SME — Apoio), não como lotação de servidor.
 *
 * Pré-requisitos:
 * - `Database\Seeders\PerfisUsuariosMunicipioSeeder` (perfil "SME — Apoio (operacional)").
 * - Instituição e escolas já cadastradas.
 *
 * Senha inicial:
 * - **Igual ao CPF** (11 dígitos, mesma sequência usada no login no campo matrícula).
 *
 * Execução:
 * - `php artisan db:seed --class=Database\\Seeders\\Setup\\FormosaDoRioPretoBaSmeApoioUsuariosSeeder`
 */
class FormosaDoRioPretoBaSmeApoioUsuariosSeeder extends Seeder
{
    private const USUARIO_CAD = 1;

    private const TIPO_USUARIO_NOME = 'SME — Apoio (operacional)';

    public function run(): void
    {
        $tipoUsuarioId = DB::table('pmieducar.tipo_usuario')
            ->where('nm_tipo', self::TIPO_USUARIO_NOME)
            ->value('cod_tipo_usuario');

        if ($tipoUsuarioId === null) {
            $this->command?->error(
                'Tipo de usuário "'.self::TIPO_USUARIO_NOME.'" não encontrado. Execute: php artisan db:seed --class=Database\\\\Seeders\\\\PerfisUsuariosMunicipioSeeder'
            );

            return;
        }

        $instituicao = LegacyInstitution::query()
            ->where('ativo', 1)
            ->where(function ($q) {
                $q->where('cidade', 'ilike', '%Formosa do Rio Preto%')
                    ->orWhere('nm_instituicao', 'ilike', '%Formosa do Rio Preto%');
            })
            ->orderBy('cod_instituicao')
            ->first();

        if ($instituicao === null) {
            $instituicao = LegacyInstitution::query()->whereKey(1)->first();
        }

        if ($instituicao === null) {
            $this->command?->error('Nenhuma instituição encontrada para vincular os usuários.');

            return;
        }

        $instituicaoId = (int) $instituicao->cod_instituicao;

        $escolasIds = LegacySchool::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('ativo', 1)
            ->pluck('cod_escola')
            ->all();

        if ($escolasIds === []) {
            $this->command?->warn("Instituição {$instituicaoId}: nenhuma escola ativa; escola_usuario não será preenchido.");
        }

        $registros = $this->registros();

        DB::transaction(function () use ($registros, $tipoUsuarioId, $instituicaoId, $escolasIds): void {
            foreach ($registros as $row) {
                $this->criarOuAtualizarUsuario(
                    $row,
                    (int) $tipoUsuarioId,
                    $instituicaoId,
                    $escolasIds
                );
            }
        });

        $this->command?->info('Formosa do Rio Preto — usuários SME Apoio processados: '.count($registros).'.');
    }

    /**
     * @return list<array{nome: string, cpf: string, data_nasc: string, sexo: 'M'|'F', ideciv: int, email: string, nome_mae: string, nome_pai: string}>
     */
    private function registros(): array
    {
        return [
            [
                'nome' => 'Jadson Damião Pereira de Oliveira',
                'cpf' => '03102702511',
                'data_nasc' => '27/09/1984',
                'sexo' => 'M',
                'ideciv' => 1,
                'email' => 'jadson.oliveira@enova.educacao.ba.gov.br',
                'nome_mae' => 'Maria Pereira de Oliveira',
                'nome_pai' => 'Jackson Oliveira Santos',
            ],
            [
                'nome' => 'Edna de Almeida Rocha',
                'cpf' => '07076928574',
                'data_nasc' => '08/05/1993',
                'sexo' => 'F',
                'ideciv' => 1,
                'email' => 'ednaalmeidarocha200@gmail.com',
                'nome_mae' => 'Maria Joaquina de Almeida Rocha',
                'nome_pai' => 'Edson de Oliveira Rocha',
            ],
            [
                'nome' => 'Rita de Cássia de Souza Gomes Rocha',
                'cpf' => '09827201508',
                'data_nasc' => '16/01/1982',
                'sexo' => 'F',
                'ideciv' => 2,
                'email' => 'ritadecssiadesouzagomes@yahoo.com.br',
                'nome_mae' => 'Anita Ribeiro de Souza Gomes',
                'nome_pai' => 'Marcelino Gomes',
            ],
            [
                'nome' => 'Fabíola Iemanja Régis Lisboa Carvalho',
                'cpf' => '01794536531',
                'data_nasc' => '28/12/1985',
                'sexo' => 'F',
                'ideciv' => 2,
                'email' => 'fabiolaregislis034@gmail.com',
                'nome_mae' => 'Onelia Lúcia Régis da Silva Lisboa',
                'nome_pai' => 'Inalvo Lisboa dos Santos',
            ],
        ];
    }

    /**
     * @param  array{nome: string, cpf: string, data_nasc: string, sexo: 'M'|'F', ideciv: int, email: string, nome_mae: string, nome_pai: string}  $row
     * @param  list<int>  $escolasIds
     */
    private function criarOuAtualizarUsuario(
        array $row,
        int $tipoUsuarioId,
        int $instituicaoId,
        array $escolasIds
    ): void {
        $cpf = preg_replace('/\D/', '', $row['cpf']) ?? '';
        if (strlen($cpf) !== 11) {
            $this->command?->warn('CPF inválido (ignorado): '.$row['cpf']);

            return;
        }

        $senhaPlain = $cpf;

        if (LegacyEmployee::query()->where('matricula', $cpf)->exists()) {
            $codUsuario = (int) LegacyEmployee::query()->where('matricula', $cpf)->value('ref_cod_pessoa_fj');
            $this->syncUsuarioInstituicaoTipoESenhaCpf($cpf, $tipoUsuarioId, $instituicaoId, $senhaPlain);
            $this->forcarTrocaSenhaNoProximoAcesso($codUsuario);
            $this->vincularEscolasUsuario($codUsuario, $escolasIds);
            $this->garantirQueNaoHaServidorEscolarParaPessoa($codUsuario, $cpf);
            $this->command?->line("Usuário já existia (CPF {$cpf}); vínculos, perfil, senha (CPF) e `force_reset_password` atualizados.");

            return;
        }

        $dataNasc = Carbon::createFromFormat('d/m/Y', $row['data_nasc'])->format('Y-m-d');

        $person = LegacyPerson::create([
            'nome' => $row['nome'],
            'tipo' => 'F',
            'email' => $row['email'],
        ]);

        LegacyIndividual::create([
            'idpes' => $person->getKey(),
            'data_nasc' => $dataNasc,
            'sexo' => $row['sexo'],
            'cpf' => $cpf,
            'nome_mae' => $row['nome_mae'],
            'nome_pai' => $row['nome_pai'],
            'ideciv' => $row['ideciv'],
            'nacionalidade' => 1,
            'operacao' => 'I',
            'origem_gravacao' => 'M',
            'data_cad' => now(),
            'zona_localizacao_censo' => 1,
        ]);

        $employee = LegacyEmployee::create([
            'ref_cod_pessoa_fj' => $person->getKey(),
            'matricula' => $cpf,
            'senha' => Hash::make($senhaPlain),
            'ativo' => 1,
            'force_reset_password' => true,
            'email' => $row['email'],
        ]);

        LegacyUser::create([
            'cod_usuario' => $employee->getKey(),
            'ref_cod_instituicao' => $instituicaoId,
            'ref_funcionario_cad' => self::USUARIO_CAD,
            'ref_cod_tipo_usuario' => $tipoUsuarioId,
            'data_cadastro' => now(),
            'ativo' => 1,
        ]);

        $this->vincularEscolasUsuario((int) $person->getKey(), $escolasIds);

        $this->garantirQueNaoHaServidorEscolarParaPessoa((int) $person->getKey(), $cpf);

        Log::info('[FormosaDoRioPretoBaSmeApoioUsuariosSeeder] Usuário criado', [
            'cpf' => $cpf,
            'nome' => $row['nome'],
            'cod_usuario' => $person->getKey(),
        ]);

        $this->command?->info("Criado: {$row['nome']} — login e senha inicial: CPF {$cpf} (11 dígitos).");
    }

    /**
     * Atualiza instituição, tipo de usuário e **senha = CPF** (hash) em usuário já existente.
     * O flag `force_reset_password` é aplicado em {@see forcarTrocaSenhaNoProximoAcesso} no fluxo de atualização.
     */
    private function syncUsuarioInstituicaoTipoESenhaCpf(
        string $cpfMatricula,
        int $tipoUsuarioId,
        int $instituicaoId,
        string $senhaPlainCpf
    ): void {
        $idpes = LegacyEmployee::query()->where('matricula', $cpfMatricula)->value('ref_cod_pessoa_fj');
        if ($idpes === null) {
            return;
        }

        LegacyUser::query()->where('cod_usuario', (int) $idpes)->update([
            'ref_cod_instituicao' => $instituicaoId,
            'ref_cod_tipo_usuario' => $tipoUsuarioId,
            'ativo' => 1,
        ]);

        LegacyEmployee::query()->where('ref_cod_pessoa_fj', (int) $idpes)->update([
            'senha' => Hash::make($senhaPlainCpf),
        ]);
    }

    /**
     * `portal.funcionario.force_reset_password`: obriga troca de senha no próximo acesso
     * ({@see CheckResetPassword}).
     */
    private function forcarTrocaSenhaNoProximoAcesso(int $idpes): void
    {
        $affected = DB::table('portal.funcionario')
            ->where('ref_cod_pessoa_fj', $idpes)
            ->update(['force_reset_password' => true]);

        if ($affected === 0) {
            $this->command?->warn("Não foi possível definir force_reset_password para idpes {$idpes} (registro em portal.funcionario não encontrado).");
        }
    }

    /**
     * Garante que não existe (e o seed não cria) vínculo de **servidor escolar** — tabela `pmieducar.servidor`.
     * Para pessoas recém-criadas pelo seed isso deve ser sempre ausente; se já existir dado legado, apenas avisa.
     */
    private function garantirQueNaoHaServidorEscolarParaPessoa(int $idpes, string $cpf): void
    {
        if (DB::table('pmieducar.servidor')->where('cod_servidor', $idpes)->exists()) {
            $this->command?->warn(
                "idpes {$idpes} (CPF {$cpf}) possui registro em pmieducar.servidor (servidor escolar). ".
                'Este seed não criou esse vínculo; revise se a pessoa deve ser apenas usuário SME.'
            );
        }
    }

    /**
     * @param  list<int>  $escolasIds
     */
    private function vincularEscolasUsuario(int $codUsuario, array $escolasIds): void
    {
        foreach ($escolasIds as $codEscola) {
            $exists = DB::table('pmieducar.escola_usuario')
                ->where('ref_cod_usuario', $codUsuario)
                ->where('ref_cod_escola', $codEscola)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('pmieducar.escola_usuario')->insert([
                'ref_cod_usuario' => $codUsuario,
                'ref_cod_escola' => $codEscola,
            ]);
        }
    }
}
