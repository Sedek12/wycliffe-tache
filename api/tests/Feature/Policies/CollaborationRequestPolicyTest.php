<?php

namespace Tests\Feature\Policies;

use App\Models\CollaborationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestData;
use Tests\TestCase;

class CollaborationRequestPolicyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    private function makeRequest(): array
    {
        $fromDept = $this->makeDepartment();
        $toDept = $this->makeDepartment();
        $fromChef = $this->makeChef($fromDept);
        $toChef = $this->makeChef($toDept);
        $targetUser = $this->makeEmploye($toDept);
        $task = $this->makeTask($fromDept, $fromChef);

        $request = CollaborationRequest::create([
            'task_id' => $task->id,
            'requested_by' => $fromChef->id,
            'target_user_id' => $targetUser->id,
            'from_department_id' => $fromDept->id,
            'to_department_id' => $toDept->id,
            'status' => 'en_attente',
        ]);

        return compact('request', 'fromChef', 'toChef');
    }

    public function test_only_the_chef_of_the_target_department_can_respond(): void
    {
        ['request' => $request, 'fromChef' => $fromChef, 'toChef' => $toChef] = $this->makeRequest();

        $this->assertFalse($fromChef->can('respond', $request));
        $this->assertTrue($toChef->can('respond', $request));
    }

    public function test_a_request_already_answered_can_no_longer_be_responded_to(): void
    {
        ['request' => $request, 'toChef' => $toChef] = $this->makeRequest();
        $request->update(['status' => 'acceptee']);

        $this->assertFalse($toChef->can('respond', $request));
    }

    public function test_only_the_requester_can_cancel_a_pending_request(): void
    {
        ['request' => $request, 'fromChef' => $fromChef, 'toChef' => $toChef] = $this->makeRequest();

        $this->assertTrue($fromChef->can('cancel', $request));
        $this->assertFalse($toChef->can('cancel', $request));
    }
}
