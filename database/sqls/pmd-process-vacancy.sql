create view process_vacancy as
select
  p.id as process_id,
  c.school_id,
  c.grade_id,
  c.period_id,
  sum(c.vacancy) as total,
  sum(c.available_vacancies) as available_vacancies,
  sum(c.available) - (
   select
     count(*)
   from preregistrations pr
   where pr.school_id = c.school_id
   and pr.grade_id = c.grade_id
   and pr.period_id = c.period_id
   and pr.process_id = p.id
   and pr.status in (1, 4)
   and pr.preregistration_type_id in (1, 2)
   and pr.in_classroom_id is null
 ) as available
from processes p
join process_grade pg on pg.process_id = p.id
join process_period pp on pp.process_id = p.id
join classrooms c on c.grade_id = pg.grade_id
and c.period_id = pp.period_id
and c.school_year_id = p.school_year_id
where p.selected_schools = false or c.school_id in (
  select school_id from process_school_selected where process_id = p.id
)
group by p.id, c.school_id, c.grade_id, c.period_id;
