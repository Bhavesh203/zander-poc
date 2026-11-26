<?php declare(strict_types=1);

namespace CustomBackorder;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CustomBackorder extends Plugin
{
    public const CUSTOM_FIELD_SET_NAME = 'custom_backorder_set';
    public const FIELD_ENABLED = 'custom_backorder_enabled';
    public const FIELD_MESSAGE = 'custom_backorder_message';
    public const FIELD_EXPECTED_RESTOCK = 'custom_backorder_expected_restock_at';
    public const FIELD_LIMIT = 'custom_backorder_limit';
    public const FIELD_ALLOW_MIXED = 'custom_backorder_allow_mixed';

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $this->ensureCustomFields($installContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);
        $this->ensureCustomFields($updateContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->removeCustomFields($uninstallContext->getContext());

        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);
        $connection->executeStatement('DROP TABLE IF EXISTS `custom_backorder`');
    }

    private function ensureCustomFields(Context $context): void
    {
        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELD_SET_NAME));

        $existingIds = $customFieldSetRepository->searchIds($criteria, $context);
        if ($existingIds->getTotal() > 0) {
            return;
        }

        $customFieldSetRepository->upsert([
            [
                'name' => self::CUSTOM_FIELD_SET_NAME,
                'config' => [
                    'label' => [
                        'en-GB' => 'Backorder Settings',
                        'de-DE' => 'Nachbestellungseinstellungen',
                    ],
                ],
                'relations' => [
                    ['entityName' => 'product'],
                ],
                'customFields' => [
                    [
                        'name' => self::FIELD_ENABLED,
                        'type' => CustomFieldTypes::BOOL,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Enable backorders',
                                'de-DE' => 'Nachbestellungen aktivieren',
                            ],
                            'customFieldPosition' => 1,
                        ],
                    ],
                    [
                        'name' => self::FIELD_MESSAGE,
                        'type' => CustomFieldTypes::TEXT,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Backorder message',
                                'de-DE' => 'Nachbestellungs-Hinweis',
                            ],
                            'placeholder' => [
                                'en-GB' => 'Available on backorder',
                            ],
                            'customFieldPosition' => 2,
                        ],
                    ],
                    [
                        'name' => self::FIELD_EXPECTED_RESTOCK,
                        'type' => CustomFieldTypes::DATETIME,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Expected restock date',
                                'de-DE' => 'Voraussichtliches Lieferdatum',
                            ],
                            'dateType' => 'date',
                            'customFieldPosition' => 3,
                        ],
                    ],
                    [
                        'name' => self::FIELD_LIMIT,
                        'type' => CustomFieldTypes::INT,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Backorder limit (blank = unlimited)',
                                'de-DE' => 'Nachbestellungslimit (leer = unbegrenzt)',
                            ],
                            'min' => 0,
                            'customFieldPosition' => 4,
                        ],
                    ],
                    [
                        'name' => self::FIELD_ALLOW_MIXED,
                        'type' => CustomFieldTypes::BOOL,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Allow mixed stock/backorder',
                                'de-DE' => 'Gemischte Lager-/Nachbestellung erlauben',
                            ],
                            'customFieldPosition' => 5,
                        ],
                    ],
                ],
            ],
        ], $context);
    }

    private function removeCustomFields(Context $context): void
    {
        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELD_SET_NAME));

        $ids = $customFieldSetRepository->searchIds($criteria, $context)->getIds();
        if ($ids === []) {
            return;
        }

        $deletePayload = array_map(static fn (string $id): array => ['id' => $id], $ids);
        $customFieldSetRepository->delete($deletePayload, $context);
    }
}

