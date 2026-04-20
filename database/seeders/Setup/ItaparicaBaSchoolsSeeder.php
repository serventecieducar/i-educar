<?php

namespace Database\Seeders\Setup;

use App\Models\City;
use App\Models\LegacyIndividual;
use App\Models\LegacyOrganization;
use App\Models\LegacyPerson;
use App\Models\LegacyPhone;
use App\Models\LegacySchool;
use App\Models\Place;
use App\Models\SchoolInep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Importa/sincroniza dados de escolas de Itaparica/BA a partir de CSV exportado da planilha municipal.
 *
 * Arquivo esperado:
 * - database/seeders/Setup/data/itaparica-ba-escolas.csv
 *
 * Colunas (header):
 * - Pode ser o formato "compacto" (inep,nome,...) ou o formato exportado do Excel (pt-BR),
 *   por exemplo: "INEP,Nome da Escola,CEP,Logradouro,Número,Bairro,Zona,Telefone da unidade,E-mail da unidade,..."
 *
 * Preenche o máximo possível nas bases:
 * - pmieducar.escola (campos educacenso e zona)
 * - cadastro.pessoa (nome/e-mail)
 * - cadastro.juridica (fantasia)
 * - modules.educacenso_cod_escola (INEP)
 * - places + person_has_place (endereço)
 */
class ItaparicaBaSchoolsSeeder extends Seeder
{
    private const USUARIO_CAD = 1;

    private const ADDRESS_TYPE = 1;

    private const CSV_PATH = __DIR__ . '/data/itaparica-ba-escolas.csv';

    public function run(): void
    {
        if (!is_file(self::CSV_PATH)) {
            $this->command?->warn('[ItaparicaBaSchoolsSeeder] CSV não encontrado em ' . self::CSV_PATH . '. Pulando importação.');

            return;
        }

        $rows = $this->readCsv(self::CSV_PATH);
        if ($rows === []) {
            $this->command?->warn('[ItaparicaBaSchoolsSeeder] CSV vazio. Nada a importar.');

            return;
        }

        $instituicaoId = 1;
        $cityId = $this->resolveCityId('Itaparica', 'BA');

        DB::transaction(function () use ($rows, $instituicaoId, $cityId): void {
            foreach ($rows as $row) {
                $this->upsertSchoolFromRow($row, $instituicaoId, $cityId);
            }
        });

        Log::channel('daily')->info('ieducar:setup [itaparica-ba] — escolas importadas do CSV.', [
            'csv' => basename(self::CSV_PATH),
            'rows' => count($rows),
        ]);
    }

