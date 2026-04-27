# Comandos úteis em produção (i-Educar)

Referência rápida para atualizar código, dependências, caches e o módulo de relatórios. Ajusta caminhos e utilizador (`www-data`, `serventec`, etc.) ao teu ambiente.

**Composer:** os exemplos usam `php composer.phar` na raiz do i-Educar (ficheiro `composer.phar` junto ao `composer.json`). Se usares o binário global `composer`, substitui o prefixo.

## Pré-requisitos no servidor

- **PHP** ≥ 8.3, extensões exigidas pelo `composer.json`; **Composer** disponível como `composer.phar` invocado via `php composer.phar`.
- **PostgreSQL** (ou o SGBD configurado) acessível.
- **Relatórios Jasper:** Java instalado; em `php.ini` / pool **FPM** não bloquear `exec` (e normalmente `shell_exec`) se usares `Portabilis_Report_ReportFactoryPHPJasper`.
- **Fila / Horizon / Pulse** (se activos): processo supervisor ou systemd conforme a vossa instalação.

---

## 1. Actualizar o core (este repositório)

Na raiz do projecto (onde está o `composer.json` do i-Educar):

```bash
cd /caminho/para/i-educar
git fetch origin
git checkout 2.11
git pull origin 2.11
```

Se existir **tag** com o mesmo nome que a branch, usa a referência explícita:

```bash
git pull origin refs/heads/2.11
```

---

## 2. Dependências Composer

```bash
php composer.phar install --no-dev --optimize-autoloader
```

Com **Composer Plug-and-Play** (pacotes em `packages/`):

```bash
php composer.phar plug-and-play
# ou, se o vosso fluxo usar:
# php composer.phar update
```

Registar pacotes Laravel após mudanças em `vendor/`:

```bash
php artisan package:discover --ansi
```

---

## 3. Pacote de relatórios (`i-educar-reports-package`)

Se o pacote estiver como **path** em `packages/serventec/i-educar-reports-package` (estrutura Serventec):

```bash
cd packages/serventec/i-educar-reports-package
git fetch origin
git checkout 2.11
git pull origin 2.11
cd ../../..
php composer.phar update serventec/i-educar-reports-package --no-dev --optimize-autoloader
php artisan package:discover --ansi
```

O nome após `update` deve ser **exactamente** o do `require` no `composer.json` da raiz (se ainda for `portabilis/i-educar-reports-package`, usa esse nome no comando).

Confirmar que os comandos existem:

```bash
php artisan list community
```

Instalação / ligação de assets e permissões (conforme README do pacote):

```bash
php artisan community:reports:install
```

Opções úteis: `--no-compile`, `--no-migrate` se precisares de controlar passos.

---

## 4. Laravel: caches e optimização

Após deploy ou quando algo parecer desactualizado:

```bash
php artisan optimize:clear
```

Se o deploy **recria** caches (recomendado em muitos ambientes):

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

(Se não usarem `route:cache` / `view:cache` em produção, omitem esses passos.)

---

## 5. PHP-FPM / OPcache

Em muitos servidores o **OPcache** não volta a ler ficheiros alterados em `vendor/` até haver **reload** do pool PHP-FPM (ou restart do container PHP). Se após `php composer.phar install` o comportamento continuar o antigo, recarrega o FPM:

```bash
# Exemplo Debian/Ubuntu
sudo systemctl reload php8.3-fpm
```

---

## 6. Permissões (Laravel)

Se o script do projecto o definir:

```bash
php composer.phar run-script set-permissions
# ou manualmente storage e bootstrap/cache com escrita para o utilizador do servidor web
```

---

## 7. Fila e Horizon (se aplicável)

```bash
php artisan queue:restart
# Horizon, se estiver em uso:
php artisan horizon:terminate
```

---

## 8. Relacionado com Jasper / relatórios

- Núcleo: dependência **`geekcom/phpjasper`** na raiz do i-Educar; binário usado pelos comandos do pacote: `vendor/geekcom/phpjasper/bin/jasperstarter/bin/jasperstarter`.
- Erros comuns e Artisan `community:reports:*`: [PR-JASPER-REPORTS-PACKAGE.md](PR-JASPER-REPORTS-PACKAGE.md).

---

## Ordem mínima sugerida (deploy típico)

1. `git pull` no core (e no pacote local de relatórios em `packages/serventec/`, se existir).  
2. `php composer.phar install --no-dev --optimize-autoloader` (e `php composer.phar plug-and-play` se aplicável).  
3. `php artisan package:discover --ansi`  
4. Migrações, se necessário: `php artisan migrate --force`  
5. `php artisan community:reports:install` (se usarem o pacote de relatórios)  
6. `php artisan optimize:clear` ou recriar `config:cache` / `route:cache` conforme política do ambiente  
7. Reload do **PHP-FPM** se OPcache “segurar” código antigo  
8. `php artisan queue:restart` (e Horizon, se houver)
