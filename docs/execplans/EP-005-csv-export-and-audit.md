# ExecPlan: EP-005 CSV Export and Audit

Last updated: 2026-04-24

## Objective
- In scope:
  - implementar RF014 no nível operacional completo para o dashboard;
  - exportar CSV das listas operacionais do dashboard respeitando exatamente filtros ativos, busca, ordenação e período quando aplicável;
  - suportar exportação das listas:
    - parcelas vencendo em até 3 dias;
    - parcelas em atraso;
    - parcelas pagas no mês selecionado;
    - acordos com atraso;
    - acordos descumpridos;
  - registrar auditoria obrigatória da exportação em `audit_logs.context`;
  - cobrir comportamento com testes automatizados, fixtures mínimas e documentação;
  - revisar e atualizar `docs/rf-status.md` ao final da futura execução para refletir RF014 com evidência objetiva.
- Out of scope:
  - paginação global do dashboard;
  - BI avançado, gráficos e ranking;
  - exportações fora do dashboard;
  - CRUD amplo de acordo, cliente, trabalhador, processo ou parcelas;
  - alteração de regras de status já consolidadas;
  - automação ou mutação de `agreements.status`;
  - RF015, exceto preservação explícita de que exportar não altera status.

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
  - `docs/execplans/EP-002-dashboard-core.md`
  - `docs/execplans/EP-003-status-and-operational-rules.md`
  - `docs/execplans/EP-004-agreement-detail-and-manual-status.md`
  - `routes/web.php`
  - `app/Livewire/Dashboard/Foundation.php`
  - `app/Support/Dashboard/DashboardQueries.php`
  - `app/Support/Dashboard/DashboardStatusCatalog.php`
  - `app/Models/Agreement.php`
  - `app/Models/Installment.php`
  - `app/Models/AuditLog.php`
  - `resources/views/livewire/dashboard/foundation.blade.php`
  - `tests/Feature/DashboardTest.php`
- Facts:
  - a baseline técnica validada é Laravel 13.6.0 + MySQL + aplicação monolítica;
  - a UI principal do dashboard é Livewire + Tailwind;
  - `GET /dashboard` usa `App\Livewire\Dashboard\Foundation`;
  - `Dashboard\Foundation` mantém estado de tela em `search`, `selectedMonth`, `quickFilter` e `sortBy`;
  - `DashboardQueries` centraliza cards, contagens, listas operacionais, painel de acordos e acordos descumpridos;
  - as listas visuais atuais usam limites internos (`limit(15)` e `limit(10)`), mas não há paginação global;
  - RF014 está `pendente` em `docs/rf-status.md`;
  - `audit_logs.context` já existe e é usado para auditoria estruturada no EP-004;
  - `AuditLog` já possui cast `context => array`;
  - `agreements.status` é persistido e não pode mudar automaticamente por exportação;
  - `Installment::derivedStatus()` calcula estado operacional por data para leitura;
  - o dashboard já implementa filtros rápidos globais `a_vencer`, `em_atraso` e `pagas_mes`;
  - exportação CSV exige auditoria no PRD e em `docs/database.md`;
  - `docs/acceptance-tests.md` já possui AT-014 e AT-NF-002 para CSV e auditoria.
  - o EP-005 implementou a rota `dashboard.export` para CSV autenticado;
  - o EP-005 adicionou `DashboardCsvExporter` e métodos exportáveis em `DashboardQueries`;
  - o CSV usa separador `;` e valores monetários normalizados com ponto decimal;
  - a auditoria de exportação usa `audit_logs.context` com `row_count`, filtros, período, arquivo e metadados.
- Assumptions:
  - a exportação será acionada a partir do dashboard autenticado e deve reutilizar o estado Livewire da tela quando a ação partir do componente;
  - o CSV deve exportar o conjunto filtrado completo da lista selecionada, não apenas o limite visual atual, porque não há paginação neste EP;
  - a ordenação exportada deve seguir a ordenação ativa quando a lista tiver ordenação configurável;
  - quando o período mensal não participa da regra atual de uma lista, ele ainda deve ser registrado em auditoria, mas não deve alterar a regra de dados para evitar divergência com a tela;
  - o formato CSV deve ser simples, compatível com Excel/LibreOffice, e usar cabeçalhos estáveis em português.
