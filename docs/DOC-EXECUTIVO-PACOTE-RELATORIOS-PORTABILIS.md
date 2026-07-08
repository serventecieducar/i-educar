# DOC Executivo — Pacote de Relatórios (Portabilis) no i-Educar

## 1) Objetivo do documento

Este documento descreve, em nível **executivo e técnico**, como o i-Educar integra e opera o **pacote de relatórios da Portabilis** (baseado em templates Jasper `.jrxml`), detalhando:

- **Estrutura** (core do i-Educar vs. pacote externo de relatórios).
- **Fluxo de emissão** (UI → controller → fábrica → runtime → PDF).
- **Configurações** e pontos de acoplamento.
- **Menus, permissões e filtros** existentes no produto (com foco no que está neste repositório).
- **Inventário dos relatórios/consultas encontrados** neste workspace e seus parâmetros.
- **Implicações e recomendações** para projetar um **novo package sem Java**.

> **Nota importante de escopo (deste workspace)**  
> Este repositório contém o **core** de execução (fábricas/controle/config) e algumas **consultas/relatórios** modernos (movimento geral/mensal).  
> Porém, **não** contém os templates `.jrxml` (nem a pasta `ReportSources`) e também **não** contém classes específicas de cada relatório Jasper (ex.: classes que definem `templateName()` e `requiredArgs()`), o que é consistente com essas peças estarem no **pacote externo** (`i-educar-reports-package`).

---

## 2) Visão geral (decisão e trade-offs)

### 2.1 Como é hoje (Jasper via Java)

- O i-Educar usa uma fábrica de relatórios que chama o **JasperStarter** (binário) via PHP (`exec()`), com o wrapper `geekcom/phpjasper`.
- Os relatórios são definidos por templates Jasper:
  - **Fonte**: `.jrxml` (design).
  - **Compilado**: `.jasper` (gerado sob demanda).
- O resultado final é um **PDF** retornado ao navegador (inline) ou à API (base64).

### 2.2 Por que isso impacta o novo package (sem Java)

Para remover Java/JasperStarter, o novo pacote precisará:

- Substituir o “motor” de renderização (hoje Jasper) por um motor **100% PHP** (ou HTML→PDF), mantendo:
  - compatibilidade de filtros/menus,
  - mesma semântica de parâmetros (ex.: `SUBREPORT_DIR`, `logo`, `ano`, `instituicao`, etc.),
  - outputs em PDF (e possivelmente outros formatos),
  - sub-relatórios e layouts padronizados (capa/cabeçalho/rodapé).

---

## 3) Componentes e estrutura (core x pacote)

### 3.1 Core de relatórios no i-Educar (neste repositório)

#### a) Modelo base de relatório (contrato “Portabilis ReportCore”)

Arquivo: `ieducar/lib/Portabilis/Report/ReportCore.php`

- Define:
  - `requiredArgs` + validação
  - `args` (parâmetros passados ao motor)
  - `templateName()` (nome do template Jasper, sem extensão)
  - `useJson() / useHtml()` (variações de datasource/render)
  - `getJsonData()` / `getJsonQuery()` (quando datasource é JSON)
  - pipeline de `modifiers` para pós-processamento de dados (casos JSON)

#### b) Fábrica (engine) JasperStarter via PHP

Arquivo: `ieducar/lib/Portabilis/Report/ReportFactoryPHPJasper.php`

Responsabilidades principais:

- Montar conexão de DB (Postgres) para o JasperStarter.
- Resolver paths:
  - **ReportSources** (`legacy.report.source_path`, default `ieducar/modules/Reports/ReportSources/`)
  - **binário jasperstarter** (default `vendor/geekcom/phpjasper/bin/jasperstarter/bin`)
- Compilar `.jrxml` → `.jasper` quando necessário.
- Executar `process()` (gera PDF).
- (Opcional) rodar com datasource JSON, escrevendo JSON temporário e passando `db_connection` com driver `json`.

Pontos críticos:

- **depende de `exec()`** habilitado no PHP do ambiente (FPM pode divergir do CLI).
- precisa de permissões de escrita em `ReportSources` para arquivos temporários/compilação.

#### c) Controller base para telas legadas de relatório (UI/submit → PDF)

Arquivo: `ieducar/lib/Portabilis/Controller/ReportCoreController.php`

Características:

- Renderiza formulário na primeira abertura (`form()`).
- No submit:
  - instancia o relatório (`report()` — esperado ser implementado na subclasse),
  - aplica `beforeValidation()`,
  - injeta parâmetros padrão:
    - `database` (dbname)
    - `SUBREPORT_DIR` (source_path)
    - `data_emissao` (flag do header)
  - valida required args e renderiza PDF inline.

Tratamento de erro:

- Pode enviar exceções para tracking quando habilitado.
- Exibe detalhes apenas se `legacy.report.show_error_details=true` ou usuário admin (nível 1).

#### d) Diagnóstico operacional

Arquivo: `app/Console/Commands/ReportsJasperDiagnoseCommand.php`

Comando: `php artisan reports:jasper-diagnose`

Verifica:

- `exec()` disponível
- pasta e permissão do `jasperstarter`
- existência/gravação do `ReportSources`
- coerência entre instalações e possíveis sobreposições em `settings` (multi-tenant)

#### e) Configuração (env → `config/legacy.php`)

Arquivo: `config/legacy.php` (seção `report`)

Chaves relevantes:

- `legacy.report.default_factory` (default `Portabilis_Report_ReportFactoryPHPJasper`)
- `legacy.report.source_path` (default `.../ieducar/modules/Reports/ReportSources/`)
- `legacy.report.jasper_bin_dir` (pasta do binário, opcional)
- `legacy.report.logo_file_name` (logo padrão nos relatórios Jasper)
- `legacy.report.skip_sources_writable_check`
- `legacy.report.show_error_details`

Envvars usadas:

