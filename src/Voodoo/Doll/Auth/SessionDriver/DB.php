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
                        "expired_at > ? " => $this->NOW()
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
                    ->where(["live_session_expired_at > ?" => $this->NOW()])
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
                    ->whereLt("expired_at", $this->NOW())
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

    
/*******************************************************************************/
    protected function setupTable()
    {
        $sql = "
            CREATE TABLE `{$this->getTableName()}` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `auth_account_id` INT(10) NULL,
                `session_id` CHAR(45) NOT NULL,
                `ip` VARCHAR(250) NULL,
                `data` MEDIUMTEXT NULL,
                `shadow_session` TINYINT(1) NOT NULL DEFAULT '0',
                `expired_at` DATETIME NULL,
                `live_session_expired_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `session_id` (`session_id`),
                INDEX `auth_account_id` (`auth_account_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        "; 
        $this->createTable($sql);
    }    
}
