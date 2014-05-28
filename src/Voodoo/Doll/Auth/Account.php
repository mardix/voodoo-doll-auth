<?php
/** Voodoo\Doll
 ******************************************************************************
 * @desc        Login adds login functionalities to your application. Just extends it to your model
 * @package     Voodoo\Doll\Auth
 * @name        Login
 * @copyright   (c) 2013
 ******************************************************************************/

namespace Voodoo\Doll\Auth;

use Voodoo\Core,
    Voodoo\Doll\Crypto;

class Account extends Base
{
    const ACCESS_LEVEL_USER = 0;
    const ACCESS_LEVEL_VIEWER = 1;
    const ACCESS_LEVEL_EDITOR = 2;
    const ACCESS_LEVEL_MODERATOR = 3;
    const ACCESS_LEVEL_ADMIN = 4;
    const ACCESS_LEVEL_SUPERADMIN = 5;
    
    const OAUTH_FACEBOOK = "facebook";
    const OAUTH_TWITTER = "twitter";
    const OAUTH_GOOGLE = "google";
    const OAUTH_GITHUB = "github";
    const OAUTH_AMAZON = "amazon";
    const OAUTH_YAHOO = "yahoo";  
    
    const STATUS_ACTIVE = "active";
    const STATUS_SUSPENDED = "suspended";
    const STATUS_DELETED = "deleted";
    const STATUS_PROBATION = "probation";
    
    /**
     * Table name
     * 
     * @var string 
     */
    protected $tableName = "auth_account";
    
    
    /**
     * Password Len
     * 
     * @var string
     */
    protected $randomPasswordLen = 8;
    
    
    /**
     * Find by email
     * 
     * @param type $email
     * @return type
     * @throws Exception
     */
    public function findByEmail($email)
    {
        if(! Core\Helpers::validEmail($email)) {
            throw new Exception("Invalid email");
        }
        return $this->reset()->where(["email" => $email])->findOne();        
    }
    
    /**
     * Find by OAuth
     * @param type $authProvider
     * @param type $uid
     * @return type
     */
    public function findByOAuth($authProvider, $uid)
    {
        $column = "{$authProvider}_uid";
        return $this->reset()->where([$column => $uid])->findOne();
    }
    
    
    /**
     * To login with email and password
     *
     * @param type $login
     * @param type $password
     * @return type
     * @throws Exception
     */
    public function loginWithEmail($email, $password)
    {
        $user = $this->findByEmail($email);
        if ($user) {
            $password = trim($password);
            if (! $user->passwordMatch($password)) {
                throw new Exception("Invalid password");
            }
            $user->updateLastLogin();
            return $user;
        } else {
            throw new Exception("Invalid Login");
        }
    }
    
    /**
     * Login with OAUTH
     * 
     * @param string $authProvider
     * @param int $uid
     * @return \Voodoo\Doll\Auth\User
     * @throws Exception
     */
    public function loginWithOAuth($authProvider, $uid)
    {
        $user = $this->findByOAuth($authProvider, $uid);
        if (! $user) {
            throw new Exception("Invalid Login");
        }
        $user->updateLastLogin();
        return $user;
    }
    
    /**
     * create new Account with email address / pw
     *
     * @param type $email
     * @param type $password
     * @throws Exception
     */
    public function createWithEmail($email, $password = null, $name = "")
    {
        if(! Core\Helpers::validEmail($email)) {
            throw new Exception("Invalid Email");
        }
        if($password && ! Core\Helpers::validPassword($password)) {
            throw new Exception("Invalid password");
        }

        $email = $this->formatEmail($email);
        $name = trim(strip_tags($name));
        if(! $this->emailExists($email)) {

            $data = [
                "name" => $name,
                "email" => $email,
                "status" => self::STATUS_ACTIVE
            ];
            if ($password) {
                $password = trim($password);
                $hash = (new Crypto\Password)->hash(trim($password));  
                $data["password_hash"] = $hash;
            }
            return $this->insert($data);
        } else {
            throw new Exception("Email exists already");
        }
    }
    
