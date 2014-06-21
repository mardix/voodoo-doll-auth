<?php
/**
 * DB
 * 
 * @name DB
 * @author Mardix
 * @since   Feb 13, 2014
 * 
 * Save the session in a relational DB
 * 
 */

namespace Voodoo\Doll\Auth\SessionDriver;

use Voodoo,
    Voodoo\Doll\Auth;

class DB extends Auth\Base
{
    use TDriver;
    
    /**
     * Table definition
     */
    protected $__table__ = [
        
        self::TABLE_KEY_TIMESTAMPABLE => [
            "onInsert" => ["created_at", "updated_at"],
            "onUpdate" => ["updated_at"]
        ],
        
        self::TABLE_KEY_SCHEMA => [
            "id" => [ "type" => "id" ],
            "auth_account_id" => [ "type" => "number", "index" => true ],
            "session_id" => [ "type" => "string", "length" => 50, "index" => true ],
            "ip" => [ "type" => "string" ],
            "data" => [ "type" => "mediumtext" ],
            "shadow_session" => [ "type" => "bool", "default" => false ],
            "expired_at" => [ "type" => "dt" ],
            "live_session_expired_at" => [ "type" => "dt" ],
            "created_at" => [ "type" => "dt"],
            "updated_at" => [ "type" => "ts"]          
        ]
    ]; 

    protected $tableName = "auth_session";
    
    protected static $session = null;

    protected $config = null;
    
    private $accountModel;
    
   
    protected function setup()
    {
        parent::setup();
        $this->config = Voodoo\Core\Config::Doll()->get("Auth");
        if (isset($this->config["accountModel"]) && $this->config["accountModel"]) {
            $this->accountModel = new $this->config["accountModel"]();
        } else {
            $this->accountModel = new Auth\Account();
        }
    }
    
    /**
     * Create a new Session
     * 
     * @param int $authId
     * @param int $ttl - Manually set the TTL
     * @param bool $shadow_session - Creates another session with the same auth_account_id, but can't be removed by the non shadow  
     * @return bool
     */
    public function createNew($authId, $ttl = null, $shadow_session = false)
    {
        if (! $shadow_session) {
            $this->reset()->where(["auth_account_id" => $authId, "shadow_session" => 0])->delete();
        }
        
        $sessionId = $this->createSessionId();
        $expireTime = time() + ($ttl ?: $this->config["sessionTTL"]);
        $ins = $this->insert([
            "auth_account_id" => $authId,
            "session_id" => $sessionId,
            "ip" => Voodoo\Core\Http\Request::getIp(),
            "expired_at" => date("Y-m-d H:i:s", $expireTime),
            "shadow_session" => $shadow_session ? 1 : 0
        ]);
        $this->setCookie($sessionId, $expireTime);
        return $ins;
    }
    
     /**
     * return the current Account session entry
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
     * Return a live Session entity
     * 
     * @return Session
     */
    public function getSession()
    {
        if (! self::$session) {
            $session = $this->reset()
                    ->where([
                        "session_id" => $this->getCookie(),
                        "expired_at > ? " =>$this->getDateTime()
                     ])->findOne();
            if ($session) {
                
                $liveSessionExpiredAt = $session->live_session_expired_at;
                if (strtotime($liveSessionExpiredAt) < time()) {
                    $ttl = time() + $this->config["liveSessionTTL"];
                    #$session->update(["live_session_expired_at" => $ttl]);
                }
                self::$session = $session;
            }
        }
        return self::$session;
    }
    
    /**
     * Return the count live sessions
     * @param bool $all - When true, it will count all session
     * @return int
     */
    public function getCount($all = false)
    {
        if ($all) {
            return $this->reset()->count("id");
        } else {
            return $this->reset()
                    ->where(["live_session_expired_at > ?" =>$this->getDateTime()])
                    ->count(("id"));
        }
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
            $this->delete();      
            $this->setCookie("", 0);
            return true;            
        }
        return false;
    }    

    /**
     * Drop all session
     * 
     * @param bool $all - if true, it will destroy all, otherwise it will destroy expired one only
     * @return boolean
     */
    public function destroyAll($all=false)
    {
        if ($all) {
            $this->reset()->delete(true);
        } else {
            $this->reset()
                    ->whereLt("expired_at",$this->getDateTime())
                    ->delete();            
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
            $session->update([
                "ip" => Voodoo\Core\Http\Request::getIp(),
                "expired_at" => date("Y-m-d H:i:s", $expireTime)                
            ]);
            $this->setCookie($session->session_id, $expireTime);
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
          $session->update(["data" => $data]);
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
          return $session->data;
        }  
        return null;
    }
   
}
