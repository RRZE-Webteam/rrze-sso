# RRZE-SSO

Single-Sign-On (SSO) SAML-Integrations-Plugin für WordPress.

## WP-Einstellungsmenü

Einstellungen › SSO

## Bereitstellung eines FAU-SP (Service Provider der FAU) mittels SimpleSAMLphp

-   1. Letzte version des SimpleSAMLphp herunterladen. Siehe https://simplesamlphp.org/download
-   2. Bitte folgen Sie den Anweisungen zur Installation von SimpleSAMLphp unter diesem Link: https://simplesamlphp.org/docs/stable/simplesamlphp-install
-   3. Folgenden Attribute in der Datei "/simplesamlphp/config/config.php" ändern/bearbeiten:

<pre>
'auth.adminpassword' = 'Beliebiges Admin-Password',
'secretsalt' => 'Beliebige, möglichst einzigartige Phrase',
'technicalcontact_name' => 'Name des technischen Ansprechpartners',
'technicalcontact_email' => 'E-Mail-Adresse des technischen Ansprechpartners',
'authproc.sp' => [
    10 => [
        'class' => 'core:AttributeMap',
        'urn2name',
    ],
    90 => 'core:LanguageAdaptor',
],
</pre>

-   4. Folgende Element des "default-sp"-Array in der Datei "/simplesamlphp/config/authsources.php" ändern/bearbeiten:

<pre>
'idp' = 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php'
</pre>

-   5. Den Inhalt der Datei "/simplesamlphp/metadata/saml20-idp-remote.php" löschen und dann den folgenden Code hinzufügen:

```php
<?php
$metadata['https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php'] = [
    'metadata-set' => 'saml20-idp-remote',
    'entityid' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php',
    'SingleSignOnService' => [
        [
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SSOService.php',
        ],
        [
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
            'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SSOService.php',
        ],
        [
            'index' => 0,
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:SOAP',
            'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SSOService.php',
        ],
    ],
    'SingleLogoutService' => [
        [
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SingleLogoutService.php',
        ],
    ],
    'certData' => 'MIIEFzCCAn+gAwIBAgIUZu6JGUynt+HmkDyTBVufV8brvfIwDQYJKoZIhvcNAQELBQAwIjEgMB4GA1UEAxMXd3d3LnNzby51bmktZXJsYW5nZW4uZGUwHhcNMjIxMDI1MTI0NDIzWhcNMjUxMTE5MTI0NDIzWjAiMSAwHgYDVQQDExd3d3cuc3NvLnVuaS1lcmxhbmdlbi5kZTCCAaIwDQYJKoZIhvcNAQEBBQADggGPADCCAYoCggGBAKV+3afbJg5B5r94ZuQMPRfFJdmixpAPiRqqif0hoXC4GAAd09txIWp2sMLWOEseiBYfSndBOz5OUHfyxvQ3IKubucP26leZxvyEDBymlaMw6ad2pi5JdCycJegGgkH2rThiNRK9rYLjyO5oUuCNumMBwqN1rCxaTsf6vC97cv5sEAoH551jNSPDYVvbn1/uUNw15GQuvvU43N3N3efiLnbRjUE8Ih/qDYp6v63/nxExINt7xgErgvD82k0gHrBJv7BIOMe7WmTQ2yQYC8FnzvCwHdHnZ8i1vRzPDYFTktxxn6Vsu1YMeNdd2K4Q+LLb/ljdoSxrNMKiwz9ls1Mj059hxlo3Q1g6JAYSZc9Lzqo26iTYLlq/LfF259OENIZX6FeQqDExK8BPLX6OXlneeksTxl9Bohsga3QPtRMRlGF415hmtpunW9LSiF1VewcKwpvjoEcDK+wutI/N7RNRhLNauUPQz16v1gZJDim4/zLB3Nfh19kLfJnlcRVnIkpKQQIDAQABo0UwQzAiBgNVHREEGzAZghd3d3cuc3NvLnVuaS1lcmxhbmdlbi5kZTAdBgNVHQ4EFgQUPB7olQAUEMwG9ImXfbDXtdLNPeUwDQYJKoZIhvcNAQELBQADggGBAEiIFXch6BzXOeU4S5/YgTQ/dHYpwHAc93YYP6WmlzEambSx+HGu4c6eav9zSrRhIVxwHkPE1nGJzBvtcM0FMML9/5U7keOtAcD7jkcHfnrC5cz9bWEbVpu4pSGVK1OWvC24gqwLn7++W3lx7prwpN7fO1uCSsudT3oOhSjy3oEJvtnBS26pqf/FFBUl6slZ4M3uVGUuf4q0PVXRIjK04oM8AwSO2Bb3tYU4u1lTBJkXJ+nFZGd8BcyYpFkQVY9/8iElY2qDWS6q1hNJ4c/phS7heJlk98MqtBeFw/Jo4juukdfIAtGmRpLg2xN3FzO2eoIzFgwQKrMrwMrTlovL71MEYkX/2NJ+TUCMcwseeAQaa8IgCXWfP7eD/RnS3DNj6su3Zes7W9HIpUJP33Ds3I+h0+QU9OYTnsjhxfOZOUm8BxNLvtBwVxKUmtJkh3zX/8F/exHXRaB2h0jx7iQ8bjpsGGnTE0izn0b/R3YuhH6yzt5nW8FaoHVQC/A7NVOfhg==',
    'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
    'scope' => [
        'fau.de',
        'uni-erlangen.de',
    ],
    'contacts' => [
        [
            'emailAddress' => 'sso-support@fau.de',
            'contactType' => 'technical',
            'givenName' => 'SSO-Admins RRZE FAU',
        ],
    ],
];
```

## Anmeldung eines FAU-SP (Service Provider der FAU)

-   Folgende Info an sso-admins@rrze.fau.de versenden:

<pre>
Webseite: (URL der Webseite)
Beschreibung: (Kurze Beschreibung der Webseite)
Metadata-URL: https://webauftritt-url/simplesaml/module.php/saml/sp/metadata.php/default-sp
Erforderliche Attribute:
displayname
givenName
sn (Surname)
o (Organization name)
uid
mail
eduPersonPrincipalName
eduPersonAffiliation
eduPersonEntitlement
eduPersonScopedAffiliation
</pre>

Hinweis: Bitte überprüfen Sie, dass die Metadaten-URL keine Fehlermeldung im Browser auslöst.
