<?php declare(strict_types=1);

namespace CustomBackorder\Core\Content\Backorder;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BackorderEntity>
 */
class BackorderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return BackorderEntity::class;
    }
}