- Unknowns:
  - se usuários com perfil `advogada` terão a mesma permissão de exportação que `admin`.

## Implementation Strategy
- Approach:
  - manter a exportação dentro do módulo `Dashboard`, sem criar exportações genéricas fora do escopo;
  - extrair as consultas exportáveis de `DashboardQueries` para métodos reutilizáveis sem limites visuais, preservando os métodos atuais para renderização;
  - criar uma superfície dedicada de exportação, preferencialmente um serviço em `app/Support/Dashboard` para montar datasets e CSV, e uma rota/action web protegida por autenticação para download;
  - acionar a exportação a partir de `App\Livewire\Dashboard\Foundation`, repassando o estado atual (`search`, `selectedMonth`, `quickFilter`, `sortBy`, tipo da lista);
  - registrar auditoria antes de retornar o arquivo quando o dataset for materializado em memória; se streaming for necessário, registrar com metadados suficientes e contagem apurada por query anterior;
  - manter o vocabulário oficial de status e as regras híbridas do EP-003 inalteradas;
  - ao finalizar a futura execução, atualizar `docs/rf-status.md` de `RF-014 - pendente` para `RF-014 - implementado` apenas se os testes e a rota/ação de CSV estiverem entregues.
- Mapping to repository artifacts:
  - rotas:
    - `routes/web.php` para adicionar uma rota autenticada de exportação do dashboard, se a ação não puder retornar download diretamente pelo Livewire;
    - nome provável: `dashboard.export`.
  - Livewire:
    - `app/Livewire/Dashboard/Foundation.php` para expor ações de exportação e passar estado atual da tela;
    - `resources/views/livewire/dashboard/foundation.blade.php` para botões de exportação por lista sem criar paginação.
  - queries:
    - `app/Support/Dashboard/DashboardQueries.php` para separar queries exportáveis sem `limit()` dos métodos de renderização;
    - preservar `filteredInstallments()`, `agreementPanel()` e `breachedAgreements()` como fontes visuais atuais;
    - adicionar métodos com nomes explícitos, por exemplo `exportInstallments()`, `exportDelayAgreements()` e `exportBreachedAgreements()`.
  - export service/helper:
    - novo arquivo provável em `app/Support/Dashboard/DashboardCsvExporter.php` ou equivalente;
    - responsabilidade: escolher dataset por `exportType`, formatar linhas, gerar resposta CSV e metadados.
  - auditoria:
    - `app/Models/AuditLog.php` deve ser reutilizado;
    - `audit_logs.context` deve registrar filtros, período, lista, sort, quantidade de linhas e origem `dashboard_export`;
    - nenhuma migration é esperada, salvo descoberta contrária na execução.
  - testes:
    - `tests/Feature/DashboardExportTest.php` para download, conteúdo CSV, filtros, período e auditoria;
    - `tests/Feature/DashboardTest.php` apenas se precisar cobrir botões/ações Livewire na tela;
    - factories e seeders existentes em `database/factories/*` e `database/seeders/DashboardDemoSeeder.php`.
  - documentação:
    - `docs/acceptance-tests.md`;
    - `docs/current-state.md`;
    - `docs/database.md` se o formato de `audit_logs.context` de exportação for formalizado;
    - `docs/rf-status.md` obrigatoriamente ao fim da implementação futura;
    - este ExecPlan deve ser atualizado com progresso e validações reais.
- Rejected alternatives:
  - usar o CSV local do MVP React como runtime:
    - rejeitado porque o runtime alvo é Laravel + Livewire, e o MVP React é apenas referência.
  - exportar tudo do banco ignorando filtros ativos:
    - rejeitado porque RF014 exige respeitar filtros e período ativos.
  - exportar somente linhas visíveis limitadas por `limit(10/15)`:
    - rejeitado porque não há paginação neste EP; o CSV deve refletir o conjunto filtrado completo da lista selecionada.
  - criar um módulo genérico de BI/exportação para todo o sistema:
    - rejeitado por ampliar escopo além do dashboard.
  - alterar `agreements.status` durante exportação:
    - rejeitado porque viola a regra consolidada de status persistido.

