<?php

namespace Lewis\OAuth2\Server\Storage;

use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Storage\ScopeInterface;

class RedisScope extends RedisAdapter implements ScopeInterface
{
    /**
     * Get scope from Redis storage.
     *
     * @param  string  $scope
     * @param  string  $grantType
     * @return \League\OAuth2\Server\Entity\ScopeEntity|null
     */
    public function get($scope, $grantType = null)
    {
        if (! $scope = $this->getValue($scope, 'oauth_scopes')) {
            return null;
        }

        return (new ScopeEntity($this->getServer()))
            ->setId($scope['id'])
            ->setDescription($scope['description']);
    }
}
