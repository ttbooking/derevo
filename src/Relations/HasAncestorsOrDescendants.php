<?php

declare(strict_types=1);

namespace TTBooking\Derevo\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

abstract class HasAncestorsOrDescendants extends HasMany
{
    /**
     * Force relation to include parent model in the result set.
     *
     * @var bool
     */
    protected $andSelf = false;

    /**
     * The left column of the related model.
     *
     * @var string
     */
    protected $foreignLeftColumn;

    /**
     * The right column of the related model.
     *
     * @var string
     */
    protected $foreignRightColumn;

    /**
     * The left column of the parent model.
     *
     * @var string
     */
    protected $leftColumn;

    /**
     * The right column of the parent model.
     *
     * @var string
     */
    protected $rightColumn;

    /**
     * Create a new has descendants relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @param  string  $leftColumn
     * @param  string  $rightColumn
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $foreignKey, $localKey, $foreignLeftColumn, $foreignRightColumn, $leftColumn, $rightColumn)
    {
        $this->foreignLeftColumn = $foreignLeftColumn;
        $this->foreignRightColumn = $foreignRightColumn;
        $this->leftColumn = $leftColumn;
        $this->rightColumn = $rightColumn;

        parent::__construct($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Force relation to include parent model in the result set.
     *
     * @param  bool  $andSelf
     * @return $this
     */
    public function andSelf($andSelf = true)
    {
        $this->andSelf = $andSelf;

        return $this;
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $op = $this->getBoundComparisonOperators();

            $this->query
                ->where($this->leftColumn, $op[0], $this->getParentLeft())
                ->where($this->rightColumn, $op[1], $this->getParentRight());
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $bounds = $this->getBounds($models, $this->leftColumn, $this->rightColumn);

        $op = $this->getBoundComparisonOperators();

        foreach ($bounds as [$left, $right]) {
            $this->query
                ->where($this->leftColumn, $op[0], $left)
                ->where($this->rightColumn, $op[1], $right);
        }
    }

    /**
     * Add the constraints for an internal relationship existence query.
     *
     * Essentially, these queries compare on column names like whereColumn.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  array|mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        if ($query->getQuery()->from == $parentQuery->getQuery()->from) {
            return $this->getRelationExistenceQueryForSelfRelation($query, $parentQuery, $columns);
        }

        $op = $this->getBoundComparisonOperators();

        return $query->select($columns)
            ->whereColumn(
                $this->getQualifiedForeignLeftColumnName(), $op[0], $this->getQualifiedParentLeftColumnName()
            )
            ->whereColumn(
                $this->getQualifiedForeignRightColumnName(), $op[1], $this->getQualifiedParentRightColumnName()
            );
    }

    /**
     * Add the constraints for a relationship query on the same table.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  array|mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQueryForSelfRelation(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $query->from($query->getModel()->getTable().' as '.$hash = $this->getRelationCountHash());

        $query->getModel()->setTable($hash);

        $op = $this->getBoundComparisonOperators();

        return $query->select($columns)
            ->whereColumn(
                $hash.'.'.$this->leftColumn, $op[0], $this->getQualifiedParentLeftColumnName()
            )
            ->whereColumn(
                $hash.'.'.$this->rightColumn, $op[1], $this->getQualifiedParentRightColumnName()
            );
    }

    /**
     * @return mixed
     */
    public function getParentLeft()
    {
        return $this->parent->getAttribute($this->leftColumn);
    }

    /**
     * @return string
     */
    public function getQualifiedParentLeftColumnName()
    {
        return $this->parent->qualifyColumn($this->leftColumn);
    }

    /**
     * @return mixed
     */
    public function getParentRight()
    {
        return $this->parent->getAttribute($this->rightColumn);
    }

    /**
     * @return string
     */
    public function getQualifiedParentRightColumnName()
    {
        return $this->parent->qualifyColumn($this->rightColumn);
    }

    /**
     * @return string
     */
    public function getQualifiedForeignLeftColumnName()
    {
        return $this->foreignLeftColumn;
    }

    /**
     * @return string
     */
    public function getQualifiedForeignRightColumnName()
    {
        return $this->foreignRightColumn;
    }

    /**
     * @return string[]
     */
    abstract protected function getBoundComparisonOperators();

    /**
     * @param  array  $models
     * @param  string|null  $leftColumn
     * @param  string|null  $rightColumn
     * @return array
     */
    protected function getBounds(array $models, $leftColumn = null, $rightColumn = null)
    {
        return collect($models)->map(fn ($value) => [
            $leftColumn ? $value->getAttribute($leftColumn) : $value->getLeft(),
            $rightColumn ? $value->getAttribute($rightColumn) : $value->getRight(),
        ])->values()->sortBy(0)->reduce(fn (array $bounds, array $pair) =>
            false === ($prevPair = last($bounds)) || $pair[0] > $prevPair[1] ? array_merge($bounds, [$pair]) : $bounds,
        [])->all();
    }
}
