<?php

namespace Lewis\OAuth2\Server\Storage;

use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Entity\AuthCodeEntity;
use League\OAuth2\Server\Storage\AuthCodeInterface;

class RedisAuthCode extends RedisAdapter implements AuthCodeInterface
{
    /**
     * Get an authorization code from Redis storage.
     * 
     * @param  string  $code
     * @return \League\OAuth2\Server\Entity\AuthCodeEntity|null
     */
    public function get($code)
    {
        if (! $code = $this->getValue($code, 'oauth_auth_codes')) {
            return null;
        }

        return (new AuthCodeEntity($this->getServer()))
            ->setToken($code['id'])
            ->setExpireTime($code['expire_time']);
    }

    /**
     * Get associated authorization code scopes from Redis storage.
     * 
     * @param  \League\OAuth2\Server\Entity\AuthCodeEntity  $code
     * @return array
     */
    public function getScopes(AuthCodeEntity $code)
    {
        $scopes = [];

        foreach ($this->getSet($code->getToken(), 'oauth_auth_code_scopes') as $scope) {
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
     * Creates a new authorization code in Redis storage.
     * 
     * @param  string  $code
     * @param  int  $expireTime
     * @param  string|int  $sessionId
     * @return \League\OAuth2\Server\Entity\AuthCodeEntity
     */
    public function create($code, $expireTime, $sessionId)
    {
        $payload = [
            'id'          => $code,
            'expire_time' => $expireTime,
            'session_id'  => $sessionId
        ];
        
        $this->setValue($code, 'oauth_auth_codes', $payload);
        $this->pushSet(null, 'oauth_auth_codes', $code);

        return (new AuthCodeEntity($this->getServer()))
               ->setToken($code)
               ->setExpireTime($expireTime);
    }

    /**
     * Associate a scope with an authorization code in Redis storage.
     * 
     * @param  \League\OAuth2\Server\Entity\AuthCodeEntity  $code
     * @param  \League\OAuth2\Server\Entity\ScopeEntity  $scope
     * @return void
     */
    public function associateScope(AuthCodeEntity $code, ScopeEntity $scope)
    {
        $this->pushSet($code->getToken(), 'oauth_auth_code_scopes', ['id' => $scope->getId()]);
    }

    /**
     * Delete an authorization code from Redis storage.
     * 
     * @param  \League\OAuth2\Server\Entity\AuthCodeEntity  $code
     * @return void
     */
    public function delete(AuthCodeEntity $code)
    {
        // Deletes the authorization code entry.
        $this->deleteKey($code->getToken(), 'oauth_auth_codes');

        // Deletes the authorization code entry from the authorization codes set.
        $this->deleteSet(null, 'oauth_auth_codes', $code->getToken());

        // Deletes the authorization codes associated scopes.
        $this->deleteKey($code->getToken(), 'oauth_auth_code_scopes');
    }
}