    /**
     * create new account with OAuth
     * 
     * @param string $authProvider
     * @param int $uid
     * @return \Voodoo\Doll\Auth\User
     * @throws Exception
     */
    public function createWithOAuth($authProvider, $uid)
    {
        $column = "{$authProvider}_uid";
        $user = $this->reset()->where([$column => $uid])->findOne();
        if ($user) {
            throw new Exception("Login exists already");
        }
        return $this->insert([
            $column => $uid,
            "status" => self::STATUS_ACTIVE,
            "has_auth_login" => 1
        ]);
    }
    
    /**
     * Update the last login datetime
     * 
     * @return \Voodoo\Doll\Auth\Login
     */
    public function updateLastLogin()
    {
        if ($this->isSingleRow()) {
            $this->update(["last_login" => $this->NOW()]);
        }
        return $this;
    }
    
    /**
     * To reset a password and return the newly generated one
     * @param string $email
     * @return string
     * @throws Exception
     */
    public function resetPassword() 
    {
        if ($this->isSingleRow()) {
            $password = strtolower(trim(Core\Helpers::generateRandomString($this->randomPasswordLen)));
            $this->update([
                "password_hash" => (new Crypto\Password)->hash($password)
            ]);
            $this->setRequirePasswordChange(true);
            return $password;
        } else {
            return null;
        }
    }


    /**
     * Change the password
     *
     * @param type $password
     * @return \App\Www\Adminzone\Model\Admin\User
     * @throws Exception
     */
    public function changePassword($password)
    {
        if(! Core\Helpers::validPassword($password)) {
            throw new Exception("Invalid password");
        } else {
            $password = trim($password);
            $hash = (new Crypto\Password)->hash($password);
            $this->update(["password_hash" => $hash]);
            
            if ($this->requirePasswordChange()) {
                $this->setRequirePasswordChange(false);
            }
        }
        return $this;
    }

    /**
     * Change the login email
     * 
     * @param type $email
     * @return \App\Www\Adminzone\Model\Admin\User
     * @throws Exception
     */
    public function changeEmail($email)
    {
        if(! Core\Helpers::validEmail($email)) {
            throw new Exception("Invalid email");
        } else {
            $email = $this->formatEmail($email);
            if(! $this->emailExists($email)) {
                $this->update(["email" => $email]);
            } else {
                throw new Exception("Email exists already");
            }
        }
        return $this;
    }

    /**
     * Get the email
     * @return string
     */
    public function getEmail()
    {
        return $this->email ;
    }
    
     /**
     * Set the screen name
     * @param string $name
     * @return \Voodoo\Doll\Auth\User
     */
    public function setName($name)
    {
        $this->update(["name" => $name]);
        return $this;
    }
    
    /**
     * Get the screen name
     * @return string
     */
    public function getName()
    {
        return $this->name ;
    }   
    
