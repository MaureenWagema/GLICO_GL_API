<?php

namespace App\Http\Controllers\V1\Portal\GroupClient;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class EmailController extends Controller
{
    // to be used when resetting password
    public function sendOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $email = $request->email;

            $client_type = $this->britam_db->table('glifeclientinfo', 'g')
                ->where('g.email', $email)
                ->select('g.client_type', 'g.Id')
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
                // send email
                if ($email == null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No email found for the client.'
                    ], 400);
                } else {
                    //validate email
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid email address.'
                        ], 400);
                    }

                    //generate OTP
                    $otp = rand(1000, 9999);
                    $gc_id = $client_type->Id;

                    //update otp in glifeclientinfo under the client
                    $this->britam_db->table('glifeclientinfo')
                        ->where('Id', $gc_id)
                        ->update(['Otp' => $otp]);

                    //delete the existing password
                    $this->britam_db->table('glifeclientinfo')
                        ->where('Id', $gc_id)
                        ->update(['Password' => null]);

                    $glifeclientinfo_details = $this->britam_db->table('glifeclientinfo')
                        ->where('Id', $gc_id)
                        ->select('email', 'Otp')
                        ->first();

                    $to = $email;
                    $subject = "One Time Password";
                    $message = "Your OTP is: " . $otp;

                    $results = $this->sendEmail($to, $subject, $message, $glifeclientinfo_details);

                    return $results;
                }
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

    //verify otp
    public function verifyOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required',
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $otp = $request->otp;
            $email = $request->email;

            //get client details using the email
            $client_type = $this->britam_db->table('glifeclientinfo', 'g')
                ->where('g.email', $email)
                ->select('g.client_type', 'g.Otp')
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
                // verify if otp matches
                $set_otp = $client_type->Otp;
                if ($set_otp == $otp) {
                    return response()->json([
                        'success' => true,
                        'message' => 'OTP matches.'
                    ], 200);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'OTP does not match.'
                    ], 400);
                }
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

    public function sendOTPusingPolicyNumber(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'policy_no' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // $scheme_id = $request->scheme_id;
            // $gc_id = $request->gc_id;

            $policy_no = $request->policy_no;

            //select * from polschemeinfo p where p.policy_no = 'BGLAP/2023/00004';

            $policy_visibility = $this->britam_db->table('polschemeinfo')
                ->where('policy_no', $policy_no)
                ->select('ClientNumber')
                ->first();

            if ($policy_visibility == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No results found.',
                    'data' => []
                ], 400);
            } else {

                $gc_id = $policy_visibility->ClientNumber;

                // get email from glifeclientinfo
                $email = $this->britam_db->table('glifeclientinfo')
                    ->where('Id', $gc_id)
                    ->select('email', 'Otp', 'Password')
                    ->first();

                $password = $email->Password;

                //check if password is not null
                if ($password != null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Client has a password already.'
                    ], 400);
                }

                //$otp = $email->Otp;

                if ($email == null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No email found for the client.'
                    ], 400);
                } else {
                    // send email
                    $email = $email->email;

                    //validate email
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid email address.'
                        ], 400);
                    }

                    //generate OTP
                    $otp = rand(1000, 9999);

                    //update otp in glifeclientinfo under the client
                    $this->britam_db->table('glifeclientinfo')
                        ->where('Id', $gc_id)
                        ->update(['Otp' => $otp]);

                    $glifeclientinfo_details = $this->britam_db->table('glifeclientinfo')
                        ->where('Id', $gc_id)
                        ->select('email', 'Otp')
                        ->first();

                    $to = $email;
                    $subject = "One Time Password";
                    $message = "Your OTP is: " . $otp;

                    $results = $this->sendEmail($to, $subject, $message, $glifeclientinfo_details);

                    return $results;
                }
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
