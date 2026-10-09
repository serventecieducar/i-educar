CREATE OR REPLACE VIEW process_grade_suggest AS
SELECT processes.id        AS process_id,
       serie.cod_serie     AS id,
       serie.nm_serie      AS name,
       (school_years.year - serie.idade_final) || to_char(instituicao.data_base_matricula, '-mm-dd') AS start_birth,
       (school_years.year - serie.idade_inicial) || to_char(instituicao.data_base_matricula, '-mm-dd') AS end_birth,
       serie.ref_cod_curso AS course_id
FROM pmieducar.serie
         JOIN pmieducar.curso ON curso.cod_curso = serie.ref_cod_curso
         JOIN pmieducar.instituicao ON instituicao.cod_instituicao = curso.ref_cod_instituicao
         JOIN public.process_grade ON process_grade.grade_id = serie.cod_serie
         JOIN processes ON processes.id = process_grade.process_id
         JOIN school_years ON school_years.id = processes.school_year_id
WHERE serie.ativo = 1;
