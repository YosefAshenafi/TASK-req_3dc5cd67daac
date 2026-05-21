<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->maskEmail($this->email),
            'role' => $this->role,
            'account_status' => $this->account_status,
            'frozen_until' => $this->frozen_until?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '[REDACTED]';
        }
        $local = $parts[0];
        $domain = $parts[1];
        $visible = substr($local, 0, min(2, strlen($local)));
        $masked = $visible . str_repeat('*', max(0, strlen($local) - 2));
        return $masked . '@' . $domain;
    }
}
