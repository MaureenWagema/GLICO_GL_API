<?php

namespace App\Http\Controllers\V1\Integration;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class DocumentsController extends Controller
{
    //

    public function sendDocument(Request $request)
    {
        try {
            $msg = 'Document sent successfully';
            $blank_data = 'No response from ERP.';
            //$url = "http://172.28.1.31:7007/Document/ProxyServices/DocumentProxyServiceRS/uploadDocument";
            $url = "http://10.10.4.62:9005/Document/ProxyServices/DocumentProxyServiceRS/uploadDocument";
            $maxRetries = 3;
            $retryDelay = 1000;
            $retryAttempts = 0;
            $blank_data = 'No response from ERP.';
            $headers = ['Content-Type: application/json'];

            $this->britam_db->transaction(function () use (&$res, &$msg, $request, $blank_data, $url, $maxRetries, $retryDelay, $retryAttempts, $headers) {

                $data = json_decode($request->getContent(), true);
                if (
                    is_array($data) &&
                    isset($data['header']) &&
                    isset($data['filecontent'])
                ) {
                    $documentHeader = $data['header'];

                    $documentHeader = [
                        'documentType' => $documentHeader['documentType'],
                        'refID' => $documentHeader['refID'],
                        'entityID' => $documentHeader['entityID'],
                        'entityName' => $documentHeader['entityName'],
                        'username' => $documentHeader['username'],
                        'lobID' => $documentHeader['lobID'],
                        'documentName' => $documentHeader['documentName'],
                        'BPMRef' => $documentHeader['BPMRef'],
                        'contextID' => $documentHeader['contextID'],
                        'properties' => $documentHeader['properties'],
                    ];

                    $data['filecontent'];

                    $EntireDocumentTest = json_encode($data, JSON_PRETTY_PRINT);
                    //Log::channel('document')->info("Doc Details : \n" . $EntireDocumentTest);

                    $EntireDocument = json_encode($data);

                    $response = retry($maxRetries, function () use ($url, $headers, $EntireDocument, &$retryAttempts, &$InvoiceNumber, &$time_now) {

                        $retryAttempts++;
                        $curl = curl_init();

                        curl_setopt_array($curl, [
                            CURLOPT_URL => $url,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_CUSTOMREQUEST => "POST",
                            CURLOPT_POSTFIELDS => $EntireDocument,
                            CURLOPT_HTTPHEADER => $headers,
                        ]);

                        $response = curl_exec($curl);
                        $error = curl_error($curl);

                        curl_close($curl);

                        if ($error) {
                            Log::channel('document')->error("cURL Error (Attempt $retryAttempts) " . $error);
                            throw new \Exception("cURL Error: " . $error);
                        }

                        return $response;

                    }, $retryDelay);

                    if ($response !== false) {

                        $responseData = json_decode($response, true);

                        Log::channel('document')->info("response: \n" . json_encode($responseData, JSON_PRETTY_PRINT));

                        if ($responseData !== null) {

                            if (isset($responseData['return']) && isset($responseData['documentId'])) {
                                $documentResponses = [
                                    'message' => 'Document sent successfully',
                                    'documentId' => $responseData['documentId'],
                                ];
                            } else {
                                $documentResponses = [
                                    'success' => false,
                                    'message' => 'Missing "return" or "documentId" key in response',
                                ];
                            }
                        } else {
                            $documentResponses = [
                                'success' => false,
                                'message' => 'Invalid JSON response from ERP',
                            ];
                        }

                    } else {
                        $documentResponses = [
                            'success' => false,
                            'message' => $blank_data
                        ];
                        Log::channel('document')->error('Blank document Response: ' . $documentResponses);
                    }

                    return $documentResponses;

                } else {
                    return response()->json([
                        'error' => 'Invalid JSON data or unexpected structure',
                    ], 400);
                }

            });


        } catch (\Throwable $th) {
            $res = array(
                'success' => false,
                'message' => $th->getMessage()
            );
        }
        return response()->json($res);
    }

    public function getDocument(Request $request)
    {
        try {
            $msg = 'Document retrieved successfully';
            //$url = "http://172.28.1.31:7007/Document/ProxyServices/DocumentProxyServiceRS/getDocument";
            $url = "http://10.10.4.62:9005/Document/ProxyServices/DocumentProxyServiceRS/getDocument";
            $maxRetries = 3;
            $retryDelay = 1000;
            $retryAttempts = 0;
            $blank_data = 'No response from ERP.';
            $headers = ['Content-Type: application/json'];

            $this->britam_db->transaction(function () use (&$res, &$msg, $headers, $url, $request, &$maxRetries, &$retryDelay, &$retryAttempts, &$blank_data) {

                $data = json_decode($request->getContent(), true);
                if (is_array($data) && isset($data['arg0'])) {
                    $arg0 = $data['arg0'];

                    if (!isset($arg0['documentType'], $arg0['drawer'], $arg0['policyNumber'])) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid JSON data format',
                        ], 400);
                    }

                    $EntireDocument = json_encode(['arg0' => $arg0], JSON_PRETTY_PRINT);
                    Log::channel('document')->info("Doc Details : \n" . $EntireDocument);

                    $response = retry($maxRetries, function () use ($url, $headers, $EntireDocument, &$retryAttempts, &$InvoiceNumber, &$time_now) {

                        $retryAttempts++;
                        $curl = curl_init();

                        curl_setopt_array($curl, [
                            CURLOPT_URL => $url,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_CUSTOMREQUEST => "POST",
                            CURLOPT_POSTFIELDS => $EntireDocument,
                            CURLOPT_HTTPHEADER => $headers,
                        ]);

                        $response = curl_exec($curl);
                        $error = curl_error($curl);

                        curl_close($curl);

                        if ($error) {
                            Log::channel('document')->error("cURL Error (Attempt $retryAttempts) " . $error);
                            throw new \Exception("cURL Error: " . $error);
                        }

                        return $response;

                    }, $retryDelay);

                    if ($response !== false) {

                        $responseData = json_decode($response, true);

                        Log::channel('document')->info("response data : \n" . json_encode($responseData, JSON_PRETTY_PRINT));

                        if ($responseData !== null) {

                            if (isset($responseData['return']['file'])) {
                                $fileData = $responseData['return']['file'];

                                $decodedData = base64_decode($fileData);

                                //$tempFilePath = tempnam(sys_get_temp_dir(), 'preview_');
                                $publicPath = public_path();

                                $tempFilePath = $publicPath . '/document.pdf';

                                file_put_contents($tempFilePath, $decodedData);

                                header("Content-type: application/pdf");
                                header("Content-Disposition: inline; filename='document.pdf'");
                                header("Content-Length: " . filesize($tempFilePath));

                                //echo $decodedData;
                                readfile($tempFilePath);
                                exit;
                            } else {
                                $documentResponses = [
                                    'success' => false,
                                    'message' => 'Missing "return" key in response',
                                ];
                            }
                        } else {
                            $documentResponses = [
                                'success' => false,
                                'message' => 'Invalid JSON response from ERP',
                            ];
                        }

                    } else {
                        $documentResponses = [
                            'success' => false,
                            'message' => $blank_data
                        ];
                        Log::channel('document')->error('Blank document Response: ' . $documentResponses);
                    }
                }

                return $documentResponses;

            });

        } catch (\Throwable $th) {
            $res = array(
                'success' => false,
                'message' => $th->getMessage()
            );
        }
        return response()->json($res);
    }
}
