<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirrors POST /api/flowable/external_worker_jobs/{id}/fail.
 *
 * Reporting a failure SPENDS one of the job's retries. To merely hand a job back
 * (e.g. it was acquired by mistake) use flowable:external-worker:unacquire.
 */
#[AsCommand(
    name: 'flowable:external-worker:fail',
    description: 'Report an acquired external worker job as failed (spends a retry)',
)]
final class ExternalWorkerJobFailCommand extends AbstractExternalWorkerJobCommand
{
    protected function configure(): void
    {
        $this->configureJobReport();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $body = $this->readReportInput($input, 'fail');
            $client = $this->client($input);

            // Forward only what was stated, so the engine's own
            // decrement-and-reschedule policy stays in charge of the rest.
            $payload = ['workerId' => (string) $body['workerId']];
            foreach (['retryTimeout', 'errorMessage', 'errorDetails'] as $key) {
                if (isset($body[$key])) {
                    $payload[$key] = (string) $body[$key];
                }
            }
            if (isset($body['retries'])) {
                $payload['retries'] = (int) $body['retries'];
            }

            $client->failExternalWorkerJob((string) $input->getArgument('id'), $payload);
            $io->success('External worker job reported as failed.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
