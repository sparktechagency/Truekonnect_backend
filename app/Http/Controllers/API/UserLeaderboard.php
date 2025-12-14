<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskPerformer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserLeaderboard extends Controller
{
    public function performerLeaderboard()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if ($user->role == 'performer') {
                $leaderboard = TaskPerformer::select('user_id', DB::raw('COUNT(*) as completed_tasks'))
                    ->where('status', 'completed')
                    ->groupBy('user_id')
                    ->orderByDesc('completed_tasks')
                    ->with('performer:id,name,avatar')
                    ->paginate(10);

                $rank = ($leaderboard->currentPage() - 1) * $leaderboard->perPage() + 1;
                $previousTasks = null;
//                $rankedData = [];
                $currentUser = null;
                $userId = $user->id;

//                foreach ($leaderboard as $key => $item) {
//                    if ($previousTasks !== null && $item->completed_tasks < $previousTasks) {
//                        $rank = $key + 1;
//                    }
//                    $item->rank = $rank;
//                    $previousTasks = $item->completed_tasks;
//
//                    if ($item->user_id == $userId) {
//                        $currentUser = $item;
//                        $currentUser->performer->name = 'You';
//                    } else {
//                        $rankedData[] = $item;
//                    }
//                }
//
//                if (!$currentUser) {
//                    $lastRank = $leaderboard->count() > 0 ? $leaderboard->count() + 1 : 1;
//
//                    $currentUser = (object)[
//                        'user_id' => $userId,
//                        'completed_tasks' => 0,
//                        'performer' => (object)['name' => 'You'],
//                        'rank' => $lastRank,
//                    ];
//                }

                foreach ($leaderboard as $item) {
                    if ($previousTasks !== null && $item->completed_tasks < $previousTasks) {
                        $rank++;
                    }

                    $item->rank = $rank;
                    $previousTasks = $item->completed_tasks;

                    if ($item->user_id == $userId) {
                        $item->performer->name = 'You';
                        $currentUser = $item;
                    }
                }

//                array_unshift($rankedData, $currentUser);
            }
            else{
                $leaderboard = Task::select('user_id', DB::raw('COUNT(*) as completed_tasks'))
                    ->where('status', 'completed')
                    ->groupBy('user_id')
                    ->orderByDesc('completed_tasks')
                    ->with('creator:id,name,avatar')
                    ->paginate(10);

                $rank = ($leaderboard->currentPage() - 1) * $leaderboard->perPage() + 1;
                $previousTasks = null;
//                $rankedData = [];
                $currentUser = null;
                $userId = $user->id;

                foreach ($leaderboard as $item) {
                    if ($previousTasks !== null && $item->completed_tasks < $previousTasks) {
                        $rank++;
                    }

                    $item->rank = $rank;
                    $previousTasks = $item->completed_tasks;

                    if ($item->user_id == $userId) {
                        $item->performer->name = 'You';
                        $currentUser = $item;
                    }
                }
            }

            return $this->successResponse(['current_user' => $currentUser,
    'leaderboard' => $leaderboard], 'Leaderboard', Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


//    public function brandLeaderboard()
//    {
//        try {
//            $user = JWTAuth::parseToken()->authenticate();
//
//
//            return $this->successResponse($rankedData, 'Leaderboard', Response::HTTP_OK);
//        } catch (\Exception $e) {
//            return $this->errorResponse('Something went wrong',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
//        }
//    }

}
