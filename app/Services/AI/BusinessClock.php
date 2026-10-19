<?php
namespace App\Services\AI;

final class BusinessClock
{
    public function __construct(private string $timezone = 'Asia/Jakarta', private ?\DateTimeImmutable $fixed = null) {}
    public function now(): \DateTimeImmutable { return ($this->fixed ?? new \DateTimeImmutable('now', new \DateTimeZone($this->timezone)))->setTimezone(new \DateTimeZone($this->timezone)); }
}
