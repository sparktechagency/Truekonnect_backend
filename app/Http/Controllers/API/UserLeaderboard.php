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

//                $leaderboard = TaskPerformer::query()
//                    ->join('users', 'users.id', '=', 'task_performers.user_id')
//                    ->select(
//                        'users.id as user_id',
//                        'users.name',
//                        'users.avatar',
//                        DB::raw('COUNT(task_performers.id) as completed_tasks')
//                    )
//                    ->where('task_performers.status', 'completed')
//                    ->groupBy('users.id', 'users.name', 'users.avatar')
//                    ->orderByDesc('completed_tasks')
//                    ->paginate(10);
//
//
//
//
//
//
//
//                $rank = ($leaderboard->currentPage() - 1) * $leaderboard->perPage();
//                $previousTasks = null;
//                $currentUser = null;
//
//                foreach ($leaderboard as $item) {
//                    if ($previousTasks === null || $item->completed_tasks < $previousTasks) {
//                        $rank++;
//                    }
//
//                    $item->rank = $rank;
//                    $previousTasks = $item->completed_tasks;
//
//                    if ($item->user_id == $userId) {
//                        $item->name = 'You';
//                        $currentUser = $item;
//                    }
//                }
//
//                /*
//                |--------------------------------------------------------------------------
//                | If current user NOT in this page → fetch separately
//                |--------------------------------------------------------------------------
//                */
//
//
//
//                if (!$currentUser) {
//
//                    // completed tasks of current user
//                    $myCompletedTasks = TaskPerformer::where('user_id', $userId)
////                        ->join('users','users.id','=','task_performers.user_id')
////                        ->select('users.*')
//                        ->where('task_performers.status', 'completed')
//                        ->count();
//
//                    // global rank
//                    $myRank = TaskPerformer::select('user_id', DB::raw('COUNT(*) as total'))
//                            ->where('status', 'completed')
//                            ->groupBy('user_id')
//                            ->having('total', '>', $myCompletedTasks)
//                            ->count() + 1;
//
//                    $currentUser = (object)[
//                        'user_id' => $userId,
//                        'completed_tasks' => $myCompletedTasks,
//                        'rank' => $myRank,
//                        'name' => 'You',
//                    ];
//                }

                $userId = auth()->id();

                $baseQuery = DB::table('users')
                    ->leftJoin('task_performers', function ($join) {
                        $join->on('task_performers.user_id', '=', 'users.id')
                            ->where('task_performers.status','=', 'completed');
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
//                $leaderboard = TaskPerformer::query()
//                    ->join('users', 'users.id', '=', 'task_performers.user_id')
//                    ->select(
//                        'users.id as user_id',
//                        'users.name',
//                        'users.avatar',
//                        DB::raw('COUNT(task_performers.id) as completed_tasks')
//                    )
//                    ->where('task_performers.status', 'completed')
//                    ->groupBy('users.id', 'users.name', 'users.avatar')
//                    ->orderByDesc('completed_tasks')
//                    ->paginate(10);
//
//                /*
//                |--------------------------------------------------------------------------
//                | 2. Pagination helpers
//                |--------------------------------------------------------------------------
//                */
//
//                $currentPage = $leaderboard->currentPage();
//                $offset      = ($currentPage - 1) * 10;
//
//                /*
//                |--------------------------------------------------------------------------
//                | 3. Get LAST completed_tasks from previous page
//                |--------------------------------------------------------------------------
//                */
//
//                $lastTasksFromPrevPage = null;
//
//                if ($offset > 0) {
//                    $lastTasksFromPrevPage = DB::table(DB::raw("
//            (
//                SELECT COUNT(*) as total
//                FROM task_performers
//                WHERE status = 'completed'
//                GROUP BY user_id
//                ORDER BY total DESC
//                LIMIT {$offset}, 1
//            ) as t
//        "))->value('total');
//                }
//
//                /*
//                |--------------------------------------------------------------------------
//                | 4. Count DISTINCT completed_tasks BEFORE this page
//                |--------------------------------------------------------------------------
//                */
//
//                $distinctRanksBefore = DB::table(DB::raw("
//        (
//            SELECT COUNT(*) as total
//            FROM task_performers
//            WHERE status = 'completed'
//            GROUP BY user_id
//            ORDER BY total DESC
//            LIMIT {$offset}
//        ) as t
//    "))
//                    ->select(DB::raw('COUNT(DISTINCT total) as cnt'))
//                    ->value('cnt') ?? 0;
//
//                /*
//                |--------------------------------------------------------------------------
//                | 5. Assign DENSE RANK (correct across pages)
//                |--------------------------------------------------------------------------
//                */
//
//                $rank          = $distinctRanksBefore;
//                $previousTasks = $lastTasksFromPrevPage;
//                $currentUser   = null;
//
//                foreach ($leaderboard as $item) {
//
//                    // increase rank ONLY when completed_tasks decreases
//                    if ($previousTasks === null || $item->completed_tasks < $previousTasks) {
//                        $rank++;
//                    }
//
//                    $item->rank = $rank;
//                    $previousTasks = $item->completed_tasks;
//
//                    if ($item->user_id == $userId) {
//                        $item->name = 'You';
//                        $currentUser = $item;
//                    }
//                }
//
//                /*
//                |--------------------------------------------------------------------------
//                | 6. If logged-in user is NOT on this page
//                |--------------------------------------------------------------------------
//                */
//
//                if (!$currentUser) {
//
//                    $myCompletedTasks = TaskPerformer::where('user_id', $userId)
//                        ->where('status', 'completed')
//                        ->count();
//
//                    // DENSE RANK calculation
//                    $myRank = DB::table(DB::raw("
//            (
//                SELECT COUNT(*) as total
//                FROM task_performers
//                WHERE status = 'completed'
//                GROUP BY user_id
//            ) as t
//        "))
//                            ->where('total', '>', $myCompletedTasks)
//                            ->distinct()
//                            ->count('total') + 1;
//
//                    $currentUser = (object) [
//                        'user_id' => $userId,
//                        'name' => 'You',
//                        'avatar' => auth()->user()->avatar ?? null,
//                        'completed_tasks' => $myCompletedTasks,
//                        'rank' => $myRank,
//                    ];
//                }

            }
            else {

                /* ---------- ADMIN / OTHER ROLE ---------- */

                $userId = auth()->id();

                $baseQuery = DB::table('users')
                    ->leftJoin('tasks', function ($join) {
                        $join->on('tasks.user_id', '=', 'users.id')
                            ->where('tasks.status','=', 'completed');
                    })
                    ->groupBy('users.id', 'users.name', 'users.avatar')
                    ->select(
                        'users.id as user_id',
                        'users.name',
                        'users.avatar',
                        DB::raw('COUNT(tasks.id) as completed_tasks'),
                        DB::raw('DENSE_RANK() OVER (ORDER BY COUNT(tasks.id) DESC) as rank')
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
//                $leaderboard->setCollection(
//                    collect([$currentUser])->merge($leaderboard->getCollection())
//                );

//                $leaderboard = Task::query()
//                    ->join('users', 'users.id', '=', 'tasks.user_id')
//                    ->select(
//                        'users.id as user_id',
//                        'users.name',
//                        'users.avatar',
//                        DB::raw('COUNT(tasks.id) as completed_tasks')
//                    )
//                    ->where('tasks.status', 'completed')
//                    ->groupBy('users.id', 'users.name', 'users.avatar')
//                    ->orderByDesc('completed_tasks')
//                    ->paginate(10);
//
//                $currentUser = null;
//                $rank = 0;
//                $lastTaskCount = null;
//                $indexOffset = ($leaderboard->currentPage() - 1) * $leaderboard->perPage();
//
//                foreach ($leaderboard as $index => $item) {
//
//                    if ($lastTaskCount !== $item->completed_tasks) {
//                        $rank = $indexOffset + $index + 1;
//                        $lastTaskCount = $item->completed_tasks;
//                    }
//
//                    $item->rank = $rank;
//
//                    if ($item->user_id == $userId) {
//                        $item->name = 'You';
//                        $currentUser = $item;
//                    }
//                }
//
//
////                $rank = ($leaderboard->currentPage() - 1) * $leaderboard->perPage();
////                $previousTasks = null;
////                $currentUser = null;
////
////                foreach ($leaderboard as $item) {
////                    if ($previousTasks === null || $item->completed_tasks < $previousTasks) {
////                        $rank++;
////                    }
////
////                    $item->rank = $rank;
////                    $previousTasks = $item->completed_tasks;
////
////                    if ($item->user_id == $userId) {
////                        $item->name = 'You';
////                        $currentUser = $item;
////                    }
////                }
//
//                if (!$currentUser) {
//                    $myCompletedTasks = Task::where('user_id', $userId)
//                        ->where('status', 'completed')
//                        ->count();
//
////                    $myRank = DB::table(DB::raw("
////                                    (
////                                        SELECT user_id, COUNT(*) AS total
////                                        FROM tasks
////                                        WHERE status = 'completed'
////                                        GROUP BY user_id
////                                    ) t
////                                "))
////                            ->where('total', '>', $myCompletedTasks)
////                            ->distinct()
////                            ->count('total') + 1;
////
////
////                    if ($myCompletedTasks === 0) {
////                        $myRank++;
////                    }
//
//                    $myRank = DB::table(DB::raw("
//                        (
//                            SELECT COUNT(*) AS total
//                            FROM tasks
//                            WHERE status = 'completed'
//                            GROUP BY user_id
//                        ) t
//                    "))
//                            ->where('total', '>', $myCompletedTasks)
//                            ->count() + 1;
//
//                    $currentUser = (object) [
//                        'user_id' => $userId,
//                        'completed_tasks' => $myCompletedTasks,
//                        'rank' => $myRank,
//                        'name' => 'You',
//                        'avatar' => null,
//                    ];
//                }
//
//
////                if (!$currentUser) {
////                    $myCompletedTasks = Task::where('user_id', $userId)
////                        ->where('status', 'completed')
////                        ->count();
////
////                    $myRank = Task::select('user_id', DB::raw('COUNT(*) as total'))
////                            ->where('status', 'completed')
////                            ->groupBy('user_id')
////                            ->having('total', '>', $myCompletedTasks)
////                            ->count() + 1;
////
////                    $currentUser = (object)[
////                        'user_id' => $userId,
////                        'completed_tasks' => $myCompletedTasks,
////                        'rank' => $myRank,
////                        'name' => 'You',
////                    ];
////                }
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
