# ExecPlan: EP-007 Status Safeguards and Role Rules

Last updated: 2026-04-24

## Objective
- In scope:
  - planejar salvaguardas para alteração manual de `agreements.status`;
  - definir e implementar, em etapa futura, motivo obrigatório quando aplicável;
  - definir e implementar confirmação especial para status terminais;
  - definir autorização mínima por perfil para ações sensíveis já existentes;
  - preservar auditoria adequada para alteração manual de status e demais ações sensíveis afetadas;
  - alinhar testes, fixtures, documentação e `docs/rf-status.md` ao final da execução futura.
- Out of scope:
  - executar este plano agora;
  - alterar código da aplicação nesta tarefa de criação do plano;
  - automatizar alteração de `agreements.status`;
  - transformar `agreements.status` em estado derivado automático;
  - criar CRUD amplo de acordo, cliente, trabalhador, processo ou parcelas;
  - alterar dashboard, paginação, CSV, ranking, gráfico ou BI;
  - mudar a stack Laravel + Livewire + Tailwind + MySQL;
  - marcar qualquer RF como implementado apenas pela criação deste plano.

## Plan Status
- Status: `Planned / Deferred`.
- Execution authorization: `Not approved for execution yet`.
- Backlog intent:
  - este ExecPlan formaliza uma etapa futura para salvaguardas de status e regras mínimas por perfil;
  - este documento é autocontido e pronto para execução quando houver aprovação explícita;
  - nenhum passo deste EP deve ser executado antes dos critérios de entrada abaixo estarem atendidos.
- Current task boundary:
  - somente criação do arquivo `docs/execplans/EP-007-status-safeguards-and-role-rules.md`;
  - nenhum código, migration, rota, controller, component, view ou teste deve ser criado nesta tarefa.

## Entry Criteria For Future Execution
- Aprovação explícita para executar o EP-007 em uma etapa posterior.
- Confirmação de que ajustes prévios ainda não executados não conflitam com:
  - alteração manual de status no detalhe do acordo;
  - auditoria em `audit_logs.context`;
  - regras de perfil em `users.role`;
  - vocabulário oficial de `agreements.status`.
- Decisão mínima de produto sobre:
  - quais mudanças de status exigem motivo obrigatório;
  - quais status são considerados terminais para confirmação especial;
  - quais perfis podem executar ações sensíveis.
- Baseline pós EP-006 preservada e validada:
  - dashboard funcional;
  - detalhe do acordo funcional;
  - exportação CSV auditada;
  - paginação real;
  - RF015 por derivação operacional, sem automação de `agreements.status`.
- `docs/rf-status.md` revisado antes da execução futura para confirmar que o EP-007 não está sendo usado para marcar RFs sem evidência.

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
  - `docs/execplans/EP-004-agreement-detail-and-manual-status.md`
  - `docs/execplans/EP-005-csv-export-and-audit.md`
  - `docs/execplans/EP-006-pagination-performance-and-rf015.md`
  - `app/Livewire/Agreements/Show.php`
  - `resources/views/livewire/agreements/show.blade.php`
  - `app/Models/User.php`
  - `tests/Feature/AgreementShowTest.php`
- Facts:
  - a baseline técnica validada é Laravel 13.6.0 + MySQL + monólito web;
  - a UI principal é Livewire + Tailwind;
  - o dashboard, detalhe do acordo, auditoria, CSV e paginação real já existem;
  - RF014 está implementado;
  - RF015 está implementado por derivação operacional em queries/UI usando `due_date`, `paid_date` e data atual;
  - `agreements.status` é persistido e só deve mudar por ação explícita da usuária;
  - `agreements.status` oficial: `com_atraso`, `ativo`, `descumprido`, `finalizado`, `encerrado_sem_quitacao`;
  - `App\Livewire\Agreements\Show::updateStatus()` permite alteração manual de `agreements.status`;
  - a alteração manual atual valida status oficial, persiste somente `agreements.status` e registra `agreement_status_updated` em `audit_logs.context`;
  - o contexto de auditoria atual da alteração manual contém `old_status`, `new_status` e `source`;
  - `users.role` existe e o modelo `User` permite `role`, mas não há regra consolidada de autorização por perfil para alteração manual de status;
  - EP-004 deixou como dúvidas futuras: motivo obrigatório, permissão por perfil e confirmação diferenciada para status terminais;
  - EP-005 deixou como dúvida futura a permissão por perfil para exportação CSV;
  - EP-006 não alterou regras de perfil nem salvaguardas de status.
