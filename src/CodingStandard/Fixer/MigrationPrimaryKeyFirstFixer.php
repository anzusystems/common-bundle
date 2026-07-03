<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\CodingStandard\Fixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;
use Symfony\Component\String\UnicodeString;

final class MigrationPrimaryKeyFirstFixer extends AbstractFixer
{
    private const int LAST_MIGRATIONS_COUNT = 10;

    /**
     * @var list<string>|null
     */
    private ?array $lastMigrations = null;

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'In CREATE TABLE statements within migrations, the primary key column must be the first column.',
            [
                new CodeSample(
                    "<?php\n\$this->addSql('CREATE TABLE foo (name VARCHAR(255) NOT NULL, id INT UNSIGNED AUTO_INCREMENT NOT NULL, PRIMARY KEY(id))');\n"
                ),
            ]
        );
    }

    public function getName(): string
    {
        return 'AnzuSystems/migration_primary_key_first';
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_CONSTANT_ENCAPSED_STRING)
            || $tokens->isTokenKindFound(T_ENCAPSED_AND_WHITESPACE)
            || $tokens->isTokenKindFound(T_START_HEREDOC);
    }

    public function supports(SplFileInfo $file): bool
    {
        if (false === (new UnicodeString($file->getPathname()))->containsAny('src/Migrations/')) {
            return false;
        }

        return in_array($file->getFilename(), $this->getLastMigrationFilenames($file), true);
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        foreach ($tokens as $index => $token) {
            if (false === $token->isGivenKind([T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE])) {
                continue;
            }

            $content = $token->getContent();
            $sql = (new UnicodeString($content))->ignoreCase();

            if (false === $sql->containsAny('CREATE TABLE') || false === $sql->containsAny('PRIMARY KEY')) {
                continue;
            }

            $fixed = $this->fixCreateTableStatements($content);

            if ($fixed !== $content) {
                $tokens[$index] = new Token([$token->getId(), $fixed]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function getLastMigrationFilenames(SplFileInfo $file): array
    {
        if (null !== $this->lastMigrations) {
            return $this->lastMigrations;
        }

        $migrationsDir = dirname($file->getPathname());
        $files = glob($migrationsDir . '/Version*.php');
        sort($files);
        $lastFiles = array_slice($files, -self::LAST_MIGRATIONS_COUNT);
        $this->lastMigrations = array_map(static fn (string $path): string => basename($path), $lastFiles);

        return $this->lastMigrations;
    }

    private function fixCreateTableStatements(string $sql): string
    {
        return (new UnicodeString($sql))->replaceMatches(
            '/CREATE\s+TABLE\s+\S+\s*\((.+)\)/i',
            fn (array $matches): string => $this->fixColumns($matches[0], $matches[1])
        )->toString();
    }

    private function fixColumns(string $fullMatch, string $columnsString): string
    {
        $columns = $this->splitColumns($columnsString);
        $pkColumnName = $this->findPrimaryKeyColumnName($columns);

        if (null === $pkColumnName) {
            return $fullMatch;
        }

        $pkIndex = $this->findColumnIndex(columns: $columns, columnName: $pkColumnName);

        if (null === $pkIndex || 0 === $pkIndex) {
            return $fullMatch;
        }

        $reordered = [$columns[$pkIndex], ...array_values(
            array_diff_key($columns, [$pkIndex => true])
        )];

        return (new UnicodeString($fullMatch))->replace($columnsString, implode(', ', $reordered))
            ->toString();
    }

    /**
     * @param list<string> $columns
     */
    private function findPrimaryKeyColumnName(array $columns): ?string
    {
        foreach ($columns as $column) {
            if (preg_match('/PRIMARY\s+KEY\s*\((\w+)\)/i', $column, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * @param list<string> $columns
     */
    private function findColumnIndex(array $columns, string $columnName): ?int
    {
        foreach ($columns as $index => $column) {
            if (preg_match('/^\s*' . preg_quote($columnName, '/') . '\b/i', $column)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function splitColumns(string $columnsString): array
    {
        preg_match_all('/(?:[^,(]+|\([^)]*\))+/', $columnsString, $matches);

        return array_map(trim(...), $matches[0]);
    }
}
