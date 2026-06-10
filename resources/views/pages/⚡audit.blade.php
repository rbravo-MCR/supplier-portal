<?php

use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Auditoría')] class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function bookings(): LengthAwarePaginator
    {
        return Booking::query()
            ->with('supplier:id,name,code')
            ->forSupplier(Auth::user()->supplier_id)
            ->when($this->search !== '', function (Builder $query): void {
                $query->search($this->search);
            })
            ->orderByDesc('created_at')
            ->paginate(10);
    }

    /**
     * @return array{outlet_total: int, supplier_pending: int, supplier_confirmed: int}
     */
    #[Computed]
    public function totals(): array
    {
        $supplierId = Auth::user()->supplier_id;
        $cacheKey = "audit.totals.{$supplierId}";

        return cache()->remember($cacheKey, 60, function () use ($supplierId): array {
            $query = Booking::query()
                ->when($supplierId, fn (Builder $query, int $id) => $query->where('supplier_id', $id));

            $totals = $query
                ->selectRaw('COUNT(id) as outlet_total')
                ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as supplier_pending")
                ->selectRaw("SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as supplier_confirmed")
                ->first();

            return [
                'outlet_total' => (int) ($totals?->outlet_total ?? 0),
                'supplier_pending' => (int) ($totals?->supplier_pending ?? 0),
                'supplier_confirmed' => (int) ($totals?->supplier_confirmed ?? 0),
            ];
        });
    }

};
?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Auditoría') }}</flux:heading>
        <flux:text class="max-w-3xl">
            {{ __('Comparativo de reservas generadas por Outlet contra las reservas pendientes y confirmadas por cada supplier.') }}
        </flux:text>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text>{{ __('Reservas Outlet') }}</flux:text>
            <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-white">{{ $this->totals['outlet_total'] }}</div>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text>{{ __('Pendientes supplier') }}</flux:text>
            <div class="mt-2 text-3xl font-semibold text-amber-600 dark:text-amber-400">{{ $this->totals['supplier_pending'] }}</div>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text>{{ __('Confirmadas supplier') }}</flux:text>
            <div class="mt-2 text-3xl font-semibold text-emerald-600 dark:text-emerald-400">{{ $this->totals['supplier_confirmed'] }}</div>
        </div>
    </div>

    <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <flux:heading>{{ __('Desglose de reservas') }}</flux:heading>
                <flux:text>{{ __('Cada reserva generada por Outlet con su estado actual en el supplier.') }}</flux:text>
            </div>

            <flux:input wire:model.live.debounce.300ms="search" :label="__('Buscar')" placeholder="Reserva o estado" class="md:w-80" data-test="audit-search" />
        </div>

        <flux:table :paginate="$this->bookings">
            <flux:table.columns>
                <flux:table.column>{{ __('Reserva Outlet') }}</flux:table.column>
                <flux:table.column>{{ __('Número reserva') }}</flux:table.column>
                <flux:table.column>{{ __('Fecha reserva') }}</flux:table.column>
                <flux:table.column>{{ __('Estado supplier') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->bookings as $booking)
                    <flux:table.row :key="$booking->id">
                        <flux:table.cell>{{ $booking->reservation_code }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $booking->status === 'confirmed' ? $booking->reservation_code : __('Falta número reserva') }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $booking->created_at?->format('d/m/Y H:i') ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$booking->status === 'confirmed' ? 'green' : 'amber'">
                                {{ $booking->status === 'confirmed' ? __('Confirmada') : __('Pendiente') }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            <div class="py-8 text-center">
                                <flux:text>{{ __('No hay reservas para comparar.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
