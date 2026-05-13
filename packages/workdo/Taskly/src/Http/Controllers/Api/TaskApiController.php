<?php

namespace Workdo\Taskly\Http\Controllers\Api;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Workdo\Taskly\Entities\ClientProject;
use Workdo\Taskly\Entities\Stage;
use Workdo\Taskly\Entities\Task;
use Workdo\Taskly\Entities\Project;
use Workdo\Taskly\Entities\UserProject;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;
use App\Models\User;
use Workdo\Taskly\Entities\ActivityLog;
use Workdo\Taskly\Entities\SubTask;
use Workdo\Taskly\Entities\Comment;
use Workdo\Taskly\Entities\TaskFile;

/**
 * @group Taskly Tasks
 * Endpoints for task management
 */
class TaskApiController extends Controller
{
    /**
     * List tasks
     *
     * Get list of tasks with optional filters for workspace, project, and status.
     *
     * @bodyParam workspace_id integer optional Workspace ID. Example: 1
     * @bodyParam project_id integer optional Project ID. Example: 5
     * @bodyParam status enum optional Filter by status (Ongoing, Finished, OnHold). Example: Ongoing
     * @bodyParam page integer optional Page number. Example: 1
     * @bodyParam limit integer optional Items per page. Example: 10
     * @response {"status": 1, "data": [...]}
     */
    public function index(Request $request)
    {

        try {

            $validator = \Validator::make(
                $request->all(), [
                    'workspace_id' => 'sometimes|exists:work_spaces,id',
                    'project_id' => 'sometimes',
                    'status' => 'in:Ongoing,Finished,OnHold',
                ]
            );

            if($validator->fails())
            {
                $messages = $validator->getMessageBag();

                return response()->json(['status'=>0, 'message'=>$messages->first()],403);
            }

            $objUser            = Auth::user();
            $currentWorkspace   = $request->workspace_id;
            $projectID          = $request->project_id;

            $tasks = Task::query();

            // Filter by workspace_id if provided
            if ($currentWorkspace) {
                $tasks->where('workspace', '=', $currentWorkspace);
            }

            // Filter by project_id if provided
            if ($projectID) {
                // Validate project access based on user role
                if (Auth::user()->hasRole('client')) {
                    $project = Project::select('projects.*')->join('user_projects', 'projects.id', '=', 'user_projects.project_id')->where('projects.workspace', '=', $currentWorkspace)->where('projects.id', '=', $projectID)->first();
                } else {
                    $project = Project::select('projects.*')->join('user_projects', 'projects.id', '=', 'user_projects.project_id')->where('user_projects.user_id', '=', $objUser->id)->where('projects.workspace', '=', $currentWorkspace)->where('projects.id', '=', $projectID)->first();
                }

                if (!$project) {
                    return response()->json(['status'=>0,'message'=>'Project not found or access denied']);
                }

                $tasks->where('project_id', '=', $project->id);
            } else {
                // If no project_id filter, get tasks from all accessible projects
                if (Auth::user()->hasRole('client')) {
                    $accessibleProjectIds = Project::join('user_projects', 'projects.id', '=', 'user_projects.project_id')
                        ->where('projects.workspace', '=', $currentWorkspace)
                        ->pluck('projects.id');
                } else {
                    $accessibleProjectIds = Project::join('user_projects', 'projects.id', '=', 'user_projects.project_id')
                        ->where('user_projects.user_id', '=', $objUser->id)
                        ->where('projects.workspace', '=', $currentWorkspace)
                        ->pluck('projects.id');
                }

                if ($accessibleProjectIds->isNotEmpty()) {
                    $tasks->whereIn('project_id', $accessibleProjectIds);
                }
            }

                if(isset($request->page) || isset($request->limit))
                {

                    $tasks->limit($request->limit ?? 10);
                    $tasks->offset($request->page ?? 1);

                }

                $tasks =  $tasks->get()->map(function($task){
                                            return [
                                                'id'                => $task->id,
                                                'title'             => $task->title,
                                                'priority'          => $task->priority,
                                                'description'       => $task->description,
                                                'start_date'        => $task->start_date,
                                                'due_date'          => $task->due_date,
                                                'project_id'        => $task->project_id,
                                                'milestone_id'      => (int) $task->milestone_id,
                                                'order'             => $task->order,
                                                'status'            => $task->status,
												'assign_to'         => $task->users()->map(function($user){
																			return [
																				'id'        => $user->id,
																				'name'      => $user->name,
																				'email'     => $user->email,
																				'avatar'    => check_file($user->avatar) ? get_file($user->avatar) : get_file('uploads/users-avatar/avatar.png'),
																			];
																		}),
                                            ];
                                });

                return response()->json(['status' => 1,'data'  => $tasks]);

        } catch (\Exception $e) {
            return response()->json(['status'=>0,'message'=>'something went wrong!!!']);
        }
    }

