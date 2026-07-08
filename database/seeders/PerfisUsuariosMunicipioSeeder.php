<?php

namespace Database\Seeders;

use App\Process;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para criar perfis (tipos de usuário) orientados a "personas" de uma rede municipal
 * e atribuir permissões de menu (pmieducar.menu_tipo_usuario).
 *
 * Importante:
 * - Este seeder NÃO cria usuários; cria apenas os perfis e o que cada perfil pode acessar.
 * - Permissões são aplicadas por menu (public.menus) e armazenadas em menu_tipo_usuario com:
 *   - visualiza (ver)
 *   - cadastra (cadastrar/alterar)
 *   - exclui (excluir)
 *
 * Execução:
 * - php artisan db:seed --class=Database\\Seeders\\PerfisUsuariosMunicipioSeeder
 */
class PerfisUsuariosMunicipioSeeder extends Seeder
{
    private const USUARIO_SISTEMA = 1;

    private const NIVEL_INSTITUCIONAL = 2;
    private const NIVEL_ESCOLA = 4;

    public function run(): void
    {
        DB::transaction(function (): void {
            $menus = $this->loadMenusById();

            // Raízes úteis (public.menus.id) já existentes no seed de menus.
            $ROOT_ESCOLA = 3;
            $ROOT_RELATORIOS_ESCOLA = 18;
            $ROOT_DOCUMENTOS_ESCOLA = 19;
            $ROOT_EDUCACENSO = 5;
            $ROOT_CONFIGURACOES = 8;
            $ROOT_CONSULTAS_FERRAMENTAS = 81; // Escola > Ferramentas > Consultas (no seed padrão)
            $ROOT_EXPORTACOES_FERRAMENTAS = 233; // Escola > Ferramentas > Exportações

            // Exceções de processos considerados "admin/configuração" (não dar para perfis operacionais).
            $processosSensivesCadastros = [
                559, // Instituição
                620, // Calendários (cadastro)
                947, // Regras de avaliação
                948, // Fórmulas de cálculo de média
                949, // Tabelas de arredondamento
                946, // Componentes curriculares
                566, // Cursos
                583, // Séries
                570, // Tipos de turma
                584, // Tipos de etapas
                554, // Tipos de usuário
                555, // Usuários
            ];

            // 1) SME — Leitura (multi-escola): relatórios + documentos + consultas + exportações + educacenso.
            $smeRelatoriosId = $this->upsertUserType(
                'SME — Relatórios (leitura)',
                self::NIVEL_INSTITUCIONAL,
                'Perfil para equipe da Secretaria Municipal de Educação que atua em várias escolas e precisa consultar relatórios, documentos e exportações, sem cadastrar/alterar dados operacionais.'
            );
            $menusSme = array_merge(
                $this->descendantMenuIdsWithProcess($ROOT_RELATORIOS_ESCOLA, $menus),
                $this->descendantMenuIdsWithProcess($ROOT_DOCUMENTOS_ESCOLA, $menus),
                $this->descendantMenuIdsWithProcess($ROOT_CONSULTAS_FERRAMENTAS, $menus),
                $this->descendantMenuIdsWithProcess($ROOT_EXPORTACOES_FERRAMENTAS, $menus),
                $this->descendantMenuIdsWithProcess($ROOT_EDUCACENSO, $menus),
            );
            $this->grant($smeRelatoriosId, $menusSme, level: 1);

            // 2) SME — Apoio (multi-escola): combina SME (consulta) + Secretaria (operacional), sem configurações sensíveis.
            $smeApoioId = $this->upsertUserType(
                'SME — Apoio (operacional)',
                self::NIVEL_INSTITUCIONAL,
                'Perfil intermediário para equipe da SME que apoia escolas: consulta relatórios/documentos/exportações e também executa rotinas operacionais (alunos, turmas, enturmações), sem acesso a configurações sensíveis.'
            );
            $menusMovimentacaoMatricula = $this->menuIdsMovimentacoesMatricula($menus);

            $menusSmeApoio = array_merge(
                $menusSme,
                $this->descendantMenuIdsWithProcess($ROOT_ESCOLA, $menus, excludeProcesses: $processosSensivesCadastros),
                $menusMovimentacaoMatricula,
            );
            // Operacional: cadastrar/alterar (sem excluir por padrão).
            $this->grant($smeApoioId, $menusSmeApoio, level: 2, allowDelete: false);

            // 3) Secretaria Escolar — Operacional: pode cadastrar/alterar dados escolares (sem configurações sensíveis).
            $secretarioId = $this->upsertUserType(
                'Secretaria Escolar — Operacional',
                self::NIVEL_ESCOLA,
                'Perfil para secretários(as) escolares: realiza cadastros e movimentações do dia a dia (alunos, turmas, enturmações, transferências) e acompanha relatórios, sem acesso a configurações sensíveis.'
            );
            $menusSecretaria = array_merge(
                $this->descendantMenuIdsWithProcess($ROOT_ESCOLA, $menus, excludeProcesses: $processosSensivesCadastros),
                $menusMovimentacaoMatricula,
            );
            $this->grant($secretarioId, $menusSecretaria, level: 2);

            // 4) Auxiliar de Secretaria — Cadastro básico: cadastra/atualiza, sem excluir.
            $auxiliarId = $this->upsertUserType(
                'Auxiliar de Secretaria — Cadastro básico',
                self::NIVEL_ESCOLA,
                'Perfil para auxiliares: executa cadastros básicos e rotinas assistidas, com foco em inclusão/atualização; não possui permissões de exclusão.'
            );
            $menusAuxiliar = array_merge(
                $this->descendantMenuIdsWithProcess($ROOT_ESCOLA, $menus, excludeProcesses: $processosSensivesCadastros),
                $menusMovimentacaoMatricula,
            );
            $this->grant($auxiliarId, $menusAuxiliar, level: 2, allowDelete: false);

            // 5) Direção — Gestão (leitura + algumas ações): acompanha relatórios e faz poucas ações operacionais.
            $direcaoId = $this->upsertUserType(
                'Direção — Gestão escolar',
                self::NIVEL_ESCOLA,
                'Perfil para diretor(a) e vice-diretor(a): acompanha relatórios, documentos e rotinas de gestão; acesso operacional é reduzido e focado em visualização.'
            );
            $menusDirecao = array_merge(
                $this->descendantMenuIdsWithProcess($ROOT_RELATORIOS_ESCOLA, $menus),
                $this->descendantMenuIdsWithProcess($ROOT_DOCUMENTOS_ESCOLA, $menus),
                $this->descendantMenuIdsWithProcess($ROOT_CONSULTAS_FERRAMENTAS, $menus),
                $this->descendantMenuIdsWithProcess($ROOT_EXPORTACOES_FERRAMENTAS, $menus),
            );
            $this->grant($direcaoId, $menusDirecao, level: 1);

            // Segurança: não atribuir nada em Configurações por padrão.
            $this->revokeFromRoot($smeRelatoriosId, $ROOT_CONFIGURACOES, $menus);
            $this->revokeFromRoot($smeApoioId, $ROOT_CONFIGURACOES, $menus);
            $this->revokeFromRoot($secretarioId, $ROOT_CONFIGURACOES, $menus);
            $this->revokeFromRoot($auxiliarId, $ROOT_CONFIGURACOES, $menus);
            $this->revokeFromRoot($direcaoId, $ROOT_CONFIGURACOES, $menus);
        });
    }

