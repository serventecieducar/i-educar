-- Prévia: contagem. Rode ESTE arquivo sozinho (uma consulta). Altere 2026 se necessário.

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
    SELECT mt.id
    FROM pmieducar.matricula_turma mt
    INNER JOIN turmas_alvo ta ON ta.cod_turma = mt.ref_cod_turma
)
SELECT
    (SELECT COUNT(*) FROM turmas_alvo) AS total_turmas_a_remover,
    (SELECT COUNT(*) FROM enturmacoes_alvo) AS total_enturmacoes_inativas_a_remover;