    /**
     * Get task board
     *
     * Get tasks organized by stages for a project.
     *
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam project_id integer required Project ID. Example: 5
     * @response {"status": 1, "data": [...]}
     */
    public function taskboard(Request $request)
    {
        try {

            $validator = \Validator::make(
                $request->all(), [
                    'workspace_id' => 'required',
                    'project_id' => 'required',
                ]
            );

            if($validator->fails())
            {
                $messages = $validator->getMessageBag();

                return response()->json(['status'=>0, 'message'=>$messages->first()],403);
            }

            $objUser            = Auth::user();
            $currentWorkspace   = $request->workspace_id;
            $projectID          = $request->project_id;

            if (Auth::user()->hasRole('client')) {

                $project = Project::select('projects.*')->join('user_projects', 'projects.id', '=', 'user_projects.project_id')->where('projects.workspace', '=', $currentWorkspace)->where('projects.id', '=', $projectID)->first();

            } else {

                $project = Project::select('projects.*')->join('user_projects', 'projects.id', '=', 'user_projects.project_id')->where('user_projects.user_id', '=', $objUser->id)->where('projects.workspace', '=', $currentWorkspace)->where('projects.id', '=', $projectID)->first();
            }

            $stages = $statusClass = [];

            if ($project) {

                $stages = Stage::orderBy('order')
                                    ->get()
                                    ->map(function($stage){
                                        return (object) [
                                            'id' => $stage->id,
                                            'name' => $stage->name,
                                            'color' => $stage->color,
                                            'complete' => $stage->complete,
                                            'order' => $stage->order,
                                        ];
                                    });

                foreach ($stages as $key => $stage) {

                    $task          = Task::where('project_id', '=', $projectID);

                    if (!Auth::user()->hasRole('client') && !Auth::user()->hasRole('company')) {
                        if (isset($objUser) && $objUser) {
                            $task->whereRaw("find_in_set('" . $objUser->id . "',assign_to)");
                        }
                    }

                    $task->orderBy('order');

                    $stage->tasks = $task->where('status', '=', $stage->id)
                                                ->get()
                                                ->map(function($task) use ($stages , $key){
                                                    return [
                                                        'id'                => $task->id,
                                                        'title'             => $task->title,
                                                        'priority'          => $task->priority,
                                                        'description'       => $task->description,
                                                        'start_date'        => $task->start_date,
                                                        'due_date'          => $task->due_date,
                                                        'project_id'        => $task->project_id,
                                                        'milestone_id'      => (int) $task->milestone_id,
                                                        'order'             => $task->order,
                                                        'previous_stage'    => isset($stages[$key-1]) ? $stages[$key-1]->id : 0,
                                                        'current_stage'     => $stages[$key]->id,
                                                        'next_stage'        => isset($stages[$key+1]) ? $stages[$key+1]->id : 0,
                                                        'assign_to'         => $task->users()->map(function($user){
                                                                                     return [
                                                                                         'id' => $user->id,
                                                                                         'name' => $user->name,
                                                                                        'email' => $user->email,
                                                                                         'avatar' => check_file($user->avatar) ? get_file($user->avatar) : get_file('uploads/users-avatar/avatar.png'),
                                                                                     ];
                                                                                 }),
                                                    ];
                                                });
                }

                return response()->json([

                    'status' => 1,
                    'data'  => $stages
                ]);

            } else {
                return response()->json(['status'=>0,'message'=>'Not found!!!']);
            }

        } catch (\Exception $e) {
            return response()->json(['status'=>0,'message'=>'something went wrong!!!']);
        }
    }

