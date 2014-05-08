<?php

use Mockery as m;
use Lewis\OAuth2\Server\Storage\RedisRefreshToken;
use League\OAuth2\Server\Entity\RefreshTokenEntity;

class RedisRefreshTokenTest extends PHPUnit_Framework_TestCase {


	public function tearDown()
	{
		m::close();
	}


	public function setUp()
	{
		$this->redis = m::mock('Predis\Client');
		$this->server = m::mock('League\OAuth2\Server\AbstractServer');
		$this->storage = new RedisRefreshToken($this->redis);
		$this->storage->setServer($this->server);
	}


	public function testGetRefreshTokenReturnsNullForInvalidRefreshToken()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:refresh:tokens:foo')->andReturn(null);

		$this->assertNull($this->storage->get('foo'));
	}


	public function testGetRefreshTokenReturnsRefreshTokenEntity()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:refresh:tokens:foo')->andReturn('{"id":"foo","expire_time":1}');

		$token = $this->storage->get('foo');

		$this->assertInstanceOf('League\OAuth2\Server\Entity\RefreshTokenEntity', $token);
		$this->assertEquals('foo', $token->getToken());
		$this->assertEquals(1, $token->getExpireTime());
	}


	public function testCreateNewRefreshTokenEntity()
	{
		$this->redis->shouldReceive('set')->once()->with('oauth:refresh:tokens:foo', '{"id":"foo","expire_time":1,"access_token_id":"bar"}');
		$this->redis->shouldReceive('sadd')->once()->with('oauth:refresh:tokens', 'foo');

		$token = $this->storage->create('foo', 1, 'bar');

		$this->assertInstanceOf('League\OAuth2\Server\Entity\RefreshTokenEntity', $token);
		$this->assertEquals('foo', $token->getToken());
		$this->assertEquals(1, $token->getExpireTime());
	}


	public function testDeleteRefreshTokenEntity()
	{
		$this->redis->shouldReceive('del')->once()->with('oauth:refresh:tokens:foo');
		$this->redis->shouldReceive('srem')->once()->with('oauth:refresh:tokens', 'foo');

		$token = (new RefreshTokenEntity($this->server))->setToken('foo');

		$this->storage->delete($token);
	}


}
