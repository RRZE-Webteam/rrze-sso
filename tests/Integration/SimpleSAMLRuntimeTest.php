<?php

namespace RRZE\SSO\Tests\Integration;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use RRZE\SSO\Options;
use RRZE\SSO\SimpleSAML as PluginSimpleSAML;
use SimpleSAML\Auth\Simple as AuthClient;
use SimpleSAML\Metadata\MetaDataStorageHandler;
use SimpleSAML\Session;

class SimpleSAMLRuntimeTest extends TestCase
{
    public function testWordPressLoadsTheConfiguredRealSimpleSamlClient(): void
    {
        self::assertTrue(is_multisite());
        self::assertTrue(is_plugin_active_for_network('rrze-sso/rrze-sso.php'));

        $options = Options::getOptions();
        $configuredAutoloader = realpath(WP_CONTENT_DIR . $options->simplesaml_include);
        $expectedAutoloader = realpath(
            self::environment('RRZE_SSO_INTEGRATION_SAML_ROOT') . '/sp-current/lib/_autoload.php'
        );

        self::assertSame($expectedAutoloader, $configuredAutoloader);
        self::assertSame('default-sp', $options->simplesaml_auth_source);
        self::assertSame(1, $options->force_sso);

        $service = new PluginSimpleSAML();

        self::assertTrue($service->loaded());
        self::assertInstanceOf(AuthClient::class, $service->getAuthSimple());
        self::assertSame(
            array(self::idpEntityId() => 'Lokaler RRZE-Test-IdP'),
            $service->getIdentityProviders()
        );
    }

    public function testRealSpMetadataResolvesTheConfiguredIdentityProvider(): void
    {
        $handler = MetaDataStorageHandler::getMetadataHandler();
        $metadata = $handler->getMetaDataConfig(self::idpEntityId(), 'saml20-idp-remote');
        $values = $metadata->toArray();

        self::assertSame(self::idpEntityId(), $values['entityid']);
        self::assertNotEmpty($values['SingleSignOnService']);
        self::assertNotEmpty($values['certificate']);
    }

    public function testLocalIdentityProviderContainsTheDiagnosticProfileContract(): void
    {
        $config = $this->loadPhpConfig(
            self::environment('RRZE_SSO_INTEGRATION_IDP_CONFIG') . '/config/authsources.php'
        );
        $users = $config['local-userpass']['users'] ?? array();
        $profiles = array();

        foreach ($users as $credential => $attributes) {
            $separator = strpos((string) $credential, ':');
            self::assertNotFalse($separator, 'Every test profile must include a password separator.');
            $profiles[substr((string) $credential, 0, (int) $separator)] = $attributes;
        }

        self::assertSame(
            array(
                'student',
                'employee',
                'admin',
                'minimal',
                'no-uid',
                'no-mail',
                'multi-value',
                'unicode',
                'invalid-login',
                'html-values',
                'collision-a',
                'collision-b',
                'renamed',
            ),
            array_keys($profiles)
        );
        self::assertSame(array('uid', 'mail'), array_keys($profiles['minimal']));
        self::assertArrayNotHasKey('uid', $profiles['no-uid']);
        self::assertArrayNotHasKey('mail', $profiles['no-mail']);
        self::assertGreaterThan(1, count($profiles['multi-value']['eduPersonAffiliation']));
        self::assertSame($profiles['collision-a']['uid'], $profiles['collision-b']['uid']);
        self::assertNotSame(
            $profiles['collision-a']['subject-id'],
            $profiles['collision-b']['subject-id']
        );
        self::assertSame(
            1,
            preg_match('/[^\x00-\x7F]/', (string) $profiles['unicode']['displayName'][0])
        );
        self::assertStringContainsString('<', (string) $profiles['html-values']['displayName'][0]);
    }

    public function testPublishedMetadataMatchesTheConfiguredEntities(): void
    {
        $idpResponse = $this->request(
            self::environment('RRZE_SSO_INTEGRATION_IDP_URL')
                . '/module.php/saml/idp/metadata'
        );
        $spResponse = $this->request(
            self::environment('RRZE_SSO_INTEGRATION_WP_URL')
                . '/simplesaml-sp/module.php/saml/sp/metadata/default-sp'
        );

        self::assertSame(200, $idpResponse['status']);
        self::assertSame(200, $spResponse['status']);
        self::assertSame(self::idpEntityId(), $this->metadataEntityId($idpResponse['body']));
        self::assertSame(self::spEntityId(), $this->metadataEntityId($spResponse['body']));
        self::assertStringContainsString('SingleSignOnService', $idpResponse['body']);
        self::assertStringContainsString('AssertionConsumerService', $spResponse['body']);
    }

