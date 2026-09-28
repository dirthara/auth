<?php

declare(strict_types=1);

namespace Dirthara\Auth\Tests;

use Error;
use stdClass;
use Stringable;
use PHPUnit\Framework\TestCase;
use Dirthara\Auth\AuthorisationDenial;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

final class AuthorisationDenialTest extends TestCase
{
    #[Test]
    public function it_carries_its_message_key_and_parameters(): void
    {
        $denial = new AuthorisationDenial(messageKey: '{actor} cannot modify this resource.', parameters: [
            'actor' => 'Ada',
        ]);

        self::assertSame('{actor} cannot modify this resource.', $denial->messageKey);
        self::assertSame(['actor' => 'Ada'], $denial->parameters);
        self::assertSame('Ada cannot modify this resource.', $denial->message);
    }

    #[Test]
    public function it_has_no_parameters_by_default(): void
    {
        $denial = new AuthorisationDenial(messageKey: 'You can only edit posts you own.');

        self::assertSame([], $denial->parameters);
        self::assertSame('You can only edit posts you own.', $denial->message);
    }

    #[Test]
    public function it_fills_in_every_placeholder_with_its_parameter(): void
    {
        $denial = new AuthorisationDenial(messageKey: '{actor} cannot {ability} post {post}.', parameters: [
            'actor' => 'Ada',
            'ability' => 'delete',
            'post' => 42,
        ]);

        self::assertSame('Ada cannot delete post 42.', $denial->message);
    }

    #[Test]
    public function it_leaves_a_placeholder_without_a_parameter_as_it_is(): void
    {
        self::assertSame(
            '{actor} cannot modify this resource.',
            new AuthorisationDenial('{actor} cannot modify this resource.')->message,
        );
    }

    #[Test]
    public function it_ignores_a_parameter_without_a_placeholder(): void
    {
        $denial = new AuthorisationDenial('You can only edit posts you own.', parameters: ['actor' => 'Ada']);

        self::assertSame('You can only edit posts you own.', $denial->message);
    }

    #[Test]
    public function it_does_not_treat_input_as_a_placeholder_of_its_own(): void
    {
        self::assertSame('{input} is not allowed.', new AuthorisationDenial('{input} is not allowed.')->message);
    }

    #[Test]
    public function it_replaces_placeholders_once_without_replacing_placeholders_in_parameters(): void
    {
        $denial = new AuthorisationDenial('{actor} cannot edit {post}.', parameters: [
            'actor' => '{post}',
            'post' => 'this post',
        ]);

        self::assertSame('{post} cannot edit this post.', $denial->message);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function parameters(): iterable
    {
        yield 'string' => ['text', 'text'];
        yield 'integer' => [18, '18'];
        yield 'float' => [1.5, '1.5'];
        yield 'true' => [true, 'true'];
        yield 'false' => [false, 'false'];
        yield 'null' => [null, 'null'];
        yield 'stringable' => [
            new class() implements Stringable {
                public function __toString(): string
                {
                    return 'stringable';
                }
            },
            'stringable',
        ];
        yield 'list' => [[1, 2], '1, 2'];
        yield 'mixed list' => [['a', true, null, 1.5], 'a, true, null, 1.5'];
        yield 'nested list' => [[1, [2, 3]], '1, 2, 3'];
        yield 'empty array' => [[], ''];
        yield 'object' => [new stdClass(), 'stdClass'];
    }

    #[Test]
    #[DataProvider('parameters')]
    public function it_renders_each_kind_of_parameter(mixed $parameter, string $rendered): void
    {
        $denial = new AuthorisationDenial('The value is {value}.', parameters: ['value' => $parameter]);

        self::assertSame('The value is ' . $rendered . '.', $denial->message);
    }

    #[Test]
    public function it_cannot_have_its_message_written(): void
    {
        $denial = new AuthorisationDenial('You can only edit posts you own.');

        $this->expectException(Error::class);

        // @mago-expect analysis:invalid-property-write Writing the message is the point of the test
        $denial->message = 'other';
    }

    #[Test]
    public function it_cannot_have_its_message_key_written(): void
    {
        $denial = new AuthorisationDenial('You can only edit posts you own.');

        $this->expectException(Error::class);

        // @mago-expect analysis:invalid-property-write Writing the message key is the point of the test
        $denial->messageKey = 'other';
    }

    #[Test]
    public function it_cannot_have_its_parameters_written(): void
    {
        $denial = new AuthorisationDenial('{actor} cannot modify this resource.', parameters: ['actor' => 'Ada']);

        $this->expectException(Error::class);

        // @mago-expect analysis:invalid-property-write Writing the parameters is the point of the test
        $denial->parameters = [];
    }
}
