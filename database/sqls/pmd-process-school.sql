CREATE OR REPLACE VIEW process_school
AS SELECT p.id AS process_id,
          c.school_id
FROM processes p
JOIN process_grade pg ON pg.process_id = p.id
JOIN process_period pp ON pp.process_id = p.id
JOIN classrooms c ON c.grade_id = pg.grade_id AND c.period_id = pp.period_id AND c.school_year_id = p.school_year_id
WHERE p.selected_schools = false or c.school_id in (
  select school_id from process_school_selected where process_id = p.id
)
GROUP BY p.id, c.school_id;
