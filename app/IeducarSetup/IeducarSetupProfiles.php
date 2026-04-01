<?php

namespace App\IeducarSetup;

use Database\Seeders\Setup\BelemPaSetupSeeder;
use Database\Seeders\Setup\FortalezaCeSetupSeeder;
use Database\Seeders\Setup\ItamariBaSetupSeeder;
use Database\Seeders\Setup\PortoAlegreRsSetupSeeder;

/**
 * Perfis cidade-UF para o comando `ieducar:setup`.
 * Chave = slug do argumento CLI (minúsculas, hífen).
 *
 * @phpstan-type Profile array{label: string, description: string, extra_seeders: list<class-string>}
 */
final class IeducarSetupProfiles
{
    private static ?string $activeSlug = null;

    public static function setActiveSlug(?string $slug): void
    {
        self::$activeSlug = $slug;
    }

    public static function activeSlug(): ?string
    {
        return self::$activeSlug;
    }

    /** @return array<string, Profile> */
    public static function all(): array
    {
        return [
            'default-br' => [
                'label' => 'Padrão Brasil (genérico)',
                'description' => 'Cadastros nacionais BNCC, calendário, benefícios, transferências, etc.',
                'extra_seeders' => [],
            ],
            'belem-pa' => [
                'label' => 'Belém (PA)',
                'description' => 'Ajustes e complementos específicos para a rede de Belém/PA.',
                'extra_seeders' => [
                    BelemPaSetupSeeder::class,
                ],
            ],
            'fortaleza-ce' => [
                'label' => 'Fortaleza (CE)',
                'description' => 'Ajustes e complementos específicos para a rede de Fortaleza/CE.',
                'extra_seeders' => [
                    FortalezaCeSetupSeeder::class,
                ],
            ],
            'porto-alegre-rs' => [
                'label' => 'Porto Alegre (RS)',
                'description' => 'Ajustes e complementos específicos para a rede de Porto Alegre/RS.',
                'extra_seeders' => [
                    PortoAlegreRsSetupSeeder::class,
                ],
            ],
            'itamari-ba' => [
                'label' => 'Itamari (BA)',
                'description' => 'Ajustes e complementos específicos para a rede de Itamari/BA.',
                'extra_seeders' => [
                    ItamariBaSetupSeeder::class,
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::all());
    }
}