- `REPORTS_FACTORY`
- `REPORTS_SOURCE_PATH`
- `JASPER_BIN_DIR`
- `REPORTS_LOGO`
- `REPORTS_SKIP_SOURCES_WRITABLE_CHECK`
- `REPORTS_DEBUG`

---

### 3.2 Pacote externo de relatórios (Portabilis `i-educar-reports-package`)

Este workspace referencia o pacote de forma indireta (docs), mas **não** traz seu código.

Evidências/expectativas (a partir do core e da documentação):

- O pacote:
  - fornece os templates `.jrxml` em uma estrutura que é **linkada** para `ieducar/modules/Reports` (ou especificamente para `ReportSources`).
  - registra um `ReportsServiceProvider` e comandos `community:reports:*` (instalar/compilar/link).
- O core espera encontrar fontes em `legacy.report.source_path`.

Documento relacionado no repositório: `docs/PR-JASPER-REPORTS-PACKAGE.md`.

---

## 4) Menus, permissões e navegação (o que está neste repo)

### 4.0 Menus criados/registrados para “Reports” (sementes de menu)

Fonte: `database/sqls/inserts/public.menus.sql`

Os itens abaixo são os **menus/submenus** que aparecem no seed e que estão diretamente ligados ao conjunto de funcionalidades de **consulta/configuração** levantadas neste documento (e/ou são nós “Relatórios” na árvore).  
Quando um item é criado **dentro de um menu já existente da instalação padrão**, isso é indicado explicitamente.

- **Escola → Relatórios (nó/pasta)**
  - **menu_id**: `18`
  - **label**: `Relatórios`
  - **url**: *(sem URL — nó agrupador)*
  - **processo/permissão**: `55` (menu pai: `Escola`)

- **Servidores → Relatórios (nó/pasta)**
  - **menu_id**: `33`
  - **label**: `Relatórios`
  - **url**: *(sem URL — nó agrupador)*
  - **processo/permissão**: `71` (menu pai: `Servidores`)
  - **submenus no seed padrão deste repo**: **nenhum** (não há itens com `parent_id = 33` no `public.menus.sql`)

#### 4.0.1 Itens do plugin criados dentro de menus padrão (hierarquia completa)

##### A) Configuração de “Movimento geral”

**Este item é criado dentro do menu padrão** `Configurações`:

- **Configurações (padrão)**
  - **menu_id**: `8` | **url**: `/intranet/educar_configuracoes_index.php` | **processo/permissão**: `25`
  - **Configurações (padrão, nó)**
    - **menu_id**: `12` | *(sem URL)* | **processo/permissão**: `999909`
    - **Configuração movimento geral (plugin/report)**
      - **menu_id**: `68`
      - **url**: `/module/Configuracao/ConfiguracaoMovimentoGeral`
      - **processo/permissão**: `9998867`

##### B) Consultas de “Movimento” (geral/mensal)

**Estes itens são criados dentro do menu padrão** `Escola → Ferramentas → Consultas`:

- **Escola (padrão)**
  - **menu_id**: `3` | **url**: `/intranet/educar_index.php` | **processo/permissão**: `55`
  - **Ferramentas (padrão, nó)**
    - **menu_id**: `20` | *(sem URL)* | **processo/permissão**: `999926`
    - **Consultas (padrão, nó)**
      - **menu_id**: `81` | *(sem URL)* | **processo/permissão**: `9998890`
      - **Consulta de movimento geral (plugin/report)**
        - **menu_id**: `127`
        - **url**: `/intranet/educar_consulta_movimento_geral.php`
        - **processo/permissão**: `9998900`
      - **Consulta de movimento mensal (plugin/report)**
        - **menu_id**: `128`
        - **url**: `/intranet/educar_consulta_movimento_mensal.php`
        - **processo/permissão**: `9998910`

#### 4.0.2 Menus relacionados, mas fora do escopo do plugin report

Os itens abaixo **não** pertencem ao plugin report, porém aparecem próximos na navegação e podem gerar confusão:

- **Escola → Documentos (padrão, nó)**
  - **menu_id**: `19` | *(sem URL)* | **processo/permissão**: `21127`
  - **Submenu**: **Documentação padrão** (padrão)
    - **menu_id**: `65`
    - **url**: `/intranet/DocumentacaoPadrao.php`
    - **processo/permissão**: `999861` (e também `999865` no seed)

> **Observação**: como o pacote externo `i-educar-reports-package` não está presente neste workspace, não foi possível listar aqui menus que sejam criados exclusivamente por ele (ex.: relatórios Jasper específicos). Esta seção reflete o que está versionado no seed de menus deste repositório.

### 4.1 Menus identificados (seed SQL)

Arquivo: `database/sqls/inserts/public.menus.sql`

Itens relevantes ao tema:

- **Configurações**
  - **Configuração movimento geral**: `/module/Configuracao/ConfiguracaoMovimentoGeral` (processo `9998867`)
- **Ferramentas → Consultas**
  - **Consulta de movimento geral**: `/intranet/educar_consulta_movimento_geral.php` (processo `9998900`)
  - **Consulta de movimento mensal**: `/intranet/educar_consulta_movimento_mensal.php` (processo `9998910`)

### 4.2 Consulta de movimento geral (tela, filtros e resultados)

#### a) Tela de filtros (form)

Arquivo: `ieducar/intranet/educar_consulta_movimento_geral.php`

Permissão:

- `PROCESSO_AP = 9998900` (validação via `clsPermissoes->permissao_cadastra`)

Filtros disponíveis:

- **Ano** (dynamic input `ano`)
- **Instituição** (dynamic input `instituicao` — existe no form embora a query use default 1 se omitido)
- **Cursos** (multi-seleção via `multipleSearchCurso`, opcional)
- **Data inicial** e **Data final** (dynamic inputs `dataInicial` / `dataFinal`)

Saída:

- Redireciona para listagem: `educar_consulta_movimento_geral_lst.php` com querystring dos filtros.

#### b) Tela de resultados (listagem)

Arquivo: `ieducar/intranet/educar_consulta_movimento_geral_lst.php`

Fonte de dados:

