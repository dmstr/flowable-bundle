<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Schema\InputSchemaPath;
use Dmstr\Flowable\Service\FlowableAuditLogger;
use Dmstr\Flowable\Service\FlowableVariableMapper;
use Dmstr\Flowable\Service\InputSchemaValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shared scaffolding for the flowable:* CLI mirror of the REST operations.
 *
 * Common options: --api-configuration (the first 8 characters or the full
 * UUID, lowercase, of an active Flowable ApiConfiguration), and for write
 * commands --acting-user (mandatory, propagated as startUserId — decision
 * 2026-06-16), --input / --input-file (validated against the same bundle-local
 * JSON schemas as the REST endpoints).
 *
 * Write commands run their engine call through {@see self::audited()}, which
 * writes the same audit entries as the REST and MCP operations (attempt, then
 * success or failure; see {@see FlowableAuditLogger}) with via "cli".
 */
abstract class AbstractFlowableCommand extends Command
{
    public function __construct(
        protected readonly FlowableClientLocator $locator,
        protected readonly InputSchemaValidator $validator,
        protected readonly FlowableVariableMapper $variableMapper,
        protected readonly FlowableAuditLogger $auditLogger,
    ) {
        parent::__construct();
    }

    protected function addApiConfigurationOption(): void
    {
        $this->addOption('api-configuration', 'c', InputOption::VALUE_REQUIRED, 'Active Flowable ApiConfiguration: first 8 characters or full UUID, lowercase');
    }

    protected function addWriteOptions(): void
    {
        $this->addApiConfigurationOption();
        $this->addOption('acting-user', 'u', InputOption::VALUE_REQUIRED, 'Acting za7 user UUID (propagated as startUserId)');
        $this->addInputOptions();
    }

    /**
     * JSON payload options without --acting-user, for write commands whose actor
     * is not a za7 user — e.g. the external worker commands, where the identity
     * that matters to the engine is the worker id.
     */
    protected function addInputOptions(): void
    {
        $this->addOption('input', 'i', InputOption::VALUE_REQUIRED, 'Inline JSON input');
        $this->addOption('input-file', 'f', InputOption::VALUE_REQUIRED, 'Path to a JSON input file');
    }

    protected function client(InputInterface $input): FlowableClientInterface
    {
        $id = $input->getOption('api-configuration');

        return $this->locator->resolve($id !== null ? (string) $id : null);
    }

    /**
     * The acting user is mandatory for write commands (decision 2026-06-16):
     * without it the command aborts and nothing is sent to the engine.
     */
    protected function requireActingUser(InputInterface $input): string
    {
        $actor = $input->getOption('acting-user');
        if ($actor === null || $actor === '') {
            throw new \RuntimeException('The --acting-user option is required for write operations.');
        }

        return (string) $actor;
    }

    /**
     * The --acting-user value of a write command that does not require it
     * (null when not given), for the audit entry only.
     */
    protected function actingUser(InputInterface $input): ?string
    {
        $actor = $input->getOption('acting-user');

        return $actor === null || $actor === '' ? null : (string) $actor;
    }

    /**
     * Run the engine call of a write command with its audit entries (via
     * "cli"; see {@see FlowableAuditLogger::audited()}). Call it after the
     * input is read and validated and the client is resolved, with only the
     * engine call in $call: an exception thrown before writes no entry, one
     * thrown by $call a failure entry, and is rethrown unchanged.
     *
     * @template T
     * @param ?string $actor the --acting-user value; the worker id for the
     *   external worker job commands
     * @param array<string,mixed> $auditContext what is known before the call
     * @param \Closure(): T $call the engine call
     * @param (\Closure(T): array<string,mixed>)|null $successContext keys derived from the result
     * @return T
     */
    protected function audited(string $action, ?string $actor, array $auditContext, \Closure $call, ?\Closure $successContext = null): mixed
    {
        return $this->auditLogger->audited(
            $action,
            FlowableAuditLogger::VIA_CLI,
            $actor,
            null,
            $auditContext,
            $call,
            $successContext,
        );
    }

    /**
     * @return array<string,mixed>
     */
    protected function readInput(InputInterface $input, string $schemaPath): array
    {
        return $this->validator->validateRaw($this->rawInput($input), $schemaPath);
    }

    /** The raw JSON from --input or --input-file; empty string when neither is given. */
    protected function rawInput(InputInterface $input): string
    {
        $raw = $input->getOption('input');
        if ($raw === null && ($file = $input->getOption('input-file')) !== null) {
            if (!is_file((string) $file)) {
                throw new \RuntimeException(sprintf('Input file not found: %s', $file));
            }
            $raw = (string) file_get_contents((string) $file);
        }

        return (string) ($raw ?? '');
    }

    /**
     * Decode the JSON input WITHOUT validating it — for commands that merge CLI
     * options into the payload and validate the merged result (see
     * AbstractExternalWorkerJobCommand).
     *
     * @return array<string,mixed>
     */
    protected function decodeInput(InputInterface $input): array
    {
        $raw = trim($this->rawInput($input));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON input: '.json_last_error_msg());
        }
        if (!\is_array($decoded)) {
            throw new \RuntimeException('JSON input must be an object.');
        }

        return $decoded;
    }

    protected function schemaPath(string $entity, string $verb): string
    {
        return InputSchemaPath::forOperation($entity, $verb);
    }

    /**
     * @param array<string,mixed> $envelope
     * @param list<string> $columns
     */
    protected function renderEnvelope(SymfonyStyle $io, array $envelope, array $columns): int
    {
        $rows = \is_array($envelope['data'] ?? null) ? $envelope['data'] : [];
        $io->table(
            $columns,
            array_map(
                fn (array $row): array => array_map(fn (string $c): string => $this->scalar($row[$c] ?? null), $columns),
                $rows,
            ),
        );
        $io->writeln(sprintf(
            '<info>total=%s start=%s size=%s</info>',
            $envelope['total'] ?? '?',
            $envelope['start'] ?? '?',
            $envelope['size'] ?? '?',
        ));

        return Command::SUCCESS;
    }

    /**
     * @param array<string,mixed>|null $data
     */
    protected function renderItem(SymfonyStyle $io, ?array $data): int
    {
        if ($data === null) {
            $io->error('Not found.');

            return Command::FAILURE;
        }
        $io->writeln((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }

    private function scalar(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            \is_bool($value) => $value ? 'true' : 'false',
            \is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_SLASHES),
        };
    }
}
// - revised 2026-11-26 (strict --api-configuration format)
// - revised 2026-11-26 (schema path built by Schema\InputSchemaPath)
// - revised 2026-11-26 (audit entries of write commands, via cli)
