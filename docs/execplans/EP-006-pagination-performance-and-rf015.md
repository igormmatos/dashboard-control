# ExecPlan: EP-006 Pagination, Performance and RF015

Last updated: 2026-04-24

## Objective
- In scope:
  - implementar paginação real nas listas operacionais do dashboard;
  - garantir compatibilidade entre paginação, filtros rápidos globais, busca, período por `due_date` e ordenação;
  - revisar desempenho das queries do dashboard e reduzir consultas desnecessárias sem alterar regras de negócio;
  - definir e implementar RF015 no nível necessário para que a virada operacional para atraso seja refletida de forma consistente;
  - preservar a estratégia híbrida já consolidada:
    - `agreements.status` permanece persistido;
    - `agreements.status` não muda automaticamente;
    - `installments` e leituras operacionais por data podem refletir atraso sem ação manual da usuária;
  - manter CSV como exportação do conjunto filtrado completo, independente da página visual atual;
  - alinhar testes, fixtures/seeds mínimas, documentação e `docs/rf-status.md` ao final da execução futura.
- Out of scope:
  - transformar `agreements.status` em status automático;
  - criar automação que altere `agreements.status` sem ação explícita da usuária;
  - alterar regras de status já consolidadas nos EPs 003 e 004;
  - mudar o contrato de RF014 para exportar apenas a página visível;
  - implementar ranking, gráfico, BI avançado, CRUD amplo ou novos módulos fora do dashboard;
  - ampliar autorização por perfil além do padrão já existente, salvo se a execução descobrir regra consolidada no código;
  - reescrever o dashboard ou substituir Livewire + Tailwind.

## Inputs and Evidence
- Reviewed:
  - `.agent/AGENTS.md`
  - `.agent/PLANS.md`
  - `docs/PRD.md`
  - `docs/rf-status.md`
  - `docs/current-state.md`
  - `docs/architecture.md`
  - `docs/database.md`
  - `docs/acceptance-tests.md`
  - `docs/execplans/EP-003-status-and-operational-rules.md`
  - `docs/execplans/EP-004-agreement-detail-and-manual-status.md`
  - `docs/execplans/EP-005-csv-export-and-audit.md`
  - `app/Livewire/Dashboard/Foundation.php`
  - `app/Support/Dashboard/DashboardQueries.php`
  - `app/Models/Agreement.php`
  - `app/Models/Installment.php`
  - `tests/Feature/DashboardTest.php`
- Facts:
  - a baseline técnica validada é Laravel 13.6.0 + MySQL + monólito web;
  - a UI principal do dashboard é Livewire + Tailwind;
  - `GET /dashboard` usa `App\Livewire\Dashboard\Foundation`;
  - `Dashboard\Foundation` mantém estado da tela em `search`, `selectedMonth`, `quickFilter` e `sortBy`;
  - `DashboardQueries` centraliza cards, contagens, listas operacionais, painel de acordos, acordos descumpridos e queries exportáveis;
  - as listas visuais ainda usam limites fixos em memória ou query (`take(15)`, `limit(10)`), portanto paginação real ainda não foi entregue;
  - RF014 está implementado por `dashboard.export`, `DashboardCsvExporter` e métodos exportáveis em `DashboardQueries`;
  - o CSV exporta o conjunto filtrado completo e não deve passar a depender da página visual;
  - `docs/rf-status.md` marca RF015 como `parcialmente implementado`;
  - o comportamento atual já reflete atraso em leitura por `due_date` e `now()`, mas a decisão final de RF015 ainda está aberta;
  - `Agreement::suggestedStatus()`, `Agreement::requiresStatusAttention()` e `Agreement::scopeWithDelaySignals()` implementam a estratégia híbrida sem mutar `agreements.status`;
  - `Installment::derivedStatus()` já calcula `vencido`, `pago_com_atraso`, `pago_em_dia`, `acordo_feito` e `em_dia` por data e pagamento;
  - `Installment::scopeOverdue()` e `Installment::scopeDueSoon()` usam data atual para listas operacionais;
  - `docs/acceptance-tests.md` possui AT-015 para virada automática refletida e AT-NF-005 para desempenho;
  - `docs/database.md` recomenda índices em `installments.due_date`, `installments.status`, `installments.is_active` e composto operacional.
  - o EP-006 implementou paginação real com paginadores Livewire nomeados para parcelas, painel de acordos e acordos descumpridos;
  - o EP-006 fechou RF015 por derivação operacional em queries/UI, sem command/scheduler e sem mutação automática de `agreements.status`;
  - a inspeção do schema confirmou índice composto existente em `installments(is_active, status, due_date)` e índices de FKs relevantes.
