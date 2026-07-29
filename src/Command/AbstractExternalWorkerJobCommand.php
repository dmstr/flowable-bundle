<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Shared scaffolding for the flowable:external-worker:* report commands
 * (complete, fail, bpmn-error, unacquire).
 *
 * Unlike the other write commands these take NO --acting-user: the identity the
 * engine checks is the worker id that holds the lock, not a za7 user. It is a
 * first-class option (--worker-id) because these commands exist mainly to
 * unstick a job by hand.
 */
abstract class AbstractExternalWorkerJobCommand extends AbstractFlowableCommand
{
    protected function configureJobReport(bool $idRequired = true): void
    {
        $this->addArgument(
            'id',
            $idRequired ? InputArgument::REQUIRED : InputArgument::OPTIONAL,
            'External worker job id (acquired and locked by --worker-id)',
        );
        $this->addApiConfigurationOption();
        $this->addOption('worker-id', null, InputOption::VALUE_REQUIRED, 'Worker id holding the lock (must match the acquire call)');
        $this->addInputOptions();
    }

    /**
     * Decode the JSON payload, merge --worker-id (plus any command-specific
     * option $overrides) into it, then validate the merged result against the
     * operation's schema — the options are the ergonomic way to drive these
     * commands, and the schemas require `workerId`, so it has to be in the
     * payload before validation runs.
     *
     * @param array<string,mixed> $overrides values from CLI options that win over the JSON input
     * @return array<string,mixed>
     */
    protected function readReportInput(InputInterface $input, string $verb, array $overrides = []): array
    {
        $body = array_merge($this->decodeInput($input), $overrides);

        $workerId = $input->getOption('worker-id');
        if ($workerId !== null && $workerId !== '') {
            $body['workerId'] = (string) $workerId;
        }
        if (!isset($body['workerId']) || $body['workerId'] === '') {
            throw new \RuntimeException('The --worker-id option (or a workerId in the JSON input) is required.');
        }

        $this->validator->validate($body, $this->schemaPath('FlowExternalWorkerJob', $verb));

        return $body;
    }
}
