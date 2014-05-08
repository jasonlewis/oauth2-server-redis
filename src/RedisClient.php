<?php

namespace Lewis\OAuth2\Server\Storage;

use League\OAuth2\Server\Entity\ClientEntity;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Storage\ClientInterface;

class RedisClient extends RedisAdapter implements ClientInterface
{
    /**
     * Indicates if clients are limited to specific grants.
     * 
     * @var bool
     */
    protected $limitClientsToGrants = false;

    /**
     * Limits clients to specific grants.
     * 
     * @return \Lewis\OAuth2\Server\Storage\RedisClient
     */
    public function limitClientsToGrants()
    {
        $this->limitClientsToGrants = true;

        return $this;
    }

    /**
     * Get client from Redis storage.
     * 
     * @param  string  $clientId
     * @param  string  $clientSecret
     * @param  string  $redirectUri
     * @param  string  $grantType
     * @return \League\OAuth2\Server\Entity\ClientEntity|null
     */
    public function get($clientId, $clientSecret = null, $redirectUri = null, $grantType = null)
    {
        if (! $client = $this->getValue($clientId, 'oauth_clients')) {
            return null;
        }

        // Attempt to grab a redirection URI from the storage that matches the
        // supplied redirection URI. If we can't find a match then we'll set
        // this it as "null".
        $client['redirect_uri'] = $this->getMatchingRedirectUri($clientId, $redirectUri);

        // If a secret and redirection URI were given then we must correctly
        // validate the client by comparing its ID, secret, and that
        // the supplied redirection URI was registered.
        if (! is_null($clientSecret) && ! is_null($redirectUri)) {
            if ($clientSecret != $client['secret'] || $redirectUri != $client['redirect_uri']) {
                return null;
            }

        // If only the clients secret is given then we must correctly validate
        // the client by comparing its ID and secret.
        } elseif (! is_null($clientSecret) && is_null($redirectUri)) {
            if ($clientSecret != $client['secret']) {
                return null;
            }

        // If only the clients redirection URI is given then we must correctly
        // validate the client by comparing the redirection URI.
        } elseif (is_null($clientSecret) && ! is_null($redirectUri)) {
            if ($redirectUri != $client['redirect_uri']) {
                return null;
            }
        }

        if ($this->clientCannotUseGrant($clientId, $grantType)) {
            return null;
        }

        return (new ClientEntity($this->getServer()))
            ->setId($client['id'])
            ->setSecret($client['secret'])
            ->setName($client['name'])
            ->setRedirectUri($client['redirect_uri']);
    }

    /**
     * Get a matching redirect URI associated with a client.
     * 
     * @param  string  $clientId
     * @param  string  $redirectUri
     * @return string|null
     */
    protected function getMatchingRedirectUri($clientId, $redirectUri)
    {
        return $this->getMatchingMember($clientId, 'oauth_client_endpoints', function ($value) use ($redirectUri) {
            return $value['redirect_uri'] == $redirectUri ? $value['redirect_uri'] : null;
        });
    }

    /**
     * Get client from Redis storage by an associated session.
     * 
     * @param  \League\OAuth2\Server\Entity\SessionEntity  $session
     * @return \League\OAuth2\Server\Entity\ClientEntity|null
     */
    public function getBySession(SessionEntity $session)
    {
        if (! $session = $this->getValue($session->getId(), 'oauth_sessions')) {
            return null;
        }

        return $this->get($session['client_id']);
    }

    /**
     * Determines if the client is able to use the grant type specified.
     * 
     * @param  string  $clientId
     * @param  string  $grantType
     * @return bool
     */
    protected function clientCannotUseGrant($clientId, $grantType)
    {
        if (! $this->limitClientsToGrants || is_null($grantType)) {
            return false;
        }

        return ! $this->getMatchingMember($clientId, 'oauth_client_grants', function ($value) use ($grantType) {
            return $value['id'] == $grantType;
        });
    }
}
