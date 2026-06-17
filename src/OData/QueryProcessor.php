<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\OData;

use WPDev\PhpSpreadsheetOData\Contracts\QueryHandlerInterface;
use WPDev\PhpSpreadsheetOData\Support\Str;

final class QueryProcessor implements QueryHandlerInterface
{
    private const MAX_TOP = 1000;

    private const MAX_SKIP = 100000;
    /**
     * @param list<array<string, mixed>> $entities
     * @param array<string, string> $queryParams
     * @return array{value: list<array<string, mixed>>, count: int|null}
     */
    public function apply(array $entities, array $queryParams): array
    {
        $result = $entities;

        if (isset($queryParams['$filter'])) {
            $result = $this->applyFilter($result, $queryParams['$filter']);
        }

        $count = null;

        if (isset($queryParams['$count'])) {
            $countVal = strtolower($queryParams['$count']);
            if ($countVal !== 'true' && $countVal !== 'false') {
                throw new \InvalidArgumentException('The $count query option must be "true" or "false".');
            }
            if ($countVal === 'true') {
                $count = count($result);
            }
        }

        if (isset($queryParams['$orderby'])) {
            $result = $this->applyOrderBy($result, $queryParams['$orderby']);
        }

        if (isset($queryParams['$skip'])) {
            $skip = $queryParams['$skip'];
            if (!preg_match('/^\d+$/', $skip)) {
                throw new \InvalidArgumentException('The $skip query option must be a non-negative integer.');
            }
            $skipValue = (int) $skip;
            if ($skipValue > self::MAX_SKIP) {
                throw new \InvalidArgumentException(sprintf(
                    'The $skip query option must not exceed %d.',
                    self::MAX_SKIP
                ));
            }
            $result = array_slice($result, $skipValue);
        }

        if (isset($queryParams['$top'])) {
            $top = $queryParams['$top'];
            if (!preg_match('/^\d+$/', $top)) {
                throw new \InvalidArgumentException('The $top query option must be a non-negative integer.');
            }
            $topValue = (int) $top;
            if ($topValue > self::MAX_TOP) {
                throw new \InvalidArgumentException(sprintf(
                    'The $top query option must not exceed %d.',
                    self::MAX_TOP
                ));
            }
            $result = array_slice($result, 0, $topValue);
        }

        if (isset($queryParams['$select'])) {
            $result = $this->applySelect($result, $queryParams['$select']);
        }

        return [
            'value' => $result,
            'count' => $count,
        ];
    }

    /**
     * @param list<array<string, mixed>> $entities
     * @return list<array<string, mixed>>
     */
    private function applyFilter(array $entities, string $filter): array
    {
        $conditions = $this->parseFilterConditions($filter);

        return array_values(array_filter(
            $entities,
            function (array $entity) use ($conditions): bool {
                return $this->matchesAllConditions($entity, $conditions);
            }
        ));
    }

