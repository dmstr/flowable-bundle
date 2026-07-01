<?php
// file generated with AI assistance: Claude Code - 2026-06-17 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Schema;

use Dmstr\Flowable\Client\FlowableClientInterface;

/**
 * Builds a JSON-Schema for a task's form fields from Flowable form-data.
 *
 * Sources are tried in priority order (tag `flowable.task_form_source`): an
 * authored `.schema.json` deployment resource (own processes) takes precedence
 * over on-the-fly conversion of inline BPMN form definitions (demo/legacy
 * processes). The returned schema describes the form fields only — the
 * surrounding `complete` envelope (`variables`/`apiConfiguration`) is added by
 * {@see TaskFormInputSchemaResolver}.
 */
interface TaskFormSchemaSourceInterface
{
    /**
     * @param array<string,mixed> $formData Flowable `/form/form-data` response
     * @return array<string,mixed>|null a `type: object` JSON-Schema, or null
     *                                  when this source does not apply
     */
    public function build(array $formData, FlowableClientInterface $client): ?array;
}
