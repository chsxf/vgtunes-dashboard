<?php

final class SpotifyHelpers
{
    public static function fetchAccessToken(string $clientId, string $clientSecret): string
    {
        $postFields = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret
        ]);

        $ch = curl_init('https://accounts.spotify.com/api/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true
        ]);
        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception($error);
        }
        curl_close($ch);

        $decodedJson = json_decode($result, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
        return $decodedJson['access_token'];
    }
}
