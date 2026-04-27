# PR no GitHub: `i-educar-reports-package` (Jasper)

O núcleo do i-Educar passou a usar [`geekcom/phpjasper`](https://packagist.org/packages/geekcom/phpjasper) em vez do pacote abandonado `cossou/jasperphp`. O módulo de relatórios **não precisa** declarar o Jasper no próprio `composer.json`: a dependência fica no `composer.json` da raiz do i-Educar.

## Repositório a alterar

- <https://github.com/portabilis/i-educar-reports-package> (ou o teu fork, depois *Pull Request* para `portabilis` se aplicável)

## Ficheiro

- `composer.json` (raiz do pacote)

## Alteração

Remover a entrada `cossou/jasperphp` do bloco `require` e declarar apenas o PHP alinhado ao i-Educar (evita `require` vazio em validações antigas do Composer). O ficheiro pode ficar assim:

```json
{
    "name": "portabilis/i-educar-reports-package",
    "authors": [
        {
            "name": "Portábilis",
            "email": "contato@portabilis.com.br",
            "homepage": "https://portabilis.com.br"
        }
    ],
    "require": {
        "php": ">=8.3"
    },
    "autoload": {
        "classmap": [
            "ieducar"
        ],
        "psr-4": {
            "iEducar\\Community\\Reports\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "iEducar\\Community\\Reports\\Providers\\ReportsServiceProvider"
            ]
        }
    }
}
```

## Ordem sugerida

1. Merge ou branch no **i-Educar** com `geekcom/phpjasper` e `ReportFactoryPHPJasper` atualizado (este repositório).
2. PR no **i-educar-reports-package** com o `composer.json` acima.
3. `composer plug-and-play` (ou `composer update`) na instalação final.

## Erro: *There are no commands defined in the "community:reports" namespace*

Isso ocorre quando o Laravel **ainda não carrega** o `ReportsServiceProvider`, ou seja, o pacote `portabilis/i-educar-reports-package` **não está instalado** no projecto (não existe em `vendor/` com autoload activo).

**O que fazer:** na raiz do i-Educar, com o código do pacote disponível (repositório em `packages/portabilis/i-educar-reports-package` com plug-and-play, ou `repositories` + `require` no `composer.json`), executar:

```bash
composer update portabilis/i-educar-reports-package
# ou, com plug-and-play:
composer plug-and-play

php artisan package:discover --ansi
php artisan list community
```

Deves ver `community:reports:install`, `community:reports:compile` e `community:reports:link`. Só depois: `php artisan community:reports:install`.

O núcleo do i-Educar precisa de ter **`geekcom/phpjasper`** instalado (`composer install`), pois os comandos passam a referenciar `vendor/geekcom/phpjasper/bin/jasperstarter/bin/jasperstarter`.

## Nota

O aviso *Package cossou/jasperphp is abandoned* desaparece quando nenhum `composer.json` da árvore exige `cossou/jasperphp`.
