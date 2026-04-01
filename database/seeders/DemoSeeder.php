<?php

namespace Database\Seeders;

use Database\Factories\LegacyCourseFactory;
use Database\Factories\LegacyKnowledgeAreaFactory;
use Database\Factories\LegacyPeriodFactory;
use Database\Factories\LegacySchoolFactory;
use Database\Factories\LegacyUserFactory;
use Illuminate\Database\Seeder;

/**
 * Carga **somente para desenvolvimento / treinamento**: escolas genéricas, turmas e alunos fictícios (Faker).
 *
 * Não use em produção nem após `php artisan ieducar:setup` com dados reais de município — conflita com cadastro oficial
 * (INEP, instituição, escolas) e mistura dados simulados com a rede real.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error(
                'DemoSeeder não deve ser executado em produção (dados fictícios). '.
                'Use `php artisan ieducar:setup` com o perfil do município ou cadastros manuais.'
            );

            return;
        }

        LegacyUserFactory::new()->current();

        $morning = LegacyPeriodFactory::new()->morning();
        $afternoon = LegacyPeriodFactory::new()->afternoon();
        $full = LegacyPeriodFactory::new()->full();

        $knowledgeAreaForEarlyChildhoodEducation = LegacyKnowledgeAreaFactory::new()->earlyChildhoodEducation();
        $knowledgeAreaForElementarySchool = LegacyKnowledgeAreaFactory::new()->elementarySchool();

        $earlyChildhoodEducation = LegacyCourseFactory::new()
            ->withName('Educação Infantil')
            ->standardAcademicYear()
            ->withEarlyChildhoodEducation()
            ->withKnowledgeArea($knowledgeAreaForEarlyChildhoodEducation)
            ->create();

        $elementarySchool = LegacyCourseFactory::new()
            ->withName('Ensino Fundamental')
            ->standardAcademicYear()
            ->withElementarySchool()
            ->withKnowledgeArea($knowledgeAreaForElementarySchool)
            ->create();

        LegacySchoolFactory::new()
            ->withName('Escola de Ensino Fundamental')
            ->withAdminAsDirector()
            ->withCourse($elementarySchool)
            ->withClassroomsForEachGrade($elementarySchool, $morning)
            ->withClassroomsForEachGrade($elementarySchool, $afternoon)
            ->withStudentsForEachClassrooms(count: 10)
            ->withBimonthlyAsStageType()
            ->create();

        LegacySchoolFactory::new()
            ->withName('Escola de Educação Infantil')
            ->withAdminAsDirector()
            ->withCourse($earlyChildhoodEducation)
            ->withClassroomsForEachGrade($earlyChildhoodEducation, $full)
            ->withStudentsForEachClassrooms(count: 10)
            ->withSemesterAsStageType()
            ->create();
    }
}
