<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\Feed;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PDO;
use WPDev\PhpSpreadsheetOData\Contracts\FeedResolverInterface;

final class PdoFeedResolver implements FeedResolverInterface
{
    /** @var PDO */
    private $pdo;

    /** @var string */
    private $tableName;

    /** @var callable */
    private $loader;

    /**
     * @param callable(string): ?Spreadsheet $loader
     */
    public function __construct(PDO $pdo, string $tableName, callable $loader)
    {
        $this->pdo = $pdo;
        $this->tableName = $tableName;
        $this->loader = $loader;
    }

    public function resolve(string $feedId): ?Spreadsheet
    {
        $sql = sprintf(
            'SELECT source_ref FROM %s WHERE feed_id = :feed_id LIMIT 1',
            $this->quoteTableName($this->tableName)
        );

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['feed_id' => $feedId]);
        $sourceRef = $statement->fetchColumn();

        if ($sourceRef === false) {
            return null;
        }

        return ($this->loader)((string) $sourceRef);
    }

    public static function createTable(PDO $pdo, string $tableName = 'odata_feeds'): void
    {
        $pdo->exec(sprintf(
            'CREATE TABLE %s (feed_id TEXT PRIMARY KEY NOT NULL, source_ref TEXT NOT NULL)',
            self::quoteTableNameStatic($tableName)
        ));
    }

    private function quoteTableName(string $tableName): string
    {
        return self::quoteTableNameStatic($tableName);
    }

    private static function quoteTableNameStatic(string $tableName): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $tableName)) {
            throw new \InvalidArgumentException(sprintf('Invalid table name "%s".', $tableName));
        }

        return $tableName;
    }
}