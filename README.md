# RRZE-SSO

Single-Sign-On (SSO) SAML-Integrations-Plugin für WordPress.

## WP-Einstellungsmenü

Einstellungen › SSO

## Bereitstellung eines FAU-SP (Service Provider der FAU) mittels SimpleSAMLphp

-   1. Letzte version des SimpleSAMLphp herunterladen. Siehe https://simplesamlphp.org/download
-   2. Bitte folgen Sie den Anweisungen zur Installation von SimpleSAMLphp unter diesem Link: https://simplesamlphp.org/docs/stable/simplesamlphp-install
-   3. Folgenden Attribute in der Datei "/simplesamlphp/config/config.php" ändern/bearbeiten:

```php
'technicalcontact_name' => 'Name des technischen Ansprechpartners',
'technicalcontact_email' => 'E-Mail-Adresse des technischen Ansprechpartners',
```
...
```php
'secretsalt' => 'Beliebige, möglichst einzigartige Phrase',
'auth.adminpassword' => 'Hash-String', // Führen Sie „bin/pwgen.php“ aus, um einen Hash zu generieren.
```
...
```php
'authproc.sp' => [
    10 => [
        'class' => 'core:AttributeMap',
        'urn2name',
    ],
    50 => [
        'class' => 'core:AttributeMap',
        'oid2name',
    ],    
    90 => 'core:LanguageAdaptor',
],
```

-   4. Folgende Element des "default-sp"-Array in der Datei "/simplesamlphp/config/authsources.php" ändern/bearbeiten:

```php
'idp' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php'
```

-   5. Den Inhalt der Datei "/simplesamlphp/metadata/saml20-idp-remote.php" löschen und dann den folgenden Code hinzufügen:

