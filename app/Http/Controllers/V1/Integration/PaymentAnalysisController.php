<?php

namespace App\Http\Controllers\V1\Integration;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class PaymentAnalysisController extends Controller
{
    //

    private function isUrlReachable($url)
    {
        try {
            $headers = @get_headers($url);
            if ($headers && strpos($headers[0], '200') !== false) {
                return true;
            }
        } catch (\Exception $e) {
        }
        return false;
    }

    function generateSerial()
    {
        $serial = '';
        $sql = 'SELECT Top 1 p.SpExportSerial FROM CompanyInfo p';
        $expression = DB::raw($sql);
        $results = $this->britam_db->select($expression->getValue($this->britam_db->getQueryGrammar()));
        $serial = ($results[0]->SpExportSerial);
        return $serial;
    }

    function updateSerial()
    {
        $serial = '';
        $sql = 'SELECT Top 1 p.SpExportSerial FROM CompanyInfo p';
        $expression = DB::raw($sql);
        $results = $this->britam_db->select($expression->getValue($this->britam_db->getQueryGrammar()));
        $this->britam_db->table('CompanyInfo')->update(['SpExportSerial' => (int) $results[0]->SpExportSerial + 1]);
    }

    private function updateRetryInfo($invoiceNumber, $retryAttempts, $maxRetries, $retryDelay)
    {

        $oid = $this->britam_db->table('AccountsPayableStaging')
            ->where('INVOICENO', $invoiceNumber)
            ->OrderBy('Oid', 'DESC')
            ->first();

        $this->britam_db->table('AccountsPayableStaging')
            ->where('Oid', $oid->Oid)
            ->where(function ($query) {
                $query->where('PendingFurtherReview', 0)
                    ->orWhereNull('PendingFurtherReview');
            })
            ->update([
                'MaxRetries' => $maxRetries,
                'RetryDelay' => $retryDelay,
                'RetryAttempts' => $retryAttempts
            ]);
    }

    private function updateIntegrationStatus($invoiceNumber, $isExtractedToERP, $message, $time_now)
    {

        $oid = $this->britam_db->table('AccountsPayableStaging')
            ->where('INVOICENO', $invoiceNumber)
            ->OrderBy('Oid', 'DESC')
            ->first();

        $this->britam_db->table('AccountsPayableStaging')
            ->where('Oid', $oid->Oid)
            ->where(function ($query) {
                $query->where('PendingFurtherReview', 0)
                    ->orWhereNull('PendingFurtherReview');
            })
            ->update([
                'IsExtractedToERP' => $isExtractedToERP,
                'MESSAGELOGS' => $message,
                'ExtractedOn' => $time_now
            ]);
    }

    private function updateGlPaymentStatus($invoiceNumber)
    {
        $oid = $this->britam_db->table('AccountsPayableStaging')
            ->where('INVOICENO', $invoiceNumber)
            ->OrderBy('Oid', 'DESC')
            ->first();

        $payment_id = $this->britam_db->table('AccountsPayableStaging')
            ->where('Oid', $oid->Oid)
            ->where(function ($query) {
                $query->where('PendingFurtherReview', 0)
                    ->orWhereNull('PendingFurtherReview');
            })
            ->first();

        $this->britam_db->table('glpayment')
            ->where('idd', $payment_id->Payment)
            ->update([
                'IsRemitted' => 1
            ]);
    }

