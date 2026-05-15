<?php

namespace App\Domain\Machinery\Enums;

enum MachineryPaymentStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case LOCKED = 'locked';
    case REJECTED = 'rejected';
    case PAID = 'paid';
    case HOLD = 'hold';

    public function canTransitionTo(self $status): bool
    {
        return match($this) {
            self::DRAFT => in_array($status, [self::SUBMITTED, self::REJECTED]),
            self::SUBMITTED => in_array($status, [self::APPROVED, self::REJECTED]),
            self::APPROVED => in_array($status, [self::LOCKED, self::PAID]),
            self::LOCKED => in_array($status, [self::PAID]),
            self::HOLD => in_array($status, [self::SUBMITTED, self::REJECTED]), // Hold can be resubmitted after correction
            self::REJECTED => false, // Terminal
            self::PAID => false, // Terminal
        };
    }
    
    public function isFinal(): bool
    {
        return in_array($this, [self::REJECTED, self::PAID]);
    }
    
    public function isLocked(): bool
    {
        return in_array($this, [self::APPROVED, self::PAID]);
    }
    
    public function isTerminal(): bool
    {
        return in_array($this, [self::REJECTED, self::PAID, self::HOLD]);
    }
}
