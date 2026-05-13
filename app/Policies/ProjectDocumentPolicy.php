<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ProjectDocument;

class ProjectDocumentPolicy
{
    /**
     * Determine if the user can view any project documents
     */
    public function viewAny(User $user): bool
    {
        // Any authenticated user can view their own project documents
        return true;
    }

    /**
     * Determine if the user can view a specific document
     */
    public function view(User $user, ProjectDocument $document): bool
    {
        return $this->userHasProjectAccess($user, $document->project_id);
    }

    /**
     * Determine if the user can create a document
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update a document
     */
    public function update(User $user, ProjectDocument $document): bool
    {
        // Company users and admin users have full access
        if ($user->type === 'company' || $user->hasRole('admin')) {
            return true;
        }

        // Only the uploader or super admin can update
        return $user->id === $document->user_id || $user->type === 'super admin';
    }

    /**
     * Determine if the user can delete a document
     */
    public function delete(User $user, ProjectDocument $document): bool
    {
        // Company users and admin users have full access
        if ($user->type === 'company' || $user->hasRole('admin')) {
            return true;
        }

        // Only the uploader or super admin can delete
        return $user->id === $document->user_id || $user->type === 'super admin';
    }

    /**
     * Determine if the user can permanently delete a document
     */
    public function forceDelete(User $user, ProjectDocument $document): bool
    {
        // Company users and admin users have full access
        if ($user->type === 'company' || $user->hasRole('admin')) {
            return true;
        }

        return $user->type === 'super admin';
    }

    /**
     * Determine if the user can restore a deleted document
     */
    public function restore(User $user, ProjectDocument $document): bool
    {
        // Company users and admin users have full access
        if ($user->type === 'company' || $user->hasRole('admin')) {
            return true;
        }

        return $user->type === 'super admin';
    }

    /**
     * Helper method to check if user has access to a project
     */
    private function userHasProjectAccess(User $user, $projectId): bool
    {
        // Super admin has access to everything
        if ($user->type === 'super admin') {
            return true;
        }

        // Company users have full access to all project documents
        if ($user->type === 'company') {
            return true;
        }

        // Users with admin roles have full access
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check if user is assigned to the project
        $hasAccess = \Workdo\Taskly\Entities\UserProject::where('user_id', $user->id)
            ->where('project_id', $projectId)
            ->exists();

        return $hasAccess;
    }
}
