<?php

namespace Rumur\WPUtils\Repository\Exception;

use Rumur\WPUtils\Contracts\Exception\WPUtilsException;

class PostNotFound extends \Exception implements WPUtilsException
{
    public static function byId(int $id): PostNotFound
    {
        return new self(sprintf('The user was not found with id %d', $id));
    }
}