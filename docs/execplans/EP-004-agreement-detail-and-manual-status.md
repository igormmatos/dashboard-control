# ExecPlan: EP-004 Agreement Detail and Manual Status

Last updated: 2026-04-24

## Objective
- In scope:
  - evoluir `GET /agreements/{id}` de detalhe mínimo para leitura operacional completa do acordo;
  - mostrar, no detalhe do acordo, o `agreements.status` persistido, o estado operacional derivado por parcelas e a sugestão ao usuário quando houver divergência operacional;
  - permitir alteração manual explícita de `agreements.status` dentro do detalhe do acordo;
  - validar a alteração manual contra o vocabulário oficial de status de acordos;
  - registrar auditoria da alteração manual de status com usuário, entidade, status anterior, status novo e contexto mínimo;
  - cobrir o fluxo com testes automatizados e fixtures mínimas reproduzíveis;
  - atualizar somente a documentação necessária para refletir o novo comportamento do detalhe do acordo.
- Out of scope:
  - CRUD amplo do acordo;
  - edição de cliente, trabalhador, processo, parcelas, valores, datas ou composição do acordo;
  - paginação global do dashboard;
  - exportação CSV;
  - automação que altere `agreements.status` sem ação explícita do usuário;
  - alteração automática de `agreements.status` a partir de `installments`;
  - novos módulos fora do detalhe do acordo e da ação manual de status.

## Inputs and Evidence
- Reviewed:
  - `.agent/AGENTS.md`
  - `.agent/PLANS.md`
  - `docs/PRD.md`
  - `docs/current-state.md`
  - `docs/architecture.md`
  - `docs/database.md`
  - `docs/acceptance-tests.md`
  - `docs/execplans/EP-002-dashboard-core.md`
  - `docs/execplans/EP-003-status-and-operational-rules.md`
- Facts:
  - a baseline validada do repositório é um monólito Laravel 13.6.0 com Livewire + Tailwind e MySQL;
  - `GET /agreements/{id}` já existe como detalhe mínimo de leitura desde o EP-002;
  - o EP-003 consolidou o detalhe do acordo como leitura operacional orientada a status, sem ação de edição naquela etapa;
  - `agreements.status` é persistido e só deve mudar por ação explícita da usuária;
  - `installments.status` segue comportamento operacional por data;
  - parcelas vencidas podem gerar sugestão operacional de mudança do acordo para `com_atraso`, sem persistência automática;
  - vocabulário oficial de `agreements.status`: `com_atraso`, `ativo`, `descumprido`, `finalizado`, `encerrado_sem_quitacao`;
  - vocabulário oficial de `installments.status`: `vencido`, `pago_com_atraso`, `pago_em_dia`, `acordo_feito`, `em_dia`;
  - `audit_logs` já é entidade confirmada para operações sensíveis;
  - o schema real de `audit_logs` não tinha campo para contexto estruturado antes do EP-004;
  - a rota `GET /agreements/{agreement}` já estava protegida por `auth` e `verified`;
  - não havia policy ou gate específico para diferenciar `admin` e `advogada` na alteração manual de status;
  - a documentação vigente separa status persistido, estado derivado por data, sugestão ao usuário e ação manual.
- Assumptions:
  - a ação manual de status será exposta no componente Livewire existente do detalhe do acordo ou em componente Livewire equivalente no mesmo escopo de rota;
  - o fluxo pode usar o usuário autenticado da sessão para preencher `audit_logs.user_id`;
  - o EP-004 pode usar autenticação como permissão mínima para alteração manual de status porque não há regra de autorização por perfil consolidada no código.
- Unknowns:
  - se a autorização entre perfis `admin` e `advogada` terá diferença real para alteração manual de status em uma fase futura;
  - se a UI deve exigir confirmação modal para qualquer mudança ou somente para estados terminais como `finalizado` e `encerrado_sem_quitacao`;
  - se será exigido campo obrigatório de motivo para toda alteração manual ou apenas auditoria técnica do evento.

## Implementation Strategy
- Approach:
  - evoluir o detalhe do acordo incrementalmente, preservando a rota existente e o modelo Livewire adotado no dashboard;
  - manter `agreements.status` como fonte persistida de verdade e tratar estado operacional derivado apenas como leitura calculada;
  - implementar a ação manual como operação pequena e explícita: selecionar novo status, validar, persistir o acordo e gravar auditoria na mesma transação;
  - centralizar validação e rótulos no catálogo de status já consolidado, evitando strings soltas em Blade, testes e seeders;
  - manter a UI orientada à operação: leitura do acordo, parcelas, sinalizações, sugestão e controle manual de status, sem abrir edição geral.
