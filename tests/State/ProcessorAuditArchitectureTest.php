<?php
// file generated with AI assistance: Claude Code - 2026-11-26 14:48:37 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\State;

use Dmstr\Flowable\State\AbstractFlowableProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every write processor runs its engine call through the shared
 * AbstractFlowableProcessor::audited() wrapper — exactly once — and none
 * writes audit entries itself, so the attempt/success/failure entries and
 * their shape cannot drift per processor.
 */
final class ProcessorAuditArchitectureTest extends TestCase
{
    /** The write processors and their audit action */
    private const array ACTIONS = [
        'DecisionExecuteProcessor' => 'decision.execute',
        'DeploymentBundleProcessor' => 'deployment.bundle',
        'DeploymentDeleteProcessor' => 'deployment.delete',
        'DeploymentUploadProcessor' => 'deployment.upload',
        'DmnDeploymentDeleteProcessor' => 'dmn.deployment.delete',
        'DmnDeploymentUploadProcessor' => 'dmn.deployment.upload',
        'EventDeploymentDeleteProcessor' => 'event_deployment.delete',
        'EventDeploymentUploadProcessor' => 'event_deployment.upload',
        'EventInstanceCreateProcessor' => 'event_instance.create',
        'ExecutionTriggerProcessor' => 'execution.trigger',
        'ExternalWorkerJobAcquireProcessor' => 'external_worker.acquire',
        'ExternalWorkerJobBpmnErrorProcessor' => 'external_worker.bpmn_error',
        'ExternalWorkerJobCompleteProcessor' => 'external_worker.complete',
        'ExternalWorkerJobFailProcessor' => 'external_worker.fail',
        'ExternalWorkerJobUnacquireProcessor' => 'external_worker.unacquire',
        'ProcessDefinitionStartProcessor' => 'process_definition.start',
        'ProcessInstanceCreateProcessor' => 'process_instance.create',
        'ProcessInstanceDeleteProcessor' => 'process_instance.delete',
        'TaskCompleteProcessor' => 'task.complete',
        'TimerJobExecuteProcessor' => 'timer_job.execute',
    ];

    public function testEverySubclassOfTheBaseProcessorIsListed(): void
    {
        $found = [];
        foreach (glob(self::stateDir() . '/*Processor.php') ?: [] as $file) {
            $class = 'Dmstr\\Flowable\\State\\' . basename($file, '.php');
            if (class_exists($class) && is_subclass_of($class, AbstractFlowableProcessor::class)) {
                $found[] = basename($file, '.php');
            }
        }
        sort($found);

        self::assertSame(array_keys(self::ACTIONS), $found);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function processors(): iterable
    {
        foreach (self::ACTIONS as $class => $action) {
            yield $class => [$class, $action];
        }
    }

    #[DataProvider('processors')]
    public function testProcessorRunsItsEngineCallThroughTheWrapperOnce(string $class, string $action): void
    {
        $source = (string) file_get_contents(self::stateDir() . '/' . $class . '.php');

        self::assertSame(1, substr_count($source, '$this->audited('), $class);
        self::assertStringContainsString("'" . $action . "'", $source);
        foreach (['FlowableAuditLogger', 'flowableLogger', '->attempt(', '->success(', '->failure(', '->record('] as $bypass) {
            self::assertStringNotContainsString($bypass, $source, $class . ' writes audit entries itself');
        }
    }

    private static function stateDir(): string
    {
        return \dirname(__DIR__, 2) . '/src/State';
    }
}