- Assumptions:
  - a paginação foi implementada no próprio componente Livewire do dashboard com paginadores nomeados;
  - a granularidade adotada é independente por lista operacional: parcelas, painel de acordos e acordos descumpridos;
  - RF015 foi fechado como comportamento de leitura operacional dinâmica por consulta/data atual, com testes provando a virada sem ação manual e sem mutação de `agreements.status`;
  - a revisão de desempenho deve começar por eliminar `get()->take()` desnecessário, aplicar paginação no banco, confirmar eager loading e revisar índices antes de criar infraestrutura pesada.
- Unknowns:
  - qual limite objetivo de volume local deve ser usado como benchmark mínimo para AT-NF-005.

## Implementation Strategy
- Approach:
  - tratar EP-006 como fechamento de escalabilidade operacional do dashboard, não como expansão funcional;
  - separar claramente três contratos:
    - visual: listas paginadas para navegação diária;
    - exportação: CSV continua usando queries exportáveis completas sem paginação visual;
    - RF015: atraso operacional refletido por data atual sem mutação automática de `agreements.status`;
  - substituir limites fixos por paginação real no banco, preferindo `paginate()`/`simplePaginate()` conforme suporte do Livewire e necessidade de contagem total;
  - centralizar builders reutilizáveis em `DashboardQueries` para evitar duplicação entre lista visual, contagens e exportação;
  - medir ou inspecionar queries antes de adicionar índices, usando schema real como fonte de decisão;
  - fechar RF015 com testes de tempo usando `Carbon::setTestNow()` para provar a virada de parcela não vencida para vencida entre dois dias diferentes.
  - decisão executada: usar `paginate()` com contagem total, 10 parcelas por página e 5 acordos por página nas listas laterais.
- Mapping to repository artifacts:
  - Livewire:
    - `app/Livewire/Dashboard/Foundation.php` para usar paginação Livewire, resetar página ao mudar `search`, `selectedMonth`, `quickFilter` e `sortBy`, e expor dados paginados à view;
    - avaliar trait de paginação compatível com Livewire 4 antes da implementação;
    - `resources/views/livewire/dashboard/foundation.blade.php` para renderizar controles de paginação por lista sem introduzir paginação global fora do dashboard.
  - queries:
    - `app/Support/Dashboard/DashboardQueries.php` para trocar métodos visuais de `Collection` limitada por paginator;
    - extrair builders internos reutilizáveis para parcelas, acordos com atraso e acordos descumpridos;
    - manter `exportInstallments()`, `exportDelayAgreements()` e `exportBreachedAgreements()` sem limite visual.
  - domínio/RF015:
    - `app/Models/Installment.php` para garantir que scopes e `derivedStatus()` permaneçam equivalentes;
    - `app/Models/Agreement.php` para manter sugestão operacional por atraso sem mutação automática;
    - possível command/scheduler somente se a execução decidir que RF015 exige persistência de `installments.status`, nunca de `agreements.status`.
  - banco/desempenho:
    - migrations existentes para confirmar índices atuais;
    - possível migration de índices em `installments` e `agreements` se a inspeção mostrar lacuna objetiva;
    - `docs/database.md` para registrar índices realmente adicionados ou decisão de não alterar schema.
  - CSV:
    - `app/Support/Dashboard/DashboardCsvExporter.php` e `DashboardQueries` devem continuar exportando datasets completos;
    - `tests/Feature/DashboardExportTest.php` deve ganhar ou manter teste garantindo que paginação visual não limita CSV.
  - testes:
    - `tests/Feature/DashboardTest.php` para paginação, filtros, busca, ordenação e reset de página;
    - `tests/Feature/DashboardExportTest.php` para regressão de CSV completo;
    - teste específico de RF015, em `DashboardTest.php` ou novo arquivo focado, usando mudança de `Carbon::setTestNow()`;
    - factories/seeders em `database/factories/*` e `database/seeders/DashboardDemoSeeder.php` somente se necessários para volume/manual QA.
  - documentação:
    - `docs/acceptance-tests.md`;
    - `docs/current-state.md`;
    - `docs/database.md` se houver índices ou decisão formal sobre RF015 no banco;
    - `docs/rf-status.md` obrigatoriamente ao final da execução futura;
    - este ExecPlan deve ser atualizado com progresso, descobertas e validações reais.
