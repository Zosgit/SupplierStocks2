<?php

namespace App\Factory;

use App\Entity\StockItem;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<StockItem>
 */
final class StockItemFactory extends PersistentProxyObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
    }

    public static function class(): string
    {
        return StockItem::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
             'externalId' => self::faker()->text(128),
            'mpn' => self::faker()->text(128),
            'producerName' => self::faker()->text(128),
            'ean' => self::faker()->ean13(),
            'price' => self::faker()->randomFloat(2, 10, 100),
            'quantity' => self::faker()->numberBetween(1, 31),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(StockItem $stockItem): void {})
        ;
    }
}
