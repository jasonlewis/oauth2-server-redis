<?php

namespace Lewis\OAuth2\Server\Storage;

use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Entity\AuthCodeEntity;
use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Storage\SessionInterface;

class RedisSession extends RedisAdapter implements SessionInterface
{
    /**
     * Get a session from Redis storage.
     * 
     * @param  string  $sessionId
     * @return \League\OAuth2\Server\Entity\SessionEntity|null
     */
    public function get($sessionId)
    {
        if (! $session = $this->getValue($sessionId, 'oauth_sessions')) {
            return null;
        }

        return (new SessionEntity($this->getServer()))
            ->setId($session['id'])
            ->setOwner($session['owner_type'], $session['owner_id']);
    }

    /**
     * Get a session from Redis storage by an associated access token.
     * 
     * @param  \League\OAuth2\Server\Entity\AccessTokenEntity  $accessToken
     * @return \League\OAuth2\Server\Entity\SessionEntity|null
     */
    public function getByAccessToken(AccessTokenEntity $accessToken)
    {
        if (! $token = $this->getValue($accessToken->getToken(), 'oauth_access_tokens')) {
            return null;
        }

        return $this->get($token['session_id']);
    }

    /**
     * Get a session from Redis storage by an associated authorization code.
     * 
     * @param  \League\OAuth2\Server\Entity\AuthCodeEntity  $authCode
     * @return \League\OAuth2\Server\Entity\SessionEntity|null
     */
    public function getByAuthCode(AuthCodeEntity $authCode)
    {
        if (! $code = $this->getValue($authCode->getToken(), 'oauth_auth_codes')) {
            return null;
        }

        return $this->get($code['session_id']);
    }

    /**
     * Get associated session scopes from Redis storage.
     * 
     * @param  \League\OAuth2\Server\Entity\SessionEntity  $session
     * @return array
     */
    public function getScopes(SessionEntity $session)
    {
        $scopes = [];

        foreach ($this->getSet($session->getId(), 'oauth_session_scopes') as $scope) {
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
     * Create a new session in Redis storage.
     * 
     * @param  string  $ownerType
     * @param  string  $ownerId
     * @param  string  $clientId
     * @param  string  $clientRedirectUri
     * @return int
     */
    public function create($ownerType, $ownerId, $clientId, $clientRedirectUri = null)
    {
        $sessionId = $this->increment('oauth_session_ids');

        $this->setValue($sessionId, 'oauth_sessions', [
            'id'         => $sessionId,
            'client_id'  => $clientId,
            'owner_type' => $ownerType,
            'owner_id'   => $ownerId,
            'redirect_uri' => $clientRedirectUri
        ]);

        return $sessionId;
    }

    /**
     * Associate a scope with a session in Redis storage.
     * 
     * @param  \League\OAuth2\Server\Entity\SessionEntity  $session
     * @param  \League\OAuth2\Server\Entity\ScopeEntity  $scope
     * @return void
     */
    public function associateScope(SessionEntity $session, ScopeEntity $scope)
    {
        $this->pushSet($session->getId(), 'oauth_session_scopes', ['id' => $scope->getId()]);
    }
}