- `src/Modules/Reports/QueryFactory/MovimentoGeralQueryFactory.php`

Indicadores exibidos (por escola):

- **Matrículas no início (antes de data_inicial)** segmentadas por colunas configuráveis:
  - `ed_inf_int` (Educação infantil integral — turno id 4)
  - `ed_inf_parc` (Educação infantil parcial — turno != 4)
  - `ano_1` … `ano_9` (1º ao 9º ano)
- **Movimentações dentro do período** (`data_inicial`–`data_final`):
  - `admitidos` (sequencial=1; enturmação/registro dentro do período)
  - `transf` (transferidos)
  - `aband` (deixou de frequentar/abandono)
  - `rem` (remanejados — enturmação inativa anterior a uma posterior, não “mesma turma”)
  - `recla` (reclassificados)
  - `obito` (óbito)
- Metadados:
  - `ciclo` (“**” se escola com regime por ciclo)
  - `aee` (“*” se escola com AEE)
  - `localizacao` (Urbana/Rural)

Drill-down (modal de alunos):

- Cada célula numérica vira link (`.mostra-consulta`) que chama API:
  - endpoint: `/module/Api/ConsultaMovimentoGeral`
  - parâmetros: `tipo` (ex.: `admitidos`, `aband`, `ano_1`, etc.), `escola`, `ano`, `data_inicial`, `data_final`, `curso` (opcional)
- Script do modal: `ieducar/intranet/scripts/consulta_movimentos.js`

#### c) Configuração das colunas (mapeamento de séries)

Tela:

- `/module/Configuracao/ConfiguracaoMovimentoGeral`
- Arquivo: `ieducar/modules/Configuracao/Views/ConfiguracaoMovimentoGeralController.php`

Função:

- Mapeia quais **séries** entram em cada “coluna” do relatório:
  - `coluna 0` → Educação Infantil (agrupamento)
  - `coluna 1..9` → 1º ao 9º ano

Persistência:

- tabela `modules.config_movimento_geral` (via `ConfiguracaoMovimentoGeralDataMapper`)

Impacto:

- A query do movimento geral lê `modules.config_movimento_geral` para filtrar as séries que compõem cada coluna.

---

### 4.3 Consulta de movimento mensal (tela, filtros e resultados)

#### a) Tela de filtros (form) — movimento mensal

Arquivo: `ieducar/intranet/educar_consulta_movimento_mensal.php`

Permissão:

- `PROCESSO_AP = 9998910`

Filtros disponíveis:

- **Ano**
- **Instituição**
- **Escola**
- **Curso** (opcional)
- **Série** (opcional)
- **Turma** (opcional)
- **Modalidade** (obrigatório):
  - Todas / Regular / AEE / Atividade complementar / EJA
- **Calendários** (quando EJA): UI `view('form.calendar')`, com múltiplos intervalos selecionáveis
- **Data inicial** e **Data final**

Saída:

- Redireciona para `educar_consulta_movimento_mensal_lst.php` com querystring completa.

#### b) Tela de resultados (listagem) — movimento mensal

Arquivo: `ieducar/intranet/educar_consulta_movimento_mensal_lst.php`

Fonte de dados:

- `src/Modules/Reports/QueryFactory/MovimentoMensalQueryFactory.php`

Origem principal:

- view `public.info_enrollment` + joins em `pmieducar.*` e `cadastro.*`

Indicadores exibidos (por série/turma/turno), todos segmentados por **sexo (M/F)** quando aplicável:

- **Matrícula inicial** (M/F/T): aluno ativo, sem dependência, entrou antes do início, e não saiu antes do início.
- **Transf.** (transferências) (M/F): transferido + enturmação transferida + saiu durante o período.
- **Deixou de Freq.** (abandono) (M/F): abandono + enturmação abandono + saiu durante o período.
- **Admitido** (M/F): ativo + sequencial=1 + não é entrada por reclassificação + entrou durante.
- **Óbito** (M/F): falecido + enturmação falecido + saiu durante.
- **Reclassificado**:
  - saiu (M/F): reclassificado + saiu durante
  - entrou (M/F): ativo + entrada_reclassificado + entrou durante
- **Remanejado**:
  - saiu (M/F): ativo + enturmação_remanejado + enturmação inativa + saiu durante + sequencial < maior_sequencial
  - entrou (M/F): ativo + entrou durante + sequencial > 1
- **Matrícula final** (M/F/T): ativo, sem dependência, entrou antes do fim, e não saiu antes do fim.

Drill-down (modal de alunos):

- Cada célula numérica vira link com:
  - endpoint: `/module/Api/ConsultaMovimentoMensal`
  - `tipo` (ex.: `mat_transf_m`, `mat_admit_f`, etc.)
  - parâmetros: `ano`, `instituicao`, `escola`, `curso/serie/turma` (opcionais), `data_inicial`, `data_final`
- Modal: mesmo `consulta_movimentos.js`

---

## 5) Relatórios Jasper via Portabilis (interfaces, parâmetros e acoplamentos)

Como o pacote externo não está presente, aqui está o que o core exige/assume sobre um relatório Jasper típico:

### 5.1 Estrutura de um relatório

Um relatório Jasper, no ecossistema Portabilis/i-Educar, normalmente envolve:

- **Classe PHP** (no pacote) que:
  - define `templateName()` (ex.: `'boletim'`)
  - define `requiredArgs()` (ex.: `ano`, `instituicao`, `escola`, etc.)
  - adiciona `args` (parâmetros do Jasper)
  - opcionalmente define `useJson()` e fornece `getJsonData()`
- **Template Jasper**:
  - `ReportSources/<templateName>.jrxml`
  - sub-relatórios referenciados via `SUBREPORT_DIR`
- **Assets**:
  - logo (`legacy.report.logo_file_name`) resolvida por `ReportFactoryPHPJasper->logoPath()`

### 5.2 Parâmetros “padrão” injetados pelo controller base

Quando a emissão acontece via `Portabilis_Controller_ReportCoreController`:

