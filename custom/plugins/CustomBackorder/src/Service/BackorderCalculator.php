<?php declare(strict_types=1);

namespace CustomBackorder\Service;

class BackorderCalculator
{
    public const REASON_DISABLED = 'backorder_disabled';
    public const REASON_MIXED_NOT_ALLOWED = 'mixed_not_allowed';
    public const REASON_LIMIT_EXCEEDED = 'limit_exceeded';

    /**
     * Computes physical vs backordered quantities and validates policy rules.
     */
    public function calculate(
        int $physicalStock,
        int $reservedForBackorders,
        int $requestedQty,
        bool $backorderEnabled,
        bool $allowMixed,
        ?int $backorderLimit,
        int $openBackorderQtyForProduct = 0
    ): BackorderCalculationResult {
        $availablePhysical = max(0, $physicalStock - $reservedForBackorders);

        if ($requestedQty <= $availablePhysical) {
            return new BackorderCalculationResult(
                requestedQty: $requestedQty,
                physicalQty: $requestedQty,
                backorderQty: 0,
                allowed: true
            );
        }

        if (!$backorderEnabled) {
            return new BackorderCalculationResult(
                requestedQty: $requestedQty,
                physicalQty: $availablePhysical,
                backorderQty: $requestedQty - $availablePhysical,
                allowed: false,
                errorReason: self::REASON_DISABLED
            );
        }

        if ($availablePhysical > 0 && !$allowMixed) {
            return new BackorderCalculationResult(
                requestedQty: $requestedQty,
                physicalQty: 0,
                backorderQty: $requestedQty,
                allowed: false,
                errorReason: self::REASON_MIXED_NOT_ALLOWED
            );
        }

        $physicalQty = min($requestedQty, $availablePhysical);
        $backorderQty = $requestedQty - $physicalQty;

        if ($backorderLimit !== null && ($openBackorderQtyForProduct + $backorderQty) > $backorderLimit) {
            return new BackorderCalculationResult(
                requestedQty: $requestedQty,
                physicalQty: $physicalQty,
                backorderQty: $backorderQty,
                allowed: false,
                errorReason: self::REASON_LIMIT_EXCEEDED
            );
        }

        return new BackorderCalculationResult(
            requestedQty: $requestedQty,
            physicalQty: $physicalQty,
            backorderQty: $backorderQty,
            allowed: true
        );
    }
}

