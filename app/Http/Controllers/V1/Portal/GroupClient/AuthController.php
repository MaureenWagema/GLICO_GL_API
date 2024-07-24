<?php

namespace App\Http\Controllers\V1\Portal\GroupClient;

use App\Models\ContactPerson;
use Illuminate\Http\Request;
use App\Models\GlifeClientInfo;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    //

    public function loginASGroupClient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {

            $email = $request->input('email');
            $password = $request->input('password');

            $results = $this->britam_db->table('glifeclientinfo')->select("*")->where("email", $email)->first();

            if ($results == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No results found.'
                ], 401);
            }

            //print resuts and format
            Log::channel('corporate_api')->info('Client results: ' . json_encode($results));

            if ($results->Password == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No password set for this client. Please contact administrator to set password.'
                ], 401);
            }

            Log::channel('corporate_api')->info('Client Password results: ' . json_encode($results->Password));

            $password_match = password_verify($password, $results->Password);

            //compared passwords
            Log::channel('corporate_api')->info('Password match: ' . $password_match ? 'true' : 'false');

            if ($password_match) {

                $gc_user = GlifeClientInfo::where('email', $email)->first();

                Log::channel('corporate_api')->info('GC User: ' . json_encode($gc_user));

                $gc_user->setConnection('sqlsrv');
                $tokens = $gc_user->createToken('corporate-api-token', expiresAt: now()->addDays(1));

                Log::channel('corporate_api')->info('Tokens: ' . json_encode($tokens));

                $accessToken = $tokens->plainTextToken;
                Log::channel('corporate_api')->info('Access Token: ' . json_encode($accessToken));

                // Set the access token as a cookie
                $accessTokenCookie = cookie('access_token', $accessToken, 60, null, null, false, true);
                Log::channel('corporate_api')->info('Access Token Cookie: ' . json_encode($accessTokenCookie));

                // return response()->json([
                //     'success' => true,
                //     'message' => 'Login successful.',
                //     'data' => $results
                // ], 200)->withCookies($accessTokenCookie);

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful.',
                    'access_token' => $accessToken,
                    'data' => $results
                ], 200)->cookie($accessTokenCookie);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials.'
                ], 401);
            }


        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error logging in' . $th->getMessage()
            ], 500);

        }
    }

    public function createGroupclientPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string', //this is the OTP
            'password' => 'required|string',
            'confirm_password' => 'required|same:password'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $password = $request->input('password');
            $email = $request->input('email');
            $confirm_password = $request->input('confirm_password');
            $otp = $request->input('otp');

            if ($confirm_password != $password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Passwords do not match.'
                ], 401);
            }

            //check if email exists
            $results = $this->britam_db->table('glifeclientinfo')->select("*")->where("email", $email)->first();

            if ($results == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please verify the email address provided.'
                ], 401);
            }

            // if ($results->client_type != 1) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Client is not a corporate institution.'
            //     ], 401);
            // }

            if ($results->Password != null) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already exists.'
                ], 401);
            }

            if ($results->Otp != null) {
                if ($results->Otp != $otp) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid OTP.'
                    ], 401);
                }
            }

            //get Id from email
            $client_id = $results->Id;

            $bcrypt_hashed_password = password_hash($password, PASSWORD_BCRYPT);

            $hashed_string_pass = (string) $bcrypt_hashed_password;

            Log::channel('corporate_api')->info('Hashed password: ' . $hashed_string_pass);

            $results = $this->britam_db->table('glifeclientinfo')
                ->where('Id', $client_id)
                ->update(['Password' => $hashed_string_pass]);

            if ($results) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password created successfully.'
                ], 201);

            } else {

                return response()->json([
                    'success' => false,
                    'message' => 'Error creating password.'
                ], 500);
            }

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error logging in ' . $th->getMessage()
            ], 500);

        }
    }

    //reset Password
    public function resetClientPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'confirm_password' => 'required|same:password'
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
            $password = $request->password;
            $confirm_password = $request->confirm_password;

            if ($confirm_password != $password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Passwords do not match.'
                ], 401);
            }

            $results = $this->britam_db->table('glifeclientinfo')->select("*")->where("email", $email)->first();

            if ($results == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please verify the email address provided.'
                ], 401);
            }

            // if ($results->client_type != 1) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Client is not a corporate institution.'
            //     ], 401);
            // }

            if ($results->Password != null) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already exists.'
                ], 401);
            }
            //get Id from email
            $client_id = $results->Id;

            $bcrypt_hashed_password = password_hash($password, PASSWORD_BCRYPT);

            $hashed_string_pass = (string) $bcrypt_hashed_password;

            Log::channel('corporate_api')->info('Hashed password: ' . $hashed_string_pass);

            $results = $this->britam_db->table('glifeclientinfo')
                ->where('Id', $client_id)
                ->update(['Password' => $hashed_string_pass]);

            if ($results) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password created successfully.'
                ], 201);

            } else {

                return response()->json([
                    'success' => false,
                    'message' => 'Error creating password.'
                ], 500);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error creating password ' . $th->getMessage()
            ], 500);

        }
    }

    //function to return current user 
    public function getCurrentUser(Request $request)
    {
        try {

            $user = ContactPerson::user();
            $user1 = $request->user();

            //get userid
            $user_id = $user->id;

            print_r($user);
            print_r($user1);
            print_r($user_id);

            return response()->json([
                'success' => true,
                'message' => 'User fetched successfully',
                'data' => $user
            ], 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching user' . $th->getMessage()
            ], 500);
        }
    }
}
