<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirrors GET /api/flowable/external_worker_jobs (and the item GET).
 *
 * `lockOwner` tells the interesting story here: a job with an owner is being
 * worked on (or its worker died and the lock has yet to expire), one without is
 * waiting to be acquired.
 */
#[AsCommand(name: 'flowable:external-worker-jobs', description: 'List Flowable external worker jobs, or show one by id')]
final class ExternalWorkerJobsCommand extends AbstractFlowableCommand
{
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, 'External worker job id (shows a single job)');
        $this->addApiConfigurationOption();
        $this->addOption('size', 's', InputOption::VALUE_REQUIRED, 'Page size for listing', '30');
        $this->addOption('unlocked', null, InputOption::VALUE_NONE, 'Only jobs no worker currently holds');
        $this->addOption('with-exception', null, InputOption::VALUE_NONE, 'Only jobs that carry an exception');
        $this->addOption('process-instance', null, InputOption::VALUE_REQUIRED, 'Filter by process instance id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $client = $this->client($input);
            $id = $input->getArgument('id');
            if ($id !== null) {
                return $this->renderItem($io, $client->findExternalWorkerJob((string) $id));
            }

            $query = ['size' => (int) $input->getOption('size')];
            if ((bool) $input->getOption('unlocked')) {
                $query['unlocked'] = 'true';
            }
            if ((bool) $input->getOption('with-exception')) {
                $query['withException'] = 'true';
            }
            if (($processInstance = $input->getOption('process-instance')) !== null) {
                $query['processInstanceId'] = (string) $processInstance;
            }

            $envelope = $client->listExternalWorkerJobs($query);

            return $this->renderEnvelope($io, $envelope, [
                'id',
                'elementId',
                'processInstanceId',
                'retries',
                'lockOwner',
                'lockExpirationTime',
                'exceptionMessage',
            ]);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
