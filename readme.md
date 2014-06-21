
#Voodoo\Doll\Auth

Doll

Add the code below in the file: /App/_conf/Doll.conf.php

    [Auth]
        dbAlias = "MyDB"
        accountModel = "\Model\Account" ; If the class is extended, set the name of the class so Auth\Session can reference the right class
        sessionTTL = 2592000 ; Session time to live. 30 days online
        sessionDriver = "\Voodoo\Doll\Auth\SessionDriver\DB" ; DB|Redis or full namespace
        sessionName = "c_" ; Session cookie name
        redisDbAlias = "MyRedis" ; The DB alias for Redis Session
        liveSessionTTL = 300 ; Live session TTL