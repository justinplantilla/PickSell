@extends('admin.layout')
@vite('resources/css/views/admin-application-detail.css')
@section('title', 'Review Application')

@section('content')
<div class="blade-inline-1">
    <a href="/admin/registrations" class="btn btn-outline btn-sm">← Back to Registrations</a>
</div>

<div class="grid-2">
    <!-- Applicant Info -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Applicant Information</span>
            <span class="badge badge-{{ $user->role }}">{{ ucfirst($user->role) }}</span>
        </div>
        <div class="card-body">
            @php
            $fields = [
                'Full Name'    => $user->full_name,
                'Sex'          => $user->sex,
                'Birthday'     => $user->birthday->format('F d, Y'),
                'Age'          => $user->age . ' years old',
                'Email'        => $user->email,
                'Contact No.'  => $user->contact_no,
                'Province'     => $user->province,
                'Municipality' => $user->municipality,
                'Barangay'     => $user->barangay,
                'Street'       => $user->street ?? '—',
                'House No.'    => $user->house_no ?? '—',
                'Applied'      => $user->created_at->format('M d, Y h:i A'),
            ];
            if ($user->role === 'seller') {
                $fields['Business Name']    = $user->business_name;
                $fields['Line of Business'] = $user->line_of_business;
            }
            if ($user->role === 'courier') {
                $fields['Vehicle Type'] = $user->vehicle_type;
                $fields['Plate Number'] = $user->plate_number;
                $fields['Delivery Area'] = $user->delivery_area ?? '—';
            }
            @endphp
            <table class="blade-inline-2">
                @foreach($fields as $label => $value)
                <tr>
                    <td class="blade-inline-3">{{ $label }}</td>
                    <td class="blade-inline-4">{{ $value }}</td>
                </tr>
                @endforeach
            </table>
        </div>
    </div>

    <!-- Uploaded Documents -->
    <div>
        <div class="card blade-inline-5">
            <div class="card-header"><span class="card-title">Uploaded Documents</span></div>
            <div class="card-body blade-inline-6">
                @if($user->id_upload)
                <div>
                    <div class="blade-inline-7">Government-Issued ID</div>
                    <a href="{{ asset('storage/'.$user->id_upload) }}" target="_blank" class="btn btn-outline btn-sm">📎 View ID</a>
                </div>
                @endif
                @if($user->business_permit)
                <div>
                    <div class="blade-inline-8">Business Permit</div>
                    <a href="{{ asset('storage/'.$user->business_permit) }}" target="_blank" class="btn btn-outline btn-sm">📎 View Permit</a>
                </div>
                @endif
                @if($user->or_cr_upload)
                <div>
                    <div class="blade-inline-9">OR/CR & Driver's License</div>
                    <a href="{{ asset('storage/'.$user->or_cr_upload) }}" target="_blank" class="btn btn-outline btn-sm">📎 View OR/CR</a>
                </div>
                @endif
            </div>
        </div>

        <!-- Decision -->
        @if($user->status === 'pending')
        <div class="card">
            <div class="card-header"><span class="card-title">Decision</span></div>
            <div class="card-body">
                <!-- Approve -->
                <form method="POST" action="/admin/registrations/{{ $user->id }}/approve" class="blade-inline-10">
                    @csrf @method('PATCH')
                    <p class="blade-inline-11">Approving will activate the account and notify the applicant via email.</p>
                    <button type="submit" class="btn btn-success" onclick="return confirm('Approve this application?')">✅ Approve Registration</button>
                </form>
                <hr class="blade-inline-12">
                <!-- Disapprove -->
                <form method="POST" action="/admin/registrations/{{ $user->id }}/disapprove">
                    @csrf @method('PATCH')
                    <div class="form-group">
                        <label class="form-label">Reason for Disapproval <span class="blade-inline-13">*</span></label>
                        <textarea name="reason" class="form-control" placeholder="State the reason..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Disapprove this application?')">❌ Disapprove Registration</button>
                </form>
            </div>
        </div>
        @else
        <div class="card">
            <div class="card-body">
                <p class="blade-inline-14">Status: <span class="badge badge-{{ $user->status }}">{{ ucfirst($user->status) }}</span></p>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
