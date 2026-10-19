<?php
namespace App\Services\AI;

/** A known grammar limitation, never permission to execute an unvalidated query. */
final class UnsupportedSqlException extends \InvalidArgumentException
{
    public function __construct(public readonly string $reasonCode, string $message, public readonly string $repairHint)
    {
        parent::__construct($message);
    }
}
