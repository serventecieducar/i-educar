<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Legacy\LegacyGrade;
use App\Models\Legacy\LegacyDiscipline;

class GradeFactory extends Factory
{
    protected $model = LegacyGrade::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'year' => now()->year,
            'course_id' => null, // será definido no seeder
            'status' => 'ativo',
        ];
    }

    /**
     * Configuração para Educação Infantil
     */
    public function infantil()
    {
        return $this->state(function () {
            return ['name' => 'Educação Infantil'];
        })->afterCreating(function (LegacyGrade $grade) {
            $disciplinas = [
                'Campos de Experiência: O eu, o outro e o nós',
                'Campos de Experiência: Corpo, gestos e movimentos',
                'Campos de Experiência: Traços, sons, cores e formas',
                'Campos de Experiência: Escuta, fala, pensamento e imaginação',
                'Campos de Experiência: Espaços, tempos, quantidades, relações e transformações',
            ];
            foreach ($disciplinas as $disciplina) {
                LegacyDiscipline::create([
                    'grade_id' => $grade->id,
                    'name' => $disciplina,
                ]);
            }
        });
    }

    /**
     * Configuração para Ensino Fundamental
     */
    public function fundamental()
    {
        return $this->state(function () {
            return ['name' => 'Ensino Fundamental'];
        })->afterCreating(function (LegacyGrade $grade) {
            $disciplinas = [
                'Língua Portuguesa',
                'Matemática',
                'Ciências',
                'História',
                'Geografia',
                'Arte',
                'Educação Física',
                'Ensino Religioso',
                'Inglês',
            ];
            foreach ($disciplinas as $disciplina) {
                LegacyDiscipline::create([
                    'grade_id' => $grade->id,
                    'name' => $disciplina,
                ]);
            }
        });
    }

    /**
     * Configuração para Ensino Médio
     */
    public function medio()
    {
        return $this->state(function () {
            return ['name' => 'Ensino Médio'];
        })->afterCreating(function (LegacyGrade $grade) {
            $disciplinas = [
                'Língua Portuguesa',
                'Matemática',
                'Biologia',
                'Física',
                'Química',
                'História',
                'Geografia',
                'Filosofia',
                'Sociologia',
                'Educação Física',
                'Arte',
                'Inglês',
            ];
            foreach ($disciplinas as $disciplina) {
                LegacyDiscipline::create([
                    'grade_id' => $grade->id,
                    'name' => $disciplina,
                ]);
            }
        });
    }
}