- `database`: nome do banco (dbname)
- `SUBREPORT_DIR`: `legacy.report.source_path`
- `data_emissao`: flag do cabeçalho (inteiro)

### 5.3 Relatórios Jasper identificados por integração de API

Arquivo: `ieducar/modules/Api/Views/ReportController.php`

Endpoints:

- `get boletim` (aluno)
  - valida: `escola`, `matricula`
  - carrega dados da matrícula via SQL
  - se a classe `BoletimReport` existir, emite PDF e devolve `base64`
- `get boletim-professor` (professor)
  - usa `app(TeacherReportCard::class)` (contrato em `iEducar\Reports\Contracts\TeacherReportCard`)
  - injeta parâmetros (ano, instituição, escola, curso, série, turma, professor, disciplina, etc.)

> Sem o pacote externo, não é possível listar todos os templates `.jrxml` e seus filtros, mas o core e o `ReportController` deixam claro o padrão de integração.

---

## 6) Pontos de atenção operacionais (para decisão)

### 6.1 Dependências e risco operacional atuais

- **Java** no host (JasperStarter).
- **`exec()` habilitado** no PHP-FPM do vhost.
- permissões de escrita e consistência do caminho do `ReportSources`.
- compatibilidade entre instalações (vendor, symlink do pacote, cache config).

### 6.2 Multi-tenant

O diagnóstico (`reports:jasper-diagnose`) alerta que, em multi-tenant, chaves `legacy.report.*` podem vir da tabela `settings` por tenant, alterando:

- factory efetiva,
- source_path efetivo.

---

## 7) Blueprint do novo package (sem Java) — recomendação de arquitetura

### 7.1 Objetivos de compatibilidade

Para reduzir risco e permitir migração incremental, o novo package deve:

- manter a mesma “API” de emissão:
  - classes com `requiredArgs`, `args`, validação
  - controller base reaproveitável (ou adaptador)
- aceitar `SUBREPORT_DIR` (mesmo que internamente não use Jasper), ou oferecer compatibilidade por adapter.
- continuar suportando:
  - **PDF inline** na UI legada
  - **PDF base64** na API (ex.: boletim)

### 7.2 Estratégias sem Java (opções)

- **HTML → PDF**:
  - renderizar HTML com Blade/Twig e converter para PDF (wkhtmltopdf headless, Chromium/Puppeteer, ou lib PHP puro — cada uma com trade-offs operacionais).
- **PDF nativo em PHP**:
  - montar PDFs programaticamente (ex.: TCPDF/FPDF/mPDF), similar ao legado `relatorios` (`ieducar/intranet/include/relatorio.inc.php`), porém com layout mais moderno e manutenção mais simples.
- **Gerador híbrido**:
  - relatórios simples: PDF nativo
  - relatórios complexos: HTML→PDF

### 7.3 Sugestão de desenho de package (alto nível)

- Um `ReportRenderContract` (já existe no core moderno em `src/Reports/Contracts/ReportRenderContract.php`, se desejarem alinhar) com:
  - `render(ReportDefinition $report, array $options): ReportOutput`
- `ReportDefinition`:
  - nome, descrição, parâmetros, validação, permissão, fonte de dados (QueryFactory/SQL/Repository)
- `ReportMenuRegistry`:
  - mapeamento de menus/rotas/permissions para cada relatório
- `ReportDataLayer`:
  - QueryFactories (como já existe em `src/Modules/Reports/QueryFactory/*`)
- `ReportTemplates`:
  - views HTML/Blade para layout e componentes reutilizáveis (header/footer/tables)

### 7.4 Plano de migração incremental (compatibilidade)

- Implementar primeiro relatórios já “modernos” (Consultas de Movimento) em novo renderer, mantendo UI/menus/filtros.
- Criar adapter para relatórios Jasper existentes:
  - manter Jasper apenas enquanto necessário
  - trocar relatório por relatório para o novo motor, mantendo `templateName()` como “alias” (para não quebrar links/rotinas).

---

## 8) Relatórios e documentos escolares mais comuns (catálogo executivo)

Esta seção descreve os **documentos e relatórios mais comuns** no âmbito escolar, com foco em:

- **O que é / para que serve**
- **Lógica e regras envolvidas** (inputs e validações)
- **Dados necessários** (visão de alto nível dentro do domínio i-Educar)
- **Faz sentido para o i-Educar?** (recomendação para adoção no novo package sem Java)

> **Nota**: alguns itens existem no i-Educar como “telas/consultas” (ex.: movimentos) e outros como “documentos oficiais” (boletins, históricos, declarações). A proposta aqui é servir de base de decisão para um novo motor de documentos, sem depender de Jasper/Java.

### 8.1 Documentos do aluno (uso externo / oficial)

#### 8.1.1 Boletim do aluno (por período/etapa e consolidado anual)

- **Finalidade**: comunicar desempenho (notas/pareceres) e frequência, por componente curricular e por etapa/ano.
- **Lógica típica**:
  - seleção da **matrícula** e do **ano letivo**;
  - recorte por **etapa** (bimestre/trimestre/semestre) quando aplicável;
  - cálculo/obtenção de **médias** conforme a **regra de avaliação** (fórmula, arredondamento, substituições);
  - composição de frequência: faltas por componente ou geral (depende de regra/turma).
- **Dados necessários**:
  - matrícula, enturmações (sequenciais), série/curso, turma/turno;
  - componentes curriculares, notas por etapa, parecer descritivo (quando existir);
  - faltas, carga horária e parâmetros da regra de avaliação.
- **Faz sentido para o i-Educar?**: **Sim (essencial)**.
- **Observações para novo package sem Java**:
  - precisa suportar variações de layout (modelos) e regras (nota x parecer; faltas geral x por disciplina);
  - deve preservar consistência com o que a escola utiliza oficialmente.

#### 8.1.2 Boletim do professor (por turma/componente)

