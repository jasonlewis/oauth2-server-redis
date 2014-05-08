<?php

use Mockery as m;
use League\OAuth2\Server\Entity\ScopeEntity;
use Lewis\OAuth2\Server\Storage\RedisAccessToken;
use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entity\RefreshTokenEntity;

class RedisAccessTokenTest extends PHPUnit_Framework_TestCase {


	public function tearDown()
	{
		m::close();
	}


	public function setUp()
	{
		$this->redis = m::mock('Predis\Client');
		$this->server = m::mock('League\OAuth2\Server\AbstractServer');
		$this->storage = new RedisAccessToken($this->redis);
		$this->storage->setServer($this->server);
	}


	public function testGetAccessTokenReturnsNullForInvalidAccessToken()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:access:tokens:foo')->andReturn(null);

		$this->assertNull($this->storage->get('foo'));
	}


	public function testGetAccessTokenReturnsAccessTokenEntity()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:access:tokens:foo')->andReturn('{"id":"foo","expire_time":1}');

		$token = $this->storage->get('foo');

		$this->assertInstanceOf('League\OAuth2\Server\Entity\AccessTokenEntity', $token);
		$this->assertEquals('foo', $token->getToken());
		$this->assertEquals(1, $token->getExpireTime());
	}


	public function testGetAccessTokenByRefreshTokenReturnsNullForInvalidRefreshToken()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:refresh:tokens:foo')->andReturn(null);

		$refresh = (new RefreshTokenEntity($this->server))->setToken('foo');

		$this->assertNull($this->storage->getByRefreshToken($refresh));
	}


	public function testGetAccessTokenByRefreshTokenReturnsAccessTokenEntity()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:refresh:tokens:foo')->andReturn('{"access_token":"bar"}');
		$this->redis->shouldReceive('get')->once()->with('oauth:access:tokens:bar')->andReturn('{"id":"bar","expire_time":1}');

		$refresh = (new RefreshTokenEntity($this->server))->setToken('foo');
		$access = $this->storage->getByRefreshToken($refresh);

		$this->assertInstanceOf('League\OAuth2\Server\Entity\AccessTokenEntity', $access);
		$this->assertEquals('bar', $access->getToken());
		$this->assertEquals(1, $access->getExpireTime());
	}


	public function testGetAccessTokenScopes()
	{
		$this->redis->shouldReceive('smembers')->once()->with('oauth:access:token:scopes:foo')->andReturn([
			['id' => 'foo'],
			['id' => 'bar'],
			['id' => 'baz']
		]);
		$this->redis->shouldReceive('get')->once()->with('oauth:scopes:foo')->andReturn(['id' => 'foo', 'description' => 'foo']);
		$this->redis->shouldReceive('get')->once()->with('oauth:scopes:bar')->andReturn(null);
		$this->redis->shouldReceive('get')->once()->with('oauth:scopes:baz')->andReturn(['id' => 'baz', 'description' => 'baz']);

		$scopes = $this->storage->getScopes((new AccessTokenEntity($this->server))->setToken('foo'));

		$this->assertCount(2, $scopes);
		$this->assertEquals('foo', $scopes[0]->getId());
		$this->assertEquals('baz', $scopes[1]->getId());
	}


	public function testCreateNewAccessTokenEntity()
	{
		$this->redis->shouldReceive('set')->once()->with('oauth:access:tokens:foo', '{"id":"foo","expire_time":1,"session_id":1}');
		$this->redis->shouldReceive('sadd')->once()->with('oauth:access:tokens', 'foo');

		$token = $this->storage->create('foo', 1, 1);

		$this->assertInstanceOf('League\OAuth2\Server\Entity\AccessTokenEntity', $token);
		$this->assertEquals('foo', $token->getToken());
		$this->assertEquals(1, $token->getExpireTime());
	}


	public function testAssociatingScopeWithAccessToken()
	{
		$token = (new AccessTokenEntity($this->server))->setToken('foo');
		$scope = (new ScopeEntity($this->server))->setId('bar');

		$this->redis->shouldReceive('sadd')->once()->with('oauth:access:token:scopes:foo', '{"id":"bar"}');

		$this->storage->associateScope($token, $scope);
	}


	public function testDeleteAccessTokenEntity()
	{
		$this->redis->shouldReceive('del')->once()->with('oauth:access:tokens:foo');
		$this->redis->shouldReceive('del')->once()->with('oauth:access:token:scopes:foo');
		$this->redis->shouldReceive('srem')->once()->with('oauth:access:tokens', 'foo');

		$token = (new AccessTokenEntity($this->server))->setToken('foo');

		$this->storage->delete($token);
	}


}
