<?php

use App\Jobs\DispatchOutboxEvent;
use App\Jobs\RecordAuditLog;
use App\Jobs\RecordBookingAction;
use App\Models\Booking;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Reservas')] class extends Component {
    use WithPagination;

    /**
     * @var array<int, string>
     */
    public array $reservationCodes = [];

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function confirm(int $bookingId): void
    {
        $booking = $this->pendingBookingQuery()
            ->whereKey($bookingId)
            ->firstOrFail();

        Gate::authorize('update', $booking);

        $validated = $this->validate([
            "reservationCodes.{$bookingId}" => ['required', 'string', 'max:255'],
        ], [
            "reservationCodes.{$bookingId}.required" => __('Captura el número de reserva.'),
        ]);

        $reservationCode = str($validated['reservationCodes'][$bookingId])
            ->trim()
            ->upper()
            ->toString();

        DB::transaction(function () use ($booking, $reservationCode): void {
            $oldValues = [
                'reservation_code' => $booking->reservation_code,
                'status' => $booking->status,
            ];

            $booking->forceFill([
                'reservation_code' => $reservationCode,
                'status' => 'confirmed',
            ])->save();

            dispatch(new RecordBookingAction([
                'booking_id' => $booking->id,
                'supplier_id' => $booking->supplier_id,
                'user_id' => Auth::id(),
                'action' => 'confirmed',
                'metadata' => ['manual_reservation_code' => $reservationCode],
            ]));

            dispatch(new RecordAuditLog([
                'supplier_id' => $booking->supplier_id,
                'user_id' => Auth::id(),
                'module' => 'booking',
                'action' => 'confirmed',
                'entity_type' => Booking::class,
                'entity_id' => $booking->id,
                'old_values' => $oldValues,
                'new_values' => [
                    'reservation_code' => $reservationCode,
                    'status' => 'confirmed',
                ],
            ]));

            $occurredAt = now()->toISOString();

            dispatch(new DispatchOutboxEvent([
                'aggregate_type' => Booking::class,
                'aggregate_id' => $booking->id,
                'event_type' => 'BookingConfirmed',
                'payload' => [
                    'booking_uuid' => $booking->uuid,
                    'supplier_id' => $booking->supplier_id,
                    'user_id' => Auth::id(),
                    'reservation_code' => $reservationCode,
                    'confirmed_at' => $occurredAt,
                    'occurred_at' => $occurredAt,
                ],
                'status' => 'pending',
                'available_at' => now(),
            ]));
        });

        unset($this->reservationCodes[$bookingId]);
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('Reserva confirmada.'));
    }

    #[Computed]
    public function bookings(): LengthAwarePaginator
    {
        return $this->pendingBookingQuery()
            ->when($this->search !== '', function (Builder $query): void {
                $query->search($this->search);
            })
            ->orderBy('pickup_at')
            ->paginate(10);
    }

    private function pendingBookingQuery(): Builder
    {
        return Booking::query()
            ->with('supplier:id,name,code')
            ->pending()
            ->forSupplier(Auth::user()->supplier_id);
    }
};
?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Reservas') }}</flux:heading>
        <flux:text class="max-w-3xl">
            {{ __('Reservas pendientes generadas por el microservicio de Outlet. Captura el número de reserva manual para confirmar cada solicitud.') }}
        </flux:text>
    </div>

    <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <flux:heading>{{ __('Reservas pendientes') }}</flux:heading>
                <flux:text>{{ __('Solo se muestran reservas disponibles para confirmación manual.') }}</flux:text>
            </div>

            <flux:input wire:model.live.debounce.300ms="search" :label="__('Buscar')" placeholder="Cliente, oficina o código" class="md:w-80" data-test="booking-search" />
        </div>

        <flux:table :paginate="$this->bookings">
            <flux:table.columns>
                <flux:table.column>{{ __('Estado') }}</flux:table.column>
                <flux:table.column>{{ __('Reserva Outlet') }}</flux:table.column>
                <flux:table.column>{{ __('Cliente') }}</flux:table.column>
                <flux:table.column>{{ __('Vehículo') }}</flux:table.column>
                <flux:table.column>{{ __('Entrega') }}</flux:table.column>
                <flux:table.column>{{ __('Devolución') }}</flux:table.column>
                <flux:table.column>{{ __('Total') }}</flux:table.column>
                <flux:table.column>{{ __('Número manual') }}</flux:table.column>
                <flux:table.column>{{ __('Acción') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->bookings as $booking)
                    <flux:table.row :key="$booking->id">
                        <flux:table.cell>
                            <flux:badge color="amber">{{ __('Pendiente') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="font-medium">{{ $booking->reservation_code }}</span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $booking->supplier?->code ?? '-' }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $booking->customer_name }}</flux:table.cell>
                        <flux:table.cell>{{ $booking->vehicle_class ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span>{{ $booking->pickup_office_code }}</span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $booking->pickup_at->format('d/m/Y H:i') }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span>{{ $booking->dropoff_office_code }}</span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $booking->dropoff_at->format('d/m/Y H:i') }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $booking->currency }} {{ number_format((float) $booking->total_amount, 2) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:input
                                wire:model="reservationCodes.{{ $booking->id }}"
                                placeholder="Número de reserva"
                                data-test="booking-manual-code-{{ $booking->id }}"
                            />
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                wire:click="confirm({{ $booking->id }})"
                                variant="primary"
                                icon="check"
                                data-test="booking-confirm-{{ $booking->id }}"
                            >
                                {{ __('Confirmar') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9">
                            <div class="py-8 text-center">
                                <flux:text>{{ __('No hay reservas pendientes por confirmar.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
