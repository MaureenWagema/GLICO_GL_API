<?php

namespace App\Http\Controllers\V1\Portal\GroupClient;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ClaimsController extends Controller
{
    //$notification = $this->sendClaimNotification($claim_request_number, $scheme_id, $member_id, $event_date, $claim_cause_id, $claim_result_id);

    public function sendClaimNotification($claim_request_number, $scheme_id, $member_id, $event_date, $claim_cause_id, $token)
    {
        $claim_team = 'fredrick_ochieng@outlook.com';
        $subject = 'New Claim Request Notification for Scheme: ' . $scheme_id;

        // Create a message string from the claim details
        $message_to_be_sent = "New Claim Request Notification:\n\n" .
            "Scheme ID: $scheme_id\n" .
            "Claim Request Number: $claim_request_number\n" .
            "Member ID: $member_id\n" .
            "Event Date: $event_date\n" .
            "Claim Cause ID: $claim_cause_id\n";

        // Log the notification
        Log::channel('corporate_api')->info('Claim request notification: ' . $message_to_be_sent);

        // Send the email
        return self::britam_email_sending($subject, $message_to_be_sent, $claim_team, $token);
    }


    public function getAllClaimsUnderScheme(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {

            $scheme_id = $request->input('scheme_id');



            $results = $this->britam_db->table('glifeclaimsnotification as p')
                ->select('p.*', 's.description as claim_status', 'g.member_no as Emp_code')
                ->join('ClaimHistoryInfo as h', 'p.id', '=', 'h.GlifeClaim_no')
                ->join('ClaimStatusInfo as s', 'h.statuscode', '=', 's.id')
                ->join('glmembersinfo as g', 'g.MemberId', '=', 'p.MemberIdKey')
                ->where('p.Scheme', $scheme_id)
                ->where('h.Active', 1)
                ->get();

            if (sizeof($results) > 0) {

                return response()->json([
                    'success' => true,
                    'message' => 'Scheme claims fetched successfully',
                    'count' => count($results),
                    'data' => $results,

                ], 200);

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No claims found'
                ], 204);
            }


        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching claims' . $th->getMessage()
            ], 500);
        }
    }

    public function searchClaimantByName(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'scheme_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $search_name = $request->name;
            $scheme_id = $request->scheme_id;

            //confirm if the scheme_id has members

            $client_type = $this->britam_db->table('glifeclientinfo', 'g')
                ->join('polschemeinfo as p', 'p.ClientNumber', '=', 'g.Id')
                //->join('glmembersinfo as gm', 'gm.SchemeID', '=', 'p.schemeID')
                ->where('p.SchemeID', $scheme_id)
                ->select('g.client_type')
                ->first();

            if ($client_type == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No results found.',
                    'data' => []
                ], 400);
                // }

                // if ($client_type->client_type != 1) {
                //     return response()->json([
                //         'success' => false,
                //         'message' => 'Scheme is not for a corporate institution.'
                //     ], 400);
            } else {
                $results = $this->britam_db->table('glifeclaimsnotification')
                    ->select("*")
                    ->where("SchemeName", $scheme_id)
                    ->where("claimant", 'LIKE', '%' . $search_name . '%')
                    ->get();

                if (sizeof($results) > 0) {

                    return response()->json([
                        'success' => true,
                        'message' => 'Claimants fetched successfully',
                        'count' => count($results),
                        'data' => $results

                    ], 200);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'No members found'
                    ], 404);
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data' . $th->getMessage()
            ], 500);
        }
    }

    public function getRequiredDocuments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'claim_type_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {

            $claim_type = $request->claim_type_id;

            //SELECT c.*, ct.description from claimtyperequirementinfo c
            // INNER JOIN claim_requirement ct ON ct.reg_code = c.req_code
            // WHERE c.claim_type = 'NAT';

            $results = $this->britam_db->table('claimtyperequirementinfo as c')
                ->join('claim_requirement as ct', 'ct.reg_code', '=', 'c.req_code')
                ->where('c.claim_type', $claim_type)
                ->where('c.PortalRequiredDocument', 1)
                ->select('c.*', 'ct.description')
                ->get();


            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Required documents fetched successfully',
                    'count' => count($results),
                    'data' => $results

                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No required documents found'
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data' . $th->getMessage()
            ], 500);
        }
    }

    //get scheme benefits
    public function getSchemeBenefit(Request $request)
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
            //select s.id, s.Description, s.ClaimType, s.IsMainBenefit from SchemeBenefitConfig s where s.SchemeID = 1
            $results = $this->britam_db->table('SchemeBenefitConfig', 's')
                ->where('s.SchemeID', $scheme_id)
                ->select('s.id', 's.Description', 's.ClaimType', 's.IsMainBenefit')
                ->get();

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Scheme benefits fetched successfully',
                    'count' => count($results),
                    'data' => $results

                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No scheme benefits found'
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data' . $th->getMessage()
            ], 500);
        }
    }

    public function submitClaimRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required',
            'member_id' => 'required',
            'event_date' => 'required|date', // 'date_format:Y-m-d H:i:s
            'claim_cause_id' => 'nullable',
            'ClaimReason' => 'nullable',
            'scheme_benefit_id' => 'required',
            //'claim_type_id' => 'required',
            'portal_user_id' => 'required',
            'death_cert_number' => 'string',
            'death_entry_number' => 'string',
            'burial_permit_number' => 'string',
            'id_number' => 'string',
            'birth_date' => 'date',
            'birth_cert_number' => 'string',
            'birth_notifiacation_number' => 'string',
            'beneficiary' => 'array',
            'beneficiary.*.is_institution' => 'required|boolean',
            'beneficiary.*.name' => 'string',
            'beneficiary.*.date_of_birth' => 'date',
            'beneficiary.*.relationship' => 'string',
            'beneficiary.*.id_number' => 'string',
            'beneficiary.*.mobile_number' => 'string',
            'beneficiary.*.payment_method' => 'string',
            'beneficiary.*.bank' => 'string',
            'beneficiary.*.bank_branch' => 'string',
            'beneficiary.*.bank_account_number' => 'string',
            'beneficiary.*.kra_pin_no' => 'string',
            'beneficiary.*.guardian_surname' => 'string',
            'beneficiary.*.guardian_other_names' => 'string',
            'beneficiary.*.guardian_telephone' => 'string',
            'beneficiary.*.guardian_email' => 'email',
            'beneficiary.*.perc_alloc' => 'string',
            'dependant' => 'array',
            'dependant.*.name' => 'string',
            'dependant.*.relationship' => 'string',
            'dependant.*.date_of_birth' => 'date',
            'dependant.*.id_number' => 'string',
            'files' => 'array',
            'files.*' => 'file',
            'paymentOptions' => 'nullable',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {

            // use transaction to ensure that the claim request is submitted only if the member is found in the scheme
            $scheme_id = (int) $request->scheme_id;
            $member_id = (int) $request->member_id;
            $scheme_benefit_id = $request->scheme_benefit_id;
            $claim_cause_id = $request->claim_cause_id;
            $event_date = $request->event_date;
            $pay_to_beneficiary = $request->pay_to_beneficiary;
            $pay_to_scheme = $request->pay_to_scheme;
            $pay_to_ufaa = $request->pay_to_ufaa;//
            $paymentOptions = $request->paymentOptions;

            //one of them must be true for the others to remain false
            if ($paymentOptions == 1) {///pay principle member
                $pay_to_ufaa = false;
                $pay_to_scheme = false;
                $pay_to_beneficiary = false;
            } else if ($paymentOptions == 2) {//pay beneficiary
                $pay_to_beneficiary = true;
                $pay_to_ufaa = false;
                $pay_to_scheme = false;
            } else if ($paymentOptions == 3) {//pay scheme
                $pay_to_scheme = true;
                $pay_to_beneficiary = false;
                $pay_to_ufaa = false;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select the payment destination'
                ], 400);
            }


            $member = $this->britam_db->table('glmembersinfo', 'g')
                ->join('polschemeinfo as p', 'p.schemeID', '=', 'g.SchemeID')
                ->where('p.schemeID', $scheme_id)
                ->where('g.MemberId', $member_id)
                ->select('g.MemberId')
                ->first();

            if ($member == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member not found in the scheme.'
                ], 400);
            } else {

                //transaction
                $this->britam_db->beginTransaction();

                //insert claim request
                $notification_date = now();
                $claim_request_number = $this->generateRequestNumber(false, true, $scheme_id);

                //DeathCertNo, DeathEntryNo, BurialPermitNumber, IDNumber, BirthCertNumber, BirthNotificationNumber
                $claim_result_id = $this->britam_db->table('ClaimRequest')->insertGetId([
                    'ContactPerson' => $request->portal_user_id,
                    'ClaimRequestNumber' => $claim_request_number,
                    'ClaimReasons' => $request->ClaimReason,
                    'SchemeID' => $scheme_id,
                    'Member' => $member_id,
                    'SchemeBenefit' => $scheme_benefit_id,
                    'Status' => 1,
                    'EventDate' => $event_date,
                    'ClaimCause' => $claim_cause_id,
                    'NotificationDate' => $notification_date,
                    'DeathCertNo' => $request->death_cert_number ?? null,
                    'DeathEntryNo' => $request->death_entry_number ?? null,
                    'BurialPermitNumber' => $request->burial_permit_number ?? null,
                    'IDNumber' => $request->id_number ?? null,
                    'BirthDate' => $request->birth_date ?? null,
                    'BirthCertNumber' => $request->birth_cert_number ?? null,
                    'BirthNotificationNumber' => $request->birth_notifiacation_number ?? null,
                    'created_by' => 'API',
                    'created_on' => date('Y-m-d H:i:s'),
                    'Rank' => $request->Rank ?? null,
                    'Station' => $request->Station ?? null,
                    'Age' => $request->Age ?? null,
                    'Gender' => $request->Gender ?? null,
                    'LastWorkingDate' => $request->LastWorkingDate ?? null,
                    'PlaceOfDeath' => $request->PlaceOfDeath ?? null,
                    'CauseOfDeath' => $request->CauseOfDeath ?? null
                ]);

                $claim_payment_result_id = null;

                // get the id of the claim payment record where the pay_to_beneficiary, pay_to_scheme and pay_to_ufaa are equal to the request values
                if ($pay_to_beneficiary == true) {
                    // get the id of the claim payment record where the pay_to_beneficiary is true
                    $claim_payment_result_id = $this->britam_db->table('ClaimPayment')->where('PayBeneficiary', true)->first()->Id;

                } else if ($pay_to_scheme == true) {
                    // get the id of the claim payment record where the pay_to_scheme is true
                    $claim_payment_result_id = $this->britam_db->table('ClaimPayment')->where('PayScheme', true)->first()->Id;

                } else if ($pay_to_ufaa == true) {
                    // get the id of the claim payment record where the pay_to_ufaa is true
                    $claim_payment_result_id = $this->britam_db->table('ClaimPayment')->where('PayUFAA', true)->first()->Id;

                }

                $this->britam_db->table('ClaimRequest')->where('Id', $claim_result_id)->update(['ClaimPayment' => $claim_payment_result_id]);


                //process dependants
                if (isset($request['dependant']) && is_array($request['dependant'])) {
                    foreach ($request['dependant'] as $dependant) {
                        $dependant_result_id = $this->britam_db->table('ClaimDependants')->insertGetId([
                            'ClaimRequestId' => $claim_result_id,
                            'Name' => $dependant['name'] ?? null,
                            'Relation' => $dependant['relationship'] ?? null,
                            'dob' => $dependant['date_of_birth'] ?? null,
                            'IDNumber' => $dependant['id_number'] ?? null,
                            'created_by' => 'API',
                            'created_on' => date('Y-m-d H:i:s')
                        ]);
                    }

                }
                //process beneficiaries
                // make sure the perc_alloc limit of 100 has not been reached for the beneficiaries
                $available_perc_alloc = 100;

                if (isset($request['beneficiary']) && is_array($request['beneficiary'])) {
                    foreach ($request['beneficiary'] as $beneficiary) {
                        $available_perc_alloc -= $beneficiary['perc_alloc'];
                        if ($available_perc_alloc < 0) {
                            return response()->json([
                                'success' => false,
                                'message' => 'The total percentage allocation for the beneficiaries exceeds 100%'
                            ], 400);
                        }

                        $beneficiary_result_id = $this->britam_db->table('ClaimBeneficiaries')->insertGetId([
                            'ClaimRequestId' => $claim_result_id,
                            'Names' => $beneficiary['name'] ?? null,
                            'DateOfBirth' => $beneficiary['date_of_birth'] ?? null,
                            'relationship' => $beneficiary['relationship'] ?? null,
                            'IdNumber' => $beneficiary['id_number'] ?? null,
                            'MobileNumber' => $beneficiary['mobile_number'] ?? null,
                            'PayMethod' => $beneficiary['payment_method'] ?? null,
                            'Bank' => $beneficiary['bank'] ?? null,
                            'BankBranch' => $beneficiary['bank_branch'] ?? null,
                            'BankAccount' => $beneficiary['bank_account_number'] ?? null,
                            'KRAPIN' => $beneficiary['kra_pin_no'] ?? null,
                            'GuardianSurname' => $beneficiary['guardian_surname'] ?? null,
                            'GuardianOtherNames' => $beneficiary['guardian_other_names'] ?? null,
                            'GuardianTelephone' => $beneficiary['guardian_telephone'] ?? null,
                            'GuardianEmail' => $beneficiary['guardian_email'] ?? null,
                            'IsInstitution' => $beneficiary['is_institution'] ?? 0,
                            'perc_alloc' => $beneficiary['perc_alloc'] ?? null,
                            'created_by' => 'API',
                            'created_on' => date('Y-m-d H:i:s')
                        ]);
                    }
                }

                //dd($request->all());

                if ($request->hasFile('files')) {

                    $fileUploadData = $request->file('files');

                    // Save each file in a separate row
                    foreach ($fileUploadData as $code => $fileData) {

                        if (!$fileData->isValid()) {
                            // Handle invalid file
                            return response()->json([
                                'success' => false,
                                'message' => 'Invalid file: ' . $fileData->getClientOriginalName(),
                            ], 400);
                        }

                        $filename = 'file_' . $code . '_' . time() . '.' . $fileData->getClientOriginalExtension() ?? 'pdf';

                        $storedFilePath = $fileData->storeAs('claim_documents', $filename, 'public_documents');
                        $storedFilePath = Storage::disk('public_documents')->putFileAs('claim_documents', $fileData, $filename);
                        $file_path = Storage::disk('public_documents')->path($storedFilePath);

                        //ftp settings
                        //Storage::disk('ftp')->putFileAs($fileData, $filename);
                        //$fullFileUrl = Storage::disk('ftp')->url($filename);
                        //$file_path = Storage::disk('ftp')->path($filename);

                        //SELECT p.description FROM claim_requirement p WHERE p.reg_code = '00002';
                        $code_description = $this->britam_db->table('claim_requirement')->select('description')->where('reg_code', $code)->first()->description;
                        $document_type = $this->britam_db->table('GroupLifeFileCategories')->select('ID')->where('IsClaimDocument', true)->first();

                        $this->britam_db->table('ClaimDocuments')->insert([
                            'ClaimRequest' => $claim_result_id,
                            'DocumentType' => $document_type->ID ?? 1,
                            'ClaimDocumentType' => $code,
                            'DocumentCode' => $code,
                            'Description' => $code_description ?? null,
                            'FileName' => $filename,
                            'FullFilePath' => $file_path,
                            'IsMandatory' => 1,
                            'created_by' => 'API',
                            'created_on' => date('Y-m-d H:i:s')
                        ]);
                    }
                }

                // $token = $request->bearerToken();
                // // send notification to the claims team
                // $notification = $this->sendClaimNotification($claim_request_number, $scheme_id, $member_id, $event_date, $claim_cause_id, $token);

                // if ($notification == false) {
                //     return response()->json([
                //         'success' => false,
                //         'message' => 'Error submitting claim request'
                //     ], 500);
                // }

                $this->britam_db->commit();

                if ($claim_result_id != null) {

                    return response()->json([
                        'success' => true,
                        'message' => 'Claim request submitted successfully',
                        'data' => [
                            'claim_request_id' => $claim_result_id,
                            'notification_date' => $notification_date,
                            'claim_request_number' => $claim_request_number
                        ]
                    ], 200);
                } else {
                    $this->britam_db->rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Error submitting claim request'
                    ], 500);
                }
            }

        } catch (\Throwable $th) {

            $this->britam_db->rollBack();
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error submitting claim request' . $th->getMessage()
            ], 500);
        }
    }

    public function getClaimRequests(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {

            //confirm if it is a corporate scheme
            $scheme_id = $request->scheme_id;

            // $results = $this->britam_db->table('ClaimRequest', 'c')
            //     ->join('glmembersinfo as g', 'g.MemberId', '=', 'c.Member')
            //     ->join('claims_types as cl', 'cl.claim_type', '=', 'c.ClaimType')
            //     ->join('polschemeinfo as p', 'p.schemeID', '=', 'c.SchemeID')
            //     ->where('c.SchemeID', $scheme_id)
            //     ->select('cl.Description', 'c.Status', 'g.Names', 'c.EventDate', 'c.NotificationDate', 'c.ClaimCause', 'p.schemeID', 'p.policy_no')
            //     ->get();

            //SELECT DISTINCT gcl.Description, ed.Description AS RequestStatus, g.Names, c.EventDate, c.NotificationDate, cs.Description AS ClaimCause, p.schemeID, p.policy_no, gc.claim_no,
            // CASE WHEN csi.description IS NULL THEN 'PENDING' ELSE csi.description END AS ClaimStatus
            // FROM ClaimRequest AS c
            // JOIN EndorsementRequestStatus ed ON ed.Id = c."Status"
            // JOIN glmembersinfo AS g ON g.MemberId = c.Member
            // LEFT JOIN SchemeBenefitConfig gcl ON gcl.id = c.SchemeBenefit
            // left JOIN claims_types AS cl ON cl.claim_type = c.ClaimType
            // JOIN polschemeinfo AS p ON p.schemeID = c.SchemeID
            // LEFT JOIN claimcausesinfo cs ON cs.claim_cause_code = c.ClaimCause
            // LEFT JOIN glifeclaimsnotification gc ON gc.MemberIdKey = g.MemberId
            // LEFT JOIN ClaimHistoryInfo ch ON gc.id = ch.GrpClaimno
            // LEFT JOIN ClaimStatusInfo csi ON ch.statuscode = csi.id
            // WHERE c.SchemeID = 61;

            // use the above query to get the claim requests
            $results = $this->britam_db->table('ClaimRequest as c')
                ->select(
                    'gcl.Description',
                    'ed.Description as RequestStatus',
                    'g.Names',
                    'c.EventDate',
                    'c.NotificationDate',
                    'cs.Description as ClaimCause',
                    'p.schemeID',
                    'p.policy_no',
                    'gc.claim_no',
                    $this->britam_db->raw("CASE WHEN csi.description IS NULL THEN 'PENDING' ELSE csi.description END AS ClaimStatus")
                )
                ->join('EndorsementRequestStatus as ed', 'ed.Id', '=', 'c.Status')
                ->join('glmembersinfo as g', 'g.MemberId', '=', 'c.Member')
                ->leftJoin('SchemeBenefitConfig as gcl', 'gcl.id', '=', 'c.SchemeBenefit')
                ->leftJoin('claims_types as cl', 'cl.claim_type', '=', 'c.ClaimType')
                ->join('polschemeinfo as p', 'p.schemeID', '=', 'c.SchemeID')
                ->leftJoin('claimcausesinfo as cs', 'cs.claim_cause_code', '=', 'c.ClaimCause')
                ->leftJoin('glifeclaimsnotification as gc', 'gc.MemberIdKey', '=', 'g.MemberId')
                ->leftJoin('ClaimHistoryInfo as ch', 'gc.id', '=', 'ch.GrpClaimno')
                ->leftJoin('ClaimStatusInfo as csi', 'ch.statuscode', '=', 'csi.id')
                ->where('c.SchemeID', $scheme_id)
                ->distinct()
                ->get();


            // $results = $this->britam_db->table('ClaimRequest as c')
            //     ->select('cl.Description', 'ed.Description as Status', 'g.Names', 'c.EventDate', 'c.NotificationDate', 'cs.Description as ClaimCause', 'p.schemeID', 'p.policy_no', 'gc.claim_no')
            //     ->join('EndorsementRequestStatus as ed', 'ed.Id', '=', 'c.Status')
            //     ->join('glmembersinfo as g', 'g.MemberId', '=', 'c.Member')
            //     ->leftJoin('SchemeBenefitConfig as cl', 'cl.id', '=', 'c.ClaimType')
            //     ->join('polschemeinfo as p', 'p.schemeID', '=', 'c.SchemeID')
            //     ->leftJoin('claimcausesinfo as cs', 'cs.claim_cause_code', '=', 'c.ClaimCause')
            //     ->leftJoin('glifeclaimsnotification as gc', 'gc.MemberIdKey', '=', 'g.MemberId')
            //     ->where('c.SchemeID', $scheme_id)
            //     ->distinct()
            //     ->get();


            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Claim requests fetched successfully',
                    'count' => count($results),
                    'data' => $results

                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No claim requests found'
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error uploading claim request document' . $th->getMessage()
            ], 500);
        }
    }

    public function uploadDocumentsToClaimRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'documents' => 'array',
            'documents.*.doc_code' => 'required|string',
            'documents.*.description' => 'required|numeric',
            'documents.*.is_mandatory' => 'required|boolean',
            'documents.*.filename' => 'required|numeric',
            'documents.*.file_path' => 'required|string',
            'claim_request_id' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $description = $request->description;
            $is_mandatory = $request->is_mandatory;
            $filename = $request->filename;
            $claim_request_id = $request->claim_request_id;

            $slamsPath = $this->britam_db->table('ComapanyInfo')->select('PortalClaimDocsPath')->first();
            $filePath = $slamsPath->PortalClaimDocsPath;
            $fullFilePath = $filePath . $filename;


            $result_id = $this->britam_db->table('ClaimRequestDocuments')->insertGetId([
                'Description' => $description,
                'IsMandatory' => $is_mandatory,
                'FileName' => $filename,
                'FullFilePath' => $fullFilePath,
                'ClaimRequest' => $claim_request_id
            ]);

            if ($result_id != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Claim request document uploaded successfully',
                    'data' => $result_id
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error uploading claim request document'
                ], 500);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error uploading claim request document' . $th->getMessage()
            ], 500);
        }
    }

    public function getClaimStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'claim_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {

            $claim_number = $request->claim_number;

            //SELECT p.id,p.claim_no,p.claim_type,h.statuscode,s.description, p.statusCode FROM glifeclaimsnotification p 
            // INNER JOIN ClaimHistoryInfo h ON p.id=h.GrpClaimno AND h.Active=1
            // INNER JOIN ClaimStatusInfo s ON h.statuscode=s.id
            // WHERE p.claim_no='GL/HL/1110/2023';


            $results = $this->britam_db->table('glifeclaimsnotification as p')
                ->join('ClaimHistoryInfo as h', 'p.id', '=', 'h.GrpClaimno')
                ->join('ClaimStatusInfo as s', 'h.statuscode', '=', 's.id')
                ->where('p.claim_no', $claim_number)
                //->where('h.Active', 1)
                ->select('p.id', 'p.claim_no', 'p.claim_type', 'h.statuscode', 's.description', 'p.statusCode')
                ->orderBy('h.id', 'desc')
                //select top 1
                //->first();
                ->get();


            if (!($results->isEmpty())) {

                $result = $results->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Claim status fetched successfully',
                    'count' => count($results),
                    'data' => $result

                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No claim status found'
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching claim status' . $th->getMessage()
            ], 500);
        }
    }
}