- **Finalidade**: visão de lançamento/consulta por professor, turma e componente (muitas redes usam como conferência/entrega).
- **Lógica típica**:
  - filtro por **ano**, **escola**, **série**, **turma**, **componente** e professor;
  - exibição de resultados e, por vezes, pendências (não lançados / bloqueios).
- **Dados necessários**:
  - vínculos de docente, alocações, diário/lançamentos, etapas.
- **Faz sentido para o i-Educar?**: **Sim**, mas pode ser **substituível** por telas (quando a rede não exige impressão).

#### 8.1.3 Histórico escolar (regular e EJA)

- **Finalidade**: documento oficial consolidando toda a trajetória escolar, séries/anos, componentes, cargas, resultados e conclusão.
- **Lógica típica**:
  - depende de **processamento/fechamento** do histórico (consistência e imutabilidade);
  - regras para:
    - dependência, aproveitamento/dispensa, reclassificação;
    - equivalências e “componentes agregados” (varia por rede);
    - migração entre matrizes curriculares ao longo dos anos.
- **Dados necessários**:
  - registros anuais consolidados, componentes, resultados finais, cargas horárias, dados da instituição/escola.
- **Faz sentido para o i-Educar?**: **Sim (essencial)**.
- **Recomendação**: tratar como **documento de alta criticidade** (auditoria/versão, carimbo de emissão, assinatura/validação).

#### 8.1.4 Declaração/Atestado de matrícula

- **Finalidade**: comprovar vínculo do aluno (matriculado), com dados de turma/turno/série e data.
- **Lógica típica**:
  - seleção da matrícula (ativa) e do ano;
  - considerar situações especiais (transferido, cancelado, abandono) e regra de “validade” do documento.
- **Dados necessários**:
  - dados do aluno, matrícula, escola, turma/turno, data emissão.
- **Faz sentido para o i-Educar?**: **Sim (muito comum)**.

#### 8.1.5 Declaração/Atestado de frequência

- **Finalidade**: comprovar que o aluno está frequentando (muitas vezes com intervalo de datas).
- **Lógica típica**:
  - intervalo (ex.: mês/etapa) e apuração de presença/faltas;
  - critério de frequência mínima pode variar.
- **Dados necessários**:
  - chamadas/frequência registrada no diário, calendário letivo.
- **Faz sentido para o i-Educar?**: **Sim**, mas depende de a rede registrar frequência com granularidade suficiente.

#### 8.1.6 Declaração/Atestado de vaga (vaga/transferência)

- **Finalidade**: comprovar existência de vaga e/ou intenção de transferência.
- **Lógica típica**:
  - pode depender de regras administrativas locais; às vezes é mais “declaratório” do que um dado do sistema.
- **Dados necessários**:
  - dados do aluno e escola; informações administrativas (texto parametrizável).
- **Faz sentido para o i-Educar?**: **Depende da rede**, mas costuma ser útil se o texto for parametrizável.

#### 8.1.7 Guia/Declaração de transferência

- **Finalidade**: formalizar transferência entre unidades/rede.
- **Lógica típica**:
  - estado da matrícula (transferida) e data do evento;
  - dados de destino podem ser informados manualmente.
- **Dados necessários**:
  - evento de transferência, motivo, datas, histórico parcial.
- **Faz sentido para o i-Educar?**: **Sim**, principalmente se a rede usa o i-Educar como fonte oficial.

#### 8.1.8 Carteirinha do estudante / transporte escolar

- **Finalidade**: identificação e benefícios (transporte), normalmente com foto, rota e validade.
- **Lógica típica**:
  - critérios de elegibilidade (benefício, rota, situação da matrícula);
  - validade por ano/semestre.
- **Dados necessários**:
  - foto (quando existir), benefício/transporte, rota, escola/turma.
- **Faz sentido para o i-Educar?**: **Depende** (varia muito por município e integrações).

#### 8.1.9 Declaração de conclusão / certificado / diploma (quando aplicável)

- **Finalidade**: comprovar conclusão de etapa/nível (ex.: fundamental, EJA), frequentemente com base legal e dados consolidados.
- **Lógica típica**:
  - exige matrícula finalizada e/ou histórico processado;
  - depende de textos legais (lei/portaria) parametrizáveis e regras do sistema local.
- **Dados necessários**:
  - dados do aluno, escola/instituição, resultados finais e data de conclusão; base legal.
- **Faz sentido para o i-Educar?**: **Depende da rede**, mas é **comum no mercado**.

#### 8.1.10 Declaração de escolaridade / “nada consta” (vida escolar)

- **Finalidade**: comprovar escolaridade, vínculo anterior, ou ausência de pendências/ocorrências (termo “nada consta” varia muito).
- **Lógica típica**:
  - pode ser apenas declaratória, com filtros por período/unidade;
  - exige cuidado jurídico (o que o sistema consegue atestar de fato).
- **Dados necessários**:
  - matrículas, situações, ocorrências/pendências (se houver), histórico.
- **Faz sentido para o i-Educar?**: **Sim**, desde que o escopo do “nada consta” seja bem definido.

### 8.2 Documentos e relatórios pedagógicos (uso interno / gestão)

#### 8.2.1 Diário de classe (impressão do diário)

- **Finalidade**: registro físico/arquivamento (conteúdo, frequência, avaliações).
- **Lógica típica**:
  - seleção por turma, componente, etapa e professor;
  - compilação de aulas/conteúdos + frequência + notas.
- **Dados necessários**:
  - planos/registro de aula (se usado), chamadas, avaliações/lançamentos.
- **Faz sentido para o i-Educar?**: **Sim**, mas pode ser substituível se a rede aceitar arquivo digital.

#### 8.2.2 Ata de resultados finais / conselho

- **Finalidade**: formalizar resultados finais (aprovado/reprovado/transferido) e decisões colegiadas.
- **Lógica típica**:
  - consolidação após fechamento do ano/etapas;
  - regras para dependência, progressão, reclassificação.
- **Dados necessários**:
  - situação final da matrícula, resultados finais por componente (quando exigido).