## Execution Steps
1. [completed] Confirmar contratos atuais de filtros, período e listas exportáveis
   - Changes:
     - revisar `Dashboard\Foundation`, `DashboardQueries`, view do dashboard e testes atuais;
     - confirmar os tipos de exportação aceitos:
       - `installments_due_soon`;
       - `installments_overdue`;
       - `installments_paid_month`;
       - `agreements_with_delay`;
       - `breached_agreements`;
     - documentar no código ou testes que `selectedMonth` afeta listas mensais (`installments_paid_month` e qualquer visão mensal explícita) e fica auditado nas demais listas sem mudar sua regra.
   - Expected result:
     - a implementação começa com contrato claro de origem dos dados, filtros globais, período por `due_date` e tipo de lista.
   - Verification:
     - revisão dos métodos de query existentes;
     - testes planejados cobrem cada `exportType` e seu escopo de período.

2. [completed] Extrair queries exportáveis sem limites visuais
   - Changes:
     - adicionar métodos em `DashboardQueries` que retornem datasets exportáveis completos, sem `limit(10)` ou `limit(15)`;
     - reutilizar os mesmos scopes atuais: `searchDashboard()`, `dueSoon()`, `overdue()`, `paid()`, `forMonthDueDate()`, `withDelaySignals()` e `breached()`;
     - preservar ordenação ativa para parcelas (`due_date_asc` ou `amount_desc`) e usar ordenação determinística para acordos.
   - Expected result:
     - CSV e tela compartilham regras, mas a tela continua limitada visualmente e o CSV exporta o conjunto filtrado completo.
   - Verification:
     - testes de query ou feature com mais registros que o limite visual provam que a exportação inclui todos os filtrados;
     - testes garantem que filtros excluem registros fora do escopo.

3. [completed] Implementar serviço/helper de CSV do dashboard
   - Changes:
     - criar `app/Support/Dashboard/DashboardCsvExporter.php` ou classe equivalente;
     - definir contrato de entrada com `exportType`, `selectedMonth`, `search`, `quickFilter` quando aplicável e `sortBy`;
     - gerar nome de arquivo previsível, por exemplo `dashboard-<tipo>-YYYY-MM-DD-HHmm.csv`;
     - gerar CSV mesmo quando não houver resultados, mantendo cabeçalho.
   - Expected result:
     - lógica de montagem do CSV fica fora da Blade e fora de métodos Livewire longos.
   - Verification:
     - teste unitário/feature valida cabeçalho, linhas, encoding e resposta para dataset vazio.

4. [completed] Definir formato e colunas mínimas por tipo de lista
   - Changes:
     - para parcelas (`installments_due_soon`, `installments_overdue`, `installments_paid_month`), usar colunas mínimas:
       - `cliente`;
       - `trabalhador`;
       - `processo`;
       - `agreement_id`;
       - `parcela`;
       - `vencimento`;
       - `valor`;
       - `status_persistido`;
       - `estado_operacional`;
       - `data_pagamento`;
       - `is_active`.
     - para acordos com atraso (`agreements_with_delay`), usar colunas mínimas:
       - `cliente`;
       - `trabalhador`;
       - `processo`;
       - `agreement_id`;
       - `valor_total`;
       - `status_persistido`;
       - `status_sugerido`;
       - `requer_atencao_status`;
       - `data_referencia`;
       - `valor_relacionado`.
     - para acordos descumpridos (`breached_agreements`), usar colunas mínimas:
       - `cliente`;
       - `trabalhador`;
       - `processo`;
       - `agreement_id`;
       - `valor_total`;
       - `status_persistido`;
       - `updated_at`.
   - Expected result:
     - consumidores do CSV recebem campos suficientes para reconstruir a lista operacional exportada.
   - Verification:
     - testes assertam cabeçalhos por tipo de exportação e presença/ausência de linhas esperadas.

5. [completed] Adicionar superfície web/Livewire para download autenticado
   - Changes:
     - adicionar botões na view do dashboard para exportar:
       - lista operacional atual de parcelas conforme filtro rápido ativo;
       - painel atual de acordos conforme filtro rápido ativo;
       - acordos descumpridos quando aplicável;
     - adicionar ação Livewire ou rota `dashboard.export` protegida por `auth` e `verified`;
     - validar `exportType`, `selectedMonth`, `quickFilter` e `sortBy` contra valores conhecidos.
   - Expected result:
     - usuária autenticada consegue baixar CSV a partir do dashboard sem sair do escopo da tela.
   - Verification:
     - teste de guest redirecionado para login;
     - teste de usuário autenticado recebendo resposta CSV com headers corretos;
     - teste de `exportType` inválido rejeitado.

