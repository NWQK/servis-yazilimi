<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AppointmentBooking;

class ExpireAppointments extends Command
{
    protected $signature = 'appointments:expire';
    protected $description = 'Yanıt süresi dolan bekleyen randevu taleplerini kapatır.';
    public function handle(AppointmentBooking $booking): int
    {
        $this->info($booking->expirePending().' randevu talebinin süresi doldu.');
        return self::SUCCESS;
    }
}
