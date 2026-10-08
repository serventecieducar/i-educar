<?php

/*
| Catálogo real deste ambiente fica em config/tenants.local.php, fora do git.
| Exemplo para produção (descomente e ajuste no arquivo local):
|
| return [
|     'catalog' => [
|         [
|             'connection' => 'sao_jose',
|             'city' => 'São José',
|             'uf' => 'SC',
|             'ibge' => '0000000',
|             'host' => 'sao-jose-sc.exemplo.gov.br',
|         ],
|     ],
|     'packages' => [
|         'portabilis/i-educar-transport-package' => [
|             'sao_jose',
|         ],
|     ],
| ];
*/

$catalog = [];
$packages = [];

$local = __DIR__ . '/tenants.local.php';

if (is_file($local)) {
    $extra = require $local;
    $catalog = $extra['catalog'] ?? [];
    $packages = $extra['packages'] ?? [];
}

$hosts = [];

foreach ($catalog as $tenant) {
    if (!empty($tenant['host']) && !empty($tenant['connection'])) {
        $hosts[$tenant['host']] = $tenant['connection'];
    }
}

return [

    /*
    | Estes hosts não escolhem banco. Mostram a página com as cidades.
    */
    'landing_hosts' => [
        'localhost',
        '127.0.0.1',
    ],

    'catalog' => $catalog,

    /*
    | Host sem porta => nome da conexão em database.connections.
    */
    'hosts' => $hosts,

    /*
    | Pacote => conexões em que ele existe. Fora da lista, rotas legadas,
    | menus resolvidos pelo pacote e migrations dele não carregam.
    */
    'packages' => $packages,

];
