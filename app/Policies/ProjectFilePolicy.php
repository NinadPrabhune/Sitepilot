<?php

namespace App\Policies;

use App\Models\ProjectFileNew;
use App\Models\User;
use Workdo\Taskly\Entities\UserProject;
use Illuminate\Auth\Access\Response;

class ProjectFilePolicy
{
    /**
     * Determine if user can view any project files
     */
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    /**
     * Determine if user can view this file
     */
    public function view(User $user, ProjectFileNew $projectFile): bool
    {
        return $this->userHasProjectAccess($user, $projectFile->project_id);
    }

    /**
     * Determine if user can create a file
     */
    public function create(User $user): bool
    {
        return $user !== null;
    }

    /**
     * Determine if user can upload file to project
     */
    public function upload(User $user, $projectId): bool
    {
        return $this->userHasProjectAccess($user, $projectId);
    }

    /**
     * Determine if user can download a file
     */
    public function download(User $user, ProjectFileNew $projectFile): bool
    {
        // Check if file is public or user has access
        if ($projectFile->is_public) {
            return true;
        }

        return $this->userHasProjectAccess($user, $projectFile->project_id);
    }

    /**
     * Determine if user can update file
     */
    public function update(User $user, ProjectFileNew $projectFile): bool
    {
        // Only original uploader or project admin can update
        return $this->userCanEditFile($user, $projectFile);
    }

    /**
     * Determine if user can delete file
     */
    public function delete(User $user, ProjectFileNew $projectFile): bool
    {
        // Only original uploader or project admin can delete
        return $this->userCanEditFile($user, $projectFile);
    }

    /**
     * Determine if user can restore file
     */
    public function restore(User $user, ProjectFileNew $projectFile): bool
    {
        return $this->userCanEditFile($user, $projectFile);
    }

    /**
     * Determine if user can permanently delete file
     */
    public function forceDelete(User $user, ProjectFileNew $projectFile): bool
    {
        // Only original uploader or project admin
        return $this->userCanEditFile($user, $projectFile);
    }

    /**
     * Determine if user can create folder
     */
    public function createFolder(User $user, $projectId): bool
    {
        return $this->userHasProjectAccess($user, $projectId);
    }

    /**
     * Determine if user can rename file
     */
    public function rename(User $user, ProjectFileNew $projectFile): bool
    {
        return $this->userCanEditFile($user, $projectFile);
    }

    /**
     * Determine if user can move file
     */
    public function move(User $user, ProjectFileNew $projectFile): bool
    {
        return $this->userCanEditFile($user, $projectFile);
    }

    /**
     * Determine if user can make file public
     */
    public function makePublic(User $user, ProjectFileNew $projectFile): bool
    {
        // Only original uploader or project admin
        return $this->userCanEditFile($user, $projectFile);
    }

    /**
     * Determine if user can archive file
     */
    public function archive(User $user, ProjectFileNew $projectFile): bool
    {
        return $this->userCanEditFile($user, $projectFile);
    }

    /**
     * Check if user has access to project
     */
    private function userHasProjectAccess(User $user, $projectId): bool
    {
        // Super admin has access to all projects
        if ($user->type === 'super admin') {
            return true;
        }

        // Check if user is part of the project
        return UserProject::where('user_id', $user->id)
            ->where('project_id', $projectId)
            ->exists();
    }

    /**
     * Check if user can edit file (is uploader or project admin)
     */
    private function userCanEditFile(User $user, ProjectFileNew $projectFile): bool
    {
        // Must have project access first
        if (!$this->userHasProjectAccess($user, $projectFile->project_id)) {
            return false;
        }

        // Original uploader can always edit
        if ($projectFile->user_id === $user->id) {
            return true;
        }

        // Super admin can edit
        if ($user->type === 'super admin') {
            return true;
        }

        // TODO: Add project role check (e.g., project manager/admin role)
        // For now only uploader or super admin can edit
        return false;
    }
}
