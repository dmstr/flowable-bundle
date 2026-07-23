<?php
// file generated with AI assistance: Claude Code - 2026-07-23 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use Dmstr\Flowable\State\DecisionExecuteProcessor;
use Dmstr\Flowable\State\FlowDecisionProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less pass-through resource for Flowable DMN decisions (decision
 * tables deployed to the DMN engine, see FlowDmnDeployment).
 *
 * Read is open to ROLE_USER. The custom collection operation
 * POST /decisions/execute evaluates a decision by key against the supplied
 * input variables and returns the matching rule outputs (design mirrors the
 * process-engine pass-through). Pass singleResult=true to use the engine's
 * single-result evaluation. Evaluation writes a historic execution and is
 * therefore gated behind ROLE_FLOWABLE_ADMIN.
 */
#[ApiResource(
    shortName: 'FlowDecision',
    routePrefix: '/flowable',
    extraProperties: ['label' => 'Decisions'],
    operations: [
        new GetCollection(
            uriTemplate: '/decisions',
            provider: FlowDecisionProvider::class,
            parameters: [
                'key' => new QueryParameter(description: 'Filter by decision key'),
                'name' => new QueryParameter(description: 'Filter by decision name'),
                'category' => new QueryParameter(description: 'Filter by category'),
                'deploymentId' => new QueryParameter(description: 'Filter by DMN deployment id'),
                'dmnDeployment' => new QueryParameter(description: 'Filter by DMN deployment (IRI or id) — relation filter'),
                'latest' => new QueryParameter(description: 'Keep only the latest version per key'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
        new Get(
            uriTemplate: '/decisions/{id}',
            provider: FlowDecisionProvider::class,
        ),
        new Post(
            uriTemplate: '/decisions/execute',
            name: 'flow_decision_execute',
            description: 'Evaluate a decision by key against the given input variables',
            processor: DecisionExecuteProcessor::class,
            deserialize: false,
            validate: false,
            output: FlowDecision::class,
            status: 200,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
            openapi: new Operation(tags: ['Flowable/DMN']),
        ),
    ],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    normalizationContext: ['groups' => ['flow_decision:read']],
    openapi: new Operation(tags: ['Flowable/DMN']),
)]
final class FlowDecision
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_decision:read'])]
    public ?string $id = null;

    #[Groups(['flow_decision:read'])]
    public ?string $key = null;

    #[Groups(['flow_decision:read'])]
    public ?string $name = null;

    #[Groups(['flow_decision:read'])]
    public ?int $version = null;

    #[Groups(['flow_decision:read'])]
    public ?string $description = null;

    #[Groups(['flow_decision:read'])]
    public ?string $category = null;

    #[Groups(['flow_decision:read'])]
    public ?string $decisionType = null;

    #[Groups(['flow_decision:read'])]
    public ?string $resourceName = null;

    #[Groups(['flow_decision:read'])]
    public ?string $deploymentId = null;

    /** Navigable IRI link to the DMN deployment this decision belongs to. */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_decision:read'])]
    public ?FlowDmnDeployment $dmnDeployment = null;

    #[Groups(['flow_decision:read'])]
    public ?string $tenantId = null;

    /**
     * Evaluation result — populated only by POST /decisions/execute. Each entry
     * is one matching rule's output as a {name, type, value} list; a single-
     * result evaluation carries exactly one entry.
     *
     * @var list<array<string,mixed>>|null
     */
    #[Groups(['flow_decision:read'])]
    public ?array $result = null;

    /** Raw Flowable payload — opt-in via the flow_decision:raw group. */
    #[Groups(['flow_decision:raw'])]
    public ?array $raw = null;

    /** Shallow instance carrying only the identifier, for IRI generation. */
    public static function reference(string $id): self
    {
        $self = new self();
        $self->id = $id;

        return $self;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromApi(array $data): self
    {
        $self = new self();
        $self->id = isset($data['id']) ? (string) $data['id'] : null;
        $self->key = $data['key'] ?? null;
        $self->name = $data['name'] ?? null;
        $self->version = isset($data['version']) ? (int) $data['version'] : null;
        $self->description = $data['description'] ?? null;
        $self->category = $data['category'] ?? null;
        $self->decisionType = $data['decisionType'] ?? null;
        $self->resourceName = $data['resourceName'] ?? null;
        $self->deploymentId = $data['deploymentId'] ?? null;
        if ($self->deploymentId !== null) {
            $self->dmnDeployment = FlowDmnDeployment::reference($self->deploymentId);
        }
        $self->tenantId = $data['tenantId'] ?? null;
        $self->raw = $data;

        return $self;
    }
}
