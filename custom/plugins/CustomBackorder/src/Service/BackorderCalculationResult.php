<?php declare(strict_types=1);

namespace CustomBackorder\Service;

final class BackorderCalculationResult
{
    public function __construct(
        public readonly int $requestedQty,
        public readonly int $physicalQty,
        public readonly int $backorderQty,
        public readonly bool $allowed,
        public readonly ?string $errorReason = null,
    ) {
    }

    public function isBackordered(): bool
    {
        return $this->backorderQty > 0;
    }
}

