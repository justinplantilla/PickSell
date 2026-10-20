<?php

namespace App\Pickrush\Http\Controllers\Hq;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Http\Controllers\PortalController;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\Hq\StaffAdministration;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/** Staff accounts, roles and hub scope (super admin only). */
class StaffController extends PortalController
{
    public function index(Request $request)
    {
        $role = StaffRole::tryFrom((string) $request->query('role'));
        $q = trim((string) $request->query('q', ''));

        return view('pickrush.hq.staff.index', [
            'staff' => Staff::with('hub')
                ->when($role, fn ($query) => $query->where('role', $role))
                ->when($request->query('samples') !== '1', fn ($query) => $query->where('is_sample', false))
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('full_name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")
                    ->orWhereHas('hub', fn ($h) => $h->where('name', 'like', "%{$q}%"))))
                ->orderBy('full_name')->paginate(50)->withQueryString(),
            'roles' => StaffRole::cases(),
            'filters' => ['role' => $role?->value, 'q' => $q, 'samples' => $request->query('samples') === '1'],
        ]);
    }

    public function create(Request $request)
    {
        $staff = new Staff(['is_active' => true, 'hub_id' => $request->integer('hub_id') ?: null, 'role' => StaffRole::HubManager]);

        return view('pickrush.hq.staff.form', ['member' => $staff, 'roles' => StaffRole::cases(), 'hub' => $staff->hub_id ? Hub::find($staff->hub_id) : null]);
    }

    public function edit(Staff $staff)
    {
        return view('pickrush.hq.staff.form', ['member' => $staff, 'roles' => StaffRole::cases(), 'hub' => $staff->hub]);
    }

    public function store(Request $request, StaffAdministration $admin)
    {
        return $this->save($request, $admin, null);
    }

    public function update(Request $request, StaffAdministration $admin, Staff $staff)
    {
        return $this->save($request, $admin, $staff);
    }

    private function save(Request $request, StaffAdministration $admin, ?Staff $staff)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:120',
            'email' => ['required', 'email', Rule::unique('pr_staff', 'email')->ignore($staff?->id)],
            'mobile_no' => ['nullable', 'regex:/^09\d{9}$/'],
            'role' => ['required', new Enum(StaffRole::class)],
            'hub_code' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
            'password' => [$staff ? 'nullable' : 'required', 'string', 'min:10'],
        ]);
        $data['hub_id'] = filled($data['hub_code'] ?? null) ? Hub::where('code', strtoupper(trim($data['hub_code'])))->value('id') : null;
        if (filled($data['hub_code'] ?? null) && ! $data['hub_id']) {
            return back()->withErrors(['hub_code' => 'No hub has that code.'])->withInput();
        }
        $data['is_active'] = $request->boolean('is_active');

        try {
            $member = $admin->save($staff, $data, $this->staff());
        } catch (DomainException $e) {
            return back()->withErrors(['operation' => $e->getMessage()])->withInput();
        }

        return redirect()->route('pickrush.hq.staff.index')->with('success', "{$member->full_name} saved.");
    }
}
