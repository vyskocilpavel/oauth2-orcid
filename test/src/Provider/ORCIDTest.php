<?php

namespace CILogon\OAuth2\Client\Test\Provider;

use Mockery as m;

class ORCIDTest extends \PHPUnit_Framework_TestCase
{
    protected $provider;

    protected function setUp()
    {
        $this->provider = new \CILogon\OAuth2\Client\Provider\ORCID([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
        ]);
    }

    public function tearDown()
    {
        m::close();
        parent::tearDown();
    }

    public function testAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);

        $this->assertAttributeNotEmpty('state', $this->provider);
    }

    public function testScopes()
    {
        $options = ['scope' => [uniqid()]];

        $url = $this->provider->getAuthorizationUrl($options);

        $this->assertContains(urlencode(implode(' ', $options['scope'])), $url);
    }

    public function testDefaultScopes()
    {
        $url = $this->provider->getAuthorizationUrl();

        $this->assertContains(urlencode('/authenticate'), $url);
    }

    public function testGetAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        $this->assertEquals('/oauth/authorize', $uri['path']);
    }

    public function testBaseAccessTokenUrl()
    {
        $url = $this->provider->getBaseAccessTokenUrl([]);
        $uri = parse_url($url);
        $this->assertEquals('/oauth/token', $uri['path']);
    }

    public function testResourceOwnerDetailsUrl()
    {
        $id = uniqid();
        $token = m::mock('League\OAuth2\Client\Token\AccessToken');
        $token->shouldReceive('getResourceOwnerId')->andReturn($id);
        $url = $this->provider->getResourceOwnerDetailsUrl($token);
        $uri = parse_url($url);
        $this->assertEquals("/v2.0/$id/record", $uri['path']);
    }

    public function testGetAccessToken()
    {
        $response = m::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn(
            '{"access_token": "mock_access_token", ' .
            '"token_type":"bearer", "refresh_token":"mock_refresh_token"}'
        );
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $response->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertNull($token->getExpires());
        $this->assertEquals('mock_refresh_token', $token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }

    public function testGetRefreshToken()
    {
        $id = uniqid();
        $accesstoken = m::mock('League\OAuth2\Client\Token\AccessToken');
        $accesstoken->shouldReceive('getToken')->andReturn($id);
        $response = m::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn(
            '{"access_token": "mock_access_token", ' .
            '"token_type":"bearer", "refresh_token":"mock_refresh_token"}'
        );
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $response->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken(
            'refresh_token',
            ['refresh_token' => 'mock_refresh_token'],
            $accesstoken
        );

        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertNull($token->getExpires());
        $this->assertEquals('mock_refresh_token', $token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }


    public function testUserData()
    {
        $id = uniqid();
        $uri = "http://orcid.org/$id";
        $date = '1234567890';
        $givenname = uniqid();
        $familyname = uniqid();
        $pubname = uniqid();
        $othername1 = uniqid();
        $othername2 = uniqid();
        $email1 = uniqid();
        $email2 = uniqid();
        $email3 = uniqid();

        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn(
            '{"access_token":"mock_access_token","token_type":"bearer",' .
            '"refresh_token":"mock_refresh_token"}'
        );
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);

        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');

        $userResponse->shouldReceive('getBody')->andReturn(
            '{"orcid-identifier":{"uri":"' . $uri . '","path":"' . $id .
            '","host":"orcid.org"},"preferences":{"locale":"EN"},"' .
            'history":{"creation-method":"DIRECT","completion-date":null' .
            ',"submission-date":{"value":' . $date . '},"last-modified-date":' .
            '{"value":' . $date . '},"claimed":true,"source":null,' .
            '"deactivation-date":null,"verified-email":true,' .
            '"verified-primary-email":true},"person":{"last-modified-date"' .
            ':{"value":' . $date . '},"name":{"created-date":{"value":' . $date .
            '},"last-modified-date":{"value":' . $date . '},"given-names":' .
            '{"value":"' . $givenname . '"},"family-name":{"value":"' . $familyname .
            '"},"credit-name":{"value":"' . $pubname . '"},"source":null,' .
            '"visibility":"PUBLIC","path":"' . $id . '"},"other-names":{' .
            '"last-modified-date":{"value":' . $date . '},"other-name":[{' .
            '"created-date":{"value":' . $date . '},"last-modified-date":{' .
            '"value":' . $date . '},"source":{"source-orcid":{"uri":"' . $uri .
            '","path":"' . $id . '","host":"orcid.org"},"source-client-id":null,' .
            '"source-name":{"value":"' . $pubname . '"}},"content":"' . $othername1 .
            '","visibility":"PUBLIC","path":"/' . $id . '/other-names/952609",' .
            '"put-code":952609,"display-index":2},{"created-date":{"value":' .
            $date . '},"last-modified-date":{"value":' . $date . '},"source":{' .
            '"source-orcid":{"uri":"' . $uri . '","path":"' . $id . '","host":' .
            '"orcid.org"},"source-client-id":null,"source-name":{"value":"' .
            $pubname . '"}},"content":"' . $othername2 . '","visibility":"PUBLIC",' .
            '"path":"/' . $id . '/other-names/952610","put-code":952610,' .
            '"display-index":1}],"path":"/' . $id . '/other-names"},"biography"' .
            ':null,"researcher-urls":{"last-modified-date":null,' .
            '"researcher-url":[],"path":"/' . $id . '/researcher-urls"},"emails"' .
            ':{"last-modified-date":{"value":' . $date . '},"email":[{' .
            '"created-date":{"value":' . $date . '},"last-modified-date":' .
            '{"value":' . $date . '},"source":{"source-orcid":{"uri":"' . $uri .
            '","path":"' . $id . '","host":"orcid.org"},"source-client-id":null,' .
            '"source-name":{"value":"' . $pubname . '"}},"email":"' . $email1 .
            '","path":null,"visibility":"PUBLIC","verified":true,"primary"' .
            ':false,"put-code":null},{"created-date":{"value":' . $date .
            '},"last-modified-date":{"value":' . $date . '},"source":{' .
            '"source-orcid":{"uri":"' . $uri . '","path":"' . $id . '","host":' .
            '"orcid.org"},"source-client-id":null,"source-name":{"value":"' .
            $pubname . '"}},"email":"' . $email2 . '","path":null,"visibility":' .
            '"PUBLIC","verified":true,"primary":true,"put-code":null},{' .
            '"created-date":{"value":' . $date . '},"last-modified-date":{' .
            '"value":' . $date . '},"source":{"source-orcid":{"uri":"' . $uri .
            '","path":"' . $id . '","host":"orcid.org"},"source-client-id"' .
            ':null,"source-name":{"value":"' . $pubname . '"}},"email":"' . $email3 .
            '","path":null,"visibility":"PUBLIC","verified":true,"primary"' .
            ':false,"put-code":null}],"path":"/' . $id . '/email"},"addresses":' .
            '{"last-modified-date":null,"address":[],"path":"/' . $id .
            '/address"},"keywords":{"last-modified-date":null,"keyword":[],' .
            '"path":"/' . $id . '/keywords"},"external-identifiers":{' .
            '"last-modified-date":null,"external-identifier":[],"path":"/' .
            $id . '/external-identifiers"},"path":"/' . $id . '/person"},"path":"/' .
            $id . '"}'
        );

        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(2)
            ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user = $this->provider->getResourceOwner($token);

        $this->assertEquals($uri, $user->getId());
        $this->assertEquals($id, $user->toArray()['orcid-identifier']['path']);
        $this->assertEquals($pubname, $user->getName());
        $this->assertEquals($givenname, $user->getGivenName());
        $this->assertEquals($givenname, $user->getFirstName());
        $this->assertEquals($familyname, $user->getFamilyName());
        $this->assertEquals($familyname, $user->getLastName());
        $this->assertEquals([$othername1, $othername2], $user->getOtherNames());
        $this->assertEquals($email2, $user->getEmail());
        $this->assertEquals($email2, $user->getPrimaryEmail());
        $this->assertEquals([$email1, $email2, $email3], $user->getEmails());
    }

    /**
     * @expectedException League\OAuth2\Client\Provider\Exception\IdentityProviderException
     **/
    public function testExceptionThrownWhenErrorObjectReceived()
    {
        $status = rand(401, 599);
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn(
            '{"error":"mock_error_name","error_description":"mock_error_message"}'
        );
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);
        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(1)
            ->andReturn($postResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    /**
     * @expectedException League\OAuth2\Client\Provider\Exception\IdentityProviderException
     **/
    public function testExceptionThrownWhenUnknownErrorObjectReceived()
    {
        $status = rand(401, 599);
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn(
            '{"error-code":"mock_error_code","developer-message":"' .
            'mock_error_message"}'
        );
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);
        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(1)
            ->andReturn($postResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    /**
     * @expectedException League\OAuth2\Client\Provider\Exception\IdentityProviderException
     **/
    public function testExceptionThrownWnenHTTPErrorStatus()
    {
        $status = rand(401, 599);
        $reason = 'HTTP ERROR';
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('');
        $postResponse->shouldReceive('getHeader')->andReturn([]);
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);
        $postResponse->shouldReceive('getReasonPhrase')->andReturn($reason);
        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(1)
            ->andReturn($postResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }
}