- **Faz sentido para o i-Educar?**: **Sim**, em redes que arquivam atas.

#### 8.2.2.1 Ata de conselho de classe (por etapa)

- **Finalidade**: registrar deliberações do conselho (observações, encaminhamentos e decisões por etapa).
- **Lógica típica**:
  - recorte por turma/etapa; lista de alunos com síntese (notas/frequência) e campo para deliberações.
- **Dados necessários**:
  - turma/etapa, alunos, síntese de notas/frequência, ocorrências (se habilitado).
- **Faz sentido para o i-Educar?**: **Sim (comum no mercado)**, mas com alta variação de layout.

#### 8.2.2.2 Ata de entrega de resultados / reunião (assinaturas)

- **Finalidade**: comprovar entrega de boletins/resultados aos responsáveis (lista + assinaturas).
- **Lógica típica**:
  - lista de alunos por turma e campo de assinatura; pode incluir data/local.
- **Dados necessários**:
  - turma, alunos e responsáveis (quando disponíveis).
- **Faz sentido para o i-Educar?**: **Sim**, quando a rede exige comprovação física.

#### 8.2.3 Ficha individual do aluno (síntese pedagógica)

- **Finalidade**: resumo do desempenho e dados pedagógicos do aluno, geralmente por ano.
- **Lógica típica**:
  - mistura dados cadastrais + resultados/pareceres + observações.
- **Dados necessários**:
  - matrícula, avaliações, pareceres, frequências, ocorrências (quando habilitadas).
- **Faz sentido para o i-Educar?**: **Sim**, mas o layout varia muito por rede.

#### 8.2.3.1 Ficha de acompanhamento/registro individual (educação infantil)

- **Finalidade**: registro descritivo (campos/competências) típico da educação infantil.
- **Lógica típica**:
  - substitui nota por campos descritivos, rubricas e observações; por períodos.
- **Dados necessários**:
  - matriz/itens avaliativos, registros descritivos por etapa/período, turma.
- **Faz sentido para o i-Educar?**: **Sim (comum)**, se o módulo/estrutura de parecer/itens existir na rede.

#### 8.2.4 Mapa de notas / mapa de frequência (por turma)

- **Finalidade**: visão rápida por turma, por etapa, para conferência.
- **Lógica típica**:
  - pivot por aluno × componente × etapa; regras de arredondamento/recuperação.
- **Dados necessários**:
  - lançamentos e regra de avaliação.
- **Faz sentido para o i-Educar?**: **Sim** (muito usado em gestão pedagógica).

#### 8.2.4.1 Espelho de diário (por turma/componente)

- **Finalidade**: consolidar em formato de “espelho” os lançamentos (conteúdos, frequência, avaliações) para conferência/arquivamento.
- **Lógica típica**:
  - recorte por componente e etapa; pode exigir paginação e totalizadores.
- **Dados necessários**:
  - diário, chamadas, avaliações, calendário/etapas.
- **Faz sentido para o i-Educar?**: **Sim**, principalmente para redes com auditoria/arquivo físico.

#### 8.2.4.2 Relatório de pendências de lançamento (notas/frequência)

- **Finalidade**: gestão de conformidade (o que ainda não foi lançado, por turma/componente/professor).
- **Lógica típica**:
  - checa “existência” de lançamentos esperados por etapa e bloqueios.
- **Dados necessários**:
  - etapas ativas, alocações docentes, diários e lançamentos.
- **Faz sentido para o i-Educar?**: **Sim (muito útil)**.

#### 8.2.5 Relatórios de “movimento” (geral e mensal)

- **Finalidade**: gestão e reporte de fluxos (admissões, transferências, abandono, remanejamento, reclassificação, óbito) em janelas temporais.
- **Lógica típica**:
  - recorte por datas e agrupamentos por escola/turma/série;
  - drill-down de alunos por indicador.
- **Dados necessários**:
  - eventos/situações da matrícula e enturmações, datas de entrada/saída, classificação.
- **Faz sentido para o i-Educar?**: **Sim** (já existe no core moderno).

### 8.3 Recomendações de priorização para o novo package (sem Java)

Se o objetivo é ter um “pacote de documentos” moderno, sem Java, com alto impacto e baixo risco:

- **Prioridade 1 (essenciais/altíssima demanda)**:
  - Boletim do aluno
  - Declaração/Atestado de matrícula
  - Histórico escolar (exige desenho cuidadoso e validações)

- **Prioridade 2 (alta demanda, mas variação de layout)**:
  - Diário de classe (impressão)
  - Mapas de notas/frequência
  - Ata de resultados finais

- **Prioridade 3 (depende de políticas locais/integrações)**:
  - Carteirinha/transporte
  - Atestado de vaga
  - Guia de transferência (se a rede já tiver fluxo próprio)

> **Diretriz**: para documentos oficiais (boletim/histórico/atas), o novo motor deve suportar **versão/auditoria** (data/hora, usuário emissor, parâmetros e reemissão) e garantir reprodutibilidade.

### 8.4 Sugestão de menus temáticos (para o novo package)

Esta proposta agrupa os relatórios/documentos por **perfil de uso** (secretaria, pedagógico, gestão) e ajuda a “encontrabilidade” no dia a dia. O objetivo é que a árvore de menus seja **intuitiva**, independente do motor (Jasper vs. novo renderer).

#### 8.4.0 Menus que devem aparecer após instalar o novo package (Advanced Reports)

Após instalar o `i-educar-advanced-reports-package` (migrations aplicadas), os seguintes itens devem aparecer no menu **Escola**:

- **Escola → Relatórios**
  - **Avaliação e frequência** (grupo / placeholder)
  - **Movimentações** → `Relatórios Avançados → Movimentações` (`/relatorios-avancados/movimentacoes`)
  - **Indicadores**
    - **Socioeconômicos** (`/relatorios-avancados/socioeconomicos`)
    - **Inclusão (NEE)** (`/relatorios-avancados/inclusao`)
    - **Distorção idade/série** (`/relatorios-avancados/distorcao-idade-serie`)
    - **Vulnerabilidade / Assistência social** (`/relatorios-avancados/vulnerabilidade`)

