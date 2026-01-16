# RRZE-SSO

Single-Sign-On (SSO) SAML-Integrations-Plugin für WordPress.

## WP-Einstellungsmenü

Einstellungen › SSO

## Bereitstellung eines FAU-SP (Service Provider der FAU) mittels SimpleSAMLphp

-   1. Letzte version des SimpleSAMLphp herunterladen. Siehe https://simplesamlphp.org/download
-   2. Bitte folgen Sie den Anweisungen zur Installation von SimpleSAMLphp unter diesem Link: https://simplesamlphp.org/docs/stable/simplesamlphp-install
-   3. Folgenden Attribute in der Datei "simplesamlphp/config/config.php" ändern/bearbeiten:

```php
'technicalcontact_name' => 'Name des technischen Ansprechpartners',
'technicalcontact_email' => 'E-Mail-Adresse des technischen Ansprechpartners',

// ...

'secretsalt' => 'Beliebige, möglichst einzigartige Phrase',
'auth.adminpassword' => 'Hash-String', // Tipp: Führen Sie simplesamlphp/bin/pwgen.php aus, um einen Hash zu generieren.

// ...

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

-   4. Folgende Element des "default-sp"-Array in der Datei "simplesamlphp/config/authsources.php" ändern/bearbeiten:

```php
'idp' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php',
```

-   5. Den Inhalt der Datei "simplesamlphp/metadata/saml20-idp-remote.php" löschen und dann den folgenden Code hinzufügen:

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
            'X509Certificate' => 'MIIEFzCCAn+gAwIBAgIUcoi39eQi65FjX12ddxhsqt5Yt2wwDQYJKoZIhvcNAQELBQAwIjEgMB4GA1UEAxMXd3d3LnNzby51bmktZXJsYW5nZW4uZGUwHhcNMjUxMDI4MTAxNzUzWhcNMjkwMTEwMTAxNzUzWjAiMSAwHgYDVQQDExd3d3cuc3NvLnVuaS1lcmxhbmdlbi5kZTCCAaIwDQYJKoZIhvcNAQEBBQADggGPADCCAYoCggGBAJkUyZHH1WOWJk0bUoPcsX72CzbQ2D4QM0BJVbDL88qKllnl/869Vt+Gtqtxe+j80qb5yhM/oLnPA1yRMVGe2LW6iQp1zYdPC1p0zhvE5ncQH3OLiFqDoPujwDiMj4mB4zjZhOvlUyLDoUsHWmWupfMflY9Ns/K2tel5CJCUvxvlO2d0+YxSuqN6f11kikfIq4RU+6GwDrnUTmepmeJOLqFUDGTkbMOxvBg4X5O8qJ1ISgSg/uSu3OBb+FqjsjNgD6gQ0DDaYSz2pcRpwh/ehV6sdN4RR/InOhzfwZXZ53R2BCPS578Pjzc7vWj3/j+igN6tYE6keVXqA5+oYyRu83v23sgHOFkORWI7znNJVAFz8IvwMqdHDPxj+q1hMBa8RE5bJwztXf4AtF1CqpBUnvFY2MIv+isa06PtPbcTUnEHeu2W9xySc7dBCFuvm6OKwevKqh8C3Zy500KEMtv3EdLhyxQRazupw7JorVmYct4Erk/uobjgqjtMvl0zi3+5xwIDAQABo0UwQzAiBgNVHREEGzAZghd3d3cuc3NvLnVuaS1lcmxhbmdlbi5kZTAdBgNVHQ4EFgQUfU5+ck8hl5o5Wt+bfuZK+dDSa1YwDQYJKoZIhvcNAQELBQADggGBADP732ctFE22blSD6xuSjsSy7/t8BELWviZmLJ0Yn1UGkrGES7QllIbrtM5KsksHXtPAFYCqwPSSMcM4XngkJSaUnRhPnDyYa54L/lQFhe9jfDjecDCtav7P1skqph62CqKA8uQej3/c699UP3wyKrJNCxSV0MmabkdMY+F77Cl8px4pDrNUXSo1ALzC9enWqLe7zKUfLpIjgMTWZuqgklTW7aLT16tqgFaAIey2Xc6xTxr3GUqQaCx+N15cW2EAZOD4fpikzrn6ckvDvkWxmrwGHhkHx68tD8k7HrTe3GhxCtesDzFyyypuuTlChkX5B6LQ65maRGdVSvJd4IPF4bB/HaW4KLig95vjKrkS2PZjufQ4NzjpEWAVhotyPKuAIGVBN189gGmm/Z0gZtz557K9Dpk1jBgOu6qnsvvBx9FHD/6dpEkRX9fmTgyLmcdMEiaiAke1dG+vVjLC2sTcMV32Ur3cTDIcWzawl3kWy8vF+Fjonghh4xUx3XNZ1g86MA==',
        ],
        [
            'encryption' => true,
            'signing' => false,
            'type' => 'X509Certificate',
            'X509Certificate' => 'MIIEFzCCAn+gAwIBAgIUcoi39eQi65FjX12ddxhsqt5Yt2wwDQYJKoZIhvcNAQELBQAwIjEgMB4GA1UEAxMXd3d3LnNzby51bmktZXJsYW5nZW4uZGUwHhcNMjUxMDI4MTAxNzUzWhcNMjkwMTEwMTAxNzUzWjAiMSAwHgYDVQQDExd3d3cuc3NvLnVuaS1lcmxhbmdlbi5kZTCCAaIwDQYJKoZIhvcNAQEBBQADggGPADCCAYoCggGBAJkUyZHH1WOWJk0bUoPcsX72CzbQ2D4QM0BJVbDL88qKllnl/869Vt+Gtqtxe+j80qb5yhM/oLnPA1yRMVGe2LW6iQp1zYdPC1p0zhvE5ncQH3OLiFqDoPujwDiMj4mB4zjZhOvlUyLDoUsHWmWupfMflY9Ns/K2tel5CJCUvxvlO2d0+YxSuqN6f11kikfIq4RU+6GwDrnUTmepmeJOLqFUDGTkbMOxvBg4X5O8qJ1ISgSg/uSu3OBb+FqjsjNgD6gQ0DDaYSz2pcRpwh/ehV6sdN4RR/InOhzfwZXZ53R2BCPS578Pjzc7vWj3/j+igN6tYE6keVXqA5+oYyRu83v23sgHOFkORWI7znNJVAFz8IvwMqdHDPxj+q1hMBa8RE5bJwztXf4AtF1CqpBUnvFY2MIv+isa06PtPbcTUnEHeu2W9xySc7dBCFuvm6OKwevKqh8C3Zy500KEMtv3EdLhyxQRazupw7JorVmYct4Erk/uobjgqjtMvl0zi3+5xwIDAQABo0UwQzAiBgNVHREEGzAZghd3d3cuc3NvLnVuaS1lcmxhbmdlbi5kZTAdBgNVHQ4EFgQUfU5+ck8hl5o5Wt+bfuZK+dDSa1YwDQYJKoZIhvcNAQELBQADggGBADP732ctFE22blSD6xuSjsSy7/t8BELWviZmLJ0Yn1UGkrGES7QllIbrtM5KsksHXtPAFYCqwPSSMcM4XngkJSaUnRhPnDyYa54L/lQFhe9jfDjecDCtav7P1skqph62CqKA8uQej3/c699UP3wyKrJNCxSV0MmabkdMY+F77Cl8px4pDrNUXSo1ALzC9enWqLe7zKUfLpIjgMTWZuqgklTW7aLT16tqgFaAIey2Xc6xTxr3GUqQaCx+N15cW2EAZOD4fpikzrn6ckvDvkWxmrwGHhkHx68tD8k7HrTe3GhxCtesDzFyyypuuTlChkX5B6LQ65maRGdVSvJd4IPF4bB/HaW4KLig95vjKrkS2PZjufQ4NzjpEWAVhotyPKuAIGVBN189gGmm/Z0gZtz557K9Dpk1jBgOu6qnsvvBx9FHD/6dpEkRX9fmTgyLmcdMEiaiAke1dG+vVjLC2sTcMV32Ur3cTDIcWzawl3kWy8vF+Fjonghh4xUx3XNZ1g86MA==',
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
