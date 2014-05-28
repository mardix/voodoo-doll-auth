<?php

/**
 * Session
 * 
 * @name Session
 * @author Mardix
 * @since   Feb 13, 2014
 */
namespace Voodoo\Doll\Auth;

use Voodoo;

class Session 
{
    private $driver;
    
    public function __construct() {
        $driver = Voodoo\Core\Config::Doll()->get("Auth.sessionDriver");
        $this->driver = new $driver();
    }

    public function __call($name, $arguments) {
        return call_user_func_array([$this->driver, $name], $arguments);
    }
}
