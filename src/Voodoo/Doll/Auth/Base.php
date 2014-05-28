<?php

/**
 * Base
 * 
 * @name Base
 * @author Mardix
 * @since   Feb 12, 2014
 */

namespace Voodoo\Doll\Auth;

use Voodoo,
    PDO;

abstract class Base extends Voodoo\Doll\Model\BaseModel
{
    public function __construct(PDO $pdo = null)
    {
        $this->dbAlias = Voodoo\Core\Config::Doll()->get("Auth.dbAlias");
        parent::__construct($pdo);
    }    
}
