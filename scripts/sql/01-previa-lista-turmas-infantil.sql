-- Prévia: lista das turmas. Rode ESTE arquivo sozinho (após a contagem).
-- Altere 2026 se necessário.

WITH turmas_alvo AS (
    SELECT
        t.cod_turma,
        t.ref_ref_cod_escola AS cod_escola,
        t.ref_ref_cod_serie AS cod_serie,
        t.nm_turma,
        t.ano
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
)
SELECT
    t.cod_turma,
    t.cod_escola,
    t.cod_serie,
    t.nm_turma,
    t.ano,
    s.nm_serie
FROM turmas_alvo t
LEFT JOIN pmieducar.serie s ON s.cod_serie = t.cod_serie
ORDER BY t.cod_escola, t.nm_turma;
