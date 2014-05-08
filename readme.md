# Redis Storage Adapter

This is a Redis storage adapter for the [League's PHP OAuth 2.0 server](https://github.com/thephpleague/oauth2-server) which is maintained by Alex Bilbie.

[![Build Status](https://travis-ci.org/dingo/api.svg?branch=master)](https://travis-ci.org/dingo/api)

## Foreword

This adapter is for the fourth version of the OAuth 2.0 server and as such is prone to breakages as the server itself is being developed.

## Usage

This storage adapter uses [Predis](https://github.com/nrk/predis) as an interface to Redis. Make sure you have Redis installed on your machine to use this adapter.

You must get a new instance of the Predis client that is injected into the storage adapater.

```php
$redis = new Predis\Client;
```

Once you have an instance of `League\OAuth2\Server\AuthorizationServer` you can set the different storages.

```php
$server->setClientStorage(new Lewis\OAuth2\Server\Storage\RedisClient($redis));
$server->setSessionStorage(new Lewis\OAuth2\Server\Storage\RedisSession($redis));
$server->setAccessTokenStorage(new Lewis\OAuth2\Server\Storage\RedisAccessToken($redis));
$server->setRefreshTokenStorage(new Lewis\OAuth2\Server\Storage\RedisRefreshTokenStorage($redis));
$server->setAuthCodeStorage(new Lewis\OAuth2\Server\Storage\RedisAuthCodeStorage($redis));
$server->setScopeStorage(new Lewis\OAuth2\Server\Storage\RedisScopeStorage($redis));
```

## License

This package is licensed under the [BSD 2-Clause license](http://opensource.org/licenses/BSD-2-Clause).
