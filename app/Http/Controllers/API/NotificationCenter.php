<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class NotificationCenter extends Controller
{
    public function getNotification()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $notifications = $user->notifications->map(function ($n) {
                return [
                    'id' => $n->id,
                    'title' => $n->data['title'] ?? null,
                    'body' => $n->data['body'] ?? null,
                    'read_at' => $n->read_at ? $n->read_at->format('M d, Y h:i:s A') : null,
                    'created_at' => $n->created_at->format('M d, Y h:i:s A'),
                ];
            });

            return $this->successResponse($notifications,'Notification retrieve successfully.', Response::HTTP_OK);
        }
        catch (TokenExpiredException $exception){
            return $this->errorResponse('Token Expired '. $exception->getMessage(), Response::HTTP_UNAUTHORIZED);
        }
        catch (TokenInvalidException $exception){
            return $this->errorResponse('Token Invalid '. $exception->getMessage(), Response::HTTP_UNAUTHORIZED);
        }
        catch (JWTException $exception){
            return $this->errorResponse('Token Missing '. $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function markAsRead(Request $request, $id){
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $notification = $user->notifications()->find($id);

            $notification->markAsRead();

            $formattedNotification = [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? null,
                'body' => $notification->data['body'] ?? null,
                'read_at' => $notification->read_at ? $notification->read_at->format('M d, Y h:i:s A') : null,
                'created_at' => $notification->created_at->format('M d, Y h:i:s A'),
            ];

            return $this->successResponse($formattedNotification, 'Notification marked as read.',Response::HTTP_OK);
        }catch (TokenExpiredException $exception){
            return $this->errorResponse('Token Expired '. $exception->getMessage(), Response::HTTP_UNAUTHORIZED);
        }
        catch (TokenInvalidException $exception){
            return $this->errorResponse('Token Invalid '. $exception->getMessage(), Response::HTTP_UNAUTHORIZED);
        }
        catch (JWTException $exception){
            return $this->errorResponse('Token Missing '. $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        catch (\Exception $e){
            return $this->errorResponse('Something went wrong.' .$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function markAllAsRead()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $user->unreadNotifications->markAsRead();

            $formattedNotifications = $user->notifications->map(function($n) {
                return [
                    'id' => $n->id,
                    'title' => $n->data['title'] ?? null,
                    'body' => $n->data['body'] ?? null,
                    'read_at' => $n->read_at ? $n->read_at->format('M d, Y h:i:s A') : null,
                    'created_at' => $n->created_at->format('M d, Y h:i:s A'),
                ];
            });

            return $this->successResponse($formattedNotifications, 'Notifications marked as read.',Response::HTTP_OK);
        } catch (TokenExpiredException $exception){
            return $this->errorResponse('Token Expired '. $exception->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (TokenInvalidException $exception){
            return $this->errorResponse('Token Invalid '. $exception->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (JWTException $exception){
            return $this->errorResponse('Token Missing '. $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $exception){
            return $this->errorResponse('Something went wrong. '.$exception->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteNotification($id)
    {
        try{
            $user = JWTAuth::parseToken()->authenticate();

            $notification = $user->notifications()->find($id);

            if(!$notification){
                return $this->errorResponse('Notification not found', Response::HTTP_NOT_FOUND);
            }

            $notification->delete();

            return $this->successResponse(null, 'Notification deleted successfully.',Response::HTTP_OK);
        } catch (TokenExpiredException $exception){
            return $this->errorResponse('Token Expired '. $exception->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (TokenInvalidException $exception){
            return $this->errorResponse('Token Invalid '. $exception->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (JWTException $exception){
            return $this->errorResponse('Token Missing '. $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e){
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteAllNotifications()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $user->notifications()->delete();

            return $this->successResponse(null, 'All notifications deleted successfully.', Response::HTTP_OK);
        } catch (TokenExpiredException $exception){
            return $this->errorResponse('Token Expired '. $exception->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (TokenInvalidException $exception){
            return $this->errorResponse('Token Invalid '. $exception->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (JWTException $exception){
            return $this->errorResponse('Token Missing '. $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e){
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
