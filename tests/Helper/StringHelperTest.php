<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Helper;

use AnzuSystems\CommonBundle\Helper\StringHelper;
use PHPUnit\Framework\TestCase;

final class StringHelperTest extends TestCase
{
    public function testExtractFirstLetterNormalCases(): void
    {
        // Test standard string processing
        $this->assertSame('a', StringHelper::extractFirstLetter('Anderson'));
        $this->assertSame('b', StringHelper::extractFirstLetter('Brown'));
        $this->assertSame('s', StringHelper::extractFirstLetter('Smith'));
        $this->assertSame('j', StringHelper::extractFirstLetter('Johnson'));
    }

    /**
     * @dataProvider normalStringDataProvider
     */
    public function testExtractFirstLetterWithNormalStrings(string $input, string $expected): void
    {
        $result = StringHelper::extractFirstLetter($input);
        $this->assertSame($expected, $result);
    }

    public function testExtractFirstLetterUnicodeHandling(): void
    {
        // Test Slovak character handling (á → a, č → c, etc.)
        $this->assertSame('c', StringHelper::extractFirstLetter('Čapek'));
        $this->assertSame('s', StringHelper::extractFirstLetter('Štefan'));
        $this->assertSame('z', StringHelper::extractFirstLetter('Žitný'));
        $this->assertSame('n', StringHelper::extractFirstLetter('Ňahaj'));
        $this->assertSame('l', StringHelper::extractFirstLetter('Ľuboš'));
        $this->assertSame('d', StringHelper::extractFirstLetter('Ďuriš'));
        $this->assertSame('t', StringHelper::extractFirstLetter('Ťapák'));
    }

    /**
     * @dataProvider unicodeStringDataProvider
     */
    public function testExtractFirstLetterWithUnicodeCharacters(string $input, string $expected): void
    {
        $result = StringHelper::extractFirstLetter($input);
        $this->assertSame($expected, $result);
    }

    public function testExtractFirstLetterEdgeCases(): void
    {
        // Test empty strings, whitespace, single characters
        $this->assertSame('', StringHelper::extractFirstLetter(''));
        $this->assertSame('a', StringHelper::extractFirstLetter('a'));
        $this->assertSame('z', StringHelper::extractFirstLetter('Z'));
        $this->assertSame('s', StringHelper::extractFirstLetter(' Smith')); // Whitespace trimmed
        $this->assertSame('', StringHelper::extractFirstLetter('   ')); // Only whitespace
    }

    /**
     * @dataProvider edgeCaseDataProvider
     */
    public function testExtractFirstLetterWithEdgeCases(string $input, string $expected): void
    {
        $result = StringHelper::extractFirstLetter($input);
        $this->assertSame($expected, $result);
    }

    public function testExtractFirstLetterSpecialCharacters(): void
    {
        // Test numbers, punctuation, symbols
        $this->assertSame('s', StringHelper::extractFirstLetter('1Smith'));
        $this->assertSame('n', StringHelper::extractFirstLetter('2nd Street'));
        $this->assertSame('', StringHelper::extractFirstLetter('!@#$%')); // Special chars get normalized
        $this->assertSame('p', StringHelper::extractFirstLetter('(parentheses)'));
        $this->assertSame('d', StringHelper::extractFirstLetter('-dash'));
    }

    public function testExtractFirstLetterCaseHandling(): void
    {
        // Test uppercase/lowercase normalization
        $this->assertSame('a', StringHelper::extractFirstLetter('A'));
        $this->assertSame('z', StringHelper::extractFirstLetter('Z'));
        $this->assertSame('a', StringHelper::extractFirstLetter('a'));
        $this->assertSame('z', StringHelper::extractFirstLetter('z'));
        $this->assertSame('m', StringHelper::extractFirstLetter('MixedCase'));
        $this->assertSame('l', StringHelper::extractFirstLetter('lowerCase'));
    }

    public function testExtractFirstLetterSlovakNameScenarios(): void
    {
        // Test realistic Slovak name scenarios
        $slovakSurnames = [
            'Novák' => 'n',
            'Kováč' => 'k',
            'Dvořák' => 'd',
            'Horváth' => 'h',
            'Varga' => 'v',
            'Tóth' => 't',
            'Nagy' => 'n',
            'Takács' => 't',
            'Molnár' => 'm',
            'Szabó' => 's',
        ];

        foreach ($slovakSurnames as $surname => $expectedInitial) {
            $result = StringHelper::extractFirstLetter($surname);
            $this->assertSame($expectedInitial, $result);
        }
    }

    public function testExtractFirstLetterAccentedNames(): void
    {
        // Test accented names: "Ľubomír" → "l", "Žofia" → "z", "Čapek" → "c"
        $accentedNames = [
            'Ľubomír' => 'l',
            'Žofia' => 'z',
            'Čapek' => 'c',
            'Šimon' => 's',
            'Ťažký' => 't',
            'Ňuňa' => 'n',
            'Ďaleko' => 'd',
            'Ráž' => 'r',
            'Áno' => 'a',
            'Éva' => 'e',
            'Ívan' => 'i',
            'Óla' => 'o',
            'Úrsula' => 'u',
            'Ýves' => 'y',
        ];

        foreach ($accentedNames as $name => $expectedInitial) {
            $result = StringHelper::extractFirstLetter($name);
            $this->assertSame($expectedInitial, $result);
        }
    }

