<?php

declare(strict_types=1);

namespace TTBooking\Derevo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use TTBooking\Derevo\Concerns\HasRelationshipsWithinTree;
use TTBooking\Derevo\Relations\HasManyDescendants;
use TTBooking\Derevo\Support\IntegerAllocator;

/**
 * @method static Builder roots()
 * @property static $parent
 * @property Collection|static[] $children
 * @property Collection|static[] $siblings
 * @property Collection|static[] $descendants
 * @property Collection|static[] $descendantsAndSelf
 */
abstract class Node extends Model
{
    use HasRelationshipsWithinTree;

    //protected const MOVE_ROOT = null;

    protected const MOVE_CHILD = 0;

    protected const MOVE_LEFT = -1;

    protected const MOVE_RIGHT = 1;

    protected string $parentColumn = 'parent_id';

    protected string $leftColumn = 'lft';

    protected string $rightColumn = 'rgt';

    protected string $depthColumn = 'depth';

    protected $guarded = ['id', 'parent_id', 'lft', 'rgt', 'depth'];

    public function getParentColumnName(): string
    {
        return $this->parentColumn;
    }

    public function getQualifiedParentColumnName(): string
    {
        return $this->qualifyColumn($this->getParentColumnName());
    }

    public function getLeftColumnName(): string
    {
        return $this->leftColumn;
    }

    public function getQualifiedLeftColumnName(): string
    {
        return $this->qualifyColumn($this->getLeftColumnName());
    }

    public function getRightColumnName(): string
    {
        return $this->rightColumn;
    }

    public function getQualifiedRightColumnName(): string
    {
        return $this->qualifyColumn($this->getRightColumnName());
    }

    public function getDepthColumnName(): string
    {
        return $this->depthColumn;
    }

    public function getQualifiedDepthColumnName(): string
    {
        return $this->qualifyColumn($this->getDepthColumnName());
    }

    public function getParentKey()
    {
        return $this->getAttribute($this->getParentColumnName());
    }

    public function getLeft(): int
    {
        return $this->getAttribute($this->getLeftColumnName());
    }

    public function getRight(): int
    {
        return $this->getAttribute($this->getRightColumnName());
    }

    public function getDepth(): int
    {
        return $this->getAttribute($this->getDepthColumnName());
    }

