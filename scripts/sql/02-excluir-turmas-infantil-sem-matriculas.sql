-- Exclusão em UMA única instrução (sem BEGIN). Rode ESTE arquivo sozinho.
-- Altere 2026 em turmas_alvo antes de rodar.
--
-- Remove turmas de Educação Infantil sem ALUNO ATIVO (matricula + matricula_turma ativos).
-- Enturmações inativas (matricula_turma) nestas turmas também são apagadas antes da turma.

WITH turmas_alvo AS (
    SELECT t.cod_turma
    FROM pmieducar.turma t
    INNER JOIN pmieducar.curso c ON c.cod_curso = t.ref_cod_curso
    WHERE t.ano = 2026
      AND c.nm_curso = 'Educação Infantil'
      AND NOT EXISTS (
          SELECT 1
          FROM pmieducar.matricula_turma mt
          INNER JOIN pmieducar.matricula m ON m.cod_matricula = mt.ref_cod_matricula
          WHERE mt.ref_cod_turma = t.cod_turma
            AND mt.ativo = 1
            AND m.ativo = 1
      )
),
enturmacoes_alvo AS (
    SELECT mt.id AS matricula_turma_id
    FROM pmieducar.matricula_turma mt
    INNER JOIN turmas_alvo ta ON ta.cod_turma = mt.ref_cod_turma
),
del_educacenso_matricula AS (
    DELETE FROM modules.educacenso_matricula em
    USING enturmacoes_alvo ea
    WHERE em.matricula_turma_id = ea.matricula_turma_id
    RETURNING 1
),
del_matricula_turma AS (
    DELETE FROM pmieducar.matricula_turma mt
    USING enturmacoes_alvo ea
    WHERE mt.id = ea.matricula_turma_id
    RETURNING 1
),
del_professor_turma_disciplina AS (
    DELETE FROM modules.professor_turma_disciplina ptd
    WHERE ptd.professor_turma_id IN (
        SELECT pt.id
        FROM modules.professor_turma pt
        INNER JOIN turmas_alvo ta ON ta.cod_turma = pt.turma_id
    )
    RETURNING 1
),
del_professor_turma AS (
    DELETE FROM modules.professor_turma pt
    USING turmas_alvo ta
    WHERE pt.turma_id = ta.cod_turma
    RETURNING 1
),
del_componente_turma AS (
    DELETE FROM modules.componente_curricular_turma cct
    USING turmas_alvo ta
    WHERE cct.turma_id = ta.cod_turma
    RETURNING 1
),
del_turma_modulo AS (
    DELETE FROM pmieducar.turma_modulo tm
    USING turmas_alvo ta
    WHERE tm.ref_cod_turma = ta.cod_turma
    RETURNING 1
),
del_turma_serie AS (
    DELETE FROM pmieducar.turma_serie ts
    USING turmas_alvo ta
    WHERE ts.turma_id = ta.cod_turma
    RETURNING 1
),
del_calendario_turma AS (
    DELETE FROM modules.calendario_turma ct
    USING turmas_alvo ta
    WHERE ct.turma_id = ta.cod_turma
    RETURNING 1
)
DELETE FROM pmieducar.turma tur
USING turmas_alvo ta
WHERE tur.cod_turma = ta.cod_turma;
