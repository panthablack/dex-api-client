<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case FAILED = 'failed';

    /**
     * Get all enum values as array
     */
    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    /**
     * Get human-readable label for the status
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending Verification',
            self::VERIFIED => 'Verified',
            self::FAILED => 'Verification Failed',
        };
    }

    /**
     * Check if status represents a completed verification attempt
     */
    public function isProcessed(): bool
    {
        return $this === self::VERIFIED || $this === self::FAILED;
    }

    /**
     * Check if status represents successful verification
     */
    public function isSuccessful(): bool
    {
        return $this === self::VERIFIED;
    }
}