    public static function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull((new static)->getParentColumnName());
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, $this->getParentColumnName());
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, $this->getParentColumnName());
    }

    public function siblings(): HasMany
    {
        return $this->hasMany(static::class, $this->getParentColumnName(), $this->getParentColumnName());
    }

    public function descendants(): HasManyDescendants
    {
        return $this->hasManyDescendants();
    }

    public function descendantsAndSelf(): HasManyDescendants
    {
        return $this->descendants()->andSelf();
    }

    public function isRoot(): bool
    {
        return is_null($this->getParentKey());
    }

    /**
     * @param  static  $other
     * @return bool
     */
    public function isDescendantOf(Node $other): bool
    {
        return
            $this->getLeft() > $other->getLeft() &&
            $this->getLeft() < $other->getRight();
    }

    /**
     * @param  static  $other
     * @return bool
     */
    public function isAncestorOf(Node $other): bool
    {
        return
            $this->getLeft() < $other->getLeft() &&
            $this->getRight() > $other->getLeft();
    }

    /**
     * @return static|null
     */
    public static function getFirstRoot(): ?self
    {
        return static::roots()
            ->orderBy((new static)->getLeftColumnName())
            ->first();
    }

    /**
     * @return static|null
     */
    public static function getLastRoot(): ?self
    {
        return static::roots()
            ->orderByDesc((new static)->getLeftColumnName())
            ->first();
    }

    /**
     * @return static|null
     */
    public function getLeftSibling(): ?self
    {
        return $this->siblings()
            ->where($this->getLeftColumnName(), '<', $this->getLeft())
            ->orderByDesc($this->getLeftColumnName())
            ->first();
    }

    /**
     * @return static|null
     */
    public function getRightSibling(): ?self
    {
        return $this->siblings()
            ->where($this->getLeftColumnName(), '>', $this->getRight())
            ->orderBy($this->getLeftColumnName())
            ->first();
    }

    /**
     * @return static|null
     */
    public function getFirstChild(): ?self
    {
        return $this->children()
            ->orderBy($this->getLeftColumnName())
            ->first();
    }

    /**
     * @return static|null
     */
    public function getLastChild(): ?self
    {
        return $this->children()
            ->orderByDesc($this->getLeftColumnName())
            ->first();
    }

    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }

    /**
     * Begin querying the node.
     *
     * @return \Illuminate\Database\Eloquent\Builder|Builder
     */
    public static function query(): Builder
    {
        return parent::query();
    }

    /**
     * Get a new query builder for the node's table.
     *
     * @return \Illuminate\Database\Eloquent\Builder|Builder
     */
    public function newQuery(): Builder
    {
        return parent::newQuery();
    }

    /**
     * Create a new Eloquent query builder for the node.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return Builder|static
     */
    public function newEloquentBuilder($query): Builder
    {
        return new Builder($query);
    }

    protected static function booted()
    {
        //static::creating(fn (Node $node) => $node->initBounds());

        static::saving(function (Node $node) {
            if ($node->isDirty($node->getParentColumnName())) {
                $node->moveTo($node->parent);
            }
        });
    }

    /**
     * @param  static|null  $target
     * @param  int  $position
     * @return $this
     */
    protected function moveTo(self $target = null, int $position = self::MOVE_CHILD): self
    {
        // move to the root
        if (is_null($target)) {
            $target = static::getLastRoot();
            $position = self::MOVE_RIGHT;
        }

        // make node the first ond only root
        if (is_null($target)) {
            $left = 0;
            $right = PHP_INT_MAX;
            $depth = 0;
        }

        // move to the left
        elseif ($position === self::MOVE_LEFT) {
            if (! is_null($leftTargetSibling = $target->getLeftSibling())) {
                $left = $leftTargetSibling->getRight();
            } else {
                $left = $target->isRoot() ? 0 : $target->parent->getLeft();
            }

            $right = $target->getLeft();
            $depth = $target->getDepth();
        }

        // move into
        elseif ($position === self::MOVE_CHILD) {
            $lastTargetChild = $target->getLastChild();
            $left = is_null($lastTargetChild) ? $target->getLeft() : $lastTargetChild->getRight();
            $right = $target->getRight();
            $depth = $target->getDepth() + 1;
        }

        // move to the right
        elseif ($position === self::MOVE_RIGHT) {
            if (! is_null($rightTargetSibling = $target->getRightSibling())) {
                $right = $rightTargetSibling->getLeft();
            } else {
                $right = $target->isRoot() ? PHP_INT_MAX : $target->parent->getRight();
            }

            $left = $target->getRight();
            $depth = $target->getDepth();
        }

        $space = IntegerAllocator::within($left, $right)->allocateTo(1, 1, 1)[1];

        // TODO: lock rows between left and right boundaries
        return $this
            ->setLeft($space->getLeftBoundary())
            ->setRight($space->getRightBoundary())
            ->setDepth($depth);
    }

    protected function initBounds(self $parent = null): self
    {
        $lastRight = $this->newQuery()
            ->where($this->getQualifiedParentColumnName(), isset($parent) ? $parent->getKey() : null)
            ->orderByDesc($rightColumn = $this->getRightColumnName())
            ->take(1)->sharedLock()->value($rightColumn) ?? -1;

        $maxRight = isset($parent) ? $parent->getRight() - 1 : PHP_INT_MAX;

        $parentDepth = isset($parent) ? $parent->getDepth() : -1;

        return $this
            ->setLeft($lastRight + 1)
            ->setRight((int) $maxRight / 2)
            ->setDepth($parentDepth + 1);
    }

    protected function setLeft(int $left): self
    {
        return $this->setAttribute($this->getLeftColumnName(), $left);
    }

    protected function setRight(int $right): self
    {
        return $this->setAttribute($this->getRightColumnName(), $right);
    }

    protected function setDepth(int $depth): self
    {
        return $this->setAttribute($this->getDepthColumnName(), $depth);
    }
}