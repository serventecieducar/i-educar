# Scripts SQL (i-Educar)

Scripts avulsos — **fora** do pacote `buriti/i-educar-setup-package`.

## Turmas infantil sem matrículas (ano 2026)

Execute **um arquivo por vez** (não selecione vários blocos juntos).

| Ordem | Arquivo | O que faz |
|-------|---------|-----------|
| 1 | [`01-previa-contagem-turmas-infantil.sql`](01-previa-contagem-turmas-infantil.sql) | Conta turmas a remover |
| 2 | [`01-previa-lista-turmas-infantil.sql`](01-previa-lista-turmas-infantil.sql) | Lista `cod_turma`, escola, série |
| 3 | [`02-excluir-turmas-infantil-sem-matriculas.sql`](02-excluir-turmas-infantil-sem-matriculas.sql) | Apaga enturmações inativas, dependências e turmas (um comando) |

**Critério:** turma **sem matrícula ativa** no ano. Enturmações só inativas em `matricula_turma` são removidas antes da turma (evita erro de FK).

Altere `2026` nos arquivos se precisar de outro ano.

### psql

```bash
psql -h HOST -U USER -d DATABASE -f scripts/sql/01-previa-contagem-turmas-infantil.sql
psql -h HOST -U USER -d DATABASE -f scripts/sql/01-previa-lista-turmas-infantil.sql
psql -h HOST -U USER -d DATABASE -f scripts/sql/02-excluir-turmas-infantil-sem-matriculas.sql
```

### phpPgAdmin / DBeaver

Abra **um** arquivo, execute **tudo** (F5). Não misture a prévia com o `02-excluir` na mesma execução.
