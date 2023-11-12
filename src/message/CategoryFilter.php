<?php

namespace Pkg6\Log\message;

use Psr\Log\InvalidArgumentException;

class CategoryFilter
{
    protected $include = [];
    protected $exclude = [];

    public function include(array $categories)
    {
        $this->checkStructure($categories);
        $this->include = $categories;
    }

    public function exclude(array $categories)
    {
        $this->checkStructure($categories);
        $this->exclude = $categories;
    }

    public function isExcluded($category)
    {
        foreach ($this->exclude as $exclude) {
            $prefix = rtrim($exclude, '*');

            if ($category === $exclude || ($prefix !== $exclude && strpos($category, $prefix) === 0)) {
                return true;
            }
        }

        if (empty($this->include)) {
            return false;
        }

        foreach ($this->include as $include) {
            if (
                $category === $include
                || (
                    !empty($include)
                    && substr_compare($include, '*', -1, 1) === 0
                    && strpos($category, rtrim($include, '*')) === 0
                )
            ) {
                return false;
            }
        }

        return true;
    }

    protected function checkStructure(array $categories)
    {
        foreach ($categories as $category) {
            if (!is_string($category)) {
                throw new InvalidArgumentException(sprintf(
                    'The log message category must be a string, %s received.',
                    gettype($category)
                ));
            }
        }
    }

}