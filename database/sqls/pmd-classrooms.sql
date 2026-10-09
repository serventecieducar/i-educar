create or replace view public.classrooms as
select
  turma.cod_turma as id,
  turma.turma_turno_id as period_id,
  turma.ref_ref_cod_escola as school_id,
  turma.ref_ref_cod_serie as grade_id,
  school_years.id as school_year_id,
  turma.nm_turma as name,
  turma.max_aluno as vacancy,
  turma.max_aluno - (
    select count(*)
    from pmieducar.matricula_turma
    join pmieducar.matricula on true
    and matricula.cod_matricula = matricula_turma.ref_cod_matricula
    where ref_cod_turma = turma.cod_turma
    and matricula_turma.ativo = 1
    and not matricula.dependencia
  ) as available_vacancies,
  turma.max_aluno - (
    select count(*)
    from pmieducar.matricula_turma
    join pmieducar.matricula on true
    and matricula.cod_matricula = matricula_turma.ref_cod_matricula
    where ref_cod_turma = turma.cod_turma
    and matricula_turma.ativo = 1
    and not matricula.dependencia
  ) - (
    select count(*)
    from preregistrations
    where in_classroom_id = turma.cod_turma
    and status in (1, 4)
    and preregistration_type_id in (1, 2)
  ) as available,
  false as multi
from pmieducar.turma
join pmieducar.serie on serie.cod_serie = turma.ref_ref_cod_serie
join school_years on school_years.year = turma.ano AND turma.ano >= LEAST(COALESCE((SELECT MAX(eal.ano) FROM pmieducar.escola_ano_letivo eal WHERE eal.andamento = 1 AND eal.ativo = 1), EXTRACT(YEAR FROM NOW())::int), EXTRACT(YEAR FROM NOW())::int)
where turma.ativo = 1
and turma.multiseriada = 0

union all

select
  turma.cod_turma as id,
  turma.turma_turno_id as period_id,
  turma.ref_ref_cod_escola as school_id,
  turma_serie.serie_id as grade_id,
  school_years.id as school_year_id,
  turma.nm_turma as name,
  turma.max_aluno as vacancy,
  turma.max_aluno - (
    select count(*)
    from pmieducar.matricula_turma
    inner join pmieducar.matricula on true
    and matricula.cod_matricula = matricula_turma.ref_cod_matricula
    where ref_cod_turma = turma.cod_turma
    and matricula_turma.ativo = 1
    and not matricula.dependencia
  ) as available_vacancies,
  turma.max_aluno - (
    select count(*)
    from pmieducar.matricula_turma
    inner join pmieducar.matricula on true
    and matricula.cod_matricula = matricula_turma.ref_cod_matricula
    where ref_cod_turma = turma.cod_turma
    and matricula_turma.ativo = 1
    and not matricula.dependencia
  ) - (
    select count(*)
    from preregistrations
    where in_classroom_id = turma.cod_turma
    and status in (1, 4)
    and preregistration_type_id in (1, 2)
  ) as available,
  true as multi
from pmieducar.turma
inner join pmieducar.turma_serie
on turma_serie.turma_id = turma.cod_turma
inner join pmieducar.serie
on serie.cod_serie = turma_serie.serie_id
inner join school_years
on school_years.year = turma.ano AND turma.ano >= LEAST(COALESCE((SELECT MAX(eal.ano) FROM pmieducar.escola_ano_letivo eal WHERE eal.andamento = 1 AND eal.ativo = 1), EXTRACT(YEAR FROM NOW())::int), EXTRACT(YEAR FROM NOW())::int)
where turma.ativo = 1
and turma.multiseriada = 1;
