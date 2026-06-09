<?php

namespace App\Modules\Booking\Application\UseCases;

use App\Jobs\DispatchOutboxEvent;
use App\Jobs\RecordAuditLog;
use App\Jobs\RecordBookingAction;
use App\Models\Booking;
use App\Modules\Booking\Application\Contracts\BookingRepository;
use App\Modules\Booking\Application\DTOs\RejectBookingData;
use App\Modules\Booking\Domain\Exceptions\BookingMustBePending;
use App\Shared\Support\SupplierContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectBooking
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly SupplierContext $supplierContext,
    ) {}

    /**
     * Reject a pending booking for the authenticated supplier.
     */
    public function handle(RejectBookingData $data): Booking
    {
        $reason = trim($data->reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => __('A rejection reason is required.'),
            ]);
        }

        $supplierId = $this->supplierContext->id();
        $booking = $this->bookings->findForSupplier($data->bookingId, $supplierId);

        if (! $booking instanceof Booking) {
            throw (new ModelNotFoundException())->setModel(Booking::class, [$data->bookingId]);
        }

        if ($booking->status !== 'pending') {
            throw BookingMustBePending::forOperation('rejected');
        }

        return DB::transaction(function () use ($booking, $data, $reason, $supplierId): Booking {
            $rejected = $this->bookings->updateStatus($booking, 'rejected');

            dispatch(new RecordBookingAction([
                'booking_id' => $rejected->id,
                'supplier_id' => $supplierId,
                'user_id' => $data->userId,
                'action' => 'rejected',
                'reason' => $reason,
            ]));

            dispatch(new RecordAuditLog([
                'supplier_id' => $supplierId,
                'user_id' => $data->userId,
                'module' => 'booking',
                'action' => 'rejected',
                'entity_type' => Booking::class,
                'entity_id' => $rejected->id,
                'old_values' => ['status' => 'pending'],
                'new_values' => ['status' => 'rejected', 'reason' => $reason],
            ]));

            $occurredAt = now()->toISOString();

            dispatch(new DispatchOutboxEvent([
                'aggregate_type' => Booking::class,
                'aggregate_id' => $rejected->id,
                'event_type' => 'BookingRejected',
                'payload' => [
                    'booking_uuid' => $rejected->uuid,
                    'supplier_id' => $supplierId,
                    'user_id' => $data->userId,
                    'reason' => $reason,
                    'rejected_at' => $occurredAt,
                    'occurred_at' => $occurredAt,
                ],
                'status' => 'pending',
                'available_at' => now(),
            ]));

            return $rejected;
        });
    }
}
