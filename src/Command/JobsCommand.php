<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Dmstr\Flowable\State\FlowJobProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirrors GET /api/flowable/jobs (and the item GET plus its stacktrace).
 *
 * `--kind` picks the engine collection (async, timer, suspended, deadletter,
 * history); ids resolve only within their own kind, so a dead-lettered job needs
 * `--kind deadletter`. This is usually the first command to run when a process
 * "does nothing": an async/HTTP task that keeps failing ends up here with
 * retries=0.
 */
#[AsCommand(name: 'flowable:jobs', description: 'List engine jobs by kind, show one by id, or print its stacktrace')]
final class JobsCommand extends AbstractFlowableCommand
{
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, 'Job id (shows a single job)');
        $this->addApiConfigurationOption();
        $this->addOption(
            'kind',
            'k',
            InputOption::VALUE_REQUIRED,
            'Job collection: '.implode('|', FlowJobProvider::KINDS),
            FlowJobProvider::DEFAULT_KIND,
        );
        $this->addOption('size', 's', InputOption::VALUE_REQUIRED, 'Page size for listing', '30');
        $this->addOption('stacktrace', null, InputOption::VALUE_REQUIRED, 'Print the exception stacktrace of this job id and exit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $client = $this->client($input);
            $kind = FlowJobProvider::normalizeKind((string) $input->getOption('kind'));

            if (($stacktraceId = $input->getOption('stacktrace')) !== null) {
                $stacktrace = $client->getJobExceptionStacktrace((string) $stacktraceId, $kind);
                if ($stacktrace === null || $stacktrace === '') {
                    $io->error(sprintf('No stacktrace for job "%s" (kind "%s").', $stacktraceId, $kind));

                    return self::FAILURE;
                }
                // Raw, unstyled — a Java stacktrace must stay copy-pasteable.
                $output->writeln($stacktrace);

                return self::SUCCESS;
            }

            $id = $input->getArgument('id');
            if ($id !== null) {
                return $this->renderItem($io, $client->findJob((string) $id, $kind));
            }

            $query = ['size' => (int) $input->getOption('size')];
            $envelope = match ($kind) {
                'timer' => $client->listTimerJobs($query),
                'suspended' => $client->listSuspendedJobs($query),
                'deadletter' => $client->listDeadLetterJobs($query),
                'history' => $client->listHistoryJobs($query),
                default => $client->listJobs($query),
            };

            return $this->renderEnvelope($io, $envelope, [
                'id',
                'elementId',
                'processInstanceId',
                'retries',
                'dueDate',
                'handlerType',
                'exceptionMessage',
            ]);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