    public function sendPayment()
    {
        ini_set('max_execution_time', 1800); //1800 seconds = 30 minutes

        try {
            $msg = 'Payment sent successfully.';
            $blank_data = 'No response from ERP.';
            // $url = "http://172.28.1.31:7007/Supplier/ProxyServices/SupplierProxyServiceRS/createSupplierInvoice";
            $url = "http://10.10.4.62:9005/Supplier/ProxyServices/SupplierProxyServiceRS/createSupplierInvoice";
            $headers = ['Content-Type: application/json'];
            $time_now = now();

            $this->britam_db->transaction(function () use (&$res, &$msg, &$url, &$headers, &$time_now, &$blank_data) {
                $sqlresults = "SELECT 
                    sp.SERIALNO AS invoiceId,
                    sp.LEDGEROU AS ledgerOu,
                    sp.INVOICENO AS invoiceNo, 
                    sp.INVOICETYPELOOKUPCODE AS invoiceTypeLookupCode,
                    sp.INVOICEDATE AS invoiceDate,
                    sp.VENDORNUMBER AS vendorNumber,
                    sp.VENDORNAME AS vendorName,
                    sp.VENDORSITECODE AS vendorSiteCode,
                    ROUND(sp.INVOICEAMOUNT,2) AS invoiceAmount,
                    sp.CURRENCYCODE AS currencyCode,
                    sp.GLDATE AS glDate,
                    sp.DESCRIPTION AS description,
                    sp.EXCHANGERATE AS exchangeRate,
                    sp.EXCHANGERATETYPE AS exchangeRateType,
                    sp.EXCHANGEDATE AS exchangeDate,
                    sp.TERMSNAME AS termsName,
                    sp.WORKFLOWFLAG AS workflowFlag,
                    sp.PAYMENTMETHODLOOKUPCODE AS paymentMethodLookupCode,
                    sp.PAYGROUPLOOKUPCODE AS payGroupLookupCode,
                    sp.SOURCE AS source,
                    sp.PAYEEACCOUNTNUMBER AS payeeAccountNumber,
                    sp.PAYEEBANKCODE AS payeeBankCode,
                    sp.PAYEEBRANCHCODE AS payeeBranchCode,
                    sp.PAYEENAME AS payeeName,
                    sp.PAYMENTTYPE AS paymentType,
                    sp.POLICYNUMBER AS policyNumber,
                    spa.[LineNo] AS [lineNo],
                    spa.LineTypeLookupCode AS lineTypeLookupCode,
                    spa.ItemDesc AS itemDesc,
                    ROUND(spa.LineLevelAmount,2) AS lineLevelAmount,
                    spa.AccountingDate AS accountingDate,
                    spa.AmountIncludesTaxFlag AS amountIncludesTaxFlag,
                    spa.taxCode AS taxCode,
                    spa.AmountIncludesWhtFlag AS amountIncludesWhtFlag,
                    spa.WhtCode AS whtCode,
                    spa.DistCodeConcatenated AS distCodeConcatenated,
                    spa.TaxGroup AS taxGroup
                FROM AccountsPayableStaging sp
                LEFT JOIN AccountsPayableAnalysisStaging spa ON spa.Payable = sp.Oid
                WHERE sp.IsExtractedToERP = 0 AND (sp.PendingFurtherReview = 0 OR sp.PendingFurtherReview IS NULL)";

                $expression = DB::raw($sqlresults);
                $results = $this->britam_db->select($expression->getValue($this->britam_db->getQueryGrammar()));

                if (count($results) > 0) {

                    $jsonData = [];
                    $tempInvoiceData = [];

                    foreach ($results as $result) {
                        $invoiceNo = $result->invoiceNo;

                        //$serial = $this->generateSerial();
                        $uw_year = date('Y', strtotime($result->accountingDate));

                        if (!isset ($tempInvoiceData[$invoiceNo])) {
                            $tempInvoiceData[$invoiceNo]['invoiceHeader'] = [
                                'serialNo' => $result->invoiceId,
                                'ledgerOu' => $result->ledgerOu,
                                'invoiceNo' => $invoiceNo,
                                'invoiceTypeLookupCode' => $result->invoiceTypeLookupCode,
                                'invoiceDate' => date('Y-m-d', strtotime($result->invoiceDate)),
                                'vendorNumber' => $result->vendorNumber,
                                'vendorName' => $result->vendorName,
                                'vendorSiteCode' => $result->vendorSiteCode,
                                'invoiceAmount' => number_format($result->invoiceAmount, 2, '.', ''),
                                // 'invoiceAmount' => $result->invoiceAmount,
                                'currencyCode' => $result->currencyCode,
                                'glDate' => date('Y-m-d', strtotime($result->glDate)),
                                'description' => $result->description,
                                'exchangeRate' => $result->exchangeRate,
                                'exchangeRateType' => $result->exchangeRateType,
                                'exchangeDate' => date('Y-m-d', strtotime($result->exchangeDate)),
                                'termsName' => $result->termsName,
                                'workflowFlag' => $result->workflowFlag,
                                'paymentMethodLookupCode' => $result->paymentMethodLookupCode,
                                'payGroupLookupCode' => $result->payGroupLookupCode,
                                'source' => $result->source,
                                'payeeAccountNumber' => $result->payeeAccountNumber,
                                'payeeBankCode' => $result->payeeBankCode,
                                'payeeBranchCode' => $result->payeeBranchCode,
                                'payeeName' => $result->payeeName,
                                'paymentType' => $result->paymentType,
                                'policyNumber' => $result->policyNumber,
                            ];

                        }

                        $tempInvoiceData[$invoiceNo]['invoiceLines'][] = [
                            'invoiceNo' => $result->invoiceNo,
                            'lineNo' => $result->lineNo,
                            'lineTypeLookupCode' => $result->lineTypeLookupCode,
                            'itemDesc' => $result->itemDesc,
                            'lineLevelAmount' => number_format($result->lineLevelAmount, 2, '.', ''),
                            //'lineLevelAmount' => $result->lineLevelAmount,
                            'accountingDate' => date('Y-m-d', strtotime($result->accountingDate)),
                            'amountIncludesTaxFlag' => $result->amountIncludesTaxFlag,
                            'taxCode' => $result->taxCode,
                            'amountIncludesWhtFlag' => $result->amountIncludesWhtFlag,
                            'whtCode' => $result->whtCode,
                            'distCodeConcatenated' => $result->distCodeConcatenated,
                            'taxGroup' => $result->taxGroup,
                        ];

                        //$this->updateSerial();
                    }
                } else {
                    $res = [
                        'success' => false,
                        'message' => 'No data found',
                    ];

                    return;
                }


                foreach ($tempInvoiceData as $invoiceData) {
                    $jsonData = [
                        'SupplierInvoice' => [
                            'invoiceHeader' => $invoiceData['invoiceHeader'],
                            'invoiceLines' => $invoiceData['invoiceLines'],
                        ],
                    ];
                    $EntireDocument = json_encode($jsonData, JSON_PRETTY_PRINT);
                    Log::channel('supplier')->info("EntireDocument :\n" . $EntireDocument);

                    try {
                        $retryAttempts = 0;
                        $maxRetries = 5;
                        $retryDelay = 1000;
                        $InvoiceNumber = null;

                        $response = retry($maxRetries, function () use ($url, $headers, $EntireDocument, &$retryAttempts, $retryDelay, $maxRetries, &$InvoiceNumber, &$time_now) {

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

                            $responseData = json_decode($EntireDocument, true);
                            $InvoiceNumber = $responseData['SupplierInvoice']['invoiceHeader']['invoiceNo'];

                            //save retry attempts to db
                            $this->updateRetryInfo($InvoiceNumber, $retryAttempts, $maxRetries, $retryDelay);

                            if ($error) {
                                Log::channel('supplier')->error("cURL Error (Attempt $retryAttempts) on (Invoice Number: $InvoiceNumber) " . $error);
                                throw new \Exception("cURL Error: " . $error);
                            }

                            return $response;

                        }, $retryDelay);

                        if ($response != null) {
                            Log::channel('supplier')->info("response" . $response);

                            $responseJson = json_decode($response, true);

                            if ($responseJson !== null) {
                                if (isset ($responseJson['success']) && $responseJson['success'] === false) {
                                    $errorMessage = isset ($responseJson['message']) ? $responseJson['message'] : 'Unknown Error';

                                    // Check if the error message indicates a duplicate entry
                                    if (strpos($errorMessage, 'ORA-00001: unique constraint (AP.AP_INVOICES_INTERFACE_U1) violated') !== false) {
                                        // Mark the record as successfully sent to ERP
                                        $this->updateIntegrationStatus($InvoiceNumber, 1, $errorMessage, $time_now);
                                        $this->updateGlPaymentStatus($InvoiceNumber);

                                        Log::channel('supplier')->info('Duplicate Entry: ' . $InvoiceNumber);

                                    } else {
                                        // Log the regular error
                                        Log::channel('supplier')->error("Error Response: " . $errorMessage);

                                        // Save error message to db
                                        $this->updateIntegrationStatus($InvoiceNumber, 0, $errorMessage, null);

                                        $paymentResponses = [
                                            'success' => false,
                                            'message' => $errorMessage,
                                        ];
                                        Log::channel('supplier')->error('Payment Error Response: ' . json_encode($paymentResponses, JSON_PRETTY_PRINT));
                                    }
                                } else {
                                    $msg = isset ($responseJson['message']) ? $responseJson['message'] : 'Unknown Success';
                                    // Save success message to db
                                    $this->updateIntegrationStatus($InvoiceNumber, 1, $msg, $time_now);
                                    $this->updateGlPaymentStatus($InvoiceNumber);

                                    $paymentResponses = [
                                        'success' => true,
                                        'message' => $msg,
                                    ];
                                    Log::channel('supplier')->info('Payment Success Response: ' . json_encode($paymentResponses, JSON_PRETTY_PRINT));
                                }
                            } else {
                                $this->updateIntegrationStatus($InvoiceNumber, 1, $blank_data, null);

                                $paymentResponses = [
                                    'success' => false,
                                    'message' => $blank_data
                                ];
                                Log::channel('supplier')->error('Payment Blank Response: ' . json_encode($paymentResponses, JSON_PRETTY_PRINT));

                            }

                            sleep(5);

                        } else {
                            $this->updateIntegrationStatus($InvoiceNumber, 0, $blank_data, null);

                            $paymentResponses = [
                                'success' => false,
                                'message' => $blank_data
                            ];
                            Log::channel('supplier')->error('Payment Blank Response: ' . json_encode($paymentResponses, JSON_PRETTY_PRINT));
                        }


                    } catch (\Exception $e) {
                        $paymentResponses = [
                            'success' => false,
                            'message' => 'Failed to send the request invoices.',
                            'error' => $e->getMessage(),
                        ];
                        Log::channel('supplier')->error('Payment Exception Error Response: ' . json_encode($paymentResponses, JSON_PRETTY_PRINT));
                    }
                }

                return $paymentResponses;


            });
        } catch (\Throwable $th) {
            $res = array(
                'success' => false,
                'message' => $th->getMessage()
            );
            Log::channel('supplier')->error('Final Error Message: ' . json_encode($res, JSON_PRETTY_PRINT));
        }
        return response()->json($res);
    }
}
