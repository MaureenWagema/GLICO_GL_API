<?php

namespace App\Http\Controllers\V1\Portal\GroupClient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class ReportsController extends Controller
{
    public function getReportsLaravel(Request $request)
    {
        try {
            $results = [];
            // $reference = $request->input('SchemeID');
            // $settings = $request->input('settings');
            // $schemeID = $request->input('SchemeID');
            // $fundYear = $request->input('fund_year');
            $reference = $request->input('SchemeID');
            $settings = $request->input('settings');
            $schemeID = $request->input('SchemeID');
            $fundYear = $request->input('fund_year');
             $url_path = 'https://localhost:44345/api/Report/Report/';
          //  $url_path = 'https://localhost:44345/api/Report/Report/';
       //     $url_path = $request->input('https://localhost:44345/api/Report/Report');
            $client = new Client([
                'verify' => false, 
            ]);
    
            $response = $client->post($url_path, [
                'json' => [
                    'reference' => $reference,
                    'settings' => $settings,
                    'SchemeID' => $schemeID,
                    'fund_year' => $fundYear
                ],
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);

            if ($response->getStatusCode() == 200) {
                $results = $response->getBody()->getContents();
            }   

            return response($results, 200)->header('Content-Type', 'application/json');
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching report: ' . $th->getMessage()
            ], 500);
        }
    }

    public function getEndorsementProcessingResults(Request $request)
    {
        $request_id = $request->input('request_id');

        if (!$request_id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request id'
            ], 400);
        }

        $base_url = config('processes.base_url');
        $endpoint = config('processes.automation_processes.endorsement_processing.endpoints.automate_endorsements');
        $url = $base_url . $endpoint . '?request_id=' . $request_id;

        $client = new Client(['verify' => false]);
        $response = $client->post($url);

        if ($response->getStatusCode() == 200) {
            $results = $response->getBody()->getContents();

            return response()->json([
                'success' => true,
                'message' => 'Endorsement request processed successfully',
                'data' => $results
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Error processing endorsement request'
            ], 500);
        }
    }

    public function getClaimsProcessingResults(Request $request)
    {
        $claim_request_id = $request->input('claim_request_id');

        if (!$claim_request_id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request id'
            ], 400);
        }

        $base_url = config('processes.base_url');
        $endpoint = config('processes.automation_processes.claims_processing.endpoints.automate_claims');
        $url = $base_url . $endpoint . '?claim_request_id=' . $claim_request_id;

        $client = new Client(['verify' => false]);
        $response = $client->post($url);

        if ($response->getStatusCode() == 200) {
            $results = $response->getBody()->getContents();

            return response()->json([
                'success' => true,
                'message' => 'Claim request processed successfully',
                'data' => $results
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Error processing claim request'
            ], 500);
        }
    }

    public function postRaisedDebitsToGL(Request $request)
    {
        $scheme_id = $request->input('scheme_id');

        if (!$scheme_id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid scheme id'
            ], 400);
        }

        $does_it_exist = $this->britam_db->table('polschemeinfo')->where('schemeID', $scheme_id)->exists();

        if (!$does_it_exist) {
            return response()->json([
                'success' => false,
                'message' => 'Scheme does not exist'
            ], 400);
        }

        $base_url = config('processes.base_url');
        $endpoint = config('processes.automation_processes.debits_processing.endpoints.automate_policies');
        $url = $base_url . $endpoint . '?scheme_id=' . $scheme_id;

        $client = new Client(['verify' => false]);
        $response = $client->post($url);

        if ($response->getStatusCode() == 200 || $response->getStatusCode() == 207) {
            $results = $response->getBody()->getContents();

            return response()->json([
                'success' => true,
                'message' => 'Debit request processed successfully',
                'data' => $results
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Error processing debit request'
            ], 500);
        }
    }

    //recalculate scheme balance
    public function recalculateSchemeBalance(Request $request)
    {
        $scheme_id = $request->input('scheme_id');

        if (!$scheme_id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid scheme id'
            ], 400);
        }

        $does_it_exist = $this->britam_db->table('polschemeinfo')->where('schemeID', $scheme_id)->exists();

        if (!$does_it_exist) {
            return response()->json([
                'success' => false,
                'message' => 'Scheme does not exist'
            ], 400);
        }

        $base_url = config('processes.base_url');
        $endpoint = config('processes.automation_processes.recalculate_scheme_balance.endpoints.recalculate_scheme_balance');
        $url = $base_url . $endpoint . '?scheme_id=' . $scheme_id;

        $client = new Client(['verify' => false]);
        $response = $client->post($url);

        if ($response->getStatusCode() == 200 || $response->getStatusCode() == 207) {
            $results = $response->getBody()->getContents();

            return response()->json([
                'success' => true,
                'message' => 'Scheme balance recalculated successfully',
                'data' => $results
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Error recalculating scheme balance'
            ], 500);
        }
    }
}
