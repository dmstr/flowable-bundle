<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirrors POST /api/flowable/external_worker_jobs/{id}/complete.
 */
#[AsCommand(
    name: 'flowable:external-worker:complete',
    description: 'Report an acquired external worker job as done',
)]
final class ExternalWorkerJobCompleteCommand extends AbstractExternalWorkerJobCommand
{
    protected function configure(): void
    {
        $this->configureJobReport();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $body = $this->readReportInput($input, 'complete');
            $client = $this->client($input);

            $client->completeExternalWorkerJob((string) $input->getArgument('id'), [
                'workerId' => (string) $body['workerId'],
                'variables' => $this->variableMapper->toFlowable($body['variables'] ?? null),
            ]);
            $io->success('External worker job completed.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
