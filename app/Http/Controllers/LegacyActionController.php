<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommentRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LegacyActionController extends Controller
{
    public function __construct(
        private readonly AuthController $auth,
        private readonly TicketController $tickets,
        private readonly UserController $users,
    ) {}

    public function __invoke(Request $request)
    {
        abort_unless($request->isMethod('post'), 405, 'Legacy actions require POST.');

        $action = $request->string('action')->toString();

        if ($action === 'login') {
            return $this->auth->store($this->form($request, LoginRequest::class));
        }
        if ($action === 'logout') {
            return $this->auth->destroy();
        }
        if (! Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
        }

        return match ($action) {
            'create_user' => $this->users->store($this->form($request, StoreUserRequest::class)),
            'update_user' => $this->users->update($this->form($request, UpdateUserRequest::class), User::findOrFail($request->integer('user_id'))),
            'toggle_user' => $this->users->toggle($request, User::findOrFail($request->integer('user_id'))),
            'create_ticket' => $this->tickets->store($this->form($request, StoreTicketRequest::class)),
            'comment' => $this->tickets->comment($this->form($request, CommentRequest::class), Ticket::findOrFail($request->integer('ticket_id'))),
            'claim_ticket' => $this->tickets->claim($request, Ticket::findOrFail($request->integer('ticket_id'))),
            'update_ticket' => $this->tickets->update($this->form($request, UpdateTicketRequest::class), Ticket::findOrFail($request->integer('ticket_id'))),
            'requester_resolution' => $this->tickets->resolution($request, Ticket::findOrFail($request->integer('ticket_id'))),
            default => redirect()->route('dashboard'),
        };
    }

    private function form(Request $request, string $class): FormRequest
    {
        /** @var FormRequest $form */
        $form = $class::createFrom($request);
        $form->setContainer(app())->setRedirector(app('redirect'));
        $form->setUserResolver($request->getUserResolver());
        $form->setRouteResolver($request->getRouteResolver());
        $form->validateResolved();

        return $form;
    }
}
