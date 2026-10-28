<?php
// file generated with AI assistance: Claude Code - 2026-10-28 21:15:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Kernel;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Example permission voter, as an application (za7-core) would provide it:
 * grants `flowable:*` attributes from a fixed role → permissions map.
 *
 * Behaves like the za7-core voter: it supports only attributes matching
 * {@see self::ATTRIBUTE_PATTERN} that start with {@see self::PREFIX} and
 * abstains on everything else, accepts any subject (including null), never
 * throws, and grants when one of the user's roles is mapped to the attribute.
 * A supported attribute that no role grants is denied.
 *
 * Roles (they exist only in tests):
 *
 * - `ROLE_USER` grants `flowable:read` only;
 * - `ROLE_FLOWABLE_ADMIN` grants all of {@see self::ALL};
 * - single-permission roles grant exactly one permission. Their name is
 *   {@see self::SINGLE_ROLE_PREFIX} plus the permission upper-cased with the
 *   colon turned into an underscore: `flowable:deploy_delete` is granted by
 *   `ROLE_PERM_FLOWABLE_DEPLOY_DELETE`. Do not build the name by hand, use
 *   {@see self::roleFor()}. {@see self::rolesExcept()} returns the single
 *   roles of all permissions but one, for "everything but this" cases.
 */
final class TestPermissionVoter implements VoterInterface
{
    public const string PREFIX = 'flowable:';

    /** What a voter of the platform accepts as a permission attribute */
    public const string ATTRIBUTE_PATTERN = '/^[a-z][a-z0-9]*:[a-z][a-z0-9_]*$/';

    public const string SINGLE_ROLE_PREFIX = 'ROLE_PERM_';

    public const string READ = 'flowable:read';
    public const string READ_STACKTRACE = 'flowable:read_stacktrace';
    public const string MCP_READ = 'flowable:mcp_read';
    public const string DEPLOY = 'flowable:deploy';
    public const string DEPLOY_DELETE = 'flowable:deploy_delete';
    public const string DECISION_EVALUATE = 'flowable:decision_evaluate';
    public const string PROCESS_START = 'flowable:process_start';
    public const string PROCESS_DELETE = 'flowable:process_delete';
    public const string TASK_COMPLETE = 'flowable:task_complete';
    public const string EVENT_SEND = 'flowable:event_send';
    public const string EXECUTION_TRIGGER = 'flowable:execution_trigger';
    public const string WORKER = 'flowable:worker';
    public const string JOB_EXECUTE = 'flowable:job_execute';

    /** Every permission the bundle's operations check */
    public const array ALL = [
        self::READ,
        self::READ_STACKTRACE,
        self::MCP_READ,
        self::DEPLOY,
        self::DEPLOY_DELETE,
        self::DECISION_EVALUATE,
        self::PROCESS_START,
        self::PROCESS_DELETE,
        self::TASK_COMPLETE,
        self::EVENT_SEND,
        self::EXECUTION_TRIGGER,
        self::WORKER,
        self::JOB_EXECUTE,
    ];

    /** Role → granted permissions (the single-permission roles are derived, see the class docblock) */
    public const array PERMISSIONS = [
        'ROLE_USER' => [self::READ],
        'ROLE_FLOWABLE_ADMIN' => self::ALL,
    ];

    public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        try {
            $result = self::ACCESS_ABSTAIN;
            $granted = self::grantedPermissions($token->getRoleNames());

            foreach ($attributes as $attribute) {
                if (!self::supports($attribute)) {
                    continue;
                }
                if (in_array($attribute, $granted, true)) {
                    return self::ACCESS_GRANTED;
                }
                $result = self::ACCESS_DENIED;
            }

            return $result;
        } catch (\Throwable) {
            return self::ACCESS_ABSTAIN;
        }
    }

    /**
     * The role that grants exactly $permission.
     */
    public static function roleFor(string $permission): string
    {
        return self::SINGLE_ROLE_PREFIX . strtoupper(str_replace(':', '_', $permission));
    }

    /**
     * The single-permission roles of all permissions but $permission.
     *
     * @return list<string>
     */
    public static function rolesExcept(string $permission): array
    {
        return array_values(array_map(
            self::roleFor(...),
            array_filter(self::ALL, static fn (string $candidate): bool => $candidate !== $permission),
        ));
    }

    /**
     * @param list<string> $roles
     * @return list<string>
     */
    public static function grantedPermissions(array $roles): array
    {
        $single = [];
        foreach (self::ALL as $permission) {
            $single[self::roleFor($permission)] = [$permission];
        }

        $granted = [];
        foreach ($roles as $role) {
            array_push($granted, ...(self::PERMISSIONS[$role] ?? $single[$role] ?? []));
        }

        return array_values(array_unique($granted));
    }

    private static function supports(mixed $attribute): bool
    {
        return is_string($attribute)
            && str_starts_with($attribute, self::PREFIX)
            && preg_match(self::ATTRIBUTE_PATTERN, $attribute) === 1;
    }
}
