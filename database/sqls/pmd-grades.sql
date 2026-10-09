CREATE OR REPLACE VIEW public.grades AS
SELECT serie.cod_serie     AS id,
       serie.nm_serie      AS name,
       serie.idade_final   AS start_birth,
       serie.idade_inicial AS end_birth,
       serie.ref_cod_curso AS course_id
FROM pmieducar.serie
         JOIN pmieducar.curso ON curso.cod_curso = serie.ref_cod_curso
         JOIN pmieducar.instituicao ON instituicao.cod_instituicao = curso.ref_cod_instituicao
WHERE serie.ativo = 1
