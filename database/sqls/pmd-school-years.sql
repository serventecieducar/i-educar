CREATE OR REPLACE VIEW public.school_years AS
SELECT DISTINCT escola_ano_letivo.ano AS id,
                escola_ano_letivo.ano AS year,
                escola_ano_letivo.ano AS name,
                null                  AS start_date,
                null                  AS end_date
FROM pmieducar.escola_ano_letivo
         JOIN pmieducar.escola ON escola.cod_escola = escola_ano_letivo.ref_cod_escola
         JOIN pmieducar.escola_serie ON escola_serie.ref_cod_escola = escola.cod_escola
         JOIN pmieducar.serie ON serie.cod_serie = escola_serie.ref_cod_serie
WHERE escola.ativo = 1
  AND serie.ativo = 1
