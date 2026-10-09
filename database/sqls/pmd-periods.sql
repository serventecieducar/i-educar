CREATE OR REPLACE VIEW public.periods AS
SELECT id,
       nome AS name
FROM pmieducar.turma_turno
WHERE ativo = 1
