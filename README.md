# RRZE-SSO

Single-Sign-On (SSO) SAML-Integrations-Plugin für WordPress.

## WP-Einstellungsmenü

Einstellungen › SSO

## Bereitstellung eines FAU-SP (Service Provider der FAU) mittels SimpleSAMLphp

-   1. Letzte version des SimpleSAMLphp herunterladen. Siehe https://simplesamlphp.org/download
-   2. Das simplesamlphp-Verzeichnis kopieren und unter dem wp-content-Verzeichnis des WordPress einsetzen
-   3. Folgenden Attribute in der Datei /simplesamlphp/config/config.php ändern/bearbeiten:

<pre>
'auth.adminpassword' = 'Beliebiges Admin-Password'
'secretsalt' => 'Beliebige, möglichst einzigartige Phrase'
'technicalcontact_name' => 'Name des technischen Ansprechpartners'
'technicalcontact_email' => 'E-Mail-Adresse des technischen Ansprechpartners'
</pre>

-   4. Folgende Element des "default-sp"-Array in der Datei /simplesamlphp/config/authsources.php ändern/bearbeiten:

<pre>
'idp' = 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php'
</pre>

-   5. Alle IdPs von der Datei /simplesamlphp/metadata/saml20-idp-remote.php entfernen und dann den folgenden Code hinzufügen:

```php
<?php
$metadata['https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php'] = array (
  'metadata-set' => 'saml20-idp-remote',
  'entityid' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php',
  'SingleSignOnService' =>
  array (
    0 =>
    array (
      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
      'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SSOService.php',
    ),
    1 =>
    array (
      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
      'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SSOService.php',
    ),
    2 =>
    array (
      'index' => 0,
      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:SOAP',
      'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SSOService.php',
    ),
  ),
  'SingleLogoutService' =>
  array (
    0 =>
    array (
      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
      'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SingleLogoutService.php',
    ),
  ),
  'certData' => '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',
  'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
  'contacts' =>
  array (
    0 =>
    array (
      'emailAddress' => 'sso-support@fau.de',
      'contactType' => 'technical',
      'givenName' => 'Frank',
      'surName' => 'Tröger',
    ),
  ),
);
```

## Webserver-Einstellungen (Apache)

-   Standard- und SSL-Virtualhost einrichten.

-   Alias für SimpleSAMLphp im SSL-Virtualhost einrichten:

<pre>Alias /simplesaml /Pfad zum simplesamlphp/www-Verzeichnis</pre>

Z.B.: Alias /simplesaml /wordpress/wp-content/simplesamlphp/www

## Anmeldung

-   Folgende Info an sso-admins@rrze.fau.de versenden:

<pre>
Webseite: (URL der Webseite)
Beschreibung: (Kurze Beschreibung der Webseite)
Metadata-URL: https://webauftritt-url/simplesaml/module.php/saml/sp/metadata.php/default-sp
Login-URL: https://webauftritt-url/wp-login.php
Erforderliche Attribute:
	displayname
	uid
	mail
    eduPersonAffiliation
</pre>

Hinweis: Bitte überprüfen Sie, dass die jeweiligen URLs keine Fehlermeldungen im Browser ausgeben.
