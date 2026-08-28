<?php

namespace SimpleSAML\Auth;

/**
 * Test and static-analysis stub for the externally installed SimpleSAMLphp client.
 */
class Simple
{
    /** @var \Throwable|null */
    public static $constructorException;

    public function __construct(string $authSource)
    {
        if (self::$constructorException) {
            throw self::$constructorException;
        }
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
 * Test and static-analysis stub for a SimpleSAMLphp session.
 */
class Session
{
    /**
     * Shared request session used by unit tests.
     *
     * @var self|null
     */
    private static $session;

    /**
     * Number of times the session has been cleaned.
     *
     * @var int
     */
    public $cleanupCalls = 0;

    public static function getSessionFromRequest(): self
    {
        if (!self::$session) {
            self::$session = new self();
        }

        return self::$session;
    }

    /**
     * Replaces the shared session between unit tests.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$session = new self();
    }

    public function cleanup(): void
    {
        $this->cleanupCalls++;
    }
}

/**
 * Static-analysis stub for SimpleSAMLphp metadata configuration.
 */
class Configuration
{
    /** @var array<string, mixed> */
    private $values;

    /**
     * @param array<string, mixed> $values Configuration values.
     */
    public function __construct(array $values = array())
    {
        $this->values = $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}

namespace SimpleSAML\Metadata;

use SimpleSAML\Configuration;

/**
 * Static-analysis stub for the SimpleSAMLphp metadata storage handler.
 */
class MetaDataStorageHandler
{
    /** @var array<string, mixed> */
    public static $entityList = array();

    /** @var array<string, Configuration|null> */
    public static $metadata = array();

    /** @var array<string, \Exception> */
    public static $metadataExceptions = array();

    public static function getMetadataHandler(): self
    {
        return new self();
    }

    /**
     * @return array<string, mixed>
     */
    public function getList(): array
    {
        return self::$entityList;
    }

    public function getMetaDataConfig(string $entityId, string $set): ?Configuration
    {
        if (isset(self::$metadataExceptions[$entityId])) {
            throw self::$metadataExceptions[$entityId];
        }

        return self::$metadata[$entityId] ?? null;
    }

    public static function reset(): void
    {
        self::$entityList = array();
        self::$metadata = array();
        self::$metadataExceptions = array();
    }
}
