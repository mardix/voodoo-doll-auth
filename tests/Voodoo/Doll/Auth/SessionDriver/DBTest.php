<?php

namespace Voodoo\Doll\Auth\SessionDriver;
use Voodoo\Doll\Auth;


class Cookie {
    public static $cookie = null;
    public static function set($c) 
    {
        self::$cookie = $c;
    }
    
    public static function get()
    {
        return self::$cookie;
    }
}

class MockDB extends DB {

    protected function setup()
    {
        parent::setup();
        self::$session = null;
    }
    /**
     * Return the cookie 
     * 
     * @return string
     */
    protected function getCookie() {
        return Cookie::get();
    }
    
    /**
     * Set the cookie
     * @param type $sessionId
     * @param type $expire
     */
    protected function setCookie($sessionId = "", $expire = 0)
    {
        Cookie::set($sessionId);     
    }    
    
    public function __destruct() {
        self::$session = null;
    }
}

class DBTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var DB
     */
    protected $object;

    protected $userId = 1234;
    
    protected function setUp() {
        $this->object = new MockDB;
    }

    protected function tearDown() {
        $this->object->reset()->delete(true);
    }

    public function testCreateNew() {
        $session = $this->object->createNew($this->userId);
        $this->assertEquals($this->userId, $session->user_id);
    }
    
    public function testGetSession() {
        $this->object->createNew($this->userId);
        $this->assertEquals($this->userId, $this->object->getSession()->user_id);        
    }
    
    public function testDestroy() {
        $session = $this->object->createNew($this->userId);
        $this->assertTrue($session->destroy());
    }
    
    public function testDestroyAll() 
    {
        $this->object->destroyAll(true);
        $this->assertEquals(0, $this->object->reset()->count("id"));
    }
    
    public function testGetUser() {
        $user  = (new Auth\User)->createWithEmail(time()."@y.com", "mypassword"); 
        $session = $this->object->createNew($user->getPK());
        $this->assertEquals($user->id, $session->getUser()->id);
    }

    public function testStealthMode() {
        $this->object->delete(true);
        $this->object->createNew($this->userId);
        $this->assertEquals(1, $this->object->reset()->where("user_id", $this->userId)->count("id"));
        (new MockDB)->createNew($this->userId, null, true);
        $this->assertEquals(2, $this->object->reset()->where("user_id", $this->userId)->count("id"));
    }


    public function testData() {
        $session = $this->object->createNew($this->userId);
        $session->setData("name", "Joe");
        $this->assertEquals("Joe", $session->getData("name"));
    }


    public function testGetCount() {
        $this->object->createNew(145);
        $this->object->createNew(244);
        $this->object->createNew(556);
        $this->object->createNew(4152);
        $this->assertEquals(4, $this->object->getCount(true));
    }
}

?>