- Mapping to repository artifacts:
  - rotas:
    - `routes/web.php` deve manter `GET /agreements/{agreement}` protegido por autenticação;
    - não deve ser criada rota CRUD ampla para edição do acordo;
    - a ação manual pode ser método Livewire no componente da rota existente.
  - Livewire e views:
    - `app/Livewire/Agreements/Show.php`
    - `resources/views/livewire/agreements/show.blade.php`
  - domínio e models:
    - `app/Models/Agreement.php`
    - `app/Models/Installment.php`
    - `app/Models/AuditLog.php`
    - catálogo ou suporte de status existente em `app/Support/Dashboard/DashboardStatusCatalog.php`, ou extração equivalente se a implementação já justificar nome menos acoplado ao dashboard.
  - validação e autorização:
    - validação Livewire ou classe dedicada para restringir o novo status ao vocabulário oficial;
    - política ou gate apenas se o repositório já tiver padrão de autorização por perfil, ou se a implementação precisar formalizar permissão mínima para usuário autenticado.
  - auditoria:
    - migration apenas se o schema atual de `audit_logs` não suportar contexto mínimo;
    - registro de ação com `action`, `entity_type`, `entity_id`, `user_id`, timestamp e payload/contexto contendo `old_status`, `new_status` e origem `agreement_detail`.
  - testes:
    - `tests/Feature/AgreementShowTest.php`
    - novo teste específico de auditoria se a separação melhorar legibilidade;
    - factories/seeders existentes em `database/factories/*` e `database/seeders/*`.
  - documentação:
    - `docs/acceptance-tests.md`
    - `docs/database.md` somente se houver alteração de schema de auditoria;
    - `docs/current-state.md` após execução, para registrar o novo estado real.
- Rejected alternatives:
  - criar tela ou rota de edição completa do acordo:
    - rejeitado porque o escopo aprovado é somente detalhe operacional e ação manual de status.
  - transformar sugestão em mudança automática:
    - rejeitado porque viola a decisão obrigatória de que `agreements.status` só muda por ação explícita.
  - atualizar `agreements.status` por job, scheduler ou hook de consulta:
    - rejeitado neste EP porque automação de status do acordo está fora de escopo.
  - implementar exportação CSV junto com auditoria:
    - rejeitado porque RF014 permanece fora deste EP.
  - criar paginação global no dashboard ao tocar no detalhe:
    - rejeitado porque paginação global foi explicitamente excluída deste EP.

## Execution Steps
1. [completed] Confirmar estado real do detalhe do acordo e do schema de auditoria
   - Changes:
     - inspecionar `routes/web.php`, `app/Livewire/Agreements/Show.php`, view correspondente, `Agreement`, `Installment`, `AuditLog`, migrations e testes atuais;
     - verificar se `audit_logs` possui coluna adequada para contexto estruturado.
   - Expected result:
     - implementação parte do código real existente, sem duplicar componentes ou presumir schema inexistente.
   - Verification:
     - revisão local dos arquivos;
     - `php artisan route:list --path=agreements --except-vendor`;
     - `php artisan migrate:status`;
     - descoberta registrada: `audit_logs` exigiu novo campo `context` JSON para registrar status anterior e novo.

2. [completed] Reforçar o contrato de leitura operacional do detalhe do acordo
   - Changes:
     - garantir que o detalhe exiba cliente, trabalhador, processo, valor total, status persistido do acordo, parcelas, status das parcelas, estado operacional derivado e sugestão ao usuário;
     - manter cálculo derivado em model/service/suporte, não na Blade;
     - exibir sugestão quando um acordo persistido como `ativo` tiver parcela operacionalmente atrasada, sem alterar `agreements.status`.
   - Expected result:
     - usuária entende a situação real do acordo sem confundir status persistido com leitura operacional.
   - Verification:
     - teste de feature renderiza status persistido, estado derivado e sugestão;
     - teste confirma que a simples visualização do detalhe não altera `agreements.status`.

