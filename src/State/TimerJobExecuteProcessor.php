<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:26:51 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * MCP tool flowable_system_execute_timer_job: runs a timer job now instead of
 * at its due date (FlowableClientInterface::executeTimerJob(), which moves the
 * timer to the executable jobs). The timer's remaining wait cannot be
 * restored. There is no HTTP operation for this; the job store stays
 * read-only over the REST resource FlowJob.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class TimerJobExecuteProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public const string INPUT_SCHEMA = 'FlowJob/mcpExecuteTimer.input.json';

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $body = $this->validatedBody($context);
        $client = $this->client($body, $context);

        $jobId = (string) ($uriVariables['id'] ?? '');
        if ('' === $jobId) {
            throw new BadRequestHttpException('Missing "id" argument (timer job id).');
        }

        $this->audited(
            'timer_job.execute',
            $context,
            ['job' => $jobId],
            static fn () => $client->executeTimerJob($jobId),
        );

        return null;
    }
}
// - revised 2026-11-26 (engine call wrapped in audited(): attempt, success and failure entries)
// - revised 2026-11-26 (input file declared once in INPUT_SCHEMA, read by validatedBody() and the MCP tool)