6. [completed] Registrar auditoria estruturada da exportação
   - Changes:
     - criar `AuditLog` com:
       - `user_id`;
       - `action`: `dashboard_csv_exported`;
       - `entity_type`: `dashboard`;
       - `entity_id`: `null`;
       - `context.source`: `dashboard_export`;
       - `context.export_type`;
       - `context.selected_month`;
       - `context.period_start`;
       - `context.period_end`;
       - `context.search`;
       - `context.quick_filter`;
       - `context.sort_by`;
       - `context.row_count`;
       - `context.filename`;
       - `context.exported_at`;
       - metadados relevantes como `uses_due_date_period` e `includes_derived_status`.
     - garantir que exportação não muta `agreements.status`.
   - Expected result:
     - toda exportação deixa trilha auditável com ator, data, lista, filtros, período e metadados.
   - Verification:
     - teste confirma criação do `audit_logs` após exportação;
     - teste confirma contexto completo;
     - teste confirma que `agreements.status` não muda antes/depois da exportação.

7. [completed] Consolidar fixtures e cenários de teste do CSV
   - Changes:
     - criar datasets de teste com:
       - parcelas vencendo em até 3 dias;
       - parcelas vencidas;
       - parcelas pagas no mês selecionado;
       - parcelas fora do mês selecionado;
       - acordo `ativo` com parcela vencida e sugestão `com_atraso`;
       - acordo `com_atraso`;
       - acordo `descumprido`;
       - acordo `encerrado_sem_quitacao`;
       - registros que devem ser excluídos por busca, período, status ou `acordo_feito`.
     - atualizar `DashboardDemoSeeder` somente se houver valor para validação manual.
   - Expected result:
     - testes provam que CSV reflete a tela e os filtros sem depender de dados externos.
   - Verification:
     - `php artisan test --compact tests/Feature/DashboardExportTest.php`;
     - datasets cobrem exportação com e sem resultados.

8. [completed] Atualizar documentação e controle de RFs
   - Changes:
     - atualizar `docs/acceptance-tests.md` com critérios específicos de RF014 e auditoria;
     - atualizar `docs/database.md` se o formato de `audit_logs.context` de exportação for formalizado;
     - atualizar `docs/current-state.md` ao final da execução;
     - revisar `docs/rf-status.md` obrigatoriamente:
       - se RF014 estiver implementado e testado, alterar para `RF-014 - implementado - ...` com evidência objetiva;
       - se algo essencial ficar incompleto, manter `pendente` ou `parcialmente implementado` com lacuna explícita.
   - Expected result:
     - documentação e controle oficial de requisitos refletem o estado real, não intenção.
   - Verification:
     - revisão de `docs/rf-status.md` confirmando RF014;
     - ausência de marcação indevida de RF015, RF016 ou RF017.

9. [completed] Executar validação final do EP-005
   - Changes:
     - rodar formatador e testes focados;
     - rodar testes do dashboard se `DashboardQueries` for alterado;
     - rodar suíte completa se a alteração tocar rotas, queries centrais ou auditoria.
   - Expected result:
     - RF014 fica verificável e sem regressão conhecida nas listas já implementadas.
   - Verification:
     - `vendor/bin/pint --dirty --format agent`;
     - `php artisan test --compact tests/Feature/DashboardExportTest.php`;
     - `php artisan test --compact tests/Feature/DashboardTest.php`;
     - `php artisan test --compact --filter=Dashboard`;
     - `php artisan test --compact`.

