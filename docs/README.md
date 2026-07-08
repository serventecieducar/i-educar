# Documentação i-Educar

Índice da documentação operacional e técnica deste repositório. Os guias seguem o **fluxo natural do sistema** (configuração → cadastros → movimentação → Censo → documentos).

---

## Navegação rápida

| Se você precisa… | Documento |
|------------------|-----------|
| Matricular o mesmo aluno em duas escolas no mesmo ano (regular + AEE ou atividade complementar) | [Matrícula dupla](operacao/MATRICULA-DUPLA-REGULAR-AEE-ATIVIDADE-COMPLEMENTAR.md) |
| Executar seeds de perfis de usuário na rede | [Perfis de usuário (município)](administracao/PERFIS-USUARIOS-MUNICIPIO.md) |
| Atualizar produção, caches, Jasper e filas | [Comandos em produção](infraestrutura/COMANDOS-PRODUCAO.md) |
| Entender o pacote de relatórios Portabilis | [Doc executivo — relatórios](relatorios/DOC-EXECUTIVO-PACOTE-RELATORIOS-PORTABILIS.md) |
| Notas sobre PR do pacote Jasper | [PR — Jasper reports package](relatorios/PR-JASPER-REPORTS-PACKAGE.md) |

---

## Por tema

### Operação escolar

Fluxos do dia a dia no módulo **Escola**: matrícula, enturmação, AEE e Censo.

| Documento | Descrição |
|-----------|-----------|
| [Matrícula dupla (regular + AEE / atividade complementar)](operacao/MATRICULA-DUPLA-REGULAR-AEE-ATIVIDADE-COMPLEMENTAR.md) | Duas matrículas no mesmo ano letivo, escolas e turnos diferentes; histórico, validações e Educacenso |

### Administração da rede

Configuração de usuários, perfis e permissões.

| Documento | Descrição |
|-----------|-----------|
| [Perfis de usuário — município](administracao/PERFIS-USUARIOS-MUNICIPIO.md) | Personas SME, secretaria e direção; seed `PerfisUsuariosMunicipioSeeder` |

### Infraestrutura e deploy

Comandos e rotinas de servidor.

| Documento | Descrição |
|-----------|-----------|
| [Comandos em produção](infraestrutura/COMANDOS-PRODUCAO.md) | Git, Composer, caches, migrações, Jasper, PHP-FPM e filas |

### Relatórios

Integração Jasper e pacote Portabilis/Serventec.

| Documento | Descrição |
|-----------|-----------|
| [Doc executivo — pacote de relatórios](relatorios/DOC-EXECUTIVO-PACOTE-RELATORIOS-PORTABILIS.md) | Arquitetura, fluxo de emissão e inventário |
| [PR — Jasper reports package](relatorios/PR-JASPER-REPORTS-PACKAGE.md) | Notas de pull request do pacote |

---

## Convenções desta pasta

| Elemento | Uso |
|----------|-----|
| Bloco `>` no topo | Metadados: tipo, módulo, público-alvo |
| `---` | Separação entre partes temáticas |
| Tabelas | Caminhos de menu, regras e checklists |
| Fluxogramas Mermaid | Visão do processo antes do passo a passo |
| Rodapé **Ver também** | Links para o índice e documentos relacionados |

### Ordem lógica no i-Educar (referência)

```
Instituição → Configurações do sistema
     ↓
Escola → Ano letivo → Cursos / séries / turmas
     ↓
Aluno (cadastro)
     ↓
Matrícula → Enturmação
     ↓
Educacenso (análise e exportação)
     ↓
Histórico e documentos escolares
```

---

## Estrutura de pastas

```
docs/
├── README.md                 ← este índice
├── operacao/                 ← rotinas escolares
├── administracao/            ← perfis e permissões
├── infraestrutura/           ← deploy e servidor
└── relatorios/               ← Jasper e pacote de relatórios
```

---

## Contribuir

Ao acrescentar um guia:

1. Coloque o arquivo na pasta temática correta.
2. Use o cabeçalho padrão (metadados + link para este índice).
3. Atualize a tabela em **Navegação rápida** e na seção temática correspondente.
4. Inclua rodapé **Ver também** com links relacionados.
