<?php

namespace App\Http\Controllers;

use App\Models\LegacySchoolClass;
use App\Services\SchoolClass\SchoolClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CleanupEmptyActiveSchoolClassesController extends Controller
{
    /**
     * Localiza turmas ativas sem nenhuma enturmação ativa (matricula_turma.ativo = 1)
     * e aplica a mesma exclusão lógica usada no cadastro de turmas.
     */
    public function __invoke(SchoolClassService $schoolClassService): JsonResponse
    {
        $deletedIds = [];
        $skipped = [];

        DB::transaction(function () use ($schoolClassService, &$deletedIds, &$skipped) {
            LegacySchoolClass::query()
                ->active()
                ->whereDoesntHave('enrollments', function ($q) {
                    $q->where('ativo', 1);
                })
                ->orderBy('cod_turma')
                ->lazyById(100, 'cod_turma')
                ->each(function (LegacySchoolClass $schoolClass) use ($schoolClassService, &$deletedIds, &$skipped) {
                    try {
                        $schoolClassService->deleteSchoolClass($schoolClass);
                        $deletedIds[] = $schoolClass->cod_turma;
                    } catch (ValidationException $e) {
                        $skipped[] = [
                            'cod_turma' => $schoolClass->cod_turma,
                            'motivo' => $e->validator->errors()->first(),
                        ];
                    }
                });
        });

        return response()->json([
            'msg' => 'Processamento concluído.',
            'excluidas' => count($deletedIds),
            'cod_turmas' => $deletedIds,
            'ignoradas' => $skipped,
        ]);
    }
}
