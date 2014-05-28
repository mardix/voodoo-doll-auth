<?php

/**
 * TDriver
 * 
 * @name TDriver
 * @author Mardix
 * @since   Feb 13, 2014
 */

namespace Voodoo\Doll\Auth\SessionDriver;

use Voodoo;

trait TDriver
{
    abstract public function createNew($userId, $ttl = null, $shadow_session = false );
    
    abstract public function getAccount();
    
    abstract public function destroy();
    
    abstract public function destroyAll($all);

    abstract public function getSession();
    
    abstract protected function saveData($data);
    
    abstract protected function retrieveData();
    
    abstract public function resetTTL($ttl=null);
    
    abstract public function getCount($all=false);
    
    /**
     * Set session data to local storage
     * 
     * @param string $key
     * @param mixed $value
     */
    public function setData($key, $value)
    {
        $data = $this->getData();
        $newD = [$key => $value];
        $data = Voodoo\Core\Helpers::arrayExtend($data, $newD);
        $this->saveData(json_encode($data));
    }
    
    /**
     * Get the data
     * 
     * @param string $key
     * @return Array
     */
    public function getData($key = null)
    {
        $data = json_decode($this->retrieveData(), true) ?: [];
        if ($key && isset($data[$key])) {
            return $data[$key];
        } else {
            return $data;
        }
    }
   
    /**
     * Return the cookie session name
     * 
     * @return string
     */
    protected function getSessionName()
    {
        $name = Voodoo\Core\Config::Doll()->get("Auth.sessionName");
        return $name;
    }
    
    /**
     * Return the cookie 
     * 
     * @return string
     */
    protected function getCookie() {
        return $_COOKIE[$this->getSessionName()];
    }
    
    /**
     * Set the cookie
     * @param type $sessionId
     * @param type $expire
     */
    protected function setCookie($sessionId = "", $expire = 0)
    {
        setcookie($this->getSessionName(), $sessionId, $expire, "/");        
    }
    
    /**
     * Create a session Id
     * @return string
     */
    protected function createSessionId()
    {
        return Voodoo\Core\Helpers::getNonce();
    }
}
