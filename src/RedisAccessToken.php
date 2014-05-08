<?php

namespace Lewis\OAuth2\Server\Storage;

use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entity\RefreshTokenEntity;
use League\OAuth2\Server\Entity\AbstractTokenEntity;
use League\OAuth2\Server\Storage\AccessTokenInterface;

class RedisAccessToken extends RedisAdapter implements AccessTokenInterface
{
    /**
     * Get access token from Redis storage.
     * 
     * @param  string  $token
     * @return \League\OAuth2\Server\Entity\AccessTokenEntity|null
     */
    public function get($token)
    {
        if (! $access = $this->getValue($token, 'oauth_access_tokens')) {
            return null;
        }

        return (new AccessTokenEntity($this->getServer()))
            ->setToken($access['id'])
            ->setExpireTime($access['expire_time']);
    }

    /**
     * Get access token from Redis storage by an associated refresh token.
     * 
     * @param  \League\OAuth2\Server\Entity\RefreshTokenEntity  $refreshToken
     * @return \League\OAuth2\Server\Entity\AccessTokenEntity|null
     */
    public function getByRefreshToken(RefreshTokenEntity $refreshToken)
    {
        if (! $refresh = $this->getValue($refreshToken->getToken(), 'oauth_refresh_tokens')) {
            return null;
        }

        return $this->get($refresh['access_token']);
    }

    /**
     * Get associated access token scopes from Redis storage.
     * 
     * @param  \League\OAuth2\Server\Entity\AbstractTokenEntity  $token
     * @return array
     */
    public function getScopes(AbstractTokenEntity $token)
    {
        $scopes = [];

        foreach ($this->getSet($token->getToken(), 'oauth_access_token_scopes') as $scope) {
            if (! $scope = $this->getValue($scope['id'], 'oauth_scopes')) {
                continue;
            }

            $scopes[] = (new ScopeEntity($this->getServer()))
                ->setId($scope['id'])
                ->setDescription($scope['description']);
        }

        return $scopes;
    }

    /**
     * Creates a new access token in Redis storage.
     * 
     * @param  string  $token
     * @param  int  $expireTime
     * @param  string|int  $sessionId
     * @return \League\OAuth2\Server\Entity\AccessTokenEntity
     */
    public function create($token, $expireTime, $sessionId)
    {
        $payload = [
            'id'          => $token,
            'expire_time' => $expireTime,
            'session_id'  => $sessionId
        ];
        
        $this->setValue($token, 'oauth_access_tokens', $payload);
        $this->pushSet(null, 'oauth_access_tokens', $token);

        return (new AccessTokenEntity($this->getServer()))
               ->setToken($token)
               ->setExpireTime($expireTime);
    }

    /**
     * Associate a scope with an access token in Redis storage.
     * 
     * @param  \League\OAuth2\Server\Entity\AbstractTokenEntity  $token
     * @param  \League\OAuth2\Server\Entity\ScopeEntity  $scope
     * @return void
     */
    public function associateScope(AbstractTokenEntity $token, ScopeEntity $scope)
    {
        $this->pushSet($token->getToken(), 'oauth_access_token_scopes', ['id' => $scope->getId()]);
    }

    /**
     * Delete an access token from Redis storage.
     * 
     * @param  \League\OAuth2\Server\Entity\AbstractTokenEntity  $token
     * @return void
     */
    public function delete(AbstractTokenEntity $token)
    {
        // Deletes the access token entry.
        $this->deleteKey($token->getToken(), 'oauth_access_tokens');

        // Deletes the access token entry from the access tokens set.
        $this->deleteSet(null, 'oauth_access_tokens', $token->getToken());

        // Deletes the access tokens associated scopes.
        $this->deleteKey($token->getToken(), 'oauth_access_token_scopes');
    }
}
