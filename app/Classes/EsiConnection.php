<?php

namespace App\Classes;

use Ixudra\Curl\Facades\Curl;
use Seat\Eseye\Configuration;
use Seat\Eseye\Containers\EsiAuthentication;
use Seat\Eseye\Eseye;
use App\User;

/**
 * Generic class for use by controllers or queued jobs that need to request information
 * from the ESI API.
 */
class EsiConnection
{
    /**
     * Eseye objects for performing all ESI requests
     *
     * @var Eseye[]
     */
    private $connections = [];

    /**
     * @param null|int $userId
     * @return Eseye
     * @throws \Exception
     */
    public function getConnection($userId = null)
    {
        $userId = $userId === null ? 0 : (int) $userId;

        if (! isset($this->connections[$userId])) {
            $this->connections[$userId] = $this->createConnection($userId);
        }

        return $this->connections[$userId];
    }

    /**
     * @param int $userId
     * @return int
     * @throws \Exception
     */
    public function getCorporationId($userId)
    {
        // Retrieve the user's character details.
        $character = $this->getConnection()->invoke('get', '/characters/{character_id}/', [
            'character_id' => $userId,
        ]);

        return $character->corporation_id;
    }

    /**
     * @return array
     */
    public function getPrimeUserIds()
    {
        $userIds = [];
        foreach (explode(',', env('ESI_PRIME_USER_IDS')) as $userId) {
            $userId = (int) trim($userId);
            if ($userId > 0) {
                $userIds[] = $userId;
            }
        }

        if (count($userIds) === 0) {
            // fallback to old variable
            if ((int) env('ESI_PRIME_USER_ID') > 0) {
                $userIds[] = env('ESI_PRIME_USER_ID');
            }
        }

        return array_unique($userIds);
    }

    /**
     * @param int $corporationId
     * @return int|null
     */
    public function getPrimeUserOfCorporation($corporationId)
    {
        foreach ($this->getPrimeUserIds() as $userId) {
            try {
                $corpId = $this->getCorporationId($userId);
            } catch (\Exception $e) {
                continue;
            }
            if ($corpId == $corporationId) {
                return $userId;
            }
        }

        return null;
    }

    /**
     * Create an ESI API object with or without access token to handle all requests.
     *
     * @param int $userId
     * @return Eseye
     * @throws \Exception
     */
    private function createConnection($userId = 0)
    {
        // Eseye configuration for all connections
        $configuration = Configuration::getInstance();
        $configuration->datasource = env('ESEYE_DATASOURCE', 'tranquility');
        $configuration->logfile_location = storage_path() . '/logs';

        $authentication = null;
        if ($userId > 0) {
            // Create authentication with app details and refresh token from nominated prime user.
            $user = User::where('eve_id', $userId)->first();
            if ($user === null) {
                throw new \Exception('User '. $userId .' not found.');
            }

            $url = 'https://login.eveonline.com/oauth/token';
            $secret = env('EVEONLINE_CLIENT_SECRET');
            $client_id = env('EVEONLINE_CLIENT_ID');

            // If we are running on Sisi, override the oauth location and client information.
            if (env('ESEYE_DATASOURCE', 'tranquility') == 'singularity')
            {
                $url = 'https://sisilogin.testeveonline.com/oauth/token';
                $secret = env('TESTEVEONLINE_CLIENT_SECRET');
                $client_id = env('TESTEVEONLINE_CLIENT_ID');
            }

            // Need to request a new valid access token from EVE SSO using the refresh token of the original request.
            $response = Curl::to($url)
                ->withData(array(
                    'grant_type' => "refresh_token",
                    'refresh_token' => $user->refresh_token
                ))
                ->withHeaders(array(
                    'Authorization: Basic ' . base64_encode($client_id . ':' . $secret)
                ))
                //->enableDebug('logFile.txt')
                ->post();
            $new_token = json_decode($response);
            if (isset($new_token->refresh_token)) {
                $user->refresh_token = $new_token->refresh_token;
                $user->save();
            }

            $authentication = new EsiAuthentication([
                'secret'        => $secret,
                'client_id'     => $client_id,
                'access_token'  => isset($new_token->access_token) ? $new_token->access_token : null,
                'refresh_token' => $user->refresh_token,
                'scopes'        => [
                    'esi-industry.read_corporation_mining.v1',
                    'esi-wallet.read_corporation_wallets.v1',
                    'esi-mail.send_mail.v1',
                    'esi-universe.read_structures.v1',
                    'esi-corporations.read_structures.v1',
                ],
                'token_expires' => isset($new_token->expires_in) ?
                    date('Y-m-d H:i:s', time() + $new_token->expires_in) :
                    null,
            ]);
        }

        // Create ESI API object.
        return new Eseye($authentication);
    }
}
