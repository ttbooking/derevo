<?php

declare(strict_types=1);

namespace TTBooking\Derevo\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use TTBooking\Derevo\Relations\HasDescendants;
use TTBooking\Derevo\Relations\HasSiblings;

trait HasRelationshipsWithinTree
{
    /**
     * Define a node-descendants relationship within a tree.
     *
     * @param  string  $related
     * @param  string|null  $leftColumn
     * @param  string|null  $rightColumn
     * @return HasDescendants
     */
    public function hasDescendants($related = null, $leftColumn = null, $rightColumn = null): HasDescendants
    {
        $instance = $this->newRelatedInstance($related ?? static::class);

        $foreignKey = $instance->getTable().'.'.$this->getForeignKey();

        $localKey = $this->getKeyName();

        $leftColumn = $leftColumn ?: $this->getLeftColumnName();

        $rightColumn = $rightColumn ?: $this->getRightColumnName();

        $foreignLeftColumn = $instance->getTable().'.'.$leftColumn;

        $foreignRightColumn = $instance->getTable().'.'.$rightColumn;

        return $this->newHasDescendants(
            $instance->newQuery(), $this, $foreignKey, $localKey,
            $foreignLeftColumn, $foreignRightColumn, $leftColumn, $rightColumn
        );
    }

    /**
     * Define a node-siblings relationship within a tree.
     *
     * @param  string  $related
     * @param  string|null  $ownerKey
     * @return HasSiblings
     */
    public function hasSiblings($related = null, $ownerKey = null): HasSiblings
    {
        $instance = $this->newRelatedInstance($related ?? static::class);

        $ownerKey = $ownerKey ?: $this->getParentColumnName();

        return $this->newHasSiblings($instance->newQuery(), $this, $ownerKey);
    }

    /**
     * Instantiate a new HasDescendants relationship.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @param  string  $leftColumn
     * @param  string  $rightColumn
     * @return HasDescendants
     */
    protected function newHasDescendants(
        Builder $query, Model $parent, $foreignKey, $localKey,
        $foreignLeftColumn, $foreignRightColumn, $leftColumn, $rightColumn
    ): HasDescendants {
        return new HasDescendants(
            $query, $parent, $foreignKey, $localKey,
            $foreignLeftColumn, $foreignRightColumn, $leftColumn, $rightColumn
        );
    }

    /**
     * Instantiate a new HasSiblings relationship.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $ownerKey
     * @return HasSiblings
     */
    protected function newHasSiblings(Builder $query, Model $parent, $ownerKey): HasSiblings
    {
        return new HasSiblings($query, $parent, $ownerKey);
    }
}