    /**
     * @return list<array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $header = null;
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(fn ($h) => trim((string) $h), $data);
                continue;
            }

            if ($data === [null] || $data === false) {
                continue;
            }

            $assoc = [];
            foreach ($header as $i => $key) {
                $assoc[$key] = isset($data[$i]) ? trim((string) $data[$i]) : '';
            }

            $assoc = $this->normalizeRow($assoc);

            if (($assoc['inep'] ?? '') === '' || ($assoc['nome'] ?? '') === '') {
                continue;
            }

            $rows[] = $assoc;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Normaliza headers/colunas de diferentes exports para chaves canônicas.
     *
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function normalizeRow(array $row): array
    {
        $get = function (array $keys) use ($row): string {
            foreach ($keys as $k) {
                if (array_key_exists($k, $row)) {
                    return (string) $row[$k];
                }
            }

            return '';
        };

        $inep = $get(['inep', 'INEP']);
        $nome = $get(['nome', 'Nome da Escola', 'Escola', 'NOME']);
        $email = $get(['email', 'E-mail da unidade', 'Email', 'E-mail']);
        $telefone = $get(['telefone', 'Telefone da unidade', 'Telefone', 'Fone']);
        $cep = $get(['cep', 'CEP']);
        $logradouro = $get(['logradouro', 'Logradouro', 'Endereço']);
        $numero = $get(['numero', 'Número', 'Numero']);
        $bairro = $get(['bairro', 'Bairro']);
        $zona = $get(['zona_urbana', 'Zona']);
        $latitude = $get(['latitude', 'Latitude']);
        $longitude = $get(['longitude', 'Longitude']);
        $cpfDiretor = $get(['cpf_diretor', 'CPF do Diretor(a)', 'CPF Diretor', 'CPF do Diretor']);
        $nomeDiretor = $get(['nome_diretor', 'Nome do Diretor(a)', 'Diretor(a)', 'Nome Diretor']);
        $cpfSecretario = $get(['cpf_secretario', 'CPF do Secretário(a)', 'CPF Secretário', 'CPF do Secretario']);
        $nomeSecretario = $get(['nome_secretario', 'Nome do Secretário(a)', 'Secretário(a)', 'Nome Secretário']);

        // "Zona" vem como "Urbana/Rural" (ou vazio). Normaliza para boolean-like.
        $zonaNorm = '';
        $zonaRaw = strtolower(trim((string) $zona));
        if ($zonaRaw !== '') {
            $zonaNorm = in_array($zonaRaw, ['urbana', 'urbano', 'u'], true) ? 'true' : 'false';
        }

        return [
            'inep' => trim((string) $inep),
            'nome' => trim((string) $nome),
            'email' => trim((string) $email),
            'telefone' => trim((string) $telefone),
            'cep' => trim((string) $cep),
            'logradouro' => trim((string) $logradouro),
            'numero' => trim((string) $numero),
            'bairro' => trim((string) $bairro),
            'zona_urbana' => $zonaNorm,
            'latitude' => trim((string) $latitude),
            'longitude' => trim((string) $longitude),
            'cpf_diretor' => trim((string) $cpfDiretor),
            'nome_diretor' => trim((string) $nomeDiretor),
            'cpf_secretario' => trim((string) $cpfSecretario),
            'nome_secretario' => trim((string) $nomeSecretario),
        ];
    }

    private function resolveCityId(string $cityName, string $uf): ?int
    {
        $city = City::queryFindByName($cityName)
            ->whereHas('state', fn ($q) => $q->where('abbreviation', $uf))
            ->first();

        return $city?->id ? (int) $city->id : null;
    }

    /**
     * @param array<string, string> $row
     */
    private function upsertSchoolFromRow(array $row, int $instituicaoId, ?int $cityId): void
    {
        $inep = (int) preg_replace('/\D/', '', (string) ($row['inep'] ?? ''));
        $nome = trim((string) ($row['nome'] ?? ''));

        if ($inep <= 0 || $nome === '') {
            return;
        }

        $email = trim((string) ($row['email'] ?? '')) ?: null;
        $telefone = trim((string) ($row['telefone'] ?? '')) ?: null;
        $cep = preg_replace('/\D/', '', (string) ($row['cep'] ?? ''));
        $logradouro = trim((string) ($row['logradouro'] ?? ''));
        $numero = trim((string) ($row['numero'] ?? ''));
        $bairro = trim((string) ($row['bairro'] ?? ''));
        $zonaUrbanaRaw = strtolower(trim((string) ($row['zona_urbana'] ?? '')));
        $zonaUrbana = $zonaUrbanaRaw !== '' && in_array($zonaUrbanaRaw, ['1', 'true', 'sim', 's', 'yes', 'y'], true);
        $zonaLocalizacao = $zonaUrbanaRaw === '' ? null : ($zonaUrbana ? 1 : 2);
        $latitude = trim((string) ($row['latitude'] ?? ''));
        $longitude = trim((string) ($row['longitude'] ?? ''));
        $cpfDiretor = trim((string) ($row['cpf_diretor'] ?? ''));
        $nomeDiretor = trim((string) ($row['nome_diretor'] ?? ''));
        $cpfSecretario = trim((string) ($row['cpf_secretario'] ?? ''));
        $nomeSecretario = trim((string) ($row['nome_secretario'] ?? ''));

        $existingInep = SchoolInep::query()->where('cod_escola_inep', $inep)->first();
        $school = null;

        if ($existingInep !== null) {
            $school = LegacySchool::query()->whereKey($existingInep->cod_escola)->first();
        }

        if ($school === null) {
            $person = LegacyPerson::query()->create([
                'nome' => $nome,
                'tipo' => 'J',
                'email' => $email,
            ]);

            // CNPJ provisório (seed) — não é o CNPJ real da mantenedora.
            $cnpj = 29000000000000 + ($inep * 100);

            LegacyOrganization::query()->create([
                'idpes' => $person->getKey(),
                'cnpj' => $cnpj,
                'insc_estadual' => 0,
                'origem_gravacao' => 'M',
                'idpes_cad' => self::USUARIO_CAD,
                'data_cad' => now(),
                'operacao' => 'I',
                'fantasia' => $nome,
            ]);

            $school = LegacySchool::query()->create([
                'ref_usuario_cad' => self::USUARIO_CAD,
                'ref_cod_instituicao' => $instituicaoId,
                'sigla' => substr((string) $inep, 0, 20),
                'ref_idpes' => $person->getKey(),
                'ativo' => 1,
                'zona_localizacao' => $zonaLocalizacao,
                'nao_ha_funcionarios_para_funcoes' => true,
                'data_cadastro' => now(),
            ]);
        } else {
            // Atualiza pessoa (nome/e-mail) e escola (zona/coord).
            LegacyPerson::query()->whereKey($school->ref_idpes)->update([
                'nome' => $nome,
                'email' => $email,
            ]);

            LegacyOrganization::query()->whereKey($school->ref_idpes)->update([
                'fantasia' => $nome,
            ]);

            $payload = [];

            if ($zonaLocalizacao !== null) {
                $payload['zona_localizacao'] = $zonaLocalizacao;
            }

            if ($latitude !== '' && $longitude !== '') {
                $payload['latitude'] = $latitude;
                $payload['longitude'] = $longitude;
            }

            if (($school->sigla ?? '') === '') {
                $payload['sigla'] = substr((string) $inep, 0, 20);
            }

            if ($payload !== []) {
                $school->update($payload);
            }
        }

        $this->upsertPhone($school->ref_idpes, $telefone);

        // Diretor(a) e secretário(a): cria/atualiza pessoa física (com CPF quando disponível) e vincula na escola.
        $directorId = $this->upsertIndividualPerson($cpfDiretor, $nomeDiretor);
        if ($directorId) {
            $school->update([
                'ref_idpes_gestor' => $directorId,
            ]);
        }

        $secretaryId = $this->upsertIndividualPerson($cpfSecretario, $nomeSecretario);
        if ($secretaryId) {
            $school->update([
                'ref_idpes_secretario_escolar' => $secretaryId,
            ]);
        }

        SchoolInep::query()->updateOrCreate(
            ['cod_escola' => $school->getKey()],
            [
                'cod_escola_inep' => $inep,
                'nome_inep' => $nome,
                'fonte' => 'MUNICIPIO_ITAPARICA_BA_CSV',
            ]
        );

        $this->upsertAddressPlace(
            personId: (int) $school->ref_idpes,
            cityId: $cityId,
            address: $logradouro,
            number: $numero,
            neighborhood: $bairro,
            postalCode: $cep,
            latitude: $latitude,
            longitude: $longitude
        );
    }

