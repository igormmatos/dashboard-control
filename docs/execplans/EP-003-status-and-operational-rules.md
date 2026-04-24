# ExecPlan: EP-003 Status and Operational Rules

Last updated: 2026-04-23

## Objective
- In scope:
  - consolidar a regra oficial de status do domínio para `agreements` e `installments`;
  - substituir a lógica provisória do EP-002 por uma especificação executável, consistente e rastreável;
  - formalizar a estratégia híbrida de RF007 entre status persistido do acordo e estados operacionais derivados por data;
  - alinhar impactos em queries, detalhe do acordo, componentes Livewire, documentação, fixtures e testes;
  - fechar o comportamento mínimo operacional de RF007, RF008, RF012 e RF013 sem ampliar escopo funcional.
- Out of scope:
  - CRUD do acordo e ações transacionais de edição;
  - exportação CSV de RF014;
  - decisão final e implementação completa de RF015 além da base necessária para não conflitar com a regra de status;
  - paginação real das listas;
  - ranking, gráfico, auditoria avançada e automações fora do fechamento de domínio.

## Inputs and Evidence
- Reviewed:
  - `.agent/AGENTS.md`
  - `.agent/PLANS.md`
  - `docs/PRD.md`
  - `docs/current-state.md`
  - `docs/architecture.md`
  - `docs/database.md`
  - `docs/acceptance-tests.md`
  - `docs/execplans/EP-001-foundation.md`
  - `docs/execplans/EP-002-dashboard-core.md`
  - `app/Support/Dashboard/DashboardStatusCatalog.php`
  - `app/Support/Dashboard/DashboardQueries.php`
  - `app/Livewire/Dashboard/Foundation.php`
  - `app/Livewire/Agreements/Show.php`
  - `app/Models/Agreement.php`
  - `app/Models/Installment.php`
  - `tests/Feature/DashboardTest.php`
  - `tests/Feature/AgreementShowTest.php`
  - `database/seeders/DashboardDemoSeeder.php`
  - `database/factories/*`
- Facts:
  - a baseline técnica validada do repositório permanece Laravel 13.6.0 + MySQL + monólito web;
  - a UI principal vigente continua em Livewire + Tailwind;
  - o EP-001 já entregou scaffold, auth base, schema mínimo e shell do dashboard;
  - o EP-002 já entregou dashboard operacional mínimo, detalhe do acordo em leitura, seeds, factories e testes;
  - o vocabulário oficial foi consolidado em `DashboardStatusCatalog` com:
    - `agreements.status`: `com_atraso`, `ativo`, `descumprido`, `finalizado`, `encerrado_sem_quitacao`
    - `installments.status`: `vencido`, `pago_com_atraso`, `pago_em_dia`, `acordo_feito`, `em_dia`
  - `DashboardQueries`, `Agreement` e `Installment` passaram a separar status persistido, estado derivado por data e sugestão operacional;
  - o detalhe do acordo em `App\Livewire\Agreements\Show` continua em leitura operacional e agora expõe status persistido, estado derivado e sugestão sem mutação automática;
  - os testes de feature, factories e seeders já refletem o vocabulário oficial do domínio;
  - as decisões oficiais desta tarefa fecham a estratégia híbrida de RF007, o vocabulário aceito de status, as ordenações mínimas de RF012, o alcance global de RF013 e a postergação da paginação.
- Assumptions:
  - o EP-003 pode ajustar nomenclatura, contratos e comportamento operacional sem introduzir novos fluxos de CRUD;
  - estados derivados por data podem existir como conceito de leitura operacional mesmo quando não forem persistidos no banco como `agreements.status`;
  - sugestões ao usuário podem ser materializadas nesta fase como sinalização de interface/leitura, sem ação persistente de alteração do acordo.
