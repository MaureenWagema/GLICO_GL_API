<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class Azure_AD_AuthController extends Controller
{
    //
    // get token from https://login.microsoftonline.com/169053ef-20fe-4b73-acf5-e2a0002f002f/oauth2/v2.0/token
    public function azure_ad_token_request(Request $request)
    {

        //store in the env and retrieve them from there
        // $client_ref = env('AZURE_CLIENT_REF');
        // $client_secret = env('AZURE_CLIENT_SECRET');
        // $tenant_id = env('AZURE_TENANT_ID');

        $client_ref = '816aa457-d258-4526-9e5c-7b68e9578569'; //$request->client_ref;
        $client_secret = 'AgR8Q~3xBPUVWf82H9KuT~POeb79kGyscxT9ycK5';
        $tenant_id = 'e303f219-75ef-479a-b23c-35ac9479a8ce';

        //error handling
        if (!$client_ref || !$client_secret || !$tenant_id) {
            return response()->json([
                'success' => 'false',
                'message' => 'Please provide the required credentials',
            ], 400);
        }


        $http = new \GuzzleHttp\Client;

        $headers = [
            'Ocp-Apim-Subscription-Key' => '12eab6d7b2b248a3b7f5fa0256884b2b',
            'Cache-Control' => 'no-cache',
        ];

        $response = $http->post('https://brtgw.britam.com/api/auth/login/', [
            'headers' => $headers,
            'form_params' => [
                'tenant_id' => $tenant_id,
                'client_ref' => $client_ref,
                'client_sec' => $client_secret,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }
}
