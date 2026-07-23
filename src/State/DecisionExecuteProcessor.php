<?php
// file generated with AI assistance: Claude Code - 2026-07-23 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Dmstr\Flowable\ApiResource\FlowDecision;

/**
 * Evaluates a DMN decision (POST /decisions/execute).
 *
 * The body carries decisionKey, inputVariables (shorthand map or explicit
 * {name,type,value} list) and an optional singleResult flag. With
 * singleResult=true the engine's single-result evaluation is used; either way
 * the outcome is normalised to a list of rows on FlowDecision::$result (a
 * single-result response becomes a one-row list).
 *
 * @implements ProcessorInterface<mixed, FlowDecision>
 */
final class DecisionExecuteProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FlowDecision
    {
        $body = $this->validator->validateRaw($this->rawBody(), $this->schemaPath('FlowDecision', 'execute'));
        $client = $this->client($body);

        $decisionKey = (string) ($body['decisionKey'] ?? '');
        $singleResult = (bool) ($body['singleResult'] ?? false);

        $payload = [
            'decisionKey' => $decisionKey,
            'inputVariables' => $this->variableMapper->toFlowable($body['inputVariables'] ?? null),
        ];

        $response = $singleResult
            ? $client->executeDecisionSingleResult($payload)
            : $client->executeDecision($payload);

        $result = new FlowDecision();
        $result->id = $decisionKey;
        $result->key = $decisionKey;
        $result->result = $this->normalizeResult($response['resultVariables'] ?? [], $singleResult);
        $this->audit('decision.execute', ['decision' => $decisionKey, 'singleResult' => $singleResult]);

        return $result;
    }

    /**
     * Normalise the engine's resultVariables to a list of rows. The multi-result
     * endpoint already returns a list of rows; the single-result endpoint returns
     * one flat row, which is wrapped so callers always see the same shape.
     *
     * @param mixed $resultVariables
     * @return list<array<string,mixed>>
     */
    private function normalizeResult(mixed $resultVariables, bool $singleResult): array
    {
        if (!\is_array($resultVariables)) {
            return [];
        }
        if ($singleResult) {
            return $resultVariables === [] ? [] : [$resultVariables];
        }

        return array_values(array_filter($resultVariables, 'is_array'));
    }
}