- Unknowns:
  - se o banco deverá continuar usando colunas `status` livres ou se a próxima etapa exigirá enum/check constraint;
  - se RF015 no futuro será atendido apenas por atualização de `installments.status`, por cálculo em consulta, ou por composição entre persistência e derivação;
  - se `acordo_feito` e `finalizado` ainda precisam de semântica textual adicional para reduzir ambiguidade funcional futura.

## Implementation Strategy
- Approach:
  - tratar o EP-003 como fechamento de domínio e reconciliação entre documentação, catálogo de status, queries, UI e testes;
  - separar explicitamente cinco camadas de regra:
    - status persistidos de `agreements`;
    - status persistidos ou atualizáveis de `installments`;
    - estados operacionais derivados por data;
    - sugestões ao usuário sem mutação automática de `agreements.status`;
    - filtros rápidos de tela que operam sobre todas as listas do dashboard;
  - preservar a estratégia híbrida oficial:
    - `agreements.status` é persistido;
    - `agreements.status` não muda automaticamente a partir de `installments`;
    - `installments.status` pode mudar conforme a data atual e a regra operacional adotada;
    - o sistema pode sugerir mudança de status do acordo quando houver parcelas atrasadas;
    - a mudança real de `agreements.status` depende de ação explícita do usuário e não entra neste EP como CRUD.
- Mapping to repository artifacts:
  - catálogo e semântica de status:
    - `app/Support/Dashboard/DashboardStatusCatalog.php`
    - possível extração de nomenclatura/conceitos de domínio para suporte compartilhado
  - queries e classificação operacional:
    - `app/Support/Dashboard/DashboardQueries.php`
    - `app/Models/Agreement.php`
    - `app/Models/Installment.php`
  - Livewire e comportamento de tela:
    - `app/Livewire/Dashboard/Foundation.php`
    - `app/Livewire/Agreements/Show.php`
    - views associadas em `resources/views/livewire/...`
  - documentação:
    - `docs/PRD.md`
    - `docs/current-state.md`
    - `docs/architecture.md`
    - `docs/database.md`
    - `docs/acceptance-tests.md`
  - testes, fixtures e seeders:
    - `tests/Feature/*`
    - `database/factories/*`
    - `database/seeders/*`
- Rejected alternatives:
  - transformar `agreements.status` em status derivado automático:
    - rejeitado porque conflita com a decisão oficial da estratégia híbrida.
  - manter o catálogo provisório do EP-002 apenas “comentado” na documentação:
    - rejeitado porque preserva inconsistência entre código, testes e semântica de negócio.
  - já introduzir CRUD de mudança de status do acordo:
    - rejeitado porque o detalhe do acordo permanece em leitura operacional nesta fase.
  - incluir paginação e novos filtros específicos por lista:
    - rejeitado porque paginação está fora do escopo e RF013 já foi fechado como filtro global.

## Execution Steps
1. [completed] Formalizar o vocabulário oficial de status e sua taxonomia operacional
   - Changes:
     - substituir no plano e na documentação a linguagem provisória do EP-002 pelo conjunto oficial:
       - `agreements.status`: `com_atraso`, `ativo`, `descumprido`, `finalizado`, `encerrado_sem_quitacao`
       - `installments.status`: `vencido`, `pago_com_atraso`, `pago_em_dia`, `acordo_feito`, `em_dia`
     - distinguir explicitamente o que é:
       - status persistido;
       - estado derivado por data;
       - sugestão ao usuário;
       - filtro de tela.
  - Expected result:
    - o projeto passa a ter um dicionário único e oficial, sem status provisórios espalhados.
  - Verification:
    - `DashboardStatusCatalog.php` atualizado com o vocabulário oficial e taxonomia entre persistência, derivação e filtros;
    - `docs/database.md`, `docs/PRD.md` e `docs/acceptance-tests.md` reconciliados com o vocabulário oficial;
    - referências normativas aos status provisórios do EP-002 removidas dos documentos centrais.

