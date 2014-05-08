<?php

use Mockery as m;
use Lewis\OAuth2\Server\Storage\RedisClient;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Entity\RefreshTokenEntity;

class RedisClientTest extends PHPUnit_Framework_TestCase {


	public function tearDown()
	{
		m::close();
	}


	public function setUp()
	{
		$this->redis = m::mock('Predis\Client');
		$this->server = m::mock('League\OAuth2\Server\AbstractServer');
		$this->storage = new RedisClient($this->redis);
		$this->storage->setServer($this->server);
	}


	public function testGetClientReturnsNullForInvalidClient()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:clients:foo')->andReturn(null);

		$this->assertNull($this->storage->get('foo'));
	}


	public function testGetClientBySecretAndRedirectUriReturnsNullWhenEitherIsInvalid()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:clients:foo')->andReturn('{"id":"foo","secret":"bar"}');
		$this->redis->shouldReceive('smembers')->once()->with('oauth:client:endpoints:foo')->andReturn([
			'{"redirect_uri":"baz"}',
			'{"redirect_uri":"zap"}'
		]);
		$this->assertNull($this->storage->get('foo', 'bar', 'foo'));
		$this->assertNull($this->storage->get('foo', 'foo', 'baz'));
	}


	public function testGetClientBySecretReturnsNullWhenSecretIsInvalid()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:clients:foo')->andReturn('{"id":"foo","secret":"bar"}');
		$this->redis->shouldReceive('smembers')->once()->with('oauth:client:endpoints:foo')->andReturn([]);
		$this->assertNull($this->storage->get('foo', 'baz', null));
	}


	public function testGetClientByRedirectionUriReturnsNullWhenRedirectionUriIsInvalid()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:clients:foo')->andReturn('{"id":"foo","secret":"bar"}');
		$this->redis->shouldReceive('smembers')->once()->with('oauth:client:endpoints:foo')->andReturn([['redirect_uri' => 'foo']]);
		$this->assertNull($this->storage->get('foo', null, 'bar'));
	}


	public function testGetClientByIdReturnsNullIfClientIsNotAbleToUseGrant()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:clients:foo')->andReturn('{"id":"foo","secret":"bar"}');
		$this->redis->shouldReceive('smembers')->once()->with('oauth:client:endpoints:foo')->andReturn([]);
		$this->redis->shouldReceive('smembers')->once()->with('oauth:client:grants:foo')->andReturn([
			['id' => 'foo'],
			['id' => 'bar']
		]);

		$this->storage->limitClientsToGrants();
		$this->assertNull($this->storage->get('foo', null, null, 'zap'));
	}


	public function testGetClientByIdReturnsClientEntity()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:clients:foo')->andReturn('{"id":"foo","secret":"bar","name":"Foo"}');
		$this->redis->shouldReceive('smembers')->once()->with('oauth:client:endpoints:foo')->andReturn([['redirect_uri' => 'foo']]);

		$client = $this->storage->get('foo', null, 'foo');

		$this->assertInstanceOf('League\OAuth2\Server\Entity\ClientEntity', $client);
		$this->assertEquals('foo', $client->getId());
		$this->assertEquals('Foo', $client->getName());
		$this->assertEquals('bar', $client->getSecret());
	}


	public function testGetClientBySessionReturnsNullWhenSessionIsInvalid()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:sessions:1')->andReturn(null);

		$session = (new SessionEntity($this->server))->setId(1);

		$this->assertNull($this->storage->getBySession($session));
	}


	public function testGetClientBySessionReturnsClientEntity()
	{
		$this->redis->shouldReceive('get')->once()->with('oauth:sessions:1')->andReturn('{"client_id":"foo"}');
		$this->redis->shouldReceive('get')->once()->with('oauth:clients:foo')->andReturn('{"id":"foo","secret":"bar","name":"Foo"}');
		$this->redis->shouldReceive('smembers')->once()->with('oauth:client:endpoints:foo')->andReturn([]);

		$session = (new SessionEntity($this->server))->setId(1);
		$client = $this->storage->getBySession($session);

		$this->assertInstanceOf('League\OAuth2\Server\Entity\ClientEntity', $client);
		$this->assertEquals('foo', $client->getId());
		$this->assertEquals('Foo', $client->getName());
		$this->assertEquals('bar', $client->getSecret());
	}


}
