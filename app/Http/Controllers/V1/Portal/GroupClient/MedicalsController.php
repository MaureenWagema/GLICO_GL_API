<?php

namespace App\Http\Controllers\V1\Portal\GroupClient;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class MedicalsController extends Controller
{
    //
    public function getMembersToGoForMedicals(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $scheme_id = $request->scheme_id;

            //SELECT * FROM glmembersinfo g WHERE g.MedicalsRequired = 1 AND g.SchemeID = 76; --medicals required
            $members = $this->britam_db->table('glmembersinfo as g')
                ->select("g.*")
                ->where('g.MedicalsRequired', 1)
                //>where('g.uw_ind', 1)
                ->where('g.SchemeID', $scheme_id)
                ->get();

            if ($members->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No members to go for medicals',
                    'data' => []
                ], 200);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'Members to go for medicals',
                    'data' => $members
                ], 200);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'An error occurred',
                'data' => $th->getMessage()
            ], 500);
        }
    }

    public function getMembersWhoHaveDoneMedicals(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $scheme_id = $request->scheme_id;

            //SSELECT * FROM glmembersinfo g WHERE g.SchemeID = 76 AND g.medical_results = 1; --medicals received
            // $members = $this->britam_db->table('glmembersinfo as g')
            //     ->select("g.*")
            //     ->where('g.medical_results', 1)
            //     ->where('g.SchemeID', $scheme_id)
            //     ->get();

            $currentDate = date('Y-m-d'); // Assuming you're using MySQL date format

            $members = $this->britam_db->table('glmembersinfo as g')
                ->select('g.*', $this->britam_db->raw("CASE 
                    WHEN DATEDIFF(day, g.MedicalTestDate, '$currentDate') > 1095 THEN 'Expired'
                    ELSE 'Valid'
                    END as IsMedicalValid"))
                ->where('g.medical_results', 1)
                ->where('g.SchemeID', $scheme_id)
                ->get();



            if ($members->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No members who have done medicals',
                    'data' => []
                ], 200);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'Members whose medicals have been received.',
                    'data' => $members
                ], 200);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'An error occurred',
                'data' => $th->getMessage()
            ], 500);
        }
    }

    public function getMemberswhoseMediacalsAreUnderwritten(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $scheme_id = $request->scheme_id;

            //SELECT * FROM glmembersinfo g WHERE g.SchemeID = 76 AND g.medical_results = 1 AND g.uw_ind = 1; --medicals underwritten
            $members = $this->britam_db->table('glmembersinfo as g')
                ->select("g.*")
                ->where('g.medical_results', 1)
                ->where('g.uw_ind', 1)
                ->where('g.SchemeID', $scheme_id)
                ->get();

            if ($members->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No members whose medicals are underwritten',
                    'data' => []
                ], 200);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'Members whose medicals are underwritten.',
                    'count' => $members->count(),
                    'data' => $members
                ], 200);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'An error occurred',
                'data' => $th->getMessage()
            ], 500);
        }
    }

    public function getUWDecisions()
    {
        try {
            $uw_decisions = $this->britam_db->table('glifeuwcodesinfo')->select("ID", "uw_name", "loadfactor", "loadfactorbase")->get();

            if ($uw_decisions->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No underwriting decisions found',
                    'data' => []
                ], 200);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'Underwriting decisions found',
                    'data' => $uw_decisions
                ], 200);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'An error occurred',
                'data' => $th->getMessage()
            ], 500);
        }
    }
}
