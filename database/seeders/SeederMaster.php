<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orquestrador central de seeders do i-Educar.
 *
 * Chamado por `php artisan ieducar:setup` (via pacote setup) ou diretamente
 * por `php artisan db:seed --class=SeederMaster`. A ordem de execução é
 * importante: cada seeder pode depender de dados criados pelo anterior.
 *
 * Todos os seeders são idempotentes (firstOrCreate / updateOrCreate),
 * podendo ser re-executados com segurança.
 */
class SeederMaster extends Seeder
{
    public function run(): void
    {
        // 1. Localidades: país Brasil, estados, municípios e distritos (IBGE/Censo).
        $this->call(BrasilLocalidadesSeeder::class);

        // 2. Encerra o ano letivo de 2024: aprova matrículas pendentes (cursando/em exame)
        //    e finaliza os anos letivos das escolas.
        $this->call(EncerrarAno2024Seeder::class);

        // 3. Encerra todos os anos letivos anteriores ao corrente, impedindo
        //    matrículas/enturmações em anos passados.
        $this->call(EncerrarAnosAnterioresSeeder::class);

        // 4. Áreas de conhecimento e componentes curriculares conforme a BNCC
        //    (Ed. Infantil, Fundamental, Médio). Base para o vínculo série→disciplina.
        $this->call(AreaConhecimentoBnccSeeder::class);

        // 5. Configuração escolar: anos letivos, cursos BNCC, séries, vínculos
        //    escola↔curso↔série↔disciplina, sequências de enturmação e bloqueio
        //    de matrícula em série não sequente. Suporta retomada via
        //    CONFIGURACAO_ESCOLAR_FROM_ESCOLA e detecção de escolas já configuradas.
        $this->call(ConfiguracaoEscolarSeeder::class);
        
        // 6. Atendimento Educacional Especializado: curso AEE, série única e
        //    turmas Matutino/Vespertino (40 vagas) em todas as escolas ativas.
        //    Suporta retomada via AEE_SEEDER_FROM_ESCOLA.
        $this->call(AeeSeeder::class);

        // 7. Calendário escolar: cria calendário anual (dias letivos) e vincula
        //    módulos (trimestres/bimestres) ao ano letivo de cada escola.
        $this->call(CalendarioEscolarSeeder::class);

        // 8. Benefícios sociais: programas federais (Bolsa Família, BPC, PNAE etc.)
        //    para vinculação aos alunos.
        $this->call(BeneficioSocialSeeder::class);

        // 9. Raças/cores (IBGE/INEP + complementares): garante raca_educacenso para exportação.
        $this->call(RacaSeeder::class);

        // 10. Religiões: lista padrão (Católica, Evangélica, Espírita etc.)
        //    usando firstOrCreate para não violar FK em bases com dados existentes.
        $this->call(ReligiaoSeeder::class);

        // 11. Tipos de transferência: motivos padrão (mudança de endereço, de escola,
        //     encerramento de turma, solicitação da família etc.).
        $this->call(TransferenciaTipoSeeder::class);
    }
}

