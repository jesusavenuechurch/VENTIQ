<?php

use App\Jobs\GenerateEventContent;
use App\Models\{AiGenerationResult, Organization, User};
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
});

it('starts writing in the background, and shows it once done', function () {
    $start = $this->actingAs($this->user)->postJson(route('assist.event-content.store'), [
        'name' => 'Youth Prayer Night', 'category' => 'Church / Spiritual', 'tone' => 'professional', 'venue' => 'Avani',
    ])->assertOk()->assertJsonPath('result.status', 'pending');

    $jobId = $start->json('result.jobId');
    Queue::assertPushed(GenerateEventContent::class, fn ($job) => $job->jobId === $jobId
        && $job->promptData['audience'] === 'General public' && $job->promptData['date'] === 'Date to be confirmed');

    AiGenerationResult::where('job_id', $jobId)->update([
        'status'   => 'completed',
        'sections' => json_encode(['description' => 'Come and pray.', 'tagline' => 'One night', 'titles' => ['Night of Prayer']]),
    ]);

    $this->getJson(route('assist.event-content.show', $jobId))
        ->assertJsonPath('result.status', 'completed')
        ->assertJsonPath('result.content.tagline', 'One night')
        ->assertJsonPath('result.content.titles', ['Night of Prayer']);

    // Picked up again when the box is reopened.
    $this->getJson(route('assist.event-content.latest'))->assertJsonPath('result.jobId', $jobId)
        ->assertJsonPath('result.input.name', 'Youth Prayer Night');
});

it('asks for the name and category', function () {
    $this->actingAs($this->user)->postJson(route('assist.event-content.store'), ['name' => '', 'category' => '', 'tone' => 'professional'])
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'category']);
    Queue::assertNothingPushed();
});

it('gives up on writing nobody picked up, and keeps it to its owner', function () {
    $result = AiGenerationResult::create(['job_id' => 'job-1', 'user_id' => $this->user->id, 'type' => 'event_description', 'status' => 'pending', 'payload' => '{}']);
    $result->forceFill(['created_at' => now()->subMinutes(5)])->save();

    $other = User::factory()->create(['organization_id' => $this->user->organization_id]);
    $this->actingAs($other)->getJson(route('assist.event-content.show', 'job-1'))->assertNotFound();

    $this->actingAs($this->user)->getJson(route('assist.event-content.show', 'job-1'))
        ->assertJsonPath('result.status', 'failed')
        ->assertJsonPath('result.error', 'That took longer than expected. Please try again.');
});