    /**
     * Return if account is active
     * 
     * @return bool
     */
    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }
    
    /**
     * Get the account status
     * 
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }
    
    /**
     * Return the status info 
     * 
     * @return type
     */
    public function getStatusInfo()
    {
        return $this->status_info;
    }
    
    /**
     * Set the status info 
     * 
     * @param type $status
     * @param type $description
     * @return \Voodoo\Doll\Auth\User
     */
    public function setStatus($status, $description = "")
    {
        $data = ["status" => $status];
        if ($description) {
            $data["status_description"] = $description;
        }
        $this->update($data);
        return $this;
    }

    
    /**
     * Set the auth token and secret
     * @param type $authProvider
     * @param type $token
     * @param type $secret
     * @return \Voodoo\Doll\Auth\User
     */
    public function setOAuthToken($authProvider, $token, $secret = "") {
        $this->update([
            "{$authProvider}_token" => $token,   
            "{$authProvider}_token_secret" => $secret            
        ]);
        return $this;
    }
    
    /**
     * Retun the token 
     * 
     * @param string $authProvider
     * @return Array[token, token_secret]
     */
    public function getOAuthToken($authProvider)
    {
        $column_token = $authProvider . "_token";
        $column_secret = $authProvider . "_token_secret";
        return [
            "token" => $this->$column_token,
            "token_secret" => $this->$column_secret
        ];
    }
    
    /**
     * Set the OAUTH 
     * @param type $authProvider
     * @param type $uid
     * @return \Voodoo\Doll\Auth\User
     */
    public function setOAuthUID($authProvider, $uid)
    {
        $this->update(["{$authProvider}_uid" => $uid]);
        return $this;
    }
    
    /**
     * Get the AUTH 
     * @param string $authProvider
     * @return string
     */
    public function getOAuthUID($authProvider)
    {
        $column = $authProvider . "_uid";
        return $this->$column ;
    }
    
    /**
     * Set the default OAUTH provider 
     * @param string $provider
     * @return \Voodoo\Doll\Auth\User
     */
    public function setDefaultOAuthProvider($provider)
    {
        $this->update(["default_oauth_provider" => $provider]);
        return $this;
    }
    
    /**
     * Get the default OAuth Provider
     * @return string
     */
    public function getDefaultOAuthProvider()
    {
        return $this->default_oauth_provider ;
    }
    
    /**
     * 
     * @param type $authProvider
     * @param string $creen_name
     * @return \Voodoo\Doll\Auth\User
     */
    public function setOAuthTwitterScreenName($name)
    {
        $this->update(["twitter_name" => $name]);
        return $this;
    }
    
    /**
     * Get the AUTH 
     * @return string
     */
    public function getOAuthTwitterScreenName()
    {
        $column = "twitter_name";
        return $this->$column ;
    }
    
    
    /**
     * Check if the OAUTH for a certain provider is set by checking the  
     * Or if the account has auth enabled
     * 
     * @param string $authProvider
     * @return string
     */
    public function hasOAuth($authProvider = null)
    {
        if (!$authProvider){
            return ($this->has_auth_login) ? true : false;
        } else {
            $column = $authProvider . "_uid";
            return ($this->$column) ? true : false  ;           
        }

    }
      
    /**
     * Check if email exists
     *
     * @param type $email
     * @return type
     * @throws Exception
     */
    public function emailExists($email)
    {
        $email = $this->formatEmail($email);
        if(Core\Helpers::validEmail($email)) {
            $user = (new static)->where(["email" => $email])->count("id");
            return ($user) ? true : false;
        } else {
            throw new Exception("Invalid email");
        }
    }

    /**
     * Check if a password match the current password
     * 
     * @param string $password
     * @return bool
     */
    public function passwordMatch($password)
    {
        return (new Crypto\Password)->verify(trim($password), $this->password_hash);   
    }
    
    /**
     * Set the require password on the account
     * 
     * @param bool $bool
     * @return \Voodoo\Doll\Auth\User
     */
    public function setRequirePasswordChange($bool = true)
    {
        $this->update(["require_password_change" => $bool ? 1 : 0]);
        return $this;
    }
    
    /**
     * Check if a password change is required
     * 
     * @return bool
     */
    public function requirePasswordChange()
    {
        return $this->require_password_change ? true : false;
    }
    
    /**
     * Top opt-in/out user from a newsletter
     * @param bool $bool
     * @return \Voodoo\Doll\Auth\User
     */
    public function setNewsletterOptin($bool = true)
    {
        $this->update(["newsletter_optin" => $bool ? 1 : 0]);
        return $this;
    }
    
    /**
     * Check if user is optin/out of newletter
     * @return bool
     */
    public function optinNewsletter()
    {
        return $this->newsletter_optin ? true : false;
    }
    
    /**
     * Set the profile photo
     * @param string $photo
     * @return \Voodoo\Doll\Auth\User
     */
    public function setProfilePhoto($photo)
    {
        $this->update(["profile_photo" => $photo]);
        return $this;
    }
    
    /**
     * Get profile photo
     * 
     * @return string
     */
    public function getProfilePhoto()
    {
        return $this->profile_photo;
    }
    
    /**
     * Set the access level
     * @param int $acl
     * @return \Voodoo\Doll\Auth\User
     */
    public function setAccessLevel($acl)
    {
        $this->update(["access_level" => $acl]);
        return $this;
    }
    
    /**
     * Get the access level
     * @return int
     */
    public function getAccessLevel()
    {
        return $this->access_level;
    }
    
    /**
     * Set the history
     * 
     * @param type $title
     * @param array $data
     * @return \Voodoo\Doll\Auth\User
     */
    public function setHistory($title, Array $data = [])
    {
        $history = json_decode($this->history, true);
        if (! is_array($history)) {
            $history = [];
        }
        $history[] = [
            "datetime" => date("Y-m-d H:i:s"),
            "title" => $title,
            "data" => $data
        ];
        $history = json_encode($history);
        $this->update(["history" => $history]);
        return $this;
    }
    
    /**
     * Prepare the email to be processed
     *
     * @param type $email
     * @return string
     */
    private function formatEmail($email)
    {
        return trim(strtolower($email));
    }
    
