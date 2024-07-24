<?php

namespace App\Http\Controllers\V1\Integration;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;

class CSVController extends Controller
{
    //
    function generateSerial()
    {
        $serial = '';
        $sql = 'SELECT Top 1 p.GlExportSerial FROM CompanyInfo p';
        $expression = DB::raw($sql);
        $results = $this->britam_db->select($expression->getValue($this->britam_db->getQueryGrammar()));
        $serial = 'SLAMS' . str_pad($results[0]->GlExportSerial, 5, 0, STR_PAD_LEFT);
        return $serial;
    }

    function updateSerial()
    {
        $serial = '';
        $sql = 'SELECT Top 1 p.GlExportSerial FROM CompanyInfo p';
        $expression = DB::raw($sql);
        $results = $this->britam_db->select($expression->getValue($this->britam_db->getQueryGrammar()));
        $this->britam_db->table('CompanyInfo')->update(['GlExportSerial' => (int) $results[0]->GlExportSerial + 1]);
    }

    public function dataBalances($date_from, $date_to)
    {
        $sql_query = "SELECT SUM(p.local_amount) AS balance FROM gltransinfo p  where (CAST(p.updated_On AS DATE) BETWEEN '" . $date_from . "' AND '" . $date_to . "') AND (p.IsExtracted=0)";
        $expression = DB::raw($sql_query);
        $results = $this->britam_db->select($expression->getValue($this->britam_db->getQueryGrammar()));
        $balance = $results[0]->balance;
        return $balance;
    }

    // public function updateExtract($ExtractBatch, $date_from, $date_to, $account_month)
    // {
    //     try {
    //         // First update: Set 'IsExtracted' to 1 for records within the date range and specific conditions
    //         $updatedRows = $this->britam_db->table('gltransinfo')
    //             ->whereBetween(DB::raw('CAST(updated_On AS DATE)'), [$date_from, $date_to])
    //             ->where('IsExtracted', 0)
    //             ->where('ExcludeFromERPExtract', 0)
    //             ->where('account_month', $account_month)
    //             ->update(['IsExtracted' => 1]);

    //         // Second update: Set 'ExtractBatch' for the rows updated in the first update
    //         if ($updatedRows > 0) {

    //             // make sure the ExtractBatch is null before updating and throw an exception if it is not null

    //             if (
    //                 $this->britam_db->table('gltransinfo')
    //                     ->whereBetween(DB::raw('CAST(updated_On AS DATE)'), [$date_from, $date_to])
    //                     ->where('IsExtracted', 1)
    //                     ->where('ExtractBatch', '!=', null)
    //                     ->where('account_month', $account_month)
    //                     ->count() > 0
    //             ) {
    //                 throw new Exception('ExtractBatch is not null for some records');
    //             }

    //             $this->britam_db->table('gltransinfo')
    //                 ->whereBetween(DB::raw('CAST(updated_On AS DATE)'), [$date_from, $date_to])
    //                 ->where('IsExtracted', 1)
    //                 ->where('ExtractBatch', null)
    //                 ->where('account_month', $account_month)
    //                 ->update(['ExtractBatch' => $ExtractBatch]);

    //         }

    //         // Optionally, return the number of rows updated for debugging purposes
    //         return $updatedRows;

    //     } catch (Exception $e) {
    //         // Handle the exception (log it, rethrow it, etc.)
    //         Log::error('Failed to update extract batch: ' . $e->getMessage());
    //         throw $e;
    //     }
    // }

    // update extract using only the id
    public function updateExtract($idd, $ExtractBatch)
    {
        try {
            // First update: Set 'IsExtracted' to 1 for records within the date range and specific conditions
            $updatedRows = $this->britam_db->table('gltransinfo')
                ->where('idd', $idd)
                ->where('IsExtracted', 0)
                ->update(['IsExtracted' => 1]);

            // Second update: Set 'ExtractBatch' for the rows updated in the first update
            if ($updatedRows > 0) {

                // make sure the ExtractBatch is null before updating and throw an exception if it is not null

                if (
                    $this->britam_db->table('gltransinfo')
                        ->where('idd', $idd)
                        ->where('IsExtracted', 1)
                        ->where('ExtractBatch', '!=', null)
                        ->count() > 0
                ) {
                    throw new Exception('ExtractBatch is not null for some records');
                }

                $this->britam_db->table('gltransinfo')
                    ->where('idd', $idd)
                    ->where('IsExtracted', 1)
                    ->where('ExtractBatch', null)
                    ->update(['ExtractBatch' => $ExtractBatch]);

            }

            // Optionally, return the number of rows updated
            return $updatedRows;

        } catch (Exception $e) {
            // Handle the exception 
            Log::error('Failed to update extract batch: ' . $e->getMessage());
            throw $e;
        }
    }



