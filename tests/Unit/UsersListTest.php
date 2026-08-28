<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\Tests\TestCase;
use RRZE\SSO\UsersList;

#[CoversClass(UsersList::class)]
class UsersListTest extends TestCase
{
    public function testColumnsAddsSsoInformation(): void
    {
        Functions\when('__')->returnArg();

        $columns = (new UsersList())->columns(array('email' => 'Email'));

        self::assertSame('Email', $columns['email']);
        self::assertSame('Organization', $columns['organization']);
        self::assertSame('Attributes', $columns['attributes']);
    }

    public function testOrganizationColumnUsesMetadataAndFallback(): void
    {
        Functions\when('get_user_meta')->alias(
            static fn(int $userId, string $key): string => 7 === $userId ? 'Example University' : ''
        );
        $list = new UsersList();

        self::assertSame('unchanged', $list->organizationColumn('unchanged', 'email', 7));
        self::assertSame('Example University', $list->organizationColumn('', 'organization', 7));
        self::assertSame('&mdash;', $list->organizationColumn('', 'organization', 8));
    }

    public function testAttributesColumnCombinesArrayAndScalarMetadata(): void
    {
        Functions\when('get_user_meta')->alias(
            static function (int $userId, string $key) {
                return array(
                    'edu_person_affiliation' => array('member', 'staff'),
                    'edu_person_entitlement' => 'urn:example:access',
                )[$key] ?? '';
            }
        );
        $list = new UsersList();

        self::assertSame('old', $list->attributesColumn('old', 'email', 7));
        self::assertSame(
            'member<br>staff<br>urn:example:access',
            $list->attributesColumn('', 'attributes', 7)
        );
    }
}
