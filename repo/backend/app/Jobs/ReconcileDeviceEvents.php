<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\DeviceEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileDeviceEvents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $deviceId
    ) {}

    public function handle(): void
    {
        $device = Device::find($this->deviceId);

        if (! $device) {
            Log::warning("ReconcileDeviceEvents: Device {$this->deviceId} not found.");
            return;
        }

        // Find the highest contiguous sequence number
        $events = DeviceEvent::where('device_id', $this->deviceId)
            ->orderBy('sequence_no')
            ->pluck('sequence_no')
            ->toArray();

        if (empty($events)) {
            return;
        }

        $maxContiguous = 0;
        foreach ($events as $seq) {
            if ($seq === $maxContiguous + 1) {
                $maxContiguous = $seq;
            } elseif ($seq > $maxContiguous + 1) {
                break;
            }
        }

        // Update the device's last_sequence_no to the highest contiguous value
        if ($maxContiguous > $device->last_sequence_no) {
            DB::table('devices')
                ->where('id', $this->deviceId)
                ->update([
                    'last_sequence_no' => $maxContiguous,
                    'last_seen_at'     => now(),
                ]);
        }

        Log::info("ReconcileDeviceEvents: Reconciled device {$this->deviceId}, last_sequence_no={$maxContiguous}.");
    }
}
