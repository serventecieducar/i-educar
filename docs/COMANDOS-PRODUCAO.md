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
- Diagnóstico (exec, pastas, symlink do módulo, Java no PATH):

```bash
php artisan reports:jasper-diagnose
```

---

## 9. Vários domínios no mesmo servidor (um funciona, outros não)

Cenários frequentes:

1. **Multi-tenant (`APP_MULTI_TENANT`): configurações por base de dados** — O middleware `LoadSettings` faz `Config::set()` com linhas da tabela **`settings`** da **base do tenant** resolvida pelo subdomínio (`ConnectTenantDatabase`). Chaves como **`legacy.report.default_factory`**, **`legacy.report.source_path`** e outras `legacy.report.*` **substituem** o que está no `config/legacy.php` e no `.env` **só para esse tenant**. Um subdomínio pode ter *Factory principal* / caminhos correctos e outro ter valores antigos, vazios ou errados. O comando `php artisan reports:jasper-diagnose` usa sobretudo o **`DB_CONNECTION` do `.env`**, não o tenant do browser — pode mostrar “tudo OK” e mesmo assim um cliente falhar na web. **Comparar entre bases:**  
   `SELECT key, value FROM settings WHERE key LIKE 'legacy.report.%' ORDER BY key;`  
   (executar em **cada** base PostgreSQL ligada a um subdomínio que falhe vs. um que funcione).

2. **Um código, vários hostnames, mesmo `php.ini` / mesmo pool** — Se o ponto 1 estiver alinhado e ainda houver diferença, verifica: **bloco `server` / `VirtualHost` diferente** (outro `root` ou symlink), **cache HTTP** (CDN/proxy a servir resposta antiga), **DNS** de um hostname a apontar para outro IP, **ModSecurity / WAF** a bloquear só alguns hosts.

3. **Pool PHP-FPM diferente por `server_name`** (quando não é um único pool) — `disable_functions` / `open_basedir` distintos. Alinha **todos** os pools que servem o i-Educar.

4. **Várias pastas no disco** (`/var/www/clienteA`, `/var/www/clienteB`) — cada uma precisa de **`php composer.phar install`** actualizado.

5. **`php artisan config:cache`** — valores de `env()` congelados; evitar variar `REPORTS_*` só por vhost sem rebuild coerente do cache.

6. **`JASPER_BIN_DIR`** no `.env` — caminho absoluto partilhado ao binário se o layout de pastas for atípico.

7. **OPcache** — reload de **todos** os pools PHP-FPM relevantes após deploy.

---

## 10. Jasper e `exec()`: original Portabilis vs alterações (fork Serventec)

### O que o **upstream** Portabilis faz (`portabilis/i-educar`, branch `2.11`)

- `ReportFactoryPHPJasper` usa **`JasperPHP\JasperPHP`** (pacote Composer **`cossou/jasperphp`**, hoje marcado *abandoned*).
- Instancia `new JasperPHP` **sem** caminho absoluto ao binário; o vendor chama **`exec()`** no ficheiro `JasperPHP.php` do pacote para correr o **JasperStarter** (Java).
- **Não existe** verificação prévia `function_exists('exec')`; se `exec` estiver em `disable_functions`, o PHP falha **dentro** do vendor (mensagem pouco clara, por exemplo referência a `JasperPHP\exec()` consoante versão do PHP e do pacote).

### O que mudou neste fork (resumo)

| Tópico | Portabilis (original) | Fork (Serventec / alterações descritas) |
|--------|----------------------|------------------------------------------|
| Biblioteca | `cossou/jasperphp` | **`geekcom/phpjasper`** (`PHPJasper\PHPJasper`) |
| Caminho ao binário | Relativo ao pacote no vendor | **`base_path()`** ou **`JASPER_BIN_DIR`** (`config/legacy.php` → `legacy.report.jasper_bin_dir`) |
| Antes de gerar PDF | Nada | **`assertJasperRuntime()`** — verifica `function_exists('exec')`, pasta e `+x` do `jasperstarter` |
| Mensagem “exec indisponível” | Não existe no core | **Nova**, explícita, a orientar FPM / `php artisan reports:jasper-diagnose` |

**Conclusão:** o **requisito** de o PHP poder executar processos externos **já existia** no pacote original; **não** foi introduzido pelo fork. O fork **torna o erro visível cedo** e **corrige** o caminho ao JasperStarter com `geekcom/phpjasper`. **Remover** `assertJasperRuntime()` não “liberta” o `exec`; apenas voltaria a falhar mais tarde, com mensagem pior.

### Abordagens em **hosts** onde o relatório falha com `exec`

1. **Habilitar `exec` no PHP que serve o site (FPM)**  
   - Em `/etc/php/*/fpm/pool.d/*.conf` (ou `php.ini` do FPM), remover `exec` de `php_admin_value[disable_functions]` / `disable_functions`.  
   - `sudo systemctl reload php*-fpm`.  
   - Confirmar **no mesmo pool** do vhost (script `phpinfo()` ou ficheiro temporário com `function_exists('exec')` via HTTP).

2. **Manter política restritiva** — usar **factory remota** (`Portabilis_Report_ReportsRenderServerFactory` + `REPORTS_URL` / token em `config/legacy.php`), para o Jasper correr **fora** deste PHP (outro serviço ou máquina onde `exec` seja permitido). É o desenho previsto quando não se quer Jasper na app web.

3. **Multi-tenant** — alinhar `settings` por base (`legacy.report.*`); o diagnóstico Artisan reflecte sobretudo `DB_CONNECTION` do `.env`, não cada tenant (ver §9).

