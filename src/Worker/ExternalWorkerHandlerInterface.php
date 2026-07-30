<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Worker;

/**
 * Contract for the application code behind a `bpmn:serviceTask
 * flowable:type="external-worker"` topic.
 *
 * Deliberately analogous to Dmstr\SymfonyJobQueue\Service\Job\
 * JobProcessorInterface — one handler class per unit of work, discovered from
 * the container — with ONE deliberate difference: it declares its topics
 * up front via {@see topics()} instead of answering a `supports(string $type)`
 * predicate.
 *
 * Why: the engine's acquire call is per topic ("give me N jobs of topic X").
 * A runner therefore has to know the full topic list BEFORE it can poll, and a
 * predicate can only answer once a job is already in hand. Declaring topics also
 * lets the registry reject a topic claimed by two handlers at wiring time rather
 * than silently letting the first match win.
 *
 * Implementations are auto-tagged `flowable.external_worker_handler`
 * (registerForAutoconfiguration in FlowableBundle), so a consuming application
 * only implements the interface — no attribute, no services.yaml entry.
 *
 * A handler must be safe to call more than once for the same job: a lock that
 * expires while the work runs makes the engine hand the same job to the next
 * worker.
 */
interface ExternalWorkerHandlerInterface
{
    /**
     * The external-worker topics this handler is responsible for.
     *
     * Known before any polling happens — see the class docblock. Returning an
     * empty list disables the handler.
     *
     * @return list<string>
     */
    public function topics(): array;

    /**
     * Do the work and say what should be reported to the engine.
     *
     * Throwing is allowed and idiomatic: the runner turns any exception into a
     * `fail` report and lets the ENGINE decide about backoff and dead-lettering.
     * Return {@see ExternalWorkerOutcome::bpmnError()} instead when the failure
     * is a modelled business outcome the process catches with an error boundary
     * event.
     */
    public function handle(ExternalWorkerJob $job): ExternalWorkerOutcome;
}
