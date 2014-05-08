<?php

use Mockery as m;
use League\OAuth2\Server\Entity\ScopeEntity;
use Lewis\OAuth2\Server\Storage\RedisSession;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Entity\AuthCodeEntity;
use League\OAuth2\Server\Entity\AccessTokenEntity;

class RedisSessionTest extends PHPUnit_Framework_TestCase {


	public function tearDown()
	{
		m::close();
	}


	public function setUp()
	{
		$this->redis = m::mock('Predis\Client');
		$this->server = m::mock('League\OAuth2\Server\AbstractServer');
		$this->storage = new RedisSession($this->redis);
		$this->storage->setServer($this->server);
	}


	public function testGetSessionByIdReturnsNullForInvalidSession()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:sessions:1')->andReturn(null);

		$this->assertNull($this->storage->get(1));
	}


	public function testGetSessionByIdReturnsSessionEntity()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:sessions:1')->andReturn('{"id":1,"owner_type":"user","owner_id":1}');
		
		$session = $this->storage->get(1);

		$this->assertInstanceOf('League\OAuth2\Server\Entity\SessionEntity', $session);
		$this->assertEquals(1, $session->getId());
		$this->assertEquals(1, $session->getOwnerId());
		$this->assertEquals('user', $session->getOwnerType());
	}


	public function testGetSessionByAccessTokenReturnsNullForInvalidAccessToken()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:access:tokens:foo')->andReturn(null);

		$token = (new AccessTokenEntity($this->server))->setToken('foo');

		$this->assertNull($this->storage->getByAccessToken($token));
	}


	public function testGetSessionByAccessTokenReturnsNullForInvalidAccessTokenSession()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:access:tokens:foo')->andReturn('{"id":"foo","session_id":1}');
		$this->redis->shouldReceive('get')->once()->with('oauth:sessions:1')->andReturn(null);

		$token = (new AccessTokenEntity($this->server))->setToken('foo');

		$this->assertNull($this->storage->getByAccessToken($token));
	}


	public function testGetSessionByAccessTokenReturnsSessionEntity()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:access:tokens:foo')->andReturn('{"id":"foo","session_id":1}');
		$this->redis->shouldReceive('get')->once()->with('oauth:sessions:1')->andReturn('{"id":1,"owner_type":"user","owner_id":1}');

		$token = (new AccessTokenEntity($this->server))->setToken('foo');
		$session = $this->storage->getByAccessToken($token);

		$this->assertInstanceOf('League\OAuth2\Server\Entity\SessionEntity', $session);
	}


	public function testGetSessionByAuthCodeReturnsNullForInvalidAuthCode()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:auth:codes:foo')->andReturn(null);

		$code = (new AuthCodeEntity($this->server))->setToken('foo');

		$this->assertNull($this->storage->getByAuthCode($code));
	}


	public function testGetSessionByAuthCodeReturnsNullForInvalidAuthCodeSession()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:auth:codes:foo')->andReturn('{"id":"foo","session_id":1}');
		$this->redis->shouldReceive('get')->once()->with('oauth:sessions:1')->andReturn(null);

		$code = (new AuthCodeEntity($this->server))->setToken('foo');

		$this->assertNull($this->storage->getByAuthCode($code));
	}


	public function testGetSessionByAuthCodeReturnsSessionEntity()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:auth:codes:foo')->andReturn('{"id":"foo","session_id":1}');
		$this->redis->shouldReceive('get')->once()->with('oauth:sessions:1')->andReturn('{"id":1,"owner_type":"user","owner_id":1}');

		$code = (new AuthCodeEntity($this->server))->setToken('foo');
		$session = $this->storage->getByAuthCode($code);

		$this->assertInstanceOf('League\OAuth2\Server\Entity\SessionEntity', $session);
	}


	public function testGetSessionScopes()
	{
		$this->redis->shouldReceive('smembers')->once()->with('oauth:session:scopes:1')->andReturn([
			['id' => 'foo'],
			['id' => 'bar'],
			['id' => 'baz']
		]);
		$this->redis->shouldReceive('get')->once()->with('oauth:scopes:foo')->andReturn(['id' => 'foo', 'description' => 'foo']);
		$this->redis->shouldReceive('get')->once()->with('oauth:scopes:bar')->andReturn(null);
		$this->redis->shouldReceive('get')->once()->with('oauth:scopes:baz')->andReturn(['id' => 'baz', 'description' => 'baz']);

		$scopes = $this->storage->getScopes((new SessionEntity($this->server))->setId(1));

		$this->assertCount(2, $scopes);
		$this->assertEquals('foo', $scopes[0]->getId());
		$this->assertEquals('baz', $scopes[1]->getId());
	}


	public function testCreateNewSessionEntity()
	{
		$this->redis->shouldReceive('incr')->once()->with('oauth:session:ids')->andReturn(1);
		$this->redis->shouldReceive('set')->once()->with('oauth:sessions:1', '{"id":1,"client_id":1,"owner_type":"user","owner_id":1,"redirect_uri":"foo"}');

		$this->assertEquals(1, $this->storage->create('user', 1, 1, 'foo'));
	}


	public function testAssociatingScopeWithSession()
	{
		$session = (new SessionEntity($this->server))->setId(1);
		$scope = (new ScopeEntity($this->server))->setId('foo');

		$this->redis->shouldReceive('sadd')->once()->with('oauth:session:scopes:1', '{"id":"foo"}');

		$this->storage->associateScope($session, $scope);
	}


}