- Rejected alternatives:
  - manter `limit()`/`take()` e chamar isso de paginação:
    - rejeitado porque o PRD exige listas com paginação ou limite por página, e o contexto atual pede paginação real.
  - paginar a exportação CSV junto com a tela:
    - rejeitado porque RF014 já foi fechado como exportação do conjunto filtrado completo.
  - alterar automaticamente `agreements.status` quando parcela vence:
    - rejeitado porque viola a estratégia híbrida consolidada.
  - implementar ranking ou gráfico durante revisão de desempenho:
    - rejeitado porque RF016 e RF017 continuam fora do escopo.
  - criar cache agressivo antes de medir queries e índices:
    - rejeitado porque pode esconder inconsistências de filtros e complicar RF015 por data atual.

## Execution Steps
1. [completed] Confirmar contratos atuais de paginação, filtros e RF015 antes de editar
   - Changes:
     - revisar `Dashboard\Foundation`, `DashboardQueries`, `DashboardCsvExporter`, view do dashboard e testes atuais;
     - usar documentação versionada do Laravel/Livewire via Boost antes de alterar código de paginação;
     - confirmar se o schema real já possui índices em `installments` e relações usadas pelo dashboard.
   - Expected result:
     - a execução começa com contratos claros para página visual, CSV completo e RF015.
   - Verification:
     - `php artisan route:list --path=dashboard --except-vendor`;
     - inspeção de migrations/schema;
     - notas de descoberta registradas neste ExecPlan.

2. [completed] Definir o contrato de paginação visual do dashboard
   - Changes:
     - decidir tamanho fixo inicial por lista, por exemplo 10 ou 15 itens por página, conforme aderência à UI atual;
     - decidir se as listas terão página independente ou se apenas a lista operacional ativa será paginada;
     - definir reset de página quando mudar `search`, `selectedMonth`, `quickFilter` ou `sortBy`;
     - documentar que filtros globais continuam atuando em todas as listas.
   - Expected result:
     - comportamento de navegação fica previsível e testável, sem ambiguidades de estado.
   - Verification:
     - testes Livewire conseguem reproduzir troca de filtro/busca/ordenação e confirmar retorno à primeira página;
     - plano atualizado se a decisão diferir da hipótese inicial.

3. [completed] Refatorar queries visuais para paginação real sem quebrar exportação
   - Changes:
     - alterar métodos visuais em `DashboardQueries` para retornarem paginadores ou estrutura equivalente;
     - remover `get()->take()` e substituir limites visuais por paginação no banco;
     - preservar métodos exportáveis sem limite visual;
     - manter eager loading de `client`, `worker`, `process` e relações necessárias para evitar N+1.
   - Expected result:
     - a tela passa a carregar páginas reais das listas operacionais, enquanto CSV continua completo.
   - Verification:
     - teste com volume acima do tamanho da página confirma itens na página 1 e página 2;
     - teste de CSV confirma que exportação contém todos os filtrados, não só a página atual.

4. [completed] Implementar controles de paginação na UI Livewire
   - Changes:
     - atualizar `app/Livewire/Dashboard/Foundation.php` com suporte de paginação Livewire;
     - atualizar `resources/views/livewire/dashboard/foundation.blade.php` para renderizar links/controles de paginação acessíveis e responsivos;
     - garantir que os botões de exportação continuem enviando filtros, período e ordenação, mas não enviem página visual como limitador de dataset.
   - Expected result:
     - usuária consegue navegar por páginas das listas no dashboard mantendo filtros e ordenação ativos.
   - Verification:
     - testes Livewire validam navegação de página e manutenção do estado;
     - smoke manual opcional no dashboard autenticado.

5. [completed] Fechar RF015 como regra operacional verificável
   - Changes:
     - definir se RF015 será implementado somente por leitura dinâmica ou por leitura dinâmica mais rotina de persistência de `installments.status`;
     - se for leitura dinâmica:
       - consolidar `derivedStatus()`, `overdue()` e painéis como fonte operacional;
       - documentar que RF015 não requer mutation automática em banco para `agreements.status`;
       - garantir que a UI mostra a virada no próximo render/request conforme `now()`.
     - se houver rotina:
       - criar command/scheduler mínimo apenas para `installments.status`, com idempotência;
       - provar que `agreements.status` não é alterado pela rotina;
       - manter a UI baseada na mesma semântica da leitura derivada.
   - Expected result:
     - RF015 deixa de ser aberto/parcial e passa a ter contrato final suficiente para operação diária.
   - Verification:
     - teste com `Carbon::setTestNow()` em dois dias confirma que uma parcela passa de `em_dia` para atraso operacional sem ação manual;
     - teste confirma que `agreements.status` permanece igual;
     - se houver command, teste de command confirma idempotência e ausência de mutação em acordos.

