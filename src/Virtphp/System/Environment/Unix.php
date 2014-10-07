<?php

namespace Virtphp\System\Environment;

use Virtphp\System\EnvironmentInterface;

class Unix implements EnvironmentInterface
{

    /**
     * Get the HOME environment value
     *
     * @return string
     */
    public function home()
    {
        return getenv('HOME');
    }
}
