<?php

namespace App\Http\Controllers\V1\Portal\Brokers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class BrokersController extends Controller
{
    //

    // Get all brokers

    public function getBrokers(Request $request)
    {
        try {
            //code...

            //SELECT *
            // FROM Intermediaryinfo i
            // WHERE i.acctype = '002';

            $brokers = $this->britam_db->table('Intermediaryinfo')
                ->where('acctype', '002')
                ->get();

            if ($brokers == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No results found.',
                    'data' => []
                ], 400);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'Brokers found.',
                    'data' => $brokers
                ], 200);
            }

        } catch (\Throwable $th) {
            //throw $th;

            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'data' => []
            ], 500);
        }
    }

    // get schemes tied to a broker

    public function getSchemesUnderBroker(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'broker_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $broker_id = $request->broker_id;

            // SELECT *
            // FROM polschemeinfo p
            // INNER JOIN intermediaryinfo a ON a.id = p.interm_ID
            // WHERE p.acctype = '002' AND p.interm_ID = 24;

            $schemes = $this->britam_db->table('polschemeinfo')
                ->join('intermediaryinfo', 'intermediaryinfo.id', '=', 'polschemeinfo.interm_ID')
                ->where('polschemeinfo.acctype', '002')
                ->where('polschemeinfo.interm_ID', $broker_id)
                ->select('polschemeinfo.*', \DB::raw("CASE 
                            WHEN polschemeinfo.SchemeDescription IS NOT NULL THEN CONCAT(polschemeinfo.SchemeDescription, ' - ', CAST(polschemeinfo.policy_no AS VARCHAR(255)))
                            WHEN polschemeinfo.CompanyName IS NOT NULL THEN CONCAT(polschemeinfo.CompanyName, ' - ', CAST(polschemeinfo.policy_no AS VARCHAR(255)))
                            ELSE CAST(polschemeinfo.policy_no AS VARCHAR(255))
                        END AS PolicyCompany"))
                ->get();

            if ($schemes == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No results found.',
                    'data' => []
                ], 400);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'Schemes found.',
                    'count' => count($schemes),
                    'data' => $schemes
                ], 200);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function getBrokersCommission(Request $request)
    {
        //broker_id
        $validator = Validator::make($request->all(), [
            'broker_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            //SELECT * FROM commissionschedule c
            // INNER JOIN glforequisition g ON g.Requistion_no = c.requisition_no
            // WHERE c.acc_type = '002' AND c.intermediary = 27;

            $broker_id = $request->broker_id;

            $commissions = $this->britam_db->table('commissionschedule')
                ->join('glforequisition', 'glforequisition.Requistion_no', '=', 'commissionschedule.requisition_no')
                ->where('commissionschedule.acc_type', '002')
                ->where('commissionschedule.intermediary', $broker_id)
                ->select('commissionschedule.*', 'glforequisition.*')
                ->get();

            if ($commissions == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No results found.',
                    'data' => []
                ], 400);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'Commissions found.',
                    'count' => count($commissions),
                    'data' => $commissions
                ], 200);
            }


        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    // get broker details from broker id
    public function getBrokerInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'broker_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            //code...

            $broker_id = $request->broker_id;

            // select * from intermediaryinfo i where i.id = 27;

            $broker = $this->britam_db->table('intermediaryinfo')
                ->where('id', $broker_id)
                ->first();

            if ($broker == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No results found.',
                    'data' => []
                ], 400);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'Broker found.',
                    'data' => $broker
                ], 200);
            }


        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    // contact persons under a broker

    public function getBrokerContactPersons(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'broker_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            //code...

            $broker_id = $request->broker_id;

            //SELECT * from contactpersoninfo c WHERE c.Intermediary = 27;

            $contact_persons = $this->britam_db->table('contactpersoninfo')
                ->where('Intermediary', $broker_id)
                ->get();

            if ($contact_persons == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No results found.',
                    'data' => []
                ], 400);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'Contact persons found.',
                    'count' => count($contact_persons),
                    'data' => $contact_persons
                ], 200);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