- Assumptions:
  - o EP-007 deve evoluir a ação manual de status já existente, não criar um novo fluxo paralelo;
  - a primeira política de autorização por perfil deve ser mínima e explícita, evitando modelar um sistema completo de permissões;
  - `admin` e `advogada` são os perfis de referência documentados, mas a permissão efetiva ainda precisa ser confirmada antes da execução;
  - motivo obrigatório pode ser armazenado em `audit_logs.context` se não houver necessidade de consulta relacional própria;
  - confirmação especial pode ser implementada no componente Livewire existente do detalhe sem introduzir CRUD amplo.
- Unknowns:
  - quais perfis poderão alterar `agreements.status`;
  - quais perfis poderão exportar CSV, se o EP-007 também formalizar essa ação sensível;
  - se motivo obrigatório será exigido para toda alteração de status ou apenas para status terminais/de risco;
  - se a confirmação especial será exigida para `finalizado`, `encerrado_sem_quitacao`, `descumprido`, ou outro subconjunto;
  - se o motivo deve ser campo livre auditado em JSON ou dado persistido em coluna/tabela própria;
  - se será necessário diferenciar autorização de visualização do detalhe e autorização de alteração manual.

## Implementation Strategy
- Approach:
  - tratar o EP-007 como endurecimento controlado do fluxo já existente de alteração manual de status;
  - preservar `agreements.status` como persistido e manual;
  - introduzir regras de perfil de forma mínima, observável e testável;
  - adicionar motivo e confirmação apenas às ações sensíveis, sem abrir edição geral do acordo;
  - manter toda alteração sensível auditada em `audit_logs.context`;
  - usar policies/gates ou validação Livewire conforme o padrão real do projeto no momento da execução futura;
  - atualizar documentação e `docs/rf-status.md` somente com evidência real após a execução futura.
- Mapping to repository artifacts:
  - Livewire:
    - `app/Livewire/Agreements/Show.php` para validação de motivo, confirmação e autorização da alteração manual;
    - `resources/views/livewire/agreements/show.blade.php` para campos de motivo, confirmação e mensagens de autorização/erro.
  - domínio e suporte:
    - `app/Support/Dashboard/DashboardStatusCatalog.php` para centralizar status terminais ou regras auxiliares, se a execução confirmar esse local como adequado;
    - `app/Models/Agreement.php` para manter regra de status persistido e evitar automação;
    - `app/Models/User.php` para helpers simples de perfil somente se houver padrão claro.
  - autorização:
    - possível `AgreementPolicy` ou gate equivalente para `updateStatus`;
    - possível regra para exportação CSV se a decisão futura incluir RF014 como ação sensível por perfil;
    - `routes/web.php` não deve receber CRUD amplo nem novas rotas de edição.
  - auditoria:
    - `app/Models/AuditLog.php` deve ser reutilizado;
    - `audit_logs.context` deve incluir motivo, confirmação exigida, confirmação fornecida, perfil do usuário e origem da ação quando aplicável;
    - migration só deve ser criada se houver decisão explícita de persistir motivo fora do JSON de auditoria.
  - testes:
    - `tests/Feature/AgreementShowTest.php` para status manual com motivo, confirmação, autorização e auditoria;
    - `tests/Feature/DashboardExportTest.php` apenas se exportação CSV entrar na regra mínima por perfil;
    - factories de `User` com roles explícitas, se ainda não houver states;
    - testes devem provar que `agreements.status` não muda em falhas de autorização, validação ou confirmação.
  - documentação:
    - `docs/acceptance-tests.md`;
    - `docs/current-state.md`;
    - `docs/architecture.md`;
    - `docs/database.md` se o formato de auditoria ou schema for alterado;
    - `docs/rf-status.md` deve ser revisado ao final da execução futura sem marcar RF indevidamente;
    - este ExecPlan deve ser atualizado com progresso, decisões e validações reais quando for executado.
- Rejected alternatives:
  - executar agora junto com a criação do plano:
    - rejeitado porque a instrução explícita é criar backlog formal e adiar execução.
  - criar CRUD completo de acordo:
    - rejeitado porque o escopo é apenas salvaguardas de status e autorização mínima.
  - automatizar `agreements.status`:
    - rejeitado porque viola decisões consolidadas dos EPs 003, 004 e 006.
  - criar sistema amplo de RBAC/permissões:
    - rejeitado por ampliar escopo; o EP-007 deve definir regras mínimas por perfil para ações sensíveis.
  - armazenar motivo em nova tabela sem decisão prévia:
    - rejeitado até haver evidência de necessidade de consulta, retenção ou relatórios independentes da auditoria JSON.

## Execution Steps
1. [pending] Reconfirmar autorização para executar o EP-007
   - Changes:
     - obter aprovação explícita para sair de `Planned / Deferred`;
     - revisar se ajustes prévios pendentes já foram concluídos;
     - atualizar este plano se a realidade do código tiver mudado desde sua criação.
   - Expected result:
     - execução futura começa sem conflito com backlog ou mudanças intermediárias.
   - Verification:
     - registro no Change Log com data/hora de autorização;
     - confirmação de que nenhum passo segue `pending` por falta de decisão bloqueante.

