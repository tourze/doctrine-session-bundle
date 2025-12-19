<?php

declare(strict_types=1);

namespace Tourze\DoctrineSessionBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Tourze\DoctrineSessionBundle\Exception\InvalidArgumentException;
use Tourze\DoctrineSessionBundle\Storage\HttpSessionStorage;

#[WithMonologChannel(channel: 'doctrine_session')]
#[Autoconfigure(public: true)]
final class HttpSessionStorageFactory implements SessionStorageFactoryInterface
{
    private MetadataBag $metaBag;

    public function __construct(
        private readonly PdoSessionHandler $handler,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'session.metadata.storage_key')] string $storageKey = '_sf2_meta',
        #[Autowire(param: 'session.metadata.update_threshold')] int $updateThreshold = 0,
        #[Autowire(value: '%env(resolve:default:default_session_name:DOCTRINE_SESSION_NAME)%')] private readonly string $sessionName = 'PHPSESSID',
    ) {
        $this->metaBag = new MetadataBag($storageKey, $updateThreshold);
    }

    private function getSessionName(): string
    {
        return $this->sessionName;
    }

    public function createStorage(?Request $request): SessionStorageInterface
    {
        if (null === $request) {
            throw new InvalidArgumentException('Request cannot be null');
        }

        return new HttpSessionStorage(
            $this->logger,
            $this->handler,
            $this->getSessionName(),
            null, // sessionId will be extracted from request
            $request,
            $this->metaBag,
        );
    }
}
