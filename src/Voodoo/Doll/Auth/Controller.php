<?php
/**
 * Auth\Controller
 * 
 * Is a trait class to use in the controller that will take care of login
 * @requires Opauth 
 */
namespace Voodoo\Doll\Auth;

use Voodoo;
use Opauth;

trait Controller
{

    /**
    abstract function successSignin();
    abstract function failedSignin();
    abstract function successSignup();
    abstract function failedSignup();
    **/
    
    protected $authAccountClass = "Account";
    private $authAccount = null;
    protected $authSessionClass = "Session";
    private $authSession = null;
    
    /**
     * Set the account class
     * @param \Voodoo\Doll\Auth\Account $account
     */
    protected function setAuthAccount(Account $account)
    {
        $this->authAccountClass = $account;
    }
    
    /**
     * Set the session class
     * @param \Voodoo\Doll\Auth\Session $session
     */
    protected function setAuthSession(Session $session)
    {
        $this->authSessionClass = $session;
    }
    
    /**
     * 
     * @return type
     */
    protected function getAuthAccount()
    {
        if (! $this->authAccount) {
            $this->authAccount = new $this->authAccountClass;
        }
        return $this->authAccount;
    }
    
    protected function getAuthSession()
    {
        if (! $this->authSession) {
            $this->authSession = new $this->authSessionClass;
        }
        return $this->authSession;
    }
    
    public function loginWithEmail($email, $password)
    {
        return $this->getAuthAccount()->loginWithEmail($email, $password);
    }
    
    /**
     * Build and return the config for Opauth
     * @return Array
     */
    private function getOpauthConfig()
    {
        $config = Voodoo\Core\Config::Doll()->get("Auth");
        $Opauth = [
            "callback" => "/callback",
            "path" => "/login/auth-signin/"
        ];
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $Opauth["Strategy"][$key] = $value;
            }
        }
        return $Opauth;
    }
    
    private function authRun($callback)
    {
        $config = $this->getOpauthConfig();
        $Opauth = new Opauth\Opauth\Opauth($config);
        return $Opauth->run();        
    }
    
    /**
     * Action to  redirect people to signin
     * @action auth-signin
     */
    public function actionAuthSignin()
    {
        try {
            $config = $this->getOpauthConfig();
            $Opauth = new Opauth\Opauth\Opauth($config);
            $Opauth->run();
        } catch (Opauth\Opauth\OpauthException $e) {
            $this->view()->setFlashMessage($e->getMessage(), "error");
        } catch (\Exception $ex) {
            $this->view()->setFlashMessage($ex->getMessage(), "error");
        }
        $this->_exit();
    }

    /**
     * Action to  redirect people to signin
     * @action auth-signin
     */
    public function actionAuthConnect()
    {
        try {
            $config = $this->getOpauthConfig();
            $Opauth = new Opauth\Opauth\Opauth($config);
            $Opauth->run();
        } catch (Opauth\Opauth\OpauthException $e) {
            $this->view()->setFlassMessage($e->getMessage(), "error");
        } catch (\Exception $ex) {
            $this->view()->setFlassMessage($ex->getMessage(), "error");
        }
        $this->_exit();
    }
}