- **Escola → Documentos**
  - **Documentos do aluno (oficiais)**
    - **Diplomas/Certificados (modelos)** (`/relatorios-avancados/diplomas`)
    - **Declarações e guias (oficiais)** (`/relatorios-avancados/documentos`)
    - **Boletim do aluno (PDF)** (`/relatorios-avancados/boletim`)
    - **Histórico escolar (PDF)** (`/relatorios-avancados/historico`)
  - **Atas e registros formais** (grupo / placeholder)
  - **Fichas e formulários (em branco)** (grupo / placeholder)

Se as rotas estiverem disponíveis e o menu não aparecer, normalmente é **cache de menus** e/ou **migrations não executadas**. Recomenda-se executar os comandos de limpeza de cache do README (inclui `php artisan advanced-reports:flush-menus`).

#### 8.4.1 Menu: Documentos do aluno (Oficiais)

- **Boletim do aluno**
- **Histórico escolar (regular/EJA)**
- **Declaração/Atestado de matrícula**
- **Declaração/Atestado de frequência**
- **Guia/declaração de transferência**
- **Declaração de conclusão / certificado** *(se aplicável)*
- **Declaração de escolaridade / vida escolar / “nada consta”** *(com escopo bem definido)*

#### 8.4.2 Menu: Avaliação e frequência (Pedagógico)

- **Mapas de notas (por turma/etapa)**
- **Mapas de frequência (por turma/etapa)**
- **Espelho de diário**
- **Relatório de pendências de lançamento (notas/frequência)**
- **Ficha individual (fundamental)**
- **Ficha de acompanhamento (educação infantil)**

**Status no novo package (sem Java)**:

- Os quatro itens principais (mapa de notas, mapa de frequência, espelho de diário e pendências) foram **incluídos no menu** como **placeholders** (tela de orientação) para manter consistência do fluxo para usuário leigo.
- A implementação completa desses relatórios permanece como **Prioridade 2**, pois exige definição de regras por rede (etapas, diários, alocações docentes e eventos de lançamento).

#### 8.4.3 Menu: Atas e registros formais (Arquivo)

- **Ata de resultados finais**
- **Ata de conselho de classe (por etapa)**
- **Ata de entrega de resultados (assinaturas)**
- *(opcional)* **Livro de chamadas / registros** (quando a rede exige impresso)

**Status no novo package (sem Java)**:

- **Ata de resultados finais**: implementada com opção de incluir **notas por componente/etapa** e **frequência** (quando disponível).
- **Lista de assinaturas (responsáveis)**: implementada.
- **Ata de conselho de classe** e **Ata de entrega de resultados**: adicionadas como **placeholders** (tela de orientação), aguardando padronização do layout e regras por rede.

#### 8.4.4 Menu: Movimentações e gestão de matrículas (Secretaria/Gestão)

- **Movimento geral** (período)
- **Movimento mensal** (período)
- *(comum no mercado, se houver dados)* **Relatório de alunos por situação** (ativos, transferidos, abandono, reclassificados, falecidos)
- *(comum no mercado)* **Relatório de vagas/turmas** (capacidade, ocupação, excedentes)

#### 8.4.5 Menu: Transporte e benefícios (quando aplicável)

- **Carteirinha do estudante**
- **Carteirinha/declaração para transporte**
- **Listagens por rota/benefício** (para operação do transporte/assistência)

#### 8.4.6 Menu: Gestão escolar (Administrativo)

- **Listas e etiquetas** (alunos, turmas, responsáveis) *(comum no mercado)*
- **Relatórios gerenciais** (quantitativos por escola/curso/série/turma)

#### 8.4.7 Regras de posicionamento em menus já existentes

Para evitar “explosão” de navegação e manter consistência com instalações padrão:

- **Se o município usa fortemente “Escola → Documentos”**: inserir ali **apenas documentos oficiais do aluno** (boletim/histórico/declarações), mantendo relatórios pedagógicos em um menu separado.
- **Se o município usa “Escola → Relatórios”**: inserir ali consultas/relatórios gerenciais e manter “Documentos” apenas para peças oficiais.
- Criar um menu dedicado **“Documentos e Relatórios”** (novo) apenas se:
  - a árvore existente não comportar bem o volume, ou
  - for necessário padronizar em multi-tenant com variações por rede.

#### 8.4.8 Menu: Indicadores (Socioeconômicos e Inclusão)

Além de “Socioeconômicos”, é comum (e útil) manter um eixo de **Inclusão (NEE)** com base nos cadastros disponíveis:

- **Inclusão (NEE)**
  - alunos com **deficiência** (cadastro físico)
  - alunos com **NIS** (quando informado)
  - alunos com **benefícios** (quando associados)
  - recortes por escola e por tipo (top)

**Observação**: estes indicadores são de **gestão** e não substituem laudos/avaliações clínicas; devem ser apresentados com disclaimer.

- **Distorção idade/série**
  - indicador clássico de rede, útil para planejamento pedagógico e metas
  - depende de `pmieducar.serie.idade_ideal` e da data de nascimento do aluno
  - recomendação: apresentar metodologia (idade aproximada) e permitir recortes por escola/curso

- **Vulnerabilidade / Assistência social**
  - consolida marcadores “objetivos” de vulnerabilidade usando dados existentes:
    - NIS preenchido
    - benefícios associados
    - deficiência cadastrada
    - transporte rural
    - renda mensal (quando informada; qualidade varia por rede)
  - saídas recomendadas: **PDF** (com opção de gráficos) e **Excel** (listagem + agregações por escola/benefício)
  - importante: apresentar **disclaimer** sobre completude/atualização dos dados e LGPD (uso para gestão/planejamento, não para rotulagem individual)

### 8.5 Temas de menor necessidade (baixa prioridade) que aparecem em “pacotes de relatórios”

