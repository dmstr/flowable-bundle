<?php
// file generated with AI assistance: Claude Code - 2026-11-27 00:16:24 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Schema;

use Dmstr\Flowable\Schema\ProcessVariablePlaceholderResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * How {@see ProcessVariablePlaceholderResolver} substitutes `{{ name }}`
 * tokens in a form schema: only bare identifiers that are keys of the
 * variable map, in string values only, in one pass; everything else stays.
 */
final class ProcessVariablePlaceholderResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function substitutions(): iterable
    {
        yield 'spaces inside the braces' => ['{{ orderId }}', ['orderId' => 'A-1'], 'A-1'];
        yield 'no whitespace' => ['{{orderId}}', ['orderId' => 'A-1'], 'A-1'];
        yield 'several spaces' => ['{{   orderId   }}', ['orderId' => 'A-1'], 'A-1'];
        yield 'tab and newline' => ["{{\torderId\n}}", ['orderId' => 'A-1'], 'A-1'];
        yield 'underscore identifier' => ['{{ _order_id2 }}', ['_order_id2' => 'x'], 'x'];
        yield 'inside a URL' => ['/api/customers?order={{ orderId }}&x=1', ['orderId' => 'A-1'], '/api/customers?order=A-1&x=1'];
        yield 'several tokens in one string' => ['{{ a }}-{{b}}-{{ a }}', ['a' => '1', 'b' => '2'], '1-2-1'];
        yield 'integer' => ['n={{ count }}', ['count' => 42], 'n=42'];
        yield 'zero' => ['n={{ count }}', ['count' => 0], 'n=0'];
        yield 'negative integer' => ['n={{ count }}', ['count' => -3], 'n=-3'];
        yield 'float' => ['p={{ price }}', ['price' => 1.5], 'p=1.5'];
        yield 'true' => ['f={{ flag }}', ['flag' => true], 'f=true'];
        yield 'false' => ['f={{ flag }}', ['flag' => false], 'f=false'];
        yield 'null' => ['v={{ customerName }}', ['customerName' => null], 'v='];
        yield 'list value' => ['v={{ items }}', ['items' => ['a', 'b']], 'v='];
        yield 'map value' => ['v={{ items }}', ['items' => ['k' => 'v']], 'v='];
        yield 'object value' => ['v={{ items }}', ['items' => new \stdClass()], 'v='];
        yield 'empty string value' => ['v={{ customerName }}', ['customerName' => ''], 'v='];
        yield 'value looking like a token is not resolved again' => ['{{ a }}', ['a' => '{{ b }}', 'b' => 'no'], '{{ b }}'];
        yield 'triple braces resolve the inner token' => ['{{{ a }}}', ['a' => '1'], '{1}'];
    }

    /**
     * @param array<string, mixed> $variables
     */
    #[DataProvider('substitutions')]
    public function testTokenIsReplaced(string $value, array $variables, string $expected): void
    {
        self::assertSame(['v' => $expected], ProcessVariablePlaceholderResolver::resolve(['v' => $value], $variables));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function untouchedTokens(): iterable
    {
        yield 'dotted client-side token' => ['{{ orderId.value }}'];
        yield 'dotted without whitespace' => ['{{orderId.value}}'];
        yield 'unknown identifier' => ['{{ customerName }}'];
        yield 'other case' => ['{{ OrderId }}'];
        yield 'hyphen' => ['{{ order-id }}'];
        yield 'leading digit' => ['{{ 1orderId }}'];
        yield 'unicode identifier' => ['{{ größe }}'];
        yield 'unicode first letter' => ['{{ éтап }}'];
        yield 'single braces' => ['{ orderId }'];
        yield 'empty token' => ['{{ }}'];
        yield 'unclosed token' => ['{{ orderId'];
        yield 'two identifiers' => ['{{ orderId customerName }}'];
    }

    #[DataProvider('untouchedTokens')]
    public function testTokenStaysUnchanged(string $value): void
    {
        $variables = ['orderId' => 'A-1', 'order' => 'B', 'größe' => 'L', 'éтап' => 'x', '1orderId' => 'y'];

        self::assertSame(['v' => $value], ProcessVariablePlaceholderResolver::resolve(['v' => $value], $variables));
    }

    public function testEmptyVariableListLeavesTheSchemaUnchanged(): void
    {
        $schema = ['x-collection' => '/api/customers?order={{ orderId }}', 'title' => '{{ customerName }}'];

        self::assertSame($schema, ProcessVariablePlaceholderResolver::resolve($schema, []));
    }

    public function testTokenAsArrayKeyIsNotReplaced(): void
    {
        $schema = ['properties' => ['{{ orderId }}' => ['title' => '{{ orderId }}']]];

        self::assertSame(
            ['properties' => ['{{ orderId }}' => ['title' => 'A-1']]],
            ProcessVariablePlaceholderResolver::resolve($schema, ['orderId' => 'A-1']),
        );
    }

    public function testNestedStructuresAndListsAreWalked(): void
    {
        $schema = [
            'type' => 'object',
            'required' => ['customerName'],
            'properties' => [
                'customerName' => [
                    'type' => 'string',
                    'default' => '{{ customerName }}',
                    'enum' => ['{{ customerName }}', 'other', '{{ unknown }}'],
                ],
                'lines' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'x-collection' => '/api/lines?order={{orderId}}',
                        'x-template' => '{{ line.label }}',
                    ],
                ],
            ],
        ];

        $expected = $schema;
        $expected['properties']['customerName']['default'] = 'Jane';
        $expected['properties']['customerName']['enum'] = ['Jane', 'other', '{{ unknown }}'];
        $expected['properties']['lines']['items']['x-collection'] = '/api/lines?order=A-1';

        self::assertSame($expected, ProcessVariablePlaceholderResolver::resolve($schema, ['customerName' => 'Jane', 'orderId' => 'A-1', 'line' => 'x']));
    }

    public function testNonStringSchemaValuesStayAsTheyAre(): void
    {
        $object = new \stdClass();
        $object->title = '{{ orderId }}';
        $schema = ['minimum' => 5, 'readOnly' => true, 'default' => null, 'ratio' => 0.5, 'meta' => $object];

        $resolved = ProcessVariablePlaceholderResolver::resolve($schema, ['orderId' => 'A-1']);

        self::assertSame($schema, $resolved);
        self::assertSame('{{ orderId }}', $resolved['meta']->title, 'objects in the schema are not walked');
    }

    public function testStringWithoutTokensIsUnchanged(): void
    {
        $schema = ['title' => 'Order', 'pattern' => '^\d{2}$', 'description' => 'Use {braces} freely'];

        self::assertSame($schema, ProcessVariablePlaceholderResolver::resolve($schema, ['orderId' => 'A-1']));
    }
}