3. [completed] Implementar ação manual restrita para alteração de `agreements.status`
   - Changes:
     - adicionar controle Livewire no detalhe para selecionar novo status oficial;
     - validar o status contra o catálogo oficial de `agreements.status`;
     - persistir somente `agreements.status`;
     - impedir que a ação altere parcelas, valores, datas, cliente, trabalhador ou processo.
   - Expected result:
     - usuária autenticada consegue alterar manualmente o status do acordo em ação explícita e limitada.
   - Verification:
     - teste de feature/Livewire altera `agreements.status` de `ativo` para `com_atraso`;
     - teste com status inválido falha validação e não persiste mudança;
     - teste confirma que parcelas relacionadas permanecem inalteradas.

4. [completed] Registrar auditoria da alteração manual de status
   - Changes:
     - criar ou reutilizar estrutura de auditoria para registrar a mudança;
     - se necessário, adicionar migration mínima para contexto estruturado em `audit_logs`;
     - gravar auditoria na mesma transação da alteração de status;
     - incluir `user_id`, `action`, `entity_type`, `entity_id`, `old_status`, `new_status` e origem da ação.
   - Expected result:
     - toda alteração manual de status do acordo deixa trilha auditável e rastreável.
   - Verification:
     - teste confirma criação de `audit_logs` após mudança de status;
     - teste confirma que falha de validação não gera auditoria;
     - migration `2026_04_24_115921_add_context_to_audit_logs_table.php` adiciona `audit_logs.context`;
     - `php artisan migrate:fresh --seed` passa.

5. [completed] Ajustar validação, autorização e UX mínima da ação
   - Changes:
     - aplicar proteção de autenticação já existente na rota;
     - adicionar autorização de alteração se houver padrão vigente de roles/policies;
     - apresentar feedback de sucesso e erro no detalhe;
     - avaliar confirmação simples antes da persistência, especialmente para status terminais, sem criar fluxo complexo.
   - Expected result:
     - ação manual é segura, compreensível e consistente com os padrões existentes da aplicação.
   - Verification:
     - teste garante guest redirecionado para login;
     - teste garante usuário autenticado autorizado consegue executar a ação;
     - decisão registrada: sem policy por perfil neste EP porque não há regra consolidada para diferenciar `admin` e `advogada`.

6. [completed] Consolidar seeds e fixtures mínimas do EP-004
   - Changes:
     - garantir fixtures para:
       - acordo `ativo` com parcela `vencido`, gerando sugestão de `com_atraso`;
       - acordo já `com_atraso`;
       - acordo em status terminal, como `finalizado` ou `encerrado_sem_quitacao`;
       - usuário autenticado responsável pela alteração;
       - auditoria criada por alteração manual.
   - Expected result:
     - testes e validação manual conseguem reproduzir os cenários essenciais sem depender de dados ad hoc.
   - Verification:
     - factories e seeders continuam compatíveis com o vocabulário oficial;
     - testes criam seus próprios dados mínimos ou reutilizam factories com estados claros;
     - `php artisan test --compact` com os testes afetados passa.

7. [completed] Atualizar testes de aceite e documentação afetada
   - Changes:
     - adicionar critérios de aceite do detalhe operacional e da alteração manual em `docs/acceptance-tests.md`;
     - atualizar `docs/database.md` se a auditoria ganhar campo de contexto;
     - atualizar `docs/current-state.md` ao final da execução para refletir o novo comportamento implementado;
     - manter PRD e arquitetura sem ampliação de escopo, salvo nota curta se necessário.
   - Expected result:
     - documentação operacional fica alinhada ao comportamento implementado, sem reabrir decisões já consolidadas.
   - Verification:
     - revisão dos documentos alterados;
     - ausência de novas promessas de CRUD, CSV, paginação ou automação de status do acordo.

8. [completed] Executar validação final mínima
   - Changes:
     - rodar formatador se houver alteração em PHP;
     - executar testes focados do detalhe, domínio e auditoria;
     - executar teste mais amplo se mudanças em migration, model ou suporte de status tiverem impacto transversal.
   - Expected result:
     - EP-004 fica implementado de forma verificável e sem regressões conhecidas no dashboard core.
   - Verification:
     - `vendor/bin/pint --dirty --format agent`;
     - `php artisan test --compact tests/Feature/AgreementShowTest.php`;
     - `php artisan test --compact --filter=Agreement`;
     - `php artisan migrate:fresh --seed`;
     - `php artisan test --compact`.

