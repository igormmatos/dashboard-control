<div class="space-y-6">
    <section class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-3xl space-y-3">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-400">Dashboard Operacional</p>
                <div class="space-y-2">
                    <h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">Acordos Trabalhistas Parcelados</h1>
                    <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                        Shell inicial da fundação em Livewire para acompanhar cobrança diária, atraso, vencimentos próximos e navegação futura para o detalhe do acordo.
                    </p>
                </div>
            </div>

            <div class="grid w-full gap-3 md:grid-cols-2 xl:max-w-2xl">
                <label class="space-y-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                    <span>Busca rápida</span>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cliente, trabalhador ou processo"
                        class="w-full rounded-2xl border border-zinc-300 bg-white px-4 py-3 text-sm text-zinc-900 outline-none transition focus:border-emerald-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white"
                    >
                </label>

                <label class="space-y-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                    <span>Mês de vencimento</span>
                    <input
                        type="month"
                        wire:model.live="selectedMonth"
                        class="w-full rounded-2xl border border-zinc-300 bg-white px-4 py-3 text-sm text-zinc-900 outline-none transition focus:border-emerald-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white"
                    >
                </label>
            </div>
        </div>

        <div class="mt-5 rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 px-4 py-4 dark:border-zinc-700 dark:bg-zinc-950">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Regras operacionais vigentes</p>
            <ul class="mt-3 space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                @foreach ($operationalRules as $rule)
                    <li>{{ $rule }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section aria-label="Indicadores principais" class="grid gap-4 lg:grid-cols-3">
        <article class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $cards['due_this_month']['label'] }}</p>
            <h2 class="mt-3 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $cards['due_this_month']['count'] }}</h2>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">R$ {{ number_format($cards['due_this_month']['amount'], 2, ',', '.') }}</p>
        </article>

        <article class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-700/40 dark:bg-amber-950/20">
            <p class="text-sm font-medium text-amber-700 dark:text-amber-300">{{ $cards['paid_this_month']['label'] }}</p>
            <h2 class="mt-3 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $cards['paid_this_month']['count'] }}</h2>
            <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">R$ {{ number_format($cards['paid_this_month']['amount'], 2, ',', '.') }}</p>
        </article>

        <article class="rounded-3xl border border-rose-200 bg-rose-50 p-5 shadow-sm dark:border-rose-700/40 dark:bg-rose-950/20">
            <p class="text-sm font-medium text-rose-700 dark:text-rose-300">{{ $cards['overdue_total']['label'] }}</p>
            <h2 class="mt-3 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $cards['overdue_total']['count'] }}</h2>
            <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">R$ {{ number_format($cards['overdue_total']['amount'], 2, ',', '.') }}</p>
        </article>
    </section>

    <section class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
        <article class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Lista operacional</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">Vencendo em 3 dias, parcelas em atraso e pagas no mês com busca, filtro e ordenação mínima.</p>
                </div>
                <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">RF005 · RF006 · RF010 · RF012 · RF013</span>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                @foreach ($quickFilterLabels as $filterKey => $filterLabel)
                    <button
                        type="button"
                        wire:click="setQuickFilter('{{ $filterKey }}')"
                        class="@if ($quickFilter === $filterKey) bg-emerald-600 text-white @else bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 @endif rounded-full px-4 py-2 text-sm font-medium transition"
                    >
                        {{ $filterLabel }} ({{ $quickFilterCounts[$filterKey] ?? 0 }})
                    </button>
                @endforeach

                <label class="ms-auto flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <span>Ordenar por</span>
                    <select wire:model.live="sortBy" class="rounded-full border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                        <option value="due_date_asc">Vencimento mais antigo</option>
                        <option value="amount_desc">Maior valor</option>
                    </select>
                </label>

                <a
                    href="{{ $installmentsExportUrl }}"
                    class="rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-950"
                >
                    Exportar parcelas CSV
                </a>
            </div>

            <div class="mt-5 overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-700">
                <div class="grid grid-cols-[1.3fr_1fr_1fr_0.8fr] gap-3 border-b border-zinc-200 bg-zinc-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-400">
                    <span>Cliente / Processo</span>
                    <span>Vencimento</span>
                    <span>Valor</span>
                    <span>Status</span>
                </div>
                @forelse ($installments as $installment)
                    <a
                        href="{{ route('agreements.show', $installment->agreement) }}"
                        wire:navigate
                        wire:key="dashboard-installment-{{ $installment->id }}"
                        class="grid grid-cols-[1.3fr_1fr_1fr_0.8fr] gap-3 border-b border-zinc-100 px-4 py-4 text-sm text-zinc-700 transition hover:bg-zinc-50 last:border-b-0 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-950"
                    >
                        <span>
                            <strong>{{ $installment->agreement->client->name }}</strong><br>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $installment->agreement->worker->name }} · {{ $installment->agreement->process->process_number }} · Parcela {{ $installment->installment_number }}
                            </span>
                        </span>
                        <span>{{ $installment->due_date->format('d/m/Y') }}</span>
                        <span>R$ {{ number_format((float) $installment->amount, 2, ',', '.') }}</span>
                        <span>
                            <strong>{{ $installment->derivedStatusLabel($asOf) }}</strong><br>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">Persistido: {{ $installment->persistedStatusLabel() }}</span>
                        </span>
                    </a>
                @empty
                    <div class="px-4 py-6 text-sm text-zinc-500 dark:text-zinc-400">
                        Nenhuma parcela encontrada para o filtro operacional atual.
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $installments->links(data: ['scrollTo' => false]) }}
            </div>
        </article>

        <article class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ $agreementPanel['title'] }}</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $agreementPanel['description'] }}</p>
            </div>

            <div class="mt-4">
                <a
                    href="{{ $delayAgreementsExportUrl }}"
                    class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 transition hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-950"
                >
                    Exportar acordos com atraso CSV
                </a>
            </div>

            <div class="mt-5 space-y-3">
                @forelse ($agreementPanel['agreements'] as $agreement)
                    <a
                        href="{{ route('agreements.show', $agreement) }}"
                        wire:navigate
                        wire:key="dashboard-agreement-panel-{{ $agreement->id }}"
                        class="block rounded-2xl border border-zinc-200 px-4 py-4 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-950"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $agreement->client->name }}</p>
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $agreement->worker->name }} · {{ $agreement->process->process_number }}</p>
                            </div>
                            <div class="text-end">
                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                    {{ $agreement->status_label }}
                                </span>
                                @if ($agreement->requires_status_attention)
                                    <p class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300">
                                        Sugestão: {{ $agreement->suggested_status_label }}
                                    </p>
                                @endif
                            </div>
                        </div>
                        <div class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                            @if ($agreement->reference_due_date)
                                Data de referência: {{ \Illuminate\Support\Carbon::parse($agreement->reference_due_date)->format('d/m/Y') }}
                            @else
                                Sem data operacional derivada
                            @endif
                            · Valor relacionado: R$ {{ number_format((float) ($agreement->matched_amount ?? 0), 2, ',', '.') }}
                        </div>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-300 px-4 py-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        Nenhum acordo encontrado para o filtro operacional atual.
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $agreementPanel['agreements']->links(data: ['scrollTo' => false]) }}
            </div>
        </article>
    </section>

    <section class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Acordos descumpridos</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">Lista operacional de RF008. Com RF013, esta seção só permanece ativa no filtro `Em atraso`.</p>
                </div>
                <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-medium text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">RF008</span>
                <a
                    href="{{ $breachedAgreementsExportUrl }}"
                    class="rounded-full border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-950"
                >
                    Exportar CSV
                </a>
            </div>

        <div class="mt-5 overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-700">
            <div class="grid grid-cols-[1.3fr_1fr_1fr] gap-3 border-b border-zinc-200 bg-zinc-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-400">
                <span>Cliente / Processo</span>
                <span>Status</span>
                <span>Valor total</span>
            </div>

            @forelse ($breachedAgreements as $agreement)
                <a
                    href="{{ route('agreements.show', $agreement) }}"
                    wire:navigate
                    wire:key="dashboard-breached-agreement-{{ $agreement->id }}"
                    class="grid grid-cols-[1.3fr_1fr_1fr] gap-3 border-b border-zinc-100 px-4 py-4 text-sm text-zinc-700 transition hover:bg-zinc-50 last:border-b-0 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-950"
                >
                    <span>
                        <strong>{{ $agreement->client->name }}</strong><br>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $agreement->worker->name }} · {{ $agreement->process->process_number }}</span>
                    </span>
                    <span>{{ $agreement->persistedStatusLabel() }}</span>
                    <span>R$ {{ number_format((float) $agreement->total_amount, 2, ',', '.') }}</span>
                </a>
            @empty
                <div class="px-4 py-6 text-sm text-zinc-500 dark:text-zinc-400">
                    Nenhum acordo descumprido encontrado para o filtro operacional atual.
                </div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $breachedAgreements->links(data: ['scrollTo' => false]) }}
        </div>
    </section>
</div>
