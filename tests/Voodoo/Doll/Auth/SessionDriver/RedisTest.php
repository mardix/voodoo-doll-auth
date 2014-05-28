<?php

namespace Voodoo\Doll\Auth\SessionDriver;
use Voodoo\Doll\Auth;

class Cookie2 {
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

class MockRedis extends Redis {

    public function __construct()
    {
        parent::__construct();
        self::$session = null;
    }
    /**
     * Return the cookie 
     * 
     * @return string
     */
    protected function getCookie() {
        return Cookie2::get();
    }
    
    /**
     * Set the cookie
     * @param type $sessionId
     * @param type $expire
     */
    protected function setCookie($sessionId = "", $expire = 0)
    {
        Cookie2::set($sessionId);     
    }    
    
    public function __destruct() {
        self::$session = null;
    }
}

class RedisTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var Redis
     */
    protected $object;

    protected function setUp() {
        $this->object = new MockRedis;
    }

    protected function tearDown() {
        
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
        $this->assertEquals(0, $this->object->getCount(true));
    }
    
    public function testGetUser() {
        $user  = (new Auth\User)->createWithEmail(time()."@y.com", "mypassword"); 
        $session = $this->object->createNew($user->getPK());
        $this->assertEquals($user->id, $session->getUser()->id);
    }

    public function testData() {
        $session = $this->object->createNew($this->userId);
        $session->setData("name", "Joe");
        $this->assertEquals("Joe", $session->getData("name"));
    }
    
    public function testGetCount() {
        $this->object->destroyAll(true);
        $this->object->createNew(145);
        $this->object->createNew(244)->getSession();
        (new MockRedis)->createNew(556)->getSession();
        $d = $this->object->createNew(4152);
        
        $this->assertEquals(4, $this->object->getCount(true));
        $this->assertEquals(2, $this->object->getCount());
        
        $d->destroy();
        $this->assertEquals(3, $this->object->getCount(true));        
    }    
       
}

?>