6. [completed] Revisar desempenho das queries do dashboard
   - Changes:
     - identificar queries repetidas, contagens pesadas e oportunidades de reutilizar builders sem materializar coleções grandes;
     - confirmar eager loading nas listas paginadas e exportáveis;
     - avaliar índices atuais para filtros por `due_date`, `status`, `is_active`, `agreement_id` e status de acordos;
     - adicionar migration de índice somente com evidência objetiva de lacuna.
   - Expected result:
     - dashboard usa paginação no banco e consultas compatíveis com crescimento moderado de dados.
   - Verification:
     - testes continuam passando;
     - schema/documentação refletem índices novos se criados;
     - registrar neste ExecPlan qualquer decisão de não adicionar índice.

7. [completed] Consolidar fixtures, seeds e testes de regressão
   - Changes:
     - criar cenários com mais registros que o tamanho da página;
     - cobrir busca, filtro rápido, mês selecionado e ordenação com paginação;
     - cobrir RF015 por virada de data;
     - cobrir regressão RF014 para CSV completo;
     - ajustar `DashboardDemoSeeder` apenas se útil para validação manual de paginação.
   - Expected result:
     - o comportamento novo fica verificável sem depender de dados reais externos.
   - Verification:
     - `php artisan test --compact tests/Feature/DashboardTest.php`;
     - `php artisan test --compact tests/Feature/DashboardExportTest.php`;
     - teste focado de RF015 se criado em arquivo separado.

8. [completed] Atualizar documentação e controle de RFs
   - Changes:
     - atualizar `docs/acceptance-tests.md` com critérios observáveis de paginação e RF015;
     - atualizar `docs/current-state.md` com paginação real e decisão final de RF015;
     - atualizar `docs/database.md` se houver novos índices ou decisão de scheduler/command;
     - revisar `docs/rf-status.md`:
       - marcar RF015 como `implementado` somente se houver evidência objetiva em código/testes;
       - manter `parcialmente implementado` se a decisão final ainda não for suficiente;
       - não alterar RF016/RF017 salvo para manter pendência explícita.
     - atualizar este ExecPlan com progresso, descobertas, decisões e resultados reais.
   - Expected result:
     - documentação e controle oficial de RFs refletem o estado real após a execução futura.
   - Verification:
     - revisão textual dos documentos alterados;
     - `docs/rf-status.md` contém evidência objetiva para qualquer mudança de status de RF.

9. [completed] Executar validação final do EP-006
   - Changes:
     - rodar formatador se houver PHP alterado;
     - rodar testes focados de dashboard, exportação e RF015;
     - rodar suíte completa se queries, models, migrations ou scheduler/command forem alterados.
   - Expected result:
     - paginação, desempenho e RF015 ficam entregues sem regressões conhecidas em RF014 e regras de status.
   - Verification:
     - `vendor/bin/pint --dirty --format agent`;
     - `php artisan route:list --path=dashboard --except-vendor`;
     - `php artisan test --compact tests/Feature/DashboardTest.php`;
     - `php artisan test --compact tests/Feature/DashboardExportTest.php`;
     - teste focado de RF015, se separado;
     - `php artisan test --compact`;
     - se houver migration: `php artisan migrate:fresh --seed`.

