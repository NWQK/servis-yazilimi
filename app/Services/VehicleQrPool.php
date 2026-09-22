<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleQrCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehicleQrPool
{
    public const TARGET = 10;

    public function replenish(int $parentId): void
    {
        DB::transaction(function () use ($parentId) {
            $this->lockOwner($parentId);
            $this->fill($parentId);
        }, 5);
    }

    public function createVehicle(int $parentId, int $qrId, callable $create): Vehicle
    {
        return DB::transaction(function () use ($parentId, $qrId, $create) {
            $this->lockOwner($parentId);
            $qr = $this->readyCode($parentId, $qrId);
            $vehicle = $create();
            abort_unless((int) $vehicle->parent_id === $parentId, 404);
            $this->assign($qr, $vehicle);
            $this->fill($parentId);
            return $vehicle;
        }, 5);
    }

    public function assignExisting(int $parentId, int $vehicleId, int $qrId): void
    {
        DB::transaction(function () use ($parentId, $vehicleId, $qrId) {
            $this->lockOwner($parentId);
            $vehicle = Vehicle::where('parent_id', $parentId)->lockForUpdate()->findOrFail($vehicleId);
            if ($vehicle->qrCode()->exists()) {
                throw ValidationException::withMessages(['qr_code_id' => 'Bu araca zaten bir QR atanmış.']);
            }
            $this->assign($this->readyCode($parentId, $qrId), $vehicle);
            $this->fill($parentId);
        }, 5);
    }

    public function markPrinted(int $parentId, array $ids): void
    {
        DB::transaction(function () use ($parentId, $ids) {
            $this->lockOwner($parentId);
            $codes = VehicleQrCode::where('parent_id', $parentId)->available()->whereIn('id', $ids)->get();
            if ($codes->count() !== count(array_unique($ids))) {
                throw ValidationException::withMessages(['ids' => 'Seçilen QR kodlarından biri artık boşta değil. Listeyi yenileyin.']);
            }
            VehicleQrCode::whereIn('id', $codes->pluck('id'))->whereNull('printed_at')->update(['printed_at' => now()]);
        }, 5);
    }

    private function lockOwner(int $parentId): void
    {
        // One stable row serializes initialization, assignment and stock replenishment,
        // including when there are no QR rows yet. Requires transactional InnoDB tables.
        User::whereKey($parentId)->lockForUpdate()->firstOrFail();
    }

    private function readyCode(int $parentId, int $qrId): VehicleQrCode
    {
        $qr = VehicleQrCode::where('parent_id', $parentId)->available()
            ->whereNotNull('printed_at')->lockForUpdate()->find($qrId);
        if (!$qr) {
            throw ValidationException::withMessages(['qr_code_id' => 'QR kullanılmış, basılmamış veya bu işletmeye ait değil. Boşta ve basılmış bir etiket seçin.']);
        }
        return $qr;
    }

    private function assign(VehicleQrCode $qr, Vehicle $vehicle): void
    {
        $qr->update(['vehicle_id' => $vehicle->id, 'assigned_at' => now()]);
    }

    private function fill(int $parentId): void
    {
        $missing = self::TARGET - VehicleQrCode::where('parent_id', $parentId)->available()->count();
        for ($i = 0; $i < $missing; $i++) {
            VehicleQrCode::create(['parent_id' => $parentId, 'token' => bin2hex(random_bytes(32))]);
        }
    }
}
