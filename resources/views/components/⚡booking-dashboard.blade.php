<?php

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $startDate = '';

    public string $endDate = '';

    public function mount(): void
    {
        $this->resetDates();
    }

    public function resetDates(): void
    {
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->endOfMonth()->toDateString();
    }

    /**
     * @return array<int, array{key: string, label: string, count: int, percent: int, bar: string, text: string}>
     */
    #[Computed]
    public function statusBars(): array
    {
        $supplierId = Auth::user()->supplier_id;
        $locale = app()->getLocale();
        $cacheKey = "dashboard.statusBars.{$supplierId}.{$this->startDate}.{$this->endDate}.{$locale}";

        return cache()->remember($cacheKey, 60, function (): array {
            $counts = $this->bookingQuery()
                ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count")
                ->selectRaw("SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count")
                ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count")
                ->first();

            $values = [
                'pending' => (int) ($counts?->pending_count ?? 0),
                'confirmed' => (int) ($counts?->confirmed_count ?? 0),
                'cancelled' => (int) ($counts?->cancelled_count ?? 0),
            ];

            $max = max(max($values), 1);

            return [
                [
                    'key' => 'pending',
                    'label' => __('Pendientes'),
                    'count' => $values['pending'],
                    'percent' => (int) round(($values['pending'] / $max) * 100),
                    'bar' => 'bg-amber-500',
                    'text' => 'text-amber-700 dark:text-amber-300',
                ],
                [
                    'key' => 'confirmed',
                    'label' => __('Confirmadas'),
                    'count' => $values['confirmed'],
                    'percent' => (int) round(($values['confirmed'] / $max) * 100),
                    'bar' => 'bg-emerald-500',
                    'text' => 'text-emerald-700 dark:text-emerald-300',
                ],
                [
                    'key' => 'cancelled',
                    'label' => __('Canceladas'),
                    'count' => $values['cancelled'],
                    'percent' => (int) round(($values['cancelled'] / $max) * 100),
                    'bar' => 'bg-rose-500',
                    'text' => 'text-rose-700 dark:text-rose-300',
                ],
            ];
        });
    }

    #[Computed]
    public function totalBookings(): int
    {
        return array_sum(array_column($this->statusBars, 'count'));
    }

    private function bookingQuery(): Builder
    {
        return Booking::query()
            ->forSupplier(Auth::user()->supplier_id)
            ->when($this->startDate !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $this->endDate));
    }
};
?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
        <flux:text class="max-w-3xl">
            {{ __('Resumen por estado de reservas con filtro por fecha de reserva.') }}
        </flux:text>
    </div>

    <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
            <flux:input wire:model.live="startDate" type="date" :label="__('Desde')" data-test="dashboard-start-date" />
            <flux:input wire:model.live="endDate" type="date" :label="__('Hasta')" data-test="dashboard-end-date" />
            <flux:button wire:click="resetDates" icon="arrow-path" data-test="dashboard-reset-dates">
                {{ __('Mes actual') }}
            </flux:button>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        @foreach ($this->statusBars as $status)
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" wire:key="status-card-{{ $status['key'] }}">
                <flux:text>{{ $status['label'] }}</flux:text>
                <div class="mt-2 text-3xl font-semibold {{ $status['text'] }}">{{ format_number($status['count']) }}</div>
            </div>
        @endforeach
    </div>

    <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mb-5 flex flex-col gap-1">
            <flux:heading>{{ __('Reservas por estado') }}</flux:heading>
            <flux:text>{{ __('Total en el periodo') }}: {{ format_number($this->totalBookings) }}</flux:text>
        </div>

        <div class="flex flex-col gap-5">
            @foreach ($this->statusBars as $status)
                <div class="grid gap-2 md:grid-cols-[9rem_1fr_4rem] md:items-center" wire:key="status-bar-{{ $status['key'] }}">
                    <div class="flex items-center justify-between gap-3 md:block">
                        <span class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $status['label'] }}</span>
                        <span class="text-sm font-semibold {{ $status['text'] }} md:hidden">{{ format_number($status['count']) }}</span>
                    </div>

                    <div class="h-8 overflow-hidden rounded-md bg-zinc-100 dark:bg-zinc-800">
                        <div class="flex h-full min-w-8 items-center justify-end rounded-md px-2 text-xs font-semibold text-white {{ $status['bar'] }}" style="width: {{ $status['percent'] }}%">
                            {{ format_number($status['count']) }}
                        </div>
                    </div>

                    <div class="hidden text-right text-sm font-semibold {{ $status['text'] }} md:block">
                        {{ format_number($status['count']) }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
