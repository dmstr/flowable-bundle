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
 * Mirrors POST /api/flowable/external_worker_jobs/{id}/bpmnError.
 *
 * The modelled failure path: the process continues along the error boundary
 * event catching the given code, instead of the engine retrying the job.
 */
#[AsCommand(
    name: 'flowable:external-worker:bpmn-error',
    description: 'Raise a BPMN error from an acquired external worker job',
)]
final class ExternalWorkerJobBpmnErrorCommand extends AbstractExternalWorkerJobCommand
{
    protected function configure(): void
    {
        $this->configureJobReport();
        $this->addOption('error-code', null, InputOption::VALUE_REQUIRED, 'BPMN error code to raise (alternative to errorCode in the JSON input)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $errorCode = $input->getOption('error-code');
            $body = $this->readReportInput($input, 'bpmn_error', $errorCode !== null && $errorCode !== ''
                ? ['errorCode' => (string) $errorCode]
                : []);
            $client = $this->client($input);

            $client->bpmnErrorExternalWorkerJob((string) $input->getArgument('id'), [
                'workerId' => (string) $body['workerId'],
                'errorCode' => (string) $body['errorCode'],
                'variables' => $this->variableMapper->toFlowable($body['variables'] ?? null),
            ]);
            $io->success(sprintf('BPMN error "%s" raised.', $body['errorCode']));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
