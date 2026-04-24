# ExecPlan: EP-002 Dashboard Core

Last updated: 2026-04-23

## Objective
- In scope:
  - entregar RF002 a RF008 e RF010 a RF013 no nível mínimo operacional;
  - implementar queries e agregações do dashboard com base em `installments.due_date`, atraso e status;
  - criar navegação para `GET /agreements/{id}`;
  - consolidar fixtures, seeders e testes de aceite do dashboard;
  - evoluir o shell atual do dashboard em Livewire para comportamento funcional com dados reais.
- Out of scope:
  - exportação CSV completa de RF014;
  - decisão final e implementação completa de RF015;
  - RF016 e RF017 além do que já existir como shell visual;
  - telas auxiliares fora do dashboard e do detalhe do acordo;
  - transformação do projeto em SPA ou introdução de React/Vue/Svelte.

## Inputs and Evidence
- Reviewed:
  - `.agent/AGENTS.md`
  - `.agent/PLANS.md`
  - `docs/PRD.md`
  - `docs/current-state.md`
  - `docs/ui-mapping.md`
  - `docs/architecture.md`
  - `docs/database.md`
  - `docs/acceptance-tests.md`
  - `docs/execplans/EP-001-foundation.md`
  - `routes/web.php`
  - `app/Livewire/Dashboard/Foundation.php`
  - `resources/views/livewire/dashboard/foundation.blade.php`
  - `app/Models/*.php`
  - `database/migrations/*.php`
  - `tests/Feature/DashboardTest.php`
  - `tests/Feature/DomainRelationshipsTest.php`
- Facts:
  - a baseline técnica validada do repositório está em Laravel 13.6.0 com Livewire + Tailwind e MySQL local;
  - o `EP-001` já entregou scaffold funcional, autenticação base, schema mínimo, shell inicial do dashboard e smoke tests;
  - a rota `GET /dashboard` já aponta para `App\Livewire\Dashboard\Foundation`;
  - o dashboard foi evoluído para usar queries reais sobre `installments` e `agreements`, com foco em `due_date`, atraso e status operacional;
  - já existem models para `Agreement`, `Installment`, `Client`, `Worker`, `Process`, `AuditLog` e `User`;
  - o schema mínimo atual contém tabelas e relacionamentos suficientes para o dashboard operacional e o detalhe mínimo do acordo;
  - o PRD fixa RF002 a RF008 e RF010 a RF013 como must-have;
  - `docs/database.md` confirma `installments` como fonte principal das métricas do dashboard e `due_date` como base do filtro mensal;
  - `docs/ui-mapping.md` confirma que o detalhe correto para RF009 é o detalhe do acordo, não o detalhe do cliente;
  - os dicionários canônicos de status de `agreements` e `installments` ainda não estão definidos documentalmente;
  - o EP-002 centralizou hipóteses provisórias em `App\Support\Dashboard\DashboardStatusCatalog`;
  - o seed operacional mínimo foi consolidado em `DatabaseSeeder` + `DashboardDemoSeeder`;
  - os cenários do dashboard e do detalhe do acordo estão cobertos por testes automatizados.
- Assumptions:
  - o detalhe do acordo de `GET /agreements/{id}` no EP-002 pode ser uma tela mínima de leitura/navegação, desde que satisfaça RF009;
  - até definição formal dos enums, o código deverá concentrar regras de status em um ponto explícito e fácil de revisar;
  - o seed de testes poderá usar valores provisórios de status, desde que esses valores fiquem documentados como hipótese operacional.
- Unknowns:
  - dicionário oficial de `agreements.status`;
  - dicionário oficial de `installments.status`;
  - regra final que separa RF007 (`com_atraso`) entre status persistido e derivação dinâmica;
  - profundidade futura do detalhe do acordo além da leitura operacional mínima;
  - necessidade de paginação real quando o volume de dados crescer.

## Implementation Strategy
- Approach:
  - evoluir o componente Livewire atual do dashboard em vez de substituí-lo por outra abordagem;
  - introduzir queries dedicadas do dashboard sobre `installments` e `agreements`, mantendo a regra de negócio fora da Blade sempre que possível;
  - usar a baseline Laravel 13.6.0 já validada, preservando a direção macro Laravel + MySQL + monólito;
  - transformar o shell atual em uma tela operacional real com filtros, agregados, listas e navegação.
