<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskPerformer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserLeaderboard extends Controller
{
//    public function performerLeaderboard()
//    {
//        try {
//            $user = JWTAuth::parseToken()->authenticate();
//            if ($user->role == 'performer') {
//                $leaderboard = TaskPerformer::
//                    join('users', 'users.id', '=', 'task_performers.user_id')
//                    ->select('task_performers.user_id', DB::raw('COUNT(*) as completed_tasks'))
////                    ->select('users.*','task_performers.*')
//                    ->where('task_performers.status', 'completed')
//                    ->groupBy('user_id')
//                    ->orderByDesc('completed_tasks')
//                    ->paginate(10);
//
//                $rank = ($leaderboard->currentPage() - 1) * $leaderboard->perPage() + 1;
//                $previousTasks = null;
////                $rankedData = [];
//                $currentUser = null;
//                $userId = $user->id;
//
////                foreach ($leaderboard as $key => $item) {
////                    if ($previousTasks !== null && $item->completed_tasks < $previousTasks) {
////                        $rank = $key + 1;
////                    }
////                    $item->rank = $rank;
////                    $previousTasks = $item->completed_tasks;
////
////                    if ($item->user_id == $userId) {
////                        $currentUser = $item;
////                        $currentUser->performer->name = 'You';
////                    } else {
////                        $rankedData[] = $item;
////                    }
////                }
////
////                if (!$currentUser) {
////                    $lastRank = $leaderboard->count() > 0 ? $leaderboard->count() + 1 : 1;
////
////                    $currentUser = (object)[
////                        'user_id' => $userId,
////                        'completed_tasks' => 0,
////                        'performer' => (object)['name' => 'You'],
////                        'rank' => $lastRank,
////                    ];
////                }
//
//                foreach ($leaderboard as $item) {
//                    if ($previousTasks !== null && $item->completed_tasks < $previousTasks) {
//                        $rank++;
//                    }
//
//                    $item->rank = $rank;
//                    $previousTasks = $item->completed_tasks;
//
//                    if ($item->user_id == $userId) {
//                        $item->performer->name = 'You';
//                        $currentUser = $item;
//                    }
//                }
//
////                array_unshift($rankedData, $currentUser);
//            }
//            else{
//                $leaderboard = Task::
//                    join('users', 'users.id', '=', 'tasks.user_id')
//                    ->select('tasks.user_id', DB::raw('COUNT(*) as completed_tasks'))
//                    ->where('tasks.status', 'completed')
//                    ->groupBy('user_id')
//                    ->orderByDesc('completed_tasks')
////                    ->with('creator:id,name,avatar')
//                    ->paginate(10);
//
//                $rank = ($leaderboard->currentPage() - 1) * $leaderboard->perPage() + 1;
//                $previousTasks = null;
////                $rankedData = [];
//                $currentUser = null;
//                $userId = $user->id;
//
//                foreach ($leaderboard as $item) {
//                    if ($previousTasks !== null && $item->completed_tasks < $previousTasks) {
//                        $rank++;
//                    }
//
//                    $item->rank = $rank;
//                    $previousTasks = $item->completed_tasks;
//
//                    if ($item->user_id == $userId) {
//                        $item->performer->name = 'You';
//                        $currentUser = $item;
//                    }
//                }
//            }
//
//            return $this->successResponse(['current_user' => $currentUser,
//    'leaderboard' => $leaderboard], 'Leaderboard', Response::HTTP_OK);
//        } catch (\Exception $e) {
//            return $this->errorResponse('Something went wrong',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
//        }
//    }

    public function performerLeaderboard()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user->id;

            if ($user->role == 'performer') {

                $userId = auth()->id();


                $baseQuery = DB::table('users')->whereIn('role',['performer','brand'])
                    ->leftJoin('task_performers', function ($join) {
                        $join->on('task_performers.user_id', '=', 'users.id')
                            ->where('task_performers.status', 'completed');
                    })
                    ->groupBy('users.id', 'users.name', 'users.avatar')
                    ->select(
                        'users.id as user_id',
                        'users.name',
                        'users.avatar',
                        DB::raw('COUNT(task_performers.id) as completed_tasks'),
                        DB::raw('DENSE_RANK() OVER (ORDER BY COUNT(task_performers.id) DESC) as rank')
                    );



                $currentUser = (clone $baseQuery)
                    ->where('users.id', $userId)
                    ->first();
                if ($currentUser) {
                    $currentUser->name = 'You';
                }

                $leaderboard = (clone $baseQuery)
                    ->orderBy('rank')
                    ->paginate(10);

                $leaderboard->getCollection()->transform(function ($user) use ($userId) {
                    if ($user->user_id == $userId) {
                        $user->name = 'You';
                    }
                    $user->avatar = $user->avatar ?? 'avatars/default_avatar.png';
                    return $user;
                });
            }
            else {

                /* ---------- ADMIN / OTHER ROLE ---------- */

                $userId = auth()->id();
                $rankedQuery = DB::table('users')->whereIn('role',['performer','brand'])
                    ->leftJoin('tasks', function ($join) {
                        $join->on('tasks.user_id', '=', 'users.id')
                            ->where('tasks.status', 'completed');
                    })
                    ->groupBy('users.id', 'users.name', 'users.avatar')
                    ->select(
                        'users.id as user_id',
                        'users.name',
                        'users.avatar',
                        DB::raw('COUNT(tasks.id) as completed_tasks'),
                        DB::raw('DENSE_RANK() OVER (ORDER BY COUNT(tasks.id) DESC) as rank')
                    );
                $currentUser = DB::query()
                    ->fromSub($rankedQuery, 'ranked_users')
                    ->where('user_id', $userId)
                    ->first();

                if ($currentUser) {
                    $currentUser->name = 'You';
                    $currentUser->avatar = $currentUser->avatar ?? 'avatars/default_avatar.png';
                }
                $leaderboard = DB::query()
                    ->fromSub($rankedQuery, 'ranked_users')
                    ->orderBy('rank')
                    ->paginate(10);

                $leaderboard->getCollection()->transform(function ($user) use ($userId) {
                    if ($user->user_id == $userId) {
                        $user->name = 'You';
                    }
                    $user->avatar = $user->avatar ?? 'avatars/default_avatar.png';
                    return $user;
                });
//                $userId = auth()->id();
//
//                $baseQuery = DB::table('users')
//                    ->leftJoin('tasks', function ($join) {
//                        $join->on('tasks.user_id', '=', 'users.id')
//                            ->where('tasks.status', 'completed');
//                    })
//                    ->groupBy('users.id', 'users.name', 'users.avatar')
//                    ->select(
//                        'users.id as user_id',
//                        'users.name',
//                        'users.avatar',
//                        DB::raw('COUNT(tasks.id) as completed_tasks'),
//                        DB::raw('DENSE_RANK() OVER (ORDER BY COUNT(tasks.id) DESC) as `rank`')
//                    );
//                $currentUser = (clone $baseQuery)
//                    ->where('users.id', $userId)
//                    ->first();
//                if ($currentUser) {
//                    $currentUser->name = 'You';
//                }
//                $leaderboard = (clone $baseQuery)
//                    ->orderBy('rank')
//                    ->paginate(10);
//
//                $leaderboard->getCollection()->transform(function ($user) use ($userId) {
//                    if ($user->user_id == $userId) {
//                        $user->name = 'You';
//                    }
//                    $user->avatar = $user->avatar ?? 'avatars/default_avatar.png';
//                    return $user;
//                });
            }

            return $this->successResponse([
                'current_user' => $currentUser,
                'leaderboard'  => $leaderboard
            ], 'Leaderboard', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Something went wrong',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
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
