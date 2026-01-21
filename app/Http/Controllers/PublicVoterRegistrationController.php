<?php

namespace App\Http\Controllers;

use App\Enums\VoterStatus;
use App\Models\Municipality;
use App\Models\Voter;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PublicVoterRegistrationController extends Controller
{
    public function __construct(private InvitationService $invitationService)
    {
    }

    public function show(string $token)
    {
        $invitation = $this->invitationService->validateInvitation($token);

        if (! $invitation) {
            return redirect()
                ->route('home')
                ->with('error', 'El enlace de registro no es válido o ya expiró.');
        }

        if (! $this->invitationService->hasRegistrationAssignee($invitation)) {
            return redirect()
                ->route('home')
                ->with('error', 'Este enlace de registro no tiene un líder asignado.');
        }

        return view('public.voter-registration', [
            'invitation' => $invitation->loadMissing(['leader', 'coordinator', 'campaign', 'municipality']),
            'token' => $token,
            'municipalities' => Municipality::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $token)
    {
        $invitation = $this->invitationService->validateInvitation($token);

        if (! $invitation) {
            throw ValidationException::withMessages([
                'document_number' => 'El enlace de registro no es válido o ya expiró.',
            ]);
        }

        if (! $this->invitationService->hasRegistrationAssignee($invitation)) {
            throw ValidationException::withMessages([
                'document_number' => 'Este enlace de registro no tiene un líder asignado.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'document_number' => ['required', 'digits:10', 'unique:voters,document_number'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'digits:10'],
            'secondary_phone' => ['nullable', 'digits:10'],
            'email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:500'],
            'municipality_id' => ['required', 'exists:municipalities,id'],
        ], [
            'document_number.unique' => 'Este número de documento ya está registrado.',
            'birth_date.before' => 'La fecha de nacimiento debe ser una fecha pasada.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $registeredByUserId = $this->invitationService->getRegistrationAssigneeUserId($invitation);

            Voter::create([
                'campaign_id' => $invitation->campaign_id,
                'document_number' => $request->document_number,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'birth_date' => $request->birth_date,
                'phone' => $request->phone,
                'secondary_phone' => $request->secondary_phone,
                'email' => $request->email,
                'municipality_id' => $invitation->municipality_id ?? $request->municipality_id,
                'address' => $request->address,
                'registered_by' => $registeredByUserId,
                'status' => VoterStatus::PENDING_REVIEW,
                'notes' => $invitation->notes,
            ]);

            DB::commit();

            return redirect()
                ->route('home')
                ->with('success', 'Tu registro fue enviado correctamente. Si necesitas registrar otro votante, vuelve a abrir el enlace.');
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error en registro público de votantes', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'document_number' => 'Ocurrió un error al procesar tu registro. Por favor, inténtalo de nuevo.',
            ]);
        }
    }
}