2. [completed] Consolidar a regra híbrida de RF007 sem mutação automática de acordo
   - Changes:
     - revisar `Agreement`, `Installment` e `DashboardQueries` para refletir a regra oficial:
       - `agreements.status` é lido como fonte persistida do acordo;
       - parcelas vencidas podem gerar sinal operacional e sugestão de mudança;
       - a lista de acordos com atraso deve respeitar a decisão oficial do domínio sem promover alteração silenciosa de `agreements.status`;
       - `installments.status` pode ser atualizado conforme a data atual, sem obrigar transição automática do acordo.
  - Expected result:
    - RF007 fica semanticamente consistente e pronto para evolução futura sem duplicação de regra.
  - Verification:
    - `Agreement::suggestedStatus()` e `Agreement::requiresStatusAttention()` introduzidos para leitura operacional;
    - `tests/Feature/DomainRelationshipsTest.php` cobre acordo `ativo` com parcela vencida sem mutação persistida;
    - dashboard e detalhe do acordo passaram a mostrar sugestão sem alterar `agreements.status`.

3. [completed] Reconciliar RF003 a RF013 com os status oficiais e com os estados derivados
   - Changes:
     - ajustar o catálogo operacional e os contratos de query para que:
       - parcelas pagas no mês usem `pago_em_dia` e `pago_com_atraso`;
       - parcelas operacionais usem `em_dia`, `vencido` e `acordo_feito` conforme a regra fechada;
       - buckets excluídos e elegibilidade operacional fiquem explícitos;
       - RF012 mantenha as duas ordenações mínimas: atraso mais antigo e maior valor;
       - RF013 atue sobre todas as listas do dashboard ao mesmo tempo.
  - Expected result:
    - cards, listas, ordenação e filtros passam a operar sobre regras oficiais, não mais sobre aproximações provisórias.
  - Verification:
    - `DashboardQueries.php` passou a usar status oficiais, filtros globais e painel contextual de acordos;
    - `tests/Feature/DashboardTest.php` cobre filtros rápidos globais, ordenação mínima e leitura derivada de status.

4. [completed] Consolidar o detalhe do acordo como leitura operacional orientada a status
   - Changes:
     - alinhar `App\Livewire\Agreements\Show` e sua view para expor:
       - status persistido do acordo;
       - status das parcelas;
       - estados derivados relevantes por data;
       - sinalização de sugestão ao usuário quando houver divergência operacional;
       - ausência de ação de edição/CRUD nesta fase.
  - Expected result:
    - o detalhe do acordo se torna a referência de leitura operacional do domínio, sem abrir escopo de manutenção.
  - Verification:
    - `App\Livewire\Agreements\Show` e sua view exibem status persistido, estado derivado e sugestão operacional;
    - `tests/Feature/AgreementShowTest.php` valida a leitura operacional do detalhe do acordo.

5. [completed] Atualizar fixtures, seeds e testes para o vocabulário final
   - Changes:
     - revisar factories, seeders e testes para remover dependência dos nomes provisórios do EP-002;
     - garantir cenários mínimos para:
       - acordo `ativo` com parcelas `em_dia`;
       - parcela `vencido`;
       - parcela `pago_em_dia`;
       - parcela `pago_com_atraso`;
       - acordo `com_atraso`;
       - acordo `descumprido`;
       - acordo `finalizado`;
       - acordo `encerrado_sem_quitacao`.
  - Expected result:
    - a base de teste fica coerente com a regra oficial do domínio e pronta para próximos incrementos.
  - Verification:
    - `database/factories/InstallmentFactory.php` e `database/seeders/DashboardDemoSeeder.php` reconciliados com o vocabulário final;
    - `php artisan migrate:fresh --seed` executado com sucesso;
    - `php artisan test` executado com 40 testes passando.