    function dailyBalances($transaction_date)
    {
        $account_year = Carbon::parse($transaction_date)->year;
        $account_month = Carbon::parse($transaction_date)->month;

        $sql_query = "SELECT g.reference, ROUND(SUM(g.local_amount), 2) AS AMt 
                    FROM gltransinfo g  
                    WHERE g.account_year = ? AND g.account_month = ? AND CAST(g.trans_date AS DATE) = ?
                    GROUP BY g.reference 
                    HAVING ROUND(SUM(g.local_amount), 2) <> 0";

        $references = $this->britam_db->select($sql_query, [$account_year, $account_month, $transaction_date]);
        $this->updateDatesForReferences($references, $transaction_date, $transaction_date);

        return $references;
    }


    function updateDatesForReferences($references, $newTransDate, $newVoucherDate)
    {
        foreach ($references as $reference) {
            $referenceValue = $reference->reference;
            $sql_query = "UPDATE gltransinfo g SET g.trans_date = ?, g.voucher_date = ? WHERE g.reference = ?";
            $this->britam_db->update($sql_query, [$newTransDate, $newVoucherDate, $referenceValue]);
        }
    }

    private $processedDates = [];
    function processAllTransactionDates($results, $dailyBalancesFunction)
    {
        foreach ($results as $result) {
            $transaction_date = $result->ACCOUNTINGDATE;
            $dailyBalancesFunction($transaction_date);

            if (!in_array($transaction_date, $this->processedDates)) {
                $dailyBalancesFunction($transaction_date);
                $this->processedDates[] = $transaction_date; // Add the date to the processed list
            }
        }
    }

    function insertGLExtractionInfo($BATCHID, $date_from, $date_to, $is_successful, $extractionMessage, $CreatedOn)
    {
        $insert_query = "INSERT INTO GL_audit_logs (BATCHID, DateFrom, DateTo, is_successful, ExtractionMessage, CreatedOn) VALUES (?,?, ?, ?, ?, ?)";
        $this->britam_db->insert($insert_query, [$BATCHID, $date_from, $date_to, $is_successful, $extractionMessage, $CreatedOn]);
    }

