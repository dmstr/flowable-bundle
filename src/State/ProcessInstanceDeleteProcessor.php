<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

/**
 * Deletes a running process instance (DELETE /process_instances/{id}).
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class ProcessInstanceDeleteProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $id = (string) ($uriVariables['id'] ?? '');
        $client = $this->client(context: $context);
        $this->audited(
            'process_instance.delete',
            $context,
            ['instance' => $id],
            static fn () => $client->deleteProcessInstance($id),
        );

        return null;
    }
}
// - revised 2026-11-26 (engine call wrapped in audited(): attempt, success and failure entries)
