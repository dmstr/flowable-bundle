<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Client;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiPlatformUtils\Service\UuidResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves the FlowableClient to use for a request from an ApiConfiguration of
 * type "flowable" (design D4):
 *   1. explicit id (full or partial UUID via UuidResolver),
 *   2. implicit when exactly one active "flowable" configuration exists,
 *   3. otherwise a 400 with guidance.
 *
 * Clients are cached per resolved configuration for the lifetime of the
 * request (the locator itself is a per-request service instance).
 */
final class FlowableClientLocator
{
    private const TYPE = 'flowable';

    /** @var array<string, FlowableClientInterface> */
    private array $cache = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly UuidResolver $uuidResolver,
    ) {
    }

    public function resolve(?string $apiConfigurationId = null): FlowableClientInterface
    {
        $configuration = $apiConfigurationId !== null && $apiConfigurationId !== ''
            ? $this->resolveExplicit($apiConfigurationId)
            : $this->resolveImplicit();

        $key = (string) $configuration->getId();

        return $this->cache[$key] ??= $this->createClient($configuration);
    }

    private function resolveExplicit(string $apiConfigurationId): ApiConfiguration
    {
        $configuration = $this->uuidResolver->findByPartialUuid(ApiConfiguration::class, $apiConfigurationId);

        if (!$configuration instanceof ApiConfiguration) {
            throw new BadRequestHttpException(sprintf('No API configuration matches "%s".', $apiConfigurationId));
        }
        if ($configuration->getType() !== self::TYPE) {
            throw new BadRequestHttpException(sprintf(
                'API configuration "%s" is of type "%s", expected "%s".',
                $apiConfigurationId,
                $configuration->getType(),
                self::TYPE,
            ));
        }

        return $configuration;
    }

    private function resolveImplicit(): ApiConfiguration
    {
        $candidates = $this->entityManager
            ->getRepository(ApiConfiguration::class)
            ->findBy(['type' => self::TYPE, 'active' => true]);

        if (\count($candidates) === 1) {
            return $candidates[0];
        }
        if ($candidates === []) {
            throw new BadRequestHttpException(
                'No active Flowable API configuration found. Create one or pass apiConfiguration explicitly.',
            );
        }

        throw new BadRequestHttpException(
            'Multiple active Flowable API configurations exist. Pass apiConfiguration to select one.',
        );
    }

    private function createClient(ApiConfiguration $configuration): FlowableClientInterface
    {
        $config = $configuration->getConfigJson();

        if (!isset($config['base_url'], $config['auth_type'])) {
            throw new BadRequestHttpException('Flowable API configuration is missing base_url or auth_type.');
        }

        return new FlowableClient(
            httpClient: $this->httpClient,
            baseUrl: (string) $config['base_url'],
            authType: (string) $config['auth_type'],
            username: $config['username'] ?? null,
            password: $config['password'] ?? null,
            token: $config['token'] ?? null,
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
        );
    }
}
