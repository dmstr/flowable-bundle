<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC, revised 2026-10-08 22:19:20 UTC (decrypt secrets via ConfigSecrets)

declare(strict_types=1);

namespace Dmstr\Flowable\Client;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\ApiConfiguration\Security\ConfigSecrets;
use Dmstr\ApiConfiguration\Security\SecretEncryptionException;
use Dmstr\Flowable\Schema\InputSchemaLoader;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves the FlowableClient to use for a request from an ApiConfiguration of
 * type "flowable" (design D4):
 *   1. explicit selector: the first 8 characters or the full UUID of an
 *      active "flowable" configuration, lowercase, the same `uuidSelector`
 *      pattern the input schemas carry (src/Schema/api-configuration.json);
 *   2. implicit when exactly one active "flowable" configuration exists,
 *   3. otherwise a 400 with guidance.
 *
 * An explicit selector is checked for its format before any lookup and then
 * compared in PHP with the ids of the active "flowable" configurations, so no
 * wildcard reaches the database. A malformed selector, no match (unknown id,
 * another type, inactive) and an 8-character prefix shared by two
 * configurations all end in the same 400 ({@see self::INVALID_SELECTION}),
 * which names no other configuration and no other type. This is the one
 * place the selection happens for HTTP, MCP, the --api-configuration option
 * of the console commands and the external worker.
 *
 * Clients are cached per resolved configuration for the lifetime of the
 * request (the locator itself is a per-request service instance).
 *
 * The secrets `password` and `token` are marked `writeOnly` in schema.json, so
 * dmstr/api-configuration-bundle stores them encrypted (`enc:v1:...`). They are
 * decrypted here, right before the client is built, and never leave it.
 *
 * The `flowable` logger is handed to every client it builds, which writes the
 * error-log entry behind each generic 502/504 there (see FlowableClient).
 */
final class FlowableClientLocator
{
    private const TYPE = 'flowable';

    public const string INVALID_SELECTION = 'apiConfiguration does not select an active Flowable API configuration. '
        .'Pass the first 8 characters or the full UUID (lowercase) of exactly one active Flowable configuration.';

    /** @var array<string, FlowableClientInterface> */
    private array $cache = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigSecrets $secrets,
        private readonly LoggerInterface $flowableLogger = new NullLogger(),
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

    private function resolveExplicit(string $selector): ApiConfiguration
    {
        if (1 !== preg_match(self::selectorPattern(), $selector)) {
            throw new BadRequestHttpException(self::INVALID_SELECTION);
        }

        // The pattern allows 8 or 36 characters, so a prefix match is an
        // exact match for a full UUID.
        $matches = array_values(array_filter(
            $this->activeConfigurations(),
            static fn (ApiConfiguration $configuration): bool => str_starts_with((string) $configuration->getId(), $selector),
        ));

        if (1 !== \count($matches)) {
            throw new BadRequestHttpException(self::INVALID_SELECTION);
        }

        return $matches[0];
    }

    /**
     * The `uuidSelector` pattern of the shared schema definition as a PCRE
     * pattern; `D` keeps `$` from matching before a trailing newline.
     */
    private static function selectorPattern(): string
    {
        return '~'.InputSchemaLoader::definition('uuidSelector')['pattern'].'~D';
    }

    /** @return list<ApiConfiguration> */
    private function activeConfigurations(): array
    {
        return array_values($this->entityManager
            ->getRepository(ApiConfiguration::class)
            ->findBy(['type' => self::TYPE, 'active' => true]));
    }

    private function resolveImplicit(): ApiConfiguration
    {
        $candidates = $this->activeConfigurations();

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

    /**
     * @throws SecretEncryptionException if a stored secret cannot be decrypted
     *         (missing or changed CREDENTIALS_ENCRYPTION_KEY, corrupted value);
     *         there is no fallback to the stored ciphertext
     */
    private function createClient(ApiConfiguration $configuration): FlowableClientInterface
    {
        try {
            $config = $this->secrets->decrypt($configuration->getConfigJson());
        } catch (SecretEncryptionException $e) {
            throw new SecretEncryptionException(
                sprintf(
                    'Cannot decrypt the credentials of Flowable API configuration "%s": %s',
                    (string) $configuration->getId(),
                    $e->getMessage(),
                ),
                0,
                $e,
            );
        }

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
            flowableLogger: $this->flowableLogger,
        );
    }
}
// - revised 2026-11-26 (strict explicit selector, lookup without UuidResolver)
// - revised 2026-11-26 (flowable logger passed to the clients)
