<?php

namespace App\Http\Controllers\API;

use App\Notifications\UserNotification;
use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\SupportTicket;
use App\Mail\SupportTicketMail;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Validation\ValidationException;

class SupportController extends Controller
{
    public function newticket(Request $request){
       try {
            // Parse and authenticate JWT token
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found or token invalid.',
                ], 401);
            }

            // Validate user input
            $validated = $request->validate([
                'subject' => 'required|string|max:255',
                'reason' => 'required|string',
                'attachment' => 'nullable|file|mimetypes:image/jpeg,image/jpg,image/png,image/webp,pdf,doc,docx|max:20480',
            ]);

            // Handle file upload
            $filePath = null;
            if ($request->hasFile('attachment')) {
                $filePath = $request->file('attachment')->store('ticket_files', 'public');
            }

            // Create the support ticket
            $ticket = SupportTicket::create([
                'user_id' => $user->id,
                'subject' => $validated['subject'],
                'issue' => $validated['reason'], // make sure your DB column is 'issue'
                'attachments' => $filePath ?? '', // or 'attachment' if that's your column name
                'status' => 'pending',
            ]);

            $allUser = User::where('id', '!=', $user->id)
                ->whereIn('role', ['admin','reviewer'])
                ->get();

            $title = 'New Ticket Raised';
            $body  = "A new support ticket has been raised by {$user->name}.\nTicket Id: {$ticket->id}\nSubject: {$ticket->subject}\nIssue: {$ticket->issue}";

           foreach ($allUser as $notifyUser) {
               $notifyUser->notify(new UserNotification($title, $body));
           }
            return response()->json([
                'success' => true,
                'message' => 'Support ticket created successfully.',
                'data' => $ticket,
            ], 201);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token. Please login again.',
                'error' => $e->getMessage(),
            ], 401);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while creating the ticket.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function allPendingTickets()
    {
        try {
            // Fetch all pending tickets (you can filter by user if needed)
            $tickets = SupportTicket::with('ticketcreator:id,name,email,phone,avatar,role')->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($tickets->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No pending tickets found.',
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pending tickets fetched successfully.',
                'data' => $tickets,
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token. Please login again.',
                'error' => $e->getMessage(),
            ], 401);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching pending tickets.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function answerTicket(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found or token invalid.',
                ], 401);
            }

            // Validate reply input
            $validated = $request->validate([
                'reply' => 'required|string',
                'user_id' => 'required|exists:users,id',
            ]);

            // Find ticket
            $ticket = SupportTicket::find($id);
            $customer = User::find($validated['user_id']);
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found.',
                ], 404);
            }

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }
            if ($ticket->status=='answered') {
                return response()->json([
                    'status' => false,
                    'message' => 'This support ticket has already been answered.',
                ], 409);
            }elseif ($ticket->status=='admin_review') {
                return response()->json([
                    'status' => false,
                    'message' => 'This support ticket has already been move to admin review.',
                ], 409);
            }else{
                $reply=$validated['reply'];
                $ticket->answer = $validated['reply'];
                $ticket->reviewed_by  = $user->id; // optional
                $ticket->status = 'answered';
                $ticket->save();

                $email=$customer->email;
                Mail::to($email)->send(new SupportTicketMail($customer, $reply,$ticket));

                $title = 'Your Support Ticket Has Been Answered';
                $body  = "Hello {$customer->name}, your support ticket '{$ticket->subject}' has been answered. Reply: {$reply}";

                $customer->notify(new UserNotification($title, $body));
                return response()->json([
                    'success' => true,
                    'message' => 'Ticket answered successfully.',
                    'data' => $ticket,
                ], 200);
            }

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token. Please login again.',
                'error' => $e->getMessage(),
            ], 401);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while answering the ticket.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function moveToAdmin(Request $request, $ticket_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found or token invalid.',
                ], 401);
            }

            // Validate reason
            $validated = $request->validate([
                'reason' => 'required|string|min:5',
            ]);

            $ticket = SupportTicket::find($ticket_id);
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found.',
                ], 404);
            }
            if ($ticket->status=='answered') {
                return response()->json([
                    'status' => false,
                    'message' => 'This support ticket has already been answered.',
                ], 409);
            }elseif ($ticket->status=='admin_review') {
                return response()->json([
                    'status' => false,
                    'message' => 'This support ticket has already been move to admin review.',
                ], 409);
            }else{
                $ticket->status = 'admin_review';
                $ticket->admin_reason = $validated['reason']; // make sure this column exists
                $ticket->save();

                $title = 'Ticket Moved to Admin';
                $body = 'Your support ticket has been moved to admin review. Reason: '. $ticket->admin_reason;

                $ticket->ticketcreator->notify(new UserNotification($title, $body));

                return response()->json([
                    'success' => true,
                    'message' => 'Ticket moved to admin successfully.',
                    'data' => $ticket,
                ], 200);
            }


        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token. Please login again.',
                'error' => $e->getMessage(),
            ], 401);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while moving the ticket.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function allAdminReviewTickets()
    {
        try {
            // Fetch all pending tickets (you can filter by user if needed)
            $tickets = SupportTicket::with(['ticketcreator:id,name,email,phone,avatar,role','reviewer:id,name,email,phone,avatar,role'])->where('status', 'admin_review')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($tickets->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No pending tickets found.',
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Admin review tickets fetched successfully.',
                'data' => $tickets,
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token. Please login again.',
                'error' => $e->getMessage(),
            ], 401);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching pending tickets.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
     public function adminAnswerTicket(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found or token invalid.',
                ], 401);
            }

            // Validate reply input
            $validated = $request->validate([
                'reply' => 'required|string',
            ]);

            // Find ticket
            $ticket = SupportTicket::find($id);
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found.',
                ], 404);
            }
            $customer = User::find($ticket->user_id);
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }
            if ($ticket->status=='answered') {
                return response()->json([
                    'status' => false,
                    'message' => 'This support ticket has already been answered.',
                ], 409);
            }elseif ($ticket->status=='admin_review') {

                $reply=$validated['reply'];
                $ticket->answer = $reply;
                $ticket->reviewed_by  = $user->id; // optional
                $ticket->status = 'answered';
                $ticket->save();

                $email=$customer->email;
                Mail::to($email)->send(new SupportTicketMail($customer, $reply,$ticket));

                $title = 'Ticket Answered';
                $body = 'Your support ticket has been answered. Reply: '. $reply;

                $customer->notify(new UserNotification($title, $body));

                return response()->json([
                    'success' => true,
                    'message' => 'Ticket answered successfully.',
                    'data' => $ticket,
                ], 200);
            }

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token. Please login again.',
                'error' => $e->getMessage(),
            ], 401);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while answering the ticket.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