4. **Não substituir `exec` por “desligar a verificação”** no código: isso só mascara a causa; o JasperStarter continua a precisar de executar um binário.

---

## 11. `php.ini` / PHP-FPM: permitir `exec()` para o Jasper

O **PHP CLI** (`php -i`) e o **PHP-FPM** (pedidos HTTP) usam ficheiros **diferentes**. O i-Educar nos relatórios usa o **FPM**. Ajusta a **versão** (`8.2`, `8.3`, …) e caminhos ao teu servidor (Debian/Ubuntu como referência).

### 11.1 Onde editar

| Contexto | Ficheiro típico |
|------------|-----------------|
| PHP-FPM global | `/etc/php/8.3/fpm/php.ini` |
| Pool de um site (recomendado) | `/etc/php/8.3/fpm/pool.d/www.conf` ou `pool.d/ieducar.conf` |
| Apache `mod_php` (raro hoje) | `/etc/php/8.3/apache2/php.ini` |

Depois de gravar: `sudo systemctl reload php8.3-fpm`.

### 11.2 Directiva principal: `disable_functions`

O Jasper (`geekcom/phpjasper`) precisa de **`exec()`**. Não pode constar em `disable_functions`.

**Exemplo — comentar a linha global** (em `fpm/php.ini`):

```ini
; Lista vazia = nenhuma função de sistema desactivada (máximo permissivo; só em ambientes controlados)
disable_functions =

; OU, se quiseres manter outras restrições, define uma lista SEM exec, shell_exec, proc_open, passthru, system
; (o Jasper usa sobretudo exec; outras partes do Laravel/podem usar proc_open)
disable_functions = pcntl_alarm,pcntl_fork,pcntl_waitpid,pcntl_wait,pcntl_wifexited,pcntl_wifstopped,pcntl_wifsignaled,pcntl_wexitstatus,pcntl_wtermsig,pcntl_wstopsig,pcntl_signal,pcntl_signal_dispatch,pcntl_get_last_error,pcntl_strerror,pcntl_sigprocmask,pcntl_sigwaitinfo,pcntl_sigtimedwait,pcntl_exec,pcntl_getpriority,pcntl_setpriority
```

**Importante:** se existir **outra** linha `disable_functions` mais abaixo ou no **pool**, a mais específica pode **sobrepor**. Grep ajuda:

```bash
sudo grep -R "disable_functions" /etc/php/8.3/fpm/
```

### 11.3 Pool FPM (exemplo de bloco)

Ficheiro: `/etc/php/8.3/fpm/pool.d/ieducar.conf` (nome à escolha). Ajusta `user`, `group`, `listen` e caminhos.

```ini
[ieducar]
user = www-data
group = www-data
listen = /run/php/php8.3-fpm-ieducar.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35

; Garantir que exec (e o que o Laravel/Jasper precisarem) NÃO estão na lista
php_admin_value[disable_functions] = pcntl_alarm,pcntl_fork,pcntl_waitpid
; Se esta linha existir com "exec" na lista, remove "exec", "shell_exec", "proc_open", "passthru", "system" conforme necessidade.

; Opcional: PHP consegue invocar java no PATH do worker
env[PATH] = /usr/local/bin:/usr/bin:/bin
```

Se o pool **não** definir `php_admin_value[disable_functions]`, herda o `php.ini` do FPM.

### 11.4 `open_basedir` (só se estiver activo)

Se usares `open_basedir`, tem de incluir **pelo menos**:

- raiz do projecto i-Educar (onde está `vendor/` e `ieducar/`);
- `/tmp` (ficheiros temporários de relatório);
- pastas do Java (`which java` / `readlink -f $(which java)`).

Exemplo ilustrativo (**uma única linha**, separador `:` no Linux):

```ini
php_admin_value[open_basedir] = /home/serventec/central-ba.serventecassessoria.com.br/:/tmp/:/usr/lib/jvm/
```

Erros típicos: esquecer o `vendor/geekcom` ou o symlink `ieducar/modules/Reports` dentro da raiz — tudo tem de cair **dentro** dos prefixos permitidos.

### 11.5 Outras directivas úteis (opcional)

```ini
; Relatórios grandes / Jasper
memory_limit = 512M
max_execution_time = 120

; Log de erros do pool (caminho à escolha)
php_admin_value[error_log] = /var/log/php8.3-fpm-ieducar.log
```

### 11.6 Verificação

1. `php -i | grep disable_functions` — reflecte **CLI**, não FPM.  
2. `phpinfo()` servido **pelo mesmo vhost** / pool, ou ficheiro temporário com `<?php var_export(function_exists('exec'));`.  
3. `php artisan reports:jasper-diagnose` no servidor (mesmo utilizador do pool, se possível: `sudo -u www-data …`).

---

## Ordem mínima sugerida (deploy típico)

1. `git pull` no core (e no pacote local de relatórios em `packages/serventec/`, se existir).  
2. `php composer.phar install --no-dev --optimize-autoloader` (e `php composer.phar plug-and-play` se aplicável).  
3. `php artisan package:discover --ansi`  
4. Migrações, se necessário: `php artisan migrate --force`  
5. `php artisan community:reports:install` (se usarem o pacote de relatórios)  
6. `php artisan reports:jasper-diagnose` (deve terminar com código 0 em cada instalação / pool relevante)  
7. `php artisan optimize:clear` ou recriar `config:cache` / `route:cache` conforme política do ambiente  
8. Reload do **PHP-FPM** (todos os pools do i-Educar) se OPcache “segurar” código antigo  
9. `php artisan queue:restart` (e Horizon, se houver)