2. [pending] Fechar contrato de produto das salvaguardas
   - Changes:
     - definir quando motivo é obrigatório;
     - definir quais status exigem confirmação especial;
     - definir texto/semântica mínima de confirmação;
     - definir se exportação CSV entra ou não na política mínima por perfil neste EP.
   - Expected result:
     - regras ficam objetivas antes de implementação, sem inferência ambígua no código.
   - Verification:
     - atualização deste ExecPlan com decisões;
     - open questions reduzidas somente ao que não bloquear execução.

3. [pending] Definir autorização mínima por perfil
   - Changes:
     - mapear perfis existentes em `users.role`;
     - definir permissões para:
       - visualizar detalhe do acordo;
       - alterar manualmente status;
       - exportar CSV, se incluído como ação sensível neste EP;
     - escolher implementação via policy/gate/helper conforme padrão real do projeto.
   - Expected result:
     - ações sensíveis deixam de depender apenas de autenticação genérica quando a regra de perfil for aprovada.
   - Verification:
     - testes para perfil autorizado e não autorizado;
     - falha de autorização não altera `agreements.status` e não cria auditoria de alteração bem-sucedida.

4. [pending] Implementar motivo obrigatório quando aplicável
   - Changes:
     - adicionar estado Livewire para motivo;
     - validar motivo com tamanho mínimo/máximo e obrigatoriedade conforme contrato;
     - persistir motivo em `audit_logs.context` junto da alteração;
     - manter o acordo inalterado se a validação falhar.
   - Expected result:
     - alterações sensíveis passam a ter justificativa rastreável.
   - Verification:
     - teste de alteração que exige motivo passa quando motivo válido é enviado;
     - teste falha sem motivo quando obrigatório;
     - auditoria contém motivo normalizado;
     - alteração sem motivo obrigatório continua permitida se essa for a decisão do contrato.

5. [pending] Implementar confirmação especial para status terminais
   - Changes:
     - adicionar confirmação explícita para status definidos como terminais;
     - bloquear persistência quando confirmação for exigida e não fornecida;
     - registrar em auditoria se confirmação especial foi exigida e fornecida.
   - Expected result:
     - transições de maior risco exigem ação consciente além da seleção do status.
   - Verification:
     - teste para status terminal sem confirmação não altera o acordo;
     - teste para status terminal com confirmação altera e audita;
     - teste para status não terminal não exige confirmação, se essa for a regra aprovada.

6. [pending] Reforçar auditoria das ações sensíveis
   - Changes:
     - ampliar `audit_logs.context` da ação `agreement_status_updated` com:
       - `old_status`;
       - `new_status`;
       - `source`;
       - `reason`, quando aplicável;
       - `reason_required`;
       - `terminal_confirmation_required`;
       - `terminal_confirmation_provided`;
       - `user_role`;
       - metadados mínimos relevantes;
     - manter auditoria dentro da mesma transação da alteração.
   - Expected result:
     - trilha auditável permite reconstruir quem alterou, por qual motivo e sob quais salvaguardas.
   - Verification:
     - testes assertam payload de auditoria;
     - falhas de autorização/validação não criam auditoria de sucesso.

7. [pending] Ajustar UI do detalhe do acordo sem criar CRUD amplo
   - Changes:
     - exibir campo de motivo quando aplicável;
     - exibir controle de confirmação para status terminais;
     - mostrar feedback claro para validação e autorização;
     - manter o formulário restrito a `agreements.status` e suas salvaguardas.
   - Expected result:
     - usuária entende quando a alteração exige justificativa ou confirmação.
   - Verification:
     - testes Livewire/feature confirmam renderização esperada;
     - revisão de Blade confirma que não foram adicionados campos de edição ampla.

8. [pending] Consolidar fixtures e testes de regressão
   - Changes:
     - criar ou ajustar factories/states de usuário para `admin` e `advogada`, se necessário;
     - cobrir status terminal, status não terminal, motivo obrigatório, autorização e auditoria;
     - manter testes existentes de EP-004 passando.
   - Expected result:
     - salvaguardas ficam verificáveis e não regressam a alteração manual básica.
   - Verification:
     - `php artisan test --compact tests/Feature/AgreementShowTest.php`;
     - `php artisan test --compact --filter=Agreement`;
     - `php artisan test --compact tests/Feature/DashboardExportTest.php` se CSV for afetado.

