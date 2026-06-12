<?php

namespace App\Modules\Booking\Application\UseCases;

use App\Jobs\RecordAuditLog;
use App\Models\Booking;
use App\Models\User;
use App\Modules\Booking\Application\Contracts\BookingRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

class ViewBooking
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly BookingRepository $bookings,
    ) {}

    /**
     * View a booking if the actor is authorized.
     */
    public function handle(int $bookingId, User $actor): Booking
    {
        $booking = $this->bookings->find($bookingId);

        if (! $booking instanceof Booking) {
            throw (new ModelNotFoundException)->setModel(Booking::class, [$bookingId]);
        }

        if (! Gate::forUser($actor)->allows('view', $booking)) {
            dispatch(new RecordAuditLog([
                'supplier_id' => $booking->supplier_id,
                'user_id' => $actor->id,
                'module' => 'booking',
                'action' => 'access_denied',
                'entity_type' => Booking::class,
                'entity_id' => $booking->id,
                'old_values' => null,
                'new_values' => [
                    'actor_supplier_id' => $actor->supplier_id,
                    'booking_supplier_id' => $booking->supplier_id,
                ],
            ]));

            throw new AuthorizationException;
        }

        return $booking;
    }
}
