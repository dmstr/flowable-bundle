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
        $this->client()->deleteProcessInstance($id);
        $this->audit('process_instance.delete', ['instance' => $id]);

        return null;
    }
}
