# Implementar multi-tenant

> **Tipo:** Guia de implementação · **Módulo:** Conexão, bancos e pacotes · **Público:** TI e quem altera o i-Educar  
> **Índice:** [Documentação](../README.md) · Conceito: [Multi-tenant](MULTI-TENANT.md)

O mesmo código atende vários municípios. O host escolhe a conexão. O nome do banco fica só no ambiente, fora do git.

---

## O que versionar

`config/tenants.php` e `config/database.php` sobem com o exemplo comentado. O catálogo e as conexões deste servidor ficam em arquivos locais, ignorados pelo git:

- `config/tenants.local.php` — cidades, UF, IBGE, host e em quais conexões cada pacote existe
- `config/database.tenants.php` — cópia de `config/database.tenants.php.example`, com uma conexão por município

Exemplo de catálogo (ajuste no arquivo local, não neste guia):

```php
return [
    'catalog' => [
        [
            'connection' => 'sao_jose',
            'city' => 'São José',
            'uf' => 'SC',
            'ibge' => '0000000',
            'host' => 'sao-jose-sc.exemplo.gov.br',
        ],
    ],
    'packages' => [
        'portabilis/i-educar-transport-package' => [
            'sao_jose',
        ],
    ],
];
```

A conexão correspondente lê o banco de uma variável de ambiente, por exemplo `SAO_JOSE_DATABASE`. Não grave o nome real do database no repositório.

`http://localhost` e `http://127.0.0.1` não abrem município. Mostram a página de entrada com as cidades do catálogo local. Qualquer caminho nesse host continua nessa página.

---

## Como o host vira conexão

`ConnectTenantDatabase` lê o host:

1. `localhost` e `127.0.0.1` estão em `landing_hosts`. A resposta é a página de cidades, sem trocar a conexão e sem carregar `LoadSettings`.
2. Se o host está em `config/tenants.php` → `hosts`, usa essa conexão. A lista é montada a partir do catálogo local.
3. Com `APP_DEFAULT_HOST=localhost`, host fora do catálogo não abre município.
4. Com outro `APP_DEFAULT_HOST`, tira os hífens e o sufixo do domínio. `sao-jose.exemplo.gov.br` vira a conexão `saojose`.
5. A conexão precisa existir em `config/database.tenants.php`. Com multi-tenant ligado e host desconhecido, a resposta é 404.

`LoadSettings` grava `legacy.app.database.dbname` com o campo `database` da conexão ativa. Esse valor é o nome do banco daquela conexão, não o nome da conexão.

---

## Migrar e semear um município

O `--database` do Artisan é o **nome da conexão** (`sao_jose`), não o nome do database.

As migrations de dados iniciais chamam `db:seed` sem `--database`. Esse seed usa a conexão padrão do processo. Se o comando estiver só com `--database=sao_jose` e o `.env` continuar em outra conexão, o schema nasce em um banco e os cadastros iniciais vão para o outro.

Os dois precisam apontar para a mesma conexão:

```bash
docker compose exec -e DB_CONNECTION=sao_jose php php artisan migrate --database=sao_jose --force
docker compose exec -e DB_CONNECTION=sao_jose php php artisan db:seed --database=sao_jose --force
```

`db:seed` sem `--class` carrega países, estados e os cadastros padrão de `DatabaseSeeder`. O `DemoSeeder` gera escolas, turmas e alunos fictícios. Não rode o `DemoSeeder` em produção.

`php artisan migrate` sem esses dois ajustes altera só a conexão do `.env`.

Pacote instalado no Composer entra no `migrate` de todo município, salvo quando o provider deixa de chamar `loadMigrationsFrom` para aquela conexão.

---

## Pacote instalado e disponível só em alguns municípios

O código do pacote continua no repositório. Quem decide se ele existe naquele pedido é `config/tenants.local.php`:

```php
'packages' => [
    'portabilis/i-educar-transport-package' => [
        'sao_jose',
    ],
],
```

O `composer require` e o plug-and-play instalam o código uma vez, para todos os hosts. A lista acima decide em quais conexões o pacote existe. `App\Support\Tenancy\PackageTenant` compara a conexão atual com essa lista. Conexão fora da lista: o pacote está no disco e não atende aquele município.

No provider, o `if` das migrations fica no `boot()`, lendo `--database` (ou `DB_CONNECTION` se o parâmetro não veio). O `if` das telas fica dentro do resolver, que corre no pedido, depois do middleware. Um `if` solto no `boot()` sobre `legacy.app.database.dbname` ainda vê o banco do `.env` e vale para todos os hosts daquele processo.

```php
use App\Support\Tenancy\PackageTenant;

public const string PACKAGE = 'portabilis/i-educar-transport-package';

public function boot()
{
    if ($this->app->runningInConsole() && PackageTenant::allows(self::PACKAGE)) {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    LegacyController::resolver(function ($uri) {
        if (!PackageTenant::allows(self::PACKAGE)) {
            return null;
        }

        return in_array($uri, static::intranet(), true)
            ? __DIR__ . '/../../ieducar/' . $uri
            : null;
    });
}
```

Tirar a conexão da lista não apaga tabela de um banco que já rodou a migration. Nesse caso, remova menu, tabela e a linha em `migrations` só naquele database.

---

## Incluir outro município

1. Criar o database vazio e um papel com `CONNECT` só nele. Guarde o nome do banco numa variável de ambiente, não no git.
2. Acrescentar a conexão em `config/database.tenants.php`, no formato do exemplo comentado, com `database` apontando para essa variável.
3. Inclua a cidade em `config/tenants.local.php` → `catalog`, com `city`, `uf`, `ibge`, `host` e `connection`. O host entra na página de entrada. Não use `localhost` como endereço de município.
4. Se algum pacote não for desse município, omita a conexão em `packages` antes do migrate.
5. Migrar e semear com `DB_CONNECTION` e `--database` iguais ao nome da conexão. Ajuste `pmieducar.instituicao` depois do seed. `DemoSeeder` só em laboratório.

---

## Backup e teste

Cada database tem o seu dump. Restaurar um município não mexe no banco do outro. O ensaio de restore usa outro database e outro host, nunca a conexão de produção. O roteiro de RPO, RTO e anonimização está em [Multi-tenant](MULTI-TENANT.md).

---

## Ver também

- [Multi-tenant](MULTI-TENANT.md)
- [Comandos em produção](COMANDOS-PRODUCAO.md)
- [Documentação](../README.md)
