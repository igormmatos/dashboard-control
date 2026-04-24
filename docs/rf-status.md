# Status dos Requisitos Funcionais

Last updated: 2026-04-24

RF-001 - implementado - autenticação Fortify redireciona login para `/dashboard` via `config/fortify.php`; testes de autenticação validam redirect para `route('dashboard')`.
RF-002 - implementado - componente `App\Livewire\Dashboard\Foundation` expõe `selectedMonth` e `DashboardQueries` filtra por `installments.due_date`; `DashboardTest` cobre recorte mensal.
RF-003 - implementado - `DashboardQueries::cards()` calcula quantidade e valor de parcelas do mês e pagas no mês; `DashboardTest` valida os cards.
RF-004 - implementado - `DashboardQueries::cards()` calcula `Valor total em atraso` com `Installment::overdue()` excluindo pagas, inativas e buckets fora da operação; `DashboardTest` valida o total.
RF-005 - implementado - `DashboardQueries::filteredInstallments()` usa `Installment::dueSoon()` para vencimentos em até 3 dias; `DashboardTest` cobre filtro `a_vencer`.
RF-006 - implementado - `DashboardQueries::filteredInstallments()` usa `Installment::overdue()` para parcelas em atraso; `DashboardTest` cobre filtro operacional de atraso.
RF-007 - implementado - `Agreement::withDelaySignals()`, `Agreement::suggestedStatus()` e `DashboardQueries::overdueAgreementPanel()` implementam estratégia híbrida para acordos com atraso; EP-003 e testes cobrem sugestão sem mutar `agreements.status`.
RF-008 - implementado - `Agreement::scopeBreached()` e `DashboardQueries::breachedAgreements()` listam `descumprido` e `encerrado_sem_quitacao`; EP-002/EP-003 validam o comportamento.
RF-009 - implementado - rota `GET /agreements/{agreement}` aponta para `App\Livewire\Agreements\Show`; EP-004 evoluiu o detalhe com leitura operacional completa e testes em `AgreementShowTest`.
RF-010 - implementado - scopes `searchDashboard()` em `Agreement` e `Installment` buscam por cliente, trabalhador e processo; `DashboardTest` cobre busca por número do processo.
RF-011 - implementado - view `resources/views/livewire/dashboard/foundation.blade.php` renderiza listas com cliente, trabalhador, processo, parcela, vencimento, valor e status; EP-002 registra colunas mínimas entregues.
RF-012 - implementado - `Dashboard\Foundation::$sortBy` e `DashboardQueries::filteredInstallments()` suportam atraso mais antigo e maior valor; `DashboardTest` cobre ordenação por maior valor.
RF-013 - implementado - `Dashboard\Foundation::setQuickFilter()` e `DashboardQueries` aplicam filtros rápidos globais `a_vencer`, `em_atraso` e `pagas_mes`; EP-003 registra efeito global e testes cobrem os filtros.
RF-014 - implementado - rota autenticada `dashboard.export`, `DashboardCsvExporter`, métodos exportáveis em `DashboardQueries` e `DashboardExportTest` entregam CSV filtrado por estado da tela com auditoria em `audit_logs.context`.
RF-015 - implementado - regra oficial fechada por derivação operacional em `Installment::derivedStatus()`, scopes `overdue()`/`dueSoon()` e UI do dashboard; `DashboardTest` cobre virada por data sem mutar `agreements.status`.
RF-016 - pendente - ranking de maior saldo em aberto permanece nice-to-have e fora dos EPs concluídos; não há implementação ou teste específico.
RF-017 - pendente - gráfico mensal simples permanece nice-to-have e fora dos EPs concluídos; não há componente, dataset ou teste de gráfico implementado.
RF-018 - pendente - listagem operacional de clientes com busca, paginação, indicadores, detalhe e CSV foi adicionada ao PRD, mas ainda não há implementação Laravel/Livewire validada.
RF-019 - pendente - detalhe do cliente com perfil, contato, indicadores e vínculos operacionais foi adicionado ao PRD, mas ainda não há rota/componente/teste específico.
RF-020 - pendente - edição de cliente com validação, persistência e auditoria foi adicionada ao PRD, mas ainda não há fluxo implementado.
RF-021 - pendente - criação de cliente isolada e integrada ao novo acordo foi adicionada ao PRD, mas ainda não há fluxo implementado.
RF-022 - pendente - tela operacional dedicada de parcelas foi adicionada ao PRD, mas ainda não há rota/componente/listagem específica fora do dashboard.
RF-023 - pendente - alteração operacional de status de parcela pela tela operacional foi adicionada ao PRD, mas ainda não há ação persistente/auditada implementada.
RF-024 - pendente - detalhe operacional da parcela foi adicionado ao PRD, mas ainda não há rota/componente/teste específico.
RF-025 - pendente - registro de pagamento ou cancelamento de parcela foi adicionado ao PRD, mas ainda não há ação persistente/auditada implementada.
RF-026 - pendente - novo acordo em assistente multietapas foi adicionado ao PRD, mas ainda não há wizard Laravel/Livewire persistente implementado.
RF-027 - pendente - geração automática de parcelas do acordo foi adicionada ao PRD, mas ainda não há serviço/ação validada para gerar cronograma a partir dos parâmetros do acordo.
RF-028 - pendente - leitura contextual de acordos entre dashboard, clientes, parcelas e detalhe foi adicionada ao PRD, mas ainda não há navegação contextual completa implementada.
RF-029 - pendente - financial ledger baseado em parcelas foi adicionado ao PRD, mas ainda não há tela/queries/testes específicos.
RF-030 - pendente - persistência transacional entre clientes, acordos, parcelas e ledger foi adicionada ao PRD, mas os fluxos operacionais novos ainda não existem para validação end-to-end.
