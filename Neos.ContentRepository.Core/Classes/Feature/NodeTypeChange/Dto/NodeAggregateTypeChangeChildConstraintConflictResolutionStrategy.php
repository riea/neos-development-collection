<?php

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\ContentRepository\Core\Feature\NodeTypeChange\Dto;

use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;

/**
 * The strategy how to handle node type constraint conflicts with already present child nodes
 * when changing a node aggregate's type.
 *
 * - delete will delete all newly disallowed child nodes
 *
 * Serialization format:
 * - All strategies except `markWithTag` serialize to a fixed string, e.g. `"delete"`.
 * - The `markWithTag` strategy serializes to "mark_with_tag:[tagname]
 *
 * @api DTO of {@see ChangeNodeAggregateType} command
 */
final class NodeAggregateTypeChangeChildConstraintConflictResolutionStrategy implements \JsonSerializable
{
    private const STRATEGY_DELETE = 'delete';
    private const STRATEGY_MARK_WITH_TAG = 'mark_with_tag';
    private const STRATEGY_HAPPY_PATH = 'happypath';
    private const STRATEGY_PROMISED_CASCADE = 'promisedCascade';

    private const VALID_VALUES = [
        self::STRATEGY_DELETE,
        self::STRATEGY_MARK_WITH_TAG,
        self::STRATEGY_HAPPY_PATH,
        self::STRATEGY_PROMISED_CASCADE,
    ];

    /** @var array<string, self> */
    private static array $instances = [];

    private function __construct(
        private readonly string $value,
        public readonly SubtreeTag|null $subtreeTag
    ) {
    }

    private static function instance(string $value): self
    {
        return self::$instances[$value] ??= new self($value, null);
    }

    /**
     * This strategy means "we remove all children / grandchildren nodes which do not match the constraint"
     */
    public static function delete(): self
    {
        return self::instance(self::STRATEGY_DELETE);
    }

    /**
     * This strategy means "we mark children with a special SubtreeTag"
     */
    public static function markWithTag(SubtreeTag $tag): self
    {
        return new self(self::STRATEGY_MARK_WITH_TAG, $tag);
    }

    /**
     * This strategy means "we only change the NodeAggregateType if all constraints of parents
     * AND children and grandchildren are still respected."
     */
    public static function happyPath(): self
    {
        return self::instance(self::STRATEGY_HAPPY_PATH);
    }

    /**
     * This strategy extends happypath by expecting that identically typed children will also be changed, affecting validation.
     * Required e.g. for global type change transformations
     */
    public static function promisedCascade(): self
    {
        return self::instance(self::STRATEGY_PROMISED_CASCADE);
    }

    /**
     * Reconstruct from a serialized string.
     *
     * Accepts:
     *   - `"delete"`, `"happypath"`, `"promisedCascade"` — plain strategy strings
     *   - `"mark_with_tag"` — legacy form, tag will be null
     *   - `"mark_with_tag:[tagName]"` — full form with SubtreeTag
     *
     * @throws \InvalidArgumentException for unknown or malformed values
     */
    public static function fromString(string $value): self
    {
        if (str_starts_with($value, self::STRATEGY_MARK_WITH_TAG . ':')) {
            $tagName = substr($value, strlen(self::STRATEGY_MARK_WITH_TAG) + 1);
            if ($tagName === '') {
                throw new \InvalidArgumentException(
                    'Invalid strategy value: tag name must not be empty in "mark_with_tag:[tagName]".'
                );
            }
            return self::markWithTag(SubtreeTag::fromString($tagName));
        }

        if (!in_array($value, self::VALID_VALUES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid strategy value "%s". Valid values are: %s, or "mark_with_tag:[tagName]".',
                    $value,
                    implode(', ', self::VALID_VALUES)
                )
            );
        }

        return self::instance($value);
    }

    /**
     * Serialize to a plain string for JSON encoding.
     *
     * - All strategies except `markWithTag` serialize to their string value (e.g. `"delete"`),
     *   preserving full backwards-compatibility.
     * - `markWithTag` with a tag serializes to `"mark_with_tag:[tagName]"`.
     * - `markWithTag` without a tag (legacy) serializes to the bare `"mark_with_tag"` string.
     *
     * Deserialization counterpart: {@see fromString()}.
     */
    public function jsonSerialize(): string
    {
        if ($this->value === self::STRATEGY_MARK_WITH_TAG && $this->subtreeTag !== null) {
            return self::STRATEGY_MARK_WITH_TAG . ':' . $this->subtreeTag->value;
        }

        return $this->value;
    }

    public function equals(NodeAggregateTypeChangeChildConstraintConflictResolutionStrategy $other): bool
    {
        return $this->jsonSerialize() === $other->jsonSerialize();
    }
}