    private function upsertPhone(int $personId, ?string $rawPhone): void
    {
        if (!$rawPhone) {
            return;
        }

        $digits = preg_replace('/\D/', '', $rawPhone);
        if (!$digits || strlen($digits) < 10) {
            return;
        }

        $ddd = substr($digits, 0, 2);
        $fone = substr($digits, 2);

        LegacyPhone::query()->updateOrCreate(
            [
                'idpes' => $personId,
                'tipo' => 1,
            ],
            [
                'ddd' => $ddd,
                'fone' => $fone,
                'idpes_cad' => self::USUARIO_CAD,
                'idpes_rev' => self::USUARIO_CAD,
                'data_rev' => now(),
            ]
        );
    }

    private function upsertIndividualPerson(string $cpf, string $name): ?int
    {
        $name = trim($name);
        $cpfDigits = preg_replace('/\D/', '', $cpf);

        if ($name === '') {
            return null;
        }

        // Se tiver CPF válido, tenta localizar a pessoa física por CPF.
        if ($cpfDigits && strlen($cpfDigits) === 11) {
            $existingIndividual = LegacyIndividual::findByCpf($cpfDigits);
            if ($existingIndividual) {
                $personId = (int) $existingIndividual->getKey();
                LegacyPerson::query()->whereKey($personId)->update([
                    'nome' => $name,
                ]);

                return $personId;
            }
        }

        // Sem CPF (ou CPF não informado): cria uma pessoa física mínima.
        $person = LegacyPerson::query()->create([
            'nome' => $name,
            'tipo' => 'F',
            'email' => null,
        ]);

        // Cria registro em fisica (cpf quando válido).
        $payload = [
            'idpes' => $person->getKey(),
            'cpf' => ($cpfDigits && strlen($cpfDigits) === 11) ? (int) $cpfDigits : null,
            'ativo' => 1,
        ];

        LegacyIndividual::query()->updateOrCreate(
            ['idpes' => $person->getKey()],
            $payload
        );

        return (int) $person->getKey();
    }

    private function upsertAddressPlace(
        int $personId,
        ?int $cityId,
        string $address,
        string $number,
        string $neighborhood,
        string $postalCode,
        string $latitude,
        string $longitude
    ): void {
        if (!$cityId || $address === '' || $neighborhood === '' || $postalCode === '') {
            return;
        }

        $existing = DB::table('public.person_has_place')
            ->where('person_id', $personId)
            ->where('type', self::ADDRESS_TYPE)
            ->whereNull('deleted_at')
            ->first(['place_id']);

        $placePayload = [
            'city_id' => $cityId,
            'address' => $address,
            'number' => $number !== '' ? $number : null,
            'neighborhood' => $neighborhood,
            'postal_code' => $postalCode,
            'latitude' => $latitude !== '' ? $latitude : null,
            'longitude' => $longitude !== '' ? $longitude : null,
        ];

        if ($existing?->place_id) {
            Place::query()->whereKey((int) $existing->place_id)->update($placePayload);
            $placeId = (int) $existing->place_id;
        } else {
            $place = Place::query()->create($placePayload);
            $placeId = (int) $place->getKey();
        }

        DB::table('public.person_has_place')->updateOrInsert(
            [
                'person_id' => $personId,
                'type' => self::ADDRESS_TYPE,
            ],
            [
                'place_id' => $placeId,
                'updated_at' => now(),
                'created_at' => now(),
                'deleted_at' => null,
            ]
        );
    }
}