```php
<?php
$metadata['https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php'] = [
    'entityid' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php',
    'contacts' => [
        [
            'contactType' => 'other',
            'givenName' => 'Security Response',
            'surName' => 'Team',
            'emailAddress' => [
                'abuse@fau.de',
            ],
        ],
        [
            'contactType' => 'support',
            'givenName' => 'WebSSO-Support',
            'surName' => 'Team',
            'emailAddress' => [
                'sso@fau.de',
            ],
        ],
        [
            'contactType' => 'administrative',
            'givenName' => 'WebSSO-Admins',
            'surName' => 'Team',
            'emailAddress' => [
                'sso-admins@fau.de',
            ],
        ],
        [
            'contactType' => 'technical',
            'givenName' => 'WebSSO-Admins',
            'surName' => 'Team',
            'emailAddress' => [
                'sso-admins@fau.de',
            ],
        ],
        [
            'contactType' => 'technical',
            'givenName' => 'WebSSO-Admins RRZE FAU',
            'emailAddress' => [
                'sso-support@fau.de',
            ],
        ],
    ],
    'metadata-set' => 'saml20-idp-remote',
    'SingleSignOnService' => [
        [
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/module.php/saml/idp/singleSignOnService',
        ],
        [
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
            'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/module.php/saml/idp/singleSignOnService',
        ],
        [
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:SOAP',
            'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/module.php/saml/idp/singleSignOnService',
        ],
    ],
    'SingleLogoutService' => [
        [
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/module.php/saml/idp/singleLogout',
        ],
    ],
    'ArtifactResolutionService' => [],
    'NameIDFormats' => [
        'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
        'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
    ],
    'keys' => [
        [
            'encryption' => false,
            'signing' => true,
            'type' => 'X509Certificate',
            'X509Certificate' => 'MIIEFzCCAn+gAwIBAgIUZu6JGUynt+HmkDyTBVufV8brvfIwDQYJKoZIhvcNAQELBQAwIjEgMB4GA1UEAxMXd3d3LnNzby51bmktZXJsYW5nZW4uZGUwHhcNMjIxMDI1MTI0NDIzWhcNMjUxMTE5MTI0NDIzWjAiMSAwHgYDVQQDExd3d3cuc3NvLnVuaS1lcmxhbmdlbi5kZTCCAaIwDQYJKoZIhvcNAQEBBQADggGPADCCAYoCggGBAKV+3afbJg5B5r94ZuQMPRfFJdmixpAPiRqqif0hoXC4GAAd09txIWp2sMLWOEseiBYfSndBOz5OUHfyxvQ3IKubucP26leZxvyEDBymlaMw6ad2pi5JdCycJegGgkH2rThiNRK9rYLjyO5oUuCNumMBwqN1rCxaTsf6vC97cv5sEAoH551jNSPDYVvbn1/uUNw15GQuvvU43N3N3efiLnbRjUE8Ih/qDYp6v63/nxExINt7xgErgvD82k0gHrBJv7BIOMe7WmTQ2yQYC8FnzvCwHdHnZ8i1vRzPDYFTktxxn6Vsu1YMeNdd2K4Q+LLb/ljdoSxrNMKiwz9ls1Mj059hxlo3Q1g6JAYSZc9Lzqo26iTYLlq/LfF259OENIZX6FeQqDExK8BPLX6OXlneeksTxl9Bohsga3QPtRMRlGF415hmtpunW9LSiF1VewcKwpvjoEcDK+wutI/N7RNRhLNauUPQz16v1gZJDim4/zLB3Nfh19kLfJnlcRVnIkpKQQIDAQABo0UwQzAiBgNVHREEGzAZghd3d3cuc3NvLnVuaS1lcmxhbmdlbi5kZTAdBgNVHQ4EFgQUPB7olQAUEMwG9ImXfbDXtdLNPeUwDQYJKoZIhvcNAQELBQADggGBAEiIFXch6BzXOeU4S5/YgTQ/dHYpwHAc93YYP6WmlzEambSx+HGu4c6eav9zSrRhIVxwHkPE1nGJzBvtcM0FMML9/5U7keOtAcD7jkcHfnrC5cz9bWEbVpu4pSGVK1OWvC24gqwLn7++W3lx7prwpN7fO1uCSsudT3oOhSjy3oEJvtnBS26pqf/FFBUl6slZ4M3uVGUuf4q0PVXRIjK04oM8AwSO2Bb3tYU4u1lTBJkXJ+nFZGd8BcyYpFkQVY9/8iElY2qDWS6q1hNJ4c/phS7heJlk98MqtBeFw/Jo4juukdfIAtGmRpLg2xN3FzO2eoIzFgwQKrMrwMrTlovL71MEYkX/2NJ+TUCMcwseeAQaa8IgCXWfP7eD/RnS3DNj6su3Zes7W9HIpUJP33Ds3I+h0+QU9OYTnsjhxfOZOUm8BxNLvtBwVxKUmtJkh3zX/8F/exHXRaB2h0jx7iQ8bjpsGGnTE0izn0b/R3YuhH6yzt5nW8FaoHVQC/A7NVOfhg==',
        ],
        [
            'encryption' => true,
            'signing' => false,
            'type' => 'X509Certificate',
            'X509Certificate' => 'MIIEFzCCAn+gAwIBAgIUZu6JGUynt+HmkDyTBVufV8brvfIwDQYJKoZIhvcNAQELBQAwIjEgMB4GA1UEAxMXd3d3LnNzby51bmktZXJsYW5nZW4uZGUwHhcNMjIxMDI1MTI0NDIzWhcNMjUxMTE5MTI0NDIzWjAiMSAwHgYDVQQDExd3d3cuc3NvLnVuaS1lcmxhbmdlbi5kZTCCAaIwDQYJKoZIhvcNAQEBBQADggGPADCCAYoCggGBAKV+3afbJg5B5r94ZuQMPRfFJdmixpAPiRqqif0hoXC4GAAd09txIWp2sMLWOEseiBYfSndBOz5OUHfyxvQ3IKubucP26leZxvyEDBymlaMw6ad2pi5JdCycJegGgkH2rThiNRK9rYLjyO5oUuCNumMBwqN1rCxaTsf6vC97cv5sEAoH551jNSPDYVvbn1/uUNw15GQuvvU43N3N3efiLnbRjUE8Ih/qDYp6v63/nxExINt7xgErgvD82k0gHrBJv7BIOMe7WmTQ2yQYC8FnzvCwHdHnZ8i1vRzPDYFTktxxn6Vsu1YMeNdd2K4Q+LLb/ljdoSxrNMKiwz9ls1Mj059hxlo3Q1g6JAYSZc9Lzqo26iTYLlq/LfF259OENIZX6FeQqDExK8BPLX6OXlneeksTxl9Bohsga3QPtRMRlGF415hmtpunW9LSiF1VewcKwpvjoEcDK+wutI/N7RNRhLNauUPQz16v1gZJDim4/zLB3Nfh19kLfJnlcRVnIkpKQQIDAQABo0UwQzAiBgNVHREEGzAZghd3d3cuc3NvLnVuaS1lcmxhbmdlbi5kZTAdBgNVHQ4EFgQUPB7olQAUEMwG9ImXfbDXtdLNPeUwDQYJKoZIhvcNAQELBQADggGBAEiIFXch6BzXOeU4S5/YgTQ/dHYpwHAc93YYP6WmlzEambSx+HGu4c6eav9zSrRhIVxwHkPE1nGJzBvtcM0FMML9/5U7keOtAcD7jkcHfnrC5cz9bWEbVpu4pSGVK1OWvC24gqwLn7++W3lx7prwpN7fO1uCSsudT3oOhSjy3oEJvtnBS26pqf/FFBUl6slZ4M3uVGUuf4q0PVXRIjK04oM8AwSO2Bb3tYU4u1lTBJkXJ+nFZGd8BcyYpFkQVY9/8iElY2qDWS6q1hNJ4c/phS7heJlk98MqtBeFw/Jo4juukdfIAtGmRpLg2xN3FzO2eoIzFgwQKrMrwMrTlovL71MEYkX/2NJ+TUCMcwseeAQaa8IgCXWfP7eD/RnS3DNj6su3Zes7W9HIpUJP33Ds3I+h0+QU9OYTnsjhxfOZOUm8BxNLvtBwVxKUmtJkh3zX/8F/exHXRaB2h0jx7iQ8bjpsGGnTE0izn0b/R3YuhH6yzt5nW8FaoHVQC/A7NVOfhg==',
        ],
    ],
    'scope' => [
        'fau.de',
        'uni-erlangen.de',
    ],
    'UIInfo' => [
        'DisplayName' => [
            'en' => 'Friedrich-Alexander-Universität Erlangen-Nürnberg (FAU)',
            'de' => 'Friedrich-Alexander-Universität Erlangen-Nürnberg (FAU)',
        ],
        'Description' => [
            'en' => 'Identity Provider of Friedrich-Alexander-Universität Erlangen-Nürnberg (FAU)',
            'de' => 'Identity Provider der Friedrich-Alexander-Universität Erlangen-Nürnberg (FAU)',
        ],
        'InformationURL' => [
            'en' => 'https://www.sso.fau.de/imprint',
            'de' => 'https://www.sso.fau.de/impressum',
        ],
        'PrivacyStatementURL' => [
            'en' => 'https://www.sso.fau.de/privacy',
            'de' => 'https://www.sso.fau.de/datenschutz',
        ],
        'Keywords' => [
            'en' => [
                'university',
                'friedrich-alexander',
                'erlangen',
                'nuremberg',
                'fau',
            ],
            'de' => [
                'universität',
                'friedrich-alexander',
                'erlangen',
                'nürnberg',
                'fau',
            ],
        ],
        'Logo' => [
            [
                'url' => 'https://idm.fau.de/static/images/logos/fau_kernmarke_q_rgb_blue.svg',
                'height' => 32,
                'width' => 32,
            ],
            [
                'url' => 'https://idm.fau.de/static/images/logos/fau_kernmarke_q_rgb_blue.svg',
                'height' => 192,
                'width' => 192,
            ],
        ],
    ],
    'DiscoHints' => [
        'IPHint' => [],
        'DomainHint' => [
            'fau.de',
            'www.fau.de',
        ],
        'GeolocationHint' => [
            'geo:49.59793616990235,11.004654332497283',
        ],
    ],
    'name' => [
        'en' => 'Friedrich-Alexander-Universität Erlangen-Nürnberg (FAU)',
        'de' => 'Friedrich-Alexander-Universität Erlangen-Nürnberg (FAU)',
    ],
];
```

Hinweis: Bitte überprüfen Sie, dass die Metadaten-URL keine Fehlermeldung im Browser auslöst.
