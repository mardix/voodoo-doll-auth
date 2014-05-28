<?php
/**
 * Redis
 * 
 * @name Redis
 * @author Mardix
 * @since   Feb 14, 2014
 * 
 * Save the session in a NoSQL
 * 
 */

namespace Voodoo\Doll\Auth\SessionDriver;

use Voodoo,
    Voodoo\Doll\Auth,
    Voodoo\Doll\Model;

class Redis
{
    use TDriver;
    
    const PREFIX = "USER_SESSION";
    const SESSION_PREFIX = "USER_SESSION:";
    const LIVE_SESSION = "USER_SESSION_LIVE:";
    const MAP_KEY = "USER_SESSION_MAP";
    
    protected static $session = null;
    private $redis = null;
    private $config = null;
    private $fields = ["id", "auth_account_id", "ip", "shadow_session", "data"];
    private $_data = [];
    private $accountModel;
    
    public function __construct()
    {
        $this->config = Voodoo\Core\Config::Doll()->get("Auth");
        $this->redis = Model\Redis::connect($this->config["redisDbAlias"]); 
        if (isset($this->config["accountModel"]) && $this->config["accountModel"]) {
            $this->accountModel = new $this->config["accountModel"]();
        } else {
            $this->accountModel = new Auth\Account();
        }        
    }
    
    /**
     * Create a new Session
     * 
     * @param int $userId
     * @param int $ttl - Manually set the TTL
     * @param bool $shadow_session - Creates another session with the same auth_account_id, but can't be removed by the non shadow  
     * @return bool
     */
    public function createNew($userId, $ttl = null, $shadow_session = false)
    {
        $sessionId = $this->createSessionId();
        $key = self::SESSION_PREFIX.$sessionId;
        $expireTime = time() + ($ttl ?: $this->config["sessionTTL"]);

        if (! $shadow_session) {
            $oldSession = $this->redis->hget(self::MAP_KEY, $userId);
            if ($oldSession) {
                $this->redis->del($oldSession);
            }
            $this->redis->hset(self::MAP_KEY, $userId, $key);
        }
        
        $data = [
            "id" => $key,
            "auth_account_id" => $userId,
            "ip" => Voodoo\Core\Http\Request::getIp(),
            "shadow_session" => $shadow_session ? 1 : 0,
            "data" => ""
        ];
        
        $this->redis->pipeline();
            foreach ($data as $k => $v) {
                $this->redis->hset($key, $k, $v);
            }
            $this->redis->expire($key, $expireTime);
        $this->redis->uncork();
        
        $this->setCookie($sessionId, $expireTime);
        $this->_data = $data;
        return $this;
    }
    
    /**
     * Return an active session
     * 
     * @return Session
     */
    public function getSession()
    {
        if (! self::$session) {
            $key = self::SESSION_PREFIX.$this->getCookie();
            if ($this->redis->exists($key)) {
                foreach($this->fields as $field) {
                    $data[$field] = $this->redis->hget($key, $field);
                }
                
                // Live Session
                $ttl = time() + $this->config["liveSessionTTL"];
                $liveSessionKey = self::LIVE_SESSION . $data["auth_account_id"];
                if (! $this->redis->exists($liveSessionKey)) {
                    $this->redis->set($liveSessionKey, 1);
                    $this->redis->expire($liveSessionKey, $ttl);
                }
                
                $this->_data = $data;
                self::$session = $this;
            }
        }
        return self::$session;
    }
    
    /**
     * Count the live session
     * @param bool $all - true = Count all sessions
     * @return int
     */
    public function getCount($all = false)
    {
        if ($all) {
            $prefix = self::SESSION_PREFIX;
        } else {
            $prefix = self::LIVE_SESSION;
        }
        $res = $this->redis->keys($prefix."*");
        return ($res) ? (count($res)) : 0;     
    }
    
    
    /**
     * Destroy a session
     * 
     * @return bool
     */
    public function destroy()
    {
        $session = $this->getSession();
        if ($session) {
            $this->redis->pipeline();
                $this->redis->del($session->id);  
                $this->redis->hdel(self::MAP_KEY, $session->auth_account_id);
                $this->redis->del(self::LIVE_SESSION . $session->auth_account_id);                
            $this->redis->uncork();
            $this->setCookie("", 0);
            return true;            
        }
        return false;
    }    

    /**
     * return the current session entry
     * 
     * @return self
     */
    public function getAccount()
    {
        $session = $this->getSession();
        if ($session) {
            return $this->accountModel->findOne($session->auth_account_id);
        }
        return false;
    }    

    /**
     * Drop all session
     * 
     * @param bool $all - destroy all keys
     * @return boolean
     */
    public function destroyAll($all=false)
    {
        if ($all) {
            $allKeys = $this->redis->keys(self::PREFIX."*");
            if($allKeys && count($allKeys)) {
                foreach ($allKeys as $key) {
                    $this->redis->del($key);
                }
            }
        }
        return true;
    }
    
    
    /**
     * To reset the cookie TTL
     * 
     * @return type
     */
    public function resetTTL($ttl=null)
    {
        $session = $this->getSession();
        if ($session) {
            $expireTime = time() + ($ttl ?: $this->config["sessionTTL"]);
            $this->redis->expire($session->id, $expireTime);
        }        
    }
    
    /**
     * Save data in local storage
     * @param string $data
     */
    protected function saveData($data)
    {
        $session = $this->getSession();
        if ($session) {
            $this->redis->hset($session->id, "data", $data);
        }        
    }
    
    /**
     * Retrieve data from local storage
     * @return string
     */
    protected function retrieveData()
    {
        $session = $this->getSession();
        if ($session) {
          return $this->redis->hget($session->id, "data");
        }  
        return null;
    }

    /**
     * Create the sessoion Id
     * @return string
     */
    protected function createSessionId()
    {
        return Voodoo\Core\Helpers::generateRandomString(6);
    }
    
    public function __get($name)
    {
        return isset($this->_data[$name]) ? $this->_data[$name] : null;
    }
}
