<?php

declare(strict_types=1);

namespace TTBooking\Derevo\Relations;

class HasDescendants extends HasAncestorsOrDescendants
{
    /**
     * @return string[]
     */
    protected function getBoundComparisonOperators()
    {
        return $this->andSelf ? ['>=', '<='] : ['>', '<'];
    }
}
