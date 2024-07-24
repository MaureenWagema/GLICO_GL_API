<?php

namespace App\Http\Controllers\V1\Portal\GroupClient;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EnquiriesController extends Controller
{
    //

    public function submitEnquiry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            //'Client' => 'required',
            'portal_user_id' => 'required', //this is the user id of the logged in user
            'Subject' => 'required',
            'Narration' => 'required',
            'has_attachment' => 'required|boolean',
            'attachments' => 'required_if:has_attachment,true|array',
            'attachments.*' => 'required_if:has_attachment,true'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $portal_user_id = $request->input('portal_user_id');
            //$client_id = $request->input('Client');
            $subject = $request->input('Subject');
            $narration = $request->input('Narration');
            $has_attachment = $request->input('has_attachment');


            $status = $this->britam_db->table('EnquiryStatusInfo')->where('IsOpen', true)->first()->Id;

            // since portal_user_id is from PortalUserLoginTable, get the contactpersoninfo id 

            $contact_person_id = $this->britam_db->table('PortalUserLoginTable')
                ->where('id', $portal_user_id)
                ->first()->ContactPerson;

            if (!$contact_person_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact person not found',
                    'data' => []
                ], 404);
            }
            // begin transaction
            $this->britam_db->beginTransaction();

            $enquiry_id = $this->britam_db->table('PortalEnquiries')->insertGetId([
                //'Client' => $client_id ?? null,
                'ContactPerson' => $contact_person_id,
                'Subject' => $subject,
                'Status' => $status,
                'created_by' => 'API',
                'created_on' => date('Y-m-d H:i:s')
            ]);

            $message_id = $this->britam_db->table('PortalEnquiryMessages')->insertGetId([
                'EnquiryId' => $enquiry_id,
                'Narration' => $narration,
                'HasAttachments' => $has_attachment,
                'created_by' => 'API',
                'created_on' => date('Y-m-d H:i:s')
            ]);

            if ($has_attachment) {

                $attachments = $request->file('attachments');

                $files_attached = [];

                foreach ($attachments as $attachment) {
                    $fileName = time() . '_' . $attachment->getClientOriginalName();
                    $mime_type = $attachment->getClientMimeType();
                    $file_size = $attachment->getSize();
                    // $file_url = $attachment->storeAs('enquiry_attachments', $fileName, 'public_documents');
                    // //$file_url = Storage::disk('public_documents')->putFileAs('enquiry_attachments', $attachment, $fileName);
                    // $full_file_path = Storage::disk('public_documents')->path($file_url);
                    Storage::disk('ftp')->putFileAs($attachment, $fileName);

                    $fullFileUrl = Storage::disk('ftp')->url($fileName);
                    $file_path = Storage::disk('ftp')->path($fileName);
                    //$full_file_path = storage_path('app/public_documents/' . $file_url);
                    $file_extension = $attachment->getClientOriginalExtension();

                    $files_attached[] = [
                        'MessageId' => $message_id,
                        'FileName' => $fileName,
                        'FileMime' => $mime_type,
                        'FileSize' => $file_size,
                        'FileUrl' => $fullFileUrl,
                        'FullFileUrl' => $file_path,
                        'FileExtension' => $file_extension,
                        'created_by' => 'API',
                        'created_on' => date('Y-m-d H:i:s')
                    ];
                }

                //insert the attachments into the database
                $this->britam_db->table('PortalEnquiryFiles')->insert($files_attached);
            }

            // commit transaction
            $this->britam_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Enquiry submitted successfully',
                'data' => $enquiry_id
            ], 201);


        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'An error occured',
                'data' => $th->getMessage()
            ], 500);
        }
    }

    //get predefined subjects
    public function getPredefinedSubjects(Request $request)
    {
        $subjects = $this->britam_db->table('EnquirySubjects')->get();

        return response()->json([
            'success' => true,
            'message' => 'Predefined subjects fetched successfully',
            'data' => $subjects
        ], 200);
    }

    public function replyToResponse(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'MessageId' => 'required',
            'ClientResponse' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $message_id = $request->input('MessageId');
            $client_response = $request->input('ClientResponse');

            //update the response
            $this->britam_db->table('EnquiryResponse')
                ->where('MessageId', $message_id)
                ->update([
                    'ClientResponse' => $client_response,
                    'dola' => date('Y-m-d H:i:s'),
                    'altered_by' => 'API',

                ]);

            return response()->json([
                'success' => true,
                'message' => 'Response submitted successfully',
                'data' => []
            ], 200);

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'An error occured',
                'data' => $th->getMessage()
            ], 500);
        }

    }

    public function getEnquiryiesUnderClient(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'portal_user_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            //code...

            $client_id = $request->input('portal_user_id');

            $contact_person_id = $this->britam_db->table('PortalUserLoginInfo')
                ->where('Id', $client_id)
                ->first()->ContactPerson;

            // $enquiries = $this->britam_db->table('PortalEnquiries')
            //     ->where('Client', $client_id)
            //     ->get();

            //
            $enquiries = $this->britam_db->table('PortalEnquiries as e')
                ->join('PortalEnquiryMessages as p', 'p.EnquiryId', '=', 'e.Id')
                ->leftJoin('PortalEnquiryFiles as pf', 'pf.MessageId', '=', 'p.Id')
                //responses
                ->leftJoin('EnquiryResponse as er', 'er.MessageId', '=', 'p.Id')
                ->where('e.ContactPerson', $contact_person_id)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Enquiries fetched successfully',
                'data' => $enquiries
            ], 200);

        } catch (\Throwable $th) {
            //throw $th;

            return response()->json([
                'success' => false,
                'message' => 'An error occured',
                'data' => $th->getMessage()
            ], 500);
        }
    }

    public function getEnquiryThread(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'enquiry_id' => 'required'
        ]);


        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $enquiry_id = $request->input('enquiry_id');

            // need a thread for each inquiry show the messages and response threads
            $enquiry = $this->britam_db->table('PortalEnquiries')
                ->where('Id', $enquiry_id)
                ->first();

            if (!$enquiry) {
                return response()->json([
                    'success' => false,
                    'message' => 'Enquiry not found',
                    'data' => []
                ], 404);
            } else {

                $enquiry_thread = $this->britam_db->table('PortalEnquiries as e')
                    ->join('PortalEnquiryMessages as p', 'p.EnquiryId', '=', 'e.Id')
                    ->leftJoin('PortalEnquiryFiles as pf', 'pf.MessageId', '=', 'p.Id')
                    ->leftJoin('EnquiryResponse as er', 'er.MessageId', '=', 'p.Id')
                    ->where('e.Id', $enquiry_id)
                    ->get();
            }

            return response()->json([
                'success' => true,
                'message' => 'Enquiry responses fetched successfully',
                'data' => $enquiry_thread
            ], 200);

        } catch (\Throwable $th) {
            //throw $th;

            return response()->json([
                'success' => false,
                'message' => 'An error occured',
                'data' => $th->getMessage()
            ], 500);
        }
    }
}