    /**
     * @return list<string>
     */
    private function parseFilterConditions(string $filter): array
    {
        $pattern = '/([A-Za-z_][A-Za-z0-9_]*)\s+(eq|ne|gt|lt|ge|le)\s+((?:\'(?:\\\\\'|[^\'])*\'|"(?:\\\\"|[^"])*"|-?\d+(?:\.\d+)?|true|false))/i';

        if (!preg_match_all($pattern, $filter, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            if (trim($filter) !== '') {
                throw new \InvalidArgumentException('Invalid $filter query syntax.');
            }

            return [];
        }

        $conditions = [];
        $lastOffset = 0;
        foreach ($matches as $index => $m) {
            $matchText = $m[0][0];
            $matchOffset = $m[0][1];

            if ($index > 0) {
                $gap = substr($filter, $lastOffset, $matchOffset - $lastOffset);
                if (!preg_match('/^\s+and\s+$/i', $gap)) {
                    throw new \InvalidArgumentException('Invalid $filter query syntax. Multiple conditions must be combined with "and".');
                }
            } else {
                $leading = substr($filter, 0, $matchOffset);
                if (trim($leading) !== '') {
                    throw new \InvalidArgumentException('Invalid $filter query syntax.');
                }
            }

            $conditions[] = $matchText;
            $lastOffset = $matchOffset + strlen($matchText);
        }

        $trailing = substr($filter, $lastOffset);
        if (trim($trailing) !== '') {
            throw new \InvalidArgumentException('Invalid $filter query syntax.');
        }

        return $conditions;
    }

    /**
     * @param array<string, mixed> $entity
     * @param list<string> $conditions
     */
    private function matchesAllConditions(array $entity, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if (!preg_match(
                "/^([A-Za-z_][A-Za-z0-9_]*)\s+(eq|ne|gt|lt|ge|le)\s+('(?:\\\\'|[^'])*'|\"(?:\\\\\"|[^\"])*\"|-?\d+(?:\.\d+)?|true|false)$/i",
                trim($condition),
                $matches
            )) {
                return false;
            }

            [, $property, $operator, $rawValue] = $matches;
            $operator = strtolower($operator);
            $value = $this->parseFilterValue($rawValue);

            if (!$this->compare($entity[$property] ?? null, $operator, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return mixed
     */
    private function parseFilterValue(string $rawValue)
    {
        $lower = strtolower($rawValue);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }

        if (
            (Str::startsWith($rawValue, "'") && Str::endsWith($rawValue, "'"))
            || (Str::startsWith($rawValue, '"') && Str::endsWith($rawValue, '"'))
        ) {
            return str_replace(["\\'", '\\"'], ["'", '"'], substr($rawValue, 1, -1));
        }

        return is_numeric($rawValue) ? (Str::contains($rawValue, '.') ? (float) $rawValue : (int) $rawValue) : $rawValue;
    }

    /**
     * @param mixed $left
     * @param mixed $right
     */
    private function compare($left, string $operator, $right): bool
    {
        if (is_numeric($left) && is_numeric($right)) {
            $left = (float) $left;
            $right = (float) $right;
        }

        switch ($operator) {
            case 'eq':
                return $left == $right;
            case 'ne':
                return $left != $right;
            case 'gt':
                return $left > $right;
            case 'lt':
                return $left < $right;
            case 'ge':
                return $left >= $right;
            case 'le':
                return $left <= $right;
            default:
                return false;
        }
    }

    /**
     * @param list<array<string, mixed>> $entities
     * @return list<array<string, mixed>>
     */
    private function applyOrderBy(array $entities, string $orderBy): array
    {
        if (trim($orderBy) === '') {
            return $entities;
        }

        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(?:\s+(asc|desc))?$/i', trim($orderBy), $matches)) {
            throw new \InvalidArgumentException('Invalid $orderby query syntax.');
        }

        $property = $matches[1];
        $direction = $matches[2] ?? 'asc';
        $descending = strtolower($direction) === 'desc';

        usort(
            $entities,
            function (array $left, array $right) use ($property, $descending): int {
                $comparison = $this->compareSortValues($left[$property] ?? null, $right[$property] ?? null);

                return $descending ? -$comparison : $comparison;
            }
        );

        return $entities;
    }

    /**
     * @param mixed $left
     * @param mixed $right
     */
    private function compareSortValues($left, $right): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            return $left <=> $right;
        }

        return strcmp(Str::toString($left), Str::toString($right));
    }

    /**
     * @param list<array<string, mixed>> $entities
     * @return list<array<string, mixed>>
     */
    private function applySelect(array $entities, string $select): array
    {
        $fields = array_map('trim', explode(',', $select));

        foreach ($fields as $field) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field)) {
                throw new \InvalidArgumentException('Invalid $select query syntax.');
            }
        }

        return array_map(
            function (array $entity) use ($fields): array {
                $selected = [];

                foreach ($fields as $field) {
                    if (array_key_exists($field, $entity)) {
                        $selected[$field] = $entity[$field];
                    }
                }

                return $selected;
            },
            $entities
        );
    }
}