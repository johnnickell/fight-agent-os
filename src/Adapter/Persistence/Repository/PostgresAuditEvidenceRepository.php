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
        if ($actor === '' || strlen($actor) > 128 || preg_match('/[\x00-\x1f\x7f]/', $actor)
            || strlen($action) > 128 || !preg_match('/^[a-z][a-z0-9_.]*$/D', $action)
            || !$this->approvedContext($action, $context)) {
            throw new InvalidArgumentException('Audit evidence contains unsupported public fields.');
        }

        ksort($context, SORT_STRING);
        $json = json_encode((object) $context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($json) > 4096) {
            throw new InvalidArgumentException('Audit evidence context exceeds its bound.');
        }

        $this->connection->insert('audit_evidence', [
            'actor_id' => $actor,
            'action' => $action,
            'subject_type' => $evidence->subjectId() instanceof AgentId ? 'agent' : 'user',
            'subject_id' => $evidence->subjectId()->toString(),
            'context' => $json,
        ]);
    }

    /**
     * Accepts only package-defined context values, never arbitrary payloads or errors
     *
     * @param array<string, string> $context
     */
    private function approvedContext(string $action, array $context): bool
    {
        if ($context === []) {
            return true;
        }
        if ($action !== 'refresh_session.administratively_revoked'
            || array_keys($context) !== ['refresh_session_id', 'reason']) {
            return false;
        }

        return is_string($context['refresh_session_id'])
            && preg_match('/^[0-9a-f-]{36}$/D', $context['refresh_session_id']) === 1
            && is_string($context['reason'])
            && $context['reason'] !== '' && preg_match_all('/./us', $context['reason']) <= 500
            && preg_match('/[\x00-\x1f\x7f]/', $context['reason']) === 0
            && preg_match('/(?:password|token|credential|secret|authorization|bearer|ciphertext|https?:\/\/)/i', $context['reason']) === 0;
    }
}
