<div class="space-y-6">
    <section class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-2">
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm font-medium text-emerald-700 hover:text-emerald-600 dark:text-emerald-400 dark:hover:text-emerald-300">
                    Voltar ao dashboard
                </a>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Detalhe do acordo</p>
                    <h1 class="mt-2 text-3xl font-semibold text-zinc-950 dark:text-white">
                        {{ $agreement->client->name }}
                    </h1>
                </div>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                    Trabalhador: {{ $agreement->worker->name }} · Processo: {{ $agreement->process->process_number }}
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-950">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Status persistido</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">{{ $agreement->persistedStatusLabel() }}</p>
                </div>
                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-950">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Valor total</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">R$ {{ number_format((float) $agreement->total_amount, 2, ',', '.') }}</p>
                </div>
                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-950 sm:col-span-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Estado operacional derivado</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">{{ $operationalStatusLabel }}</p>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Calculado para leitura a partir do status persistido e das parcelas em {{ $asOf->format('d/m/Y') }}.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-[1.1fr_1fr]">
        <article class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Leitura operacional do acordo</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">O sistema pode sugerir mudança de status, mas só altera o acordo por ação manual explícita.</p>
                </div>
                <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">Status manual</span>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-4 dark:border-zinc-700 dark:bg-zinc-950">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Sugestão operacional</p>
                    @if ($suggestedStatusLabel)
                        <p class="mt-1 text-sm font-semibold text-amber-700 dark:text-amber-300">{{ $suggestedStatusLabel }}</p>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Há parcelas vencidas. A mudança real de agreements.status depende da ação manual abaixo.</p>
                    @else
                        <p class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">Sem sugestão pendente</p>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">O status persistido do acordo permanece como fonte de verdade.</p>
                    @endif
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-4 dark:border-zinc-700 dark:bg-zinc-950">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Regras aplicadas</p>
                    <ul class="mt-2 space-y-2 text-xs text-zinc-600 dark:text-zinc-300">
                        @foreach ($operationalRules as $rule)
                            <li>{{ $rule }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </article>

        <article class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Alteração manual de status</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">Esta ação altera somente agreements.status e registra auditoria. Não edita parcelas nem dados cadastrais.</p>
            </div>

            @if (session('status'))
                <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif

            <form wire:submit="updateStatus" class="mt-5 space-y-4">
                <label class="block space-y-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                    <span>Novo status oficial</span>
                    <select
                        wire:model="selectedStatus"
                        class="w-full rounded-2xl border border-zinc-300 bg-white px-4 py-3 text-sm text-zinc-900 outline-none transition focus:border-emerald-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white"
                    >
                        @foreach ($agreementStatuses as $status)
                            <option value="{{ $status }}">{{ \App\Support\Dashboard\DashboardStatusCatalog::agreementStatusLabel($status) }}</option>
                        @endforeach
                    </select>
                </label>

                @error('selectedStatus')
                    <p class="text-sm font-medium text-rose-700 dark:text-rose-300">{{ $message }}</p>
                @enderror

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60"
                    wire:loading.attr="disabled"
                    wire:target="updateStatus"
                >
                    Atualizar status manualmente
                </button>
            </form>
        </article>
    </section>

    <section class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Parcelas do acordo</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">Leitura operacional das parcelas com distinção entre status persistido e estado derivado por data.</p>
            </div>
        </div>

        <div class="mt-5 overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-700">
            <div class="grid grid-cols-[0.7fr_1fr_1fr_1fr_1fr] gap-3 border-b border-zinc-200 bg-zinc-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-400">
                <span>Parcela</span>
                <span>Vencimento</span>
                <span>Valor</span>
                <span>Status persistido</span>
                <span>Estado operacional</span>
            </div>
            @forelse ($agreement->installments as $installment)
                <div class="grid grid-cols-[0.7fr_1fr_1fr_1fr_1fr] gap-3 border-b border-zinc-100 px-4 py-3 text-sm text-zinc-700 last:border-b-0 dark:border-zinc-800 dark:text-zinc-200">
                    <span>{{ $installment->installment_number }}</span>
                    <span>{{ $installment->due_date->format('d/m/Y') }}</span>
                    <span>R$ {{ number_format((float) $installment->amount, 2, ',', '.') }}</span>
                    <span>{{ $installment->persistedStatusLabel() }}</span>
                    <span>{{ $installment->derivedStatusLabel($asOf) }}</span>
                </div>
            @empty
                <div class="px-4 py-6 text-sm text-zinc-500 dark:text-zinc-400">
                    Nenhuma parcela encontrada para este acordo.
                </div>
            @endforelse
        </div>
    </section>
</div>
