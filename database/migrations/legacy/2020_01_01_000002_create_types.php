<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared(
            '
                DO $$
                BEGIN
                  IF NOT EXISTS (SELECT 1 FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace WHERE t.typname = \'typ_idlog\' AND n.nspname = \'public\') THEN
                    CREATE TYPE public.typ_idlog AS (idlog integer);
                  END IF;

                  IF NOT EXISTS (SELECT 1 FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace WHERE t.typname = \'typ_idpes\' AND n.nspname = \'public\') THEN
                    CREATE TYPE public.typ_idpes AS (idpes integer);
                  END IF;
                END $$;
            '
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared(
            '
                DROP TYPE IF EXISTS public.typ_idlog;
                DROP TYPE IF EXISTS public.typ_idpes;
            '
        );
    }
}
