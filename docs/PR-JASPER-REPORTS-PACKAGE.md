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

## Nota

O aviso *Package cossou/jasperphp is abandoned* desaparece quando nenhum `composer.json` da árvore exige `cossou/jasperphp`.
