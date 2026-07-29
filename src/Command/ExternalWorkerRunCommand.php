<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Service\FlowableVariableMapper;
use Dmstr\Flowable\Service\InputSchemaValidator;
use Dmstr\Flowable\Worker\ExternalWorkerHandlerRegistry;
use Dmstr\Flowable\Worker\ExternalWorkerRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Runs the external worker poll loop
 * ({@see \Dmstr\Flowable\Worker\ExternalWorkerRunner}).
 *
 * This is the process a consuming application keeps running next to its
 * messenger workers: it pulls jobs of the topics its
 * {@see \Dmstr\Flowable\Worker\ExternalWorkerHandlerInterface} implementations
 * declare and reports the outcomes back to the engine.
 *
 * No --acting-user: the identity the engine records for these operations is the
 * worker id, not a za7 user.
 *
 * Signals: SIGTERM/SIGINT ask the runner to stop, which finishes the job in
 * flight, unacquires whatever is still locked and exits 0 — so a container stop
 * or a Ctrl-C does not leave jobs locked until their lockDuration expires.
 */
#[AsCommand(
    name: 'flowable:external-worker:run',
    description: 'Poll Flowable external worker jobs and dispatch them to the registered handlers',
)]
final class ExternalWorkerRunCommand extends AbstractFlowableCommand implements SignalableCommandInterface
{
    public function __construct(
        FlowableClientLocator $locator,
        InputSchemaValidator $validator,
        FlowableVariableMapper $variableMapper,
        private readonly ExternalWorkerRunner $runner,
        private readonly ExternalWorkerHandlerRegistry $registry,
    ) {
        parent::__construct($locator, $validator, $variableMapper);
    }

    protected function configure(): void
    {
        $this->addApiConfigurationOption();
        $this->addOption(
            'topic',
            't',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Topic to poll; repeatable. Omit to poll every topic with a registered handler',
        );
        $this->addOption('worker-id', null, InputOption::VALUE_REQUIRED, 'Worker identity used for the locks (default: <hostname>-<pid>)');
        $this->addOption('lock-duration', null, InputOption::VALUE_REQUIRED, 'ISO-8601 lock duration per acquired job', ExternalWorkerRunner::DEFAULT_LOCK_DURATION);
        $this->addOption('batch', 'b', InputOption::VALUE_REQUIRED, 'Jobs to acquire per topic and iteration', (string) ExternalWorkerRunner::DEFAULT_BATCH_SIZE);
        $this->addOption('max-jobs', null, InputOption::VALUE_REQUIRED, 'Stop after this many jobs');
        $this->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Stop after this many seconds (keeps a long-running worker fresh)');
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Run a single iteration over all topics, then exit');
        $this->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Seconds to wait after an iteration that found no work', (string) ExternalWorkerRunner::DEFAULT_IDLE_SLEEP_SECONDS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            /** @var list<string> $topics */
            $topics = $input->getOption('topic');
            $workerId = (string) ($input->getOption('worker-id') ?? $this->defaultWorkerId());

            $effectiveTopics = $topics !== [] ? $topics : $this->registry->topics();
            if ($effectiveTopics === []) {
                $io->warning('No external worker handlers are registered and no --topic was given; nothing to poll.');

                return self::SUCCESS;
            }
            $io->text(sprintf('Worker <info>%s</info> polling: %s', $workerId, implode(', ', $effectiveTopics)));

            $processed = $this->runner->run(
                workerId: $workerId,
                topics: $topics,
                lockDuration: (string) $input->getOption('lock-duration'),
                batchSize: max(1, (int) $input->getOption('batch')),
                maxJobs: $this->optionalInt($input, 'max-jobs'),
                timeLimit: $this->optionalInt($input, 'time-limit'),
                once: (bool) $input->getOption('once'),
                apiConfiguration: $this->stringOption($input, 'api-configuration'),
                idleSleepSeconds: max(0, (int) $input->getOption('sleep')),
            );

            $io->success(sprintf('Processed %d job(s).', $processed));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /** @return list<int> */
    public function getSubscribedSignals(): array
    {
        return \defined('SIGTERM') ? [\SIGTERM, \SIGINT] : [];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        // Only flip the flag and let the loop wind down on its own: returning an
        // exit code here would terminate the process immediately and leave the
        // acquired jobs locked until their lockDuration expires.
        $this->runner->requestStop();

        return false;
    }

    /**
     * A worker id must be unique per process, because it owns the locks: two
     * processes sharing one id can report each other's jobs. hostname + pid is
     * unique enough and stays recognisable in the engine's lockOwner column.
     */
    private function defaultWorkerId(): string
    {
        return (gethostname() ?: 'worker').'-'.getmypid();
    }

    private function optionalInt(InputInterface $input, string $name): ?int
    {
        $value = $input->getOption($name);

        return $value !== null && $value !== '' ? (int) $value : null;
    }

    private function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return $value !== null && $value !== '' ? (string) $value : null;
    }
}
