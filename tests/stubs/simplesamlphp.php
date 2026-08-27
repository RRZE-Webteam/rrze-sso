<?php

namespace SimpleSAML\Auth;

/**
 * Static-analysis stub for the externally installed SimpleSAMLphp client.
 */
class Simple
{
    public function __construct(string $authSource)
    {
    }

    public function requireAuth(): void
    {
    }

    /**
     * @return mixed
     */
    public function getAuthData(string $key)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return array();
    }

    public function isAuthenticated(): bool
    {
        return false;
    }

    public function logout(string $returnTo): void
    {
    }
}

namespace SimpleSAML;

/**
 * Static-analysis stub for a SimpleSAMLphp session.
 */
class Session
{
    public static function getSessionFromRequest(): self
    {
        return new self();
    }

    public function cleanup(): void
    {
    }
}

/**
 * Static-analysis stub for SimpleSAMLphp metadata configuration.
 */
class Configuration
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array();
    }
}

namespace SimpleSAML\Metadata;

use SimpleSAML\Configuration;

/**
 * Static-analysis stub for the SimpleSAMLphp metadata storage handler.
 */
class MetaDataStorageHandler
{
    public static function getMetadataHandler(): self
    {
        return new self();
    }

    /**
     * @return array<string, mixed>
     */
    public function getList(): array
    {
        return array();
    }

    public function getMetaDataConfig(string $entityId, string $set): ?Configuration
    {
        return null;
    }
}