- Mapping to repository artifacts:
  - dashboard principal:
    - `app/Livewire/Dashboard/Foundation.php`
    - `resources/views/livewire/dashboard/foundation.blade.php`
  - rotas e navegação:
    - `routes/web.php`
    - novo componente/rota para `GET /agreements/{id}`
  - domínio e acesso a dados:
    - `app/Models/Agreement.php`
    - `app/Models/Installment.php`
    - possíveis query objects / services em `app/`
  - fixtures e seeds:
    - `database/seeders/*`
    - factories adicionais em `database/factories/*` se necessário
  - testes:
    - `tests/Feature/DashboardTest.php`
    - novos testes de feature e integração para dashboard e detalhe do acordo
    - atualização do critério de aceite descrito em `docs/acceptance-tests.md`
- Rejected alternatives:
  - implementar o dashboard em Blade puro com formulários tradicionais:
    - rejeitado porque a direção vigente de UI é Livewire + Tailwind.
  - migrar partes do MVP React para o runtime final:
    - rejeitado por violar a direção macro do projeto.
  - adivinhar silenciosamente enums definitivos de status:
    - rejeitado porque o repositório ainda não documenta essas regras como fechadas.

## Execution Steps
1. [completed] Consolidar regra operacional de status e recorte temporal
  - Changes:
    - identificar e documentar no próprio código/plano quais hipóteses de status serão usadas provisoriamente para RF004 a RF008 e RF013;
    - definir o contrato mínimo para filtro mensal por `due_date`, atraso e elegibilidade operacional.
  - Expected result:
    - as regras mínimas do dashboard ficam explícitas e centralizadas, sem ambiguidade silenciosa.
  - Verification:
    - regras provisórias centralizadas em `App\Support\Dashboard\DashboardStatusCatalog`;
    - hipóteses refletidas na UI do dashboard, em `docs/acceptance-tests.md` e nos testes de feature.

2. [completed] Implementar agregações do dashboard por `due_date`, atraso e status
  - Changes:
    - adicionar queries/services/scopes para:
      - cards do mês (RF003);
       - valor total em atraso (RF004);
       - lista vencendo em 3 dias (RF005);
       - lista de parcelas em atraso (RF006);
       - lista de acordos com atraso (RF007);
       - lista de acordos descumpridos (RF008).
  - Expected result:
    - o dashboard deixa de ser apenas shell e passa a refletir dados reais do banco.
  - Verification:
    - `App\Support\Dashboard\DashboardQueries` passou a alimentar cards, filtros e listas;
    - testes de feature validam agregados, recorte mensal, listas operacionais e sinais de atraso.

3. [completed] Evoluir a UI do dashboard em Livewire para operação mínima real
  - Changes:
    - ligar o seletor de mês (RF002), busca rápida (RF010), filtros rápidos por status (RF013) e ordenação mínima (RF012);
    - renderizar colunas mínimas das listas (RF011);
    - manter Tailwind + Livewire como abordagem principal e Alpine apenas se houver necessidade pontual justificada.
  - Expected result:
    - usuária autenticada consegue operar o dashboard com dados reais e interações mínimas de produção.
  - Verification:
    - `tests/Feature/DashboardTest.php` cobre renderização, busca, filtros rápidos e ordenação mínima;
    - o componente `App\Livewire\Dashboard\Foundation` renderiza listas e indicadores reais.

4. [completed] Implementar navegação e tela mínima de detalhe do acordo
  - Changes:
    - criar rota `GET /agreements/{id}`;
    - entregar detalhe mínimo de leitura para satisfazer RF009;
    - garantir que listas do dashboard naveguem corretamente para o acordo.
  - Expected result:
    - qualquer item operacional relevante do dashboard abre o detalhe do acordo em até 2 cliques.
  - Verification:
    - rota `agreements.show` criada;
    - `tests/Feature/AgreementShowTest.php` valida o detalhe mínimo do acordo autenticado.