## Validation Gates
- [x] Exportação CSV existe apenas para listas do dashboard.
- [x] Exportação exige usuário autenticado.
- [x] CSV da lista de parcelas vencendo em até 3 dias usa as mesmas regras de `dueSoon()` da tela.
- [x] CSV da lista de parcelas em atraso usa as mesmas regras de `overdue()` da tela.
- [x] CSV de parcelas pagas no mês usa `selectedMonth` por `due_date`.
- [x] CSV de acordos com atraso usa a regra híbrida oficial sem mutar `agreements.status`.
- [x] CSV de acordos descumpridos inclui somente `descumprido` e `encerrado_sem_quitacao`.
- [x] Busca ativa por cliente, trabalhador ou processo é respeitada.
- [x] Ordenação ativa é respeitada quando aplicável.
- [x] CSV vazio retorna arquivo com cabeçalho e zero linhas de dados.
- [x] Auditoria registra ator, data, tipo de lista, filtros, período, ordenação, quantidade de linhas, nome do arquivo e origem.
- [x] Exportação não altera `agreements.status` nem `installments.status`.
- [x] Nenhuma paginação global é introduzida.
- [x] Nenhuma exportação fora do dashboard é introduzida.
- [x] Nenhuma regra de status consolidada é alterada.
- [x] Testes automatizados cobrem conteúdo CSV, filtros, período, auditoria e dataset vazio.
- [x] `docs/rf-status.md` é revisado ao final e RF014 só é marcado como implementado com evidência objetiva.

## Validation Results
- `vendor/bin/pint --dirty --format agent` - passou; ajustou apenas ordenação de imports em `routes/web.php`.
- `php artisan route:list --path=dashboard --except-vendor` - passou; confirmou `dashboard` e `dashboard.export`.
- `php artisan test --compact tests/Feature/DashboardExportTest.php` - passou com 6 testes e 62 assertions.
- `php artisan test --compact tests/Feature/DashboardTest.php` - passou com 5 testes e 27 assertions.
- `php artisan test --compact --filter=Dashboard` - passou com 11 testes e 89 assertions.
- `php artisan test --compact` - passou com 48 testes e 205 assertions.

## Risks and Mitigations
- Risk: CSV divergir da tela por duplicação de queries.
  - Mitigation: extrair métodos exportáveis em `DashboardQueries` reutilizando os mesmos scopes e contratos do dashboard.
- Risk: exportar apenas o limite visual e não o conjunto filtrado completo.
  - Mitigation: separar métodos de renderização com `limit()` dos métodos de exportação sem limite e testar com volume acima do limite visual.
- Risk: período mensal ser aplicado indevidamente em listas que hoje não usam `selectedMonth`.
  - Mitigation: documentar e testar por tipo de exportação quando `selectedMonth` altera o dataset e quando apenas entra na auditoria.
- Risk: auditoria incompleta não permitir reconstruir o contexto da exportação.
  - Mitigation: definir campos obrigatórios em `audit_logs.context` e assertar o payload em teste.
- Risk: resposta CSV ficar grande demais em memória.
  - Mitigation: manter implementação simples para o volume atual; se o dataset crescer, avaliar stream response sem alterar contrato funcional.
- Risk: locale de CSV causar problemas em Excel brasileiro.
  - Mitigation: registrar separador e formato monetário como decisão de implementação; cobrir cabeçalho e valores em teste.
- Risk: exportação introduzir escopo de BI, ranking ou gráficos.
  - Mitigation: limitar `exportType` às listas do dashboard já existentes e rejeitar tipos não reconhecidos.
- Risk: confundir RF014 com RF015 e alterar status durante exportação.
  - Mitigation: testar explicitamente que exportar não altera `agreements.status`.

## Open Questions
1. Perfis `admin` e `advogada` terão a mesma permissão para exportar CSV em uma fase futura com autorização por perfil?
2. A auditoria deve registrar também o hash do conteúdo exportado em uma evolução futura, ou `row_count` e filtros bastam?

## Change Log
- 2026-04-24 00:00 - Plano criado para entregar RF014 com CSV das listas operacionais do dashboard, auditoria estruturada em `audit_logs.context` e revisão obrigatória de `docs/rf-status.md` ao final da execução futura.
- 2026-04-24 09:35 - EP-005 executado: `dashboard.export`, `DashboardCsvExporter`, queries exportáveis sem limite visual, botões no dashboard, testes de CSV/auditoria, documentação e `docs/rf-status.md` atualizados. Decisões: separador `;`, moeda decimal com ponto, auditoria com `row_count` sem hash, permissão mínima por autenticação existente.
- 2026-04-24 09:35 - Validação final concluída com Pint, rota do dashboard, testes focados de exportação, testes do dashboard, filtro `Dashboard` e suíte completa passando.
