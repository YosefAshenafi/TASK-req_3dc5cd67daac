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

        // Only look at events beyond the already-confirmed counter; this avoids
        // re-walking all history on every reconciliation and correctly handles the
        // case where an in-order event later closes a previously-seen gap.
        $events = DeviceEvent::where('device_id', $this->deviceId)
            ->where('sequence_no', '>', $device->last_sequence_no)
            ->whereIn('status', ['accepted', 'out_of_order'])
            ->orderBy('sequence_no')
            ->pluck('sequence_no')
            ->toArray();

        if (empty($events)) {
            return;
        }

        $maxContiguous = (int) $device->last_sequence_no;
        foreach ($events as $seq) {
            if ($seq === $maxContiguous + 1) {
                $maxContiguous = $seq;
            } else {
                break; // gap still present — stop here
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