## Validation Gates
- [x] Listas operacionais do dashboard usam paginação real, não apenas `limit()` fixo.
- [x] Paginação é compatível com busca rápida por cliente, trabalhador e processo.
- [x] Paginação é compatível com filtros rápidos globais `a_vencer`, `em_atraso` e `pagas_mes`.
- [x] Paginação é compatível com mês selecionado por `installments.due_date`.
- [x] Paginação é compatível com ordenação por atraso mais antigo e maior valor.
- [x] Troca de busca, filtro, mês ou ordenação reseta ou normaliza a página atual de forma previsível.
- [x] Exportação CSV continua exportando o conjunto filtrado completo, não a página visual.
- [x] CSV continua auditado em `audit_logs.context` sem alteração de contrato de RF014.
- [x] RF015 tem decisão final registrada: leitura dinâmica por `due_date`, `paid_date` e data atual.
- [x] RF015 mostra virada para atraso sem ação manual da usuária.
- [x] RF015 não altera automaticamente `agreements.status`.
- [x] Estratégia híbrida de EP-003 e EP-004 permanece íntegra.
- [x] Queries do dashboard usam eager loading suficiente para evitar N+1 nas listas paginadas.
- [x] Índices necessários são confirmados; nenhuma migration adicional foi criada por falta de lacuna objetiva.
- [x] Testes automatizados cobrem paginação, filtros, busca, ordenação, CSV completo e RF015.
- [x] `docs/rf-status.md` é revisado e atualizado com evidência objetiva para RF015.
- [x] Nenhum ranking, gráfico, BI avançado, CRUD amplo ou exportação fora do dashboard é introduzido.
- [x] Stack permanece Laravel + Livewire + Tailwind + MySQL, sem React/Vue/Svelte no runtime alvo.

## Validation Results
- `vendor/bin/pint --dirty --format agent` - passou; ajustou apenas posição de chaves em `app/Support/Dashboard/DashboardQueries.php`.
- `php artisan route:list --path=dashboard --except-vendor` - passou; confirmou `dashboard` e `dashboard.export`.
- `php artisan test --compact tests/Feature/DashboardTest.php` - passou com 8 testes e 45 assertions.
- `php artisan test --compact tests/Feature/DashboardExportTest.php` - passou com 6 testes e 62 assertions.
- `php artisan test --compact --filter=Dashboard` - passou com 14 testes e 107 assertions.
- `php artisan test --compact` - passou com 51 testes e 223 assertions.
- `php artisan migrate:fresh --seed` não foi executado no fechamento porque o EP-006 não criou migration; a inspeção do schema confirmou índices operacionais já existentes.

## Risks and Mitigations
- Risk: paginação visual quebrar o contrato de CSV completo.
  - Mitigation: manter métodos exportáveis separados dos métodos paginados e criar teste de regressão com volume acima do tamanho da página.
- Risk: múltiplas listas paginadas competirem pelo mesmo parâmetro de página no Livewire.
  - Mitigation: definir nomes de página independentes ou restringir paginação à seção ativa, e testar troca de páginas/filtros.
- Risk: RF015 ser interpretado como autorização para mutar `agreements.status`.
  - Mitigation: registrar no plano e em testes que `agreements.status` não muda automaticamente; qualquer rotina, se existir, limita-se a `installments.status`.
- Risk: leitura dinâmica por `now()` divergir de status persistido de parcela.
  - Mitigation: manter `derivedStatus()` como fonte de exibição operacional e testar equivalência dos scopes críticos.
- Risk: adicionar índices sem evidência e aumentar complexidade de migrations.
  - Mitigation: inspecionar schema real e priorizar índices recomendados em `docs/database.md` somente quando ausentes e relevantes.
- Risk: paginação introduzir regressão em filtros globais de RF013.
  - Mitigation: cobrir busca, mês, quick filter e sort no mesmo teste de paginação.
- Risk: desempenho ser tratado de forma subjetiva.
  - Mitigation: definir cenário mínimo de volume em fixture/teste e registrar validações objetivas possíveis no ambiente local.
- Risk: UI de paginação ficar pesada em mobile.
  - Mitigation: usar controles compactos e responsivos já compatíveis com Livewire/Tailwind, preservando a prioridade mobile do PRD.

## Open Questions
1. Qual volume mínimo de dados será aceito como referência local para validar AT-NF-005 em benchmark futuro?
2. O tamanho de página fixo, hoje 10 para parcelas e 5 para acordos, deve se tornar configurável em uma fase futura?

## Change Log
- 2026-04-24 00:00 - Plano criado para fechar paginação real do dashboard, revisão de desempenho e decisão/implementação final de RF015, preservando CSV completo e a estratégia híbrida de status.
- 2026-04-24 09:58 - EP-006 executado: paginação real com paginadores Livewire nomeados, RF015 fechado por derivação operacional em queries/UI, CSV completo preservado e documentação/RF status atualizados. Decisão: sem command/scheduler e sem nova migration de índice.
- 2026-04-24 09:58 - Validação final concluída com Pint, rota do dashboard, testes focados de dashboard/exportação, filtro `Dashboard` e suíte completa passando.
