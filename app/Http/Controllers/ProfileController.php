<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\UpdateApprovalPinRequest;
use App\Services\Corrections\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Name, username and role are managed by the owner; users may only change their own password (and approvers their PIN) here.
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function updateApprovalPin(UpdateApprovalPinRequest $request, ApprovalService $approvals): RedirectResponse
    {
        $approvals->setPin($request->user(), $request->validated('approval_pin'));

        return redirect()->route('profile.edit')->with('status', 'approval-pin-updated');
    }
}
