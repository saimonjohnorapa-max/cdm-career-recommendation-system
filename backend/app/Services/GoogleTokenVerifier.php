<?php

namespace App\Services;

use App\Exceptions\GoogleVerificationUnavailable;
use Google\Auth\AccessToken;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

class GoogleTokenVerifier
{
    public function __construct(private ?ClientInterface $client = null)
    {
    }

    public function verify(string $token): ?array
    {
        $clientId = (string) config('services.google.client_id');
        if ($clientId === '' || $clientId === 'your_google_client_id') {
            throw new GoogleVerificationUnavailable('Google sign-in is not configured. Please contact the portal administrator.');
        }

        try {
            // Retain TLS verification. The local Windows bundle comes from the
            // machine's trusted certificate store, never from the login request.
            $client = $this->client ?? new Client([
                'verify' => config('services.google.ca_bundle') ?: true,
                'connect_timeout' => 5,
                'timeout' => 15,
            ]);
            $verifier = new AccessToken(fn ($request, $options = []) => $client->send($request, $options));
            $claims = $verifier->verify($token, ['audience' => $clientId, 'throwException' => false]);
            return is_array($claims) ? $claims : null;
        } catch (\DomainException $exception) {
            // The JWT library throws for malformed JSON/base64 in a credential.
            return null;
        } catch (\Throwable $exception) {
            // Certificate/network failures are service failures, not invalid accounts.
            throw new GoogleVerificationUnavailable('Google sign-in is temporarily unavailable. Please try again shortly or contact the portal administrator.', 0, $exception);
        }
    }
}
