<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Service;

use Symfony\Bundle\SecurityBundle\Security;

/**
 * Resolves the acting za7 user to propagate to Flowable as startUserId.
 *
 * The value is the authenticated user's identifier (Keycloak JWT "sub"),
 * read from the security context — never from client input, so it cannot be
 * spoofed (design D7).
 */
final class ActingUserResolver
{
    public function __construct(private readonly Security $security)
    {
    }

    public function currentUserId(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }
}