6. [completed] Reconciliar a documentação funcional e técnica com a regra oficial de domínio
   - Changes:
     - atualizar PRD, arquitetura, banco, current-state e critérios de aceite para refletir:
       - estratégia híbrida oficial de RF007;
       - status aceitos;
       - diferença entre persistência, derivação, sugestão e ação manual;
       - RF012 com ambas as ordenações;
       - RF013 com efeito global;
       - paginação explicitamente fora deste EP.
  - Expected result:
    - documentação e código passam a falar a mesma linguagem operacional.
  - Verification:
    - `docs/PRD.md`, `docs/current-state.md`, `docs/architecture.md`, `docs/database.md` e `docs/acceptance-tests.md` atualizados;
    - notas do EP-002 como baseline vigente foram substituídas pela regra oficial do EP-003.

## Validation Gates
- [x] O projeto possui um dicionário oficial único para `agreements.status` e `installments.status`.
- [x] `agreements.status` continua persistido e não sofre mutação automática derivada de parcelas.
- [x] Estados derivados por data ficam explícitos e separados dos status persistidos.
- [x] Sugestões de mudança ao usuário ficam documentadas e refletidas na leitura operacional sem abrir CRUD.
- [x] RF007, RF008, RF012 e RF013 ficam alinhados à regra oficial de domínio.
- [x] O dashboard aplica filtros rápidos globalmente a todas as listas relevantes.
- [x] O detalhe do acordo permanece em leitura operacional e expõe corretamente status, estados derivados e sugestões.
- [x] Seeds, factories e testes deixam de depender do vocabulário provisório do EP-002.
- [x] `php artisan migrate:fresh --seed` passa.
- [x] `php artisan test` passa.
- [x] Nenhuma violação de stack ou ampliação indevida de escopo foi introduzida.
- [x] Paginação, CSV completo e CRUD do acordo permanecem fora deste EP.

## Risks and Mitigations
- Risk: confundir status persistido com estado derivado e recriar a ambiguidade que o EP-003 pretende eliminar.
  - Mitigation: documentar e testar separadamente persistência, derivação, sugestão e filtro.
- Risk: manter referências residuais aos nomes provisórios do EP-002 em testes, seeders ou views.
  - Mitigation: usar o EP-003 para revisão transversal de código, fixtures, testes e documentação.
- Risk: a regra híbrida de RF007 ficar parcialmente implementada e gerar comportamento contraditório entre dashboard e detalhe do acordo.
  - Mitigation: validar os dois fluxos com cenários específicos de acordo ativo com parcela vencida.
- Risk: o esforço de fechamento de domínio puxar CRUD, paginação ou automação de atualização de status.
  - Mitigation: manter como limite explícito que este EP só consolida leitura operacional e especificação executável.
- Risk: `finalizado` e `encerrado_sem_quitacao` permanecerem semanticamente próximos demais para uso consistente.
  - Mitigation: registrar definição operacional mínima em docs e testes, deixando apenas nuances realmente não decididas em `Open Questions`.

## Open Questions
1. `installments.status` deve ser recalculado apenas em leitura, persistido por rotina/scheduler, ou admitir ambas as estratégias desde que a semântica final permaneça a mesma?
2. A sugestão ao usuário para mudança de `agreements.status` deve aparecer no dashboard, no detalhe do acordo, ou nos dois pontos já neste EP?
3. `acordo_feito` em `installments.status` representa parcela renegociada, suspensa operacionalmente, ou outro estado específico que ainda precisa de definição textual mais precisa?
4. `finalizado` deve significar acordo integralmente quitado, encerrado manualmente, ou ambos desde que sem saldo operacional?

## Change Log
- 2026-04-23 17:00 - Plano criado a partir do estado real pós EP-001 e EP-002 e das decisões oficiais de fechamento de domínio para status e regras operacionais.
- 2026-04-23 18:05 - EP-003 concluído com vocabulário oficial de status, regra híbrida de RF007, filtros globais do dashboard, detalhe do acordo em leitura operacional e documentação/testes reconciliados.
