## Seed de perfis (personas) — Rede Municipal

Este documento descreve os **perfis de usuário (tipos de usuário)** criados via seed para atender personas comuns em redes municipais.

### Como executar

```bash
php artisan db:seed --class=Database\\Seeders\\PerfisUsuariosMunicipioSeeder
```

> Observação: o seed cria **apenas perfis** (tipos de usuário) e suas permissões de menu.  
> Ele **não cria usuários** automaticamente.

---

## Perfis criados

### **SME — Relatórios (leitura)**
Para quem é:
- Técnicos da Secretaria Municipal de Educação que atuam com **várias escolas** e precisam **consultar** informações.

O que pode fazer (humanizado):
- Ver relatórios da escola (movimento, listagens, acompanhamentos).
- Gerar/consultar documentos (quando disponíveis no menu).
- Consultar ferramentas de apoio (consultas e exportações).
- Acessar telas de Educacenso para consulta/exportação.

O que NÃO pode fazer:
- Não cadastra/edita/exclui alunos, turmas ou matrículas.
- Não acessa configurações sensíveis (instituição, regras de avaliação, fórmulas, componentes, cursos, séries, permissões).

---

### **SME — Apoio (operacional)**
Para quem é:
- Equipes da SME que dão suporte às escolas e precisam **consultar** dados e também **executar rotinas operacionais** em diferentes unidades.

O que pode fazer:
- Tudo do perfil **SME — Relatórios (leitura)**.
- Realizar as rotinas do perfil **Secretaria Escolar — Operacional** (cadastros e movimentações do dia a dia no módulo Escola).

O que NÃO pode fazer:
- Não acessa configurações sensíveis (instituição, regras de avaliação, fórmulas, componentes, cursos, séries, permissões).
- Não possui permissões de exclusão por padrão.

---

### **Secretaria Escolar — Operacional**
Para quem é:
- Secretários(as) escolares que executam as rotinas do dia a dia.

O que pode fazer:
- Operar o módulo Escola: cadastros e movimentações necessárias para rotina escolar.
- Acompanhar relatórios e documentos.

O que NÃO pode fazer:
- Não acessa configurações sensíveis (instituição, regras de avaliação, fórmulas, componentes, cursos, séries, permissões).

---

### **Auxiliar de Secretaria — Cadastro básico**
Para quem é:
- Auxiliares que ajudam em digitação/conferência e rotinas assistidas.

O que pode fazer:
- Cadastrar/atualizar informações dentro do módulo Escola, com foco em operação básica.

O que NÃO pode fazer:
- Não tem permissões de exclusão.
- Não acessa configurações sensíveis.

---

### **Direção — Gestão escolar**
Para quem é:
- Diretor(a) e vice-diretor(a) com foco em acompanhamento.

O que pode fazer:
- Consultar relatórios e documentos.
- Consultar exportações/consultas, quando disponíveis.

O que NÃO pode fazer:
- Não executa cadastros operacionais por padrão.
- Não acessa configurações sensíveis.

---

## Como as permissões são aplicadas

- O i-Educar usa `public.menus` (árvore de menus) e a tabela `pmieducar.menu_tipo_usuario` para decidir quais telas aparecem e quais ações são permitidas.
- Cada vínculo perfil ↔ menu possui 3 flags:
  - **visualiza**: pode abrir/consultar
  - **cadastra**: pode cadastrar/alterar
  - **exclui**: pode excluir

O seed aplica permissões por **subárvores** (ex.: Relatórios, Documentos, Consultas) e remove permissões de Configurações para esses perfis.

