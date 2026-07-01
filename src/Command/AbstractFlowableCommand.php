<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Service\FlowableVariableMapper;
use Dmstr\Flowable\Service\InputSchemaValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shared scaffolding for the flowable:* CLI mirror of the REST operations.
 *
 * Common options: --api-configuration (full/partial UUID), and for write
 * commands --acting-user (mandatory, propagated as startUserId — decision
 * 2026-06-16), --input / --input-file (validated against the same bundle-local
 * JSON schemas as the REST endpoints).
 */
abstract class AbstractFlowableCommand extends Command
{
    public function __construct(
        protected readonly FlowableClientLocator $locator,
        protected readonly InputSchemaValidator $validator,
        protected readonly FlowableVariableMapper $variableMapper,
    ) {
        parent::__construct();
    }

    protected function addApiConfigurationOption(): void
    {
        $this->addOption('api-configuration', 'c', InputOption::VALUE_REQUIRED, 'Flowable ApiConfiguration UUID (full or partial)');
    }

    protected function addWriteOptions(): void
    {
        $this->addApiConfigurationOption();
        $this->addOption('acting-user', 'u', InputOption::VALUE_REQUIRED, 'Acting za7 user UUID (propagated as startUserId)');
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
     * @return array<string,mixed>
     */
    protected function readInput(InputInterface $input, string $schemaPath): array
    {
        $raw = $input->getOption('input');
        if ($raw === null && ($file = $input->getOption('input-file')) !== null) {
            if (!is_file((string) $file)) {
                throw new \RuntimeException(sprintf('Input file not found: %s', $file));
            }
            $raw = (string) file_get_contents((string) $file);
        }

        return $this->validator->validateRaw((string) ($raw ?? ''), $schemaPath);
    }

    protected function schemaPath(string $entity, string $verb): string
    {
        return \dirname(__DIR__).'/ApiResource/'.$entity.'/'.$verb.'.input.json';
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
