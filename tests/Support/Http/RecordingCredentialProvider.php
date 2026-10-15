<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Zef\Framework\Http\AuthCredential;
use Zef\Framework\Http\AuthResult;
use Zef\Framework\Http\CredentialProviderInterface;

/**
 * Deterministic credential-provider double: records every resolution's
 * credential and clock, and answers the configured result -- the shape
 * the boundary's tests need to observe which handle each status
 * travelled under and which timestamp the middleware read.
 */
final class RecordingCredentialProvider implements CredentialProviderInterface
{
    /** @var list<array{AuthCredential, int}> */
    public array $resolutions = [];

    public AuthResult $result;

    public function __construct(?AuthResult $result = null)
    {
        $this->result = $result ?? new AuthResult(\Zef\Framework\Http\AuthStatus::FAILED);
    }

    #[Override]
    public function resolve(AuthCredential $credential, int $nowMs): AuthResult
    {
        $this->resolutions[] = [$credential, $nowMs];

        return $this->result;
    }
}