Abaixo estão temas que, embora existam em algumas redes/sistemas, tendem a ter **menor prioridade** frente ao núcleo (boletim/histórico/declarações/atas/movimentos). Eles podem entrar como **backlog** do novo package, implementados sob demanda.

#### 8.5.1 Etiquetas, listas e utilitários de secretaria

- **Etiquetas** (aluno, prontuário, pasta, caderno de chamada): úteis, mas não “documento oficial”.
- **Listas rápidas**: aniversariantes, alunos por bairro/logradouro, contatos de responsáveis.
- **Listas de turma**: com/sem foto, com campos customizáveis.

**Faz sentido para o i-Educar?**: **Sim**, mas como **utilitário** e não como prioridade 1.

#### 8.5.2 Relatórios “administrativos” periféricos

- **Inventário/patrimônio** (quando o sistema não é de patrimônio).
- **Relatórios de almoxarifado/insumos** (quando não há módulo próprio).

**Faz sentido para o i-Educar?**: **Geralmente não** (escopo costuma estar fora da gestão escolar pedagógica/secretaria).

#### 8.5.3 Biblioteca (quando o módulo não é central ou é externo)

- Em algumas instalações há biblioteca integrada, mas não é universal.
- Relatórios como: empréstimos, atrasos, acervo, usuários.

**Faz sentido para o i-Educar?**: **Depende** (só se a rede usa biblioteca integrada no próprio i-Educar).

#### 8.5.4 Alimentação escolar/merenda

- Cardápios, controle de distribuição, listas por turma, quantitativos.

**Faz sentido para o i-Educar?**: **Depende** (muitas redes têm sistemas próprios; pode ser complementar).

#### 8.5.5 Transporte escolar (além do essencial)

- Rotas detalhadas, mapas de embarque/desembarque, relatórios por veículo/motorista.

**Faz sentido para o i-Educar?**: **Depende** (alta variação; normalmente vem de integrações).

#### 8.5.6 “Relatórios para auditoria/controle” muito específicos

- Trilhas de auditoria impressas, logs, relatórios de alterações cadastrais.

**Faz sentido para o i-Educar?**: **Sim**, mas como **relatórios técnicos** para equipe administrativa/controle interno.

### 8.6 Atas, fichas em branco e modelos (mercado/necessidade educacional)

Além dos documentos já descritos, estes itens são frequentemente solicitados por redes por necessidade de **padronização** e **impressão**. Eles podem ser tratados como “templates” com campos parametrizáveis (texto + placeholders), e nem sempre dependem de dados complexos.

#### 8.6.1 Tipos de atas (variações comuns)

- **Ata de resultados finais** (já listada)
- **Ata de conselho de classe** (já listada)
- **Ata de reunião pedagógica** (pauta + participantes + encaminhamentos)
- **Ata de reunião de responsáveis** (turma + data + lista + assinaturas)
- **Ata de ocorrência/registro disciplinar** (quando a rede formaliza em ata)

**Faz sentido para o i-Educar?**: **Sim**, quando houver necessidade de arquivo físico e padronização municipal.

#### 8.6.2 “Fichas em branco” e formulários padronizados

- **Ficha de matrícula/rematrícula** (capa + dados cadastrais para conferência/assinatura)
- **Ficha de autorização** (uso de imagem, saídas, atividades, transporte) — normalmente texto parametrizável
- **Ficha de saúde/alergias** (com campos livres e responsáveis)
- **Ficha de acompanhamento** (infantil) — muitas vezes sem dados automáticos, mas com cabeçalho e campos para preenchimento manual

**Faz sentido para o i-Educar?**: **Sim**, desde que sejam tratados como **modelos oficiais** e com governança (versão).

#### 8.6.3 Termos e declarações “legais” (com texto parametrizável)

- Termos com base legal municipal/estadual, portarias, resoluções (variáveis por rede).

**Faz sentido para o i-Educar?**: **Sim**, mas requer módulo de **templates parametrizáveis** e controle de versão.

---

## 8) Inventário do que foi possível levantar neste repositório

### 8.1 Consultas/relatórios com UI e filtros completos

- **Consulta de movimento geral** (processo `9998900`)
  - filtros: ano, instituição, cursos (multi), data inicial/final
  - indicadores: ed. infantil (int/parc), 1º–9º, admitidos, transf., aband., rem., recla., óbito
  - drill-down via API: alunos por indicador

- **Consulta de movimento mensal** (processo `9998910`)
  - filtros: ano, instituição, escola, curso/série/turma (opcionais), modalidade, calendários (EJA), data inicial/final
  - indicadores: matrícula inicial/final, transferências, abandono, admitidos, óbito, reclassificados (saiu/entrou), remanejados (saiu/entrou), por sexo
  - drill-down via API: alunos por indicador

### 8.2 Emissão Jasper via API (sem detalhar templates)

- **Boletim do aluno** (`ReportController@get boletim`)
- **Boletim do professor** (`ReportController@get boletim-professor`)

### 8.3 Núcleo Jasper (engine e config)

- `ReportCore` + `ReportFactoryPHPJasper` + `ReportCoreController`
- `reports:jasper-diagnose`
- `config/legacy.php` (`legacy.report.*`)

---

## 9) Próximos passos para completar “todos os relatórios do pacote Portabilis”

Para documentar **100% do pacote externo** (lista de relatórios Jasper, menus próprios, filtros por relatório e a árvore de `ReportSources`), é necessário ter no workspace:

- o repositório do pacote `i-educar-reports-package` (ou fork) com:
  - `ieducar/modules/Reports/ReportSources/*.jrxml`
  - classes PHP que implementam `templateName()` e `requiredArgs()`
  - service provider e comandos `community:reports:*`

Quando esse pacote estiver disponível no workspace, a extensão deste documento deve incluir:

- lista completa de `.jrxml` e subreports
- para cada relatório:
  - finalidade
  - menu/permissão
  - filtros (UI)
  - parâmetros Jasper (args)
  - fonte de dados (SQL/datasource)
  - saída (PDF/encoding) e particularidades
