CREATE VIEW process_vacancy_statistics
AS
SELECT
    pv.process_id,
    pv.school_id,
    pv.grade_id,
    pv.period_id,
    pv.total,
    pv.available,
    pv.available_vacancies,
    (
        select
            count(*)
        from preregistrations p
        where true
          and p.process_id = pv.process_id
          and p.school_id = pv.school_id
          and p.grade_id = pv.grade_id
          and p.period_id = pv.period_id
          and p.status = 1
    ) as waiting,
    (
        select
            count(*)
        from preregistrations p
        where true
          and p.process_id = pv.process_id
          and p.school_id = pv.school_id
          and p.grade_id = pv.grade_id
          and p.period_id = pv.period_id
          and p.status = 2
    ) as accepted,
    (
        select
            count(*)
        from preregistrations p
        where true
          and p.process_id = pv.process_id
          and p.school_id = pv.school_id
          and p.grade_id = pv.grade_id
          and p.period_id = pv.period_id
          and p.status = 3
    ) as rejected
FROM process_vacancy pv;
