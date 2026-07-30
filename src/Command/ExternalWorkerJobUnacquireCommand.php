<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirrors POST /api/flowable/external_worker_jobs/{id}/unacquire — and, without
 * the id argument, the engine's "unacquire ALL jobs of this worker"
 * (POST /external-job-api/unacquire/jobs), which has no REST counterpart because
 * it is an operator recovery action, not something a client should be able to do
 * to a whole fleet.
 *
 * Unacquiring costs no retry, so this is the right way to free a job whose worker
 * died while holding the lock.
 */
#[AsCommand(
    name: 'flowable:external-worker:unacquire',
    description: 'Release the lock on an external worker job (or on all jobs of a worker)',
)]
final class ExternalWorkerJobUnacquireCommand extends AbstractExternalWorkerJobCommand
{
    protected function configure(): void
    {
        $this->configureJobReport(idRequired: false);
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Release ALL jobs held by --worker-id (no job id needed)');
        $this->addOption('tenant-id', null, InputOption::VALUE_REQUIRED, 'Restrict --all to one tenant');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $body = $this->readReportInput($input, 'unacquire');
            $client = $this->client($input);
            $workerId = (string) $body['workerId'];
            $id = $input->getArgument('id');

            if ((bool) $input->getOption('all') || $id === null) {
                $payload = ['workerId' => $workerId];
                $tenantId = $input->getOption('tenant-id');
                if ($tenantId !== null && $tenantId !== '') {
                    $payload['tenantId'] = (string) $tenantId;
                }
                $client->unacquireAllExternalWorkerJobs($payload);
                $io->success(sprintf('Released all jobs held by worker "%s".', $workerId));

                return self::SUCCESS;
            }

            $client->unacquireExternalWorkerJob((string) $id, ['workerId' => $workerId]);
            $io->success('External worker job released.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
