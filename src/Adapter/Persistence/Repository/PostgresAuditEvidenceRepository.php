<?php

declare(strict_types=1);

namespace App\Adapter\Persistence\Repository;

use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\Agent\AgentId;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidence;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use InvalidArgumentException;
use LogicException;

/**
 * Class PostgresAuditEvidenceRepository
 *
 * Appends bounded, typed, secret-free evidence on the caller's connection
 */
final readonly class PostgresAuditEvidenceRepository implements AuditEvidenceRepository
{
    /**
     * Constructs PostgresAuditEvidenceRepository
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function add(AuditEvidence $evidence): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException('Audit evidence requires an enclosing transaction.');
        }

        $actor = $evidence->actorId();
        $action = $evidence->action();
        $context = $evidence->context();
        if (
            !$this->approvedActor($actor, $action)
            || strlen($action) > 128 || !preg_match('/^[a-z][a-z0-9_.]*$/D', $action)
            || !$this->approvedContext($action, $context)
        ) {
            throw new InvalidArgumentException('Audit evidence contains unsupported public fields.');
        }

        ksort($context, SORT_STRING);
        $json = json_encode((object) $context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($json) > 4096) {
            throw new InvalidArgumentException('Audit evidence context exceeds its bound.');
        }

        $this->connection->insert('audit_evidence', [
            'actor_id'     => $actor,
            'action'       => $action,
            'subject_type' => $evidence->subjectId() instanceof AgentId ? 'agent' : 'user',
            'subject_id'   => $evidence->subjectId()->toString(),
            'context'      => $json
        ]);
    }

    /**
     * Validates canonical principal IDs or the package's anonymous reset actor
     */
    private function approvedActor(string $actor, string $action): bool
    {
        if ($actor === 'anonymous') {
            return in_array($action, [
                'user.password_reset_requested',
                'user.password_reset_delivery.failed',
                'user.password_reset_delivery.confirmed'
            ], true);
        }

        return preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/D', $actor) === 1;
    }

    /**
     * Validates package-defined context values without accepting arbitrary payloads
     *
     * @phpstan-param array<string, mixed> $context
     */
    private function approvedContext(string $action, array $context): bool
    {
        if ($context === []) {
            return true;
        }
        if (
            $action !== 'refresh_session.administratively_revoked'
            || array_keys($context) !== ['refresh_session_id', 'reason']
        ) {
            return false;
        }

        return is_string($context['refresh_session_id'])
            && preg_match('/^[0-9a-f-]{36}$/D', $context['refresh_session_id']) === 1
            && is_string($context['reason'])
            && $context['reason'] !== '' && preg_match_all('/./us', $context['reason']) <= 500
            && preg_match('/[\x00-\x1f\x7f]/', $context['reason']) === 0
            && preg_match(
                '/(?:password|token|credential|secret|authorization|bearer|ciphertext|https?:\/\/)/i',
                $context['reason']
            ) === 0;
    }
}