    /**
     * Get task details
     *
     * Get detailed information about a specific task.
     *
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam project_id integer required Project ID. Example: 5
     * @bodyParam task_id integer required Task ID. Example: 10
     * @response {"status": 1, "data": {...}}
     */
    public function taskDetails(Request $request)
    {
        try{

            $validator = \Validator::make(
                $request->all(), [
                    'workspace_id'  => 'required',
                    'project_id'  => 'required',
                    'task_id'       => 'required',
                ]
            );

            if($validator->fails())
            {
                $messages = $validator->getMessageBag();

                return response()->json(['status'=> 0, 'message'=>$messages->first()],403);
            }

            $objUser            = Auth::user();
            $currentWorkspace   = $request->workspace_id;
            $taskID             = $request->task_id;
            $projectID             = $request->project_id;

            $task = Task::where('workspace',$currentWorkspace)->where('project_id',$projectID)->where('id',$taskID)->first();

            $taskDetails = [
                'id'                => $task->id,
                'title'             => $task->title,
                'priority'          => $task->priority,
                'description'       => $task->description,
                'start_date'        => $task->start_date,
                'due_date'          => $task->due_date,
                'project_id'        => $task->project_id,
                'milestone_id'      => (int) $task->milestone_id,
                'order'             => $task->order,
                'status'            => $task->status,
                'assign_to'         => $task->users()->map(function($user){
                                            return [
                                                'id'        => $user->id,
                                                'name'      => $user->name,
                                                'email'     => $user->email,
                                                'avatar'    => check_file($user->avatar) ? get_file($user->avatar) : get_file('uploads/users-avatar/avatar.png'),
                                            ];
                                        }),

            ];

            return response()->json([
                'status' => 1,
                'data'  => $taskDetails,

            ]);

        } catch (\Exception $e) {
            return response()->json(['status'=>0,'message'=>'something went wrong!!!']);
        }
    }

    /**
     * Create or update a task
     *
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam project_id integer required Project ID. Example: 5
     * @bodyParam title string required Task title. Example: Complete project documentation
     * @bodyParam priority string required Priority (Low, Medium, High). Example: High
     * @bodyParam start_date date optional Start date (Y-m-d format). Example: 2024-01-15
     * @bodyParam due_date date optional Due date (Y-m-d format). Example: 2024-01-20
     * @bodyParam assign_to required Assign to user IDs. Example: [1,2,3]
     * @bodyParam task_id integer optional Task ID (for updates). Example: 10
     * @response {"status": 1, "data": {...}}
     */
    public function taskCreateAndUpdate(Request $request)
    {

		$objUser            = Auth::user();
		$projectID          = $request->project_id;
        if($request->task_id){

            $validator = \Validator::make(
                $request->all(), [
                    'workspace_id'  => 'required',
                    'project_id'    => 'required',
                    'title'         => 'required',
                    'priority'      => 'required|in:Low,Medium,High',
                    'start_date'    => 'date_format:Y-m-d',
                    'due_date'      => 'date_format:Y-m-d',
                    'assign_to'     => 'required',
                    'task_id'       => 'required',
                ]
            );

            if($validator->fails()) {
                $messages = $validator->getMessageBag();
                return response()->json(['status' => 0, 'message' => $messages->first()], 403);
            }
            $objUser            = Auth::user();
            $projectID          = $request->project_id;
            $taskID             = $request->task_id;
            $currentWorkspace   = $request->workspace_id;

			if ($objUser->hasRole('client')) {
                $project = Project::where('projects.workspace', '=', $currentWorkspace)->where('projects.id', '=', $projectID)->first();
            } else {
                $project = Project::select('projects.*')->join('user_projects', 'user_projects.project_id', '=', 'projects.id')->where('user_projects.user_id', '=', $objUser->id)->where('projects.workspace', '=', $currentWorkspace)->where('projects.id', '=', $projectID)->first();
            }

            if($request->assign_to){
                $ids = $request->assign_to;
                foreach ($ids as $id) {
                    if (!UserProject::where('project_id',$projectID)->where('user_id', $id)->exists()) {
                        return response()->json(['status' => 0, 'message' => 'User is not assigned to the project ' . $project->name . '. '] , 403);
                    }
                }
            }



            if ($project) {
                $post              = $request->all();
				$post['milestone_id']   = !empty($request->milestone_id) ? $request->milestone_id : 0;
                $post['assign_to'] = implode(",", $request->assign_to);
                $task              = Task::where('workspace',$currentWorkspace)->where('project_id',$projectID)->where('id',$taskID)->first();
                $task->update($post);

                return response()->json(['status' => 1, 'message' => 'Task Updated Successfully.'] , 200);
            } else {
                return response()->json(['status' => 0, 'message' => 'Project not found!'] , 403);
            }

        }else{

            $validator = \Validator::make(
                $request->all(), [
                    'workspace_id'  => 'required',
                    'project_id'    => 'required',
                    'title'         => 'required',
                    'priority'      => 'required|in:Low,Medium,High',
                    'start_date'    => 'date_format:Y-m-d',
                    'due_date'      => 'date_format:Y-m-d',
                    'assign_to'     => 'required',
                ]
            );

            if($validator->fails())
            {
                $messages = $validator->getMessageBag();
                return response()->json(['status' => 0, 'message' => $messages->first()] , 403);
            }

			 $objUser            = Auth::user();
            $projectID          = $request->project_id;

            $currentWorkspace   = $request->workspace_id;

			if ($objUser->hasRole('client')) {
                $project = Project::where('projects.workspace', '=', $currentWorkspace)->where('projects.id', '=', $projectID)->first();
            } else {
                $project = Project::select('projects.*')->join('user_projects', 'user_projects.project_id', '=', 'projects.id')->where('user_projects.user_id', '=', $objUser->id)->where('projects.workspace', '=', $currentWorkspace)->where('projects.id', '=', $projectID)->first();
            }

            if($request->assign_to){
                $ids = $request->assign_to;
                foreach ($ids as $id) {
                    if (!UserProject::where('project_id',$projectID)->where('user_id', $id)->exists()) {
                        return response()->json(['status' => 0, 'message' => 'User is not assigned to the project ' . $project->name . '. '] , 403);
                    }
                }
            }

            $objUser            = Auth::user();
            $projectID          = $request->project_id;
            $currentWorkspace   = $request->workspace_id;

            $post = $request->all();

            $stage = Stage::orderBy('order')->first();

            if ($stage) {

                $post['milestone_id']   = !empty($request->milestone_id) ? $request->milestone_id : 0;
                $post['status']         = $stage->id;
                $post['assign_to']      = implode(",", $request->assign_to) ;
                $post['workspace']      = $currentWorkspace;
                $task                   = Task::create($post);

                ActivityLog::create(
                    [
                        'user_id'       => $objUser->id,
                        'user_type'     => get_class($objUser),
                        'project_id'    => $projectID,
                        'log_type'      => 'Create Task',
                        'remark'        => json_encode(['title' => $task->title]),
                    ]
                );

                return response()->json(['status' => 1  , 'message' => 'Task Created Successfully!'], 200);
            } else {
                return response()->json(['status' => 0 , 'message' => 'Please add stages first.'], 200);
            }
        }
    }

