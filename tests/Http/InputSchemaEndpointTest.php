<?php
// file generated with AI assistance: Claude Code - 2026-11-26 16:02:34 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Service\TaskInputSchema;
use Dmstr\Flowable\Tests\Kernel\TestFlowableEngine;
use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the two runtime input-schema endpoints answer over HTTP, pinned as
 * they behave today:
 *
 *  - GET /tasks/{id}/input_schema (flow_task_input_schema): a task with a
 *    form gets its fields under `variables` in the complete envelope; a task
 *    without a form and an unknown task both get the same open "no form"
 *    schema with 200 (unlike the MCP tool flowable_get_task_form, which
 *    reports an unknown task as an error);
 *  - GET /process_definitions/{id}/input_schema: a definition with a start
 *    form gets businessKey, variables and apiConfiguration; a definition
 *    without one and an unknown definition both get the same open "no start
 *    form" schema with 200.
 *
 * Both are served as `application/schema+json`. The form data is the shape
 * of the engine's `/form/form-data` answer the converter reads
 * (`formProperties`); it is not verified against a real engine here.
 */
final class InputSchemaEndpointTest extends ApiTestCase
{
    private const string FORM_DATA = '/service/form/form-data';

    private const array FORM_PROPERTIES = [
        'formProperties' => [
            ['id' => 'approved', 'name' => 'Approved', 'type' => 'boolean', 'required' => true],
            ['id' => 'comment', 'name' => 'Comment', 'type' => 'string', 'required' => false],
        ],
    ];

    // --- task --------------------------------------------------------------------------

    public function testTaskWithAFormGetsItsFieldsInTheCompleteEnvelope(): void
    {
        $this->engine()->withResponse(self::FORM_DATA, self::FORM_PROPERTIES, 'GET');

        $response = $this->get('/tasks/t1/input_schema');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('application/schema+json', $response->headers->get('Content-Type'));
        $schema = self::json($response);
        self::assertSame('flow_task_complete input', $schema['title']);
        self::assertSame('Complete "stub" — provide the task form variables.', $schema['description']);
        self::assertSame(['variables', 'apiConfiguration'], array_keys($schema['properties']));
        self::assertSame(['variables'], $schema['required']);
        self::assertFalse($schema['additionalProperties']);
        $variables = $schema['properties']['variables'];
        self::assertSame(['approved', 'comment'], array_keys($variables['properties']));
        self::assertSame('boolean', $variables['properties']['approved']['type']);
        self::assertSame('Approved', $variables['properties']['approved']['title']);
        self::assertSame(['approved'], $variables['required']);
        self::assertSame(['taskId' => 't1'], $this->formDataQuery());
    }

    public function testTaskWithoutAFormGetsTheOpenSchema(): void
    {
        // The default engine answer carries neither formKey nor formProperties
        $response = $this->get('/tasks/t1/input_schema');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('application/schema+json', $response->headers->get('Content-Type'));
        self::assertSame(self::normalised(TaskInputSchema::withoutForm()), self::json($response));
    }

    public function testUnknownTaskGetsTheOpenSchemaToo(): void
    {
        $this->engine()->failWith(404, TestFlowableEngine::errorBody('Could not find a task with id \'nope\'.'));

        $response = $this->get('/tasks/nope/input_schema');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(self::normalised(TaskInputSchema::withoutForm()), self::json($response));
    }

    public function testUnknownTaskIsAnErrorForTheMcpTool(): void
    {
        $this->engine()->failWith(404, TestFlowableEngine::errorBody('Could not find a task with id \'nope\'.'));

        $result = $this->mcpCallTool('flowable_get_task_form', ['id' => 'nope'], [P::roleFor(P::MCP_READ)]);

        self::assertSame(-32603, $result['error']['code'] ?? null, json_encode($result, JSON_THROW_ON_ERROR));
        self::assertSame('User task "nope" not found (unknown or already completed).', $result['error']['message']);
    }

    // --- process definition ------------------------------------------------------------

    public function testDefinitionWithAStartFormGetsTheStartEnvelope(): void
    {
        $this->engine()->withResponse(self::FORM_DATA, self::FORM_PROPERTIES, 'GET');

        $response = $this->get('/process_definitions/order:1:5/input_schema');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('application/schema+json', $response->headers->get('Content-Type'));
        $schema = self::json($response);
        self::assertSame('flow_process_definition_start input', $schema['title']);
        self::assertSame('Start "stub" — provide the start form variables.', $schema['description']);
        self::assertSame(['businessKey', 'variables', 'apiConfiguration'], array_keys($schema['properties']));
        self::assertSame(['variables'], $schema['required']);
        self::assertSame(['approved', 'comment'], array_keys($schema['properties']['variables']['properties']));
        self::assertSame(['processDefinitionId' => 'order:1:5'], $this->formDataQuery());
    }

    public function testDefinitionWithoutAStartFormGetsTheOpenSchema(): void
    {
        $response = $this->get('/process_definitions/order:1:5/input_schema');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(self::definitionWithoutForm(), self::json($response));
    }

    public function testUnknownDefinitionGetsTheOpenSchemaToo(): void
    {
        $this->engine()->failWith(404, TestFlowableEngine::errorBody('Could not find a process definition.'));

        $response = $this->get('/process_definitions/nope/input_schema');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(self::definitionWithoutForm(), self::json($response));
    }

    // --- helpers -----------------------------------------------------------------------

    private function get(string $path): Response
    {
        return $this->requestAs('GET', self::FLOWABLE . $path, [P::roleFor(P::READ)], headers: ['Accept' => 'application/schema+json, application/json']);
    }

    /**
     * @return array<string, mixed>
     */
    private function formDataQuery(): array
    {
        foreach ($this->engine()->requests as $index => $request) {
            if (str_ends_with($request['path'], self::FORM_DATA)) {
                return $this->engine()->queries[$index];
            }
        }
        self::fail('No form-data request reached the engine');
    }

    /**
     * @return array<string, mixed>
     */
    private static function definitionWithoutForm(): array
    {
        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title' => 'flow_process_definition_start input',
            'description' => 'This definition has no start form; start without variables.',
            'type' => 'object',
            'properties' => [],
        ];
    }

    /**
     * A schema as it comes back from JSON (an empty object decodes as []).
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private static function normalised(array $schema): array
    {
        return json_decode(json_encode($schema, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }
}