5. [completed] Consolidar fixtures, factories, seeders e cenários de aceite
  - Changes:
    - criar dataset consistente com parcelas ativas/inativas, pagas, a vencer, em atraso e acordos descumpridos;
    - documentar valores provisórios de status usados em teste;
    - alinhar factories/seeders com os cenários do dashboard.
  - Expected result:
    - testes e validações manuais passam a usar uma base de dados reproduzível e suficiente.
  - Verification:
    - factories adicionadas para `Client`, `Worker`, `Process`, `Agreement` e `Installment`;
    - `php artisan migrate:fresh --seed` executado com sucesso;
    - `docs/acceptance-tests.md` atualizado com a hipótese provisória de status usada no EP-002.

6. [completed] Fechar a cobertura de testes e reconciliar o aceite do dashboard
  - Changes:
    - adicionar testes para RF002 a RF008 e RF010 a RF013 no nível mínimo operacional;
    - atualizar `docs/acceptance-tests.md` apenas no que for necessário para refletir a implementação efetiva do EP-002.
  - Expected result:
    - o EP-002 fica objetivamente verificável por teste automatizado e checklist funcional.
  - Verification:
    - `php artisan test` executado com 40 testes passando;
    - `php artisan route:list` confirma `GET /dashboard` e `GET /agreements/{agreement}`.

## Validation Gates
- [x] RF002 funciona por `due_date` com mês/ano selecionado.
- [x] RF003 e RF004 usam agregações reais do banco.
- [x] RF005 e RF006 usam elegibilidade operacional consistente.
- [x] RF007 e RF008 ficam implementados sem esconder ambiguidades de status ainda abertas.
- [x] RF010 a RF013 funcionam no nível mínimo operacional acordado.
- [x] `GET /agreements/{id}` existe e satisfaz RF009 com navegação a partir do dashboard.
- [x] Fixtures e seeders reproduzem os cenários mínimos do dashboard.
- [x] Testes cobrindo dashboard e detalhe do acordo passam com `php artisan test`.
- [x] Nenhuma violação de stack foi introduzida.
- [x] Exportação CSV completa e decisão final de RF015 permanecem fora do escopo, salvo aprovação explícita.

## Risks and Mitigations
- Risk: regras de status insuficientemente definidas contaminarem queries, filtros e testes.
  - Mitigation: centralizar hipóteses provisórias, documentá-las e mantê-las rastreáveis em `Open Questions`.
- Risk: lógica de negócio se espalhar entre Livewire, Blade e Eloquent sem fronteira clara.
  - Mitigation: concentrar agregações e classificação em queries/scopes/services dedicados.
- Risk: o dashboard crescer para além do escopo mínimo operacional.
  - Mitigation: limitar EP-002 aos RF002-RF008 e RF010-RF013, além da navegação mínima para o detalhe do acordo.
- Risk: detalhe do acordo puxar escopo de edição e telas auxiliares.
  - Mitigation: entregar detalhe mínimo de leitura e navegação, deixando edição para plano posterior.
- Risk: seeds e testes dependerem de enums ainda instáveis.
  - Mitigation: usar fixtures explícitas, com nomenclatura provisória documentada e fácil de substituir.
- Risk: performance do dashboard degradar com queries acopladas e pouco seletivas.
  - Mitigation: aproveitar índices já previstos em `docs/database.md` e validar consultas com dados de teste antes de ampliar escopo.

## Open Questions
1. RF007 deve ser atendido por status persistido em `agreements`, por derivação dinâmica a partir de `installments`, ou por uma estratégia híbrida?
2. Quais valores provisórios de `agreements.status` e `installments.status` serão aceitos no EP-002 até a definição canônica?
3. O detalhe do acordo no EP-002 deve expor apenas leitura operacional ou já incluir ações simples de navegação contextual?
4. Para RF012, quais ordenações entram no nível mínimo operacional: atraso mais antigo, maior valor, ou ambas?
5. RF013 deve atuar sobre todas as listas do dashboard de uma vez ou sobre blocos/listas específicos em uma futura iteração?
6. O EP-002 deve já introduzir paginação real nas listas operacionais ou isso pode ficar para o próximo plano se o volume inicial de dados for controlado?

## Change Log
- 2026-04-23 12:00 - Plano criado a partir da baseline validada do EP-001 e da direção reconciliada do projeto.
- 2026-04-23 16:10 - EP-002 concluído com dashboard operacional mínimo em Livewire, detalhe do acordo, seed operacional e testes automatizados passando.