/*******************************************************************************/
    /**
     * Setup the table
     */
    protected function setupTable()
    {
        $sql = "
        CREATE TABLE `{$this->getTableName()}` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(50) NOT NULL DEFAULT '',
            `email` VARCHAR(125) NOT NULL DEFAULT '',
            `password_hash` VARCHAR(125) NOT NULL DEFAULT '',
            `profile_photo` VARCHAR(125) NOT NULL DEFAULT '',
            `access_level` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
            `status` VARCHAR(25) NULL DEFAULT 'active',
            `status_description` VARCHAR(250) NULL DEFAULT NULL,
            `history` LONGTEXT,
            `newsletter_optin` TINYINT(1) NOT NULL DEFAULT '1',
            `last_login` DATETIME NOT NULL,
            `require_password_change` TINYINT(1) NOT NULL DEFAULT '0',
            `created_at` DATETIME NOT NULL,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `has_auth_login` TINYINT(1) NOT NULL DEFAULT '0',
            `default_oauth_provider` VARCHAR(50) NOT NULL DEFAULT '',
            `facebook_uid` VARCHAR(50) NULL DEFAULT NULL,
            `facebook_token` VARCHAR(125) NULL DEFAULT NULL,
            `facebook_token_secret` VARCHAR(250) NULL DEFAULT NULL,
            `twitter_uid` VARCHAR(50) NULL DEFAULT NULL,
            `twitter_token` VARCHAR(50) NULL DEFAULT NULL,
            `twitter_token_secret` VARCHAR(250) NULL DEFAULT NULL,
            `twitter_name` VARCHAR(50) NULL DEFAULT NULL,
            `google_uid` VARCHAR(50) NULL DEFAULT NULL,
            `google_token` VARCHAR(50) NULL DEFAULT NULL,
            `google_token_secret` VARCHAR(250) NULL DEFAULT NULL,
            `github_uid` VARCHAR(50) NULL DEFAULT NULL,
            `github_token` VARCHAR(50) NULL DEFAULT NULL,
            `github_token_secret` VARCHAR(250) NULL DEFAULT NULL,

            PRIMARY KEY (`id`),
            INDEX `email` (`email`),
            INDEX `status` (`status`),
            INDEX `facebook_uid` (`facebook_uid`),
            INDEX `twitter_uid` (`twitter_uid`),
            INDEX `google_uid` (`google_uid`),
            INDEX `github_uid` (`github_uid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";
        $this->createTable($sql);
    }
   
}