    /**
     * @return array<int, array{parent_id: ?int, process: ?int}>
     */
    private function loadMenusById(): array
    {
        $rows = DB::table('public.menus')->select(['id', 'parent_id', 'process'])->get();
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r->id] = [
                'parent_id' => $r->parent_id !== null ? (int) $r->parent_id : null,
                'process' => $r->process !== null ? (int) $r->process : null,
            ];
        }

        return $byId;
    }

    private function upsertUserType(string $name, int $nivel, string $descricao): int
    {
        $existing = DB::table('pmieducar.tipo_usuario')->where('nm_tipo', $name)->first(['cod_tipo_usuario']);
        if ($existing) {
            DB::table('pmieducar.tipo_usuario')->where('cod_tipo_usuario', (int) $existing->cod_tipo_usuario)->update([
                'nivel' => $nivel,
                'descricao' => $descricao,
                'ativo' => 1,
            ]);

            return (int) $existing->cod_tipo_usuario;
        }

        return (int) DB::table('pmieducar.tipo_usuario')->insertGetId([
            'ref_funcionario_cad' => self::USUARIO_SISTEMA,
            'nm_tipo' => $name,
            'descricao' => $descricao,
            'nivel' => $nivel,
            'data_cadastro' => now(),
            'ativo' => 1,
        ], 'cod_tipo_usuario');
    }

    /**
     * Bloco "Movimentações de Matrícula" (processo {@see Process::REGISTRATION_ACTIONS} e filhos:
     * nova matrícula 680, enturmar 683, desenturmar 696, remanejar 695, etc.).
     * Fora da árvore Escola (menu 3) — necessário para perfis operacionais da SME.
     *
     * @param  array<int, array{parent_id: ?int, process: ?int}>  $menus
     * @return list<int>
     */
    private function menuIdsMovimentacoesMatricula(array $menus): array
    {
        $rootId = null;
        foreach ($menus as $id => $meta) {
            if (($meta['process'] ?? null) === Process::REGISTRATION_ACTIONS) {
                $rootId = $id;
                break;
            }
        }

        if ($rootId === null) {
            return [];
        }

        return $this->descendantMenuIdsWithProcess($rootId, $menus);
    }

    /**
     * @param  array<int, array{parent_id: ?int, process: ?int}>  $menus
     * @param  list<int>  $excludeProcesses
     * @return list<int>
     */
    private function descendantMenuIdsWithProcess(int $rootId, array $menus, array $excludeProcesses = []): array
    {
        $exclude = array_fill_keys($excludeProcesses, true);
        $result = [];
        $queue = [$rootId];

        while ($queue !== []) {
            $current = array_shift($queue);
            if ($current === null) {
                continue;
            }

            $meta = $menus[$current] ?? null;
            if ($meta && $meta['process'] !== null) {
                if (!isset($exclude[$meta['process']])) {
                    $result[] = $current;
                }
            }

            foreach ($menus as $id => $m) {
                if ($m['parent_id'] === $current) {
                    $queue[] = $id;
                }
            }
        }

        $result = array_values(array_unique($result));
        sort($result);

        return $result;
    }

    /**
     * @param list<int> $menuIds
     */
    private function grant(int $userTypeId, array $menuIds, int $level, bool $allowDelete = true): void
    {
        $visualiza = $level >= 1;
        $cadastra = $level >= 2;
        $exclui = $allowDelete && $level >= 3;

        foreach ($menuIds as $menuId) {
            DB::table('pmieducar.menu_tipo_usuario')->updateOrInsert(
                [
                    'ref_cod_tipo_usuario' => $userTypeId,
                    'menu_id' => $menuId,
                ],
                [
                    'visualiza' => $visualiza,
                    'cadastra' => $cadastra,
                    'exclui' => $exclui,
                ]
            );
        }
    }

    /**
     * Remove permissões em todos os menus abaixo de uma raiz.
     *
     * @param  array<int, array{parent_id: ?int, process: ?int}>  $menus
     */
    private function revokeFromRoot(int $userTypeId, int $rootId, array $menus): void
    {
        $ids = $this->descendantMenuIdsWithProcess($rootId, $menus);
        if ($ids === []) {
            return;
        }

        DB::table('pmieducar.menu_tipo_usuario')
            ->where('ref_cod_tipo_usuario', $userTypeId)
            ->whereIn('menu_id', $ids)
            ->delete();
    }
}

