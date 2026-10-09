<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:26:51 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\Output\FlowTaskForm;
use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Service\TaskInputSchema;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * MCP tool flowable_get_task_form: the per-task input schema for completing
 * a user task — the same schema GET /flowable/tasks/{id}/input_schema serves
 * (shared via TaskInputSchema), wrapped as {taskId, schema}.
 *
 * Unlike the HTTP endpoint, an unknown task id is reported (404) instead of
 * being answered with the open "no form" schema, so an agent does not take a
 * mistyped id for a task without form.
 *
 * @implements ProviderInterface<FlowTaskForm>
 */
final class FlowTaskFormProvider extends AbstractFlowableProvider implements ProviderInterface
{
    use CompositeRowsTrait;

    public function __construct(
        FlowableClientLocator $locator,
        RequestStack $requestStack,
        private readonly TaskInputSchema $taskInputSchema,
    ) {
        parent::__construct($locator, $requestStack);
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): FlowTaskForm
    {
        $taskId = self::requiredId($uriVariables, 'user task id');
        $apiConfiguration = $this->queryParam('apiConfiguration', $context);

        $schema = $this->taskInputSchema->resolve($taskId, $apiConfiguration);
        if (null === $schema) {
            if (null === $this->client($context)->findTask($taskId)) {
                throw new NotFoundHttpException(sprintf('User task "%s" not found (unknown or already completed).', $taskId));
            }
            $schema = TaskInputSchema::withoutForm();
        }

        return FlowTaskForm::of($taskId, $schema);
    }
}
