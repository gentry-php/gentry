<?php

namespace Gentry\Gentry\Cache;

use Psr\Cache;

class InvalidArgumentException extends \InvalidArgumentException
implements Cache\InvalidArgumentException
{
}