    public function testExtractFirstLetterCompoundNames(): void
    {
        // Test compound names: "Van Der Berg" → "v", "De Silva" → "d"
        $this->assertSame('v', StringHelper::extractFirstLetter('Van Der Berg'));
        $this->assertSame('d', StringHelper::extractFirstLetter('De Silva'));
        $this->assertSame('v', StringHelper::extractFirstLetter('von Habsburg'));
        $this->assertSame('d', StringHelper::extractFirstLetter('da Vinci'));
        $this->assertSame('l', StringHelper::extractFirstLetter('La Fontaine'));
    }

    public function testExtractFirstLetterNamesWithPrefixes(): void
    {
        // Test names with prefixes: "Mc Donald" → "m", "O'Connor" → "o"
        $this->assertSame('m', StringHelper::extractFirstLetter('McDonald'));
        $this->assertSame('m', StringHelper::extractFirstLetter('MacLeod'));
        $this->assertSame('o', StringHelper::extractFirstLetter("O'Connor"));
        $this->assertSame('o', StringHelper::extractFirstLetter("O'Brien"));
        $this->assertSame('d', StringHelper::extractFirstLetter("D'Angelo"));
    }

    public function testExtractFirstLetterConsistencyForAlphabetFiltering(): void
    {
        // Ensure consistent lowercase output for alphabet filtering
        $testNames = ['Anderson', 'BROWN', 'clark', 'Davis', 'EVANS'];
        $expectedInitials = ['a', 'b', 'c', 'd', 'e'];

        foreach ($testNames as $index => $name) {
            $result = StringHelper::extractFirstLetter($name);
            $this->assertSame($expectedInitials[$index], $result);

            // Verify it's lowercase
            $this->assertSame(strtolower($result), $result);

            // Verify it's suitable for alphabet filtering
            $this->assertTrue(ctype_alpha($result) || '' === $result);
            $this->assertTrue(mb_strlen($result) <= 1);
        }
    }

    public function testExtractFirstLetterPersonTextsCompatibility(): void
    {
        // Test that the method handles the same character sets used in PersonTexts
        $personNames = [
            'John Doe' => 'j',
            'Jane Smith' => 'j',
            'Peter Novák' => 'p',
            'Maria Kováč' => 'm',
            'Disabled Person' => 'd',
            'Spectator Official' => 's',
            'News Editor' => 'n',
            'Sports Manager' => 's',
        ];

        foreach ($personNames as $name => $expectedInitial) {
            $result = StringHelper::extractFirstLetter($name);
            $this->assertSame($expectedInitial, $result);
        }
    }

    public function testExtractFirstLetterNormalizationConsistency(): void
    {
        // Test that the same input always produces the same output
        $testInputs = ['Novák', 'SMITH', 'čapek', 'Žitný'];

        foreach ($testInputs as $input) {
            $result1 = StringHelper::extractFirstLetter($input);
            $result2 = StringHelper::extractFirstLetter($input);
            $result3 = StringHelper::extractFirstLetter($input);

            $this->assertSame($result1, $result2);
            $this->assertSame($result2, $result3);
        }
    }

    public static function normalStringDataProvider(): array
    {
        return [
            'Simple name' => ['Smith', 's'],
            'Uppercase name' => ['JOHNSON', 'j'],
            'Mixed case name' => ['Anderson', 'a'],
            'Single character' => ['A', 'a'],
            'Long name' => ['Schwarzenegger', 's'],
            'Common surname' => ['Brown', 'b'],
            'International name' => ['García', 'g'],
            'Eastern European' => ['Petrov', 'p'],
            'Nordic name' => ['Andersson', 'a'],
            'Celtic name' => ['Murphy', 'm'],
        ];
    }

    public static function unicodeStringDataProvider(): array
    {
        return [
            // Slovak characters
            'Č character' => ['Čapek', 'c'],
            'Š character' => ['Štefan', 's'],
            'Ž character' => ['Žitný', 'z'],
            'Ň character' => ['Ňahaj', 'n'],
            'Ľ character' => ['Ľuboš', 'l'],
            'Ď character' => ['Ďuriš', 'd'],
            'Ť character' => ['Ťapák', 't'],
            'Ŕ character' => ['Ŕíša', 'r'],

            // Accented vowels
            'Á character' => ['Álvarez', 'a'],
            'É character' => ['Éva', 'e'],
            'Í character' => ['Ívan', 'i'],
            'Ó character' => ['Óla', 'o'],
            'Ú character' => ['Úrsula', 'u'],
            'Ý character' => ['Ýves', 'y'],

            // International characters
            'German Ö' => ['Ömer', 'o'],
            'German Ü' => ['Üwe', 'u'],
            'German Ä' => ['Äpfel', 'a'],
            'French Ç' => ['Çelik', 'c'],
            'Spanish Ñ' => ['Ñoño', 'n'],
            'Polish Ł' => ['Łukasz', 'l'],
        ];
    }

    public static function edgeCaseDataProvider(): array
    {
        return [
            'Empty string' => ['', ''],
            'Single space' => [' ', ''],
            'Multiple spaces' => ['   ', ''],
            'Leading space' => [' Smith', 's'],
            'Tab character' => ["\t", ''],
            'Newline character' => ["\n", ''],
            'Mixed whitespace' => [" \t\n ", ''],
            'Single letter' => ['a', 'a'],
            'Single uppercase' => ['Z', 'z'],
            'Number first' => ['1Smith', 's'],
            'Special char first' => ['!Important', 'i'],
            'Hyphen first' => ['-dash', 'd'],
            'Underscore first' => ['_underscore', 'u'],
            'Parenthesis first' => ['(test)', 't'],
        ];
    }
}
