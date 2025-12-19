<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\EventSubscriber;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Symfony\Bridge\Doctrine\SchemaListener\AbstractSchemaListener;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Tourze\DoctrineSessionBundle\Service\PdoSessionHandler;

#[AutoconfigureTag(name: 'doctrine.event_listener', attributes: ['event' => 'postGenerateSchema'])]
final class PdoSessionHandlerSchemaListener extends AbstractSchemaListener
{
    public function __construct(private readonly PdoSessionHandler $sessionHandler)
    {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $connection = $event->getEntityManager()->getConnection();

        $this->sessionHandler->configureSchema($event->getSchema(), $this->getIsSameDatabaseChecker($connection));
    }
}