    public function testRealSpStartsAuthenticationAtTheLocalIdentityProvider(): void
    {
        $wpUrl = self::environment('RRZE_SSO_INTEGRATION_WP_URL');
        $returnTo = $wpUrl . '/wp-admin/';
        $service = new PluginSimpleSAML();
        self::assertTrue($service->loaded());
        $client = $service->getAuthSimple();
        self::assertInstanceOf(AuthClient::class, $client);

        try {
            $loginUrl = $client->getLoginURL($returnTo);
        } finally {
            Session::getSessionFromRequest()->cleanup();
        }

        self::assertStringStartsWith(
            $wpUrl . '/simplesaml-sp/',
            $loginUrl
        );

        $response = $this->request($loginUrl);
        self::assertContains($response['status'], array(302, 303));

        $location = $response['headers']['location'] ?? '';
        self::assertStringStartsWith(
            self::environment('RRZE_SSO_INTEGRATION_IDP_URL') . '/',
            $location
        );
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        self::assertNotEmpty($query['SAMLRequest'] ?? '');
        self::assertSame($returnTo, $query['RelayState'] ?? '');
    }

    public function testLocalIdentityProviderProducesARealSamlResponse(): void
    {
        $wpUrl = self::environment('RRZE_SSO_INTEGRATION_WP_URL');
        $returnTo = $wpUrl . '/wp-admin/';
        $cookieJar = tempnam(sys_get_temp_dir(), 'rrze-sso-integration-');
        self::assertNotFalse($cookieJar);

        try {
            $service = new PluginSimpleSAML();
            self::assertTrue($service->loaded());
            $client = $service->getAuthSimple();
            self::assertInstanceOf(AuthClient::class, $client);

            $loginPage = $this->request($client->getLoginURL($returnTo), $cookieJar, true);
            self::assertSame(200, $loginPage['status']);
            self::assertStringStartsWith(
                self::environment('RRZE_SSO_INTEGRATION_IDP_URL') . '/',
                $loginPage['url']
            );

            $loginForm = $this->firstForm($loginPage['body'], $loginPage['url']);
            [$username, $password] = $this->diagnosticCredential('student');
            $loginForm['fields']['username'] = $username;
            $loginForm['fields']['password'] = $password;

            $responsePage = $this->request(
                $loginForm['action'],
                $cookieJar,
                true,
                $loginForm['fields']
            );
            self::assertSame(
                200,
                $responsePage['status'],
                sprintf(
                    'IdP login POST to %s failed: %s',
                    $loginForm['action'],
                    trim(substr(strip_tags($responsePage['body']), 0, 500))
                )
            );

            $responseForm = $this->firstForm($responsePage['body'], $responsePage['url']);
            self::assertStringStartsWith($wpUrl . '/simplesaml-sp/', $responseForm['action']);
            self::assertSame($returnTo, $responseForm['fields']['RelayState'] ?? '');

            $encodedResponse = $responseForm['fields']['SAMLResponse'] ?? '';
            self::assertNotEmpty($encodedResponse);
            $samlResponse = base64_decode($encodedResponse, true);
            self::assertNotFalse($samlResponse);

            $document = new DOMDocument();
            self::assertTrue($document->loadXML($samlResponse, LIBXML_NONET));
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
            $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');

            self::assertSame(
                $responseForm['action'],
                $xpath->evaluate('string(/samlp:Response/@Destination)')
            );
            self::assertSame(
                self::idpEntityId(),
                $xpath->evaluate('string(/samlp:Response/saml:Issuer)')
            );
            self::assertSame(
                'urn:oasis:names:tc:SAML:2.0:status:Success',
                $xpath->evaluate('string(/samlp:Response/samlp:Status/samlp:StatusCode/@Value)')
            );
            self::assertGreaterThan(
                0,
                $xpath->query('/samlp:Response//*[local-name()="Signature"]')?->length ?? 0
            );
        } finally {
            Session::getSessionFromRequest()->cleanup();
            if (is_string($cookieJar) && is_file($cookieJar)) {
                unlink($cookieJar);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadPhpConfig(string $path): array
    {
        $loader = static function (string $configPath): array {
            $config = array();
            require $configPath;

            return $config;
        };

        return $loader($path);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function diagnosticCredential(string $profile): array
    {
        $config = $this->loadPhpConfig(
            self::environment('RRZE_SSO_INTEGRATION_IDP_CONFIG') . '/config/authsources.php'
        );

        foreach (array_keys($config['local-userpass']['users'] ?? array()) as $credential) {
            [$username, $password] = array_pad(explode(':', (string) $credential, 2), 2, '');
            if ($profile === $username) {
                return array($username, $password);
            }
        }

        self::fail(sprintf('Diagnostic profile %s does not exist.', $profile));
    }

    /**
     * @return array{action: string, fields: array<string, string>}
     */
    private function firstForm(string $html, string $pageUrl): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        self::assertTrue($loaded);

        $xpath = new DOMXPath($document);
        $form = $xpath->query('(//form)[1]')?->item(0);
        self::assertNotNull($form, 'Expected an HTML form in the SimpleSAMLphp response.');

        $action = (string) $form->attributes?->getNamedItem('action')?->nodeValue;
        $fields = array();
        foreach ($xpath->query('.//input[@name]', $form) ?: array() as $input) {
            $name = (string) $input->attributes?->getNamedItem('name')?->nodeValue;
            $fields[$name] = (string) $input->attributes?->getNamedItem('value')?->nodeValue;
        }

        return array(
            'action' => $this->absoluteUrl($pageUrl, $action),
            'fields' => $fields,
        );
    }

    private function absoluteUrl(string $baseUrl, string $url): string
    {
        if (1 === preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $parts = parse_url($baseUrl);
        $origin = sprintf(
            '%s://%s%s',
            $parts['scheme'] ?? 'https',
            $parts['host'] ?? '',
            isset($parts['port']) ? ':' . $parts['port'] : ''
        );

        if (str_starts_with($url, '/')) {
            return $origin . $url;
        }

        $path = $parts['path'] ?? '/';
        if ('' === $url) {
            return $baseUrl;
        }
        if (str_starts_with($url, '?')) {
            return $origin . $path . $url;
        }

        return $origin . rtrim(dirname($path), '/') . '/' . $url;
    }

    private function metadataEntityId(string $xml): string
    {
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($xml, LIBXML_NONET));
        $xpath = new DOMXPath($document);
        $entities = $xpath->query('//*[local-name()="EntityDescriptor"]');
        self::assertNotFalse($entities);
        self::assertGreaterThan(0, $entities->length);

        return (string) $entities->item(0)?->attributes?->getNamedItem('entityID')?->nodeValue;
    }

    private static function environment(string $constant): string
    {
        self::assertTrue(defined($constant), sprintf('%s is not defined.', $constant));

        return (string) constant($constant);
    }

    private static function idpEntityId(): string
    {
        return self::environment('RRZE_SSO_INTEGRATION_IDP_URL') . '/saml-idp';
    }

    private static function spEntityId(): string
    {
        return self::environment('RRZE_SSO_INTEGRATION_WP_URL') . '/saml-sp';
    }

    /**
     * @param array<string, string>|null $postFields
     * @return array{status: int, headers: array<string, string>, body: string, url: string}
     */
    private function request(
        string $url,
        ?string $cookieJar = null,
        bool $followRedirects = false,
        ?array $postFields = null
    ): array
    {
        $handle = curl_init($url);
        self::assertNotFalse($handle);
        $headers = array();
        curl_setopt_array(
            $handle,
            array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => $followRedirects,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                    if (str_starts_with($line, 'HTTP/')) {
                        $headers = array();
                    }
                    $separator = strpos($line, ':');
                    if (false !== $separator) {
                        $name = strtolower(trim(substr($line, 0, $separator)));
                        $headers[$name] = trim(substr($line, $separator + 1));
                    }

                    return strlen($line);
                },
            )
        );
        if (null !== $cookieJar) {
            curl_setopt($handle, CURLOPT_COOKIEJAR, $cookieJar);
            curl_setopt($handle, CURLOPT_COOKIEFILE, $cookieJar);
        }
        if (null !== $postFields) {
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($postFields));
        }

        $body = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $effectiveUrl = (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);

        self::assertNotFalse($body, sprintf('Request to %s failed: %s', $url, $error));

        return array(
            'status' => $status,
            'headers' => $headers,
            'body' => (string) $body,
            'url' => $effectiveUrl,
        );
    }
}
