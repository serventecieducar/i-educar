<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateSchemas extends Migration
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
                CREATE SCHEMA IF NOT EXISTS cadastro;
                CREATE SCHEMA IF NOT EXISTS modules;
                CREATE SCHEMA IF NOT EXISTS pmieducar;
                CREATE SCHEMA IF NOT EXISTS portal;
                CREATE SCHEMA IF NOT EXISTS relatorio;
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
                DROP SCHEMA IF EXISTS cadastro CASCADE;
                DROP SCHEMA IF EXISTS modules CASCADE;
                DROP SCHEMA IF EXISTS pmieducar CASCADE;
                DROP SCHEMA IF EXISTS portal CASCADE;
                DROP SCHEMA IF EXISTS relatorio CASCADE;
            '
        );
    }
}
