<?php

namespace App;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REFUSED = 'refused';
    
    /**
     * Check if a status transition is valid
     */
    public function canTransitionTo(PaymentStatus $newStatus): bool
    {
        return match($this) {
            self::PENDING => in_array($newStatus, [self::ACCEPTED, self::REFUSED]),
            self::ACCEPTED, self::REFUSED => false,
        };
    }
    
    /**
     * Check if the status is terminal (cannot be changed)
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::ACCEPTED, self::REFUSED]);
    }
}