    /**
     * Update task stage
     *
     * Move a task to a different stage/status.
     *
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam project_id integer required Project ID. Example: 5
     * @bodyParam task_id integer required Task ID. Example: 10
     * @bodyParam new_status integer required New stage ID. Example: 3
     * @response {"status": 1, "message": "Task stage update successfully."}
     */
    public function taskStageUpdate(Request $request)
    {
        $validator = \Validator::make(
            $request->all(), [
                'workspace_id'  => 'required',
                'project_id'    => 'required',
                'task_id'       => 'required',
                'new_status'    => 'required',
                // 'old_status'    => 'required',
            ]
        );

        if($validator->fails())
        {
            $messages = $validator->getMessageBag();
            return response()->json(['status' => 0, 'message' => $messages->first()] , 403);
        }

        try{

            $objUser            = Auth::user();
            $currentWorkspace   = $request->workspace_id;
            $projectID          = $request->project_id;
            $taskID             = $request->task_id;

            $task         = Task::where('workspace',$currentWorkspace)->where('project_id',$projectID)->where('id',$taskID)->first();

            if ($request->new_status != $task->status) {

                $new_status   = Stage::where('id',$request->new_status)->first();
                $old_status   = Stage::where('id',$task->status)->first();
                $task->status = $request->new_status;
                $task->save();

                ActivityLog::create(
                    [
                        'user_id' => $objUser->id,
                        'user_type' => get_class($objUser),
                        'project_id' => $projectID,
                        'log_type' => 'Move',
                        'remark' => json_encode(
                            [
                                'title'      => $task->title,
                                'old_status' => $old_status->name,
                                'new_status' => $new_status->name,
                            ]
                        ),
                    ]
                );
            }

            return response()->json(['status' => 1 ,'message' => 'Task stage update successfully.']);

        } catch (\Exception $e) {
            return response()->json(['status' => 0 ,'message' => 'something went wrong!!!']);
        }
    }

    /**
     * Delete task
     *
     * Delete a task and its associated comments, subtasks, and files.
     *
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam project_id integer required Project ID. Example: 5
     * @bodyParam task_id integer required Task ID. Example: 10
     * @response {"status": 1, "message": "Task deleted successfully."}
     */
	public function taskDelete(Request $request)
    {

        $objUser = Auth::user();
        $task              = Task::where('workspace', '=', $request->workspace_id)->where('project_id',$request->project_id)->where('id',$request->task_id)->first();
        if(!$task){
            return response()->json(['status'=>0,'message'=>'Task Not Found!']);
        }
        Comment::where('task_id', '=', $task->id)->delete();
        SubTask::where('task_id', '=', $task->id)->delete();
        $TaskFiles = TaskFile::where('task_id', '=', $task->id)->get();

        foreach($TaskFiles as $TaskFile){
            delete_file($TaskFile->file);
            $TaskFile->delete();
        }
        $task->delete();
        return response()->json(['status'=>1, 'message' => 'Task Deleted Successfully!']);

    }

}
