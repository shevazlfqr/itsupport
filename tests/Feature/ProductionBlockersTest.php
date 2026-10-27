<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionBlockersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('ticket_attachments');
    }

    public function test_uploaded_filename_payloads_are_escaped_and_new_files_are_private(): void
    {
        $requester = $this->account();
        $this->login($requester);

        $filenames = [
            '"><img src=x onerror=alert(1)>.png',
            '<svg onload=alert(1)>.png',
            'screenshot (final) é.png',
        ];
        $files = array_map(fn (string $name) => UploadedFile::fake()->image($name), $filenames);

        $this->post(route('tickets.store'), [
            'subject' => 'Printer cannot connect',
            'description' => 'The shared printer no longer connects from my laptop.',
            'category' => 'Perangkat',
            'priority' => 'medium',
            'screenshots' => $files,
        ])->assertRedirect();

        $ticket = Ticket::query()->firstOrFail();
        $attachments = $ticket->attachments()->orderBy('id')->get();
        $this->assertCount(count($filenames), $attachments);

        foreach ($attachments as $index => $attachment) {
            $this->assertSame('ticket_attachments', $attachment->disk);
            $this->assertSame($filenames[$index], $attachment->original_name);
            $this->assertTrue(Storage::disk('ticket_attachments')->exists('uploads/'.$attachment->stored_name));
            $this->assertFalse(Storage::disk('public')->exists('uploads/'.$attachment->stored_name));
        }

        $response = $this->get(route('dashboard', ['ticket' => $ticket->id]))->assertOk();
        foreach ($filenames as $filename) {
            $response->assertSee(e($filename), false);
        }
        $response->assertDontSee('storage/uploads/', false);
        $response->assertSee(route('tickets.attachments.show', [
            'ticket' => $ticket->id,
            'attachment' => $attachments->first()->id,
            'download' => 1,
        ]), false);
    }

    public function test_upload_rejects_oversized_and_disallowed_files_without_creating_a_ticket(): void
    {
        $requester = $this->account();
        $payload = [
            'subject' => 'Printer cannot connect',
            'description' => 'The shared printer no longer connects from my laptop.',
            'category' => 'Perangkat',
            'priority' => 'medium',
        ];

        $this->actingAs($requester)->post(route('tickets.store'), $payload + [
            'screenshots' => [UploadedFile::fake()->image('oversized.png')->size(10241)],
        ])->assertSessionHasErrors('screenshots.0');

        $this->post(route('tickets.store'), $payload + [
            'screenshots' => [UploadedFile::fake()->create('report.txt', 1, 'text/plain')],
        ])->assertSessionHasErrors('screenshots.0');

        Auth::logout();
        $this->post(route('tickets.store'), $payload + [
            'screenshots' => [UploadedFile::fake()->image('screenshot.png')],
        ])->assertRedirect('/login');

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], Storage::disk('ticket_attachments')->allFiles('uploads'));
    }

    public function test_uploaded_file_is_removed_if_activity_write_rolls_back_ticket_creation(): void
    {
        $requester = $this->account();
        $failActivity = true;
        Activity::creating(function () use (&$failActivity): void {
            if ($failActivity) {
                throw new \RuntimeException('Test activity write failure');
            }
        });

        try {
            app(TicketService::class)->create($requester, [
                'subject' => 'Printer cannot connect',
                'description' => 'The shared printer no longer connects from my laptop.',
                'category' => 'Perangkat',
                'priority' => 'medium',
            ], [UploadedFile::fake()->image('screenshot.png')]);
            $this->fail('The injected activity failure must abort ticket creation.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Test activity write failure', $error->getMessage());
        } finally {
            $failActivity = false;
        }

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], Storage::disk('ticket_attachments')->allFiles('uploads'));
    }

    public function test_attachment_download_checks_ticket_access_for_owner_it_admin_and_guest(): void
    {
        $owner = $this->account('employee');
        $other = $this->account('employee');
        $technician = $this->account('it');
        $administrator = $this->account('admin');
        $ticket = $this->ticket($owner);
        $attachment = $this->attachment($ticket, $owner);
        $url = route('tickets.attachments.show', ['ticket' => $ticket->id, 'attachment' => $attachment->id, 'download' => 1]);

        $this->get($url)->assertRedirect('/login');

        $ownerResponse = $this->actingAs($owner)->get($url)->assertOk();
        $this->assertStringContainsString('attachment', (string) $ownerResponse->headers->get('content-disposition'));
        $cacheControl = (string) $ownerResponse->headers->get('cache-control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertSame('private attachment bytes', $ownerResponse->streamedContent());

        $this->actingAs($other)->get($url)->assertForbidden();

        foreach ([$technician, $administrator] as $privilegedUser) {
            $response = $this->actingAs($privilegedUser)->get($url)->assertOk();
            $this->assertSame('private attachment bytes', $response->streamedContent());
        }
    }

    public function test_attachment_preview_is_inline_only_for_supported_image_types(): void
    {
        $owner = $this->account();
        $ticket = $this->ticket($owner);
        $attachment = $this->attachment($ticket, $owner, 'ticket_attachments', 'image/png');
        $url = route('tickets.attachments.show', [
            'ticket' => $ticket->id,
            'attachment' => $attachment->id,
            'preview' => 1,
        ]);

        $preview = $this->actingAs($owner)->get($url)->assertOk();
        $this->assertStringContainsString('inline', (string) $preview->headers->get('content-disposition'));
        $preview->assertHeader('x-content-type-options', 'nosniff');

        $heic = $this->attachment($ticket, $owner, 'ticket_attachments', 'image/heic');
        $download = $this->get(route('tickets.attachments.show', [
            'ticket' => $ticket->id,
            'attachment' => $heic->id,
            'preview' => 1,
        ]))->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));
    }

    public function test_legacy_public_attachment_stays_available_only_through_authorized_route(): void
    {
        $owner = $this->account();
        $other = $this->account();
        $ticket = $this->ticket($owner, 'closed');
        $attachment = $this->attachment($ticket, $owner, 'public');
        $url = route('tickets.attachments.show', ['ticket' => $ticket->id, 'attachment' => $attachment->id]);

        $this->actingAs($owner)->get($url)->assertOk();
        $this->actingAs($other)->get($url)->assertForbidden();
    }

    public function test_apache_upload_directory_contains_a_direct_access_deny_rule(): void
    {
        $rules = file_get_contents(base_path('storage/app/public/uploads/.htaccess'));

        $this->assertIsString($rules);
        $this->assertStringContainsString('Require all denied', $rules);
    }

    public function test_attachment_route_returns_not_found_for_missing_file_or_ticket(): void
    {
        $owner = $this->account();
        $ticket = $this->ticket($owner);
        $attachment = $this->attachment($ticket, $owner);
        $url = route('tickets.attachments.show', ['ticket' => $ticket->id, 'attachment' => $attachment->id]);

        Storage::disk('ticket_attachments')->delete('uploads/'.$attachment->stored_name);
        $this->actingAs($owner)->get($url)->assertNotFound();

        $attachment = $this->attachment($ticket, $owner);
        $ticketId = $ticket->id;
        $attachmentId = $attachment->id;
        $ticket->delete();

        $this->get(route('tickets.attachments.show', ['ticket' => $ticketId, 'attachment' => $attachmentId]))->assertNotFound();
    }

    public function test_attachment_route_rejects_mismatched_ticket_and_invalid_storage_path(): void
    {
        $owner = $this->account();
        $ticket = $this->ticket($owner);
        $attachment = $this->attachment($ticket, $owner);
        $otherTicket = $this->ticket($owner);

        $this->actingAs($owner)->get(route('tickets.attachments.show', [
            'ticket' => $otherTicket->id,
            'attachment' => $attachment->id,
        ]))->assertNotFound();

        $attachment->update(['stored_name' => '..']);
        $this->get(route('tickets.attachments.show', [
            'ticket' => $ticket->id,
            'attachment' => $attachment->id,
        ]))->assertNotFound();
    }

    public function test_account_deactivation_invalidates_only_that_accounts_existing_sessions(): void
    {
        $target = $this->account();
        $unrelated = $this->account();
        $this->login($target);
        $this->get('/')->assertOk();

        app(UserService::class)->toggle($target);
        Auth::forgetGuards();
        $this->get('/')->assertRedirect('/login');

        app(UserService::class)->toggle($target->fresh());
        Auth::forgetGuards();
        $this->get('/')->assertRedirect('/login');

        $this->actingAs($unrelated)->withSession(['auth.session_version' => 0])->get('/')->assertOk();

        $this->post(route('login.store'), ['email' => $target->email, 'password' => 'password'])->assertRedirect();
        $this->get('/')->assertOk();
    }

    public function test_disabled_user_and_authenticated_context_without_current_version_are_rejected(): void
    {
        $disabled = $this->account(active: false);
        $disabled->session_version = 1;
        $disabled->save();

        $this->actingAs($disabled)->get('/')->assertRedirect('/login');

        $activeAfterReactivation = $this->account();
        $activeAfterReactivation->session_version = 2;
        $activeAfterReactivation->save();

        $this->actingAs($activeAfterReactivation)->get('/')->assertRedirect('/login');

        $staleSession = $this->account();
        $staleSession->session_version = 2;
        $staleSession->save();
        $this->actingAs($staleSession)->withSession(['auth.session_version' => 0])->get('/')->assertRedirect('/login');
    }

    public function test_admin_deactivation_route_increments_session_version(): void
    {
        $administrator = $this->account('admin');
        $target = $this->account();

        $this->actingAs($administrator)
            ->post(route('users.toggle', $target))
            ->assertRedirect();

        $this->assertFalse($target->fresh()->active);
        $this->assertSame(1, $target->fresh()->session_version);
    }

    private function account(string $role = 'employee', bool $active = true): User
    {
        return User::query()->create([
            'name' => 'Test '.ucfirst($role),
            'email' => 'user-'.Str::uuid().'@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'department' => 'IT',
            'job_title' => 'Staff',
            'active' => $active,
        ]);
    }

    private function login(User $user): void
    {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();
    }

    private function ticket(User $requester, string $status = 'new'): Ticket
    {
        return Ticket::query()->create([
            'subject' => 'Printer issue',
            'description' => 'The shared printer is unavailable.',
            'category' => 'Perangkat',
            'priority' => 'medium',
            'status' => $status,
            'requester_id' => $requester->id,
            'assignee_id' => null,
            'resolution_note' => '',
            'created_at' => now(),
            'updated_at' => now(),
            'sla_deadline' => now()->addDay(),
        ]);
    }

    private function attachment(Ticket $ticket, User $owner, string $disk = 'ticket_attachments', string $mime = 'image/png'): Attachment
    {
        $storedName = Str::random(32).'.png';
        Storage::disk($disk)->put('uploads/'.$storedName, 'private attachment bytes');

        return $ticket->attachments()->create([
            'user_id' => $owner->id,
            'disk' => $disk,
            'stored_name' => $storedName,
            'original_name' => 'screenshot.png',
            'mime' => $mime,
            'size' => 24,
            'created_at' => now(),
        ]);
    }
}
