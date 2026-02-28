<x-filament-panels::page>
	<div class="space-y-6">
		<div class="bg-white dark:bg-gray-900 shadow rounded-xl p-6 space-y-4">
			<div>
				<h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Buku Besar</h2>
				<p class="text-sm text-gray-500 dark:text-gray-400">Filter &amp; Pencarian</p>
				<!-- Filter & Pencarian -->
			</div>

			<div class="grid gap-4 md:grid-cols-3">
				@php
					$coaDropdownOptions = collect($this->coaOptions)
						->map(fn ($label, $id) => ['id' => (string) $id, 'label' => $label])
						->values();
				@endphp
				<div
					class="md:col-span-1"
					x-data="coaMultiSelect({ options: {{ Js::from($coaDropdownOptions) }}, value: @entangle('coa_ids').live, wire: $wire })"
					x-on:keydown.escape.window="close()"
					x-on:click.away="close()"
					data-coa-multi-select
				>
					<label for="coa-search" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Pilih Akun (Multiple)</label>
					<input type="hidden" id="coa-select" name="coa_ids" :value="JSON.stringify(value)">
					<div class="mt-1 relative">
						<div class="min-h-[2.5rem] border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 focus-within:border-primary-500 focus-within:ring-primary-500">
							<div class="flex flex-wrap gap-1 p-2">
								<template x-for="selected in selectedOptions" :key="selected.id">
									<span class="inline-flex items-center gap-1 px-2 py-1 text-sm bg-primary-100 text-primary-800 dark:bg-primary-600/30 dark:text-primary-100 rounded">
										<span x-text="selected.label"></span>
										<button type="button" x-on:click="remove(selected)" class="text-primary-600 hover:text-primary-800 dark:text-primary-200 dark:hover:text-primary-100">
											<x-heroicon-m-x-mark class="h-3 w-3" />
										</button>
									</span>
								</template>
								<input
									id="coa-search"
									type="text"
									x-model="search"
									x-on:focus="open = true"
									x-on:input="open = true"
									x-on:keydown.enter.prevent="selectFirst()"
									autocomplete="off"
									placeholder="Cari kode atau nama akun..."
									dusk="coa-search-input"
									class="flex-1 min-w-0 bg-transparent border-0 focus:ring-0 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400"
								>
							</div>
						</div>
						<div
							x-show="open"
							x-transition.origin-top
							x-cloak
							class="absolute z-20 mt-2 w-full max-h-60 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
						>
							<ul class="divide-y divide-gray-100 text-sm dark:divide-gray-700">
								<template x-if="filteredOptions.length === 0">
									<li class="px-4 py-3 text-gray-500 dark:text-gray-300">Tidak ada hasil</li>
								</template>
								<template x-for="option in filteredOptions" :key="option.id">
									<li>
										<button
											type="button"
											class="flex w-full items-start gap-2 px-4 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700"
											:class="{'bg-primary-100 text-primary-900 dark:bg-primary-600/30 dark:text-primary-100': value.includes(String(option.id))}"
											x-on:click="toggle(option)"
											x-bind:data-option-id="option.id"
										>
											<span class="font-medium" x-text="option.label"></span>
										</button>
									</li>
								</template>
							</ul>
						</div>
					</div>
					<div class="mt-3 flex flex-wrap gap-2">
						<button
							type="button"
							wire:click="showAll"
							class="inline-flex items-center rounded-lg border border-primary-100 bg-primary-50 px-3 py-2 text-sm font-medium text-primary-700 hover:bg-primary-100 focus:outline-none focus:ring-2 focus:ring-primary-400"
						>
							Tampilkan Semua
						</button>
						<button
							type="button"
							wire:click="showByJournalEntry"
							class="inline-flex items-center rounded-lg border border-secondary-100 bg-secondary-50 px-3 py-2 text-sm font-medium text-secondary-700 hover:bg-secondary-100 focus:outline-none focus:ring-2 focus:ring-secondary-400"
						>
							Buku Besar berdasarkan Journal Entry
						</button>
					</div>
				</div>

				<div class="space-y-2">
					<label for="start-date" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Tanggal Mulai</label>
					<input
						id="start-date"
						type="date"
						wire:model="start_date"
						class="block w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-primary-500"
					>
				</div>

				<div class="space-y-2">
					<label for="end-date" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Tanggal Akhir</label>
					<input
						id="end-date"
						type="date"
						wire:model="end_date"
						class="block w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-primary-500"
					>
				</div>
			</div>
		</div>

		@if($this->showPreview)
		<div class="bg-white dark:bg-gray-900 shadow rounded-xl">
			@php
				$startDate = $this->start_date ?: now()->startOfMonth()->format('Y-m-d');
				$endDate = $this->end_date ?: now()->endOfMonth()->format('Y-m-d');
				$entries = collect();
				$coaData = collect();

				Log::info('[BukuBesarPage View] Rendering with view_mode: ' . $this->view_mode . ', coa_ids: ' . json_encode($this->coa_ids));

				if ($this->view_mode === 'by_coa' && !empty($this->coa_ids)) {
					Log::info('[BukuBesarPage View] Processing by_coa mode with coa_ids: ' . json_encode($this->coa_ids));
					$coas = $this->selectedCoas;
					Log::info('[BukuBesarPage View] Found ' . $coas->count() . ' selected COAs');
					foreach ($coas as $coa) {
						$query = \App\Models\JournalEntry::query()
							->where('coa_id', $coa->id)
							->orderBy('date')
							->orderBy('id');

						if ($startDate) {
							$query->where('date', '>=', $startDate);
						}
						if ($endDate) {
							$query->where('date', '<=', $endDate);
						}

						$entries = $query->get();

						$openingDebit = \App\Models\JournalEntry::where('coa_id', $coa->id)
							->when($startDate, fn ($q) => $q->where('date', '<', $startDate))
							->sum('debit');

						$openingCredit = \App\Models\JournalEntry::where('coa_id', $coa->id)
							->when($startDate, fn ($q) => $q->where('date', '<', $startDate))
							->sum('credit');

						$openingBalance = in_array($coa->type, ['Asset', 'Expense'])
							? ($coa->opening_balance + $openingDebit - $openingCredit)
							: ($coa->opening_balance - $openingDebit + $openingCredit);

						$coaData->push([
							'coa' => $coa,
							'entries' => $entries,
							'opening_balance' => $openingBalance
						]);
					}
				} elseif ($this->view_mode === 'by_journal_entry') {
					$query = \App\Models\JournalEntry::query()
						->with(['coa', 'source'])
						->orderBy('date')
						->orderBy('id');

					if ($startDate) {
						$query->where('date', '>=', $startDate);
					}
					if ($endDate) {
						$query->where('date', '<=', $endDate);
					}

					$allEntries = $query->get();

					$coaData->push([
						'coa' => null, // No specific COA for journal entry view
						'entries' => $allEntries,
						'opening_balance' => 0, // No opening balance for journal entry view
						'view_mode' => 'by_journal_entry'
					]);
				} elseif ($this->view_mode === 'all') {
					// Get all COAs that have journal entries within the date range
					$coaIds = \App\Models\JournalEntry::query()
						->when($startDate, fn ($q) => $q->where('date', '>=', $startDate))
						->when($endDate, fn ($q) => $q->where('date', '<=', $endDate))
						->distinct()
						->pluck('coa_id');

					$coas = \App\Models\ChartOfAccount::whereIn('id', $coaIds)->get();

					foreach ($coas as $coa) {
						$query = \App\Models\JournalEntry::query()
							->where('coa_id', $coa->id)
							->orderBy('date')
							->orderBy('id');

						if ($startDate) {
							$query->where('date', '>=', $startDate);
						}
						if ($endDate) {
							$query->where('date', '<=', $endDate);
						}

						$entries = $query->get();

						$openingDebit = \App\Models\JournalEntry::where('coa_id', $coa->id)
							->when($startDate, fn ($q) => $q->where('date', '<', $startDate))
							->sum('debit');

						$openingCredit = \App\Models\JournalEntry::where('coa_id', $coa->id)
							->when($startDate, fn ($q) => $q->where('date', '<', $startDate))
							->sum('credit');

						$openingBalance = in_array($coa->type, ['Asset', 'Expense'])
							? ($coa->opening_balance + $openingDebit - $openingCredit)
							: ($coa->opening_balance - $openingDebit + $openingCredit);

						$coaData->push([
							'coa' => $coa,
							'entries' => $entries,
							'opening_balance' => $openingBalance
						]);
					}
				}
			@endphp

			@if ($coaData->isEmpty())
				<div class="p-10 text-center text-gray-500 dark:text-gray-400">
					@if ($this->view_mode === 'by_coa')
						Silakan pilih satu atau lebih akun untuk menampilkan Buku Besar.
					@elseif ($this->view_mode === 'all')
						Tidak ada akun dengan transaksi untuk ditampilkan.
					@elseif ($this->view_mode === 'by_journal_entry')
						Tidak ada journal entry untuk ditampilkan pada periode ini.
					@endif
				</div>
			@else
				@foreach ($coaData as $data)
					@if ($this->view_mode !== 'by_coa' || $loop->first)
						@if ($this->view_mode !== 'by_journal_entry')
						<div class="border-b border-gray-200 dark:border-gray-800 p-6">
							<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
								<div>
									<p class="text-sm text-gray-500 dark:text-gray-400">Kode Akun</p>
									<p class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->safeText($data['coa']->code, 'coa_code') }}</p>
								</div>
								<div>
									<p class="text-sm text-gray-500 dark:text-gray-400">Nama Akun</p>
									<p class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->safeText($data['coa']->name, 'coa_name') }}</p>
								</div>
								<div class="text-sm text-gray-500 dark:text-gray-400">
									<p>Periode</p>
									<p class="font-medium text-gray-900 dark:text-gray-100">{{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} &ndash; {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</p>
								</div>
							</div>
						</div>
						@else
						<div class="border-b border-gray-200 dark:border-gray-800 p-6">
							<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
								<div>
									<p class="text-sm text-gray-500 dark:text-gray-400">Tampilan</p>
									<p class="text-xl font-semibold text-gray-900 dark:text-gray-100">Buku Besar berdasarkan Journal Entry</p>
								</div>
								<div class="text-sm text-gray-500 dark:text-gray-400">
									<p>Periode</p>
									<p class="font-medium text-gray-900 dark:text-gray-100">{{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} &ndash; {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</p>
								</div>
							</div>
						</div>
						@endif
					@endif

					<div class="overflow-x-auto">
						<table class="min-w-full w-full divide-y divide-gray-200 dark:divide-gray-800">
							<thead class="bg-gray-50 dark:bg-gray-800/60">
								<tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
									<th class="px-6 py-3">Tanggal</th>
									<th class="px-6 py-3">No Transaksi</th>
									<th class="px-6 py-3">Akun</th>
									<th class="px-6 py-3">Deskripsi</th>
									<th class="px-6 py-3 text-right">Debit</th>
									<th class="px-6 py-3 text-right">Kredit</th>
									@if ($this->view_mode !== 'by_journal_entry')
									<th class="px-6 py-3 text-right">Saldo</th>
									@endif
								</tr>
							</thead>
							<tbody class="divide-y divide-gray-200 bg-white text-sm text-gray-700 dark:divide-gray-800 dark:bg-gray-900 dark:text-gray-100">
								@if ($this->view_mode !== 'by_journal_entry')
								<tr class="bg-primary-50/60 font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
									<td colspan="3" class="px-6 py-3">Saldo Awal</td>
									<td class="px-6 py-3 text-right">&mdash;</td>
									<td class="px-6 py-3 text-right">&mdash;</td>
									<td class="px-6 py-3 text-right">Rp {{ number_format($data['opening_balance'] ?? 0, 0, ',', '.') }}</td>
								</tr>

								@php
									$runningBalance = $data['opening_balance'] ?? 0;
								@endphp
								@endif

								@forelse ($data['entries'] as $entry)
									@if ($this->view_mode !== 'by_journal_entry')
									@php
										if (in_array($data['coa']->type, ['Asset', 'Expense'])) {
											$runningBalance = $runningBalance + $entry->debit - $entry->credit;
										} else {
											$runningBalance = $runningBalance - $entry->debit + $entry->credit;
										}
									@endphp
									@endif
									<tr class="hover:bg-gray-50 dark:hover:bg-gray-800/70">
										<td class="px-6 py-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($entry->date)->format('d/m/Y') }}</td>
										<td class="px-6 py-3 whitespace-nowrap">{{ $this->safeText($entry->reference, 'entry_reference') ?: '-' }}</td>
										@if ($this->view_mode === 'by_journal_entry')
										<td class="px-6 py-3 whitespace-nowrap">{{ $entry->coa ? $entry->coa->code . ' - ' . $entry->coa->name : '-' }}</td>
										<td class="px-6 py-3">{{ $this->safeText($entry->description, 'entry_description') ?: '-' }}</td>
										@elseif ($this->view_mode === 'all')
										<td class="px-6 py-3 whitespace-nowrap">{{ $data['coa'] ? ($this->safeText($data['coa']->code, 'coa_code') . ' - ' . $this->safeText($data['coa']->name, 'coa_name')) : '-' }}</td>
										<td class="px-6 py-3">{{ $this->safeText($entry->description, 'entry_description') ?: '-' }}</td>
										@else
										<td class="px-6 py-3 whitespace-nowrap"></td>
										<td class="px-6 py-3">{{ $this->safeText($entry->description, 'entry_description') ?: '-' }}</td>
										@endif
										<td class="px-6 py-3 whitespace-nowrap text-right">
											{{ $entry->debit > 0 ? 'Rp ' . number_format($entry->debit, 0, ',', '.') : 'Rp 0' }}
										</td>
										<td class="px-6 py-3 whitespace-nowrap text-right">
											{{ $entry->credit > 0 ? 'Rp ' . number_format($entry->credit, 0, ',', '.') : 'Rp 0' }}
										</td>
										@if ($this->view_mode !== 'by_journal_entry')
										<td class="px-6 py-3 whitespace-nowrap text-right font-semibold">
											Rp {{ number_format($runningBalance, 0, ',', '.') }}
										</td>
										@endif
									</tr>
								@empty
									<tr>
										<td colspan="{{ $this->view_mode === 'by_journal_entry' ? 6 : 7 }}" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
											Tidak ada transaksi pada periode ini.
										</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>

					@if ($this->view_mode !== 'by_coa' && !$loop->last)
						<hr class="my-8 border-gray-200 dark:border-gray-800">
					@endif
				@endforeach
			@endif
		</div>
		@else
		<div class="bg-white dark:bg-gray-900 shadow rounded-xl p-10 text-center text-gray-500 dark:text-gray-400">
			<x-heroicon-o-funnel class="mx-auto mb-3 h-10 w-10 text-gray-400" />
			<p class="text-base font-medium">Set filter terlebih dahulu, lalu klik <strong>Tampilkan Laporan</strong> untuk melihat data.</p>
		</div>
		@endif
	</div>

</x-filament-panels::page>

@once
	@push('styles')
		<style>[x-cloak]{display:none !important;}</style>
	@endpush

	@push('scripts')
		<script>
			window.Alpine = window.Alpine || {};
			document.addEventListener('alpine:init', () => {
				Alpine.data('coaMultiSelect', ({ options, value, wire }) => ({
					options,
					value: Array.isArray(value) ? value : [],
					wire,
					open: false,
					search: '',
					init() {
						this.syncFromValue();
						this.$watch('value', (newValue) => {
							this.syncFromValue();
							// Call Livewire method when value changes
							if (this.wire && newValue.length > 0) {
								this.wire.call('selectCoas', newValue);
							}
						});
					},
					syncFromValue() {
						// Sync search when value changes
					},
					get selectedOptions() {
						return this.options.filter(opt => this.value.includes(String(opt.id)));
					},
					get filteredOptions() {
						if (!this.search) {
							return this.options.filter(opt => !this.value.includes(String(opt.id)));
						}
						const term = this.search.toLowerCase();
						return this.options.filter((opt) => 
							opt.label.toLowerCase().includes(term) && !this.value.includes(String(opt.id))
						);
					},
					toggle(option) {
						const id = String(option.id);
						if (this.value.includes(id)) {
							this.value = this.value.filter(v => v !== id);
						} else {
							this.value = [...this.value, id];
						}
						this.search = '';
					},
					remove(option) {
						this.value = this.value.filter(v => v !== String(option.id));
					},
					selectFirst() {
						const first = this.filteredOptions[0];
						if (first) {
							this.toggle(first);
						}
					},
					close() {
						this.open = false;
					}
				}));
			});
		</script>
	@endpush
@endonce
