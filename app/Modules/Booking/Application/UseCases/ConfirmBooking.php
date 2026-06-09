<?php

namespace App\Modules\Booking\Application\UseCases;

use App\Jobs\DispatchOutboxEvent;
use App\Jobs\RecordAuditLog;
use App\Jobs\RecordBookingAction;
use App\Models\Booking;
use App\Modules\Booking\Application\Contracts\BookingRepository;
use App\Modules\Booking\Application\DTOs\ConfirmBookingData;
use App\Modules\Booking\Domain\Exceptions\BookingMustBePending;
use App\Shared\Support\SupplierContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ConfirmBooking
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly SupplierContext $supplierContext,
    ) {}

    /**
     * Confirm a pending booking for the authenticated supplier.
     */
    public function handle(ConfirmBookingData $data): Booking
    {
        $supplierId = $this->supplierContext->id();
        $booking = $this->bookings->findForSupplier($data->bookingId, $supplierId);

        if (! $booking instanceof Booking) {
            throw (new ModelNotFoundException())->setModel(Booking::class, [$data->bookingId]);
        }

        if ($booking->status !== 'pending') {
            throw BookingMustBePending::forOperation('confirmed');
        }

        return DB::transaction(function () use ($booking, $data, $supplierId): Booking {
            $confirmed = $this->bookings->updateStatus($booking, 'confirmed');

            dispatch(new RecordBookingAction([
                'booking_id' => $confirmed->id,
                'supplier_id' => $supplierId,
                'user_id' => $data->userId,
                'action' => 'confirmed',
            ]));

            dispatch(new RecordAuditLog([
                'supplier_id' => $supplierId,
                'user_id' => $data->userId,
                'module' => 'booking',
                'action' => 'confirmed',
                'entity_type' => Booking::class,
                'entity_id' => $confirmed->id,
                'old_values' => ['status' => 'pending'],
                'new_values' => ['status' => 'confirmed'],
            ]));

            $occurredAt = now()->toISOString();

            dispatch(new DispatchOutboxEvent([
                'aggregate_type' => Booking::class,
                'aggregate_id' => $confirmed->id,
                'event_type' => 'BookingConfirmed',
                'payload' => [
                    'booking_uuid' => $confirmed->uuid,
                    'supplier_id' => $supplierId,
                    'user_id' => $data->userId,
                    'confirmed_at' => $occurredAt,
                    'occurred_at' => $occurredAt,
                ],
                'status' => 'pending',
                'available_at' => now(),
            ]));

            return $confirmed;
        });
    }
}