    public function getGLLifetable(Request $request)
    {
        ini_set('max_execution_time', 1800); //1800 seconds = 30 minutes

        try {
            $this->britam_db->transaction(function () use (&$res, $request) {
                $date_from = $request->input('date_from');
                $date_to = $request->input('date_to');
                $account_month = ($request->input('month'));

                //check whether the data balances
                $balance = $this->dataBalances($date_from, $date_to);
                Log::channel('csv')->info('Data balances: ' . $balance);

                if ($balance > 0 || $balance < 0) {
                    $msg = __('Transactions do not balance');
                    $res = [
                        'success' => false,
                        'message' => $msg,
                        'balance' => $balance,
                    ];

                    $this->insertGLExtractionInfo("No SERIAL Generated", $date_from, $date_to, 0, $msg, now());

                    return $res;
                }

                $creditAmount = "CASE WHEN x.short_description = 'KES' THEN gl.local_amount ELSE gl.foreign_amount END";
                $debitAmount = "CASE WHEN x.short_description = 'KES' THEN gl.local_amount ELSE gl.foreign_amount END";

                $serial = $this->generateSerial();
                $GLsql_query = "SELECT 
                gl.idd,
                '$serial' AS BATCHID, 
                gl.trans_date AS ACCOUNTINGDATE,
                'SLAMSKE' AS USERJESOURCENAME, 
                'MANUAL' AS USERJECATEGORYNAME, 
                ABS(CASE WHEN $creditAmount < 0 THEN $creditAmount ELSE 0 END) AS creditAmount,
                ABS(CASE WHEN $debitAmount > 0 THEN $debitAmount ELSE 0 END) AS debitAmount,
                gl.CompanySegmentCode AS Companycode,
                gl.LOBSegmentCode AS LineOfBusiness,
                gl.ProductSegmentCode AS ProductCode,
                gl.SegmentBranchCode  AS BranchCode,
                gl.DepartmentCode AS DepartmentCode, 
                gl.DistributionChannelCode AS DistributionChannelCode, 
                CASE WHEN glchart.isbankaccount = 1 THEN glchart.displayAccountNo ELSE glchart.displayAccountNo END AS LedgerAccount,
                '2041' AS SETOFBOOKSID,
                'NEW' AS STATUS,
                x.short_description AS currencyCode,
                'User' AS USERCURRENCYCONVERSIONTYPE,
                gl.currency_rate AS CURRENCYCONVERSIONRATE,
                'A' AS ACTUALFLAG,
                gl.payee AS PAYEE,
                gl.SchemeName AS REFERENCE1,
                gl.SchemeNo AS REFERENCE2,
                CASE WHEN gl.entry_type = 1 THEN CONCAT(TRIM(gl.DebitNo), ' -- ', (SELECT TOP 1 p.SchemeDescription FROM polschemeinfo p WHERE p.policy_no = gl.SchemeNo)) WHEN gl.entry_type = 5 THEN gl.JournalReference ELSE CONCAT(TRIM(gl.reference), ' -- ', (SELECT TOP 1 p.SchemeDescription FROM polschemeinfo p WHERE p.policy_no = gl.SchemeNo)) END AS REFERENCE3,
                CASE WHEN gl.entry_type = 1 THEN gl.BankTransactionReference WHEN gl.entry_type = 3 THEN gl.reference WHEN gl.entry_type = 4 THEN gl.claim_no WHEN gl.entry_type = 5 AND gl.voucher_no NOT LIKE '%CA%' THEN CONCAT(gl.voucher_no, ' - ', gl.SchemeName) WHEN gl.entry_type = 5 AND gl.voucher_no LIKE '%CA%' THEN gl.ItemNarration ELSE NULL END AS REFERENCE4,
                gl.uw_year AS REFERENCE5,
                gl.IntermediaryName AS REFERENCE6,
                gl.IntermediaryKRAPinNo AS REFERENCE7,
                gl.reference AS REFERENCE8,
                gl.narration AS REFERENCE9,
				gl.DocumentSource AS DocumentSource
                FROM gltransinfo gl
                LEFT JOIN glbranchinfo branch ON gl.glbranch = branch.glBranch
                LEFT JOIN transtypeinfo trans ON gl.entry_type = trans.uwtrans_ID
                LEFT JOIN glchartaccinfo glchart ON gl.displayAccountNo = glchart.displayAccountNo
                LEFT JOIN currency_mainteinance x ON gl.currency_code = x.currency_code
                WHERE 1=1";

                if (isset($date_from) && isset($date_to)) {
                    $GLsql_query .= " AND ((CAST(gl.updated_On AS DATE) BETWEEN '" . $date_from . "' AND '" . $date_to . "') AND (gl.IsExtracted = 0) AND (ExcludeFromERPExtract = 0) AND (gl.account_month = $account_month)) ORDER BY gl.updated_On ASC";
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Kindly provide the date range'
                    );
                    return response()->json($res);
                }

                $expression = DB::raw($GLsql_query);
                $results = $this->britam_db->select($expression->getValue($this->britam_db->getQueryGrammar()));

                if ($results != null) {

                    try {
                        foreach ($results as $row) {
                            $sqlInsert = "INSERT INTO GLExtractERPStaging (BATCHID, ACCOUNTINGDATE, USERJESOURCENAME, USERJECATEGORYNAME, 
                                            ENTEREDCREDIT, ENTEREDDEBIT, COMPANYCODE, LINEOFBUSINESSCODE, PRODUCTCODE, BRANCHCODE, DEPARTMENTCODE, 
                                            DISTRIBUTIONCHANNELCODE, ACTUALACCOUNTCODE, SETOFBOOKSID, STATUS, CURRENCYCODE, USERCURRENCYCONVERSIONTYPE, 
                                            CURRENCYCONVERSIONRATE, ACTUALFLAG, PAYEE, REFERENCE1, REFERENCE2, REFERENCE3, REFERENCE4, REFERENCE5, 
                                            REFERENCE6, REFERENCE7, REFERENCE8, REFERENCE9, DocumentSource, IsExtractedToERP, CreatedOn, SlamsLedgerReference) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";


                            $this->britam_db->statement($sqlInsert, [
                                $row->BATCHID,
                                $row->ACCOUNTINGDATE,
                                $row->USERJESOURCENAME,
                                $row->USERJECATEGORYNAME,
                                $row->creditAmount,
                                $row->debitAmount,
                                $row->Companycode,
                                $row->LineOfBusiness,
                                $row->ProductCode,
                                $row->BranchCode,
                                $row->DepartmentCode,
                                $row->DistributionChannelCode,
                                $row->LedgerAccount,
                                $row->SETOFBOOKSID,
                                $row->STATUS,
                                $row->currencyCode,
                                $row->USERCURRENCYCONVERSIONTYPE,
                                $row->CURRENCYCONVERSIONRATE,
                                $row->ACTUALFLAG,
                                $row->PAYEE,
                                $row->REFERENCE1,
                                $row->REFERENCE2,
                                $row->REFERENCE3,
                                $row->REFERENCE4,
                                $row->REFERENCE5,
                                $row->REFERENCE6,
                                $row->REFERENCE7,
                                $row->REFERENCE8,
                                $row->REFERENCE9,
                                $row->DocumentSource,
                                0,
                                now(),
                                $row->idd
                            ]);

                            $this->updateExtract($row->idd, $row->BATCHID);
                        }

                        $this->updateSerial();

                        $msg = 'GL successfully posted to staging table';

                        $res = array(
                            'success' => true,
                            'msg' => $msg,
                            'system_id' => $serial
                        );
                        $this->insertGLExtractionInfo($serial, $date_from, $date_to, 1, $msg, now());

                    } catch (QueryException $e) {
                        $res = array(
                            'success' => false,
                            'msg' => 'A database error occurred: ' . $e->getMessage(),
                        );

                        $this->insertGLExtractionInfo($serial, $date_from, $date_to, 0, $e->getMessage(), now());

                    } catch (Exception $e) {
                        $res = array(
                            'success' => false,
                            'msg' => 'An error occurred: ' . $e->getMessage(),
                        );

                        $this->insertGLExtractionInfo($serial, $date_from, $date_to, 0, $e->getMessage(), now());
                    }

                    return response()->json($res);

                } else {
                    $res = [
                        'success' => false,
                        'message' => 'No records found to update.',
                    ];
                }

                return response()->json($res);

            }, 5);
        } catch (Exception $e) {
            $res = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (\Throwable $throwable) {
            $res = [
                'success' => false,
                'message' => $throwable->getMessage(),
            ];
        }

        return response()->json($res);
    }

    public function GLToStagingAutomated(Request $request)
    {
        ini_set('max_execution_time', 1800); //1800 seconds = 30 minutes

        try {
            $this->britam_db->transaction(function () use (&$res, $request) {
                $date_from = $request->input('date_from');
                $date_to = $request->input('date_to');

                //check whether the data balances
                $balance = $this->dataBalances($date_from, $date_to);
                Log::channel('csv')->info('Data balances: ' . $balance);

                if ($balance > 0 || $balance < 0) {
                    $msg = __('Transactions do not balance');
                    $res = [
                        'success' => false,
                        'message' => $msg,
                        'balance' => $balance,
                    ];

                    $this->insertGLExtractionInfo("No SERIAL Generated", $date_from, $date_to, 0, $msg, now());

                    return $res;
                }

                $creditAmount = "CASE WHEN x.short_description = 'KES' THEN gl.local_amount ELSE gl.foreign_amount END";
                $debitAmount = "CASE WHEN x.short_description = 'KES' THEN gl.local_amount ELSE gl.foreign_amount END";

                $serial = $this->generateSerial();
                $GLsql_query = "SELECT 
                gl.idd,
                '$serial' AS BATCHID,
                gl.account_month AS ACCOUNTMONTH,
                gl.trans_date AS ACCOUNTINGDATE,
                'SLAMSKE' AS USERJESOURCENAME, 
                'MANUAL' AS USERJECATEGORYNAME, 
                ABS(CASE WHEN $creditAmount < 0 THEN $creditAmount ELSE 0 END) AS creditAmount,
                ABS(CASE WHEN $debitAmount > 0 THEN $debitAmount ELSE 0 END) AS debitAmount,
                gl.CompanySegmentCode AS Companycode,
                gl.LOBSegmentCode AS LineOfBusiness,
                gl.ProductSegmentCode AS ProductCode,
                gl.SegmentBranchCode  AS BranchCode,
                gl.DepartmentCode AS DepartmentCode, 
                gl.DistributionChannelCode AS DistributionChannelCode, 
                CASE WHEN glchart.isbankaccount = 1 THEN glchart.displayAccountNo ELSE glchart.displayAccountNo END AS LedgerAccount,
                '2041' AS SETOFBOOKSID,
                'NEW' AS STATUS,
                x.short_description AS currencyCode,
                'User' AS USERCURRENCYCONVERSIONTYPE,
                gl.currency_rate AS CURRENCYCONVERSIONRATE,
                'A' AS ACTUALFLAG,
                gl.payee AS PAYEE,
                gl.SchemeName AS REFERENCE1,
                gl.SchemeNo AS REFERENCE2,
                CASE WHEN gl.entry_type = 1 THEN CONCAT(TRIM(gl.DebitNo), ' -- ', (SELECT TOP 1 p.SchemeDescription FROM polschemeinfo p WHERE p.policy_no = gl.SchemeNo)) WHEN gl.entry_type = 5 THEN gl.JournalReference ELSE CONCAT(TRIM(gl.reference), ' -- ', (SELECT TOP 1 p.SchemeDescription FROM polschemeinfo p WHERE p.policy_no = gl.SchemeNo)) END AS REFERENCE3,
                CASE WHEN gl.entry_type = 1 THEN gl.BankTransactionReference WHEN gl.entry_type = 3 THEN gl.reference WHEN gl.entry_type = 4 THEN gl.claim_no WHEN gl.entry_type = 5 AND gl.voucher_no NOT LIKE '%CA%' THEN CONCAT(gl.voucher_no, ' - ', gl.SchemeName) WHEN gl.entry_type = 5 AND gl.voucher_no LIKE '%CA%' THEN gl.ItemNarration ELSE NULL END AS REFERENCE4,
                gl.uw_year AS REFERENCE5,
                gl.IntermediaryName AS REFERENCE6,
                gl.IntermediaryKRAPinNo AS REFERENCE7,
                gl.reference AS REFERENCE8,
                gl.narration AS REFERENCE9,
				gl.DocumentSource AS DocumentSource
                FROM gltransinfo gl
                LEFT JOIN glbranchinfo branch ON gl.glbranch = branch.glBranch
                LEFT JOIN transtypeinfo trans ON gl.entry_type = trans.uwtrans_ID
                LEFT JOIN glchartaccinfo glchart ON gl.displayAccountNo = glchart.displayAccountNo
                LEFT JOIN currency_mainteinance x ON gl.currency_code = x.currency_code
                WHERE 1=1";

                //AND (gl.account_month = $account_month)

                if (isset($date_from) && isset($date_to)) {
                    $GLsql_query .= " AND ((CAST(gl.updated_On AS DATE) BETWEEN '" . $date_from . "' AND '" . $date_to . "') AND (gl.IsExtracted = 0) AND (ExcludeFromERPExtract = 0) AND (ExtractBatch = NULL)) ORDER BY gl.updated_On ASC";
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Kindly provide the date range'
                    );
                    return response()->json($res);
                }

                $expression = DB::raw($GLsql_query);
                $results = $this->britam_db->select($expression->getValue($this->britam_db->getQueryGrammar()));

                //get the account month
                //$account_month = $results[0]->ACCOUNTMONTH;

                if ($results != null) {


                    try {
                        foreach ($results as $row) {
                            $sqlInsert = "INSERT INTO GLExtractERPStaging (BATCHID, ACCOUNTINGDATE, USERJESOURCENAME, USERJECATEGORYNAME, 
                                            ENTEREDCREDIT, ENTEREDDEBIT, COMPANYCODE, LINEOFBUSINESSCODE, PRODUCTCODE, BRANCHCODE, DEPARTMENTCODE, 
                                            DISTRIBUTIONCHANNELCODE, ACTUALACCOUNTCODE, SETOFBOOKSID, STATUS, CURRENCYCODE, USERCURRENCYCONVERSIONTYPE, 
                                            CURRENCYCONVERSIONRATE, ACTUALFLAG, PAYEE, REFERENCE1, REFERENCE2, REFERENCE3, REFERENCE4, REFERENCE5, 
                                            REFERENCE6, REFERENCE7, REFERENCE8, REFERENCE9, DocumentSource, IsExtractedToERP, CreatedOn, SlamsLedgerReference) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";


                            $this->britam_db->statement($sqlInsert, [
                                $row->BATCHID,
                                $row->ACCOUNTINGDATE,
                                $row->USERJESOURCENAME,
                                $row->USERJECATEGORYNAME,
                                $row->creditAmount,
                                $row->debitAmount,
                                $row->Companycode,
                                $row->LineOfBusiness,
                                $row->ProductCode,
                                $row->BranchCode,
                                $row->DepartmentCode,
                                $row->DistributionChannelCode,
                                $row->LedgerAccount,
                                $row->SETOFBOOKSID,
                                $row->STATUS,
                                $row->currencyCode,
                                $row->USERCURRENCYCONVERSIONTYPE,
                                $row->CURRENCYCONVERSIONRATE,
                                $row->ACTUALFLAG,
                                $row->PAYEE,
                                $row->REFERENCE1,
                                $row->REFERENCE2,
                                $row->REFERENCE3,
                                $row->REFERENCE4,
                                $row->REFERENCE5,
                                $row->REFERENCE6,
                                $row->REFERENCE7,
                                $row->REFERENCE8,
                                $row->REFERENCE9,
                                $row->DocumentSource,
                                0,
                                now(),
                                $row->idd
                            ]);

                            $this->updateExtract($row->idd, $row->BATCHID);
                        }

                        $this->updateSerial();

                        $msg = 'GL successfully posted to staging table';

                        $res = array(
                            'success' => true,
                            'msg' => $msg,
                            'system_id' => $serial
                        );
                        $this->insertGLExtractionInfo($serial, $date_from, $date_to, 1, $msg, now());

                    } catch (QueryException $e) {
                        $res = array(
                            'success' => false,
                            'msg' => 'A database error occurred: ' . $e->getMessage(),
                        );

                        $this->insertGLExtractionInfo($serial, $date_from, $date_to, 0, $e->getMessage(), now());

                    } catch (Exception $e) {
                        $res = array(
                            'success' => false,
                            'msg' => 'An error occurred: ' . $e->getMessage(),
                        );

                        $this->insertGLExtractionInfo($serial, $date_from, $date_to, 0, $e->getMessage(), now());
                    }

                    return response()->json($res);

                } else {
                    $res = [
                        'success' => false,
                        'message' => 'No records found to update.',
                    ];
                }

                return response()->json($res);

            }, 5);
        } catch (Exception $e) {
            $res = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (\Throwable $throwable) {
            $res = [
                'success' => false,
                'message' => $throwable->getMessage(),
            ];
        }

        return response()->json($res);
    }

    public function reverseGetGLLifetable(Request $request)
    {
        try {
            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            $extract_batch = $request->input('extract_batch');

            //check whether there is the required data
            $recordsToUpdate = $this->britam_db->table('gltransinfo AS g')
                ->whereDate('g.updated_On', '>=', $date_from)
                ->whereDate('g.updated_On', '<=', $date_to)
                ->where('g.IsExtracted', '=', 1)
                ->where('g.ExtractBatch', '=', $extract_batch)
                ->count();

            // if there is data to update
            if ($recordsToUpdate > 0) {

                //set the records to not extracted
                $this->britam_db->table('gltransinfo')
                    ->whereDate('updated_On', '>=', $date_from)
                    ->whereDate('updated_On', '<=', $date_to)
                    ->where('IsExtracted', '=', 1)
                    ->update(['IsExtracted' => 0]);

                //set batch number to null
                $this->britam_db->table('gltransinfo')
                    ->whereDate('updated_On', '>=', $date_from)
                    ->whereDate('updated_On', '<=', $date_to)
                    ->where('ExtractBatch', '=', $extract_batch)
                    ->update(['ExtractBatch' => null]);

                //delete the records from the staging table
                $this->britam_db->table('GLExtractERPStaging')
                    ->where('BATCHID', '=', $extract_batch)
                    ->delete();

                $res = [
                    'success' => true,
                    'message' => 'Batch Reversed Successfully.'
                ];
            } else {
                $res = [
                    'success' => false,
                    'message' => 'No records found to update.',
                ];
            }
        } catch (QueryException $exception) {
            $res = [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }
        return response()->json($res);
    }
}
