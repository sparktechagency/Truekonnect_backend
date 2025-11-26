<?php

namespace App\Http\Controllers\API;

use App\Notifications\UserNotification;
use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\SupportTicket;
use App\Mail\SupportTicketMail;
use Illuminate\Http\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Validation\ValidationException;

class SupportController extends Controller
{
    public function newticket(Request $request){
       try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return $this->errorResponse('User Not Found',Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'subject' => 'required|string|max:255',
                'reason' => 'required|string',
                'attachment' => 'nullable|file|mimetypes:image/jpeg,image/jpg,image/png,image/webp,pdf,doc,docx|max:20480',
            ]);

            $filePath = null;
            if ($request->hasFile('attachment')) {
                $filePath = $request->file('attachment')->store('ticket_files', 'public');
            }

            $ticket = SupportTicket::create([
                'user_id' => $user->id,
                'subject' => $validated['subject'],
                'issue' => $validated['reason'],
                'attachments' => $filePath ?? '',
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
            return $this->successResponse($ticket,'Ticket Raised',Response::HTTP_OK);
        } catch (JWTException $e) {
           return $this->errorResponse('Invalid Token',$e->getMessage(),Response::HTTP_UNAUTHORIZED);
        } catch (ValidationException $e) {
           return $this->errorResponse('Validation Failed'.$e->getMessage(),Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
           return $this->errorResponse('Something went wrong '.$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function allPendingTickets()
    {
        try {
            $tickets = SupportTicket::with('ticketcreator:id,name,email,phone,avatar,role')->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($tickets->isEmpty()) {
                return $this->successResponse($tickets,'No pending tickets found.',Response::HTTP_OK);
            }

            return $this->successResponse($tickets,'Pending tickets found.',Response::HTTP_OK);

        } catch (JWTException $e) {
            return $this->errorResponse('Something went wrong '.$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong '.$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function answerTicket(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return $this->errorResponse('User not found  ',Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'reply' => 'required|string',
                'user_id' => 'required|exists:users,id',
            ]);

            $ticket = SupportTicket::find($id);
            $customer = User::find($validated['user_id']);
            if (!$ticket) {
                return $this->errorResponse('Ticket not found',Response::HTTP_NOT_FOUND);
            }

            if (!$customer) {
                return $this->errorResponse('Customer not found',Response::HTTP_NOT_FOUND);
            }
            if ($ticket->status=='answered') {
                return $this->errorResponse('Ticket already answered',Response::HTTP_CONFLICT);
            }elseif ($ticket->status=='admin_review') {
                return $this->errorResponse('Ticket already move to admin review',Response::HTTP_CONFLICT);
            }else{
                $reply=$validated['reply'];
                $ticket->answer = $validated['reply'];
                $ticket->reviewed_by  = $user->id;
                $ticket->status = 'answered';
                $ticket->save();

                $email=$customer->email;
                Mail::to($email)->send(new SupportTicketMail($customer, $reply,$ticket));

                $title = 'Your Support Ticket Has Been Answered';
                $body  = "Hello {$customer->name}, your support ticket '{$ticket->subject}' has been answered. Reply: {$reply}";

                $customer->notify(new UserNotification($title, $body));
                return $this->successResponse($ticket,'Ticket Answered',Response::HTTP_OK);
            }

        } catch (JWTException $e) {
            return $this->errorResponse('Something went wrong '.$e->getMessage(),Response::HTTP_UNAUTHORIZED);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation Failed'.$e->getMessage(),Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong '.$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function moveToAdmin(Request $request, $ticket_id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return $this->errorResponse('User not found  ',Response::HTTP_NOT_FOUND);
            }

            // Validate reason
            $validated = $request->validate([
                'reason' => 'required|string|min:5',
            ]);

            $ticket = SupportTicket::find($ticket_id);
            if (!$ticket) {
                return $this->errorResponse('Ticket not found',Response::HTTP_NOT_FOUND);
            }
            if ($ticket->status=='answered') {
                return $this->errorResponse('Ticket already answered',Response::HTTP_CONFLICT);
            }elseif ($ticket->status=='admin_review') {
                return $this->errorResponse('Ticket already move to admin review',Response::HTTP_CONFLICT);
            }else{
                $ticket->status = 'admin_review';
                $ticket->admin_reason = $validated['reason'];
                $ticket->save();

                $title = 'Ticket Moved to Admin';
                $body = 'Your support ticket has been moved to admin review. Reason: '. $ticket->admin_reason;

                $ticket->ticketcreator->notify(new UserNotification($title, $body));

                return $this->successResponse($ticket,'Ticket Moved to Admin',Response::HTTP_OK);
            }


        } catch (JWTException $e) {
            return $this->errorResponse('Something went wrong '.$e->getMessage(),Response::HTTP_UNAUTHORIZED);

        } catch (ValidationException $e) {
            return $this->errorResponse('Validation Failed'.$e->getMessage(),Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong '.$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function allAdminReviewTickets()
    {
        try {
            // Fetch all pending tickets (you can filter by user if needed)
            $tickets = SupportTicket::with(['ticketcreator:id,name,email,phone,avatar,role','reviewer:id,name,email,phone,avatar,role'])->where('status', 'admin_review')
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            if ($tickets->isEmpty()) {
                return $this->successResponse(null,'Ticket not found',Response::HTTP_OK);
            }

            return $this->successResponse($tickets,'Ticket Reviewed',Response::HTTP_OK);

        } catch (JWTException $e) {
            return $this->errorResponse('Invalid expired token '.$e->getMessage(),Response::HTTP_UNAUTHORIZED);

        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong '.$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
     public function adminAnswerTicket(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return $this->errorResponse('User not found  ',Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'reply' => 'required|string',
            ]);

            $ticket = SupportTicket::find($id);
            if (!$ticket) {
                return $this->errorResponse('Ticket not found',Response::HTTP_NOT_FOUND);
            }
            $customer = User::find($ticket->user_id);
            if (!$customer) {
                return $this->errorResponse('User not found',Response::HTTP_NOT_FOUND);
            }
            if ($ticket->status=='answered') {
                return $this->errorResponse('Ticket already answered',Response::HTTP_CONFLICT);
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

                return $this->successResponse($ticket,'Ticket Answered',Response::HTTP_OK);
            }

        } catch (JWTException $e) {
            return $this->errorResponse('Something went wrong '.$e->getMessage(),Response::HTTP_UNAUTHORIZED);

        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed'.$e->getMessage(),Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong '.$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
