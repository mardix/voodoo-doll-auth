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


class Account extends Core\Model
{
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
            "name" => [ "type" => "string", "length" => 75],
            "email" => [ "type" => "string", "length" => 125, "index" => true ],
            "password_hash" => [ "type" => "string" ],
            "require_password_change" => ["type" => "bool", "default" => '0'],
            "first_name" => [ "type" => "string" ],
            "last_name" => [ "type" => "string" ],
            "dob" => [ "type" => "date" ],
            "gender" => [ "type" => "string", "length" => 15 ],
            "address" => [ "type" => "string" ],
            "address2" => [ "type" => "string" ],
            "city" => [ "type" => "string"  ],
            "state" => [ "type" => "string" ],
            "zip_code" => [ "type" => "string" ],
            "country" => [ "type" => "string" ],
            "telephone" => [ "type" => "string" ],
            "company_name" => [ "type" => "string" ],
            "timezone" => [ "type" => "string", "length" => 25, "default" => "EST" ],
            "lang" => [ "type" => "string", "length" => 4, "default" => "EN" ],
            "picture_url" => [ "type" => "string" ],
            "access_level" => ["type" => "TINYINT", "length" => 3, "default" => '1' ],
            "status" => ["type" => "string", "length" => 25, "default" => "ACTIVE", "index" => true ],
            "status_description" => ["type" => "text"],
            "history" => ["type" => "LONGTEXT"],
            "newsletter_optin" => ["type" => "bool", "default" => 1],
            "enable_auth_login" => ["type" => "bool", "default" => true],
            "facebook_uid" => ["type" => "string", "length" => 50, "index" => true ],
            "facebook_token" => ["type" => "string", "length" => 125],
            "facebook_token_secret" => ["type" => "string"],
            "twitter_uid" => ["type" => "string", "length" => 50, "index" => true ],
            "twitter_token" => ["type" => "string", "length" => 125],
            "twitter_token_secret" => ["type" => "string"],  
            "twitter_name" => ["type" => "string", "length" => 50],
            "google_uid" => ["type" => "string", "length" => 50, "index" => true ],
            "google_token" => ["type" => "string", "length" => 125],
            "google_token_secret" => ["type" => "string"],            
            "github_uid" => ["type" => "string", "length" => 50, "index" => true ],
            "github_token" => ["type" => "string", "length" => 125],
            "github_token_secret" => ["type" => "string"],            
            "last_login" => [ "type" => "dt"],
            "created_at" => [ "type" => "dt"],
            "updated_at" => [ "type" => "ts"]          
        ]
    ]; 

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
    
    const STATUS_ACTIVE = "ACTIVE";
    const STATUS_DELETED = "DELETED";
    const STATUS_CANCELLED = "CANCELLED";
    const STATUS_SUSPENDED = "SUSPENDED";
    const STATUS_PROBATION = "PROBATION";
    
    const GENDER_MALE = "male";
    const GENDER_FEMALE = "female";
    const GENDER_OTHER = "other";
    
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
     * @return \Voodoo\Doll\Auth\Account
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
    public function createWithEmail($email, $password = null, $name = "", $status = self::STATUS_ACTIVE )
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
                "status" => $status
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
     * @return \Voodoo\Doll\Auth\Account
     * @throws Exception
     */
    public function createWithOAuth($authProvider, $uid, $status = self::STATUS_ACTIVE)
    {
        $column = "{$authProvider}_uid";
        $user = $this->reset()->where([$column => $uid])->findOne();
        if ($user) {
            throw new Exception("Login exists already");
        }
        return $this->insert([
            $column => $uid,
            "status" => $status,
            "has_auth_login" => 1
        ]);
    }
    
    /**
     * Update the last login datetime
     * 
     * @return \Voodoo\Doll\Auth\Account
     */
    public function updateLastLogin()
    {
        if ($this->isSingleRow()) {
            $this->update(["last_login" => $this->getDateTime()]);
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
     * @return \Voodoo\Doll\Auth\Account
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
     * @return  \Voodoo\Doll\Auth\Account
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
     * @return \Voodoo\Doll\Auth\Account
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
     * Set the status info 
     * 
     * @param type $status
     * @param type $description
     * @return \Voodoo\Doll\Auth\Account
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
     * @return \Voodoo\Doll\Auth\Account
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
     * @return \Voodoo\Doll\Auth\Account
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
     * 
     * @param type $authProvider
     * @param string $creen_name
     * @return \Voodoo\Doll\Auth\Account
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
     * Enable/Disable auth login
     * 
     * @param bool
     * @return \Voodoo\Doll\Auth\Account
     */
    public function enableAuthLogin($enable = true)
    {
        $this->update(["enable_auth_login" => $enable ? "1" : "0"]);
        return $this;
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
     * @return \Voodoo\Doll\Auth\Account
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
     * @return \Voodoo\Doll\Auth\Account
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
     * Set account picture url
     * @param string $url
     * @return \Voodoo\Doll\Auth\Account
     */
    public function setPictureUrl($url)
    {
        $this->update(["picture_url" => $url]);
        return $this;
    }
    
    /**
     * Get profile photo
     * 
     * @return string
     */
    public function getPictureUrl()
    {
        return $this->picture_url;
    }
    
    /**
     * Set the access level
     * @param int $acl
     * @return \Voodoo\Doll\Auth\Account
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
     * @return \Voodoo\Doll\Auth\Account
     */
    public function setHistory($title, Array $data = [])
    {
        $history = json_decode($this->history, true);
        if (! is_array($history)) {
            $history = [];
        }
        $history[] = [
            "datetime" => $this->getDateTime(),
            "title" => $title,
            "data" => $data
        ];
        $history = json_encode($history);
        $this->update(["history" => $history]);
        return $this;
    }
    
    /**
     * Return the age based of the DOB
     * @return int
     */
    public function getAge()
    {
        return (new \DateTime($this->dob))
                ->diff(new \DateTime)
                ->format("%y");        
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
   
}