## Validation Gates
- [x] `GET /agreements/{id}` continua protegido por autenticação.
- [x] O detalhe do acordo exibe status persistido de `agreements.status`.
- [x] O detalhe exibe estado operacional derivado por parcelas sem persistir alteração automática no acordo.
- [x] O detalhe mostra sugestão ao usuário quando houver atraso operacional relevante.
- [x] A sugestão não altera `agreements.status` sem ação explícita.
- [x] A usuária autenticada consegue alterar manualmente `agreements.status` para um status oficial.
- [x] Status inválido é rejeitado por validação e não altera o banco.
- [x] A alteração manual registra auditoria com ator, entidade, status anterior e status novo.
- [x] A alteração manual não edita cliente, trabalhador, processo, parcelas, valores ou datas.
- [x] Seeds/fixtures mínimas cobrem sugestão, alteração manual e auditoria.
- [x] Testes automatizados dos fluxos afetados passam.
- [x] Nenhuma rota ou UI de CRUD amplo do acordo é introduzida.
- [x] Nenhuma paginação global do dashboard é implementada neste EP.
- [x] Nenhuma exportação CSV é implementada neste EP.
- [x] Nenhuma automação passa a alterar `agreements.status`.
- [x] Documentação afetada reflete o comportamento implementado e os limites do escopo.

## Risks and Mitigations
- Risk: a ação manual de status crescer para CRUD geral do acordo.
  - Mitigation: restringir a operação a `agreements.status`, com testes garantindo que demais campos e parcelas não mudam.
- Risk: confundir sugestão operacional com mutação automática.
  - Mitigation: testar visualização sem alteração persistida e manter textos de UI distinguindo "status atual" de "sugestão".
- Risk: auditoria insuficiente para reconstruir a alteração.
  - Mitigation: registrar no mínimo usuário, entidade, status anterior, status novo, ação e timestamp; adicionar contexto estruturado se o schema ainda não suportar.
- Risk: strings de status se espalharem em Livewire, Blade e testes.
  - Mitigation: reutilizar o catálogo oficial de status ou extrair suporte compartilhado antes de adicionar novas referências.
- Risk: autorização por perfil ficar implícita e divergir do futuro modelo `admin`/`advogada`.
  - Mitigation: manter autenticação obrigatória como mínimo e registrar em `Open Questions` qualquer diferença de perfil não decidida; se o padrão de policy já existir, segui-lo.
- Risk: status terminais serem alterados sem confirmação suficiente.
  - Mitigation: avaliar confirmação simples no detalhe para `finalizado` e `encerrado_sem_quitacao`, sem criar workflow complexo fora do escopo.
- Risk: migration de auditoria impactar testes existentes.
  - Mitigation: preferir reuso de coluna existente; se migration for necessária, manter mudança mínima e validar com `migrate:fresh --seed`.

## Open Questions
1. A alteração manual de status deve exigir campo de motivo obrigatório ou a auditoria técnica com status anterior e novo basta neste EP?
2. Perfis `admin` e `advogada` terão a mesma permissão para alterar status de acordo em fases futuras? No EP-004, a permissão mínima implementada é usuário autenticado.
3. Mudanças para status terminais, como `finalizado` e `encerrado_sem_quitacao`, exigem confirmação diferenciada?
4. A exportação CSV futura reutilizará o mesmo formato JSON estruturado de `audit_logs.context` adotado neste EP?
5. A sugestão operacional deve sugerir apenas `com_atraso` quando há parcela vencida ou também outras transições futuras, como `finalizado`, quando todas as parcelas estiverem pagas?

## Change Log
- 2026-04-24 00:00 - Plano criado a partir do estado documental pós EP-003 e das decisões obrigatórias para detalhe do acordo e alteração manual de status.
- 2026-04-24 09:02 - EP-004 executado: detalhe do acordo ganhou estado operacional derivado explícito, ação manual de status, auditoria em `audit_logs.context`, fixtures mínimas, testes e documentação reconciliada. Validações executadas com sucesso: `vendor/bin/pint --dirty --format agent`, `php artisan test --compact tests/Feature/AgreementShowTest.php`, `php artisan test --compact --filter=Agreement`, `php artisan migrate:fresh --seed` e `php artisan test --compact`. Permissão por perfil e motivo obrigatório permaneceram como dúvidas futuras por não bloquearem execução segura.