9. [pending] Atualizar documentação e controle de RFs após execução futura
   - Changes:
     - atualizar `docs/acceptance-tests.md` com aceite de motivo, confirmação, autorização e auditoria;
     - atualizar `docs/current-state.md` com comportamento real;
     - atualizar `docs/architecture.md` com regra mínima por perfil;
     - atualizar `docs/database.md` apenas se auditoria/schema mudar;
     - revisar `docs/rf-status.md` sem marcar RF novo como implementado sem evidência observável;
     - atualizar este ExecPlan com progresso e decisões reais.
   - Expected result:
     - documentação reflete implementação real, não intenção.
   - Verification:
     - revisão dos documentos alterados;
     - `docs/rf-status.md` permanece coerente com PRD e evidências.

10. [pending] Executar validação final da execução futura
    - Changes:
      - rodar formatador se houver PHP alterado;
      - rodar testes focados e suíte completa se policies/models/auditoria forem alterados.
    - Expected result:
      - EP-007 fica executado sem regressões conhecidas no dashboard, detalhe, CSV ou auditoria.
    - Verification:
      - `vendor/bin/pint --dirty --format agent`;
      - `php artisan route:list --path=agreements --except-vendor`;
      - `php artisan route:list --path=dashboard --except-vendor`, se CSV/perfis afetarem dashboard;
      - `php artisan test --compact tests/Feature/AgreementShowTest.php`;
      - `php artisan test --compact --filter=Agreement`;
      - `php artisan test --compact`, se mudanças forem transversais;
      - `php artisan migrate:fresh --seed`, se migration for criada.

## Validation Gates
- [ ] EP-007 só é executado após aprovação explícita futura.
- [ ] Motivo obrigatório tem regra objetiva e testada.
- [ ] Status terminais têm confirmação especial quando definido pelo contrato.
- [ ] Perfil autorizado consegue executar ação sensível aprovada.
- [ ] Perfil não autorizado é bloqueado sem alterar `agreements.status`.
- [ ] Falhas de validação não alteram `agreements.status`.
- [ ] Falhas de confirmação não alteram `agreements.status`.
- [ ] Auditoria registra motivo, perfil e flags de salvaguarda quando aplicável.
- [ ] Auditoria permanece na mesma transação da alteração manual.
- [ ] Nenhuma automação altera `agreements.status`.
- [ ] Nenhum CRUD amplo é introduzido.
- [ ] Dashboard, CSV, paginação e RF015 permanecem sem regressão.
- [ ] Stack Laravel + Livewire + Tailwind + MySQL é preservada.
- [ ] Testes automatizados cobrem autorização, motivo, confirmação, auditoria e regressão do fluxo manual.
- [ ] Documentação e `docs/rf-status.md` são revisados apenas após execução real.

## Risks and Mitigations
- Risk: antecipar o EP-007 antes de fechar regras de produto gerar autorização ou motivo incompatíveis com a operação real.
  - Mitigation: manter status `Planned / Deferred` e exigir critérios de entrada antes de execução.
- Risk: transformar salvaguardas em CRUD amplo de acordo.
  - Mitigation: limitar alterações a `agreements.status`, motivo, confirmação, autorização e auditoria.
- Risk: usar perfis `admin` e `advogada` de forma especulativa.
  - Mitigation: confirmar permissões antes de implementar e cobrir com testes por role.
- Risk: confirmação terminal virar barreira excessiva para operações comuns.
  - Mitigation: aplicar somente aos status definidos como terminais/de risco.
- Risk: motivo obrigatório ser armazenado em local inadequado.
  - Mitigation: começar por `audit_logs.context` salvo decisão explícita por schema próprio.
- Risk: quebrar o fluxo já implementado no EP-004.
  - Mitigation: manter testes existentes de alteração manual e adicionar casos novos de salvaguarda.
- Risk: afetar RF014 ao discutir autorização por perfil.
  - Mitigation: só alterar exportação CSV se a decisão futura incluir CSV como ação sensível neste EP; manter testes de exportação se afetado.
- Risk: recriar automação indevida de status.
  - Mitigation: registrar e testar que `agreements.status` só muda por ação explícita.

## Open Questions
1. Quais perfis podem alterar manualmente `agreements.status`: apenas `admin`, ou `admin` e `advogada`?
2. A exportação CSV deve receber regra de perfil neste EP ou permanecer somente autenticada até um EP específico de autorização?
3. Motivo será obrigatório para toda alteração de status ou apenas para status terminais/de risco?
4. Quais status exigem confirmação especial: `finalizado`, `encerrado_sem_quitacao`, `descumprido`, ou outro conjunto?
5. O motivo deve ficar apenas em `audit_logs.context` ou haverá necessidade futura de consulta/report dedicado que justifique schema próprio?

## Change Log
- 2026-04-24 10:14 - Plano criado como backlog formal `Planned / Deferred`, sem autorização de execução, para salvaguardas de alteração manual de status, motivo, confirmação terminal, regras mínimas por perfil e auditoria.
